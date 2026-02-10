import { Controller } from '@hotwired/stimulus';
import { MapService } from '../services/MapService.js';
import { ApiService } from '../services/ApiService.js';

/**
 * GeoArea View Map Controller
 * 
 * Контроллер для отображения гео-зон в режиме просмотра (show view).
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: только отображение, никаких изменений
 * - Dependency Inversion: использует сервисы (MapService, ApiService)
 * - Open/Closed: расширяемый через values
 * 
 * Использует композицию сервисов вместо наследования.
 */
export default class extends Controller {
    static values = {
        apiGeometryUrl: String,
        defaultLat: Number,
        defaultLng: Number,
        defaultZoom: Number,
        areaIds: Array,  // Массив UUID зон для отображения
        translationErrorLoadGeometry: String,
    }

    static targets = ['map']

    /**
     * Инициализация контроллера
     */
    connect() {
        // Создаём сервисы (композиция вместо наследования)
        this.mapService = new MapService({
            container: this.mapTarget,
            defaultLat: this.defaultLatValue,
            defaultLng: this.defaultLngValue,
            defaultZoom: this.defaultZoomValue,
            interactive: true  // Можно изменить на false для read-only карты
        });

        this.apiService = new ApiService({
            geometryUrl: this.apiGeometryUrlValue
        });

        // Инициализируем карту
        this.mapService.initialize();

        // Загружаем гео-зоны
        this._loadGeoAreas();
    }

    /**
     * Очистка при удалении контроллера
     */
    disconnect() {
        if (this.mapService) {
            this.mapService.destroy();
            this.mapService = null;
        }
    }

    /**
     * Загрузка и отображение гео-зон
     * @private
     */
    async _loadGeoAreas() {
        if (!this.hasAreaIdsValue || this.areaIdsValue.length === 0) {
            console.log('[GeoAreaViewMap] No areas to display');
            return;
        }

        console.log('[GeoAreaViewMap] Loading areas:', this.areaIdsValue.length);

        for (const areaId of this.areaIdsValue) {
            try {
                // Загружаем геометрию через API сервис
                const data = await this.apiService.getGeometry(areaId);

                // Добавляем на карту через Map сервис
                this.mapService.addGeoJsonLayer(areaId, data.geometry, {
                    color: '#3388ff',
                    weight: 2,
                    opacity: 0.8,
                    fillOpacity: 0.3,
                    popupContent: data.name
                });

            } catch (error) {
                console.error('[GeoAreaViewMap] Error loading area:', areaId, error);
                // Продолжаем загружать остальные зоны
            }
        }

        // Зумим на все загруженные зоны
        this.mapService.fitToAllLayers({
            padding: [50, 50],
            maxZoom: 12,
            animate: false  // Без анимации при загрузке
        });

        console.log('[GeoAreaViewMap] All areas loaded:', this.mapService.getLayersCount());
    }
}
