/**
 * Bancard VPOS Blocks Integration
 * JavaScript para integración con WooCommerce Checkout Blocks
 */

(function() {
    'use strict';

    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { createElement, useEffect, useState } = window.React;
    const { __ } = window.wp.i18n;
    const { decodeEntities } = window.wp.htmlEntities;

    // Obtener configuración del gateway
    const settings = window.wc.wcSettings.getSetting('bancard_vpos_data', {});
    const defaultLabel = __('Bancard VPOS', 'bancard-vpos');

    /**
     * Componente de etiqueta del método de pago
     */
    const Label = (props) => {
        const { PaymentMethodLabel } = props.components;
        return createElement(PaymentMethodLabel, {
            text: decodeEntities(settings.title || defaultLabel)
        });
    };

    /**
     * Componente de contenido del método de pago
     */
    const Content = (props) => {
        const { eventRegistration, emitResponse } = props;
        const { onPaymentSetup, onCheckoutValidation } = eventRegistration;
        const [isProcessing, setIsProcessing] = useState(false);
        const [processId, setProcessId] = useState('');

        useEffect(() => {
            const unsubscribePaymentSetup = onPaymentSetup(async () => {
                setIsProcessing(true);

                try {
                    // Aquí podemos agregar validaciones adicionales si es necesario
                    return {
                        type: emitResponse.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                bancard_environment: settings.environment,
                                bancard_public_key: settings.public_key
                            }
                        }
                    };
                } catch (error) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: error.message || settings.messages.error
                    };
                } finally {
                    setIsProcessing(false);
                }
            });

            const unsubscribeCheckoutValidation = onCheckoutValidation(async () => {
                // Validaciones adicionales si son necesarias
                return {
                    type: emitResponse.responseTypes.SUCCESS
                };
            });

            return () => {
                unsubscribePaymentSetup();
                unsubscribeCheckoutValidation();
            };
        }, [
            emitResponse.responseTypes.ERROR,
            emitResponse.responseTypes.SUCCESS,
            onPaymentSetup,
            onCheckoutValidation
        ]);

        // Contenido que se muestra cuando se selecciona este método de pago
        return createElement('div', {
            className: 'bancard-vpos-payment-method-content'
        }, [
            settings.description && createElement('p', {
                key: 'description',
                className: 'bancard-description'
            }, decodeEntities(settings.description)),
            
            createElement('div', {
                key: 'secure-notice',
                className: 'bancard-secure-notice'
            }, [
                createElement('span', {
                    key: 'icon',
                    className: 'bancard-secure-icon'
                }, '🔒'),
                createElement('span', {
                    key: 'text'
                }, settings.messages.secure_payment || __('Pago seguro con Bancard VPOS', 'bancard-vpos'))
            ]),

            isProcessing && createElement('div', {
                key: 'processing',
                className: 'bancard-processing-notice'
            }, settings.messages.processing || __('Procesando...', 'bancard-vpos'))
        ]);
    };

    /**
     * Componente para el editor (admin)
     */
    const Edit = (props) => {
        return createElement('div', {
            className: 'bancard-vpos-payment-method-edit'
        }, [
            createElement('p', {
                key: 'title'
            }, decodeEntities(settings.title || defaultLabel)),
            
            createElement('p', {
                key: 'description',
                style: { fontStyle: 'italic', color: '#666' }
            }, decodeEntities(settings.description || __('Los clientes serán redirigidos a Bancard para completar el pago', 'bancard-vpos')))
        ]);
    };

    /**
     * Función para determinar si el método de pago puede ser usado
     */
    const canMakePayment = () => {
        // Verificar configuración básica
        if (!settings.public_key) {
            if (settings.debug) {
                console.warn('Bancard VPOS: Clave pública no configurada');
            }
            return false;
        }

        return true;
    };

    /**
     * Configuración del método de pago
     */
    const bancardVPOSPaymentMethod = {
        name: 'bancard_vpos',
        label: createElement(Label),
        content: createElement(Content),
        edit: createElement(Edit),
        canMakePayment: canMakePayment,
        paymentMethodId: 'bancard_vpos',
        supports: {
            features: settings.supports || ['products'],
            showSavedCards: false,
            showSaveOption: false
        },
        ariaLabel: decodeEntities(settings.title || defaultLabel)
    };

    // Registrar el método de pago
    registerPaymentMethod(bancardVPOSPaymentMethod);

    // Debug logging
    if (settings.debug && window.console) {
        console.log('[Bancard VPOS Blocks] Payment method registered', {
            settings: settings,
            paymentMethod: bancardVPOSPaymentMethod
        });
    }

})();