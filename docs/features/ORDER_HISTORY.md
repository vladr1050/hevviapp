# 📦 Order History - История заказов

> **Автоматическое отслеживание изменений статусов заказов**

---

## 📋 Обзор

Order History - это система автоматического логирования всех изменений статусов заказов с определением инициатора изменения и сохранением метаданных.

### Основные возможности

- ✅ Автоматическое отслеживание изменений статусов
- ✅ Определение инициатора (User, Carrier, Manager, System)
- ✅ Метаданные (старый статус, новый статус, дата)
- ✅ Read-only режим (нельзя изменить историю вручную)
- ✅ Интеграция с Symfony Security
- ✅ Просмотр истории в админ-панели

---

## 🏗️ Структура данных

### Связь сущностей

```
Order (1) ←────────→ (*) OrderHistory
    id                      id
    status                  order_id (FK)
    user_id                 status
    carrier_id              changed_by
    ...                     changed_at
```

### Order Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order extends BaseSecurityDBO
{
    // Статусы заказа
    const STATUS_REQUEST = 1;   // Запрос
    const STATUS_BILL = 2;      // Счет выставлен
    const STATUS_PAID = 3;      // Оплачено
    const STATUS_ASSIGNED = 4;  // Назначен перевозчик
    const STATUS_CANCELLED = 5; // Отменен

    #[ORM\Column(type: 'integer')]
    private int $status = self::STATUS_REQUEST;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Carrier::class)]
    private ?Carrier $carrier = null;

    #[ORM\ManyToOne(targetEntity: ServiceArea::class)]
    private ?ServiceArea $serviceArea = null;

    #[ORM\Column(type: 'integer')]
    private int $price = 0;

    #[ORM\OneToMany(
        targetEntity: OrderHistory::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $history;

    // Getters and setters...
}
```

### OrderHistory Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderHistoryRepository::class)]
#[ORM\Table(name: 'order_history')]
class OrderHistory extends BaseSecurityDBO
{
    // Типы изменивших
    const CHANGED_BY_USER = 1;      // Пользователь
    const CHANGED_BY_CARRIER = 2;   // Перевозчик
    const CHANGED_BY_SYSTEM = 3;    // Система
    const CHANGED_BY_MANUAL = 4;    // Менеджер

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(type: 'integer')]
    private int $status;

    #[ORM\Column(type: 'integer')]
    private int $changedBy;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $changedAt;

    // Getters and setters...
}
```

---

## ⚙️ Doctrine Event Subscriber

### OrderHistorySubscriber

```php
namespace App\EventSubscriber;

use App\Entity\Order;
use App\Entity\OrderHistory;
use App\Entity\Manager;
use App\Entity\User;
use App\Entity\Carrier;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class OrderHistorySubscriber
{
    private array $pendingHistoryEntries = [];

    public function __construct(
        private readonly Security $security
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Order) {
            return;
        }

        // Проверяем, изменился ли статус
        if (!$args->hasChangedField('status')) {
            return;
        }

        $oldStatus = $args->getOldValue('status');
        $newStatus = $args->getNewValue('status');

        // Определяем тип изменившего
        $changedBy = $this->determineChangedBy();

        // Создаем запись истории
        $history = new OrderHistory();
        $history->setOrder($entity);
        $history->setStatus($newStatus);
        $history->setChangedBy($changedBy);
        $history->setChangedAt(new \DateTime());

        // Добавляем в очередь для сохранения
        $this->pendingHistoryEntries[] = $history;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pendingHistoryEntries)) {
            return;
        }

        $em = $args->getObjectManager();

        foreach ($this->pendingHistoryEntries as $history) {
            $em->persist($history);
        }

        // Очищаем очередь
        $this->pendingHistoryEntries = [];

        // Сохраняем без вызова нового postFlush
        $em->flush();
    }

    private function determineChangedBy(): int
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return OrderHistory::CHANGED_BY_SYSTEM;
        }

        if ($user instanceof Manager) {
            return OrderHistory::CHANGED_BY_MANUAL;
        }

        if ($user instanceof User) {
            return OrderHistory::CHANGED_BY_USER;
        }

        if ($user instanceof Carrier) {
            return OrderHistory::CHANGED_BY_CARRIER;
        }

        return OrderHistory::CHANGED_BY_SYSTEM;
    }
}
```

### Принципы работы

1. **preUpdate** - отслеживает изменения статуса Order
2. **determineChangedBy** - определяет тип пользователя через Security
3. **postFlush** - сохраняет накопленные записи после основного flush

