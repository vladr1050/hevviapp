import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Address View Controller
 * 
 * Контроллер для просмотра адреса на карте в режиме read-only.
 * Используется в list view Sonata Admin для отображения адресов на OpenStreetMap.
 * Следует принципам SOLID: Single Responsibility, Dependency Inversion
 * 
 * @property {string} addressValue - Адрес для отображения
 * @property {string} nominatimUrlValue - URL API Nominatim для геокодирования
 * @property {number} defaultLatValue - Дефолтная широта
 * @property {number} defaultLngValue - Дефолтная долгота
 * @property {number} defaultZoomValue - Дефолтный уровень зума
 * @property {string} userAgentValue - User Agent для запросов к API
 */
export default class extends Controller {
    static values = {
        address: String,
        nominatimUrl: String,
        defaultLat: Number,
        defaultLng: Number,
        defaultZoom: Number,
        userAgent: String,
        errorGeocoding: String,
        loadingText: String
    }

    static targets = ['modal', 'map', 'errorMessage', 'loadingIndicator']

    /**
     * Инициализация контроллера
     */
    connect() {
        this.map = null;
        this.marker = null;
        
        // Фикс иконок Leaflet (проблема с Webpack)
        this._fixLeafletIcons();
    }

    /**
     * Очистка ресурсов при удалении контроллера
     */
    disconnect() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
    }

    /**
     * Открытие модального окна с картой
     */
    async openModal() {
        this.modalTarget.classList.add('show');
        this.modalTarget.style.display = 'block';
        document.body.classList.add('modal-open');
        
        // Показываем индикатор загрузки
        this._showLoading();
        this._clearError();
        
        // Инициализация карты после открытия модального окна
        if (!this.map) {
            await this._initializeMap();
        } else {
            // Обновление размера карты при повторном открытии
            setTimeout(() => this.map.invalidateSize(), 100);
        }
        
        // Геокодируем адрес и показываем на карте
        await this._geocodeAndShowAddress();
        
        // Скрываем индикатор загрузки
        this._hideLoading();
    }

    /**
     * Закрытие модального окна
     */
    closeModal() {
        this.modalTarget.classList.remove('show');
        this.modalTarget.style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    /**
     * Инициализация карты Leaflet
     * @private
     */
    async _initializeMap() {
        // Создание карты
        this.map = L.map(this.mapTarget).setView(
            [this.defaultLatValue, this.defaultLngValue],
            this.defaultZoomValue
        );

        // Добавление тайлов OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(this.map);
    }

    /**
     * Геокодирование адреса и отображение на карте
     * @private
     */
    async _geocodeAndShowAddress() {
        if (!this.addressValue) {
            this._showError(this.errorGeocodingValue);
            return;
        }

        try {
            const results = await this._geocodeAddress(this.addressValue);
            
            if (results.length === 0) {
                this._showError(this.errorGeocodingValue);
                return;
            }

            // Берем первый результат
            const location = results[0];
            const lat = parseFloat(location.lat);
            const lon = parseFloat(location.lon);

            // Центрируем карту на найденной точке
            this.map.setView([lat, lon], 16);
            
            // Устанавливаем маркер
            this._setMarker(lat, lon, this.addressValue);

        } catch (error) {
            console.error('Geocoding error:', error);
            this._showError(this.errorGeocodingValue);
        }
    }

    /**
     * Установка маркера на карте
     * @private
     * @param {number} lat - Широта
     * @param {number} lon - Долгота
     * @param {string} address - Адрес для popup
     */
    _setMarker(lat, lon, address) {
        // Удаляем старый маркер если есть
        if (this.marker) {
            this.map.removeLayer(this.marker);
        }

        // Создаем новый маркер
        this.marker = L.marker([lat, lon]).addTo(this.map);
        this.marker.bindPopup(address).openPopup();
    }

    /**
     * Прямое геокодирование (адрес -> координаты)
     * @private
     * @param {string} address - Адрес для поиска
     * @returns {Promise<Array>} Массив результатов
     */
    async _geocodeAddress(address) {
        const url = `${this.nominatimUrlValue}/search?` + new URLSearchParams({
            q: address,
            format: 'json',
            addressdetails: '1',
            limit: '1'
        });

        const response = await fetch(url, {
            headers: {
                'User-Agent': this.userAgentValue
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Отображение индикатора загрузки
     * @private
     */
    _showLoading() {
        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'block';
        }
    }

    /**
     * Скрытие индикатора загрузки
     * @private
     */
    _hideLoading() {
        if (this.hasLoadingIndicatorTarget) {
            this.loadingIndicatorTarget.style.display = 'none';
        }
    }

    /**
     * Отображение сообщения об ошибке
     * @private
     * @param {string} message - Текст ошибки
     */
    _showError(message) {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = message;
            this.errorMessageTarget.style.display = 'block';
        }
    }

    /**
     * Очистка сообщения об ошибке
     * @private
     */
    _clearError() {
        if (this.hasErrorMessageTarget) {
            this.errorMessageTarget.textContent = '';
            this.errorMessageTarget.style.display = 'none';
        }
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
