<?php
/*
Plugin Name: WooCommerce PawaPay Gateway
Description: Paiement mobile via PawaPay pour WooCommerce (avec conversion automatique vers XOF/XAF).
Version: 1.2.2
Author: Ferray Digital Solutions
Requires at least: 5.6
WC requires at least: 5.5
WC tested up to: 7.0
*/

if (! defined('ABSPATH')) {
    exit;
}

// Déclarer la compatibilité avec WooCommerce
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_action('plugins_loaded', 'wc_pawapay_init_gateway', 11);

function wc_pawapay_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    require_once __DIR__ . '/includes/class-wc-gateway-pawapay.php';
    require_once __DIR__ . '/includes/class-pawapay-api.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_PawaPay';
        return $gateways;
    });

    // Déclarer la compatibilité avec les blocs
    add_action('woocommerce_blocks_loaded', function () {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once __DIR__ . '/includes/class-wc-pawapay-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function ($payment_method_registry) {
                    $payment_method_registry->register(new WC_PawaPay_Blocks_Support());
                }
            );
        }
    });
}

add_action('wp_ajax_pawapay_get_providers', 'pawapay_get_providers_ajax');
add_action('wp_ajax_nopriv_pawapay_get_providers', 'pawapay_get_providers_ajax');

function pawapay_get_providers_ajax()
{
    check_ajax_referer('pawapay_nonce', 'security');

    $country = sanitize_text_field($_POST['country']);

    // Récupérer le gateway PawaPay
    $gateway = new WC_Gateway_PawaPay();
    $client = $gateway->client;

    $response = $client->provider_availability($country);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($code !== 200) {
        wp_send_json_error(['message' => 'Erreur API PawaPay: Code ' . $code]);
        return;
    }

    $data = json_decode($body, true);

    if (empty($data) || !isset($data[0]['providers'])) {
        wp_send_json_error(['message' => 'Aucun opérateur disponible']);
        return;
    }

    wp_send_json_success(['providers' => $data[0]['providers']]);
}

// Localisation des scripts
add_action('wp_enqueue_scripts', 'pawapay_localize_scripts');

function pawapay_localize_scripts()
{
    if (is_checkout() || is_wc_endpoint_url('order-pay')) {
        wp_register_script('pawapay-checkout', plugin_dir_url(__FILE__) . 'assets/js/pawapay-checkout.js', ['jquery'], '1.0.0', true);
        wp_register_style('pawapay-checkout', plugin_dir_url(__FILE__) . 'assets/css/pawapay-checkout.css', [], '1.0.0');

        wp_enqueue_script('pawapay-checkout');
        wp_enqueue_style('pawapay-checkout');

        wp_localize_script('pawapay-checkout', 'pawapay_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('pawapay_nonce'),
            'select_country_first' => __('Veuillez d\'abord sélectionner un pays', 'woocommerce'),
            'loading_providers' => __('Chargement des opérateurs...', 'woocommerce'),
            'select_provider' => __('Sélectionnez un opérateur', 'woocommerce'),
            'no_providers' => __('Aucun opérateur disponible', 'woocommerce'),
            'error_loading' => __('Erreur lors du chargement', 'woocommerce'),
        ]);
    }
}

add_action('woocommerce_rest_checkout_process_payment_with_context', 'pawapay_save_custom_fields', 10, 2);
function pawapay_save_custom_fields($context, $payment_result)
{
    if (isset($_POST['pawapay_country'])) {
        $context->payment_data['pawapay_country'] = sanitize_text_field($_POST['pawapay_country']);
    }
    if (isset($_POST['pawapay_provider'])) {
        $context->payment_data['pawapay_provider'] = sanitize_text_field($_POST['pawapay_provider']);
    }
    if (isset($_POST['pawapay_phone'])) {
        $context->payment_data['pawapay_phone'] = sanitize_text_field($_POST['pawapay_phone']);
    }
}
