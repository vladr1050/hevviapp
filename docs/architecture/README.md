# 🏛️ Архитектура проекта

> **Обзор архитектурных решений и принципов проектирования**

---

## 📋 Обзор

Проект построен на **Symfony 8** с применением принципов **SOLID**, **OOP** и современных паттернов проектирования.

### Ключевые принципы

- ✅ **SOLID** - пять принципов объектно-ориентированного программирования
- ✅ **DDD** - Domain-Driven Design для бизнес-логики
- ✅ **Layered Architecture** - слоистая архитектура
- ✅ **Dependency Injection** - внедрение зависимостей
- ✅ **Event-Driven** - событийно-ориентированная архитектура

---

## 🏗️ Слоистая архитектура

```
┌────────────────────────────────────────────────────────┐
│                 Presentation Layer                      │
│          (Controllers, Templates, Admin)                │
├────────────────────────────────────────────────────────┤
│                                                          │
│  Sonata Admin     Twig Templates     Stimulus JS       │
│  (OrderAdmin)     (base.html.twig)   (Controllers)     │
│                                                          │
└─────────────────────┬──────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────┐
│                  Application Layer                      │
│              (Services, Commands)                       │
├────────────────────────────────────────────────────────┤
│                                                          │
│  Commands         Services           Event Subscribers  │
│  (Console)        (Business Logic)   (Doctrine Events) │
│                                                          │
└─────────────────────┬──────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────┐
│                   Domain Layer                          │
│              (Entities, Value Objects)                  │
├────────────────────────────────────────────────────────┤
│                                                          │
│  Order            ServiceArea        GeoArea           │
│  OrderHistory     MatrixItem         User/Manager      │
│                                                          │
└─────────────────────┬──────────────────────────────────┘
                      │
                      ▼
┌────────────────────────────────────────────────────────┐
│              Infrastructure Layer                       │
│           (Database, External APIs)                     │
├────────────────────────────────────────────────────────┤
│                                                          │
│  PostgreSQL       PostGIS            GADM API          │
│  Doctrine ORM     Repositories       HTTP Client       │
│                                                          │
└────────────────────────────────────────────────────────┘
```

---

## 🎯 SOLID принципы

### S - Single Responsibility Principle

**Каждый класс имеет одну причину для изменения**

| Класс | Ответственность |
|-------|----------------|
| `OrderHistorySubscriber` | Отслеживание изменений статусов |
| `GadmGeoAreaParser` | Парсинг GADM данных |
| `MapService` | Управление картой Leaflet |
| `ApiService` | HTTP запросы к API |

### O - Open/Closed Principle

**Открыт для расширения, закрыт для модификации**

```php
// Базовый интерфейс
interface GadmDataProviderInterface
{
    public function fetchCountryData(string $iso3): array;
}

// Можно добавить новые реализации без изменения существующего кода
class GadmJsonProvider implements GadmDataProviderInterface { }
class GadmShapefileProvider implements GadmDataProviderInterface { }
```

### L - Liskov Substitution Principle

**Можно заменить базовый класс на наследника**

```php
// Все сущности наследуют BaseSecurityDBO
class Order extends BaseSecurityDBO { }
class ServiceArea extends BaseSecurityDBO { }

// Можно работать с любой сущностью через базовый класс
function logEntity(BaseSecurityDBO $entity) {
    echo $entity->getId();
    echo $entity->getCreatedAt();
}
```

### I - Interface Segregation Principle

**Клиенты не должны зависеть от неиспользуемых методов**

```php
// Разные интерфейсы для разных целей
interface GadmDataProviderInterface { }
interface OsmDataProviderInterface { }

// А не один большой интерфейс GeoDataProviderInterface
```

### D - Dependency Inversion Principle

**Зависимости через абстракции**

```php
class OrderHistorySubscriber
{
    public function __construct(
        private readonly Security $security  // ← Абстракция
    ) {}
    
    // Не зависит от конкретных реализаций User/Manager/Carrier
}
```

