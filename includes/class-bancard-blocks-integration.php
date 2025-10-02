<?php
/**
 * Bancard VPOS Blocks Integration
 * Integración con los bloques de checkout de WooCommerce
 */

if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Verificar que la clase padre existe
if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Bancard VPOS payment method integration for WooCommerce Blocks
 */
final class WC_Bancard_VPOS_Blocks_Integration extends AbstractPaymentMethodType {

    /**
     * The gateway instance.
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     */
    protected $name = 'bancard_vpos';

    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_bancard_vpos_settings', []);
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : false;
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     */
    public function is_active() {
        return $this->gateway ? $this->gateway->is_available() : false;
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles() {
        $script_path = '/assets/bancard-blocks.js';
        $script_asset_path = BANCARD_VPOS_PATH . 'assets/bancard-blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(),
                'version'      => BANCARD_VPOS_VERSION,
            );
        $script_url = BANCARD_VPOS_URL . 'assets/bancard-blocks.js';

        wp_register_script(
            'wc-bancard-vpos-blocks-integration',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-bancard-vpos-blocks-integration', 'bancard-vpos', BANCARD_VPOS_PATH . 'languages');
        }

        return ['wc-bancard-vpos-blocks-integration'];
    }

    /**
     * Returns an array of script handles to enqueue in the admin context.
     */
    public function get_payment_method_script_handles_for_admin() {
        return $this->get_payment_method_script_handles();
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'icon'        => $this->gateway->icon,
            'environment' => $this->get_setting('environment', 'staging'),
            'public_key'  => $this->get_setting('public_key'),
            'debug'       => $this->get_setting('debug', 'no') === 'yes',
            'endpoints'   => [
                'production' => [
                    'checkout_js' => 'https://vpos.infonet.com.py/checkout/javascript/dist/bancard-checkout-4.0.0.js'
                ],
                'staging' => [
                    'checkout_js' => 'https://vpos.infonet.com.py:8888/checkout/javascript/dist/bancard-checkout-4.0.0.js'
                ]
            ],
            'messages' => [
                'processing' => __('Procesando pago...', 'bancard-vpos'),
                'redirecting' => __('Redirigiendo a Bancard...', 'bancard-vpos'),
                'error' => __('Error en el procesamiento del pago', 'bancard-vpos'),
                'complete_payment' => __('Completa tu pago en el formulario seguro de Bancard', 'bancard-vpos'),
                'secure_payment' => __('Pago seguro con Bancard VPOS', 'bancard-vpos'),
            ]
        ];
    }

    /**
     * Get setting from the gateway.
     */
    protected function get_setting($key, $default = '') {
        return $this->gateway ? $this->gateway->get_option($key, $default) : $default;
    }
}