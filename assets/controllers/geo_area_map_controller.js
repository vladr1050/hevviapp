import { Controller } from '@hotwired/stimulus';
import { MapService } from '../services/MapService.js';
import { ApiService } from '../services/ApiService.js';
import { GeoAreaService } from '../services/GeoAreaService.js';

/**
 * GeoArea Map Controller
 *
 * Управляет интерактивной картой для выбора гео-зон с фильтрацией по странам и городам.
 *
 * Следует принципам SOLID:
 * - Single Responsibility: координирует работу UI и сервисов
 * - Open/Closed: расширяемый через values и сервисы
 * - Liskov Substitution: может быть заменён другим контроллером
 * - Interface Segregation: использует только нужные методы сервисов
 * - Dependency Inversion: зависит от абстракций (сервисов), а не конкретных реализаций
 *
 * Использует композицию сервисов:
 * - MapService: управление картой Leaflet
 * - ApiService: HTTP запросы к API
 * - GeoAreaService: управление состоянием зон
 *
 * Интеграция с Select2 через jQuery события.
 */
export default class extends Controller {
    static values = {
        apiCountriesUrl: String,
        apiCitiesUrl: String,
        apiGeometryUrl: String,
        apiCustomAreasUrl: String,
        apiCustomAreaCreateUrl: String,
        defaultLat: Number,
        defaultLng: Number,
        defaultZoom: Number,
        selectedAreasJson: String,
        // Translations
        translationNoCountry: String,
        translationNoCity: String,
        translationLoadingCities: String,
        translationErrorLoadCountries: String,
        translationErrorLoadCities: String,
        translationErrorLoadGeometry: String,
        translationErrorAreaAlreadyAdded: String,
        translationAddButton: String,
        translationRemoveButton: String,
        // Custom Area Translations
        translationCustomAreaLoading: String,
        translationCustomAreaPlaceholder: String,
        translationCustomAreaCreated: String,
        translationCustomAreaUpdated: String,
        translationCustomAreaSaveError: String,
        translationCustomAreaSelectCountry: String,
        translationCustomAreaModalNotFound: String,
        translationCustomAreaControllerNotFound: String,
        translationCustomAreaEditError: String,
        translationCustomBadge: String,
        translationEditButton: String,
        translationCreateCustomButton: String,
    }

    static targets = [
        'map',
        'countrySelect',
        'citySelect',
        'citySelectWrapper',
        'addButton',
        'customAreaSelect',
        'customAreaSelectWrapper',
        'addCustomAreaButton',
        'createCustomAreaButton',
        'selectedList',
        'hiddenInput'
    ]

    /**
     * Инициализация контроллера
     */
    connect() {
        // Инициализация сервисов (Dependency Inversion Principle)
        this.mapService = new MapService({
            container: this.mapTarget,
            defaultLat: this.defaultLatValue,
            defaultLng: this.defaultLngValue,
            defaultZoom: this.defaultZoomValue,
            interactive: true
        });

        this.apiService = new ApiService({
            countriesUrl: this.apiCountriesUrlValue,
            citiesUrl: this.apiCitiesUrlValue,
            geometryUrl: this.apiGeometryUrlValue,
            customAreasUrl: this.apiCustomAreasUrlValue,
            customAreaCreateUrl: this.apiCustomAreaCreateUrlValue
        });

        this.geoAreaService = new GeoAreaService();

        // Состояние UI
        this.currentCountryISO3 = null;

        // Инициализация
        this.mapService.initialize();
        this._loadCountries();
        this._setupSelect2Listeners();
        this._setupCustomAreaModalListener();
        this._loadExistingAreas();
    }

    /**
     * Очистка ресурсов при удалении контроллера
     */
    disconnect() {
        // Отписываемся от jQuery событий
        if (window.jQuery) {
            jQuery(this.countrySelectTarget).off('select2:select.geoAreaMap change.geoAreaMap');
            jQuery(this.citySelectTarget).off('select2:select.geoAreaMap change.geoAreaMap');
        }

        // Очищаем сервисы
        if (this.mapService) {
            this.mapService.destroy();
            this.mapService = null;
        }

        if (this.geoAreaService) {
            this.geoAreaService.clear();
            this.geoAreaService = null;
        }

        this.apiService = null;
    }

    /**
     * Настройка слушателей Select2 событий
     * Select2 использует jQuery и триггерит события через jQuery
     * @private
     */
    _setupSelect2Listeners() {
        // Проверяем наличие jQuery (используется Sonata Admin)
        if (typeof window.jQuery === 'undefined') {
            console.error('[GeoAreaMap] jQuery not found. Select2 events will not work.');
            return;
        }

        const $ = window.jQuery;

        // Слушаем изменения в селекте страны
        // Используем select2:select для надёжной работы с Select2
        // Также слушаем change для обратной совместимости
        $(this.countrySelectTarget)
            .on('select2:select.geoAreaMap', (e) => {
                this._handleCountryChange(e.params.data);
            })
            .on('change.geoAreaMap', (e) => {
                // Фолбек для случаев когда Select2 не инициализирован
                if (!$(e.target).hasClass('select2-hidden-accessible')) {
                    const selectedOption = e.target.options[e.target.selectedIndex];
                    if (selectedOption) {
                        this._handleCountryChange({
                            id: selectedOption.value,
                            element: $(selectedOption)
                        });
                    }
                }
            });

        // Слушаем изменения в селекте города
        $(this.citySelectTarget)
            .on('select2:select.geoAreaMap', (e) => {
                this._handleCityChange(e.params.data);
            })
            .on('change.geoAreaMap', (e) => {
                // Фолбек для случаев когда Select2 не инициализирован
                if (!$(e.target).hasClass('select2-hidden-accessible')) {
                    const cityId = e.target.value;
                    this.addButtonTarget.disabled = !cityId;
                }
            });

        console.log('[GeoAreaMap] Select2 listeners configured');
    }

