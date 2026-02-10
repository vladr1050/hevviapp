import { Controller } from '@hotwired/stimulus';

/**
 * Order Offer Controller
 * 
 * Управляет добавлением OrderOffer с автоматическим расчетом цены.
 * Следует принципам SOLID: Single Responsibility, Open/Closed, Dependency Inversion
 * 
 * @property {string} calculateUrlValue - URL для расчета OrderOffer
 * @property {string} orderIdValue - ID заказа
 */
export default class extends Controller {
    static values = {
        calculateUrl: String,
        orderId: String,
    }

    /**
     * Инициализация контроллера
     */
    connect() {
        console.log('🎯 Order Offer Controller CONNECTED!', {
            orderId: this.orderIdValue,
            calculateUrl: this.calculateUrlValue,
            element: this.element
        });
        
        // Небольшая задержка для полной загрузки DOM
        setTimeout(() => this.interceptAddButton(), 100);
    }

    /**
     * Перехватить кнопку добавления в Sonata Collection
     */
    interceptAddButton() {
        // Ищем кнопку добавления в коллекции OrderOffer внутри нашего элемента
        const addButton = this.element.querySelector('a.sonata-collection-add');
        
        if (!addButton) {
            console.warn('Add button not found in OrderOffer collection', this.element);
            // Попробуем найти через небольшую задержку (для случаев динамической загрузки)
            setTimeout(() => {
                const retryButton = this.element.querySelector('a.sonata-collection-add');
                if (retryButton) {
                    console.log('Found add button on retry');
                    this.setupButtonHandler(retryButton);
                }
            }, 500);
            return;
        }

        console.log('Found add button, setting up handler');
        this.setupButtonHandler(addButton);
    }

    /**
     * Настроить обработчик для кнопки
     */
    setupButtonHandler(addButton) {
        // Сохраняем оригинальный href
        this.originalHref = addButton.getAttribute('href');
        
        // Заменяем на наш обработчик
        addButton.addEventListener('click', (e) => this.handleAdd(e, addButton));
    }

    /**
     * Обработать добавление OrderOffer
     * 
     * @param {Event} e - Событие клика
     * @param {HTMLElement} addButton - Кнопка добавления
     */
    async handleAdd(e, addButton) {
        e.preventDefault();
        e.stopPropagation();

        // Показываем индикатор загрузки
        this.setLoading(addButton, true);

        try {
            // Пытаемся рассчитать OrderOffer
            const response = await fetch(this.calculateUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const result = await response.json();

            if (result.success) {
                // Успешно рассчитали - показываем сообщение и перезагружаем страницу
                this.showSuccessMessage(result.message);
                
                // Перезагружаем страницу для обновления формы
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                // Не удалось рассчитать - показываем предупреждение
                this.showWarningMessage(result.message);
                
                // Добавляем пустой OrderOffer для ручного заполнения
                if (this.originalAddHandler) {
                    this.originalAddHandler.call(addButton, e);
                }
            }
        } catch (error) {
            console.error('Error calculating OrderOffer:', error);
            this.showErrorMessage('Ошибка при расчете коммерческого предложения');
            
            // В случае ошибки тоже добавляем пустой OrderOffer
            if (this.originalAddHandler) {
                this.originalAddHandler.call(addButton, e);
            }
        } finally {
            this.setLoading(addButton, false);
        }
    }

    /**
     * Установить состояние загрузки кнопки
     * 
     * @param {HTMLElement} button - Кнопка
     * @param {boolean} loading - Флаг загрузки
     */
    setLoading(button, loading) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Расчет...';
        } else {
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    }

    /**
     * Показать сообщение об успехе
     * 
     * @param {string} message - Текст сообщения
     */
    showSuccessMessage(message) {
        this.showFlashMessage('success', message);
    }

    /**
     * Показать предупреждение
     * 
     * @param {string} message - Текст сообщения
     */
    showWarningMessage(message) {
        this.showFlashMessage('warning', message);
    }

    /**
     * Показать сообщение об ошибке
     * 
     * @param {string} message - Текст сообщения
     */
    showErrorMessage(message) {
        this.showFlashMessage('danger', message);
    }

    /**
     * Показать flash сообщение
     * 
     * @param {string} type - Тип сообщения (success, warning, danger, info)
     * @param {string} message - Текст сообщения
     */
    showFlashMessage(type, message) {
        // Создаем элемент alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        // Находим контейнер для сообщений или создаем его
        let container = document.querySelector('.sonata-ba-content .alert-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'alert-container mb-3';
            
            const content = document.querySelector('.sonata-ba-content');
            if (content) {
                content.insertBefore(container, content.firstChild);
            }
        }

        // Добавляем сообщение
        container.appendChild(alert);

        // Автоматически удаляем через 5 секунд
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }, 5000);
    }
}
