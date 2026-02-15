import { latLngToCell, cellToBoundary, gridDisk } from 'h3-js';
import { polygon, multiPolygon, featureCollection } from '@turf/helpers';
import dissolve from '@turf/dissolve';

/**
 * HexagonService
 * 
 * Сервис для работы с H3 гексагональной индексацией.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает ТОЛЬКО за работу с H3 гексагонами
 * - Open/Closed: расширяемый через конфигурацию
 * - Liskov Substitution: может быть заменён другой реализацией гексагональной индексации
 * - Interface Segregation: предоставляет только необходимые методы
 * - Dependency Inversion: не зависит от конкретных реализаций карты
 * 
 * Используется как композиция в других сервисах.
 * 
 * @see https://h3geo.org/docs/
 */
export class HexagonService {
    /**
     * @param {Object} config - Конфигурация
     * @param {number} config.resolution - Разрешение H3 (0-15, где 0 - самые большие гексагоны)
     */
    constructor(config = {}) {
        // Разрешение по умолчанию 8 (гексагоны ~0.7 км²)
        // 7 - ~5 км², 8 - ~0.7 км², 9 - ~0.1 км²
        this.resolution = config.resolution ?? 8;
        
        console.log('[HexagonService] Initialized with resolution:', this.resolution);
    }

    /**
     * Установить разрешение H3
     * @param {number} resolution - Разрешение (0-15)
     */
    setResolution(resolution) {
        if (resolution < 0 || resolution > 15) {
            throw new Error('Resolution must be between 0 and 15');
        }
        this.resolution = resolution;
        console.log('[HexagonService] Resolution changed to:', resolution);
    }

    /**
     * Получить текущее разрешение
     * @returns {number}
     */
    getResolution() {
        return this.resolution;
    }

    /**
     * Конвертировать координаты в H3 индекс
     * @param {number} lat - Широта
     * @param {number} lng - Долгота
     * @param {number} resolution - Разрешение (опционально, использует this.resolution)
     * @returns {string} H3 индекс
     */
    latLngToH3(lat, lng, resolution = null) {
        const res = resolution ?? this.resolution;
        return latLngToCell(lat, lng, res);
    }

    /**
     * Получить границы гексагона в виде координат
     * @param {string} h3Index - H3 индекс
     * @returns {Array<Array<number>>} Массив координат [[lat, lng], ...]
     */
    getHexagonBoundary(h3Index) {
        // cellToBoundary возвращает массив [lat, lng]
        return cellToBoundary(h3Index);
    }

    /**
     * Получить гексагоны в радиусе от центрального
     * @param {string} h3Index - Центральный H3 индекс
     * @param {number} k - Радиус в гексагонах (k-ring)
     * @returns {Array<string>} Массив H3 индексов
     */
    getHexagonRing(h3Index, k = 1) {
        return gridDisk(h3Index, k);
    }

    /**
     * Получить все гексагоны, покрывающие заданную область
     * @param {Array<Array<number>>} bounds - Границы области [[lat, lng], ...]
     * @param {number} resolution - Разрешение (опционально)
     * @returns {Array<string>} Массив уникальных H3 индексов
     */
    getHexagonsForBounds(bounds, resolution = null) {
        const res = resolution ?? this.resolution;
        const hexagons = new Set();

        // Получаем центр области
        const center = this._calculateCenter(bounds);
        const centerH3 = latLngToCell(center.lat, center.lng, res);
        
        // Начинаем с центра
        hexagons.add(centerH3);
        
        // Расширяем область пока не покроем все границы
        let currentRing = [centerH3];
        let radius = 1;
        const maxRadius = 50; // Защита от бесконечного цикла
        
        while (radius < maxRadius) {
            const newHexagons = new Set();
            
            currentRing.forEach(h3Index => {
                const ring = gridDisk(h3Index, 1);
                ring.forEach(hex => {
                    if (!hexagons.has(hex)) {
                        newHexagons.add(hex);
                        hexagons.add(hex);
                    }
                });
            });
            
            // Проверяем покрываем ли мы всю область
            if (this._allBoundsCovered(bounds, Array.from(hexagons))) {
                break;
            }
            
            currentRing = Array.from(newHexagons);
            radius++;
        }
        
        // Фильтруем только гексагоны которые пересекаются с областью
        return Array.from(hexagons).filter(h3Index => 
            this._hexagonIntersectsBounds(h3Index, bounds)
        );
    }