---

## 🎨 Sonata Admin

### OrderHistoryAdmin

```php
namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

class OrderHistoryAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        // Read-only: только list и show
        $collection->remove('create');
        $collection->remove('edit');
        $collection->remove('delete');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('order', null, [
                'label' => 'form.label_order',
            ])
            ->add('status', 'choice', [
                'label' => 'form.label_status',
                'choices' => [
                    Order::STATUS_REQUEST => 'order.status.request',
                    Order::STATUS_BILL => 'order.status.bill',
                    Order::STATUS_PAID => 'order.status.paid',
                    Order::STATUS_ASSIGNED => 'order.status.assigned',
                    Order::STATUS_CANCELLED => 'order.status.cancelled',
                ],
                'catalogue' => 'AppBundle',
            ])
            ->add('changedBy', 'choice', [
                'label' => 'form.label_changed_by',
                'choices' => [
                    OrderHistory::CHANGED_BY_USER => 'order_history.changed_by.user',
                    OrderHistory::CHANGED_BY_CARRIER => 'order_history.changed_by.carrier',
                    OrderHistory::CHANGED_BY_SYSTEM => 'order_history.changed_by.system',
                    OrderHistory::CHANGED_BY_MANUAL => 'order_history.changed_by.manual',
                ],
                'catalogue' => 'AppBundle',
            ])
            ->add('changedAt', null, [
                'label' => 'form.label_changed_at',
            ]);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('order')
            ->add('status')
            ->add('changedBy')
            ->add('changedAt');
    }
}
```

### Интеграция с OrderAdmin

```php
namespace App\Admin;

class OrderAdmin extends BaseAdmin
{
    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->tab('tabs_general')
                ->with('order_info')
                    ->add('status')
                    ->add('user')
                    ->add('carrier')
                    ->add('serviceArea')
                    ->add('price')
                ->end()
            ->end()
            ->tab('tabs_history')
                ->with('order_history')
                    ->add('history', null, [
                        'label' => 'form.label_history',
                        'template' => 'admin/order/show_history.html.twig',
                    ])
                ->end()
            ->end();
    }
}
```

### Шаблон show_history.html.twig

```twig
{# templates/admin/order/show_history.html.twig #}
<table class="table table-bordered">
    <thead>
        <tr>
            <th>{{ 'form.label_status'|trans({}, 'AppBundle') }}</th>
            <th>{{ 'form.label_changed_by'|trans({}, 'AppBundle') }}</th>
            <th>{{ 'form.label_changed_at'|trans({}, 'AppBundle') }}</th>
        </tr>
    </thead>
    <tbody>
        {% for item in object.history %}
            <tr>
                <td>
                    <span class="label label-{{ item.statusClass }}">
                        {{ ('order.status.' ~ item.statusLabel)|trans({}, 'AppBundle') }}
                    </span>
                </td>
                <td>{{ ('order_history.changed_by.' ~ item.changedByLabel)|trans({}, 'AppBundle') }}</td>
                <td>{{ item.changedAt|date('Y-m-d H:i:s') }}</td>
            </tr>
        {% else %}
            <tr>
                <td colspan="3">{{ 'No history'|trans }}</td>
            </tr>
        {% endfor %}
    </tbody>
</table>
```

---

## 🌐 Переводы

### translations/AppBundle.ru.yaml

```yaml
menu:
    order_history: "История заказов"

form:
    label_order: "Заказ"
    label_status: "Статус"
    label_changed_by: "Изменено"
    label_changed_at: "Дата изменения"
    label_history: "История изменений статуса"

order:
    status:
        request: "Запрос"
        bill: "Счет выставлен"
        paid: "Оплачено"
        assigned: "Назначен"
        cancelled: "Отменен"

order_history:
    changed_by:
        user: "Пользователь"
        carrier: "Перевозчик"
        system: "Система"
        manual: "Менеджер"
```

### translations/AppBundle.en.yaml

```yaml
menu:
    order_history: "Order History"

form:
    label_order: "Order"
    label_status: "Status"
    label_changed_by: "Changed By"
    label_changed_at: "Changed At"
    label_history: "Status Change History"

order:
    status:
        request: "Request"
        bill: "Bill Issued"
        paid: "Paid"
        assigned: "Assigned"
        cancelled: "Cancelled"

order_history:
    changed_by:
        user: "User"
        carrier: "Carrier"
        system: "System"
        manual: "Manager"
```

