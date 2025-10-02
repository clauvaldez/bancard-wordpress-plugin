/**
 * Bancard VPOS Frontend JavaScript
 * Compatible with WooCommerce 9.0+ and modern browsers
 */

(function($) {
    'use strict';
    
    // Verificar dependencias
    if (typeof BancardVPOS === 'undefined') {
        console.warn('BancardVPOS object not found');
        return;
    }

    var BancardVPOSHandler = {
        
        // Configuración
        config: {
            environment: BancardVPOS.environment || 'staging',
            ajax_url: BancardVPOS.ajax_url,
            nonce: BancardVPOS.nonce,
            debug: BancardVPOS.debug || false
        },

        // URLs de scripts de Bancard
        scriptUrls: {
            production: 'https://vpos.infonet.com.py/checkout/javascript/dist/bancard-checkout-4.0.0.js',
            staging: 'https://vpos.infonet.com.py:8888/checkout/javascript/dist/bancard-checkout-4.0.0.js'
        },

        // Estado
        state: {
            scriptLoaded: false,
            processing: false,
            iframeLoaded: false
        },

        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.checkForProcessId();
            this.log('Bancard VPOS Handler initialized');
        },

        /**
         * Vincular eventos
         */
        bindEvents: function() {
            var self = this;

            // Evento de checkout de WooCommerce
            $('body').on('checkout_place_order_bancard_vpos', function() {
                return self.handleCheckoutSubmit();
            });

            // Cambio de método de pago
            $(document).on('change', 'input[name="payment_method"]', function() {
                if ($(this).val() === 'bancard_vpos') {
                    self.onPaymentMethodSelected();
                }
            });

            // Manejo de errores AJAX
            $(document).ajaxError(function(event, jqXHR, ajaxSettings) {
                if (self.isBancardAjax(ajaxSettings)) {
                    self.handleAjaxError(jqXHR);
                }
            });

            // Manejo de éxito AJAX
            $(document).ajaxSuccess(function(event, xhr, settings) {
                if (self.isBancardAjax(settings)) {
                    self.handleAjaxSuccess(xhr);
                }
            });

            // Evento personalizado para abrir iframe
            $(document).on('bancard:openIframe', function(e, processId) {
                self.openIframe(processId);
            });
        },

        /**
         * Verificar si hay process_id en la URL
         */
        checkForProcessId: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var processId = urlParams.get('bancard_process');
            
            if (processId && $('#bancard-iframe-container').length > 0) {
                this.openIframe(processId);
            }

            // También verificar en data attribute
            var $container = $('#bancard-iframe-container');
            if ($container.length > 0 && $container.data('process-id')) {
                this.openIframe($container.data('process-id'));
            }
        },

        /**
         * Manejar envío del checkout
         */
        handleCheckoutSubmit: function() {
            if (this.state.processing) {
                return false;
            }

            this.showMessage(BancardVPOS.messages.processing, 'info');
            return true; // Permitir envío normal del formulario
        },

        /**
         * Cuando se selecciona Bancard como método de pago
         */
        onPaymentMethodSelected: function() {
            this.log('Bancard VPOS selected as payment method');
            // Aquí se puede agregar lógica adicional si es necesaria
        },

        /**
         * Abrir iframe de Bancard
         */
        openIframe: function(processId) {
            var self = this;
            
            if (!processId) {
                this.showMessage('Process ID no encontrado', 'error');
                return;
            }

            this.log('Opening iframe for process ID: ' + processId);

            var $container = $('#bancard-iframe-container');
            if ($container.length === 0) {
                $container = $('<div id="bancard-iframe-container"></div>').appendTo('.bancard-payment-container, body');
            }

            $container.show().html('<div class="bancard-loading-spinner"><span class="spinner"></span> Cargando formulario de pago...</div>');

            this.loadBancardScript(function() {
                if (typeof Bancard !== 'undefined' && Bancard.Checkout) {
                    try {
                        Bancard.Checkout.createForm('bancard-iframe-container', processId, {
                            onReady: function() {
                                self.log('Bancard iframe ready');
                                self.state.iframeLoaded = true;
                                $container.find('.bancard-loading-spinner').remove();
                            },
                            onError: function(error) {
                                self.log('Bancard iframe error: ' + JSON.stringify(error));
                                self.showMessage('Error al cargar el formulario de pago', 'error');
                            },
                            onComplete: function(data) {
                                self.log('Payment completed: ' + JSON.stringify(data));
                                self.handlePaymentComplete(data);
                            }
                        });
                    } catch (error) {
                        self.log('Error creating Bancard form: ' + error.message);
                        self.showMessage('Error al inicializar el formulario de pago', 'error');
                    }
                } else {
                    self.showMessage('Error: Script de Bancard no disponible', 'error');
                }
            });
        },

        /**
         * Cargar script de Bancard
         */
        loadBancardScript: function(callback) {
            var self = this;
            
            if (this.state.scriptLoaded && typeof Bancard !== 'undefined') {
                callback();
                return;
            }

            var scriptUrl = this.scriptUrls[this.config.environment];
            
            $.getScript(scriptUrl)
                .done(function() {
                    self.log('Bancard script loaded successfully');
                    self.state.scriptLoaded = true;
                    callback();
                })
                .fail(function(jqxhr, settings, exception) {
                    self.log('Failed to load Bancard script: ' + exception);
                    self.showMessage('Error al cargar el script de Bancard', 'error');
                });
        },

        /**
         * Manejar completación de pago
         */
        handlePaymentComplete: function(data) {
            this.log('Payment completion data: ' + JSON.stringify(data));
            
            // Mostrar mensaje de éxito
            this.showMessage('Pago procesado exitosamente', 'success');
            
            // Redirigir si es necesario
            if (data.redirect_url) {
                setTimeout(function() {
                    window.location.href = data.redirect_url;
                }, 2000);
            }
        },

        /**
         * Verificar si es una petición AJAX de Bancard
         */
        isBancardAjax: function(settings) {
            return settings.url === this.config.ajax_url && 
                   settings.data && 
                   settings.data.indexOf('bancard') !== -1;
        },

        /**
         * Manejar errores AJAX
         */
        handleAjaxError: function(jqXHR) {
            var message = BancardVPOS.messages.error;
            
            try {
                var response = JSON.parse(jqXHR.responseText);
                if (response.data && typeof response.data === 'string') {
                    message = response.data;
                }
            } catch(e) {
                this.log('Error parsing AJAX error response');
            }
            
            this.showMessage(message, 'error');
            this.state.processing = false;
        },

        /**
         * Manejar éxito AJAX
         */
        handleAjaxSuccess: function(xhr) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success && response.data && response.data.status) {
                    switch (response.data.status) {
                        case 'success':
                            this.showMessage('Pago procesado exitosamente', 'success');
                            break;
                        case 'redirect':
                            this.showMessage(BancardVPOS.messages.redirecting, 'info');
                            if (response.data.redirect_url) {
                                setTimeout(function() {
                                    window.location.href = response.data.redirect_url;
                                }, 1500);
                            }
                            break;
                        case 'failed':
                            this.showMessage('El pago no pudo ser procesado', 'error');
                            break;
                    }
                }
            } catch(e) {
                this.log('Error parsing AJAX success response');
            }
        },

        /**
         * Mostrar mensaje al usuario
         */
        showMessage: function(message, type) {
            type = type || 'error';
            
            var className = 'woocommerce-message';
            switch (type) {
                case 'error':
                    className = 'woocommerce-error';
                    break;
                case 'info':
                    className = 'woocommerce-info';
                    break;
                case 'success':
                    className = 'woocommerce-message';
                    break;
            }
            
            var $message = $('<div class="' + className + ' bancard-message" role="alert">' + 
                           '<span>' + message + '</span></div>');
            
            // Buscar contenedor de mensajes
            var $target = $('.woocommerce-notices-wrapper').first();
            if ($target.length === 0) {
                $target = $('form.checkout, form.woocommerce-checkout, .woocommerce-order-received').first();
                if ($target.length > 0) {
                    $target = $('<div class="woocommerce-notices-wrapper"></div>').prependTo($target);
                } else {
                    $target = $('<div class="woocommerce-notices-wrapper"></div>').prependTo('body');
                }
            }
            
            // Limpiar mensajes anteriores del mismo tipo
            $target.find('.' + className + '.bancard-message').remove();
            
            // Agregar nuevo mensaje
            $target.prepend($message);
            
            // Scroll al mensaje
            this.scrollToElement($target);

            // Auto-hide para mensajes de info
            if (type === 'info') {
                setTimeout(function() {
                    $message.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },

        /**
         * Scroll a elemento
         */
        scrollToElement: function($element) {
            if ($element.length > 0) {
                $('html, body').animate({
                    scrollTop: $element.offset().top - 100
                }, 500);
            }
        },

        /**
         * Logging
         */
        log: function(message) {
            if (this.config.debug && window.console) {
                console.log('[Bancard VPOS] ' + message);
            }
        }
    };

    // Funciones globales para compatibilidad hacia atrás
    window.bancardCreateSingle = function(order_id, onSuccess, onError) {
        $.post(BancardVPOS.ajax_url, { 
            action: 'bancard_create_payment', 
            order_id: order_id,
            nonce: BancardVPOS.nonce
        })
        .done(function(resp) {
            if (resp && resp.success) {
                if (onSuccess) onSuccess(resp.data);
            } else {
                if (onError) onError(resp);
            }
        })
        .fail(function(err) { 
            if (onError) onError(err); 
        });
    };

    window.bancardOpenIframe = function(process_id) {
        BancardVPOSHandler.openIframe(process_id);
    };

    // Inicializar cuando el DOM esté listo
    $(document).ready(function() {
        BancardVPOSHandler.init();
    });

    // Exponer handler para uso externo
    window.BancardVPOSHandler = BancardVPOSHandler;

})(jQuery);