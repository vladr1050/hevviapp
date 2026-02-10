#!/bin/bash

echo "🧪 Тестирование GeoArea API"
echo "======================================"
echo ""

# Цвета для вывода
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# URL сервера (измените если нужно)
BASE_URL="http://localhost"

echo "📊 1. Проверка данных в БД..."
echo "------------------------------"

# Проверка количества стран
COUNTRIES=$(docker-compose exec -T db psql -U postgres -d postgres -t -c "SELECT COUNT(*) FROM geo_area WHERE scope = 1;" 2>/dev/null | xargs)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Стран в БД: ${COUNTRIES}"
else
    echo -e "${RED}✗${NC} Не удалось подключиться к БД"
fi

# Проверка количества городов
CITIES=$(docker-compose exec -T db psql -U postgres -d postgres -t -c "SELECT COUNT(*) FROM geo_area WHERE scope = 2;" 2>/dev/null | xargs)
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓${NC} Городов в БД: ${CITIES}"
else
    echo -e "${RED}✗${NC} Не удалось подключиться к БД"
fi

echo ""
echo "🌐 2. Тестирование API эндпоинтов..."
echo "------------------------------"

# Тест 1: Получение стран
echo -n "Тест 1: GET /api/geo-area/countries ... "
RESPONSE=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/geo-area/countries")
HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
BODY=$(echo "$RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "200" ]; then
    COUNT=$(echo "$BODY" | jq '. | length' 2>/dev/null)
    if [ $? -eq 0 ] && [ "$COUNT" -gt 0 ]; then
        echo -e "${GREEN}✓ OK${NC} (Получено стран: $COUNT)"
        echo "   Пример: $(echo "$BODY" | jq -r '.[0].name' 2>/dev/null)"
        COUNTRY_ISO3=$(echo "$BODY" | jq -r '.[0].countryISO3' 2>/dev/null)
    else
        echo -e "${YELLOW}⚠ WARNING${NC} (HTTP 200, но список пуст)"
    fi
else
    echo -e "${RED}✗ FAILED${NC} (HTTP $HTTP_CODE)"
    echo "   Response: $BODY"
fi

echo ""

# Тест 2: Получение городов (если есть страна)
if [ -n "$COUNTRY_ISO3" ]; then
    echo -n "Тест 2: GET /api/geo-area/cities?countryISO3=$COUNTRY_ISO3 ... "
    RESPONSE=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/geo-area/cities?countryISO3=${COUNTRY_ISO3}")
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "200" ]; then
        COUNT=$(echo "$BODY" | jq '. | length' 2>/dev/null)
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✓ OK${NC} (Получено городов: $COUNT)"
            if [ "$COUNT" -gt 0 ]; then
                echo "   Пример: $(echo "$BODY" | jq -r '.[0].name' 2>/dev/null)"
                CITY_ID=$(echo "$BODY" | jq -r '.[0].id' 2>/dev/null)
            else
                echo -e "   ${YELLOW}⚠ Список городов пуст для $COUNTRY_ISO3${NC}"
            fi
        else
            echo -e "${YELLOW}⚠ WARNING${NC} (Некорректный JSON)"
        fi
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $HTTP_CODE)"
        echo "   Response: $BODY"
    fi
fi

echo ""

# Тест 3: Получение геометрии (если есть город)
if [ -n "$CITY_ID" ]; then
    echo -n "Тест 3: GET /api/geo-area/${CITY_ID}/geometry ... "
    RESPONSE=$(curl -s -w "\n%{http_code}" "${BASE_URL}/api/geo-area/${CITY_ID}/geometry")
    HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
    BODY=$(echo "$RESPONSE" | head -n -1)
    
    if [ "$HTTP_CODE" = "200" ]; then
        HAS_GEOMETRY=$(echo "$BODY" | jq -r '.geometry.type' 2>/dev/null)
        if [ "$HAS_GEOMETRY" = "MultiPolygon" ]; then
            echo -e "${GREEN}✓ OK${NC} (Геометрия загружена)"
            echo "   Город: $(echo "$BODY" | jq -r '.name' 2>/dev/null)"
        else
            echo -e "${YELLOW}⚠ WARNING${NC} (Геометрия отсутствует или некорректна)"
        fi
    else
        echo -e "${RED}✗ FAILED${NC} (HTTP $HTTP_CODE)"
    fi
fi

echo ""
echo "📋 3. Проверка маршрутов Symfony..."
echo "------------------------------"
php bin/console debug:router | grep geo_area

echo ""
echo "======================================"
echo "✅ Тестирование завершено!"
echo ""
echo "💡 Советы по отладке:"
echo "1. Если стран/городов нет в БД, запустите:"
echo "   php bin/console app:parse-geo-areas latvia"
echo ""
echo "2. Проверьте логи в браузере (F12 → Console, Network)"
echo ""
echo "3. Убедитесь что контейнеры запущены:"
echo "   docker-compose ps"
echo ""
echo "4. Очистите кэш Symfony:"
echo "   php bin/console cache:clear"
