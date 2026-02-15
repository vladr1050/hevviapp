import { Controller } from '@hotwired/stimulus';
import { MapService } from '../services/MapService.js';
import { HexagonService } from '../services/HexagonService.js';
import L from 'leaflet';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';
import { featureCollection } from '@turf/helpers';
import dissolve from '@turf/dissolve';

/**
 * Custom Area Modal Controller
 * 
 * Управляет модальным окном для создания/редактирования кастомных гео-зон.
 * Предоставляет инструменты для рисования полигонов на карте.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: управление модальным окном и рисованием
 * - Open/Closed: расширяемый через values и методы
 * - Dependency Inversion: использует MapService для работы с картой
 * 
 * Использует Leaflet.draw для рисования полигонов.
 */
export default class extends Controller {
    static values = {
        defaultLat: Number,
        defaultLng: Number,
        defaultZoom: Number,
        countryIso3: String,
        mode: String, // 'create' или 'edit'
        existingGeometry: String, // GeoJSON для редактирования
        existingName: String,
        existingId: String,
        // Translations
        translationNameRequired: String,
        translationGeometryRequired: String,
        translationJqueryError: String,
        translationDrawError: String,
        translationInvalidGeometry: String,
        translationLoadError: String,
        translationHexagonSelectedLabel: String,
        translationHexagonClearButton: String,
        translationHexagonClickHint: String,
    }

    static targets = [
        'modal',
        'map',
        'nameInput',
        'saveButton',
        'cancelButton',
        'errorMessage',
        'hexagonToggle',
        'hexagonResolution',
        'hexagonResolutionWrapper',
        'resolutionLabel',
        'resolutionInfo',
        'hexagonStats',
        'hexagonCount',
        'clearHexagonsButton',
    ]

    /**
     * Инициализация контроллера
     */
    connect() {
        console.log('[CustomAreaModal] Connected', {
            mode: this.modeValue,
            countryIso3: this.countryIso3Value
        });

        // Флаги состояния
        this.isOpen = false;
        this.drawnItems = null; // Leaflet FeatureGroup для нарисованных объектов
        this.currentGeometry = null; // Текущая геометрия (GeoJSON)
        
        // Инициализация HexagonService
        this.hexagonService = new HexagonService({
            resolution: 6 // По умолчанию районы города (~36 км²)
        });
        
        // Состояние гексагональной сетки
        this.hexagonGridEnabled = false;
        this.hexagonLayerId = 'modal-hexagon-grid';
        this.selectedHexagons = new Set(); // Выбранные H3 индексы
        this.selectedHexagonsLayerId = 'selected-hexagons-layer';
        
        // Инициализация при открытии модального окна
        this._setupModalListeners();
        this._setupHexagonToggleListener();
    }

    /**
     * Очистка при отключении контроллера
     */
    disconnect() {
        if (this.mapService) {
            this.mapService.destroy();
            this.mapService = null;
        }

        if (this.drawControl) {
            this.drawControl = null;
        }
        
        if (this.hexagonService) {
            this.hexagonService = null;
        }

        this.drawnItems = null;
    }

