# Email уведомления о статусах заказов

Система автоматической отправки email уведомлений при смене статусов заказов.

Планируется переход к единому **Notification Service**: правила в БД, Sonata Admin, логи, Symfony Messenger. Ниже — **зафиксированные решения** для новой системы и описание **текущей (legacy)** реализации.

**Статус реализации (MVP):** сущности `NotificationRule` / `NotificationLog`, миграции `Version20260409183000` (правила и логи) и `Version20260409190000` (таблица `messenger_messages` для `doctrine://`), админки Sonata (в т.ч. действие **«Повторить»** в списке журнала), команды `app:notification:seed-rules` (пустая таблица или **`--ensure-missing`** для добавления новых дефолтов), `app:notification:replay` (`--sync`, `--force`, `--invoice-id`), сервис **`NotificationDispatchService`** + **`NotificationRuleProcessor`** (плейсхолдеры, PDF счёта, Mailjet, логи). **Symfony Messenger:** `NotificationEventMessage` → транспорт `async` (`MESSENGER_TRANSPORT_DSN`, по умолчанию `sync://`). Триггеры: **`ORDER_PRICE_CONFIRMED`** — синхронно из `InvoiceIssuingService`; **`ORDER_ASSIGNED_TO_CARRIER`** — `OrderAssignmentSubscriber`; смена статуса заказа в **`OrderHistorySubscriber`**: **`ORDER_STATUS_CHANGED_TO_ACCEPTED`**, **`ORDER_STATUS_CHANGED_TO_ASSIGNED`**, **`ORDER_STATUS_CHANGED_TO_IN_TRANSIT`**, **`ORDER_STATUS_CHANGED_TO_DELIVERED`**. Legacy **`OrderStatusService`** и параллельная отправка через Twig `templates/email/order_status/` **удалены** для этих сценариев.

## Решения по спецификации (Notification Service)

### Legacy

- Сценарии **ACCEPTED**, **ASSIGNED**, **IN_TRANSIT**, **DELIVERED** переведены на правила в БД и **`NotificationDispatchService`**. Старые Twig-шаблоны в `templates/email/order_status/` для этих писем **больше не используются** приложением (могут оставаться в репозитории для справки до удаления).

### Плейсхолдер `{{ORDER_ID}}`

- В шаблонах правил подставляется **публичный номер / референс заказа** — тот идентификатор, который пользователь видит в интерфейсе (тот же смысл, что в кабинете и в данных счёта как ссылка на заказ). **Внутренний UUID по умолчанию в `{{ORDER_ID}}` не подставляется.**
- Если понадобится UUID для поддержки — заводится **отдельный** плейсхолдер (например `{{ORDER_INTERNAL_ID}}`).

### Адреса забора и доставки

- Для загрузки и маршрута используются **полные строки адреса** из заказа (поля вида адреса забора и адреса доставки в сущности), **без** обязательного выделения «только города», если отдельных полей города в модели нет.
- Целевые имена в справочнике плейсхолдеров: **`{{PICKUP_ADDRESS}}`**, **`{{DELIVERY_ADDRESS}}`**. Пример строки маршрута в тексте: `{{PICKUP_ADDRESS}} → {{DELIVERY_ADDRESS}}`.
- Имена вида **`{{PICKUP_CITY}}` / `{{DELIVERY_CITY}}`** в старых примерах или сидах трактуются как **алиасы** на полный адрес забора и полный адрес доставки соответственно, либо в шаблонах **однократно заменяются** на `*_ADDRESS`.

### Статус DELIVERED

- Заказ переводится в **DELIVERED** **администратором в админке (Sonata)**. Уведомление о завершении доставки привязывается к этому изменению статуса заказа.

---

## Архитектура (обзор)

Система построена согласно принципам **SOLID** и использует идиомы **Symfony 8**:

### Компоненты

1. **EmailServiceInterface** (`src/Service/Email/Contract/EmailServiceInterface.php`)
   - Интерфейс для email сервисов (Dependency Inversion Principle)
   - Позволяет легко менять провайдера email без изменения основной логики

2. **MailjetEmailService** (`src/Service/Email/MailjetEmailService.php`)
   - Реализация отправки через Mailjet API
   - Single Responsibility: только отправка email
   - Конфигурируется через DI контейнер

3. **OrderHistorySubscriber** (`src/EventSubscriber/OrderHistorySubscriber.php`)
   - После сохранения истории статуса вызывает **`NotificationDispatchService`** для событий `ORDER_STATUS_CHANGED_TO_*` (см. список ключей в `App\Notification\NotificationEventKey`).

