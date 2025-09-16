<?php
/*
Plugin Name: WooCommerce PawaPay Gateway
Description: Paiement mobile via la page de paiement PawaPay pour WooCommerce.
Version: 1.0.0
Author: Kabirou ALASSANE
Author URI: https://kabiroualassane.link
Requires at least: 5.6
WC requires at least: 5.5
WC tested up to: 8.0
*/

if (!defined('ABSPATH')) {
    exit;
}

define('WC_PAWAPAY_PLUGIN_FILE', __FILE__);

/**
 * Fonction principale d'initialisation du plugin.
 */
function wc_pawapay_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once __DIR__ . '/includes/class-pawapay-api.php';
    require_once __DIR__ . '/includes/class-wc-gateway-pawapay.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_PawaPay';
        return $gateways;
    });
}
add_action('plugins_loaded', 'wc_pawapay_init_gateway');

/**
 * Enregistrement de l'endpoint pour la conversion de devise.
 */
function pawapay_register_currency_conversion_route()
{
    register_rest_route('pawapay/v1', '/convert-currency', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'pawapay_handle_currency_conversion',
        'permission_callback' => function () {
            return true;
        },
    ]);
}
add_action('rest_api_init', 'pawapay_register_currency_conversion_route');

/**
 * Gère la conversion de devise via REST API.
 */
function pawapay_handle_currency_conversion(WP_REST_Request $request)
{
    $params = $request->get_json_params();

    if (empty($params)) {
        $params = $request->get_params();
    }

    $from = sanitize_text_field($params['from']);
    $to = sanitize_text_field($params['to']);
    $amount = floatval($params['amount']);

    if (!function_exists('WC')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'WooCommerce non initialisé'
        ], 500);
    }

    $gateways = WC()->payment_gateways->payment_gateways();

    if (!isset($gateways['pawapay'])) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Gateway PawaPay non trouvé'
        ], 500);
    }

    $pawapay_gateway = $gateways['pawapay'];

    if (!method_exists($pawapay_gateway, 'convert_currency')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Méthode de conversion non disponible'
        ], 500);
    }

    $converted = $pawapay_gateway->convert_currency($from, $to, $amount);

    if (is_wp_error($converted)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $converted->get_error_message()
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'data' => $converted
    ], 200);
}

/**
 * Gère les notifications de webhook de PawaPay.
 */
function pawapay_handle_webhook(WP_REST_Request $request)
{
    $body = $request->get_json_params();
    $logger = wc_get_logger();
    $logger->info('Webhook PawaPay reçu: ' . wp_json_encode($body), ['source' => 'pawapay']);

    if (!isset($body['paymentPageId']) || !isset($body['status'])) {
        return new WP_Error('missing_data', 'Données de webhook invalides', ['status' => 400]);
    }

    $payment_page_id = sanitize_text_field($body['paymentPageId']);
    list($order_id) = explode('_', $payment_page_id);

    $order = wc_get_order($order_id);

    if (!$order) {
        $logger->error('Webhook PawaPay: Commande non trouvée pour ID: ' . $order_id, ['source' => 'pawapay']);
        return new WP_Error('order_not_found', 'Commande non trouvée', ['status' => 404]);
    }

    if ($order->is_paid() || in_array($order->get_status(), ['processing', 'completed', 'failed'])) {
        return new WP_REST_Response(['status' => 'success', 'message' => 'Commande déjà traitée'], 200);
    }

    $transaction_id = isset($body['depositId']) ? sanitize_text_field($body['depositId']) : null;

    switch ($body['status']) {
        case 'COMPLETED':
            $order->add_order_note(__('Paiement PawaPay réussi. ID de transaction: ', 'woocommerce') . $transaction_id);
            $order->payment_complete($transaction_id);
            break;
        case 'FAILED':
            $reason = isset($body['failureReason']) ? sanitize_text_field($body['failureReason']) : 'Inconnue';
            $order->update_status('failed', sprintf(__('Le paiement PawaPay a échoué. Raison: %s', 'woocommerce'), $reason));
            break;
        case 'CANCELLED':
            $order->update_status('cancelled', __('Le paiement PawaPay a été annulé.', 'woocommerce'));
            break;
    }

    return new WP_REST_Response(['status' => 'success'], 200);
}

add_action('wp_enqueue_scripts', 'pawapay_add_styles');
function pawapay_add_styles()
{
    if (is_checkout()) {
        wp_enqueue_style('pawapay-checkout-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.0');
    }
}
