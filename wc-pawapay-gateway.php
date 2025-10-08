<?php
/*
Plugin Name: WooCommerce PawaPay Gateway
Plugin URI: https://github.com/BlakvGhost/wc-pawapay-gateway-plugin#readme
Description: Accept Mobile Money payments through the PawaPay Payment Page in WooCommerce. Supports multi-country and multi-operator payments with automatic currency conversion (XOF/XAF/EUR/USD), full refund management, WooCommerce Block & Classic Checkout compatibility, and an integrated PawaPay Dashboard. Includes optional ExchangeRate API integration and unique webhook identifiers for multi-store setups.
Version: 1.3.0
Author: Kabirou ALASSANE
Author URI: https://kabiroualassane.link
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: wc-pawapay
Domain Path: /languages
Requires at least: 5.6
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 5.5
WC tested up to: 6.8
*/


if (!defined('ABSPATH')) {
    exit;
}

define('WC_PAWAPAY_PLUGIN_FILE', __FILE__);
define('WC_PAWAPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

function wc_pawapay_load_textdomain()
{
    load_plugin_textdomain(
        'wc-pawapay',
        false,
        dirname(plugin_basename(WC_PAWAPAY_PLUGIN_FILE)) . '/languages/'
    );
}
add_action('plugins_loaded', 'wc_pawapay_load_textdomain');

function wc_pawapay_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/class-pawapay-api.php';
    require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/class-wc-gateway-pawapay.php';
    require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/class-wc-gateway-pawapay-refunds.php';
    require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/class-wc-gateway-pawapay-blocks-support.php';
    require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/class-pawapay-emails.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_PawaPay';
        return $gateways;
    });
}
add_action('plugins_loaded', 'wc_pawapay_init_gateway');

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

function pawapay_handle_webhook(WP_REST_Request $request)
{
    $body = $request->get_json_params();

    if (!isset($body['status'])) {
        return new WP_Error('missing_data', 'Données de webhook invalides', ['status' => 400]);
    }

    $order_id = $body['depositId'] ?? 0;

    $gateways = WC()->payment_gateways->payment_gateways();
    $gateway = $gateways['pawapay'] ?? null;

    if (!$gateway) {
        return new WP_Error('gateway_not_found', 'Gateway PawaPay non trouvé', ['status' => 500]);
    }

    $client = $gateway->client;
    $url    = $client->get_base_url() . '/deposits/' . $order_id;
    $resp   = wp_remote_get($url, ['headers' => $client->get_headers(), 'timeout' => 30]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        wc_add_notice(__('Impossible de vérifier le statut du paiement(webhook).', 'woocommerce'), 'error');
        exit;
    }

    $data   = json_decode(wp_remote_retrieve_body($resp), true);
    $status = $data['status'] ? $data['data']['status'] : null;

    $order_id = $body['metadata']['order_id'] ?? 0;
    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('order_not_found', 'Commande non trouvée', ['status' => 404]);
    }

    if ($order->is_paid() || in_array($order->get_status(), ['processing', 'completed', 'failed'])) {
        return new WP_REST_Response(['status' => 'success', 'message' => 'Commande déjà traitée'], 200);
    }

    $transaction_id = isset($body['depositId']) ? sanitize_text_field($body['depositId']) : null;

    switch ($status) {
        case 'COMPLETED':
            if (!$order->is_paid()) {
                $order->add_order_note(__('Paiement PawaPay réussi. ID de transaction: ', 'woocommerce') . $transaction_id);
                $order->payment_complete($transaction_id);
                $order->update_status('completed', __('Paiement confirmé par PawaPay.', 'woocommerce'));
                do_action('pawapay_payment_success', $order_id, $order);
            }
            break;
        case 'FAILED':
            $reason = isset($body['failureReason']) ? sanitize_text_field($body['failureReason']) : 'Inconnue';
            $order->update_status('failed', sprintf(__('Le paiement PawaPay a échoué. Raison: %s', 'woocommerce'), $reason));
            do_action('pawapay_payment_failed', $order_id, $order, $reason);
            break;
        case 'CANCELLED':
            $reason = isset($body['failureReason']) ? sanitize_text_field($body['failureReason']) : 'Inconnue';
            $order->update_status('cancelled', __('Le paiement PawaPay a été annulé.', 'woocommerce'));
            do_action('pawapay_payment_failed', $order_id, $order, $reason);
            break;
    }

    return new WP_REST_Response(['status' => 'success'], 200);
}


