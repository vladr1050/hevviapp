# Структура системы Email уведомлений

## Архитектура решения

Система построена на принципах **SOLID** и следует идиомам **Symfony 8**:

```
┌─────────────────────────────────────────────────────────────┐
│                   OrderHistorySubscriber                     │
│              (Doctrine Event Subscriber)                     │
│                                                              │
│  - Слушает изменения статусов заказов (preUpdate)          │
│  - Создает записи в OrderHistory                            │
│  - Вызывает OrderStatusService для отправки email           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                   OrderStatusService                         │
│              (Business Logic Layer)                          │
│                                                              │
│  - Определяет, нужно ли отправлять email для статуса       │
│  - Выбирает правильный шаблон                               │
│  - Рендерит шаблон через Twig                               │
│  - Получает переводы через Translator                       │
│  - Вызывает EmailService для отправки                       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              EmailServiceInterface                           │
│                  (Abstraction)                               │
│                                                              │
│  - Интерфейс для email сервисов (DIP)                      │
│  - Позволяет легко заменить провайдера                      │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│               MailjetEmailService                            │
│            (Concrete Implementation)                         │
│                                                              │
│  - Реализация отправки через Mailjet API                   │
│  - Обработка ошибок и логирование                           │
│  - Поддержка отключения отправки (dev режим)               │
└─────────────────────────────────────────────────────────────┘
```

## Структура файлов

```
php-backoffice-service/
│
├── config/
│   ├── services.yaml                    # Конфигурация DI контейнера
│   └── packages/
│
├── src/
│   ├── Command/
│   │   └── TestOrderStatusEmailCommand.php  # Тестовая команда
│   │
│   ├── Entity/
│   │   ├── Order.php                    # Сущность заказа
│   │   └── User.php                     # Сущность пользователя
│   │
│   ├── EventSubscriber/
│   │   └── OrderHistorySubscriber.php   # ✨ Обновлен: добавлена отправка email
│   │
│   └── Service/
│       ├── Email/
│       │   ├── Contract/
│       │   │   └── EmailServiceInterface.php    # ✨ Новый: интерфейс
│       │   └── MailjetEmailService.php          # ✨ Новый: Mailjet имплементация
│       │
│       └── OrderStatus/
│           └── OrderStatusService.php    # ✨ Обновлен: логика отправки email
│
├── templates/
│   └── email/
│       └── order_status/
│           ├── base.html.twig           # ✨ Новый: базовый шаблон
│           ├── accepted.html.twig       # ✨ Новый: статус ACCEPTED
│           ├── assigned.html.twig       # ✨ Новый: статус ASSIGNED
│           ├── pickup_done.html.twig    # ✨ Новый: статус PICKUP_DONE
│           └── delivered.html.twig      # ✨ Новый: статус DELIVERED
│
├── translations/
│   ├── AppBundle.en.yaml                # ✨ Обновлен: добавлены переводы
│   └── AppBundle.ru.yaml                # ✨ Обновлен: добавлены переводы
│
├── docs/
│   ├── EMAIL_NOTIFICATIONS.md           # ✨ Новый: полная документация
│   ├── QUICK_START_EMAIL.md             # ✨ Новый: быстрый старт
│   └── EMAIL_SYSTEM_STRUCTURE.md        # ✨ Новый: этот файл
│
└── .env.mailjet.example                 # ✨ Новый: пример конфигурации
```

## Принципы SOLID в реализации

### 1. Single Responsibility Principle (SRP)

**✅ Каждый класс имеет одну ответственность:**

- `EmailServiceInterface` - определяет контракт для отправки email
- `MailjetEmailService` - отправляет email через Mailjet API
- `OrderStatusService` - управляет логикой отправки уведомлений для статусов
- `OrderHistorySubscriber` - отслеживает изменения статусов и координирует действия

### 2. Open/Closed Principle (OCP)

**✅ Система открыта для расширения, но закрыта для модификации:**

- Можно добавить новые статусы без изменения существующего кода
- Можно добавить новые провайдеры email без изменения OrderStatusService
- Можно изменить шаблоны без изменения PHP кода

### 3. Liskov Substitution Principle (LSP)

**✅ Любая реализация EmailServiceInterface может заменить другую:**

```php
// Можно заменить Mailjet на SendGrid без изменения кода
App\Service\Email\Contract\EmailServiceInterface:
    class: App\Service\Email\SendGridEmailService  # Вместо MailjetEmailService
```

### 4. Interface Segregation Principle (ISP)

**✅ Интерфейс EmailServiceInterface содержит только необходимые методы:**

```php
interface EmailServiceInterface
{
    public function send(string $to, string $subject, string $htmlContent, ?string $textContent = null): bool;
}
```