    /**
     * Получить гексагоны для видимой области карты
     * @param {Object} mapBounds - Bounds объект Leaflet {north, south, east, west}
     * @param {number} resolution - Разрешение (опционально)
     * @returns {Array<string>} Массив H3 индексов
     */
    getHexagonsForMapBounds(mapBounds, resolution = null) {
        const res = resolution ?? this.resolution;
        
        const { north, south, east, west } = mapBounds;
        
        // Создаем сетку гексагонов покрывающую видимую область
        const hexagons = new Set();
        
        // Вычисляем шаг по широте и долготе для эффективного покрытия
        const latStep = (north - south) / 20;
        const lngStep = (east - west) / 20;
        
        for (let lat = south; lat <= north; lat += latStep) {
            for (let lng = west; lng <= east; lng += lngStep) {
                const h3Index = latLngToCell(lat, lng, res);
                hexagons.add(h3Index);
                
                // Добавляем соседние гексагоны для полного покрытия
                const neighbors = gridDisk(h3Index, 1);
                neighbors.forEach(neighbor => hexagons.add(neighbor));
            }
        }
        
        return Array.from(hexagons);
    }

    /**
     * Получить GeoJSON полигон для гексагона
     * @param {string} h3Index - H3 индекс
     * @returns {Object} GeoJSON Polygon
     */
    hexagonToGeoJson(h3Index) {
        const boundary = cellToBoundary(h3Index);
        
        // Конвертируем в формат GeoJSON [lng, lat]
        const coordinates = boundary.map(([lat, lng]) => [lng, lat]);
        
        // Замыкаем полигон
        coordinates.push(coordinates[0]);
        
        return {
            type: 'Polygon',
            coordinates: [coordinates]
        };
    }

    /**
     * Получить GeoJSON FeatureCollection для массива гексагонов
     * @param {Array<string>} h3Indexes - Массив H3 индексов
     * @returns {Object} GeoJSON FeatureCollection
     */
    hexagonsToGeoJson(h3Indexes) {
        const features = h3Indexes.map(h3Index => ({
            type: 'Feature',
            properties: {
                h3Index: h3Index
            },
            geometry: this.hexagonToGeoJson(h3Index)
        }));
        
        return {
            type: 'FeatureCollection',
            features: features
        };
    }

    /**
     * Определить является ли геометрия гексагональной зоной
     * @param {Object} geoJson - GeoJSON геометрия
     * @returns {boolean}
     */
    isHexagonalGeometry(geoJson) {
        if (!geoJson) return false;
        
        // Гексагональные зоны хранятся как MultiPolygon
        if (geoJson.type !== 'MultiPolygon') {
            return false;
        }
        
        // Проверяем что каждый полигон имеет ровно 7 точек (6 вершин + замыкающая)
        const polygons = geoJson.coordinates;
        
        if (polygons.length === 0) {
            return false;
        }
        
        // Проверяем первые несколько полигонов
        const samplesToCheck = Math.min(5, polygons.length);
        let hexagonCount = 0;
        
        for (let i = 0; i < samplesToCheck; i++) {
            const ring = polygons[i][0]; // Внешний ring полигона
            
            // Гексагон должен иметь 7 точек (6 вершин + замыкающая)
            if (ring.length === 7) {
                hexagonCount++;
            }
        }
        
        // Если большинство полигонов похожи на гексагоны, считаем что это гексагональная зона
        return hexagonCount >= samplesToCheck * 0.8;
    }

