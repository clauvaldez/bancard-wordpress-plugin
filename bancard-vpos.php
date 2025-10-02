<?php
/*
Plugin Name: Bancard VPOS Gateway
Description: Pasarela de pagos Bancard VPOS para WooCommerce - Compatible con WordPress 6.7+ y WooCommerce 9.0+
Version: 2.0.0
Author: Cv - 2025
Text Domain: bancard-vpos
Domain Path: /languages
Requires at least: 6.0
Tested up to: 6.7
WC requires at least: 8.0
WC tested up to: 9.0
Requires PHP: 7.4
Network: false
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Seguridad - Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Constantes del plugin
define('BANCARD_VPOS_VERSION', '2.0.0');
define('BANCARD_VPOS_PATH', plugin_dir_path(__FILE__));
define('BANCARD_VPOS_URL', plugin_dir_url(__FILE__));
define('BANCARD_VPOS_BASENAME', plugin_basename(__FILE__));
define('BANCARD_VPOS_SLUG', 'bancard-vpos');

// Verificar requisitos mínimos
register_activation_hook(__FILE__, 'bancard_vpos_check_requirements');

function bancard_vpos_check_requirements() {
    // Verificar PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(BANCARD_VPOS_BASENAME);
        wp_die(__('Bancard VPOS requiere PHP 7.4 o superior. Tu versión actual es: ' . PHP_VERSION, 'bancard-vpos'));
    }
    
    // Verificar WordPress
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(BANCARD_VPOS_BASENAME);
        wp_die(__('Bancard VPOS requiere WordPress 6.0 o superior.', 'bancard-vpos'));
    }
    
    // Verificar WooCommerce
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(BANCARD_VPOS_BASENAME);
        wp_die(__('Bancard VPOS requiere WooCommerce para funcionar.', 'bancard-vpos'));
    }
    
    // Verificar versión de WooCommerce
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
        deactivate_plugins(BANCARD_VPOS_BASENAME);
        wp_die(__('Bancard VPOS requiere WooCommerce 8.0 o superior. Tu versión actual es: ' . WC_VERSION, 'bancard-vpos'));
    }
}

// Inicializar plugin cuando WordPress esté listo
add_action('plugins_loaded', 'bancard_vpos_init', 11);

function bancard_vpos_init() {
    // Verificar WooCommerce nuevamente
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'bancard_vpos_wc_missing_notice');
        return;
    }
    
    // Declarar compatibilidad con HPOS (High-Performance Order Storage)
    add_action('before_woocommerce_init', 'bancard_vpos_declare_hpos_compatibility');
    
    // Declarar compatibilidad con Cart/Checkout Blocks
    add_action('before_woocommerce_init', 'bancard_vpos_declare_cart_checkout_blocks_compatibility');
    
    // Cargar la clase del gateway
    if (!class_exists('WC_Bancard_VPOS_Gateway')) {
        require_once BANCARD_VPOS_PATH . 'bancard-gateway-classes.php';
    }
    
    // Cargar integración con bloques (solo si la clase padre existe)
    if (!class_exists('WC_Bancard_VPOS_Blocks_Integration') && 
        class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        require_once BANCARD_VPOS_PATH . 'includes/class-bancard-blocks-integration.php';
    }
    
    // Registrar el gateway
    add_filter('woocommerce_payment_gateways', 'bancard_vpos_add_gateway');
    
    // Registrar integración con bloques
    add_action('woocommerce_blocks_payment_method_type_registration', 'bancard_vpos_register_blocks_integration');
    
    // Cargar textdomain para traducciones
    add_action('init', 'bancard_vpos_load_textdomain');
    
    // Cargar scripts y estilos
    add_action('wp_enqueue_scripts', 'bancard_vpos_enqueue_scripts');
    add_action('admin_enqueue_scripts', 'bancard_vpos_admin_enqueue_scripts');
    
    // Agregar enlaces en la página de plugins
    add_filter('plugin_action_links_' . BANCARD_VPOS_BASENAME, 'bancard_vpos_plugin_action_links');
    
    // Hook para procesar webhooks/callbacks
    add_action('woocommerce_api_bancard_vpos', 'bancard_vpos_process_webhook');
    
    // Agregar meta box en órdenes para información de Bancard
    add_action('add_meta_boxes', 'bancard_vpos_add_order_meta_box');
}

// Declarar compatibilidad HPOS
function bancard_vpos_declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}

// Declarar compatibilidad con Cart/Checkout Blocks
function bancard_vpos_declare_cart_checkout_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

// Mostrar aviso si WooCommerce no está activo
function bancard_vpos_wc_missing_notice() {
    $message = sprintf(
        /* translators: %s: WooCommerce link */
        __('Bancard VPOS requiere %s para funcionar.', 'bancard-vpos'),
        '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
    );
    
    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Bancard VPOS:', 'bancard-vpos') . '</strong> ' . wp_kses_post($message) . '</p></div>';
}

// Cargar textdomain para traducciones
function bancard_vpos_load_textdomain() {
    load_plugin_textdomain('bancard-vpos', false, dirname(BANCARD_VPOS_BASENAME) . '/languages');
}

// Agregar gateway a WooCommerce
function bancard_vpos_add_gateway($gateways) {
    $gateways[] = 'WC_Bancard_VPOS_Gateway';
    return $gateways;
}

// Registrar integración con bloques de checkout
function bancard_vpos_register_blocks_integration($payment_method_registry) {
    // Solo registrar si la clase existe
    if (class_exists('WC_Bancard_VPOS_Blocks_Integration')) {
        $payment_method_registry->register(new WC_Bancard_VPOS_Blocks_Integration());
    }
}

