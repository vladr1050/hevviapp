# Быстрый старт: Email уведомления о статусах заказов

## 1. Установка зависимостей

Mailjet уже установлен в composer.json, но если нужно переустановить:

```bash
composer require mailjet/mailjet-apiv3-php
```

## 2. Настройка переменных окружения

Добавьте в `.env.local`:

```bash
# Mailjet Configuration
MAILJET_API_KEY=your_mailjet_api_key_here
MAILJET_API_SECRET=your_mailjet_api_secret_here
MAILJET_SENDER_EMAIL=noreply@yourdomain.com
MAILJET_SENDER_NAME="Hevii Logistics"
MAILJET_ENABLED=true
```

### Получение API ключей Mailjet:

1. Зарегистрируйтесь на https://www.mailjet.com/
2. Перейдите в https://app.mailjet.com/account/apikeys
3. Скопируйте API Key и Secret Key

### Верификация отправителя:

1. Перейдите в https://app.mailjet.com/account/sender
2. Добавьте ваш email или домен
3. Подтвердите через DNS или email

## 3. Очистка кэша

```bash
php bin/console cache:clear
```

## 4. Проверка работы

### Тестирование без реальной отправки

Установите в `.env.local`:
```bash
MAILJET_ENABLED=false
```

Это позволит проверить логику без реальной отправки email.

### Тестирование с реальной отправкой

```bash
# Посмотрите список заказов
php bin/console doctrine:query:sql "SELECT id, status FROM \"order\" LIMIT 10"

# Отправьте тестовое письмо
php bin/console app:test-order-status-email --status=ACCEPTED
```

### Проверка логов

```bash
tail -f var/log/dev.log | grep -i email
```

## 5. Проверка в боевых условиях

1. Откройте админ-панель Sonata
2. Перейдите в раздел "Orders"
3. Выберите заказ
4. Измените статус на один из: ACCEPTED, ASSIGNED, PICKUP_DONE, DELIVERED
5. Сохраните
6. Проверьте, что email отправлен (проверьте логи и почтовый ящик отправителя)

## 6. Настройка текстов email

### Изменение шаблонов

Шаблоны находятся в `templates/email/order_status/`:
- `accepted.html.twig`
- `assigned.html.twig`
- `pickup_done.html.twig`
- `delivered.html.twig`

### Изменение переводов

Переводы находятся в:
- `translations/AppBundle.en.yaml` (английский)
- `translations/AppBundle.ru.yaml` (русский)

Секция: `email.order_status.*`

После изменений очистите кэш:
```bash
php bin/console cache:clear
```

## 7. Troubleshooting

### Email не отправляются

1. ✅ Проверьте `MAILJET_ENABLED=true`
2. ✅ Проверьте правильность API ключей
3. ✅ Убедитесь, что email отправителя верифицирован в Mailjet
4. ✅ Проверьте логи: `var/log/dev.log`

### Неправильная локализация

1. ✅ Проверьте локаль пользователя в базе данных
2. ✅ Убедитесь, что переводы существуют для нужной локали
3. ✅ Очистите кэш: `php bin/console cache:clear`

### Ошибки в шаблонах

1. ✅ Проверьте синтаксис Twig
2. ✅ Убедитесь, что все переменные определены
3. ✅ Проверьте логи для точной информации об ошибке

## 8. Полезные команды

```bash
# Проверка конфигурации
php bin/console debug:container MailjetEmailService

# Проверка переводов
php bin/console debug:translation en AppBundle
php bin/console debug:translation ru AppBundle

# Проверка шаблонов
php bin/console debug:twig email/order_status/accepted.html.twig

# Просмотр логов в реальном времени
tail -f var/log/dev.log
```

## 9. Production настройка

Для production окружения:

1. Убедитесь, что переменные настроены в `.env.prod.local`
2. Включите HTTPS для безопасности
3. Настройте мониторинг логов
4. Рассмотрите использование Symfony Messenger для асинхронной отправки

### Пример для production с Messenger:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            email: '%env(MESSENGER_TRANSPORT_EMAIL_DSN)%'
        routing:
            'App\Message\OrderStatusEmailMessage': email
```

## Готово! 🎉

Система email уведомлений настроена и готова к использованию.

Полная документация: [EMAIL_NOTIFICATIONS.md](./EMAIL_NOTIFICATIONS.md)
