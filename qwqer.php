<?php
/*
 * Plugin Name: QWQER Shipping for Woocommerce
 * Plugin URI: https://qwqer.com/
 * Description: Express Delivery for Woocommerce
 * Version: 1.1
 * Author: Notfarqwqer
 * Author URI: https://www.linkedin.com/in/notfarweb/
 * License: GPLv3
 */

defined('ABSPATH') || exit;

/*
 * Languages
 */
add_action( 'plugins_loaded', 'qwqer_textdomain' );

function qwqer_textdomain() {
    load_plugin_textdomain( 'qwqer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/*
 * Check if the Woocommerce plugin is activated
 */
function qwqerActivation()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        /*
         * If the Woocommerce plugin is not active, then deactivate this plugin
         */
        deactivate_plugins(plugin_basename(__FILE__));
    }
}

add_action('admin_init', 'qwqerActivation');

/*
 * Check when you need to show an error message
 */
function qwqerAdminNoticesMsg()
{
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        add_action('admin_notices', 'qwqerAdminNotices');
    }
}

add_action('admin_init', 'qwqerAdminNoticesMsg');

/*
 * Error message
 */
function qwqerAdminNotices()
{
    echo '<div class="error"><p>' . __('Important! Install and activate the plugin "Woocommerce"', 'qwqer') . '</p></div>'; //Message
}

/*
 * Error message store address and city fields
 */
function qwqerSettingsAdminNotices()
{
    echo '<div class="error"><p>' . __('Important! Address and city fields in woocommerce settings are required', 'qwqer') . '</p></div>'; //Message
}

/*
 * If the logic is not broken, then run all the functions of the plugin
 */
$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
    add_action('woocommerce_shipping_init', 'qwqer_shipping_method_init');
    function qwqer_shipping_method_init()
    {
        $store_address = get_option('woocommerce_store_address');
        $store_city = get_option('woocommerce_store_city');
        if($store_address && $store_city) {
            require_once 'inc' . DIRECTORY_SEPARATOR . 'settings.php';
            require_once 'inc' . DIRECTORY_SEPARATOR . 'createOrder.php';
        } else {
            add_action('admin_notices', 'qwqerSettingsAdminNotices');
        }

    }

    add_filter('woocommerce_shipping_methods', 'qwqer_shipping_method');
    function qwqer_shipping_method($methods)
    {
        $methods['qwqer_shipping_method'] = 'WC_Qwqer_Shipping_Method';
        return $methods;
    }
}

/*
 * Clear form checkout
 */
function ClearCheckoutForm() {
    if (is_checkout()) { ?>
        <script type="text/javascript">
            ( function( $ ) {
                $('form.checkout').each(function() {
                    $('form.checkout').find('input').val('');
                });
            }( jQuery ) );
        </script>
    <?php }
}
add_action( 'wp_footer', 'ClearCheckoutForm' );