    /**
     * Обработка изменения выбранной страны
     * @private
     * @param {Object} data - Данные от Select2 {id, text, element}
     */
    async _handleCountryChange(data) {
        const countryId = data.id;

        if (!countryId) {
            this._resetCitySelect();
            return;
        }

        // Получаем ISO3 код из data-атрибута option элемента
        // ВАЖНО: data.element возвращает нативный DOM элемент, нужно обернуть в jQuery
        let $option;
        if (data.element) {
            // Оборачиваем нативный элемент в jQuery
            $option = window.jQuery(data.element);
        } else {
            // Фолбек: ищем опцию по значению
            $option = window.jQuery(this.countrySelectTarget).find(`option[value="${countryId}"]`);
        }

        this.currentCountryISO3 = $option.data('iso3') || $option.attr('data-iso3');

        console.log('[GeoAreaMap] Country selected:', {
            id: countryId,
            iso3: this.currentCountryISO3,
            element: data.element
        });

        await this._loadCities(this.currentCountryISO3);
        await this._loadCustomAreas(this.currentCountryISO3);

        // Активируем кнопку создания кастомной зоны
        if (this.hasCreateCustomAreaButtonTarget) {
            this.createCustomAreaButtonTarget.disabled = false;
        }
    }

    /**
     * Обработка изменения выбранного города
     * @private
     * @param {Object} data - Данные от Select2 {id, text}
     */
    _handleCityChange(data) {
        const cityId = data.id;
        this.addButtonTarget.disabled = !cityId;

        console.log('[GeoAreaMap] City selected:', cityId);
    }

    /**
     * Обработчик изменения выбранной страны (для фолбека без Select2)
     * Оставлено для обратной совместимости
     */
    async onCountryChange(event) {
        console.warn('[GeoAreaMap] onCountryChange called directly. This should not happen if Select2 is working.');
        const countryId = event.target.value;

        if (!countryId) {
            this._resetCitySelect();
            this._resetCustomAreaSelect();
            return;
        }

        const selectedOption = event.target.options[event.target.selectedIndex];
        this.currentCountryISO3 = selectedOption.dataset.iso3;

        await this._loadCities(this.currentCountryISO3);
        await this._loadCustomAreas(this.currentCountryISO3);
    }

    /**
     * Обработчик изменения выбранного города (для фолбека без Select2)
     * Оставлено для обратной совместимости
     */
    onCityChange(event) {
        console.warn('[GeoAreaMap] onCityChange called directly. This should not happen if Select2 is working.');
        const cityId = event.target.value;
        this.addButtonTarget.disabled = !cityId;
    }

    /**
     * Добавление выбранной гео-зоны на карту
     */
    async addGeoArea(event) {
        event.preventDefault();

        const cityId = this.citySelectTarget.value;
        if (!cityId) return;

        // Проверка дубликата через GeoAreaService
        if (this.geoAreaService.hasArea(cityId)) {
            console.warn('[GeoAreaMap] Area already added:', cityId);
            alert(this.translationErrorAreaAlreadyAddedValue || 'Эта зона уже добавлена');
            return;
        }

        const cityName = this.citySelectTarget.options[this.citySelectTarget.selectedIndex].text;

        console.log('[GeoAreaMap] Adding area:', {id: cityId, name: cityName});

        // Добавляем в GeoAreaService
        const areaData = {
            id: cityId,
            name: cityName,
            countryISO3: this.currentCountryISO3,
        };

        this.geoAreaService.addArea(cityId, areaData);

        // Загружаем геометрию и отображаем на карте (через сервисы)
        await this._loadAndDisplayGeometry(cityId);

        // Обновляем список
        this._updateSelectedList();

        // Обновляем скрытое поле
        this._updateHiddenInput();

        // Отключаем эту опцию в селекте
        this._disableCityOption(cityId);

        // Сбрасываем выбор города и обновляем Select2
        this._resetCitySelection();
    }