### 5. Dependency Inversion Principle (DIP)

**✅ Высокоуровневые модули не зависят от низкоуровневых:**

```php
// OrderStatusService зависит от абстракции (интерфейса), а не от конкретной реализации
public function __construct(
    private readonly EmailServiceInterface $emailService,  // Абстракция, а не MailjetEmailService
    // ...
) {}
```

## Идиомы Symfony

### 1. Dependency Injection

**✅ Все зависимости инжектятся через конструктор:**

```php
public function __construct(
    private readonly EmailServiceInterface $emailService,
    private readonly Environment $twig,
    private readonly TranslatorInterface $translator,
    private readonly LoggerInterface $logger
) {}
```

### 2. Service Configuration

**✅ Сервисы настроены в services.yaml:**

```yaml
App\Service\Email\Contract\EmailServiceInterface:
    class: App\Service\Email\MailjetEmailService
    arguments:
        $mailjetApiKey: '%mailjet_api_key%'
        # ...
```

### 3. Environment Variables

**✅ Конфигурация через переменные окружения:**

```yaml
parameters:
    mailjet_api_key: '%env(MAILJET_API_KEY)%'
    mailjet_enabled: '%env(bool:MAILJET_ENABLED)%'
```

### 4. Twig Templates

**✅ Шаблоны используют наследование и блоки:**

```twig
{% extends 'email/order_status/base.html.twig' %}
{% block content %}
    <!-- Контент -->
{% endblock %}
```

### 5. Translation Component

**✅ Все тексты переводятся через trans:**

```php
$this->translator->trans('email.order_status.accepted.title', [], 'AppBundle', $locale)
```

### 6. Event Subscribers

**✅ Doctrine события обрабатываются через Subscriber:**

```php
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class OrderHistorySubscriber implements EventSubscriber
```

### 7. Console Commands

**✅ Тестовая команда использует атрибут AsCommand:**

```php
#[AsCommand(
    name: 'app:test-order-status-email',
    description: 'Тестовая команда...'
)]
```

### 8. Logger Integration

**✅ Логирование через PSR-3 LoggerInterface:**

```php
$this->logger->info('Email sent successfully', [
    'order_id' => $order->getId()?->toRfc4122(),
    'status' => $status,
]);
```

## Поток данных

```
1. Администратор изменяет статус заказа в Sonata Admin
        ↓
2. Doctrine вызывает OrderHistorySubscriber::preUpdate()
        ↓
3. Создается OrderHistory и добавляется в pendingHistories
        ↓
4. Заказ добавляется в ordersForEmailNotification
        ↓
5. Doctrine вызывает OrderHistorySubscriber::postFlush()
        ↓
6. OrderHistory сохраняется в БД
        ↓
7. Вызывается OrderHistorySubscriber::sendEmailNotifications()
        ↓
8. Для каждого заказа вызывается OrderStatusService::sendEmailToSender()
        ↓
9. OrderStatusService проверяет, нужно ли отправлять email для статуса
        ↓
10. Получается локаль пользователя и выбирается шаблон
        ↓
11. Twig рендерит шаблон с переводами
        ↓
12. Translator переводит тексты на нужный язык
        ↓
13. MailjetEmailService отправляет email через Mailjet API
        ↓
14. Результат логируется через LoggerInterface
```

## Расширяемость

### Добавление нового статуса

1. Добавьте шаблон в `templates/email/order_status/`
2. Добавьте переводы в `translations/AppBundle.{locale}.yaml`
3. Обновите константы в `OrderStatusService`

### Добавление нового провайдера email

1. Создайте класс, реализующий `EmailServiceInterface`
2. Зарегистрируйте в `services.yaml`

### Добавление нового языка

1. Создайте `translations/AppBundle.{locale}.yaml`
2. Скопируйте структуру переводов

## Безопасность

- ✅ API ключи хранятся в переменных окружения
- ✅ Чувствительные данные не логируются
- ✅ Email отправляется только верифицированным пользователям
- ✅ Ошибки обрабатываются gracefully

## Производительность

- ✅ Email отправляется после flush (не блокирует транзакцию)
- ✅ Используется батчинг для множественных изменений
- ✅ Ошибки одного email не влияют на другие
- ✅ Логирование оптимизировано

## Тестируемость

- ✅ Все зависимости инжектятся (легко мокаются)
- ✅ Интерфейсы позволяют создавать mock объекты
- ✅ Логика отделена от инфраструктуры
- ✅ Есть тестовая команда для проверки

## Поддерживаемость

- ✅ Четкая структура файлов
- ✅ Подробная документация
- ✅ Понятные имена классов и методов
- ✅ Комментарии на русском языке
- ✅ Логирование всех важных событий

---

**Система готова к использованию и легко расширяется! 🚀**
