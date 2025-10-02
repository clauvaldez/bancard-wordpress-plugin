<?php
/*
 * Script de diagnóstico para Bancard VPOS Gateway
 * Coloca este archivo temporalmente en la carpeta del plugin y accédelo via web
 * Ejemplo: http://tudominio.com/wp-content/plugins/bancard-vpos/bancard-debug.php
 * 
 * IMPORTANTE: Elimina este archivo después de usarlo por seguridad
 */

// Cargar WordPress
$wp_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (file_exists($wp_path)) {
    require_once $wp_path;
} else {
    die('No se pudo encontrar WordPress');
}

// Solo permitir a administradores
if (!current_user_can('administrator')) {
    die('Acceso denegado - Solo administradores');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico Bancard VPOS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { background: white; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
        .success { color: #0073aa; } .error { color: #d63638; } .warning { color: #dba617; }
        .code { background: #f6f7f7; padding: 10px; border-radius: 3px; font-family: monospace; margin: 10px 0; }
        h2 { border-bottom: 2px solid #0073aa; padding-bottom: 5px; }
        .fix-button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; margin: 5px; }
        .fix-button:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Diagnóstico Bancard VPOS Gateway</h1>
        
        <?php
        
        echo "<h2>1. Verificación de WordPress y WooCommerce</h2>";
        
        // Check WordPress
        echo "<p><strong>WordPress:</strong> ";
        echo "<span class='success'>✅ Versión " . get_bloginfo('version') . "</span></p>";
        
        // Check WooCommerce
        if (class_exists('WooCommerce')) {
            echo "<p><strong>WooCommerce:</strong> ";
            echo "<span class='success'>✅ Versión " . WC()->version . "</span></p>";
        } else {
            echo "<p><strong>WooCommerce:</strong> ";
            echo "<span class='error'>❌ No instalado o no activo</span></p>";
        }
        
        echo "<h2>2. Estado del Plugin</h2>";
        
        // Check plugin activo
        $active_plugins = get_option('active_plugins');
        $plugin_active = false;
        foreach($active_plugins as $plugin) {
            if (strpos($plugin, 'bancard-vpos') !== false) {
                $plugin_active = true;
                echo "<p><strong>Plugin activo:</strong> <span class='success'>✅ {$plugin}</span></p>";
                break;
            }
        }
        
        if (!$plugin_active) {
            echo "<p><strong>Plugin activo:</strong> <span class='error'>❌ No encontrado en plugins activos</span></p>";
        }
        
        // Check archivos del plugin
        $plugin_dir = dirname(__FILE__);
        $required_files = [
            'bancard-vpos.php' => 'Archivo principal',
            'bancard-gateway-classes.php' => 'Clases del gateway',
            'assets/bancard.js' => 'JavaScript frontend',
            'assets/bancard.css' => 'Estilos CSS'
        ];
        
        echo "<h3>Archivos requeridos:</h3>";
        foreach($required_files as $file => $desc) {
            $path = $plugin_dir . '/' . $file;
            if (file_exists($path)) {
                echo "<p><strong>{$desc}:</strong> <span class='success'>✅ {$file}</span></p>";
            } else {
                echo "<p><strong>{$desc}:</strong> <span class='error'>❌ {$file} no encontrado</span></p>";
            }
        }
        
        echo "<h2>3. Verificación del Gateway</h2>";
        
        // Check si la clase existe
        if (class_exists('WC_Bancard_VPOS_Gateway')) {
            echo "<p><strong>Clase Gateway:</strong> <span class='success'>✅ WC_Bancard_VPOS_Gateway cargada</span></p>";
            
            // Verificar si está registrado
            $gateways = WC()->payment_gateways()->payment_gateways;
            if (isset($gateways['bancard_vpos'])) {
                echo "<p><strong>Gateway registrado:</strong> <span class='success'>✅ Bancard VPOS</span></p>";
                
                $gateway = $gateways['bancard_vpos'];
                echo "<p><strong>Estado:</strong> " . ($gateway->enabled === 'yes' ? "<span class='success'>✅ Habilitado</span>" : "<span class='warning'>⚠️ Deshabilitado</span>") . "</p>";
                echo "<p><strong>Título:</strong> " . esc_html($gateway->title) . "</p>";
                echo "<p><strong>Has Fields:</strong> " . ($gateway->has_fields ? "<span class='success'>✅ true</span>" : "<span class='error'>❌ false</span>") . "</p>";
                echo "<p><strong>Supports:</strong> " . implode(', ', $gateway->supports) . "</p>";
                
                // Check configuración
                echo "<h3>Configuración:</h3>";
                echo "<p><strong>Public Key:</strong> " . (!empty($gateway->public_key) ? "<span class='success'>✅ Configurado (" . substr($gateway->public_key, 0, 10) . "...)</span>" : "<span class='error'>❌ Vacío</span>") . "</p>";
                echo "<p><strong>Private Key:</strong> " . (!empty($gateway->private_key) ? "<span class='success'>✅ Configurado</span>" : "<span class='error'>❌ Vacío</span>") . "</p>";
                echo "<p><strong>Entorno:</strong> " . esc_html($gateway->environment) . "</p>";
                echo "<p><strong>Debug:</strong> " . ($gateway->debug === 'yes' ? "<span class='warning'>⚠️ Activo</span>" : "<span class='success'>✅ Inactivo</span>") . "</p>";
                
            } else {
                echo "<p><strong>Gateway registrado:</strong> <span class='error'>❌ No encontrado</span></p>";
            }
        } else {
            echo "<p><strong>Clase Gateway:</strong> <span class='error'>❌ WC_Bancard_VPOS_Gateway no encontrada</span></p>";
        }
        
        echo "<h2>4. Verificación de Checkout</h2>";
        
        // Verificar gateways disponibles
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        if (isset($available_gateways['bancard_vpos'])) {
            echo "<p><strong>Disponible en checkout clásico:</strong> <span class='success'>✅ Sí</span></p>";
        } else {
            echo "<p><strong>Disponible en checkout clásico:</strong> <span class='error'>❌ No</span></p>";
            
            // Diagnosticar por qué no está disponible
            if (class_exists('WC_Bancard_VPOS_Gateway')) {
                $gateway = new WC_Bancard_VPOS_Gateway();
                $is_available = $gateway->is_available();
                echo "<p><strong>Razón:</strong> ";
                if (!$is_available) {
                    if ($gateway->enabled !== 'yes') {
                        echo "<span class='warning'>Gateway deshabilitado</span>";
                    } elseif (empty($gateway->public_key) || empty($gateway->private_key)) {
                        echo "<span class='error'>Claves no configuradas</span>";
                    } else {
                        echo "<span class='error'>Otro problema de configuración</span>";
                    }
                } else {
                    echo "<span class='warning'>Disponible pero no aparece</span>";
                }
                echo "</p>";
            }
        }
        
        // Verificar integración con bloques
        echo "<h3>Integración con Checkout Blocks:</h3>";
        if (class_exists('WC_Bancard_VPOS_Blocks_Integration')) {
            echo "<p><strong>Clase de integración:</strong> <span class='success'>✅ WC_Bancard_VPOS_Blocks_Integration cargada</span></p>";
            
            // Verificar si está registrada
            if (has_action('woocommerce_blocks_payment_method_type_registration')) {
                echo "<p><strong>Hook registrado:</strong> <span class='success'>✅ woocommerce_blocks_payment_method_type_registration</span></p>";
            } else {
                echo "<p><strong>Hook registrado:</strong> <span class='error'>❌ Hook no encontrado</span></p>";
            }
            
            // Verificar archivos de bloques
            $blocks_js = plugin_dir_path(__FILE__) . 'assets/bancard-blocks.js';
            $blocks_asset = plugin_dir_path(__FILE__) . 'assets/bancard-blocks.asset.php';
            
            echo "<p><strong>Script de bloques:</strong> " . (file_exists($blocks_js) ? "<span class='success'>✅ bancard-blocks.js</span>" : "<span class='error'>❌ bancard-blocks.js no encontrado</span>") . "</p>";
            echo "<p><strong>Asset de bloques:</strong> " . (file_exists($blocks_asset) ? "<span class='success'>✅ bancard-blocks.asset.php</span>" : "<span class='error'>❌ bancard-blocks.asset.php no encontrado</span>") . "</p>";
            
        } else {
            echo "<p><strong>Clase de integración:</strong> <span class='error'>❌ WC_Bancard_VPOS_Blocks_Integration no encontrada</span></p>";
        }
        
        // Verificar compatibilidad declarada
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            echo "<p><strong>FeaturesUtil disponible:</strong> <span class='success'>✅ Sí</span></p>";
        } else {
            echo "<p><strong>FeaturesUtil disponible:</strong> <span class='warning'>⚠️ No (versión antigua de WooCommerce)</span></p>";
        }
        
        echo "<h2>5. Errores de WordPress</h2>";
        
        // Mostrar errores recientes del log de WordPress
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo "<p><strong>WP_DEBUG:</strong> <span class='success'>✅ Activo</span></p>";
        } else {
            echo "<p><strong>WP_DEBUG:</strong> <span class='warning'>⚠️ Inactivo (recomendado activar temporalmente)</span></p>";
        }
        
        echo "<h2>6. Acciones de Reparación</h2>";
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'reactivate_plugin':
                    deactivate_plugins('bancard-vpos/bancard-vpos.php');
                    activate_plugin('bancard-vpos/bancard-vpos.php');
                    echo "<div class='code'>Plugin reactivado</div>";
                    break;
                    
                case 'clear_cache':
                    // Limpiar cache de transients
                    delete_transient('woocommerce_shipping_cache');
                    wp_cache_flush();
                    echo "<div class='code'>Cache limpiado</div>";
                    break;
                    
                case 'enable_gateway':
                    $settings = get_option('woocommerce_bancard_vpos_settings', array());
                    $settings['enabled'] = 'yes';
                    update_option('woocommerce_bancard_vpos_settings', $settings);
                    echo "<div class='code'>Gateway habilitado</div>";
                    break;
                    
                case 'fix_has_fields':
                    // Esta es una corrección a nivel de base de datos si es necesario
                    echo "<div class='code'>Verificar que has_fields = true en el código</div>";
                    break;
            }
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        }
        ?>
        
        <form method="post" style="margin-top: 20px;">
            <button type="submit" name="action" value="reactivate_plugin" class="fix-button">🔄 Reactivar Plugin</button>
            <button type="submit" name="action" value="clear_cache" class="fix-button">🗑️ Limpiar Cache</button>
            <button type="submit" name="action" value="enable_gateway" class="fix-button">✅ Habilitar Gateway</button>
        </form>
        
        <h2>7. Código de Prueba</h2>
        <p>Si el gateway sigue sin aparecer, agrega temporalmente este código al final del archivo <code>functions.php</code> de tu tema:</p>
        
        <div class="code">
add_action('wp_footer', function() {
    if (is_admin() && current_user_can('administrator')) {
        echo '&lt;script&gt;console.log("Gateways disponibles:", ' . json_encode(array_keys(WC()->payment_gateways()->get_available_payment_gateways())) . ');&lt;/script&gt;';
    }
});
        </div>
        
        <h2>8. Enlaces Útiles</h2>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout'); ?>">Configurar Métodos de Pago</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>">Ver Logs de WooCommerce</a></li>
            <li><a href="<?php echo admin_url('plugins.php'); ?>">Administrar Plugins</a></li>
        </ul>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <strong>⚠️ IMPORTANTE:</strong> Elimina este archivo (bancard-debug.php) después de usarlo por seguridad.
        </div>
    </div>
</body>
</html>