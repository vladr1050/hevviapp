import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * MapService
 * 
 * Сервис для работы с Leaflet картами.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает ТОЛЬКО за инициализацию и управление картой
 * - Open/Closed: расширяемый через конфигурацию
 * - Dependency Inversion: не зависит от конкретных контроллеров
 * 
 * Используется как композиция в Stimulus контроллерах.
 */
export class MapService {
    /**
     * @param {Object} config - Конфигурация карты
     * @param {HTMLElement} config.container - Контейнер для карты
     * @param {number} config.defaultLat - Дефолтная широта
     * @param {number} config.defaultLng - Дефолтная долгота
     * @param {number} config.defaultZoom - Дефолтный зум
     * @param {boolean} config.interactive - Интерактивная карта (по умолчанию true)
     */
    constructor(config) {
        this.container = config.container;
        this.defaultLat = config.defaultLat;
        this.defaultLng = config.defaultLng;
        this.defaultZoom = config.defaultZoom;
        this.interactive = config.interactive !== false;
        
        this.map = null;
        this.layers = new Map(); // Map<layerId, Layer>
        this.hexagonLayers = new Map(); // Map<hexagonLayerId, Layer> - отдельно для гексагональных слоев
        
        this._fixLeafletIcons();
    }

    /**
     * Инициализация карты
     * @returns {L.Map}
     */
    initialize() {
        if (this.map) {
            console.warn('[MapService] Map already initialized');
            return this.map;
        }

        const mapOptions = {
            center: [this.defaultLat, this.defaultLng],
            zoom: this.defaultZoom,
            zoomControl: this.interactive,
            dragging: this.interactive,
            touchZoom: this.interactive,
            scrollWheelZoom: this.interactive,
            doubleClickZoom: this.interactive,
            boxZoom: this.interactive,
        };

        this.map = L.map(this.container, mapOptions);

        // Добавляем тайлы OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);

        // Фикс размера карты после инициализации
        // Необходимо для случаев когда контейнер скрыт или в табе
        setTimeout(() => {
            if (this.map) {
                this.map.invalidateSize();
                console.log('[MapService] Map size invalidated');
            }
        }, 100);

        // Наблюдаем за видимостью контейнера
        this._setupVisibilityObserver();

        console.log('[MapService] Map initialized', {
            interactive: this.interactive,
            center: [this.defaultLat, this.defaultLng],
            zoom: this.defaultZoom
        });

        return this.map;
    }

    /**
     * Добавить GeoJSON слой на карту
     * @param {string} layerId - Уникальный ID слоя
     * @param {Object} geometry - GeoJSON геометрия
     * @param {Object} options - Опции стиля и popup
     * @returns {L.Layer}
     */
    addGeoJsonLayer(layerId, geometry, options = {}) {
        if (this.layers.has(layerId)) {
            console.warn('[MapService] Layer already exists:', layerId);
            return this.layers.get(layerId);
        }

        const defaultStyle = {
            color: options.color || '#3388ff',
            weight: options.weight || 2,
            opacity: options.opacity || 0.8,
            fillOpacity: options.fillOpacity || 0.3
        };

        const layer = L.geoJSON(geometry, {
            style: defaultStyle
        }).addTo(this.map);

        // Добавляем popup если есть
        if (options.popupContent) {
            layer.bindPopup(options.popupContent);
        }

        this.layers.set(layerId, layer);

        console.log('[MapService] Layer added:', layerId);

        return layer;
    }

    /**
     * Удалить слой с карты
     * @param {string} layerId - ID слоя
     * @returns {boolean} - true если удалён
     */
    removeLayer(layerId) {
        if (!this.layers.has(layerId)) {
            return false;
        }

        const layer = this.layers.get(layerId);
        this.map.removeLayer(layer);
        this.layers.delete(layerId);

        console.log('[MapService] Layer removed:', layerId);

        return true;
    }