// Cargar scripts en frontend
function bancard_vpos_enqueue_scripts() {
    if (!is_checkout() && !is_order_received_page()) {
        return;
    }
    
    // Script principal
    wp_enqueue_script(
        'bancard-vpos-js',
        BANCARD_VPOS_URL . 'assets/bancard.js',
        array('jquery', 'wc-checkout'),
        BANCARD_VPOS_VERSION,
        true
    );
    
    // Estilos
    wp_enqueue_style(
        'bancard-vpos-css',
        BANCARD_VPOS_URL . 'assets/bancard.css',
        array(),
        BANCARD_VPOS_VERSION
    );
    
    // Localizar script con datos necesarios
    $gateway_settings = get_option('woocommerce_bancard_vpos_settings', array());
    $localize_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'environment' => isset($gateway_settings['environment']) ? $gateway_settings['environment'] : 'staging',
        'nonce' => wp_create_nonce('bancard_vpos_nonce'),
        'checkout_url' => wc_get_checkout_url(),
        'messages' => array(
            'processing' => __('Procesando pago...', 'bancard-vpos'),
            'redirecting' => __('Redirigiendo a Bancard...', 'bancard-vpos'),
            'error' => __('Error en el procesamiento del pago', 'bancard-vpos'),
            'select_card' => __('Por favor selecciona una tarjeta', 'bancard-vpos'),
        ),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    );
    
    wp_localize_script('bancard-vpos-js', 'BancardVPOS', $localize_data);
}

// Cargar scripts en admin
function bancard_vpos_admin_enqueue_scripts($hook) {
    // Solo cargar en páginas de configuración de WooCommerce
    if (strpos($hook, 'woocommerce') === false) {
        return;
    }
    
    wp_enqueue_style(
        'bancard-vpos-admin-css',
        BANCARD_VPOS_URL . 'assets/bancard.css',
        array(),
        BANCARD_VPOS_VERSION
    );
}

// Agregar enlaces de configuración en página de plugins
function bancard_vpos_plugin_action_links($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=wc-settings&tab=checkout&section=bancard_vpos'),
        __('Configuración', 'bancard-vpos')
    );
    
    array_unshift($links, $settings_link);
    return $links;
}

// Procesar webhooks de Bancard
function bancard_vpos_process_webhook() {
    // Aceptar POST directo desde Bancard sin parámetros adicionales
    $gateway = new WC_Bancard_VPOS_Gateway();
    $gateway->process_webhook();
}

// Agregar meta box en órdenes
function bancard_vpos_add_order_meta_box() {
    $screen = wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';
        
    add_meta_box(
        'bancard-vpos-order-data',
        __('Información de Bancard VPOS', 'bancard-vpos'),
        'bancard_vpos_order_meta_box_content',
        $screen,
        'side',
        'default'
    );
}

// Contenido del meta box
function bancard_vpos_order_meta_box_content($post_or_order_object) {
    $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
    
    if (!$order || $order->get_payment_method() !== 'bancard_vpos') {
        echo '<p>' . __('Esta orden no fue procesada con Bancard VPOS.', 'bancard-vpos') . '</p>';
        return;
    }
    
    $process_id = $order->get_meta('_bancard_process_id');
    $transaction_id = $order->get_meta('_bancard_transaction_id');
    
    echo '<table class="widefat">';
    if ($process_id) {
        echo '<tr><td><strong>' . __('Process ID:', 'bancard-vpos') . '</strong></td><td>' . esc_html($process_id) . '</td></tr>';
    }
    if ($transaction_id) {
        echo '<tr><td><strong>' . __('Transaction ID:', 'bancard-vpos') . '</strong></td><td>' . esc_html($transaction_id) . '</td></tr>';
    }
    echo '</table>';
}

// Activación del plugin
register_activation_hook(__FILE__, 'bancard_vpos_activate');

function bancard_vpos_activate() {
    // Verificar requisitos
    bancard_vpos_check_requirements();
    
    // Configuración por defecto
    $default_settings = array(
        'enabled' => 'yes',
        'title' => __('Tarjeta de Crédito/Débito', 'bancard-vpos'),
        'description' => __('Pagar de forma segura con tu tarjeta a través de Bancard VPOS', 'bancard-vpos'),
        'public_key' => '',
        'private_key' => '',
        'environment' => 'staging',
        'debug' => 'no'
    );
    
    // Solo establecer configuración por defecto si no existe
    if (!get_option('woocommerce_bancard_vpos_settings')) {
        update_option('woocommerce_bancard_vpos_settings', $default_settings);
    }
    
    // Crear tabla para logs si no existe
    bancard_vpos_create_log_table();
    
    // Limpiar cache de rewrite rules
    flush_rewrite_rules();
}

// Crear tabla para logs
function bancard_vpos_create_log_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bancard_vpos_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        process_id varchar(255) DEFAULT '',
        event_type varchar(50) NOT NULL,
        message text NOT NULL,
        data longtext DEFAULT '',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY process_id (process_id),
        KEY event_type (event_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Desactivación del plugin
register_deactivation_hook(__FILE__, 'bancard_vpos_deactivate');

function bancard_vpos_deactivate() {
    // Limpiar cache
    flush_rewrite_rules();
}

// Función de logging
function bancard_vpos_log($order_id, $process_id, $event_type, $message, $data = array()) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'bancard_vpos_logs';
    
    $wpdb->insert(
        $table_name,
        array(
            'order_id' => $order_id,
            'process_id' => $process_id,
            'event_type' => $event_type,
            'message' => $message,
            'data' => maybe_serialize($data),
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );
}