    /**
     * Удаление гео-зоны из списка и с карты
     */
    removeGeoArea(event) {
        const areaId = event.currentTarget.dataset.areaId;
        const isCustomArea = event.currentTarget.dataset.isCustomArea === 'true';

        console.log('[GeoAreaMap] Removing area:', areaId, 'isCustomArea:', isCustomArea);

        // Удаляем с карты через MapService
        this.mapService.removeLayer(areaId);

        // Удаляем из GeoAreaService
        this.geoAreaService.removeArea(areaId);

        // Включаем эту опцию обратно в селекте
        if (isCustomArea) {
            this._enableCustomAreaOption(areaId);
        } else {
            this._enableCityOption(areaId);
        }

        // Обновляем отображение
        this._updateSelectedList();
        this._updateHiddenInput();

        // Обновляем текст кнопки если удалили кастомную зону
        if (isCustomArea) {
            this._updateCreateCustomAreaButtonText();
        }

        // Автозуминг на оставшиеся элементы через MapService
        this.mapService.fitToAllLayers({
            padding: [50, 50],
            maxZoom: 12,
            animate: true
        });
    }

    /**
     * Добавление выбранной кастомной зоны на карту
     */
    async addCustomArea(event) {
        event.preventDefault();

        const customAreaId = this.customAreaSelectTarget.value;
        if (!customAreaId) return;

        // Проверка дубликата через GeoAreaService
        if (this.geoAreaService.hasArea(customAreaId)) {
            console.warn('[GeoAreaMap] Custom area already added:', customAreaId);
            alert(this.translationErrorAreaAlreadyAddedValue || 'Эта зона уже добавлена');
            return;
        }

        const customAreaName = this.customAreaSelectTarget.options[this.customAreaSelectTarget.selectedIndex].text;

        console.log('[GeoAreaMap] Adding custom area:', {id: customAreaId, name: customAreaName});

        // Добавляем в GeoAreaService
        const areaData = {
            id: customAreaId,
            name: customAreaName,
            countryISO3: this.currentCountryISO3,
            isCustomArea: true,
        };

        this.geoAreaService.addArea(customAreaId, areaData);

        // Загружаем геометрию и отображаем на карте
        await this._loadAndDisplayGeometry(customAreaId);

        // Обновляем список
        this._updateSelectedList();

        // Обновляем скрытое поле
        this._updateHiddenInput();

        // Отключаем эту опцию в селекте
        this._disableCustomAreaOption(customAreaId);

        // Сбрасываем выбор кастомной зоны
        this._resetCustomAreaSelection();
    }

    /**
     * Открыть модальное окно для создания новой кастомной зоны
     */
    openCreateCustomAreaModal(event) {
        event.preventDefault();

        if (!this.currentCountryISO3) {
            alert(this.translationCustomAreaSelectCountryValue);
            return;
        }

        console.log('[GeoAreaMap] Opening create custom area modal');

        // Получаем контроллер модального окна
        const modalElement = document.getElementById('customAreaModal');
        if (!modalElement) {
            console.error('[GeoAreaMap] Modal element not found');
            alert(this.translationCustomAreaModalNotFoundValue);
            return;
        }

        const modalController = this.application.getControllerForElementAndIdentifier(
            modalElement,
            'custom-area-modal'
        );

        if (!modalController) {
            console.error('[GeoAreaMap] Modal controller not found');
            alert(this.translationCustomAreaControllerNotFoundValue);
            return;
        }

        // Открываем модальное окно
        modalController.open({
            mode: 'create',
            countryIso3: this.currentCountryISO3,
        });
    }

    /**
     * Открыть модальное окно для редактирования кастомной зоны
     */
    async openEditCustomAreaModal(event) {
        event.preventDefault();

        const areaId = event.currentTarget.dataset.areaId;
        const area = this.geoAreaService.getArea(areaId);

        if (!area) {
            console.error('[GeoAreaMap] Area not found:', areaId);
            return;
        }

        console.log('[GeoAreaMap] Opening edit custom area modal for:', areaId);

        // Загружаем геометрию из API
        try {
            const data = await this.apiService.getGeometry(areaId);

            // Получаем контроллер модального окна
            const modalElement = document.getElementById('customAreaModal');
            if (!modalElement) {
                console.error('[GeoAreaMap] Modal element not found');
                return;
            }

            const modalController = this.application.getControllerForElementAndIdentifier(
                modalElement,
                'custom-area-modal'
            );

            if (!modalController) {
                console.error('[GeoAreaMap] Modal controller not found');
                return;
            }

            // Открываем модальное окно в режиме редактирования
            modalController.open({
                mode: 'edit',
                countryIso3: area.countryISO3,
                existingGeometry: JSON.stringify(data.geometry),
                existingName: area.name,
                existingId: areaId,
            });
        } catch (error) {
            console.error('[GeoAreaMap] Error loading geometry for edit:', error);
            alert(this.translationCustomAreaEditErrorValue);
        }
    }

    /**
     * Инициализация карты Leaflet
     * @private
     */
    _initializeMap() {
        this.map = L.map(this.mapTarget).setView(
            [this.defaultLatValue, this.defaultLngValue],
            this.defaultZoomValue
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);
    }

