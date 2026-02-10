# 🚚 ServiceArea - Зоны обслуживания

> **Система управления зонами обслуживания с матрицей цен**

---

## 📋 Обзор

ServiceArea - это модуль для управления зонами обслуживания с гибкой системой ценообразования на основе километража.

### Основные возможности

- ✅ Создание и редактирование зон обслуживания
- ✅ Матрица цен по диапазонам километража
- ✅ Встроенное редактирование в форме (inline editing)
- ✅ Автоматическое каскадное удаление элементов
- ✅ Валидация пересечений диапазонов
- ✅ Двуязычный интерфейс (ru/en)

---

## 🏗️ Структура данных

### Сущности

```
ServiceArea (1) ←────────→ (*) MatrixItem
    id                          id
    name                        mileage_from
    created_at                  mileage_to
    updated_at                  price
                                service_area_id (FK)
                                created_at
                                updated_at
```

### ServiceArea Entity

```php
namespace App\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceAreaRepository::class)]
#[ORM\Table(name: 'service_area')]
class ServiceArea extends BaseSecurityDBO
{
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\OneToMany(
        targetEntity: MatrixItem::class,
        mappedBy: 'serviceArea',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $matrixItems;

    public function __construct()
    {
        parent::__construct();
        $this->matrixItems = new ArrayCollection();
    }

    // Getters and setters...
    
    public function addMatrixItem(MatrixItem $item): self
    {
        if (!$this->matrixItems->contains($item)) {
            $this->matrixItems[] = $item;
            $item->setServiceArea($this);
        }
        return $this;
    }

    public function removeMatrixItem(MatrixItem $item): self
    {
        if ($this->matrixItems->removeElement($item)) {
            if ($item->getServiceArea() === $this) {
                $item->setServiceArea(null);
            }
        }
        return $this;
    }
}
```

### MatrixItem Entity

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatrixItemRepository::class)]
#[ORM\Table(name: 'matrix_item')]
class MatrixItem extends BaseSecurityDBO
{
    #[ORM\Column(type: 'integer')]
    private int $mileageFrom;

    #[ORM\Column(type: 'integer')]
    private int $mileageTo;

    #[ORM\Column(type: 'integer')]
    private int $price;

    #[ORM\ManyToOne(
        targetEntity: ServiceArea::class,
        inversedBy: 'matrixItems',
        cascade: ['persist']
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServiceArea $serviceArea = null;

    // Getters and setters...
}
```

---

## 🎨 Sonata Admin

### ServiceAreaAdmin

```php
namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\Form\Type\CollectionType as SonataCollectionType;

class ServiceAreaAdmin extends BaseAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
                ->with('service_area_info')
                    ->add('name', TextType::class, [
                        'label' => 'form.label_name',
                    ])
                ->end()
            ->end()
            ->tab('tabs_matrix_items')
                ->with('matrix_items_list')
                    ->add('matrixItems', CollectionType::class, [
                        'by_reference' => false,  // ВАЖНО!
                        'type_options' => [
                            'delete' => true,
                        ],
                    ], [
                        'edit' => 'inline',
                        'inline' => 'table',
                        'sortable' => 'position',
                    ])
                ->end()
            ->end();
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('name', null, [
                'label' => 'form.label_name',
            ])
            ->add('matrixItems', null, [
                'label' => 'list.label_matrix_items_count',
                'template' => 'admin/service_area/list_matrix_items_count.html.twig',
            ])
            ->add('createdAt', null, [
                'label' => 'form.label_created_at',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
            ]);
    }
}
```

### MatrixItemAdmin

```php
namespace App\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class MatrixItemAdmin extends BaseAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('mileageFrom', IntegerType::class, [
                'label' => 'form.label_mileage_from',
            ])
            ->add('mileageTo', IntegerType::class, [
                'label' => 'form.label_mileage_to',
            ])
            ->add('price', IntegerType::class, [
                'label' => 'form.label_price',
                'help' => 'form.help_price_in_cents',
            ]);
    }
}
```

---

## ⚙️ Конфигурация

### services.yaml

```yaml
services:
    App\Admin\ServiceAreaAdmin:
        tags:
            - { name: sonata.admin, model_class: App\Entity\ServiceArea, manager_type: orm, label: "Service Areas" }
        calls:
            - [ setTranslationDomain, ['AppBundle'] ]
            - [ addChild, ['@App\Admin\MatrixItemAdmin', 'matrixItems'] ]

    App\Admin\MatrixItemAdmin:
        tags:
            - { name: sonata.admin, model_class: App\Entity\MatrixItem, manager_type: orm, label: "Matrix Items", show_in_dashboard: false }
        calls:
            - [ setTranslationDomain, ['AppBundle'] ]
