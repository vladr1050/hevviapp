/**
 * GeoAreaService
 * 
 * Сервис для работы с гео-зонами (бизнес-логика).
 * 
 * Следует принципам SOLID:
 * - Single Responsibility: управление состоянием гео-зон
 * - Open/Closed: легко расширяемый новыми методами
 * - Dependency Inversion: работает с абстрактными данными
 */
export class GeoAreaService {
    constructor() {
        this.selectedAreas = new Map(); // Map<areaId, {id, name, countryISO3}>
    }

    /**
     * Добавить зону
     * @param {string} areaId - UUID зоны
     * @param {Object} areaData - Данные зоны {id, name, countryISO3}
     * @returns {boolean} - true если добавлена, false если уже существует
     */
    addArea(areaId, areaData) {
        if (this.selectedAreas.has(areaId)) {
            console.warn('[GeoAreaService] Area already exists:', areaId);
            return false;
        }

        this.selectedAreas.set(areaId, areaData);
        console.log('[GeoAreaService] Area added:', areaData.name);
        return true;
    }

    /**
     * Удалить зону
     * @param {string} areaId - UUID зоны
     * @returns {boolean} - true если удалена
     */
    removeArea(areaId) {
        const existed = this.selectedAreas.delete(areaId);
        
        if (existed) {
            console.log('[GeoAreaService] Area removed:', areaId);
        }
        
        return existed;
    }

    /**
     * Проверить наличие зоны
     * @param {string} areaId - UUID зоны
     * @returns {boolean}
     */
    hasArea(areaId) {
        return this.selectedAreas.has(areaId);
    }

    /**
     * Получить зону
     * @param {string} areaId - UUID зоны
     * @returns {Object|undefined}
     */
    getArea(areaId) {
        return this.selectedAreas.get(areaId);
    }

    /**
     * Получить все зоны
     * @returns {Array<Object>}
     */
    getAllAreas() {
        return Array.from(this.selectedAreas.values());
    }

    /**
     * Получить количество зон
     * @returns {number}
     */
    getCount() {
        return this.selectedAreas.size;
    }

    /**
     * Получить уникальные ISO3 коды стран
     * @returns {Set<string>}
     */
    getUniqueCountryISO3() {
        const iso3Set = new Set();
        this.selectedAreas.forEach(area => {
            if (area.countryISO3) {
                iso3Set.add(area.countryISO3);
            }
        });
        return iso3Set;
    }

    /**
     * Очистить все зоны
     */
    clear() {
        this.selectedAreas.clear();
        console.log('[GeoAreaService] All areas cleared');
    }

    /**
     * Получить зоны отсортированные в обратном порядке (новые сверху)
     * @returns {Array<Object>}
     */
    getAreasReversed() {
        return this.getAllAreas().reverse();
    }
}