---

## 💼 Использование

### Просмотр истории в админке

1. Перейдите в "Заказы"
2. Откройте заказ (кнопка "Show")
3. Перейдите на вкладку "История изменений"
4. Увидите все изменения статуса

### Изменение статуса

```php
// В контроллере или сервисе
$order = $orderRepository->find($id);
$order->setStatus(Order::STATUS_PAID);

$entityManager->persist($order);
$entityManager->flush();

// OrderHistorySubscriber автоматически создаст запись в истории!
```

### Программный доступ к истории

```php
$order = $orderRepository->find($id);
$history = $order->getHistory();

foreach ($history as $entry) {
    echo sprintf(
        "Status changed to %d by %d at %s\n",
        $entry->getStatus(),
        $entry->getChangedBy(),
        $entry->getChangedAt()->format('Y-m-d H:i:s')
    );
}
```

---

## 🔍 SQL запросы

### Вся история заказа

```sql
SELECT 
    oh.id,
    oh.status,
    oh.changed_by,
    oh.changed_at,
    o.id as order_id
FROM order_history oh
JOIN orders o ON oh.order_id = o.id
WHERE o.id = 'order-uuid-here'
ORDER BY oh.changed_at DESC;
```

### Статистика изменений

```sql
-- Количество изменений по типам инициаторов
SELECT 
    changed_by,
    COUNT(*) as count
FROM order_history
GROUP BY changed_by
ORDER BY count DESC;

-- Наиболее часто меняемые заказы
SELECT 
    order_id,
    COUNT(*) as changes_count
FROM order_history
GROUP BY order_id
ORDER BY changes_count DESC
LIMIT 10;
```

---

## ✅ Принципы SOLID

### Single Responsibility Principle

✅ **OrderHistorySubscriber** - только отслеживание изменений статусов  
✅ **OrderHistoryAdmin** - только отображение истории  

### Open/Closed Principle

✅ Можно легко добавить новые типы изменивших без изменения существующего кода

### Liskov Substitution Principle

✅ Работает с `BaseSecurityDBO` как базовым классом

### Interface Segregation Principle

✅ Минимальные зависимости - только Security для определения пользователя

### Dependency Inversion Principle

✅ Зависит от абстракции Security, а не конкретных классов

---

## 🧪 Тестирование

### Тестовые сценарии

1. **Изменение статуса менеджером:**
   - Авторизуйтесь как Manager
   - Измените статус заказа
   - Проверьте: `changed_by = 4 (MANUAL)`

2. **Изменение статуса пользователем:**
   - Авторизуйтесь как User
   - Отмените заказ
   - Проверьте: `changed_by = 1 (USER)`

3. **Изменение статуса перевозчиком:**
   - Авторизуйтесь как Carrier
   - Измените статус на ASSIGNED
   - Проверьте: `changed_by = 2 (CARRIER)`

4. **Изменение через консоль (без авторизации):**
   - Выполните команду без Security context
   - Проверьте: `changed_by = 3 (SYSTEM)`

### SQL проверка

```sql
-- Проверка последнего изменения
SELECT * FROM order_history 
WHERE order_id = 'uuid'
ORDER BY changed_at DESC 
LIMIT 1;

-- Должно быть:
-- status: новый статус
-- changed_by: ожидаемый тип (1-4)
-- changed_at: текущая дата/время
```

---

## 🚀 Лучшие практики

### 1. Не изменяйте историю вручную

История должна создаваться только автоматически через Subscriber.

### 2. Используйте транзакции

```php
$em->beginTransaction();
try {
    $order->setStatus(Order::STATUS_PAID);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

### 3. Добавляйте индексы

```sql
CREATE INDEX idx_order_history_order_id ON order_history(order_id);
CREATE INDEX idx_order_history_changed_at ON order_history(changed_at);
```

### 4. Очищайте старую историю

```php
// Удаление истории старше 1 года
$qb = $em->createQueryBuilder();
$qb->delete(OrderHistory::class, 'oh')
   ->where('oh.changedAt < :date')
   ->setParameter('date', new \DateTime('-1 year'))
   ->getQuery()
   ->execute();
```

---

## 📚 Дополнительные ресурсы

- [Doctrine Events](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/events.html)
- [Symfony Security](https://symfony.com/doc/current/security.html)
- [Sonata Admin Routes](https://docs.sonata-project.org/projects/SonataAdminBundle/en/4.x/reference/routing/)

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026  
**Статус:** ✅ Production Ready