    /**
     * Зумировать карту на все слои
     * @param {Object} options - Опции fitBounds
     */
    fitToAllLayers(options = {}) {
        if (this.layers.size === 0) {
            this.map.setView([this.defaultLat, this.defaultLng], this.defaultZoom);
            console.log('[MapService] No layers - reset to default view');
            return;
        }

        try {
            const allLayers = Array.from(this.layers.values());
            
            // Проверяем что все слои имеют валидную геометрию
            const validLayers = allLayers.filter(layer => {
                try {
                    const bounds = layer.getBounds();
                    return bounds && bounds.isValid();
                } catch (e) {
                    return false;
                }
            });

            if (validLayers.length === 0) {
                console.warn('[MapService] No valid layers for zoom');
                return;
            }

            const featureGroup = L.featureGroup(validLayers);
            const bounds = featureGroup.getBounds();

            if (!bounds || !bounds.isValid()) {
                console.warn('[MapService] Invalid bounds after featureGroup');
                return;
            }

            const fitOptions = {
                padding: options.padding || [50, 50],
                maxZoom: options.maxZoom || 12,
                animate: options.animate !== false,
                duration: options.duration || 0.5
            };

            this.map.fitBounds(bounds, fitOptions);

            console.log('[MapService] Fitted to all layers:', {
                total: this.layers.size,
                valid: validLayers.length,
                bounds: {
                    south: bounds.getSouth().toFixed(4),
                    north: bounds.getNorth().toFixed(4),
                    west: bounds.getWest().toFixed(4),
                    east: bounds.getEast().toFixed(4)
                }
            });
        } catch (error) {
            console.error('[MapService] Error fitting bounds:', error);
        }
    }

    /**
     * Очистить все слои
     */
    clearAllLayers() {
        this.layers.forEach((layer, layerId) => {
            this.map.removeLayer(layer);
        });
        this.layers.clear();

        // Также очищаем гексагональные слои
        this.removeAllHexagonLayers();

        console.log('[MapService] All layers cleared');
    }

    /**
     * Уничтожить карту и освободить ресурсы
     */
    destroy() {
        // Отключаем наблюдатели
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }
        
        if (this.mutationObserver) {
            this.mutationObserver.disconnect();
            this.mutationObserver = null;
        }
        