    /**
     * Загрузка списка стран через ApiService
     * @private
     */
    async _loadCountries() {
        try {
            const countries = await this.apiService.getCountries();

            // Очищаем селект
            this.countrySelectTarget.innerHTML = `<option value="">${this.translationNoCountryValue}</option>`;

            // Добавляем страны с data-iso3 атрибутом
            countries.forEach(country => {
                const option = document.createElement('option');
                option.value = country.id;
                option.textContent = country.name;
                option.setAttribute('data-iso3', country.countryISO3);
                option.dataset.iso3 = country.countryISO3;
                this.countrySelectTarget.appendChild(option);
            });

            // Обновляем Select2 если инициализирован
            this._updateSelect2(this.countrySelectTarget);

        } catch (error) {
            console.error('[GeoAreaMap] Error loading countries:', error);
            alert(this.translationErrorLoadCountriesValue);
        }
    }

    /**
     * Загрузка списка городов через ApiService
     * @private
     */
    async _loadCities(countryISO3) {
        console.log('[GeoAreaMap] Loading cities for country:', countryISO3);

        // Показываем индикатор загрузки
        this.citySelectTarget.innerHTML = `<option value="">${this.translationLoadingCitiesValue}</option>`;
        this.citySelectTarget.disabled = true;
        this.addButtonTarget.disabled = true;
        this._updateSelect2(this.citySelectTarget);

        try {
            const cities = await this.apiService.getCities(countryISO3);

            // Очищаем селект
            this.citySelectTarget.innerHTML = `<option value="">${this.translationNoCityValue}</option>`;

            // Добавляем города
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                this.citySelectTarget.appendChild(option);
            });

            // ВАЖНО: Активируем селект
            this.citySelectTarget.disabled = false;

            console.log('[GeoAreaMap] City select enabled, options:', this.citySelectTarget.options.length);

            // Отключаем уже добавленные города
            this._updateCityOptionsState();

