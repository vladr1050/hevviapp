# Custom Geo Areas - Отладка

## Что было исправлено

### 1. Редактирование полигонов
**Проблема:** Полигон получал пунктирные линии но вершины не двигались

**Решение:** 
- Упрощена загрузка геометрии
- Координаты извлекаются напрямую из GeoJSON
- Создается новый `L.polygon()` из координат (не через L.geoJSON)
- Полигон добавляется в `drawnItems` → становится редактируемым

```javascript
// Извлекаем координаты из GeoJSON
const latLngs = geometry.coordinates[0][0].map(coord => [coord[1], coord[0]]);

// Создаем редактируемый полигон
const polygon = L.polygon(latLngs, {...style});

// Добавляем в drawnItems
this.drawnItems.addLayer(polygon);
```

### 2. Автовыбор страны
**Проблема:** Селектор страны оставался пустым при открытии ServiceArea с кастомными зонами

**Исправления:**
- Добавлено логирование для диагностики
- Добавлена проверка что страны загружены перед автовыбором
- Добавлена загрузка кастомных зон после выбора страны
- Добавлен повторный вызов с задержкой если страны еще не загружены

## Проверка работы

### 1. Hard Refresh браузера
**ОБЯЗАТЕЛЬНО!** Иначе загрузится старый JavaScript

- Windows/Linux: `Ctrl + Shift + R`
- Mac: `Cmd + Shift + R`

### 2. Откройте консоль браузера (F12)

### 3. Проверьте автовыбор страны

Откройте ServiceArea с кастомной зоной на редактирование.

**В консоли должно быть:**
```javascript
[GeoAreaMap] Loading area: {
  id: "...",
  name: "...",
  isCustom: true,      ← должно быть true
  countryISO3: "LVA",  ← должен быть код
  scope: 3             ← должно быть 3
}

[GeoAreaMap] Auto-select country check: {
  countriesInSet: 1,
  countries: ["LVA"],
  allAreas: [{name: "...", countryISO3: "LVA"}]
}

[GeoAreaMap] Auto-selecting country: LVA
[GeoAreaMap] Country auto-selected via Select2: Latvia
```

**Если видите:**
```javascript
countriesInSet: 0
```
→ Проблема: `countryISO3` не сохраняется в GeoAreaService

**Если видите:**
```javascript
Countries not loaded yet, waiting...
```
→ Нормально: Будет повторный вызов через 500ms

### 4. Проверьте редактирование полигона

Нажмите желтую кнопку "Редактировать" у кастомной зоны.

**В консоли должно быть:**
```javascript
[CustomAreaModal] Loading existing geometry for edit {type: "MultiPolygon", ...}
[CustomAreaModal] Existing geometry loaded and ready for editing: X points
```

**На карте должно быть:**
- Полигон с синими границами
- **Квадратные маркеры на вершинах** (можно перетаскивать)
- **Полупрозрачные квадратики на линиях** (добавить новую вершину)

**В правом верхнем углу карты:**
- ✏️ Edit layers (карандаш) - включить режим редактирования
- 💾 Save (галочка) - сохранить изменения
- ❌ Cancel (крестик) - отменить

### 5. Проверьте API эндпоинты

```bash
# Получить список стран
curl http://localhost:8090/api/geo-area/countries

# Получить кастомные зоны для Latvia
curl http://localhost:8090/api/geo-area/custom-areas?countryISO3=LVA

# Получить геометрию с scope
curl http://localhost:8090/api/geo-area/{id}/geometry
# Должно вернуть: {id, name, countryISO3, scope: 3, geometry}
```

## Что делать если не работает

### Редактирование не включается

1. Проверьте что Leaflet.draw загружен:
```javascript
console.log(typeof L.Control.Draw); // должно быть 'function'
```

2. Проверьте что полигон добавлен в drawnItems:
```javascript
console.log(modalController.drawnItems.getLayers().length); // должно быть > 0
```

3. Проверьте что drawControl инициализирован:
```javascript
console.log(modalController.drawControl); // не должно быть null
```

### Страна не выбирается автоматически

1. Проверьте что scope возвращается из API:
```bash
curl http://localhost:8090/api/geo-area/{custom-area-id}/geometry | jq .scope
# Должно вернуть: 3
```

2. Проверьте логи в консоли:
- Есть ли `countryISO3` в загруженных зонах?
- Вызывается ли `_autoSelectCountry()`?
- Есть ли страны в селекторе?

3. Проверьте что страна есть в БД:
```sql
SELECT id, name, country_iso3 
FROM geo_area 
WHERE id = '<custom-area-id>';
```

## Быстрый тест

```javascript
// В консоли браузера на странице редактирования:

// Получить контроллер
const controller = document.querySelector('[data-controller="geo-area-map"]');
const instance = Stimulus.controllers.find(c => c.element === controller);

// Проверить загруженные зоны
console.log(instance.geoAreaService.getAllAreas());

// Проверить уникальные страны
console.log(instance.geoAreaService.getUniqueCountryISO3());

// Вручную вызвать автовыбор
await instance._autoSelectCountry();
```

## Лицензия

SIA SLYFOX Confidential © 2026
