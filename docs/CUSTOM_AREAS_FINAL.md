# Custom Geo Areas - Финальная Реализация

## Упрощенная Архитектура (Работает!)

Кастомные зоны создаются **напрямую в БД** при рисовании - простое и надежное решение.

## Возможности

### ✅ 1. Создание кастомной зоны
- Выбор страны активирует кнопку "Create Custom Area"
- Открывается модальное окно с картой и инструментами рисования
- После рисования полигон автоматически замыкается (первая точка = последняя)
- При сохранении зона создается в БД сразу с реальным UUID
- Отображается на карте с бейджем "custom"

### ✅ 2. Редактирование кастомной зоны
- У кастомных зон в списке есть кнопка "Редактировать" (желтая)
- Открывается модальное окно с загруженной геометрией
- Можно изменить полигон и название
- При сохранении обновляется в БД
- Геометрия перезагружается на карте

### ✅ 3. Удаление кастомной зоны
- Кнопка "Удалить" (красная) у всех зон
- Удаляется с карты и из списка
- При сохранении ServiceArea связь просто не создается
- Физически остается в БД (можно переиспользовать)

### ✅ 4. Автовыбор страны при редактировании
- При открытии формы редактирования ServiceArea
- Если есть кастомные зоны - автоматически выбирается их страна
- Загружаются города и кастомные зоны этой страны

## Простой Flow

### Создание ServiceArea с кастомной зоной:

```
1. Создать ServiceArea (/admin/app/servicearea/create)
2. Таб "Geo Areas"
3. Выбрать страну (Latvia)
4. Нажать "Create Custom Area"
5. Нарисовать полигон на карте
6. Ввести название ("Рижский район")
7. Нажать "Save" → зона СРАЗУ в БД
8. Добавить города если нужно
9. Заполнить остальные поля
10. Нажать "Create" → стандартное сохранение
```

### Редактирование кастомной зоны:

```
1. Открыть ServiceArea на редактирование
2. Таб "Geo Areas"
3. Найти кастомную зону в списке (с бейджем "custom")
4. Нажать желтую кнопку "Редактировать"
5. Изменить полигон или название
6. Нажать "Save" → обновится в БД
7. Нажать "Update" для сохранения ServiceArea
```

## Технические детали

### Backend (PHP)

#### API Эндпоинты:
- `POST   /api/geo-area/custom-area` - создать кастомную зону
- `PUT    /api/geo-area/custom-area/{id}` - обновить кастомную зону
- `DELETE /api/geo-area/custom-area/{id}` - удалить кастомную зону
- `GET    /api/geo-area/custom-areas?countryISO3=XXX` - список по стране
- `GET    /api/geo-area/{id}/geometry` - получить геометрию (возвращает scope)

#### Особенности:
- Геометрия конвертируется из GeoJSON в WKT для PostGIS
- Полигоны автоматически замыкаются на фронтенде
- `scope = 3` для кастомных зон
- `country_iso3` устанавливается автоматически

### Frontend (JavaScript/Stimulus)

#### custom_area_modal_controller.js
**Основные методы:**
- `open({mode, countryIso3, existingGeometry?, existingName?, existingId?})`
- `save()` - диспатчит событие `custom-area-modal:save`
- `_ensureRingClosed(ring)` - замыкает полигоны для PostGIS

**Режимы:**
- `mode: 'create'` - создание новой зоны
- `mode: 'edit'` - редактирование существующей

#### geo_area_map_controller.js
**Новые методы:**
- `openCreateCustomAreaModal()` - открыть модальное окно для создания
- `openEditCustomAreaModal()` - открыть модальное окно для редактирования
- `addCustomArea()` - добавить существующую кастомную зону из селекта