    /**
     * Открыть модальное окно
     * @param {Object} options - Опции {mode, countryIso3, existingGeometry?, existingName?, existingId?}
     */
    open(options = {}) {
        // Обновляем values из опций
        if (options.mode) this.modeValue = options.mode;
        if (options.countryIso3) this.countryIso3Value = options.countryIso3;
        if (options.existingGeometry) this.existingGeometryValue = options.existingGeometry;
        if (options.existingName) this.existingNameValue = options.existingName;
        if (options.existingId) this.existingIdValue = options.existingId;

        console.log('[CustomAreaModal] Opening modal', options);
        
        // ВАЖНО: Полностью очищаем состояние при открытии
        this.selectedHexagons.clear();
        this.hexagonGridEnabled = false;
        this.currentGeometry = null;
        
        // Очищаем слои с карты если карта уже инициализирована
        if (this.mapService) {
            this.mapService.removeHexagonLayer(this.hexagonLayerId);
            this.mapService.removeHexagonLayer(this.selectedHexagonsLayerId);
        }
        
        // Сбрасываем чекбокс гексагонов
        if (this.hasHexagonToggleTarget) {
            this.hexagonToggleTarget.checked = false;
            this.hexagonToggleTarget.disabled = false;
            
            if (window.jQuery) {
                window.jQuery(this.hexagonToggleTarget).iCheck('uncheck');
                window.jQuery(this.hexagonToggleTarget).iCheck('enable');
            }
        }
        
        // Скрываем контролы разрешения и статистику
        if (this.hasHexagonResolutionWrapperTarget) {
            this.hexagonResolutionWrapperTarget.style.display = 'none';
        }
        
        if (this.hasHexagonStatsTarget) {
            this.hexagonStatsTarget.style.display = 'none';
        }
        
        console.log('[CustomAreaModal] State cleared for new modal session');

        // Показываем модальное окно используя jQuery (используется в Sonata Admin)
        if (this.hasModalTarget) {
            if (typeof window.jQuery === 'undefined') {
                console.error('[CustomAreaModal] jQuery not found');
                alert(this.translationJqueryErrorValue);
                return;
            }

            // Используем jQuery API для Bootstrap модальных окон
            window.jQuery(this.modalTarget).modal('show');
            
            this.isOpen = true;

            // Инициализируем карту после открытия (иначе размеры будут неправильные)
            setTimeout(() => {
                this._initializeMap();
                this._loadExistingGeometry();
            }, 300); // Задержка для корректного рендеринга
        }

        // Устанавливаем название если режим редактирования
        if (this.modeValue === 'edit' && this.existingNameValue) {
            this.nameInputTarget.value = this.existingNameValue;
        } else {
            this.nameInputTarget.value = '';
        }

        this._clearError();
    }

    /**
     * Закрыть модальное окно
     */
    close() {
        if (this.hasModalTarget) {
            if (typeof window.jQuery !== 'undefined') {
                // Используем jQuery API для Bootstrap модальных окон
                window.jQuery(this.modalTarget).modal('hide');
            }
        }

        this.isOpen = false;
        this._cleanup();
    }

    /**
     * Обработчик нажатия кнопки "Сохранить"
     */
    save(event) {
        event.preventDefault();

        const name = this.nameInputTarget.value.trim();

        if (!name) {
            this._showError(this.translationNameRequiredValue);
            return;
        }

        // Обновляем геометрию перед сохранением
        const hasDrawnPolygons = this.drawnItems && this.drawnItems.getLayers().length > 0;
        const hasSelectedHexagons = this.selectedHexagons.size > 0;
        
        console.log('[CustomAreaModal] Save state:', {
            hasDrawnPolygons,
            hasSelectedHexagons,
            hexagonGridEnabled: this.hexagonGridEnabled
        });

        if (hasDrawnPolygons && hasSelectedHexagons) {
            // Есть И полигон И гексагоны - объединяем всё вместе БЕЗ dissolve
            console.log('[CustomAreaModal] Combining drawn polygons with selected hexagons');
            this._updateGeometryFromBothWithoutDissolve();
        } else if (hasSelectedHexagons) {
            // Только гексагоны (новая зона или удалили полигон)
            console.log('[CustomAreaModal] Using only selected hexagons');
            this._updateGeometryFromSelectedHexagonsWithoutDissolve();
        } else if (hasDrawnPolygons) {
            // Только полигон (обычная зона или отключили гексагоны)
            console.log('[CustomAreaModal] Using only drawn polygons');
            this._updateGeometryFromLayers();
        }

        if (!this.currentGeometry) {
            this._showError(this.translationGeometryRequiredValue);
            return;
        }

        console.log('[CustomAreaModal] Saving custom area', {
            name,
            geometry: this.currentGeometry,
            countryIso3: this.countryIso3Value,
            hexagonMode: this.hexagonGridEnabled,
            selectedHexagonsCount: this.selectedHexagons.size,
            drawnPolygonsCount: hasDrawnPolygons ? this.drawnItems.getLayers().length : 0,
            mode: this.modeValue,
        });

        // Диспатчим событие с данными кастомной зоны
        const detail = {
            name,
            geometry: this.currentGeometry,
            countryISO3: this.countryIso3Value,
            mode: this.modeValue,
            id: this.existingIdValue || null,
        };

        this.dispatch('save', { detail });

        // Закрываем модальное окно
        this.close();
    }