    /**
     * Извлечь H3 индексы из гексагональной геометрии
     * @param {Object} geoJson - GeoJSON MultiPolygon
     * @param {number} resolution - Разрешение для определения H3 индексов
     * @returns {Array<string>} Массив H3 индексов
     */
    extractH3IndexesFromGeometry(geoJson, resolution = null) {
        const res = resolution ?? this.resolution;
        
        if (geoJson.type !== 'MultiPolygon') {
            console.warn('[HexagonService] Cannot extract H3 from non-MultiPolygon geometry');
            return [];
        }
        
        const h3Indexes = [];
        
        // Для каждого полигона находим центр и определяем H3 индекс
        geoJson.coordinates.forEach((polygon, index) => {
            const ring = polygon[0]; // Внешний ring
            
            // Вычисляем центр полигона
            // ВАЖНО: GeoJSON использует формат [lng, lat]
            let sumLat = 0;
            let sumLng = 0;
            const pointsCount = ring.length - 1; // Минус замыкающая точка
            
            for (let i = 0; i < pointsCount; i++) {
                const [lng, lat] = ring[i]; // GeoJSON: [longitude, latitude]
                sumLat += lat;
                sumLng += lng;
            }
            
            const centerLat = sumLat / pointsCount;
            const centerLng = sumLng / pointsCount;
            
            // Получаем H3 индекс для центра
            // latLngToCell ожидает (lat, lng, resolution)
            const h3Index = latLngToCell(centerLat, centerLng, res);
            h3Indexes.push(h3Index);
            
            // Дебаг для первых 3 полигонов
            if (index < 3) {
                console.log(`[HexagonService] Polygon ${index}:`, {
                    center: [centerLat, centerLng],
                    h3Index: h3Index,
                    pointsCount: pointsCount
                });
            }
        });
        
        console.log('[HexagonService] Extracted H3 indexes from geometry:', h3Indexes.length);
        
        return h3Indexes;
    }

    /**
     * Объединить массив гексагонов в один монолитный полигон
     * Убирает внутренние границы, оставляет только внешний контур
     * @param {Array<string>} h3Indexes - Массив H3 индексов
     * @returns {Object} GeoJSON Polygon или MultiPolygon
     */
    mergeHexagonsToPolygon(h3Indexes) {
        if (h3Indexes.length === 0) {
            throw new Error('Cannot merge empty hexagons array');
        }
        
        if (h3Indexes.length === 1) {
            // Один гексагон - возвращаем как есть
            return this.hexagonToGeoJson(h3Indexes[0]);
        }
        
        console.log('[HexagonService] Merging hexagons:', h3Indexes.length);
        
        try {
            // Создаем Feature для каждого гексагона
            const features = h3Indexes.map(h3Index => {
                return polygon([this.hexagonToGeoJson(h3Index).coordinates], {
                    h3Index: h3Index
                });
            });
            
            // Создаем FeatureCollection
            const fc = featureCollection(features);
            
            // Используем dissolve для объединения всех полигонов в один
            // dissolve объединяет соседние полигоны, убирая внутренние границы
            const dissolved = dissolve(fc);
            
            console.log('[HexagonService] Hexagons merged successfully');
            console.log('[HexagonService] Result type:', dissolved.features[0].geometry.type);
            console.log('[HexagonService] Features count:', dissolved.features.length);
            
            // Возвращаем геометрию первого (и обычно единственного) feature
            // Может быть Polygon или MultiPolygon если гексагоны не все соседние
            return dissolved.features[0].geometry;
            
        } catch (error) {
            console.error('[HexagonService] Error merging hexagons:', error);
            
            // Fallback: возвращаем MultiPolygon из всех гексагонов
            console.warn('[HexagonService] Falling back to MultiPolygon without merge');
            const polygons = h3Indexes.map(h3Index => 
                this.hexagonToGeoJson(h3Index).coordinates
            );
            
            return {
                type: 'MultiPolygon',
                coordinates: polygons
            };
        }
    }