4. **OrderAssignmentSubscriber** — уведомление перевозчику при принятии назначения (`ORDER_ASSIGNED_TO_CARRIER`).

## Настройка

### 1. Настройка Mailjet

1. Зарегистрируйтесь на [Mailjet](https://www.mailjet.com/)
2. Получите API ключи в [Account API Keys](https://app.mailjet.com/account/apikeys)
3. Добавьте переменные окружения в `.env.local`:

```bash
# Mailjet Configuration
MAILJET_API_KEY=your_mailjet_api_key_here
MAILJET_API_SECRET=your_mailjet_api_secret_here
MAILJET_SENDER_EMAIL=noreply@yourdomain.com
MAILJET_SENDER_NAME="Your Company Name"
MAILJET_ENABLED=true
```

### 2. Symfony Messenger (очередь уведомлений)

- В `.env`: по умолчанию `MESSENGER_TRANSPORT_DSN=sync://` — сообщения обрабатываются сразу в том же PHP-процессе.
- Для отложенной обработки задайте, например, `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1`, выполните миграции (в т.ч. `Version20260409190000` для таблицы `messenger_messages` или используйте `php bin/console messenger:setup-transports`), затем запустите воркер: `php bin/console messenger:consume async -vv` (supervisor/systemd на production).

### 3. Верификация отправителя

В Mailjet необходимо верифицировать email отправителя:
1. Перейдите в [Sender Domains & Addresses](https://app.mailjet.com/account/sender)
2. Добавьте домен или конкретный email
3. Подтвердите владение через DNS или email

## Статусы и события уведомлений

По смене статуса заказа (история в `OrderHistorySubscriber`):

1. **ACCEPTED** → `ORDER_STATUS_CHANGED_TO_ACCEPTED`
2. **ASSIGNED** → `ORDER_STATUS_CHANGED_TO_ASSIGNED`
3. **IN_TRANSIT** → `ORDER_STATUS_CHANGED_TO_IN_TRANSIT`
4. **DELIVERED** → `ORDER_STATUS_CHANGED_TO_DELIVERED`

Дополнительно: **назначение перевозчика** (принятие `OrderAssignment`) → `ORDER_ASSIGNED_TO_CARRIER`; **счёт после подтверждения цены** → `ORDER_PRICE_CONFIRMED` (синхронно, с PDF).

## Шаблоны

### Шаблоны писем

Тексты и темы настраиваются в **Sonata → Правила уведомлений** (HTML с плейсхолдерами `{{ORDER_ID}}` и др.). Каталог `templates/email/order_status/` **не используется** для отправки через Notification Service (может сохраняться только как справочный материал).

### Доступные переменные в шаблонах

- `order` - объект Order
  - `order.id` - UUID заказа
  - `order.pickupAddress` - адрес забора
  - `order.dropoutAddress` - адрес доставки
  - `order.notes` - примечания
  - `order.carrier` - перевозчик (для статуса ASSIGNED)
  - `order.cargo` - коллекция грузов

- `sender` - объект User (отправитель заказа)
  - `sender.firstName` - имя
  - `sender.lastName` - фамилия
  - `sender.email` - email
  - `sender.phone` - телефон

- `locale` - текущая локаль пользователя (en/ru)

### Изменение шаблонов

Вы можете изменить любой шаблон в `templates/email/order_status/`.
Все шаблоны используют Twig и поддерживают локализацию через фильтр `trans`.

## Локализация

### Структура переводов

Переводы находятся в файлах:
- `translations/AppBundle.en.yaml` - английский
- `translations/AppBundle.ru.yaml` - русский

### Добавление нового языка

1. Создайте файл `translations/AppBundle.{locale}.yaml`
2. Скопируйте структуру из существующего файла
3. Переведите все ключи `email.order_status.*`

### Ключи переводов

```yaml
email:
    order_status:
        header: "Заголовок письма"
        greeting: "Приветствие"
        order_details: "Детали заказа"
        # ... и так далее
        
        accepted:
            title: "Тема письма для статуса ACCEPTED"
            message: "Основное сообщение"
            details: "Детальная информация"
```

## Логирование

Все действия системы логируются через Monolog:

- **INFO**: Успешная отправка email
- **WARNING**: Отсутствует отправитель у заказа
- **DEBUG**: Статус не требует отправки email
- **ERROR**: Ошибки при отправке или рендеринге шаблона

Пример лога:
```
[2026-02-17 10:30:45] app.INFO: Order status email sent successfully 
{"order_id":"a1b2c3d4-...", "status":3, "recipient":"user@example.com", "locale":"en"}
```

## Тестирование

### Отключение отправки в dev окружении

Установите в `.env.local`:
```bash
MAILJET_ENABLED=false
```

При этом все логи будут записываться, но email не будут отправляться.

### Ручное тестирование

Создайте команду для тестирования:

```php
// src/Command/TestOrderEmailCommand.php
use Symfony\Component\Console\Command\Command;
use App\Service\OrderStatus\OrderStatusService;

class TestOrderEmailCommand extends Command
{
    protected static $defaultName = 'app:test-order-email';
    
    public function __construct(
        private OrderStatusService $orderStatusService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Получите тестовый заказ и отправьте email
        // ...
        return Command::SUCCESS;
    }
}
```

## Расширение системы

### Добавление нового статуса для уведомлений

1. **Добавьте константу в Order entity** (если еще нет)
2. **Создайте шаблон** `templates/email/order_status/your_status.html.twig`
3. **Добавьте переводы** в `translations/AppBundle.{locale}.yaml`
4. **Обновите OrderStatusService**:

```php
private const array EMAIL_NOTIFICATION_STATUSES = [
    Order::STATUS['ACCEPTED'],
    Order::STATUS['ASSIGNED'],
    Order::STATUS['PICKUP_DONE'],
    Order::STATUS['DELIVERED'],
    Order::STATUS['YOUR_NEW_STATUS'], // Добавьте здесь
];

private const array STATUS_TEMPLATE_MAP = [
    // ...
    Order::STATUS['YOUR_NEW_STATUS'] => 'email/order_status/your_status.html.twig',
];

private const array STATUS_SUBJECT_MAP = [
    // ...
    Order::STATUS['YOUR_NEW_STATUS'] => 'email.order_status.your_status.title',
];
```

### Замена email провайдера

Благодаря использованию интерфейса `EmailServiceInterface`, вы можете легко заменить Mailjet на другой сервис:

1. Создайте новый класс, реализующий `EmailServiceInterface`
2. Зарегистрируйте его в `config/services.yaml`:

```yaml
App\Service\Email\Contract\EmailServiceInterface:
    class: App\Service\Email\YourNewEmailService
    arguments:
        # Ваши аргументы
```

### Отправка уведомлений не только отправителю

Измените `OrderStatusService::sendEmailToSender()` для отправки дополнительным получателям:

```php
public function sendEmailToSender(Order $order): void
{
    // Существующий код для отправителя
    // ...
    
    // Дополнительно отправьте перевозчику
    if ($order->getCarrier()) {
        $this->sendToCarrier($order);
    }
}

private function sendToCarrier(Order $order): void
{
    // Логика отправки перевозчику
}
```

## Troubleshooting

### Email не отправляются

1. Проверьте `MAILJET_ENABLED=true` в `.env.local`
2. Проверьте правильность API ключей
3. Убедитесь, что отправитель верифицирован в Mailjet
4. Проверьте логи: `var/log/dev.log` или `var/log/prod.log`

### Неправильная локализация

1. Проверьте, что у User установлена корректная локаль
2. Убедитесь, что файлы переводов существуют
3. Очистите кэш: `php bin/console cache:clear`

### Ошибки рендеринга шаблонов

1. Проверьте синтаксис Twig в шаблонах
2. Убедитесь, что все используемые переменные определены
3. Проверьте логи для точной информации об ошибке

## Безопасность

- API ключи хранятся в переменных окружения (не коммитятся в репозиторий)
- Все email отправляются асинхронно в postFlush, не блокируя основной процесс
- Ошибки email не влияют на сохранение заказа
- Чувствительные данные не логируются

## Performance

- Email отправляются после успешного сохранения истории (postFlush)
- Используется батчинг: несколько изменений статуса обрабатываются вместе
- При ошибке отправки одного email, остальные продолжают обрабатываться
- Рекомендуется настроить очередь (Symfony Messenger) для production

## Дальнейшее развитие

### Рекомендуемые улучшения:

1. **Асинхронная отправка через Symfony Messenger**
   - Избежать блокировки основного процесса
   - Retry механизм при ошибках

2. **Шаблоны с переменными блоками**
   - Редактируемые блоки контента через админ-панель
   - Версионирование шаблонов

3. **A/B тестирование email**
   - Разные варианты писем
   - Аналитика открытий и кликов

4. **Уведомления через другие каналы**
   - SMS через Twilio
   - Push уведомления
   - Telegram боты

5. **Персонализация**
   - Динамический контент на основе истории пользователя
   - Рекомендации в письмах
