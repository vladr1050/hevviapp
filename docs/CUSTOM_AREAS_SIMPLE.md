# Custom Geo Areas - Упрощенный Подход

## Простое решение

Кастомные зоны создаются **напрямую в БД** при рисовании, без временных ID и сессий.

## Как это работает

### 1. Пользователь рисует полигон в модальном окне
```
Открыть модальное окно
    ↓
Нарисовать полигон на карте
    ↓
Ввести название
    ↓
Нажать "Сохранить"
```

### 2. Кастомная зона сразу сохраняется в БД
```javascript
// API POST /geo-area/custom-area
{
    name: "Рижский район",
    geometry: {...GeoJSON...},
    countryISO3: "LVA"
}

// Response
{
    id: "реальный-uuid",
    name: "Рижский район"
}
```

### 3. Реальный ID добавляется в форму
```
GeoArea создана в БД с реальным UUID
    ↓
ID добавляется в скрытое поле формы
    ↓
Отображается на карте
```

### 4. При сохранении ServiceArea
```
Submit формы
    ↓
Стандартная валидация Symfony (все ID реальные!)
    ↓
ServiceArea связывается с существующей GeoArea
    ↓
Готово!
```

## Архитектура

### Backend (PHP)

#### API Controller: `/geo-area/custom-area`
```php
POST   /geo-area/custom-area      - создать кастомную зону в БД
PUT    /geo-area/custom-area/{id} - обновить кастомную зону
DELETE /geo-area/custom-area/{id} - удалить кастомную зону
GET    /geo-area/custom-areas     - получить список по стране
```

#### ServiceAreaAdminController
```php
class ServiceAreaAdminController extends CRUDController
{
    // Пустой! Используется стандартная логика CRUDController
    // Кастомные зоны уже в БД, обработка не требуется
}
```

### Frontend (JavaScript)

#### ApiService
```javascript
createCustomArea({name, geometry, countryISO3})
  → POST /geo-area/custom-area
  → возвращает {id: "uuid", name: "..."}
```

#### geo_area_map_controller
```javascript
// Обработка сохранения из модального окна
document.addEventListener('custom-area-modal:save', async (event) => {
    // Создаем зону в БД
    const response = await apiService.createCustomArea({
        name: event.detail.name,
        geometry: event.detail.geometry,
        countryISO3: event.detail.countryISO3
    });
    
    // Получаем реальный UUID
    const realId = response.id;
    
    // Добавляем в форму с реальным ID
    geoAreaService.addArea(realId, {...});
    updateHiddenInput(); // Добавляем UUID в скрытое поле
});
```

## Преимущества упрощенного подхода

✅ **Нет временных ID** - только реальные UUID
✅ **Нет сессий** - все в БД сразу
✅ **Нет DataTransformer** - стандартная валидация работает
✅ **Нет сложной логики** в контроллере - стандартный CRUDController
✅ **Простота** - меньше кода, меньше багов

## Недостатки (и решения)

### "Мусорные" записи если пользователь не сохранит ServiceArea

**Решение 1:** Периодическая очистка через cron
```php
// Удаляем кастомные зоны без связей старше 24 часов
DELETE FROM geo_area 
WHERE scope = 3 
  AND id NOT IN (SELECT geo_area_id FROM service_area_geo_area)
  AND created_at < NOW() - INTERVAL '24 hours';
```

**Решение 2:** Мягкое удаление (soft delete)
```php
// Добавить поле deleted_at
// При удалении помечать вместо физического удаления
```

**Решение 3:** Ничего не делать
- Кастомные зоны занимают мало места
- Пользователь может переиспользовать их позже

## Сравнение подходов

### Старый (сложный):
```
Рисование → temp_xxx → сессия → submit формы 
→ валидация (DataTransformer фильтрует temp_xxx) 
→ обработка в контроллере → создание в БД 
→ очистка сессии
```

### Новый (простой):
```
Рисование → создание в БД → реальный UUID 
→ submit формы → стандартная валидация 
→ стандартное сохранение
```

## Файлы

### Основные:
- `src/Controller/Api/GeoAreaController.php` - API для кастомных зон
- `src/Form/DataTransformer/GeoAreaWithTempIdsTransformer.php` - НЕ ИСПОЛЬЗУЕТСЯ (можно удалить)
- `src/Service/CustomGeoAreaService.php` - НЕ ИСПОЛЬЗУЕТСЯ (можно удалить)
- `src/Controller/Admin/ServiceAreaAdminController.php` - пустой, стандартный CRUDController
- `assets/controllers/custom_area_modal_controller.js` - модальное окно
- `assets/services/ApiService.js` - методы API
- `assets/controllers/geo_area_map_controller.js` - интеграция

### Можно удалить (не используются):
- `src/Form/DataTransformer/GeoAreaWithTempIdsTransformer.php`
- `src/Service/CustomGeoAreaService.php`

## Использование

1. Откройте `/admin/app/servicearea/create`
2. Перейдите на таб "Гео-зоны"
3. Выберите страну
4. Нажмите "Создать кастомную зону"
5. Нарисуйте полигон и введите название
6. Нажмите "Сохранить" → **зона сразу в БД**
7. Заполните остальные поля ServiceArea
8. Нажмите "Create" → **стандартное сохранение**

## Результат

**Простое, чистое решение** следующее идиомам Symfony и Sonata Admin:
- Стандартная валидация форм
- Стандартный CRUDController
- Минимум кастомной логики
- Максимальная надежность

🎉 **Все работает просто и понятно!**