---

## 📦 Структура проекта

### Backend (PHP)

```
src/
├── Admin/              # Sonata Admin классы
│   ├── OrderAdmin.php
│   ├── ServiceAreaAdmin.php
│   └── MatrixItemAdmin.php
├── Command/            # Console команды
│   ├── ParseGeoAreasGadmCommand.php
│   └── ParseGeoAreasCommand.php
├── Controller/         # HTTP контроллеры
│   └── ApiController.php
├── Entity/             # Domain сущности
│   ├── Order.php
│   ├── OrderHistory.php
│   ├── ServiceArea.php
│   ├── MatrixItem.php
│   ├── GeoArea.php
│   ├── User.php
│   ├── Manager.php
│   └── Carrier.php
├── EventSubscriber/    # Event subscribers
│   ├── OrderHistorySubscriber.php
│   └── OrderAssignmentSubscriber.php
├── Repository/         # Doctrine repositories
│   ├── OrderRepository.php
│   ├── GeoAreaRepository.php
│   └── ServiceAreaRepository.php
├── Service/            # Business logic
│   └── GeoArea/
│       ├── GadmGeoAreaParser.php
│       ├── OsmGeoAreaParser.php
│       └── Contract/
└── Twig/               # Twig extensions
    └── Extension/
```

### Frontend (JavaScript)

```
assets/
├── controllers/        # Stimulus controllers
│   ├── geo_area_map_controller.js
│   └── geo_area_view_map_controller.js
├── services/          # JS services
│   ├── MapService.js
│   ├── ApiService.js
│   └── GeoAreaService.js
└── styles/            # SCSS styles
    └── app.scss
```

---

## 🎨 Паттерны проектирования

### 1. Repository Pattern

```php
class OrderRepository extends ServiceEntityRepository
{
    public function findActiveOrders(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status != :cancelled')
            ->setParameter('cancelled', Order::STATUS_CANCELLED)
            ->getQuery()
            ->getResult();
    }
}
```

**Преимущества:**
- ✅ Инкапсулирует логику запросов
- ✅ Переиспользование кода
- ✅ Легко тестировать

### 2. Service Layer Pattern

```php
class GadmGeoAreaParser
{
    public function __construct(
        private readonly GadmDataProviderInterface $dataProvider,
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    public function parse(string $country): void
    {
        $data = $this->dataProvider->fetchCountryData($country);
        // ... парсинг и сохранение
    }
}
```

**Преимущества:**
- ✅ Бизнес-логика отделена от контроллеров
- ✅ Переиспользование в разных местах
- ✅ Легко тестировать

### 3. Event Subscriber Pattern

```php
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class OrderHistorySubscriber
{
    public function preUpdate(PreUpdateEventArgs $args): void { }
    public function postFlush(PostFlushEventArgs $args): void { }
}
```

**Преимущества:**
- ✅ Декаплинг (слабая связанность)
- ✅ Автоматическое выполнение
- ✅ Расширяемость

### 4. Dependency Injection

```php
services:
    App\Service\GeoArea\GadmGeoAreaParser:
        arguments:
            $dataProvider: '@App\Service\GeoArea\GadmDataProvider\GadmJsonProvider'
            $entityManager: '@doctrine.orm.entity_manager'
```

**Преимущества:**
- ✅ Легко заменить зависимости
- ✅ Тестируемость (можно инжектить моки)
- ✅ Конфигурируемость

### 5. Strategy Pattern (Frontend)

```javascript
// Разные стратегии отображения карты
class EditMapStrategy {
    initialize(map) {
        // Интерактивная карта с редактированием
    }
}

class ViewMapStrategy {
    initialize(map) {
        // Read-only карта
    }
}

// Использование
const strategy = isEditMode ? new EditMapStrategy() : new ViewMapStrategy();
strategy.initialize(map);
```

---

## 🔄 Поток данных