    /**
     * Вычислить центр области
     * @private
     * @param {Array<Array<number>>} bounds - Границы [[lat, lng], ...]
     * @returns {Object} {lat, lng}
     */
    _calculateCenter(bounds) {
        const lats = bounds.map(coord => coord[0]);
        const lngs = bounds.map(coord => coord[1]);
        
        return {
            lat: (Math.max(...lats) + Math.min(...lats)) / 2,
            lng: (Math.max(...lngs) + Math.min(...lngs)) / 2
        };
    }

    /**
     * Проверить покрываются ли все точки границ гексагонами
     * @private
     * @param {Array<Array<number>>} bounds - Границы
     * @param {Array<string>} h3Indexes - Массив H3 индексов
     * @returns {boolean}
     */
    _allBoundsCovered(bounds, h3Indexes) {
        return bounds.every(([lat, lng]) => {
            const pointH3 = latLngToCell(lat, lng, this.resolution);
            return h3Indexes.includes(pointH3);
        });
    }

    /**
     * Проверить пересекается ли гексагон с границами области
     * @private
     * @param {string} h3Index - H3 индекс
     * @param {Array<Array<number>>} bounds - Границы области
     * @returns {boolean}
     */
    _hexagonIntersectsBounds(h3Index, bounds) {
        // Простая проверка: если хотя бы одна вершина гексагона внутри области
        const hexBoundary = cellToBoundary(h3Index);
        
        // Находим bbox области
        const lats = bounds.map(coord => coord[0]);
        const lngs = bounds.map(coord => coord[1]);
        const bbox = {
            north: Math.max(...lats),
            south: Math.min(...lats),
            east: Math.max(...lngs),
            west: Math.min(...lngs)
        };
        
        // Проверяем пересечение
        return hexBoundary.some(([lat, lng]) => 
            lat >= bbox.south && lat <= bbox.north &&
            lng >= bbox.west && lng <= bbox.east
        );
    }

    /**
     * Получить информацию о разрешении
     * @param {number} resolution - Разрешение H3
     * @returns {Object} Информация о разрешении
     */
    static getResolutionInfo(resolution) {
        // Приблизительные размеры гексагонов для разных разрешений
        const resolutionData = {
            0: { area: '4250546.85 km²', edge: '1107.71 km', description: 'Континенты' },
            1: { area: '607220.98 km²', edge: '418.68 km', description: 'Большие регионы' },
            2: { area: '86745.85 km²', edge: '158.24 km', description: 'Регионы' },
            3: { area: '12392.26 km²', edge: '59.81 km', description: 'Области' },
            4: { area: '1770.32 km²', edge: '22.61 km', description: 'Районы' },
            5: { area: '252.90 km²', edge: '8.54 km', description: 'Города' },
            6: { area: '36.13 km²', edge: '3.23 km', description: 'Районы города' },
            7: { area: '5.16 km²', edge: '1.22 km', description: 'Кварталы' },
            8: { area: '0.74 km²', edge: '461.35 m', description: 'Улицы' },
            9: { area: '0.10 km²', edge: '174.38 m', description: 'Здания' },
            10: { area: '0.015 km²', edge: '65.91 m', description: 'Участки' },
            11: { area: '0.002 km²', edge: '24.91 m', description: 'Дома' },
            12: { area: '0.0003 km²', edge: '9.42 m', description: 'Комнаты' },
            13: { area: '0.00004 km²', edge: '3.56 m', description: 'Мебель' },
            14: { area: '0.000006 km²', edge: '1.35 m', description: 'Объекты' },
            15: { area: '0.0000009 km²', edge: '0.51 m', description: 'Детали' }
        };
        
        return resolutionData[resolution] || resolutionData[8];
    }
}
