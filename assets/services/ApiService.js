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
     * @param {string} config.customAreasUrl - URL для получения кастомных зон
     * @param {string} config.customAreaCreateUrl - URL для создания/редактирования кастомных зон
     */
    constructor(config) {
        this.countriesUrl = config.countriesUrl;
        this.citiesUrl = config.citiesUrl;
        this.geometryUrl = config.geometryUrl;
        this.customAreasUrl = config.customAreasUrl || '';
        this.customAreaCreateUrl = config.customAreaCreateUrl || '';
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

    /**
     * Получить список кастомных зон по ISO3 коду страны
     * @param {string} countryISO3 - ISO3 код страны
     * @returns {Promise<Array>}
     */
    async getCustomAreas(countryISO3) {
        try {
            const url = `${this.customAreasUrl}?countryISO3=${countryISO3}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Loaded custom areas:', data.length, 'for', countryISO3);
            return data;
        } catch (error) {
            console.error('[ApiService] Error loading custom areas:', error);
            throw error;
        }
    }

    /**
     * Создать кастомную зону напрямую в БД
     * @param {Object} areaData - Данные зоны {name, geometry, countryISO3}
     * @returns {Promise<Object>}
     */
    async createCustomArea(areaData) {
        try {
            const response = await fetch(this.customAreaCreateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(areaData),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Custom area created:', data.id);
            return data;
        } catch (error) {
            console.error('[ApiService] Error creating custom area:', error);
            throw error;
        }
    }

    /**
     * Обновить кастомную зону
     * @param {string} areaId - ID зоны
     * @param {Object} areaData - Данные зоны {name, geometry}
     * @returns {Promise<Object>}
     */
    async updateCustomArea(areaId, areaData) {
        try {
            const url = `${this.customAreaCreateUrl}/${areaId}`;
            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(areaData),
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || `HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Custom area updated:', data.id);
            return data;
        } catch (error) {
            console.error('[ApiService] Error updating custom area:', error);
            throw error;
        }
    }

    /**
     * Удалить кастомную зону
     * @param {string} areaId - ID зоны
     * @returns {Promise<Object>}
     */
    async deleteCustomArea(areaId) {
        try {
            const url = `${this.customAreaCreateUrl}/${areaId}`;
            const response = await fetch(url, {
                method: 'DELETE',
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('[ApiService] Custom area deleted:', areaId);
            return data;
        } catch (error) {
            console.error('[ApiService] Error deleting custom area:', error);
            throw error;
        }
    }

}
