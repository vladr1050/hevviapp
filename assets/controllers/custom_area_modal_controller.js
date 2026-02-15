import { Controller } from '@hotwired/stimulus';
import { MapService } from '../services/MapService.js';
import L from 'leaflet';
import 'leaflet-draw';
import 'leaflet-draw/dist/leaflet.draw.css';

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
    }

    static targets = [
        'modal',
        'map',
        'nameInput',
        'saveButton',
        'cancelButton',
        'errorMessage',
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
        
        // Инициализация при открытии модального окна
        this._setupModalListeners();
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

        if (!this.currentGeometry) {
            this._showError(this.translationGeometryRequiredValue);
            return;
        }

        console.log('[CustomAreaModal] Saving custom area', {
            name,
            geometry: this.currentGeometry,
            countryIso3: this.countryIso3Value,
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

        // Инициализация Leaflet.draw
        this._initializeDrawControls();
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

            // Очищаем предыдущие слои
            this.drawnItems.clearLayers();

            // Конвертируем GeoJSON в LatLng координаты для Leaflet
            let latLngs = [];
            
            if (geometry.type === 'MultiPolygon') {
                // MultiPolygon: берем первый полигон
                if (geometry.coordinates && geometry.coordinates[0] && geometry.coordinates[0][0]) {
                    latLngs = geometry.coordinates[0][0].map(coord => [coord[1], coord[0]]);
                }
            } else if (geometry.type === 'Polygon') {
                // Polygon: берем внешнее кольцо
                if (geometry.coordinates && geometry.coordinates[0]) {
                    latLngs = geometry.coordinates[0].map(coord => [coord[1], coord[0]]);
                }
            }

            if (latLngs.length === 0) {
                console.error('[CustomAreaModal] No coordinates found in geometry');
                this._showError(this.translationInvalidGeometryValue);
                return;
            }

            // Создаем редактируемый полигон напрямую из координат
            const polygon = L.polygon(latLngs, {
                color: '#3388ff',
                weight: 2,
                opacity: 0.8,
                fillOpacity: 0.3,
            });

            // Добавляем в drawnItems - теперь он редактируемый!
            this.drawnItems.addLayer(polygon);

            // Зумим на геометрию
            this.mapService.map.fitBounds(this.drawnItems.getBounds(), {
                padding: [50, 50],
                maxZoom: 12,
            });
            
            console.log('[CustomAreaModal] Existing geometry loaded and ready for editing:', latLngs.length, 'points');

            // Сохраняем текущую геометрию
            this.currentGeometry = geometry;

        } catch (error) {
            console.error('[CustomAreaModal] Error loading existing geometry:', error);
            this._showError(this.translationLoadErrorValue + ': ' + error.message);
        }
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

        this.currentGeometry = null;
        this._clearError();
    }
}