    /**
     * Обработчик нажатия кнопки "Отмена"
     */
    cancel(event) {
        event.preventDefault();
        this.close();
    }

    /**
     * Инициализация карты Leaflet
     * @private
     */
    _initializeMap() {
        if (this.mapService) {
            // Карта уже инициализирована
            return;
        }

        console.log('[CustomAreaModal] Initializing map');

        // Инициализация MapService
        this.mapService = new MapService({
            container: this.mapTarget,
            defaultLat: this.defaultLatValue || 56.9496,
            defaultLng: this.defaultLngValue || 24.1052,
            defaultZoom: this.defaultZoomValue || 7,
            interactive: true
        });

        this.mapService.initialize();

        // Настройка слушателей событий карты для гексагонов
        this._setupMapEventListeners();

        // Инициализация Leaflet.draw
        this._initializeDrawControls();
    }

    /**
     * Настройка слушателей событий карты
     * @private
     */
    _setupMapEventListeners() {
        const map = this.mapService.getMap();
        
        if (!map) {
            console.error('[CustomAreaModal] Map not initialized');
            return;
        }
        
        // Обновляем гексагональную сетку при изменении видимой области
        map.on('moveend', () => {
            if (this.hexagonGridEnabled) {
                this._updateHexagonGrid();
            }
        });
        
        map.on('zoomend', () => {
            if (this.hexagonGridEnabled) {
                this._updateHexagonGrid();
            }
        });
        
        console.log('[CustomAreaModal] Map event listeners configured');
    }

    /**
     * Инициализация контролов для рисования
     * @private
     */
    _initializeDrawControls() {
        // Проверяем наличие Leaflet.draw
        if (typeof L.Control.Draw === 'undefined') {
            console.error('[CustomAreaModal] Leaflet.draw not found');
            this._showError(this.translationDrawErrorValue);
            return;
        }

        // FeatureGroup для нарисованных объектов
        this.drawnItems = new L.FeatureGroup();
        this.mapService.map.addLayer(this.drawnItems);

        // Настройки контрола рисования
        this.drawControl = new L.Control.Draw({
            position: 'topright',
            draw: {
                polygon: {
                    allowIntersection: false,
                    showArea: true,
                    metric: true,
                    shapeOptions: {
                        color: '#3388ff',
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.3,
                    }
                },
                polyline: false,
                rectangle: false,
                circle: false,
                marker: false,
                circlemarker: false,
            },
            edit: {
                featureGroup: this.drawnItems,
                remove: true
            }
        });

        this.mapService.map.addControl(this.drawControl);

        // Слушатели событий рисования
        this.mapService.map.on(L.Draw.Event.CREATED, (e) => {
            const layer = e.layer;
            
            // Очищаем предыдущие слои (разрешен только один полигон)
            this.drawnItems.clearLayers();
            
            // Добавляем новый слой
            this.drawnItems.addLayer(layer);
            
            // Сохраняем геометрию
            this._updateGeometryFromLayers();
            
            console.log('[CustomAreaModal] Polygon created');
        });

        this.mapService.map.on(L.Draw.Event.EDITED, (e) => {
            // Обновляем геометрию после редактирования
            this._updateGeometryFromLayers();
            console.log('[CustomAreaModal] Polygon edited');
        });

        this.mapService.map.on(L.Draw.Event.DELETED, (e) => {
            // Обновляем геометрию после удаления
            this._updateGeometryFromLayers();
            console.log('[CustomAreaModal] Polygon deleted');
        });
    }

