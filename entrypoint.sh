#!/bin/bash
set -e

# docker compose run php <command> — выполнить команду без полного bootstrap / php-fpm.
if [ "$#" -gt 0 ]; then
	exec "$@"
fi

echo "🚀 Starting Hevii php-backoffice-service..."
echo "📝 APP_ENV: ${APP_ENV:-not set}"
echo "🐛 APP_DEBUG: ${APP_DEBUG:-not set}"

symfony_ready() {
	[ -f vendor/autoload_runtime.php ]
}

# Проверяем, что необходимые директории существуют
if [ ! -d "var" ]; then
	mkdir -p var/cache var/log
fi

if [ ! -d "public" ]; then
	mkdir -p public
fi

# Меняем права только на папки, где это нужно (если они существуют)
if [ -d "var" ]; then
	chown -R www-data:www-data var
fi

if [ -d "public" ]; then
	chown -R www-data:www-data public
fi

# Проверяем и устанавливаем PostGIS (если еще не установлен)
echo "🗺️  Checking PostGIS extension..."
psql "$DATABASE_URL" -c "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" 2>/dev/null || {
	echo "⚠️  Could not install PostGIS via psql, trying alternative method..."
	if symfony_ready; then
		php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS postgis_topology;" 2>/dev/null || true
	fi
}
echo "✅ PostGIS ready"

# Схему на prod меняют только миграции. doctrine:schema:update --force на каждом старте
# ломает объекты вне ORM (например PostgreSQL SEQUENCE order_number_seq для order_number).
if [ "${APP_ENV:-dev}" = "dev" ] && symfony_ready; then
	echo "🛠️  APP_ENV=dev: doctrine:schema:update --force"
	php bin/console doctrine:schema:update --force || true
else
	echo "ℹ️  APP_ENV=${APP_ENV:-}: пропуск schema:update (используйте doctrine:migrations:migrate)"
fi

# Host bind-mount скрывает vendor и JWT-ключи из образа; rsync ключи не копирует.
ensure_jwt_keypair() {
	if ! symfony_ready; then
		echo "ℹ️  Skipping JWT keypair until composer install (vendor missing)"
		return 0
	fi

	mkdir -p config/jwt

	if [ -z "${JWT_PASSPHRASE:-}" ]; then
		echo "⚠️  JWT_PASSPHRASE is not set — /api/auth/login will fail"
		return 0
	fi

	local needs_generate=0
	if [ ! -s config/jwt/private.pem ] || [ ! -s config/jwt/public.pem ]; then
		echo "🔑 JWT key files are missing"
		needs_generate=1
	elif ! openssl pkey -in config/jwt/private.pem -passin env:JWT_PASSPHRASE -noout 2>/dev/null; then
		echo "🔑 JWT private key does not match JWT_PASSPHRASE"
		needs_generate=1
	fi

	if [ "$needs_generate" = "1" ]; then
		echo "🔑 Generating JWT keypair..."
		if php bin/console lexik:jwt:generate-keypair --no-interaction --overwrite; then
			chown www-data:www-data config/jwt/*.pem 2>/dev/null || true
			echo "✅ JWT keypair ready"
		else
			echo "⚠️  JWT keypair generation failed — /api/auth/login may be broken until fixed"
		fi
	fi
}

if [ "${APP_ENV:-dev}" != "dev" ]; then
	ensure_jwt_keypair || echo "⚠️  JWT keypair check failed — continuing startup"
fi

# Загрузка GeoArea дампов (если существуют)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
GEO_AREAS_DUMP_DIR="${GEO_AREAS_DUMP_DIR:-$SCRIPT_DIR/docker/dumps/geo_areas}"
GEO_AREAS_DUMP_PATTERN="geo_areas_dump_*_*.sql"

DUMP_FILES=$(find "$GEO_AREAS_DUMP_DIR" -maxdepth 1 -name "$GEO_AREAS_DUMP_PATTERN" -type f 2>/dev/null | sort)

if [ -n "$DUMP_FILES" ] && symfony_ready; then
	echo "📍 Found GeoArea dump files..."

	EXISTING_COUNT=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM geo_area" --quiet 2>/dev/null | tail -n 1 || echo "0")

	if [ "$EXISTING_COUNT" = "0" ] || [ -z "$EXISTING_COUNT" ]; then
		echo "   Loading geo areas from dump files..."

		FILE_COUNT=0
		for DUMP_FILE in $DUMP_FILES; do
			FILE_COUNT=$((FILE_COUNT + 1))
			FILENAME=$(basename "$DUMP_FILE")
			echo "   📄 Loading part $FILE_COUNT: $FILENAME"

			psql "$DATABASE_URL" -f "$DUMP_FILE" 2>/dev/null || {
				echo "   ⚠️  Failed to load via psql, trying alternative method..."
				php bin/console dbal:run-sql "$(cat $DUMP_FILE)" 2>/dev/null || true
			}
		done

		echo "✅ GeoArea dumps loaded successfully ($FILE_COUNT files)"
	else
		echo "ℹ️  GeoArea table already contains data ($EXISTING_COUNT records), skipping dump load"
	fi
elif [ -n "$DUMP_FILES" ]; then
	echo "ℹ️  GeoArea dumps found but vendor missing — skipping until composer install"
else
	echo "ℹ️  No GeoArea dump files found in $GEO_AREAS_DUMP_DIR, skipping"
fi

exec php-fpm -F
