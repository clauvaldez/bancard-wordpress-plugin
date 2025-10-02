<?php
/**
 * Bancard VPOS Gateway Class
 * Compatible with WooCommerce 9.0+ and WordPress 6.7+
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

class WC_Bancard_VPOS_Gateway extends WC_Payment_Gateway {
    
    /**
     * Gateway properties
     */
    public $public_key;
    public $private_key;
    public $environment;
    public $debug;
    
    /**
     * API endpoints
     */
    private $api_endpoints = array(
        'production' => array(
            'single_buy' => 'https://vpos.infonet.com.py/vpos/api/0.3/single_buy',
            'confirm' => 'https://vpos.infonet.com.py/vpos/api/0.3/confirmation',
            'rollback' => 'https://vpos.infonet.com.py/vpos/api/0.3/rollback',
            'checkout_js' => 'https://vpos.infonet.com.py/checkout/javascript/dist/bancard-checkout-4.0.0.js'
        ),
        'staging' => array(
            'single_buy' => 'https://vpos.infonet.com.py:8888/vpos/api/0.3/single_buy',
            'confirm' => 'https://vpos.infonet.com.py:8888/vpos/api/0.3/confirmation',
            'rollback' => 'https://vpos.infonet.com.py:8888/vpos/api/0.3/rollback',
            'checkout_js' => 'https://vpos.infonet.com.py:8888/checkout/javascript/dist/bancard-checkout-4.0.0.js'
        )
    );

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'bancard_vpos';
        $this->method_title = __('Bancard VPOS', 'bancard-vpos');
        $this->method_description = __('Procesa pagos con tarjetas de crédito y débito a través de Bancard VPOS', 'bancard-vpos');
        $this->has_fields = true;
        $this->icon = BANCARD_VPOS_URL . 'assets/bancard-icon.png';
        
        // Características soportadas
        $this->supports = array(
            'products',
            'refunds'
        );

        // Inicializar configuración
        $this->init_form_fields();
        $this->init_settings();

        // Obtener configuración
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->public_key = $this->get_option('public_key');
        $this->private_key = $this->get_option('private_key');
        $this->environment = $this->get_option('environment', 'staging');
        $this->debug = $this->get_option('debug', 'no');

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_ajax_bancard_create_payment', array($this, 'ajax_create_payment'));
        add_action('wp_ajax_nopriv_bancard_create_payment', array($this, 'ajax_create_payment'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        
        // Webhook para confirmaciones
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'process_webhook'));
        
        // Hook para procesamiento con Store API (Blocks)
        add_action('woocommerce_rest_checkout_process_payment_with_context', array($this, 'process_payment_with_context'), 10, 2);
    }

    /**
     * Inicializar campos de configuración
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Deshabilitar', 'bancard-vpos'),
                'type' => 'checkbox',
                'label' => __('Habilitar Bancard VPOS', 'bancard-vpos'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Título', 'bancard-vpos'),
                'type' => 'text',
                'description' => __('Título que verán los clientes durante el checkout', 'bancard-vpos'),
                'default' => __('Tarjeta de Crédito/Débito', 'bancard-vpos'),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Descripción', 'bancard-vpos'),
                'type' => 'textarea',
                'description' => __('Descripción que verán los clientes durante el checkout', 'bancard-vpos'),
                'default' => __('Pagar de forma segura con tu tarjeta a través de Bancard VPOS', 'bancard-vpos'),
                'desc_tip' => true
            ),
            'environment' => array(
                'title' => __('Entorno', 'bancard-vpos'),
                'type' => 'select',
                'description' => __('Selecciona el entorno de Bancard VPOS', 'bancard-vpos'),
                'options' => array(
                    'staging' => __('Pruebas (Staging)', 'bancard-vpos'),
                    'production' => __('Producción', 'bancard-vpos')
                ),
                'default' => 'staging',
                'desc_tip' => true
            ),
            'public_key' => array(
                'title' => __('Clave Pública', 'bancard-vpos'),
                'type' => 'text',
                'description' => __('Tu clave pública proporcionada por Bancard', 'bancard-vpos'),
                'desc_tip' => true
            ),
            'private_key' => array(
                'title' => __('Clave Privada', 'bancard-vpos'),
                'type' => 'password',
                'description' => __('Tu clave privada proporcionada por Bancard', 'bancard-vpos'),
                'desc_tip' => true
            ),
            'debug' => array(
                'title' => __('Modo Debug', 'bancard-vpos'),
                'type' => 'checkbox',
                'label' => __('Habilitar logging detallado', 'bancard-vpos'),
                'description' => __('Guarda logs detallados para debugging. Solo usar en desarrollo.', 'bancard-vpos'),
                'default' => 'no',
                'desc_tip' => true
            )
        );
    }

    /**
     * Verificar si el gateway está disponible
     */
    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        
        if (empty($this->public_key) || empty($this->private_key)) {
            return false;
        }
        
        return true;
    }

    /**
     * Mostrar campos de pago en checkout
     */
    public function payment_fields() {
        if ($this->description) {
            echo '<div class="bancard-description">';
            echo wp_kses_post(wpautop(wptexturize($this->description)));
            echo '</div>';
        }
        
        echo '<div id="bancard-payment-form" class="bancard-payment-container">';
        echo '<p class="bancard-redirect-notice">';
        echo esc_html__('Serás redirigido al formulario seguro de Bancard para completar tu pago.', 'bancard-vpos');
        echo '</p>';
        echo '</div>';
        
        // Contenedor para iframe (se usará en página de agradecimiento)
        echo '<div id="bancard-iframe-container" style="display:none;"></div>';
    }

    /**
     * Procesar pago
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->log_event($order_id, '', 'error', 'Orden no encontrada');
            return array(
                'result' => 'failure',
                'messages' => __('Orden no encontrada', 'bancard-vpos')
            );
        }

        try {
            // Preparar datos de la operación
            $amount = $this->format_amount($order->get_total());
            $currency = $order->get_currency();
            
            // Generar token de seguridad
            $token = $this->generate_token($order_id, $amount, $currency);
            
            // Preparar operación
            $operation = array(
                'token' => $token,
                'shop_process_id' => (int)$order_id,
                'currency' => $currency,
                'amount' => $amount,
                'description' => sprintf(__('Orden #%s - %s', 'bancard-vpos'), $order->get_order_number(), get_bloginfo('name')),
                'return_url' => $this->get_return_url($order),
                'cancel_url' => wc_get_checkout_url(),
            );

            // Solo agregar additional_data si hay casos especiales (promociones, etc.)
            $additional_data = $this->get_additional_data($order);
            if ($additional_data !== null) {
                $operation['additional_data'] = $additional_data;
            }

            // Payload para API
            $payload = array(
                'public_key' => $this->public_key,
                'operation' => $operation
            );

            $this->log_event($order_id, '', 'request', 'Enviando solicitud a Bancard', $payload);

            // Enviar solicitud a Bancard
            $response = $this->send_api_request('single_buy', $payload);

            if (is_wp_error($response)) {
                $this->log_event($order_id, '', 'error', 'Error en API: ' . $response->get_error_message());
                wc_add_notice(__('Error de conexión con Bancard. Por favor intenta nuevamente.', 'bancard-vpos'), 'error');
                return array('result' => 'failure');
            }

            $data = json_decode($response, true);
            
            if (!$data) {
                $this->log_event($order_id, '', 'error', 'Respuesta inválida de API', array('response' => $response));
                wc_add_notice(__('Respuesta inválida del servidor de pagos.', 'bancard-vpos'), 'error');
                return array('result' => 'failure');
            }

            $this->log_event($order_id, '', 'response', 'Respuesta de Bancard', $data);

            // Verificar respuesta exitosa - ahora esperamos {"status": "success"}
            if (isset($data['status']) && $data['status'] === 'success') {
                // Si hay process_id, lo guardamos
                if (isset($data['process_id']) && !empty($data['process_id'])) {
                    $order->update_meta_data('_bancard_process_id', sanitize_text_field($data['process_id']));
                }
                
                $order->update_meta_data('_bancard_token', $token);
                $order->update_meta_data('_bancard_environment', $this->environment);
                
                // Cambiar estado de la orden
                $order->update_status('pending', __('Esperando pago de Bancard VPOS', 'bancard-vpos'));
                $order->save();

                $process_id = isset($data['process_id']) ? $data['process_id'] : 'N/A';
                $this->log_event($order_id, $process_id, 'success', 'Respuesta exitosa de Bancard');

                // Redirigir a página de recibo
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
            
            // Mantener compatibilidad con respuesta anterior (process_id)
            if (isset($data['process_id']) && !empty($data['process_id'])) {
                // Guardar process_id en la orden
                $order->update_meta_data('_bancard_process_id', sanitize_text_field($data['process_id']));
                $order->update_meta_data('_bancard_token', $token);
                $order->update_meta_data('_bancard_environment', $this->environment);
                
                // Cambiar estado de la orden
                $order->update_status('pending', __('Esperando pago de Bancard VPOS', 'bancard-vpos'));
                $order->save();

                $this->log_event($order_id, $data['process_id'], 'success', 'Process ID obtenido exitosamente');

                // Redirigir a página de recibo
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            // Manejar errores específicos de Bancard
            $error_message = $this->get_error_message($data);
            $this->log_event($order_id, '', 'error', 'Error de Bancard: ' . $error_message, $data);
            
            wc_add_notice($error_message, 'error');
            return array('result' => 'failure');

        } catch (Exception $e) {
            $this->log_event($order_id, '', 'exception', 'Excepción: ' . $e->getMessage());
            wc_add_notice(__('Error interno del servidor. Por favor intenta nuevamente.', 'bancard-vpos'), 'error');
            return array('result' => 'failure');
        }
    }

    /**
     * Página de recibo (donde se muestra el iframe)
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        $process_id = $order->get_meta('_bancard_process_id');
        
        if (!$process_id) {
            echo '<p>' . __('Error: No se pudo obtener el ID de proceso de Bancard.', 'bancard-vpos') . '</p>';
            return;
        }

        echo '<div class="bancard-receipt-container">';
        echo '<h3>' . __('Completar Pago', 'bancard-vpos') . '</h3>';
        echo '<p>' . __('Por favor completa tu pago en el formulario seguro de Bancard a continuación:', 'bancard-vpos') . '</p>';
        echo '<div id="bancard-iframe-container" data-process-id="' . esc_attr($process_id) . '"></div>';
        echo '</div>';

        // Script para cargar iframe
        $checkout_js_url = $this->api_endpoints[$this->environment]['checkout_js'];
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var processId = '<?php echo esc_js($process_id); ?>';
            var checkoutJsUrl = '<?php echo esc_js($checkout_js_url); ?>';
            
            // Cargar script de Bancard
            $.getScript(checkoutJsUrl)
                .done(function() {
                    if (typeof Bancard !== 'undefined' && Bancard.Checkout) {
                        Bancard.Checkout.createForm('bancard-iframe-container', processId, {
                            onReady: function() {
                                console.log('Bancard iframe ready');
                            },
                            onError: function(error) {
                                console.error('Bancard iframe error:', error);
                                alert('<?php echo esc_js(__('Error al cargar el formulario de pago', 'bancard-vpos')); ?>');
                            }
                        });
                    }
                })
                .fail(function() {
                    alert('<?php echo esc_js(__('Error al cargar el script de Bancard', 'bancard-vpos')); ?>');
                });
        });
        </script>
        <?php
    }

    /**
     * Página de agradecimiento
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        $process_id = $order->get_meta('_bancard_process_id');
        
        if ($process_id && $order->get_status() === 'pending') {
            echo '<div class="woocommerce-info">';
            echo __('Tu pago está siendo procesado. Te notificaremos cuando se complete.', 'bancard-vpos');
            echo '</div>';
        }
    }

    /**
     * Procesar webhook de confirmación
     */
    public function process_webhook() {
        $raw_body = file_get_contents('php://input');
        $data = json_decode($raw_body, true);
        
        if (!$data) {
            $this->log_event(0, '', 'webhook_error', 'Webhook con datos inválidos', array('raw_body' => $raw_body));
            http_response_code(400);
            exit('Invalid data');
        }

        $this->log_event(0, '', 'webhook_received', 'Webhook recibido', $data);

        // Manejar diferentes formatos de webhook
        $order_id = null;
        $operation = null;

        // Formato nuevo: {"operation": {...}}
        if (isset($data['operation'])) {
            $operation = $data['operation'];
            $order_id = isset($operation['shop_process_id']) ? intval($operation['shop_process_id']) : null;
        }
        // Formato alternativo: datos directos
        elseif (isset($data['shop_process_id'])) {
            $order_id = intval($data['shop_process_id']);
            $operation = $data;
        }

        if (!$order_id || !$operation) {
            $this->log_event(0, '', 'webhook_error', 'Formato de webhook no reconocido', $data);
            http_response_code(400);
            exit('Invalid webhook format');
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            $this->log_event($order_id, '', 'webhook_error', 'Orden no encontrada');
            http_response_code(404);
            exit('Order not found');
        }

        // Verificar que la orden pertenece a este gateway
        if ($order->get_payment_method() !== $this->id) {
            $this->log_event($order_id, '', 'webhook_error', 'Orden no pertenece a Bancard VPOS');
            http_response_code(400);
            exit('Invalid payment method');
        }

        // Verificar token de seguridad
        if (!$this->verify_webhook_token($operation, $order)) {
            $this->log_event($order_id, '', 'webhook_error', 'Token de webhook inválido');
            http_response_code(403);
            exit('Invalid token');
        }

        $status = isset($operation['response_code']) ? $operation['response_code'] : '';
        $response = isset($operation['response']) ? $operation['response'] : '';

        // Manejar diferentes códigos de respuesta
        if ($status === '00' || $response === 'S') {
            $this->process_successful_payment($order, $data);
        } elseif ($status === '01') {
            $this->process_pending_payment($order, $data);
        } else {
            $this->process_failed_payment($order, $data);
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo wp_json_encode(array('status' => 'success'));
        exit;
    }

    /**
     * Procesar pago exitoso
     */
    private function process_successful_payment($order, $data) {
        $operation = isset($data['operation']) ? $data['operation'] : $data;
        
        // Obtener diferentes campos de transacción según el formato
        $transaction_id = '';
        if (isset($operation['ticket_number'])) {
            $transaction_id = $operation['ticket_number'];
        } elseif (isset($operation['ticket'])) {
            $transaction_id = $operation['ticket'];
        }
        
        $authorization_number = isset($operation['authorization_number']) ? $operation['authorization_number'] : '';
        
        // Actualizar orden
        if ($transaction_id) {
            $order->update_meta_data('_bancard_transaction_id', $transaction_id);
        }
        if ($authorization_number) {
            $order->update_meta_data('_bancard_authorization_number', $authorization_number);
        }
        $order->update_meta_data('_bancard_response', maybe_serialize($data));
        
        // Completar pago solo si no está ya completado
        if (!$order->is_paid()) {
            $order->payment_complete($transaction_id);
            
            $note = __('Pago completado exitosamente via Bancard VPOS.', 'bancard-vpos');
            if ($transaction_id) {
                $note .= sprintf(' Transaction ID: %s', $transaction_id);
            }
            if ($authorization_number) {
                $note .= sprintf(' Authorization: %s', $authorization_number);
            }
            
            $order->add_order_note($note);
        }
        
        $process_id = isset($operation['process_id']) ? $operation['process_id'] : 'N/A';
        $this->log_event($order->get_id(), $process_id, 'payment_success', 'Pago completado exitosamente', $operation);
    }

    /**
     * Procesar pago pendiente
     */
    private function process_pending_payment($order, $data) {
        $order->update_status('on-hold', __('Pago pendiente en Bancard VPOS', 'bancard-vpos'));
        $this->log_event($order->get_id(), $data['operation']['process_id'], 'payment_pending', 'Pago pendiente');
    }

    /**
     * Procesar pago fallido
     */
    private function process_failed_payment($order, $data) {
        $operation = $data['operation'];
        $error_message = isset($operation['response_description']) ? $operation['response_description'] : __('Pago rechazado', 'bancard-vpos');
        
        $order->update_status('failed', sprintf(__('Pago fallido: %s', 'bancard-vpos'), $error_message));
        $this->log_event($order->get_id(), $data['operation']['process_id'], 'payment_failed', 'Pago fallido: ' . $error_message);
    }

    /**
     * AJAX para crear pago
     */
    public function ajax_create_payment() {
        check_ajax_referer('bancard_vpos_nonce', 'nonce');
        
        if (!isset($_POST['order_id'])) {
            wp_send_json_error(__('ID de orden requerido', 'bancard-vpos'));
        }

        $order_id = intval($_POST['order_id']);
        $result = $this->process_payment($order_id);
        
        wp_send_json($result);
    }

    /**
     * Generar token de seguridad
     */
    private function generate_token($order_id, $amount, $currency) {
        return md5($this->private_key . $order_id . $amount . $currency);
    }

    /**
     * Verificar token de webhook
     */
    private function verify_webhook_token($operation, $order) {
        if (!isset($operation['token'])) {
            return false;
        }

        $shop_process_id = isset($operation['shop_process_id']) ? (string)$operation['shop_process_id'] : '';
        $amount          = isset($operation['amount']) ? (string)$operation['amount'] : '';
        $currency        = isset($operation['currency']) ? (string)$operation['currency'] : '';
        $response        = isset($operation['response']) ? (string)$operation['response'] : '';
        $response_code   = isset($operation['response_code']) ? (string)$operation['response_code'] : '';
        $received_token  = (string)$operation['token'];

        // Normalizar amount a 2 decimales con punto
        if ($amount !== '') {
            $amount = number_format((float)$amount, 2, '.', '');
        }

        // Candidatos conocidos de fórmula de token según documentación de Bancard
        $candidates = array();
        // 1) Fórmula clásica: private_key + shop_process_id + amount + currency
        $candidates[] = md5($this->private_key . $shop_process_id . $amount . $currency);
        // 1.1) Confirmación oficial (Buy Single Confirm): private_key + shop_process_id + 'confirm' + amount + currency
        $candidates[] = md5($this->private_key . $shop_process_id . 'confirm' . $amount . $currency);
        // 2) Confirmación con response al final
        if ($response !== '') {
            $candidates[] = md5($this->private_key . $shop_process_id . $amount . $currency . $response);
            // Variante con response al inicio (compatibilidad)
            $candidates[] = md5($this->private_key . $response . $shop_process_id . $amount . $currency);
        }
        // 3) Confirmación con response_code al final
        if ($response_code !== '') {
            $candidates[] = md5($this->private_key . $shop_process_id . $amount . $currency . $response_code);
            // Variante con response_code al inicio (compatibilidad)
            $candidates[] = md5($this->private_key . $response_code . $shop_process_id . $amount . $currency);
        }

        foreach ($candidates as $expected_token) {
            if (hash_equals($expected_token, $received_token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formatear monto
     */
    private function format_amount($amount) {
        return number_format(floatval($amount), 2, '.', '');
    }

    /**
     * Obtener additional_data solo si es necesario (promociones, etc.)
     * Según documentación de Bancard, este campo es opcional y solo para casos especiales
     */
    private function get_additional_data($order) {
        // Solo agregar additional_data si hay promociones o casos especiales
        $additional_data = array();
        
        // Ejemplo: Si hay un cupón de descuento, podríamos incluirlo
        $coupons = $order->get_coupon_codes();
        if (!empty($coupons)) {
            $additional_data['promotion_code'] = implode(',', $coupons);
        }
        
        // Solo retornar si hay datos especiales, sino null
        return !empty($additional_data) ? json_encode($additional_data) : null;
    }

    /**
     * Obtener URL del webhook
     */
    private function get_webhook_url() {
        return add_query_arg('wc-api', 'bancard_vpos', home_url('/'));
    }

    /**
     * Enviar solicitud a API
     */
    private function send_api_request($endpoint, $data) {
        $url = $this->api_endpoints[$this->environment][$endpoint];
        
        $args = array(
            'body' => wp_json_encode($data),
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'WooCommerce-BancardVPOS/' . BANCARD_VPOS_VERSION
            ),
            'timeout' => 30,
            'sslverify' => $this->environment === 'production'
        );

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Registrar código de respuesta para debugging
        if (function_exists('bancard_vpos_log')) {
            bancard_vpos_log(0, '', 'api_response_code', 'Código de respuesta HTTP: ' . $code, array('body' => $body));
        }

        if ($code !== 200) {
            return new WP_Error('http_error', sprintf('HTTP %d: %s', $code, $body));
        }

        return $body;
    }

    /**
     * Obtener mensaje de error
     */
    private function get_error_message($data) {
        // Manejar diferentes formatos de error de Bancard
        if (isset($data['messages']) && is_array($data['messages']) && !empty($data['messages'])) {
            return implode('. ', $data['messages']);
        }
        
        if (isset($data['message'])) {
            return $data['message'];
        }
        
        // Manejar errores específicos de la API
        if (isset($data['status']) && $data['status'] !== 'success') {
            if (isset($data['description'])) {
                return $data['description'];
            }
            return sprintf(__('Error en la respuesta: %s', 'bancard-vpos'), $data['status']);
        }
        
        // Si no hay process_id ni status success, es un error
        if (!isset($data['process_id']) && !isset($data['status'])) {
            return __('Respuesta inválida del servidor de pagos', 'bancard-vpos');
        }
        
        return __('Error desconocido en el procesamiento del pago', 'bancard-vpos');
    }

    /**
     * Log de eventos
     */
    private function log_event($order_id, $process_id, $event_type, $message, $data = array()) {
        if (function_exists('bancard_vpos_log')) {
            bancard_vpos_log($order_id, $process_id, $event_type, $message, $data);
        }
        
        if ($this->debug === 'yes' && function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $context = array('source' => 'bancard-vpos');
            $logger->info(sprintf('[Order: %d] [Process: %s] %s', $order_id, $process_id, $message), $context);
        }
    }

    /**
     * Procesar pago con contexto de Store API (para Blocks)
     */
    public function process_payment_with_context($context, &$result) {
        if ($context->payment_method !== $this->id) {
            return;
        }

        try {
            $order = $context->order;
            $payment_data = $context->payment_data;

            // Procesar el pago usando la misma lógica que el método tradicional
            $payment_result = $this->process_payment($order->get_id());

            if ($payment_result['result'] === 'success') {
                $result->set_status('success');
                
                if (isset($payment_result['redirect'])) {
                    $result->set_redirect_url($payment_result['redirect']);
                }
                
                // Agregar datos adicionales si es necesario
                $result->set_payment_details(array(
                    'bancard_process_id' => $order->get_meta('_bancard_process_id'),
                    'environment' => $this->environment
                ));
            } else {
                $result->set_status('failure');
                
                if (isset($payment_result['messages'])) {
                    throw new Exception($payment_result['messages']);
                }
            }

        } catch (Exception $e) {
            $this->log_event($context->order->get_id(), '', 'blocks_error', 'Error en procesamiento con bloques: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Procesar reembolso
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Orden inválida', 'bancard-vpos'));
        }

        $process_id = $order->get_meta('_bancard_process_id');
        $transaction_id = $order->get_meta('_bancard_transaction_id');
        
        if (!$process_id || !$transaction_id) {
            return new WP_Error('missing_transaction', __('Información de transacción no encontrada', 'bancard-vpos'));
        }

        // Implementar lógica de reembolso según API de Bancard
        // Por ahora retornamos false para indicar que debe hacerse manualmente
        return false;
    }
}