    /**
     * Загрузка существующей геометрии (для режима редактирования)
     * @private
     */
    _loadExistingGeometry() {
        if (this.modeValue !== 'edit' || !this.existingGeometryValue) {
            return;
        }

        try {
            const geometry = JSON.parse(this.existingGeometryValue);
            
            console.log('[CustomAreaModal] Loading existing geometry for edit', geometry);

            // Загружаем как обычный полигон для редактирования
            // После dissolve гексагоны становятся монолитным полигоном
            // и восстановить исходные H3 индексы точно невозможно
            this._loadPolygonGeometry(geometry);
            
            // Примечание в инструкциях что можно включить сетку для добавления гексагонов
            console.log('[CustomAreaModal] Zone loaded for editing. Enable hexagon grid to add more hexagons.');

        } catch (error) {
            console.error('[CustomAreaModal] Error loading existing geometry:', error);
            this._showError(this.translationLoadErrorValue + ': ' + error.message);
        }
    }

    /**
     * Загрузка обычной полигональной геометрии для редактирования
     * @private
     * @param {Object} geometry - GeoJSON геометрия
     */
    _loadPolygonGeometry(geometry) {
        console.log('[CustomAreaModal] Loading polygon geometry for manual editing', {
            type: geometry.type,
            mode: this.modeValue,
            polygonsCount: geometry.type === 'MultiPolygon' ? geometry.coordinates.length : 1
        });
        
        // Очищаем предыдущие слои
        this.drawnItems.clearLayers();

        let polygonsLoaded = 0;
        
        if (geometry.type === 'MultiPolygon') {
            // MultiPolygon: загружаем ВСЕ полигоны
            console.log('[CustomAreaModal] Loading MultiPolygon with', geometry.coordinates.length, 'polygons');
            
            geometry.coordinates.forEach((polygonCoords, index) => {
                if (polygonCoords && polygonCoords[0]) {
                    const latLngs = polygonCoords[0].map(coord => [coord[1], coord[0]]);
                    
                    if (latLngs.length > 0) {
                        const polygon = L.polygon(latLngs, {
                            color: '#3388ff',
                            weight: 2,
                            opacity: 0.8,
                            fillOpacity: 0.3,
                        });
                        
                        this.drawnItems.addLayer(polygon);
                        polygonsLoaded++;
                        
                        console.log('[CustomAreaModal] Loaded polygon', index + 1, 'with', latLngs.length, 'points');
                    }
                }
            });
        } else if (geometry.type === 'Polygon') {
            // Polygon: загружаем один полигон
            if (geometry.coordinates && geometry.coordinates[0]) {
                const latLngs = geometry.coordinates[0].map(coord => [coord[1], coord[0]]);
                
                if (latLngs.length > 0) {
                    const polygon = L.polygon(latLngs, {
                        color: '#3388ff',
                        weight: 2,
                        opacity: 0.8,
                        fillOpacity: 0.3,
                    });
                    
                    this.drawnItems.addLayer(polygon);
                    polygonsLoaded++;
                    
                    console.log('[CustomAreaModal] Loaded single polygon with', latLngs.length, 'points');
                }
            }
        }

        if (polygonsLoaded === 0) {
            console.error('[CustomAreaModal] No coordinates found in geometry');
            this._showError(this.translationInvalidGeometryValue);
            return;
        }

        // Зумим на всю геометрию
        const bounds = this.drawnItems.getBounds();
        if (bounds && bounds.isValid()) {
            this.mapService.map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 12,
            });
        }
        
        console.log('[CustomAreaModal] Existing geometry loaded and ready for editing:', polygonsLoaded, 'polygon(s)');

        // Сохраняем текущую геометрию
        this.currentGeometry = geometry;
    }

    /**
     * Обновление геометрии из нарисованных слоев
     * @private
     */
    _updateGeometryFromLayers() {
        const layers = this.drawnItems.getLayers();
        
        if (layers.length === 0) {
            this.currentGeometry = null;
            return;
        }

        // Собираем все полигоны в массив
        const polygons = [];
        
        layers.forEach((layer) => {
            if (layer instanceof L.Polygon) {
                // Получаем координаты полигона
                const coords = this._getPolygonCoordinates(layer);
                polygons.push(coords);
            }
        });

        // Формируем MultiPolygon GeoJSON
        this.currentGeometry = {
            type: 'MultiPolygon',
            coordinates: polygons
        };

        console.log('[CustomAreaModal] Geometry updated', this.currentGeometry);
    }

    /**
     * Получить координаты полигона в формате GeoJSON
     * @private
     * @param {L.Polygon} layer
     * @returns {Array}
     */
    _getPolygonCoordinates(layer) {
        const latLngs = layer.getLatLngs();
        
        // L.Polygon может возвращать массив массивов (для полигонов с дырками)
        const rings = [];
        
        if (Array.isArray(latLngs[0]) && latLngs[0][0] && typeof latLngs[0][0].lat !== 'undefined') {
            // Простой полигон без дыр
            const ring = latLngs[0].map(latLng => [latLng.lng, latLng.lat]);
            // ВАЖНО: Замыкаем кольцо (первая точка = последняя точка)
            this._ensureRingClosed(ring);
            rings.push(ring);
        } else if (Array.isArray(latLngs[0][0])) {
            // Полигон с дырками
            latLngs.forEach(ring => {
                const coords = ring.map(latLng => [latLng.lng, latLng.lat]);
                // ВАЖНО: Замыкаем каждое кольцо
                this._ensureRingClosed(coords);
                rings.push(coords);
            });
        } else {
            // Фолбек: просто массив LatLng
            const ring = latLngs.map(latLng => [latLng.lng, latLng.lat]);
            // ВАЖНО: Замыкаем кольцо
            this._ensureRingClosed(ring);
            rings.push(ring);
        }

        return rings;
    }

    /**
     * Убедиться что кольцо полигона замкнуто (первая точка = последняя точка)
     * @private
     * @param {Array} ring - Массив координат
     */
    _ensureRingClosed(ring) {
        if (ring.length < 3) {
            return; // Недостаточно точек для полигона
        }

        const first = ring[0];
        const last = ring[ring.length - 1];

        // Проверяем совпадают ли первая и последняя точки
        if (first[0] !== last[0] || first[1] !== last[1]) {
            // Замыкаем кольцо - добавляем первую точку в конец
            ring.push([first[0], first[1]]);
            console.log('[CustomAreaModal] Ring closed automatically');
        }
    }

    /**
     * Показать сообщение об ошибке
     * @private
     */
    _showError(message) {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = message;
            this.errorMessageTarget.classList.remove('d-none');
        } else {
            alert(message);
        }
    }

    /**
     * Очистить сообщение об ошибке
     * @private
     */
    _clearError() {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = '';
            this.errorMessageTarget.classList.add('d-none');
        }
    }

    /**
     * Настройка слушателей модального окна
     * @private
     */
    _setupModalListeners() {
        if (this.hasModalTarget && typeof window.jQuery !== 'undefined') {
            // Слушаем событие закрытия модального окна через jQuery
            window.jQuery(this.modalTarget).on('hidden.bs.modal', () => {
                this._cleanup();
            });
        }
    }

    /**
     * Очистка ресурсов
     * @private
     */
    _cleanup() {
        // Очищаем карту и контролы при закрытии
        if (this.drawnItems) {
            this.drawnItems.clearLayers();
        }
        
        // Удаляем гексагональную сетку
        if (this.mapService) {
            this.mapService.removeHexagonLayer(this.hexagonLayerId);
            this.mapService.removeHexagonLayer(this.selectedHexagonsLayerId);
        }
        
        // Очищаем выбранные гексагоны
        this.selectedHexagons.clear();
        this._updateHexagonStats();

        this.currentGeometry = null;
        this._clearError();
    }

    /**
     * Настройка слушателя для переключателя гексагональной сетки
     * @private
     */
    _setupHexagonToggleListener() {
        if (!this.hasHexagonToggleTarget) {
            return;
        }
        
        if (typeof window.jQuery === 'undefined') {
            return;
        }
        
        const $ = window.jQuery;
        const $toggle = $(this.hexagonToggleTarget);
        
        // Даем время iCheck инициализироваться
        setTimeout(() => {
            // Слушаем события iCheck
            $toggle.on('ifChecked.customAreaModal', () => {
                this.hexagonGridEnabled = true;
                this._handleHexagonToggle(true);
            });
            
            $toggle.on('ifUnchecked.customAreaModal', () => {
                this.hexagonGridEnabled = false;
                this._handleHexagonToggle(false);
            });
            
            // Fallback для стандартного change
            $toggle.on('change.customAreaModal', (e) => {
                const isChecked = e.target.checked;
                this.hexagonGridEnabled = isChecked;
                this._handleHexagonToggle(isChecked);
            });
            
            console.log('[CustomAreaModal] Hexagon toggle listeners configured');
        }, 500);
    }

    /**
     * Обработка переключения гексагональной сетки
     * @private
     * @param {boolean} enabled
     */
    _handleHexagonToggle(enabled) {
        console.log('[CustomAreaModal] Hexagon grid toggled:', enabled);
        
        if (enabled) {
            // Показываем контролы разрешения
            if (this.hasHexagonResolutionWrapperTarget) {
                this.hexagonResolutionWrapperTarget.style.display = 'block';
            }
            
            // Показываем статистику если есть выбранные гексагоны
            if (this.selectedHexagons.size > 0 && this.hasHexagonStatsTarget) {
                this.hexagonStatsTarget.style.display = 'block';
            }
            
            // Генерируем и отображаем сетку
            this._updateHexagonGrid();
            
            // Отображаем уже выбранные гексагоны если есть
            if (this.selectedHexagons.size > 0) {
                this._updateSelectedHexagonsLayer();
            }
        } else {
            // Скрываем контролы разрешения
            if (this.hasHexagonResolutionWrapperTarget) {
                this.hexagonResolutionWrapperTarget.style.display = 'none';
            }
            
            // Скрываем статистику
            if (this.hasHexagonStatsTarget) {
                this.hexagonStatsTarget.style.display = 'none';
            }
            
            // Удаляем сетку с карты
            if (this.mapService) {
                this.mapService.removeHexagonLayer(this.hexagonLayerId);
                this.mapService.removeHexagonLayer(this.selectedHexagonsLayerId);
            }
        }
    }

    /**
     * Обработчик изменения разрешения гексагональной сетки
     */
    onHexagonResolutionChange(event) {
        const resolution = parseInt(event.target.value, 10);
        
        // Обновляем HexagonService
        this.hexagonService.setResolution(resolution);
        
        // Обновляем label и info
        if (this.hasResolutionLabelTarget) {
            this.resolutionLabelTarget.textContent = resolution;
        }
        
        if (this.hasResolutionInfoTarget) {
            const info = HexagonService.getResolutionInfo(resolution);
            this.resolutionInfoTarget.textContent = `(~${info.area})`;
        }
        
        console.log('[CustomAreaModal] Hexagon resolution changed to:', resolution);
        
        // Перегенерируем сетку если она включена
        if (this.hexagonGridEnabled) {
            this._updateHexagonGrid();
        }
    }

    /**
     * Обновить гексагональную сетку на карте
     * @private
     */
    _updateHexagonGrid() {
        if (!this.mapService) {
            console.error('[CustomAreaModal] Map not initialized');
            return;
        }
        
        const map = this.mapService.getMap();
        
        if (!map) {
            console.error('[CustomAreaModal] Map instance not available');
            return;
        }
        
        try {
            // Получаем границы видимой области карты
            const bounds = map.getBounds();
            const mapBounds = {
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest()
            };
            
            console.log('[CustomAreaModal] Generating hexagon grid for bounds:', mapBounds);
            
            // Генерируем гексагоны для видимой области
            const h3Indexes = this.hexagonService.getHexagonsForMapBounds(mapBounds);
            
            console.log('[CustomAreaModal] Generated hexagons count:', h3Indexes.length);
            
            // Ограничиваем количество гексагонов для производительности
            const maxHexagons = 2000;
            let hexagonsToDisplay = h3Indexes;
            
            if (h3Indexes.length > maxHexagons) {
                console.warn('[CustomAreaModal] Too many hexagons:', h3Indexes.length, 'limiting to:', maxHexagons);
                hexagonsToDisplay = h3Indexes.slice(0, maxHexagons);
            }
            
            // Конвертируем в GeoJSON
            const geoJson = this.hexagonService.hexagonsToGeoJson(hexagonsToDisplay);
            
            // Удаляем старый слой если есть
            this.mapService.removeHexagonLayer(this.hexagonLayerId);
            
            // Добавляем новый слой с обработчиком кликов
            this.mapService.addHexagonLayer(this.hexagonLayerId, geoJson, {
                color: '#ff6600',
                weight: 1,
                opacity: 0.4,
                fillOpacity: 0.05,
                fillColor: '#ff6600',
                enableHover: true,
                hoverFillOpacity: 0.15,
                hoverWeight: 2,
                showH3Index: false,
                onEachFeature: (feature, layer) => {
                    // Добавляем обработчик клика на гексагон
                    layer.on('click', () => {
                        this._toggleHexagonSelection(feature.properties.h3Index);
                    });
                }
            });
            
            console.log('[CustomAreaModal] Hexagon grid updated successfully, displayed:', hexagonsToDisplay.length);
        } catch (error) {
            console.error('[CustomAreaModal] Error updating hexagon grid:', error);
        }
    }

    /**
     * Переключить выбор гексагона
     * @private
     * @param {string} h3Index
     */
    _toggleHexagonSelection(h3Index) {
        if (!h3Index) {
            console.warn('[CustomAreaModal] H3 index is empty');
            return;
        }
        
        if (this.selectedHexagons.has(h3Index)) {
            // Убираем из выбранных
            this.selectedHexagons.delete(h3Index);
            console.log('[CustomAreaModal] Hexagon deselected:', h3Index, 'Total selected:', this.selectedHexagons.size);
        } else {
            // Добавляем в выбранные
            this.selectedHexagons.add(h3Index);
            console.log('[CustomAreaModal] Hexagon selected:', h3Index, 'Total selected:', this.selectedHexagons.size);
        }
        
        // Обновляем визуализацию выбранных гексагонов
        this._updateSelectedHexagonsLayer();
        
        // Обновляем статистику
        this._updateHexagonStats();
        
        // Обновляем геометрию из выбранных гексагонов
        this._updateGeometryFromSelectedHexagons();
    }

    /**
     * Обновить слой выбранных гексагонов
     * @private
     */
    _updateSelectedHexagonsLayer() {
        if (!this.mapService) {
            console.warn('[CustomAreaModal] MapService not available for _updateSelectedHexagonsLayer');
            return;
        }
        
        // Удаляем старый слой
        this.mapService.removeHexagonLayer(this.selectedHexagonsLayerId);
        
        if (this.selectedHexagons.size === 0) {
            console.log('[CustomAreaModal] No selected hexagons to display');
            return;
        }
        
        console.log('[CustomAreaModal] Updating selected hexagons layer, count:', this.selectedHexagons.size);
        
        // Создаем GeoJSON для выбранных гексагонов
        const selectedArray = Array.from(this.selectedHexagons);
        const geoJson = this.hexagonService.hexagonsToGeoJson(selectedArray);
        
        console.log('[CustomAreaModal] GeoJSON created for selected hexagons:', geoJson);
        console.log('[CustomAreaModal] Features count:', geoJson.features?.length);
        
        // Добавляем слой с выделенным стилем
        const layer = this.mapService.addHexagonLayer(this.selectedHexagonsLayerId, geoJson, {
            color: '#00ff00',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.4,
            fillColor: '#00ff00',
            enableHover: false,
            onEachFeature: (feature, layer) => {
                // Добавляем обработчик клика для отмены выбора
                layer.on('click', () => {
                    this._toggleHexagonSelection(feature.properties.h3Index);
                });
            }
        });
        
        console.log('[CustomAreaModal] Selected hexagons layer updated:', this.selectedHexagons.size);
        console.log('[CustomAreaModal] Layer object:', layer);
        console.log('[CustomAreaModal] Layer has bounds?', layer && typeof layer.getBounds === 'function');
    }

    /**
     * Обновить статистику выбранных гексагонов
     * @private
     */
    _updateHexagonStats() {
        if (!this.hasHexagonStatsTarget) return;
        
        const count = this.selectedHexagons.size;
        
        if (count > 0) {
            this.hexagonStatsTarget.style.display = 'block';
            if (this.hasHexagonCountTarget) {
                this.hexagonCountTarget.textContent = count;
            }
        } else {
            this.hexagonStatsTarget.style.display = 'none';
        }
    }

    /**
     * Очистить все выбранные гексагоны
     */
    clearSelectedHexagons(event) {
        if (event) event.preventDefault();
        
        console.log('[CustomAreaModal] Clearing all selected hexagons');
        
        this.selectedHexagons.clear();
        this._updateSelectedHexagonsLayer();
        this._updateHexagonStats();
        this._updateGeometryFromSelectedHexagons();
    }

    /**
     * Обновить геометрию из выбранных гексагонов БЕЗ dissolve
     * @private
     */
    _updateGeometryFromSelectedHexagonsWithoutDissolve() {
        if (this.selectedHexagons.size === 0) {
            console.warn('[CustomAreaModal] No hexagons selected');
            this.currentGeometry = null;
            return;
        }
        
        // Сохраняем гексагоны как MultiPolygon БЕЗ dissolve
        const hexagonsArray = Array.from(this.selectedHexagons);
        const polygons = hexagonsArray.map(h3Index => {
            const geom = this.hexagonService.hexagonToGeoJson(h3Index);
            return geom.coordinates;
        });
        
        this.currentGeometry = {
            type: 'MultiPolygon',
            coordinates: polygons
        };
        
        console.log('[CustomAreaModal] Geometry updated from selected hexagons (without dissolve):', {
            hexagonsCount: this.selectedHexagons.size,
            geometryType: this.currentGeometry.type
        });
    }

    /**
     * Обновить геометрию из нарисованных полигонов И выбранных гексагонов БЕЗ dissolve
     * @private
     */
    _updateGeometryFromBothWithoutDissolve() {
        console.log('[CustomAreaModal] Combining drawn polygons and selected hexagons (without dissolve)');
        
        try {
            // Получаем координаты из нарисованных полигонов
            const layers = this.drawnItems.getLayers();
            const allPolygons = [];
            
            layers.forEach((layer) => {
                if (layer instanceof L.Polygon) {
                    const coords = this._getPolygonCoordinates(layer);
                    allPolygons.push(coords);
                }
            });
            
            // Добавляем координаты выбранных гексагонов
            const hexagonsArray = Array.from(this.selectedHexagons);
            hexagonsArray.forEach(h3Index => {
                const geom = this.hexagonService.hexagonToGeoJson(h3Index);
                allPolygons.push(geom.coordinates);
            });
            
            console.log('[CustomAreaModal] Combined geometries:', {
                drawnPolygons: layers.length,
                hexagons: hexagonsArray.length,
                totalPolygons: allPolygons.length
            });
            
            // Сохраняем как MultiPolygon
            this.currentGeometry = {
                type: 'MultiPolygon',
                coordinates: allPolygons
            };
            
            console.log('[CustomAreaModal] Successfully combined all geometries as MultiPolygon');
            
        } catch (error) {
            console.error('[CustomAreaModal] Error combining geometries:', error);
            
            // Fallback: только нарисованные полигоны
            this._updateGeometryFromLayers();
        }
    }
}