**Обработчик события:**
```javascript
document.addEventListener('custom-area-modal:save', async (event) => {
    if (event.detail.mode === 'edit') {
        // PUT запрос - обновить существующую зону
        await apiService.updateCustomArea(id, {name, geometry});
        // Перезагрузить геометрию на карте
    } else {
        // POST запрос - создать новую зону
        const response = await apiService.createCustomArea({name, geometry, countryISO3});
        // Добавить на карту с реальным UUID
    }
});
```

**Автовыбор страны:**
- При загрузке существующих зон проверяется `scope = 3`
- Зоны с `scope = 3` помечаются как `isCustomArea: true`
- В списке отображаются с бейджем "custom" и кнопкой редактирования
- Страна автоматически выбирается через `_autoSelectCountry()`

## Структура данных

### GeoArea в БД:
```sql
id: uuid
name: varchar
scope: int (1=COUNTRY, 2=CITY, 3=CUSTOM_AREA)
country_iso3: varchar
geometry: geometry(MULTIPOLYGON, 4326)
```

### GeoJSON формат (фронтенд → бэкенд):
```json
{
  "type": "MultiPolygon",
  "coordinates": [
    [
      [
        [24.1052, 56.9496],
        [24.2052, 56.9496],
        [24.2052, 57.0496],
        [24.1052, 57.0496],
        [24.1052, 56.9496]  ← замкнуто!
      ]
    ]
  ]
}
```

### WKT формат (PostGIS):
```
MULTIPOLYGON(((24.1052 56.9496, 24.2052 56.9496, 24.2052 57.0496, 24.1052 57.0496, 24.1052 56.9496)))
```

## Визуальные индикаторы

- 🔵 **Города** - голубой цвет (#3388ff), без бейджа
- 🟠 **Кастомные зоны** - оранжевый цвет (#ff8800), бейдж "custom"
- ✏️ **Кнопка редактирования** - только у кастомных зон (желтая)

## Проверка работы

### 1. Создание:
```bash
# Откройте в браузере
open http://localhost:8090/admin/app/servicearea/create

# Консоль должна показать:
[CustomAreaModal] Ring closed automatically
[ApiService] Custom area created: <uuid>
[GeoAreaMap] Custom area created with real ID: <uuid>
```

### 2. Редактирование:
```bash
# Откройте существующую ServiceArea
open http://localhost:8090/admin/app/servicearea/<id>/edit

# Консоль должна показать:
[GeoAreaMap] Loading existing areas: X
[GeoAreaMap] Custom area updated: <uuid>
```

### 3. SQL проверка:
```sql
-- Проверить созданные кастомные зоны
SELECT id, name, scope, country_iso3, ST_AsText(geometry) 
FROM geo_area 
WHERE scope = 3;
```

## Важные файлы

### Backend:
- `src/Controller/Api/GeoAreaController.php` - API для кастомных зон
- `src/Repository/GeoAreaRepository.php` - метод `findCustomAreasByCountryISO3()`
- `src/Controller/Admin/ServiceAreaAdminController.php` - стандартный (пустой)

### Frontend:
- `assets/controllers/custom_area_modal_controller.js` - модальное окно
- `assets/controllers/geo_area_map_controller.js` - интеграция
- `assets/services/ApiService.js` - HTTP методы

### Templates:
- `templates/form/custom_area_modal.html.twig` - модальное окно
- `templates/form/geo_area_selection_widget.html.twig` - виджет выбора

### Config:
- `config/packages/sonata_admin.yaml` - controller: ServiceAreaAdminController
- `package.json` - зависимости (leaflet, leaflet-draw)

## Не используются (можно удалить):
- ❌ `src/Form/DataTransformer/GeoAreaWithTempIdsTransformer.php` - удален
- ❌ `src/Service/CustomGeoAreaService.php` - удален

## Следующие улучшения

1. **Физическое удаление** кастомных зон через API при нажатии кнопки
2. **Множественные полигоны** в одной зоне
3. **Импорт GeoJSON** из файла
4. **История изменений** геометрии
5. **Права доступа** на создание/редактирование кастомных зон

## Лицензия

SIA SLYFOX Confidential © 2026
