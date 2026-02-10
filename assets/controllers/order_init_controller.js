import { Controller } from '@hotwired/stimulus';

/**
 * Order Init Controller
 * 
 * Инициализирует OrderOffer контроллер для формы Order.
 * Этот контроллер подключается к корневому элементу формы Order.
 */
export default class extends Controller {
    connect() {
        console.log('📋 Order Init Controller connected');
        
        // Получаем Order ID из URL или формы
        const orderId = this.getOrderId();
        
        if (!orderId) {
            console.warn('⚠️ Order ID not found, OrderOffer features disabled');
            return;
        }
        
        console.log('✅ Found Order ID:', orderId);
        
        // Ищем коллекцию offers
        const offersCollection = this.element.querySelector('[id*="offers"]');
        
        if (!offersCollection) {
            console.log('ℹ️ Offers collection not found yet, will try on tab change');
            this.setupTabListener(orderId);
            return;
        }
        
        this.initializeOfferController(offersCollection, orderId);
    }
    
    /**
     * Получить Order ID из URL или скрытого поля
     */
    getOrderId() {
        // Попробуем из URL: /admin/app/order/{id}/edit
        const urlMatch = window.location.pathname.match(/\/order\/([a-f0-9-]+)\//);
        if (urlMatch) {
            return urlMatch[1];
        }
        
        // Попробуем из формы
        const idInput = this.element.querySelector('input[name*="[id]"]');
        if (idInput && idInput.value) {
            return idInput.value;
        }
        
        return null;
    }
    
    /**
     * Настроить слушатель переключения вкладок
     */
    setupTabListener(orderId) {
        // Слушаем клики по вкладкам
        const tabs = this.element.querySelectorAll('.nav-tabs a[data-bs-toggle="tab"]');
        
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                console.log('🔄 Tab changed:', e.target.getAttribute('href'));
                
                const offersCollection = this.element.querySelector('[id*="offers"]');
                if (offersCollection && !offersCollection.hasAttribute('data-order-offer-initialized')) {
                    this.initializeOfferController(offersCollection, orderId);
                }
            });
        });
    }
    
    /**
     * Инициализировать контроллер для коллекции offers
     */
    initializeOfferController(collection, orderId) {
        console.log('🚀 Initializing OrderOffer controller for collection', collection);
        
        const calculateUrl = `/admin/order-offer/calculate/${orderId}`;
        
        // Добавляем data-атрибуты для Stimulus
        collection.setAttribute('data-controller', 'order-offer');
        collection.setAttribute('data-order-offer-calculate-url-value', calculateUrl);
        collection.setAttribute('data-order-offer-order-id-value', orderId);
        collection.setAttribute('data-order-offer-initialized', 'true');
        
        console.log('✅ OrderOffer controller initialized', {
            orderId,
            calculateUrl,
            element: collection
        });
    }
}