```

### Переводы

**translations/AppBundle.ru.yaml:**

```yaml
menu:
    service_areas: "Зоны обслуживания"
    matrix_items: "Элементы матрицы"

form:
    group_tabs_general: "Общее"
    group_tabs_matrix_items: "Элементы матрицы"
    group_service_area_info: "Информация о зоне"
    group_matrix_items_list: "Список элементов"
    
    label_name: "Название"
    label_mileage_from: "Километраж от"
    label_mileage_to: "Километраж до"
    label_price: "Цена"
    help_price_in_cents: "Укажите цену в центах (1 EUR = 100 центов)"

list:
    label_matrix_items_count: "Количество элементов"
```

---

## 💼 Использование

### Создание зоны обслуживания

1. Перейдите в админ-панель: http://localhost:8090/admin
2. Выберите "Зоны обслуживания" → "Добавить"
3. На вкладке "Общее":
   - Введите название зоны (например, "Рига")
4. На вкладке "Элементы матрицы":
   - Нажмите "Добавить новый"
   - Заполните диапазон и цену
   - Добавьте еще элементы по необходимости
5. Сохраните

### Пример данных

**Зона: Рига**

| От (км) | До (км) | Цена (центы) | Цена (EUR) |
|---------|---------|--------------|------------|
| 0 | 50 | 1000 | 10.00 |
| 51 | 100 | 1500 | 15.00 |
| 101 | 200 | 2500 | 25.00 |
| 201 | 500 | 5000 | 50.00 |

### Редактирование

1. Откройте зону на редактирование
2. Перейдите на вкладку "Элементы матрицы"
3. Измените значения или добавьте/удалите элементы
4. Сохраните

---

## 🔍 Запросы к базе данных

### Получение всех зон

```php
$serviceAreas = $entityManager
    ->getRepository(ServiceArea::class)
    ->findAll();
```

### Получение зоны с элементами матрицы

```php
$serviceArea = $entityManager
    ->getRepository(ServiceArea::class)
    ->find($id);

$matrixItems = $serviceArea->getMatrixItems();
```

### Поиск цены по километражу

```php
public function findPriceByMileage(ServiceArea $serviceArea, int $mileage): ?int
{
    $qb = $this->createQueryBuilder('m');
    $qb->select('m.price')
       ->where('m.serviceArea = :area')
       ->andWhere('m.mileageFrom <= :mileage')
       ->andWhere('m.mileageTo >= :mileage')
       ->setParameter('area', $serviceArea)
       ->setParameter('mileage', $mileage)
       ->setMaxResults(1);

    return $qb->getQuery()->getOneOrNullResult()['price'] ?? null;
}
```

### SQL запросы

```sql
-- Все зоны обслуживания
SELECT id, name, created_at 
FROM service_area 
ORDER BY name;

-- Матрица цен для зоны
SELECT 
    mileage_from,
    mileage_to,
    price,
    price / 100.0 as price_eur
FROM matrix_item 
WHERE service_area_id = 'uuid-here'
ORDER BY mileage_from;

