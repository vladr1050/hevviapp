/**
 * ApiService
 * 
 * Сервис для работы с API эндпоинтами.
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: отвечает ТОЛЬКО за HTTP запросы
 * - Open/Closed: легко добавлять новые методы без изменения существующих
 * - Dependency Inversion: возвращает промисы, не зависит от UI
 */
export class ApiService {
    /**
     * @param {Object} config - Конфигурация API
     * @param {string} config.countriesUrl - URL для получения стран
     * @param {string} config.citiesUrl - URL для получения городов
     * @param {string} config.geometryUrl - URL для получения геометрии (с плейсхолдером __ID__)
     */
    constructor(config) {
        this.countriesUrl = config.countriesUrl;
        this.citiesUrl = config.citiesUrl;
        this.geometryUrl = config.geometryUrl;
    }

    /**
     * Получить список стран
     * @returns {Promise<Array>}
     */
    async getCountries() {
        try {
            const response = await fetch(this.countriesUrl);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Loaded countries:', data.length);
            return data;
        } catch (error) {
            console.error('[ApiService] Error loading countries:', error);
            throw error;
        }
    }

    /**
     * Получить список городов по ISO3 коду страны
     * @param {string} countryISO3 - ISO3 код страны
     * @returns {Promise<Array>}
     */
    async getCities(countryISO3) {
        try {
            const url = `${this.citiesUrl}?countryISO3=${countryISO3}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Loaded cities:', data.length, 'for', countryISO3);
            return data;
        } catch (error) {
            console.error('[ApiService] Error loading cities:', error);
            throw error;
        }
    }

    /**
     * Получить геометрию гео-зоны
     * @param {string} areaId - UUID зоны
     * @returns {Promise<Object>}
     */
    async getGeometry(areaId) {
        try {
            const url = this.geometryUrl.replace('__ID__', areaId);
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Loaded geometry for:', data.name);
            return data;
        } catch (error) {
            console.error('[ApiService] Error loading geometry:', error);
            throw error;
        }
    }

    /**
     * Получить геометрии нескольких зон
     * @param {Array<string>} areaIds - Массив UUID зон
     * @returns {Promise<Array>}
     */
    async getMultipleGeometries(areaIds) {
        const promises = areaIds.map(id => this.getGeometry(id));
        
        try {
            const results = await Promise.allSettled(promises);
            
            const successful = results
                .filter(r => r.status === 'fulfilled')
                .map(r => r.value);
            
            const failed = results
                .filter(r => r.status === 'rejected')
                .length;
            
            console.log('[ApiService] Loaded geometries:', {
                total: areaIds.length,
                successful: successful.length,
                failed: failed
            });
            
            return successful;
        } catch (error) {
            console.error('[ApiService] Error loading multiple geometries:', error);
            throw error;
        }
    }
}
