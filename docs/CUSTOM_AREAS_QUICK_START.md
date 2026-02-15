# Quick Start - Кастомные Географические Зоны

## Исправленная архитектура

Согласно best practices Symfony и Sonata Admin:
- **Admin класс** (`ServiceAreaAdmin`) - только представление (конфигурация форм, списков)
- **Контроллер** (`ServiceAreaAdminController`) - вся бизнес-логика обработки
- **Сервис** (`CustomGeoAreaService`) - работа с кастомными зонами

## Что было сделано

### 1. Backend

#### Убрано из ServiceAreaAdmin:
- ✅ Конструктор с зависимостями
- ✅ Методы prePersist/preUpdate  
- ✅ Вся бизнес-логика

#### Создан ServiceAreaAdminController:
- ✅ Расширяет `CRUDController`
- ✅ Инжектит `CustomGeoAreaService` через конструктор
- ✅ Переопределяет `createAction()` и `editAction()`
- ✅ Обрабатывает кастомные зоны из сессии

#### CustomGeoAreaService:
- ✅ Работа с сессией (getFromSession, persistFromSession)
- ✅ Конвертация GeoJSON → WKT для PostGIS
- ✅ Персистенция в БД

#### API эндпоинты (GeoAreaController):
- `GET /geo-area/custom-areas` - список кастомных зон
- `POST /geo-area/custom-area/session` - сохранение в сессию
- `GET /geo-area/custom-area/session/{id}` - получение из сессии
- `DELETE /geo-area/custom-area/session/{id}` - удаление из сессии

### 2. Frontend

- ✅ `custom_area_modal_controller.js` - модальное окно с Leaflet.draw
- ✅ `geo_area_map_controller.js` - интеграция с кастомными зонами
- ✅ `ApiService.js` - методы для API кастомных зон
- ✅ Шаблоны с модальным окном и селектом кастомных зон

### 3. Конфигурация

В `config/packages/sonata_admin.yaml`:

```yaml
App\Admin\ServiceAreaAdmin:
    tags:
        -   name: sonata.admin
            controller: App\Controller\Admin\ServiceAreaAdminController
            # ... другие параметры
```

## Установка

### 1. Установите зависимости:

```bash
npm install
```

### 2. Соберите frontend:

```bash
npm run build
# или для разработки
npm run watch
```

### 3. Очистите кэш:

```bash
php bin/console cache:clear
```

## Использование

### Создание ServiceArea с кастомной зоной:

1. Откройте `/admin/app/servicearea/create`
2. Перейдите на таб "Гео-зоны"
3. Выберите страну
4. Нажмите "Создать кастомную зону"
5. В модальном окне:
   - Введите название
   - Нарисуйте полигон на карте
   - Нажмите "Сохранить"
6. Кастомная зона сохранится во **временную сессию**
7. Заполните остальные поля и нажмите "Create"
8. Кастомная зона автоматически сохранится в БД

### Редактирование кастомной зоны:

1. Если создана временная кастомная зона, кнопка меняется на "Редактировать"
2. Нажмите "Редактировать кастомную зону"
3. Измените геометрию в модальном окне
4. Нажмите "Сохранить" и затем "Update"

### Удаление кастомной зоны:

1. Нажмите кнопку "Удалить" у кастомной зоны в списке
2. Если это временная зона, кнопка снова станет "Создать"

## Архитектурные принципы

✅ **Separation of Concerns**: Admin только для представления, контроллер для логики  
✅ **Dependency Injection**: Сервисы инжектятся через конструктор  
✅ **SOLID**: Каждый класс имеет одну ответственность  
✅ **Symfony Best Practices**: Следование идиомам фреймворка  
✅ **Sonata Admin Patterns**: Правильное расширение CRUDController  

## Проверка работы

```bash
# Откройте браузер
open http://localhost/admin/app/servicearea/create

# Или проверьте API
curl http://localhost/geo-area/custom-areas?countryISO3=LVA
```

## Troubleshooting

### Ошибка "Too few arguments to function __construct()"

**Решение**: Убедитесь, что Admin класс НЕ имеет конструктора. Вся логика должна быть в контроллере.

### Ошибка "Access level must be protected"

**Решение**: Не переопределяйте protected методы из CRUDController как private. Используйте родительские методы.

### Кастомная зона не сохраняется

**Решение**: Проверьте, что контроллер указан в `sonata_admin.yaml` и сервис `CustomGeoAreaService` автоматически регистрируется.

## Дополнительная документация

Полная документация: [`docs/CUSTOM_GEO_AREAS.md`](./CUSTOM_GEO_AREAS.md)