            // Обновляем Select2
            this._updateSelect2(this.citySelectTarget);

        } catch (error) {
            console.error('[GeoAreaMap] Error loading cities:', error);
            alert(this.translationErrorLoadCitiesValue);
            this._resetCitySelect();
        }
    }

    /**
     * Загрузка списка кастомных зон через ApiService
     * @private
     */
    async _loadCustomAreas(countryISO3) {
        console.log('[GeoAreaMap] Loading custom areas for country:', countryISO3);

        if (!this.hasCustomAreaSelectTarget) {
            console.warn('[GeoAreaMap] customAreaSelectTarget not found');
            return;
        }

        console.log('[GeoAreaMap] customAreaSelect element:', this.customAreaSelectTarget);

        // Показываем индикатор загрузки
        this.customAreaSelectTarget.innerHTML = `<option value="">${this.translationCustomAreaLoadingValue}</option>`;
        this.customAreaSelectTarget.disabled = true;
        if (this.hasAddCustomAreaButtonTarget) {
            this.addCustomAreaButtonTarget.disabled = true;
        }
        this._updateSelect2(this.customAreaSelectTarget);

        try {
            const customAreas = await this.apiService.getCustomAreas(countryISO3);

            console.log('[GeoAreaMap] Custom areas loaded:', customAreas.length);

            // Очищаем селект
            this.customAreaSelectTarget.innerHTML = `<option value="">${this.translationCustomAreaPlaceholderValue}</option>`;

            // Добавляем кастомные зоны
            customAreas.forEach(area => {
                const option = document.createElement('option');
                option.value = area.id;
                option.textContent = area.name;
                this.customAreaSelectTarget.appendChild(option);
            });

            // ВАЖНО: Активируем селект
            this.customAreaSelectTarget.disabled = false;

            console.log('[GeoAreaMap] customAreaSelect enabled, options count:', this.customAreaSelectTarget.options.length);

            // Отключаем уже добавленные кастомные зоны
            this._updateCustomAreaOptionsState();

            // Обновляем Select2
            this._updateSelect2(this.customAreaSelectTarget);

        } catch (error) {
            console.error('[GeoAreaMap] Error loading custom areas:', error);
            this._resetCustomAreaSelect();
        }
    }

    /**
     * Загрузка и отображение геометрии через сервисы
     * @private
     * @param {string} areaId - UUID зоны
     * @param {boolean} autoZoom - Автоматически зумить после добавления (по умолчанию true)
     */
    async _loadAndDisplayGeometry(areaId, autoZoom = true) {
        try {
            // Загружаем геометрию через ApiService
            const data = await this.apiService.getGeometry(areaId);

            // Добавляем на карту через MapService
            this.mapService.addGeoJsonLayer(areaId, data.geometry, {
                color: '#3388ff',
                weight: 2,
                opacity: 0.8,
                fillOpacity: 0.3,
                popupContent: data.name
            });

            // Автозуминг только если явно запрошен
            if (autoZoom) {
                // Небольшая задержка для корректной отрисовки слоя
                setTimeout(() => {
                    this.mapService.fitToAllLayers({
                        padding: [50, 50],
                        maxZoom: 12,
                        animate: true
                    });
                }, 50);
            }

        } catch (error) {
            console.error('[GeoAreaMap] Error loading geometry:', error);
            alert(this.translationErrorLoadGeometryValue);
        }
    }

    /**
     * Загрузка существующих гео-зон из скрытого поля
     * @private
     */
    async _loadExistingAreas() {
        if (!this.hasHiddenInputTarget) return;

        // Находим select внутри hiddenInput
        const selectElement = this.hiddenInputTarget.querySelector('select');
        if (!selectElement) return;

        const selectedOptions = Array.from(selectElement.selectedOptions);
        if (selectedOptions.length === 0) return;

        console.log('[GeoAreaMap] Loading existing areas:', selectedOptions.length);

        // Собираем уникальные ISO3 коды стран из загруженных зон
        const countryISO3Set = new Set();

        // Загружаем ВСЕ зоны последовательно БЕЗ зума
        for (const option of selectedOptions) {
            const areaId = option.value;
            const areaName = option.textContent;

            try {
                const data = await this.apiService.getGeometry(areaId);

                // Определяем является ли зона кастомной (scope = 3)
                const isCustomArea = data.scope === 3;

                console.log('[GeoAreaMap] Loading area:', {
                    id: areaId,
                    name: areaName,
                    isCustom: isCustomArea,
                    countryISO3: data.countryISO3,
                    scope: data.scope
                });

                const areaData = {
                    id: areaId,
                    name: areaName,
                    countryISO3: data.countryISO3 || '',
                    isCustomArea: isCustomArea,
                };

                // Добавляем через GeoAreaService
                this.geoAreaService.addArea(areaId, areaData);

                // Собираем ISO3 коды
                if (data.countryISO3) {
                    countryISO3Set.add(data.countryISO3);
                }

                // Загружаем геометрию БЕЗ автозума
                await this._loadGeometryWithoutZoom(areaId, data);

            } catch (error) {
                console.error('[GeoAreaMap] Error loading existing area:', error);
            }
        }

        console.log('[GeoAreaMap] All areas loaded, count:', this.mapService.getLayersCount());

        this._updateSelectedList();

        // ВАЖНО: Обновляем скрытое поле после загрузки существующих зон
        // Иначе при submit формы Symfony думает что все зоны удалены
        this._updateHiddenInput();

        // Зумим ОДИН РАЗ на все загруженные элементы с задержкой
        // Задержка нужна чтобы все слои успели отрисоваться
        setTimeout(() => {
            const layersCount = this.mapService.getLayersCount();
            if (layersCount > 0) {
                this.mapService.fitToAllLayers({
                    padding: [50, 50],
                    maxZoom: 12,
                    animate: false
                });
                console.log('[GeoAreaMap] Zoomed to all loaded layers:', layersCount);
            }
        }, 200);  // 200ms задержка для отрисовки

        // Автоматически выбираем страну (используя GeoAreaService для получения ISO3)
        await this._autoSelectCountry();
    }

    /**
     * Загрузка геометрии без автозума через MapService
     * @private
     */
    async _loadGeometryWithoutZoom(areaId, data) {
        // Добавляем на карту через MapService
        this.mapService.addGeoJsonLayer(areaId, data.geometry, {
            color: '#3388ff',
            weight: 2,
            opacity: 0.8,
            fillOpacity: 0.3,
            popupContent: data.name
        });
    }

    /**
     * Обновление визуального списка выбранных зон
     * @private
     */
    _updateSelectedList() {
        // Получаем массив через GeoAreaService (уже в обратном порядке)
        const areas = this.geoAreaService.getAreasReversed();

        if (areas.length === 0) {
            this.selectedListTarget.innerHTML = '<div class="text-muted small">Нет выбранных зон</div>';
            return;
        }

        this.selectedListTarget.innerHTML = areas.map(area => {
            const isCustomArea = area.isCustomArea || false;
            const badge = isCustomArea ? `<span class="badge bg-info text-white ms-2" style="margin-left: 7px">${this.translationCustomBadgeValue}</span>` : '';

            // Кнопка редактирования только для кастомных зон
            const editButton = isCustomArea ? `
                <button type="button"
                        class="btn btn-sm btn-warning me-1"
                        style="margin-right: 7px;"
                        data-area-id="${area.id}"
                        data-action="geo-area-map#openEditCustomAreaModal"
                        title="${this.translationEditButtonValue}">
                    <i class="fas fa-edit"></i>
                </button>
            ` : '';

            return `
                <div class="selected-area-item d-flex justify-content-between align-items-center mb-2 p-2 border rounded bg-light">
                    <span class="selected-area-name">${this._escapeHtml(area.name)}${badge}</span>
                    <div>
                        ${editButton}
                        <button type="button"
                                class="btn btn-sm btn-danger"
                                data-area-id="${area.id}"
                                data-is-custom-area="${isCustomArea}"
                                data-action="geo-area-map#removeGeoArea"
                                title="${this.translationRemoveButtonValue}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Обновление скрытого поля с выбранными ID
     * @private
     */
    _updateHiddenInput() {
        if (!this.hasHiddenInputTarget) return;

        // Находим select внутри hiddenInput
        const selectElement = this.hiddenInputTarget.querySelector('select');
        if (!selectElement) return;

        // Очищаем все опции
        Array.from(selectElement.options).forEach(option => {
            option.selected = false;
        });

        // Выбираем нужные опции через GeoAreaService
        this.geoAreaService.getAllAreas().forEach(area => {
            const areaId = area.id;
            const option = selectElement.querySelector(`option[value="${areaId}"]`);
            if (option) {
                option.selected = true;
            } else {
                // Если опции нет, создаем её
                const newOption = document.createElement('option');
                newOption.value = areaId;
                newOption.textContent = area.name;
                newOption.selected = true;
                selectElement.appendChild(newOption);
            }
        });

        // Триггерим событие change для валидации Sonata
        selectElement.dispatchEvent(new Event('change', { bubbles: true }));
    }

    /**
     * Сброс селекта городов
     * @private
     */
    _resetCitySelect() {
        this.citySelectTarget.innerHTML = `<option value="">${this.translationNoCityValue}</option>`;
        this.citySelectTarget.disabled = true;
        this.addButtonTarget.disabled = true;
        this.currentCountryISO3 = null;

        // Обновляем Select2 после сброса
        if (window.jQuery && window.jQuery(this.citySelectTarget).hasClass('select2-hidden-accessible')) {
            window.jQuery(this.citySelectTarget).trigger('change.select2');
            console.log('[GeoAreaMap] City select reset');
        }
    }

    /**
     * Сброс выбора города (очистка селекта с обновлением Select2)
     * @private
     */
    _resetCitySelection() {
        this.citySelectTarget.value = '';
        this.addButtonTarget.disabled = true;

        // Обновляем Select2 чтобы показать placeholder
        if (window.jQuery && window.jQuery(this.citySelectTarget).hasClass('select2-hidden-accessible')) {
            window.jQuery(this.citySelectTarget).val('').trigger('change.select2');
            console.log('[GeoAreaMap] City selection reset');
        }
    }

    /**
     * Отключить опцию города в селекте (уже добавлен)
     * @private
     * @param {string} cityId - ID города
     */
    _disableCityOption(cityId) {
        const option = this.citySelectTarget.querySelector(`option[value="${cityId}"]`);
        if (option) {
            option.disabled = true;
            console.log('[GeoAreaMap] Disabled city option:', cityId);

            // Обновляем Select2
            if (window.jQuery && window.jQuery(this.citySelectTarget).hasClass('select2-hidden-accessible')) {
                window.jQuery(this.citySelectTarget).trigger('change.select2');
            }
        }
    }

    /**
     * Включить опцию города в селекте (удалён из списка)
     * @private
     * @param {string} cityId - ID города
     */
    _enableCityOption(cityId) {
        const option = this.citySelectTarget.querySelector(`option[value="${cityId}"]`);
        if (option) {
            option.disabled = false;
            console.log('[GeoAreaMap] Enabled city option:', cityId);

            // Обновляем Select2
            if (window.jQuery && window.jQuery(this.citySelectTarget).hasClass('select2-hidden-accessible')) {
                window.jQuery(this.citySelectTarget).trigger('change.select2');
            }
        }
    }

    /**
     * Обновить состояние всех опций городов через GeoAreaService
     * @private
     */
    _updateCityOptionsState() {
        const options = this.citySelectTarget.querySelectorAll('option:not([value=""])');
        options.forEach(option => {
            const cityId = option.value;
            // Проверяем через GeoAreaService
            option.disabled = cityId && this.geoAreaService.hasArea(cityId);
        });

        console.log('[GeoAreaMap] Updated city options state:', {
            total: options.length,
            disabled: Array.from(options).filter(o => o.disabled).length
        });
    }

    /**
     * Сброс селекта кастомных зон
     * @private
     */
    _resetCustomAreaSelect() {
        if (!this.hasCustomAreaSelectTarget) {
            return;
        }

        this.customAreaSelectTarget.innerHTML = `<option value="">${this.translationCustomAreaPlaceholderValue}</option>`;
        this.customAreaSelectTarget.disabled = true;

        if (this.hasAddCustomAreaButtonTarget) {
            this.addCustomAreaButtonTarget.disabled = true;
        }

        // Обновляем Select2 после сброса
        if (window.jQuery && window.jQuery(this.customAreaSelectTarget).hasClass('select2-hidden-accessible')) {
            window.jQuery(this.customAreaSelectTarget).trigger('change.select2');
        }
    }

    /**
     * Сброс выбора кастомной зоны (очистка селекта с обновлением Select2)
     * @private
     */
    _resetCustomAreaSelection() {
        if (!this.hasCustomAreaSelectTarget) {
            return;
        }

        this.customAreaSelectTarget.value = '';

        if (this.hasAddCustomAreaButtonTarget) {
            this.addCustomAreaButtonTarget.disabled = true;
        }

        // Обновляем Select2 чтобы показать placeholder
        if (window.jQuery && window.jQuery(this.customAreaSelectTarget).hasClass('select2-hidden-accessible')) {
            window.jQuery(this.customAreaSelectTarget).val('').trigger('change.select2');
        }
    }

    /**
     * Отключить опцию кастомной зоны в селекте (уже добавлена)
     * @private
     * @param {string} customAreaId - ID кастомной зоны
     */
    _disableCustomAreaOption(customAreaId) {
        if (!this.hasCustomAreaSelectTarget) {
            return;
        }

        const option = this.customAreaSelectTarget.querySelector(`option[value="${customAreaId}"]`);
        if (option) {
            option.disabled = true;

            // Обновляем Select2
            if (window.jQuery && window.jQuery(this.customAreaSelectTarget).hasClass('select2-hidden-accessible')) {
                window.jQuery(this.customAreaSelectTarget).trigger('change.select2');
            }
        }
    }

    /**
     * Включить опцию кастомной зоны в селекте (удалена из списка)
     * @private
     * @param {string} customAreaId - ID кастомной зоны
     */
    _enableCustomAreaOption(customAreaId) {
        if (!this.hasCustomAreaSelectTarget) {
            return;
        }

        const option = this.customAreaSelectTarget.querySelector(`option[value="${customAreaId}"]`);
        if (option) {
            option.disabled = false;

            // Обновляем Select2
            if (window.jQuery && window.jQuery(this.customAreaSelectTarget).hasClass('select2-hidden-accessible')) {
                window.jQuery(this.customAreaSelectTarget).trigger('change.select2');
            }
        }
    }

    /**
     * Обновить состояние всех опций кастомных зон через GeoAreaService
     * @private
     */
    _updateCustomAreaOptionsState() {
        if (!this.hasCustomAreaSelectTarget) {
            return;
        }

        const options = this.customAreaSelectTarget.querySelectorAll('option:not([value=""])');
        options.forEach(option => {
            const areaId = option.value;
            // Проверяем через GeoAreaService
            option.disabled = areaId && this.geoAreaService.hasArea(areaId);
        });

        console.log('[GeoAreaMap] Updated custom area options state:', {
            total: options.length,
            disabled: Array.from(options).filter(o => o.disabled).length
        });

        // ВАЖНО: Обновляем Select2 после изменения состояния опций
        this._updateSelect2(this.customAreaSelectTarget);
    }

    /**
     * Настройка слушателя событий модального окна кастомных зон
     * @private
     */
    _setupCustomAreaModalListener() {
        // Слушаем событие сохранения из модального окна
        document.addEventListener('custom-area-modal:save', async (event) => {
            const detail = event.detail;

            console.log('[GeoAreaMap] Custom area modal save event:', detail);

            try {
                let response;

                // Если есть ID - обновляем существующую зону, иначе создаем новую
                if (detail.mode === 'edit' && detail.id) {
                    // Обновление существующей зоны
                    response = await this.apiService.updateCustomArea(detail.id, {
                        name: detail.name,
                        geometry: detail.geometry,
                    });

                    console.log('[GeoAreaMap] Custom area updated:', response.id);

                    // Перезагружаем геометрию на карте
                    this.mapService.removeLayer(detail.id);
                    await this._loadAndDisplayGeometry(detail.id);

                    // Обновляем данные в GeoAreaService
                    const areaData = this.geoAreaService.getArea(detail.id);
                    if (areaData) {
                        areaData.name = detail.name;
                    }

                    // Обновляем список
                    this._updateSelectedList();

                    alert(this.translationCustomAreaUpdatedValue);

                } else {
                    // Создание новой зоны напрямую в БД
                    response = await this.apiService.createCustomArea({
                        name: detail.name,
                        geometry: detail.geometry,
                        countryISO3: detail.countryISO3,
                    });

                    const realId = response.id;

                    console.log('[GeoAreaMap] Custom area created with real ID:', realId);

                    // Добавляем в GeoAreaService с реальным ID
                    const areaData = {
                        id: realId,
                        name: detail.name,
                        countryISO3: detail.countryISO3,
                        isCustomArea: true,
                    };

                    this.geoAreaService.addArea(realId, areaData);

                    // Отображаем на карте
                    this.mapService.addGeoJsonLayer(realId, detail.geometry, {
                        color: '#ff8800',
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.3,
                        popupContent: detail.name
                    });

                    // Зумим на геометрию
                    setTimeout(() => {
                        this.mapService.fitToAllLayers({
                            padding: [50, 50],
                            maxZoom: 12,
                            animate: true
                        });
                    }, 50);

                    // Обновляем список
                    this._updateSelectedList();

                    // Обновляем скрытое поле
                    this._updateHiddenInput();

                    // Перезагружаем список кастомных зон (чтобы новая появилась в селекте)
                    await this._loadCustomAreas(detail.countryISO3);

                    // Обновляем текст кнопки с количеством созданных зон
                    this._updateCreateCustomAreaButtonText();

                    alert(this.translationCustomAreaCreatedValue);
                }

            } catch (error) {
                console.error('[GeoAreaMap] Error saving custom area:', error);
                alert(this.translationCustomAreaSaveErrorValue + ': ' + error.message);
            }
        });

        // Настройка слушателей Select2 для кастомных зон
        if (this.hasCustomAreaSelectTarget && typeof window.jQuery !== 'undefined') {
            const $ = window.jQuery;

            $(this.customAreaSelectTarget)
                .on('select2:select.geoAreaMap', (e) => {
                    const customAreaId = e.params.data.id;
                    if (this.hasAddCustomAreaButtonTarget) {
                        this.addCustomAreaButtonTarget.disabled = !customAreaId;
                    }
                })
                .on('change.geoAreaMap', (e) => {
                    if (!$(e.target).hasClass('select2-hidden-accessible')) {
                        const customAreaId = e.target.value;
                        if (this.hasAddCustomAreaButtonTarget) {
                            this.addCustomAreaButtonTarget.disabled = !customAreaId;
                        }
                    }
                });
        }
    }

    /**
     * Обновить текст кнопки создания кастомной зоны с количеством
     * @private
     */
    _updateCreateCustomAreaButtonText() {
        if (!this.hasCreateCustomAreaButtonTarget) {
            return;
        }

        // Считаем количество кастомных зон
        const customAreasCount = this.geoAreaService.getAllAreas()
            .filter(area => area.isCustomArea).length;

        const buttonText = customAreasCount > 0
            ? `<i class="fas fa-plus-circle"></i> ${this.translationCreateCustomButtonValue} (${customAreasCount})`
            : `<i class="fas fa-plus-circle"></i> ${this.translationCreateCustomButtonValue}`;

        this.createCustomAreaButtonTarget.innerHTML = buttonText;
    }

    // Метод _fitMapToAllLayers() удалён - теперь через MapService.fitToAllLayers()

    /**
     * Автоматический выбор страны используя GeoAreaService
     * @private
     * @param {Set<string>} countryISO3Set - Набор уникальных ISO3 кодов (опционально)
     */
    async _autoSelectCountry(countryISO3Set = null) {
        // Если Set не передан, получаем из GeoAreaService
        if (!countryISO3Set) {
            countryISO3Set = this.geoAreaService.getUniqueCountryISO3();
        }

        console.log('[GeoAreaMap] Auto-select country check:', {
            countriesInSet: countryISO3Set.size,
            countries: Array.from(countryISO3Set),
            allAreas: this.geoAreaService.getAllAreas().map(a => ({
                name: a.name,
                countryISO3: a.countryISO3
            }))
        });

        if (countryISO3Set.size === 0) {
            console.log('[GeoAreaMap] No countries to auto-select');
            return;
        }

        if (countryISO3Set.size > 1) {
            console.log('[GeoAreaMap] Multiple countries detected:', Array.from(countryISO3Set));
            console.log('[GeoAreaMap] Not auto-selecting country (mixed countries)');
            return;
        }

        // Все города из одной страны - выбираем её
        const targetISO3 = Array.from(countryISO3Set)[0];

        console.log('[GeoAreaMap] Auto-selecting country:', targetISO3);

        // Ждем загрузки списка стран если еще не загружен
        const countryOptions = Array.from(this.countrySelectTarget.options);
        if (countryOptions.length <= 1) {
            console.log('[GeoAreaMap] Countries not loaded yet, waiting...');
            // Небольшая задержка и повторная попытка
            setTimeout(() => this._autoSelectCountry(countryISO3Set), 500);
            return;
        }

        // Ищем опцию страны по ISO3
        const countryOption = countryOptions.find(
            option => option.dataset.iso3 === targetISO3 || option.getAttribute('data-iso3') === targetISO3
        );

        if (!countryOption) {
            console.warn('[GeoAreaMap] Country option not found for ISO3:', targetISO3);
            console.warn('[GeoAreaMap] Available options:', countryOptions.map(o => ({
                value: o.value,
                text: o.textContent,
                iso3: o.dataset.iso3 || o.getAttribute('data-iso3')
            })));
            return;
        }

        const countryId = countryOption.value;

        // Устанавливаем значение в селекте
        this.countrySelectTarget.value = countryId;
        this.currentCountryISO3 = targetISO3;

        // Если Select2 инициализирован, обновляем его
        if (window.jQuery && window.jQuery(this.countrySelectTarget).hasClass('select2-hidden-accessible')) {
            window.jQuery(this.countrySelectTarget).val(countryId).trigger('change.select2');
            console.log('[GeoAreaMap] Country auto-selected via Select2:', countryOption.textContent);
        } else {
            console.log('[GeoAreaMap] Country auto-selected:', countryOption.textContent);
        }

        // Загружаем города и кастомные зоны выбранной страны
        await this._loadCities(targetISO3);
        await this._loadCustomAreas(targetISO3);

        // ВАЖНО: Активируем кнопку создания кастомной зоны после автовыбора
        if (this.hasCreateCustomAreaButtonTarget) {
            this.createCustomAreaButtonTarget.disabled = false;
            console.log('[GeoAreaMap] Create custom area button enabled after auto-select');
        }
    }

    /**
     * Экранирование HTML
     * @private
     */
    _escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Обновление Select2 после изменения опций
     * Вспомогательный метод для DRY
     * @private
     */
    _updateSelect2(selectElement) {
        if (window.jQuery && window.jQuery(selectElement).hasClass('select2-hidden-accessible')) {
            window.jQuery(selectElement).trigger('change.select2');
        }
    }

    // Метод _fixLeafletIcons() удалён - теперь в MapService
}