-- Цена для конкретного километража
SELECT price 
FROM matrix_item 
WHERE service_area_id = 'uuid-here'
  AND mileage_from <= 75
  AND mileage_to >= 75;
```

---

## ✅ Валидация

### Правила

1. **Диапазоны не пересекаются:**
   - `mileageFrom` < `mileageTo`
   - Диапазоны одной зоны не перекрываются

2. **Цена положительная:**
   - `price` > 0

3. **Название уникально:**
   - Имя зоны уникально

### Пример валидатора

```php
namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MileageRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if ($value->getMileageFrom() >= $value->getMileageTo()) {
            $this->context->buildViolation('mileage_from должен быть меньше mileage_to')
                ->atPath('mileageFrom')
                ->addViolation();
        }

        // Проверка пересечений с другими элементами зоны
        $serviceArea = $value->getServiceArea();
        if ($serviceArea) {
            foreach ($serviceArea->getMatrixItems() as $item) {
                if ($item === $value) continue;

                if ($this->rangesOverlap($value, $item)) {
                    $this->context->buildViolation('Диапазоны пересекаются')
                        ->atPath('mileageFrom')
                        ->addViolation();
                }
            }
        }
    }

    private function rangesOverlap(MatrixItem $a, MatrixItem $b): bool
    {
        return !($a->getMileageTo() < $b->getMileageFrom() 
              || $a->getMileageFrom() > $b->getMileageTo());
    }
}
```

---

## 🧪 Тестирование

### Юнит-тесты

```php
namespace App\Tests\Entity;

use App\Entity\ServiceArea;
use App\Entity\MatrixItem;
use PHPUnit\Framework\TestCase;

class ServiceAreaTest extends TestCase
{
    public function testAddMatrixItem(): void
    {
        $serviceArea = new ServiceArea();
        $serviceArea->setName('Test Area');

        $item = new MatrixItem();
        $item->setMileageFrom(0);
        $item->setMileageTo(50);
        $item->setPrice(1000);

        $serviceArea->addMatrixItem($item);

        $this->assertCount(1, $serviceArea->getMatrixItems());
        $this->assertSame($serviceArea, $item->getServiceArea());
    }

    public function testRemoveMatrixItem(): void
    {
        $serviceArea = new ServiceArea();
        $item = new MatrixItem();
        
        $serviceArea->addMatrixItem($item);
        $serviceArea->removeMatrixItem($item);

        $this->assertCount(0, $serviceArea->getMatrixItems());
        $this->assertNull($item->getServiceArea());
    }
}
```

---

## 🚀 Лучшие практики

### 1. Используйте `by_reference => false`

```php
->add('matrixItems', CollectionType::class, [
    'by_reference' => false,  // ✅ ВАЖНО для корректной работы adder/remover
])
```

### 2. Настройте cascade операции

```php
#[ORM\OneToMany(
    cascade: ['persist', 'remove'],  // ✅ Автосохранение и удаление
    orphanRemoval: true              // ✅ Удаление orphan записей
)]
```

### 3. Используйте onDelete CASCADE

```php
#[ORM\JoinColumn(
    nullable: false,
    onDelete: 'CASCADE'  // ✅ Каскадное удаление в БД
)]
```

### 4. Валидируйте на уровне приложения

Не полагайтесь только на БД - валидируйте в коде.

### 5. Используйте транзакции

```php
$em->beginTransaction();
try {
    $em->persist($serviceArea);
    $em->flush();
    $em->commit();
} catch (\Exception $e) {
    $em->rollback();
    throw $e;
}
```

---

## 📚 Дополнительные ресурсы

- [Sonata Admin Documentation](https://docs.sonata-project.org/projects/SonataAdminBundle/)
- [Doctrine Collections](https://www.doctrine-project.org/projects/doctrine-collections/en/latest/index.html)
- [Symfony Forms](https://symfony.com/doc/current/forms.html)

---

**Версия:** 1.0  
**Последнее обновление:** Февраль 2026  
**Статус:** ✅ Production Ready
