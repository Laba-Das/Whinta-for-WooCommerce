<?php
/*
 * Plugin Name: Whinta for WooCommerce
 * Plugin URI: https://www.Teckshop.net/our-plugin/
 * Description: Whinta for WooCommerce is a powerful plugin that seamlessly integrates WhatsApp notifications into your WooCommerce store.
 * Version: 1.0.1
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Teckshop.net
 * Author URI: https://www.Teckshop.net
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://www.Teckshop.net/our-plugin/
 * Text Domain: my-basics-plugin
 * Domain Path: /languages
 */

// Add a menu item to the admin dashboard for plugin settings with a WhatsApp icon.
function whinta_for_woocommerce_menu() {
    add_menu_page('Whinta for WooCommerce Settings', '<i class="fab fa-whatsapp"></i> Whinta for WooCommerce', 'manage_options', 'whatsapp-notifier-settings', 'whinta_for_woocommerce_page');
}
add_action('admin_menu', 'whinta_for_woocommerce_menu');

// Create the plugin settings page.
function whinta_for_woocommerce_page() {
    ?>
    <div class="wrap">
        <h2>Whinta for WooCommerce Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('whatsapp_notifier_settings');
            do_settings_sections('whatsapp-notifier-settings');
            submit_button();
            ?>
        </form>
        <div class="whatsapp-explanation">
            <h3>Placeholder Explanations:</h3>
            <p>{customer_name} - Customer's name</p>
            <p>{order_total} - Order's total amount</p>
            <p>{billing_address} - Customer's billing address</p>
            <p>{order_status} - Order's status</p>
        </div>
    </div>
    <?php
}

// Define and register the settings.
function whinta_for_woocommerce_settings_init() {
    register_setting('whatsapp_notifier_settings', 'whatsapp_app_key');
    register_setting('whatsapp_notifier_settings', 'whatsapp_auth_key');
    register_setting('whatsapp_notifier_settings', 'whatsapp_default_message');
    register_setting('whatsapp_notifier_settings', 'whatsapp_status_change_message'); // New field for status change message
}

add_action('admin_init', 'whinta_for_woocommerce_settings_init');

// Define fields and sections for the settings page.
function whinta_for_woocommerce_settings_fields() {
    add_settings_section('whatsapp_notifier_section', 'Whinta for WooCommerce Configuration', null, 'whatsapp-notifier-settings');
    
    add_settings_field('whatsapp_app_key', 'App Key', 'whatsapp_app_key_callback', 'whatsapp-notifier-settings', 'whatsapp_notifier_section');
    add_settings_field('whatsapp_auth_key', 'Auth Key', 'whatsapp_auth_key_callback', 'whatsapp-notifier-settings', 'whatsapp_notifier_section');
    add_settings_field('whatsapp_default_message', 'Default Message', 'whatsapp_default_message_callback', 'whatsapp-notifier-settings', 'whatsapp_notifier_section');
    add_settings_field('whatsapp_status_change_message', 'Status Change Message', 'whatsapp_status_change_message_callback', 'whatsapp-notifier-settings', 'whatsapp_notifier_section'); // New field for status change message
}

add_action('admin_init', 'whinta_for_woocommerce_settings_fields');

// Callback functions for rendering settings fields.
function whatsapp_app_key_callback() {
    $value = esc_attr(get_option('whatsapp_app_key'));
    echo '<input type="text" name="whatsapp_app_key" value="' . $value . '" />';
}

function whatsapp_auth_key_callback() {
    $value = esc_attr(get_option('whatsapp_auth_key'));
    echo '<input type="text" name="whatsapp_auth_key" value="' . $value . '" />';
}

function whatsapp_default_message_callback() {
    $value = esc_attr(get_option('whatsapp_default_message'));
    echo '<textarea name="whatsapp_default_message" rows="5" cols="50">' . $value . '</textarea>';
}

