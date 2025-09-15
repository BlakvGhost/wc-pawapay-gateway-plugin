<?php
/*
Plugin Name: WooCommerce PawaPay Gateway
Description: Paiement mobile via PawaPay pour WooCommerce (avec conversion automatique vers XOF/XAF).
Version: 1.2.1
Author: Ferray Digital Solutions
Requires at least: 5.6
WC requires at least: 5.5
WC tested up to: 7.0
*/

if (! defined('ABSPATH')) {
    exit;
}

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
    if (is_checkout()) {
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