function pawapay_handle_refund_webhook(WP_REST_Request $request)
{
    $body = $request->get_json_params();

    if (!isset($body['status'])) {
        return new WP_Error('missing_data', 'Données de webhook invalides', ['status' => 400]);
    }

    $refund_id = $body['refundId'] ?? 0;

    $gateways = WC()->payment_gateways->payment_gateways();
    $gateway = $gateways['pawapay'] ?? null;

    if (!$gateway) {
        return new WP_Error('gateway_not_found', 'Gateway PawaPay non trouvé', ['status' => 500]);
    }

    $client = $gateway->client;
    $url    = $client->get_base_url() . '/refunds/' . $refund_id;

    $checkResponse = wp_remote_get($url, [
        'headers' => $client->get_headers(),
        'timeout' => 30,
    ]);

    if (is_wp_error($checkResponse) || wp_remote_retrieve_response_code($checkResponse) !== 200) {
        return new WP_Error('refund_check_failed', __('Failed to verify refund status via PawaPay API.', 'wc-pawapay'));
    }

    $checkData = json_decode(wp_remote_retrieve_body($checkResponse), true);

    if (isset($checkData['data']['status']) && $checkData['data']['status'] === 'COMPLETED') {
        $order_id = $checkData['metadata']['order_id'] ?? 0;

        $order = wc_get_order($order_id);
        if ($order) {
            do_action('pawapay_refund_processed', $order_id, $order, $order->get_total(), __('Remboursement complété via webhook PawaPay', 'wc-pawapay'));
        }
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

function pawapay_register_webhook_route()
{
    register_rest_route('pawapay/v1', '/deposit-callback', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'pawapay_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'pawapay_register_webhook_route');

function pawapay_register_refund_webhook_route()
{
    register_rest_route('pawapay/v1', '/refund-callback', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'pawapay_handle_refund_webhook',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'pawapay_register_refund_webhook_route');

function pawapay_register_return_route()
{
    register_rest_route('pawapay/v1', '/return', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'pawapay_handle_return',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'pawapay_register_return_route');

function pawapay_handle_return(WP_REST_Request $request)
{
    $order_id    = absint($request->get_param('order_id'));
    $deposit_id  = sanitize_text_field($request->get_param('deposit_id'));

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_redirect(wc_get_checkout_url());
        exit;
    }

    $gateway = WC()->payment_gateways->payment_gateways()['pawapay'] ?? null;
    if (!$gateway) {
        wc_add_notice(__('Erreur: Gateway PawaPay introuvable.', 'woocommerce'), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }

    $client = $gateway->client;
    $url    = $client->get_base_url() . '/deposits/' . $deposit_id;
    $resp   = wp_remote_get($url, ['headers' => $client->get_headers(), 'timeout' => 30]);

    if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
        wc_add_notice(__('Impossible de vérifier le statut du paiement.', 'woocommerce'), 'error');
        wp_redirect($order->get_cancel_order_url_raw());
        exit;
    }

    $data   = json_decode(wp_remote_retrieve_body($resp), true);
    $status = $data['status'] ? $data['data']['status'] : null;

    switch ($status) {
        case 'COMPLETED':
            if (!$order->is_paid()) {
                $order->payment_complete($deposit_id);
                $order->update_status('completed', __('Paiement confirmé par PawaPay (return).', 'woocommerce'));
                do_action('pawapay_payment_success', $order_id, $order);
            }
            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;

        case 'FAILED':
        case 'CANCELLED':
        default:
            $reason = isset($data['failureReason']) ? sanitize_text_field($data['failureReason']) : 'Inconnue';
            $order->update_status('failed', __('Paiement échoué ou annulé via PawaPay (return).', 'woocommerce'));
            wp_safe_redirect(add_query_arg('pawapay_error', '1', wc_get_checkout_url()));
            do_action('pawapay_payment_failed', $order_id, $order, $reason);
            exit;
    }
}

add_action('woocommerce_before_checkout_form', function () {
    if (!empty($_GET['pawapay_error'])) {
        wc_print_notice(__('Votre paiement n\'a pas pu être traité, merci de réessayer.', 'woocommerce'), 'error');
    }
});

require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/admin/class-pawapay-admin-menu.php';