// Callback function for rendering the status change message text area.
function whatsapp_status_change_message_callback() {
    $value = esc_attr(get_option('whatsapp_status_change_message'));
    if (empty($value)) {
        $value = "Hello, {customer_name}!🎉\n\nGreat news! Your order status has been updated to {order_status} 🚚. Your order with a total of 💰{order_total} is on its way to your billing address: 🏡 {billing_address} . Expect delivery in 7-10 working days ⏳.\n\nThanks for choosing us! 💙";
        update_option('whatsapp_status_change_message', $value); // Set the default message
    }
    echo '<textarea name="whatsapp_status_change_message" rows="5" cols="50">' . $value . '</textarea>';
}


// Send WhatsApp message on new order
function send_whatsapp_message_on_order($order_id) {
    // Get the order object
    $order = wc_get_order($order_id);

    // Extract order information
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name = $order->get_billing_last_name();
    $billing_address = $order->get_billing_address_1();
    $order_total = $order->get_total();

    // Extract the order status
    $order_status = wc_get_order_status_name($order->get_status());
	
    // Get API credentials and default message from settings
    $app_key = get_option('whatsapp_app_key', 'YOUR_DEFAULT_APP_KEY');
    $auth_key = get_option('whatsapp_auth_key', 'YOUR_DEFAULT_AUTH_KEY');
    $default_message = get_option('whatsapp_default_message', 'Thank you, {customer_name}, for your purchase. Your order total is {order_total}. Shipping address: {billing_address}. Order status: {order_status}.');

    // Replace placeholders in the default message
    $message = str_replace('{customer_name}', $billing_first_name, $default_message);
    $message = str_replace('{order_total}', $order_total, $message);
    $message = str_replace('{billing_address}', $billing_address, $message);
    $message = str_replace('{order_status}', $order_status, $message);

    // Whinta API Endpoint
    $whinta_api_url = 'https://whinta.com/api/create-message';

    // Define message parameters
    $data = array(
        'appkey' => $app_key,
        'authkey' => $auth_key,
        'to' => '91' . $order->get_billing_phone(),
        'message' => $message,
        'sandbox' => 'false'
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $whinta_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
    ));

    $response = curl_exec($curl);

    curl_close($curl);
}

add_action('woocommerce_new_order', 'send_whatsapp_message_on_order');


function send_whatsapp_message_on_status_change($order_id, $old_status, $new_status) {
    // Get the order object
    $order = wc_get_order($order_id);

    // Extract order information
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name = $order->get_billing_last_name();
    $billing_address = $order->get_billing_address_1();
    $order_total = $order->get_total();

    // Extract the order status
    $order_status = wc_get_order_status_name($new_status);

    // Get API credentials from settings
    $app_key = get_option('whatsapp_app_key', 'YOUR_DEFAULT_APP_KEY');
    $auth_key = get_option('whatsapp_auth_key', 'YOUR_DEFAULT_AUTH_KEY');
    
    // Retrieve the custom status change message from the settings
    $custom_status_change_message = get_option('whatsapp_status_change_message');

    // If a custom message is provided, use it; otherwise, use a default message
    if (!empty($custom_status_change_message)) {
        $message = $custom_status_change_message;
    } else {
        // Default message when no custom message is set
        $message = "Hello, $billing_first_name!🎉

Great news! Your order status has been updated to $order_status 🚚. Your order with a total of 💰$order_total is on its way to your billing address: 🏡 $billing_address . Expect delivery in 7-10 working days ⏳.

Thanks for choosing us! 💙";
    }

    // Replace the shortcodes with actual values
    $message = str_replace('{customer_name}', $billing_first_name, $message);
    $message = str_replace('{order_total}', $order_total, $message);
    $message = str_replace('{billing_address}', $billing_address, $message);
    $message = str_replace('{order_status}', $order_status, $message);

    // Whinta API Endpoint
    $whinta_api_url = 'https://whinta.com/api/create-message';

    // Define message parameters
    $data = array(
        'appkey' => $app_key,
        'authkey' => $auth_key,
        'to' => '91' . $order->get_billing_phone(),
        'message' => $message,
        'sandbox' => 'false'
    );

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $whinta_api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data,
    ));

    $response = curl_exec($curl);

    curl_close($curl);
}


add_action('woocommerce_order_status_changed', 'send_whatsapp_message_on_status_change', 10, 3);



function enqueue_custom_js() {
    wp_enqueue_script('custom-script', plugin_dir_url(__FILE__) . 'custom.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'enqueue_custom_js');



