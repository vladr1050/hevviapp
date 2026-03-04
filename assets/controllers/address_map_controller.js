import { Controller } from '@hotwired/stimulus';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Address Map Controller
 * 
 * Управляет интерактивной картой для выбора адресов с геокодированием.
 * Следует принципам SOLID: Single Responsibility, Open/Closed, Dependency Inversion
 * 
 * @property {string} nominatimUrlValue - URL API Nominatim для геокодирования
 * @property {number} defaultLatValue - Дефолтная широта
 * @property {number} defaultLngValue - Дефолтная долгота
 * @property {number} defaultZoomValue - Дефолтный уровень зума
 * @property {string} userAgentValue - User Agent для запросов к API
 * @property {string} targetInputIdValue - ID целевого input поля для адреса
 */
export default class extends Controller {
    static values = {
        nominatimUrl: String,
        defaultLat: Number,
        defaultLng: Number,
        defaultZoom: Number,
        userAgent: String,
        targetInputId: String,
        latInputId: String,
        lngInputId: String,
        errorNoSelection: String,
        errorNotFound: String,
        errorGeocoding: String,
        errorSearch: String,
        errorEmpty: String,
        searchingText: String,
        searchButtonText: String
    }

    static targets = ['modal', 'map', 'searchInput', 'searchButton', 'cancelButton', 'confirmButton', 'errorMessage']

    /**
     * Инициализация контроллера
     */
    connect() {
        this.map = null;
        this.marker = null;
        this.selectedAddress = null;
        this.selectedCoordinates = null;
        
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
    openModal() {
        this.modalTarget.classList.add('show');
        this.modalTarget.style.display = 'block';
        document.body.classList.add('modal-open');
        
        // Инициализация карты после открытия модального окна
        if (!this.map) {
            this._initializeMap();
        } else {
            // Обновление размера карты при повторном открытии
            setTimeout(() => this.map.invalidateSize(), 100);
        }
        
        this._clearError();
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
     * Подтверждение выбора адреса
     */
    confirmSelection() {
        if (!this.selectedAddress) {
            this._showError(this.errorNoSelectionValue);
            return;
        }

        const targetInput = document.getElementById(this.targetInputIdValue);
        if (targetInput) {
            targetInput.value = this.selectedAddress;
            // Триггерим событие change для возможной валидации
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Сохраняем координаты в скрытые поля если они указаны
        if (this.selectedCoordinates && this.hasLatInputIdValue && this.hasLngInputIdValue) {
            const latInput = document.getElementById(this.latInputIdValue);
            const lngInput = document.getElementById(this.lngInputIdValue);
            
            if (latInput) {
                latInput.value = this.selectedCoordinates.lat;
                latInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            if (lngInput) {
                lngInput.value = this.selectedCoordinates.lng;
                lngInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        this.closeModal();
    }

    /**
     * Поиск адреса по тексту
     */
    async searchAddress(event) {
        // Предотвращаем отправку формы при Enter
        if (event && event.type === 'keydown') {
            event.preventDefault();
        }
        
        const searchQuery = this.searchInputTarget.value.trim();
        
        if (!searchQuery) {
            this._showError(this.errorEmptyValue);
            return;
        }

        this._clearError();
        this.searchButtonTarget.disabled = true;
        this.searchButtonTarget.textContent = this.searchingTextValue;

        try {
            const results = await this._geocodeAddress(searchQuery);
            
            if (results.length === 0) {
                this._showError(this.errorNotFoundValue);
                return;
            }

            // Берем первый результат
            const location = results[0];
            const lat = parseFloat(location.lat);
            const lon = parseFloat(location.lon);

            // Центрируем карту на найденной точке
            this.map.setView([lat, lon], 16);
            
            // Устанавливаем маркер
            this._setMarker(lat, lon);
            
            // Получаем полный адрес для выбранной точки
            await this._reverseGeocode(lat, lon);

        } catch (error) {
            console.error('Search error:', error);
            this._showError(this.errorSearchValue);
        } finally {
            this.searchButtonTarget.disabled = false;
            this.searchButtonTarget.textContent = this.searchButtonTextValue;
        }
    }

    /**
     * Инициализация карты Leaflet
     * @private
     */
    _initializeMap() {
        // Создание карты
        this.map = L.map(this.mapTarget).setView(
            [this.defaultLatValue, this.defaultLngValue],
            this.defaultZoomValue
        );

        // Добавление тайлов CartoDB Voyager (английские подписи)
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20
        }).addTo(this.map);

        // Обработка клика по карте
        this.map.on('click', (e) => this._onMapClick(e));
    }

    /**
     * Обработка клика по карте
     * @private
     * @param {L.LeafletMouseEvent} e - Событие клика
     */
    async _onMapClick(e) {
        const { lat, lng } = e.latlng;
        this._setMarker(lat, lng);
        await this._reverseGeocode(lat, lng);
    }

    /**
     * Установка маркера на карте
     * @private
     * @param {number} lat - Широта
     * @param {number} lng - Долгота
     */
    _setMarker(lat, lng) {
        // Удаляем старый маркер если есть
        if (this.marker) {
            this.map.removeLayer(this.marker);
        }

        // Создаем новый маркер
        this.marker = L.marker([lat, lng]).addTo(this.map);
        this.selectedCoordinates = { lat, lng };
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
            limit: '5',
            'accept-language': 'en'
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
     * Обратное геокодирование (координаты -> адрес)
     * @private
     * @param {number} lat - Широта
     * @param {number} lng - Долгота
     */
    async _reverseGeocode(lat, lng) {
        this._clearError();
        
        try {
            const url = `${this.nominatimUrlValue}/reverse?` + new URLSearchParams({
                lat: lat.toString(),
                lon: lng.toString(),
                format: 'json',
                addressdetails: '1',
                'accept-language': 'en'
            });

            const response = await fetch(url, {
                headers: {
                    'User-Agent': this.userAgentValue
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.selectedAddress = this._formatAddress(data);
            
            // Обновляем подпись маркера
            if (this.marker) {
                this.marker.bindPopup(this.selectedAddress).openPopup();
            }

        } catch (error) {
            console.error('Reverse geocoding error:', error);
            this._showError(this.errorGeocodingValue);
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
     * Форматирование адреса в читаемый вид
     * @private
     * @param {Object} data - Данные от Nominatim API
     * @returns {string} Отформатированный адрес
     */
    _formatAddress(data) {
        const addr = data.address || {};
        const parts = [];
        
        // Улица и номер дома
        const street = addr.road || addr.street || '';
        const houseNumber = addr.house_number || '';
        if (street) {
            parts.push(houseNumber ? `${street} ${houseNumber}` : street);
        }
        
        // Индекс и город
        const postcode = addr.postcode || '';
        const city = addr.city || addr.town || addr.village || addr.municipality || '';
        if (postcode && city) {
            parts.push(`${postcode} ${city}`);
        } else if (city) {
            parts.push(city);
        } else if (postcode) {
            parts.push(postcode);
        }
        
        // Страна
        const country = addr.country || '';
        if (country) {
            parts.push(country);
        }
        
        return parts.length > 0 ? parts.join(', ') : data.display_name;
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