        if (this.map) {
            this.clearAllLayers();
            this.map.remove();
            this.map = null;
            console.log('[MapService] Map destroyed');
        }
    }

    /**
     * Принудительное обновление размера карты
     * Используйте при изменении размера контейнера или переключении табов
     */
    invalidateSize() {
        if (this.map) {
            this.map.invalidateSize();
            console.log('[MapService] Map size invalidated manually');
        }
    }

    /**
     * Получить карту Leaflet
     * @returns {L.Map}
     */
    getMap() {
        return this.map;
    }

    /**
     * Получить количество слоёв
     * @returns {number}
     */
    getLayersCount() {
        return this.layers.size;
    }

    /**
     * Проверить наличие слоя
     * @param {string} layerId
     * @returns {boolean}
     */
    hasLayer(layerId) {
        return this.layers.has(layerId);
    }

    /**
     * Добавить гексагональный слой на карту
     * @param {string} layerId - Уникальный ID слоя
     * @param {Object} geoJson - GeoJSON FeatureCollection с гексагонами
     * @param {Object} options - Опции стиля
     * @returns {L.Layer}
     */
    addHexagonLayer(layerId, geoJson, options = {}) {
        if (this.hexagonLayers.has(layerId)) {
            console.warn('[MapService] Hexagon layer already exists:', layerId);
            return this.hexagonLayers.get(layerId);
        }

        const defaultStyle = {
            color: options.color || '#ff6600',
            weight: options.weight || 1,
            opacity: options.opacity || 0.5,
            fillOpacity: options.fillOpacity || 0.1,
            fillColor: options.fillColor || '#ff6600'
        };

        const layer = L.geoJSON(geoJson, {
            style: defaultStyle,
            onEachFeature: (feature, layer) => {
                if (options.onEachFeature) {
                    options.onEachFeature(feature, layer);
                }
                
                // Добавляем hover эффект если включен
                if (options.enableHover !== false) {
                    layer.on('mouseover', function () {
                        this.setStyle({
                            fillOpacity: options.hoverFillOpacity || 0.3,
                            weight: options.hoverWeight || 2
                        });
                    });
                    
                    layer.on('mouseout', function () {
                        this.setStyle({
                            fillOpacity: defaultStyle.fillOpacity,
                            weight: defaultStyle.weight
                        });
                    });
                }

                // Добавляем popup с H3 индексом если включен
                if (options.showH3Index !== false && feature.properties.h3Index) {
                    layer.bindTooltip(feature.properties.h3Index, {
                        permanent: false,
                        direction: 'center',
                        className: 'hexagon-tooltip'
                    });
                }
            }
        }).addTo(this.map);

        this.hexagonLayers.set(layerId, layer);

        console.log('[MapService] Hexagon layer added:', layerId, 'features:', geoJson.features?.length || 0);

        return layer;
    }

    /**
     * Удалить гексагональный слой с карты
     * @param {string} layerId - ID слоя
     * @returns {boolean} - true если удалён
     */
    removeHexagonLayer(layerId) {
        if (!this.hexagonLayers.has(layerId)) {
            return false;
        }

        const layer = this.hexagonLayers.get(layerId);
        this.map.removeLayer(layer);
        this.hexagonLayers.delete(layerId);

        console.log('[MapService] Hexagon layer removed:', layerId);

        return true;
    }

    /**
     * Удалить все гексагональные слои
     */
    removeAllHexagonLayers() {
        this.hexagonLayers.forEach((layer, layerId) => {
            this.map.removeLayer(layer);
        });
        this.hexagonLayers.clear();

        console.log('[MapService] All hexagon layers removed');
    }

    /**
     * Проверить наличие гексагонального слоя
     * @param {string} layerId
     * @returns {boolean}
     */
    hasHexagonLayer(layerId) {
        return this.hexagonLayers.has(layerId);
    }

    /**
     * Получить количество гексагональных слоёв
     * @returns {number}
     */
    getHexagonLayersCount() {
        return this.hexagonLayers.size;
    }

    /**
     * Обновить стиль гексагонального слоя
     * @param {string} layerId - ID слоя
     * @param {Object} style - Новый стиль
     */
    updateHexagonLayerStyle(layerId, style) {
        if (!this.hexagonLayers.has(layerId)) {
            console.warn('[MapService] Hexagon layer not found:', layerId);
            return;
        }

        const layer = this.hexagonLayers.get(layerId);
        layer.setStyle(style);

        console.log('[MapService] Hexagon layer style updated:', layerId);
    }

    /**
     * Настройка наблюдателя за видимостью контейнера
     * Исправляет проблему с маленьким квадратиком при инициализации в скрытом табе
     * @private
     */
    _setupVisibilityObserver() {
        // ResizeObserver для отслеживания изменения размера
        if (typeof ResizeObserver !== 'undefined') {
            this.resizeObserver = new ResizeObserver(() => {
                if (this.map && this._isVisible()) {
                    this.map.invalidateSize();
                    console.log('[MapService] Map resized');
                }
            });
            this.resizeObserver.observe(this.container);
        }

        // MutationObserver для отслеживания изменения display/visibility
        if (typeof MutationObserver !== 'undefined') {
            this.mutationObserver = new MutationObserver(() => {
                if (this.map && this._isVisible()) {
                    // Небольшая задержка для завершения CSS анимаций
                    setTimeout(() => {
                        if (this.map) {
                            this.map.invalidateSize();
                            console.log('[MapService] Map invalidated after visibility change');
                        }
                    }, 50);
                }
            });

            // Наблюдаем за изменениями атрибутов style и class
            this.mutationObserver.observe(this.container, {
                attributes: true,
                attributeFilter: ['style', 'class']
            });

            // Также наблюдаем за родительскими элементами (табы Sonata)
            let parent = this.container.parentElement;
            let depth = 0;
            while (parent && depth < 5) {
                this.mutationObserver.observe(parent, {
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
                parent = parent.parentElement;
                depth++;
            }
        }

        console.log('[MapService] Visibility observers set up');
    }

    /**
     * Проверка видимости контейнера
     * @private
     * @returns {boolean}
     */
    _isVisible() {
        if (!this.container) return false;
        
        const rect = this.container.getBoundingClientRect();
        const style = window.getComputedStyle(this.container);
        
        return rect.width > 0 && 
               rect.height > 0 && 
               style.display !== 'none' && 
               style.visibility !== 'hidden';
    }

    /**
     * Фикс иконок Leaflet для работы с Webpack
     * @private
     */
    _fixLeafletIcons() {
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
            iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
        });
    }
}
