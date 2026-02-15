# Custom Geo Areas - Валидация и DataTransformer

## Проблема

При создании кастомной зоны, ей присваивается временный ID (`temp_xxx`), который добавляется в скрытое поле формы. При submit формы, Symfony EntityType пытается валидировать этот ID и найти соответствующую запись в БД, но не находит её (т.к. она еще не сохранена), что приводит к ошибке:

```
The selected choice is invalid.
```

## Решение

Создан **DataTransformer** (`GeoAreaWithTempIdsTransformer`), который:
1. ✅ Фильтрует временные ID (`temp_xxx`) при валидации
2. ✅ Пропускает только реальные ID существующих GeoArea
3. ✅ Временные зоны обрабатываются отдельно в контроллере

## Архитектура решения

### 1. GeoAreaWithTempIdsTransformer

```php
class GeoAreaWithTempIdsTransformer implements DataTransformerInterface
{
    // Model → View: Collection<GeoArea> → array of IDs
    public function transform($value): array
    
    // View → Model: array of IDs → Collection<GeoArea>
    // ВАЖНО: Фильтрует temp_xxx ID!
    public function reverseTransform($value): Collection
}
```

**Ключевая логика:**
```php
// Фильтруем временные ID
$realIds = array_filter($value, function ($id) {
    return !str_starts_with($id, 'temp_');
});

// Загружаем только реальные GeoArea из БД
$geoAreas = $repository->find($realIds);
```

### 2. GeoAreaSelectionType

Подключает DataTransformer через `buildForm()`:

```php
public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder->addModelTransformer(
        new GeoAreaWithTempIdsTransformer($this->entityManager)
    );
}
```

### 3. ServiceAreaAdminController

Обрабатывает временные зоны из сессии:

```php
private function processCustomGeoAreas(ServiceArea $serviceArea): void
{
    $customAreas = $this->customGeoAreaService->getFromSession();
    
    foreach ($customAreas as $tempId => $areaData) {
        // Создаем GeoArea БЕЗ flush
        $geoArea = $this->customGeoAreaService->createFromSession($tempId);
        
        // Добавляем к ServiceArea
        $serviceArea->addGeoArea($geoArea);
    }
}
```

## Flow создания ServiceArea с кастомной зоной

### Шаг 1: Создание временной зоны
```
Пользователь рисует полигон
    ↓
Сохраняется в сессию с temp_xxx ID
    ↓
Отображается на карте
    ↓
temp_xxx добавляется в скрытое поле формы
```

### Шаг 2: Submit формы
```
Форма отправляется
    ↓
DataTransformer фильтрует temp_xxx ID
    ↓
Валидация проходит (только реальные ID)
    ↓
ServiceArea создается с реальными GeoArea
```

### Шаг 3: Обработка в контроллере
```
processCustomGeoAreas() вызывается
    ↓
Читает сессию с temp_xxx зонами
    ↓
Создает реальные GeoArea объекты (persist без flush)
    ↓
Добавляет их к ServiceArea
    ↓
admin->create() делает flush всей транзакции
    ↓
Сессия очищается после успешного сохранения
```

## Важные моменты

### 1. Временные ID никогда не валидируются
DataTransformer автоматически отфильтровывает их, поэтому валидация EntityType не проверяет их существование в БД.

### 2. Транзакционность
- ❌ НЕ делаем flush в `createFromSession()`
- ✅ Sonata Admin сам делает flush один раз для всей транзакции
- ✅ Если что-то пойдет не так - откатится вся транзакция (rollback)

### 3. Очистка сессии
- ❌ НЕ очищаем сессию в `processCustomGeoAreas()`
- ✅ Очищаем ПОСЛЕ успешного `admin->create()` / `admin->update()`
- ✅ Если сохранение провалится - данные остаются в сессии

## Пример

```php
// 1. Пользователь создает временную зону
// Сессия: ['temp_abc123' => {...geometry...}]
// Скрытое поле: ['uuid-real-city', 'temp_abc123']

// 2. Submit формы
// DataTransformer фильтрует: ['uuid-real-city'] ← temp_abc123 убран
// Валидация: OK (uuid-real-city существует в БД)

// 3. В контроллере
processCustomGeoAreas($serviceArea);
// Читает temp_abc123 из сессии
// Создает реальную GeoArea
// Добавляет к ServiceArea

// 4. Sonata Admin
$this->admin->create($serviceArea);
// Делает flush - сохраняет ServiceArea + все GeoArea

// 5. Успех
clearSession(); // Удаляем temp_abc123 из сессии
```

## Альтернативные подходы (НЕ используются)

### Вариант 1: Отключить валидацию (плохо)
```php
'constraints' => [] // Отключает все валидации
```
❌ Потеря валидации существующих зон

### Вариант 2: Создавать зоны сразу при рисовании (плохо)
```php
// В API при POST /custom-area/session
$geoArea = new GeoArea();
$em->persist($geoArea);
$em->flush(); // ← Сразу в БД
```
❌ Зоны сохраняются даже если пользователь не сохранил ServiceArea
❌ Мусорные записи в БД

### Вариант 3: DataTransformer (используется ✅)
```php
class GeoAreaWithTempIdsTransformer implements DataTransformerInterface
{
    public function reverseTransform($value): Collection
    {
        // Фильтруем temp_xxx
        // Загружаем только реальные
    }
}
```
✅ Чистое решение
✅ Транзакционность
✅ Нет мусора в БД

## Troubleshooting

### "The selected choice is invalid" все еще появляется

**Решение:**
1. Проверьте что DataTransformer подключен: `buildForm()` в `GeoAreaSelectionType`
2. Очистите кэш: `php bin/console cache:clear`
3. Проверьте логи: временные ID должны фильтроваться

### Кастомная зона не сохраняется

**Решение:**
1. Проверьте что `processCustomGeoAreas()` вызывается ДО `admin->create()`
2. Убедитесь что `createFromSession()` НЕ делает flush
3. Проверьте таблицу `geo_area` после сохранения: `SELECT * FROM geo_area WHERE scope = 3`

### Сессия не очищается

**Решение:**
Убедитесь что `clearSession()` вызывается ПОСЛЕ успешного flush, внутри try блока.

## Лицензия

SIA SLYFOX Confidential © 2026