### Пример: Изменение статуса заказа

```
1. User действие (HTTP Request)
   ↓
2. Controller/Admin (Presentation)
   ↓
3. Order Entity (Domain)
   ↓
4. Doctrine flush
   ↓
5. OrderHistorySubscriber::preUpdate (Event)
   ↓
6. OrderHistory Entity создается (Domain)
   ↓
7. OrderHistorySubscriber::postFlush (Event)
   ↓
8. Database UPDATE + INSERT (Infrastructure)
   ↓
9. Response (Presentation)
```

### Пример: Импорт геоданных

```
1. Console Command
   ↓
2. GadmGeoAreaParser (Service)
   ↓
3. GadmJsonProvider::fetchCountryData() (Infrastructure)
   ↓
4. HTTP Request to GADM API
   ↓
5. Parser обрабатывает JSON
   ↓
6. GeoArea Entity создается (Domain)
   ↓
7. EntityManager::persist() + flush()
   ↓
8. Database INSERT (Infrastructure)
```

---

## 🧪 Тестируемость

### Юнит-тесты

```php
class OrderTest extends TestCase
{
    public function testStatusChange(): void
    {
        $order = new Order();
        $order->setStatus(Order::STATUS_REQUEST);
        
        $this->assertEquals(Order::STATUS_REQUEST, $order->getStatus());
    }
}
```

### Функциональные тесты

```php
class OrderControllerTest extends WebTestCase
{
    public function testCreateOrder(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/orders', [...]);
        
        $this->assertResponseIsSuccessful();
    }
}
```

---

## 📊 Метрики качества

### До рефакторинга

```
Файлы: 1 большой контроллер
Строк: ~800
Цикломатическая сложность: Высокая
Тестируемость: Сложно
Переиспользование: Нет
```

### После рефакторинга (v2.0)

```
Файлы: 5 сервисов + 2 контроллера
Строки: распределены по файлам
Цикломатическая сложность: Низкая
Тестируемость: Легко
Переиспользование: Да
```

---

## 🚀 Масштабируемость

### Горизонтальное масштабирование

- ✅ Stateless backend (можно запустить несколько инстансов)
- ✅ PostgreSQL с read replicas
- ✅ Кеширование (Redis)

### Вертикальное масштабирование

- ✅ Оптимизация запросов (индексы, EXPLAIN)
- ✅ Lazy loading сущностей
- ✅ Batch операции для массовых операций

### Производительность

```php
// ❌ N+1 проблема
$orders = $orderRepository->findAll();
foreach ($orders as $order) {
    echo $order->getUser()->getName();  // N запросов
}

// ✅ Eager loading
$orders = $orderRepository->createQueryBuilder('o')
    ->leftJoin('o.user', 'u')
    ->addSelect('u')
    ->getQuery()
    ->getResult();  // 1 запрос
```

---

## 🔐 Безопасность

### Аутентификация

- FRPC Sonata Authorization Bundle
- Symfony Security компонент
- Role-based access control (RBAC)

### Авторизация

```yaml
security:
    role_hierarchy:
        ROLE_ADMIN: ROLE_USER
        ROLE_SUPER_ADMIN: [ROLE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
```

### Валидация

```php
use Symfony\Component\Validator\Constraints as Assert;

class Order
{
    #[Assert\Positive]
    private int $price;
    
    #[Assert\Choice([1, 2, 3, 4, 5])]
    private int $status;
}
```

---

## 📚 Дополнительные ресурсы

- **[Symfony Best Practices](https://symfony.com/doc/current/best_practices.html)**
- **[Doctrine Best Practices](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/best-practices.html)**
- **[SOLID Principles](https://en.wikipedia.org/wiki/SOLID)**
- **[Domain-Driven Design](https://en.wikipedia.org/wiki/Domain-driven_design)**

---

**Версия:** 2.0  
**Последнее обновление:** Февраль 2026  
**Статус:** ✅ Production Ready
