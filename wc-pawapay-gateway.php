<?php
/*
Plugin Name: WooCommerce PawaPay Gateway
Description: Paiement mobile via la page de paiement PawaPay pour WooCommerce.
Version: 2.1.0
Author: Ferray Digital Solutions
Requires at least: 5.6
WC requires at least: 5.5
WC tested up to: 8.0
*/

if (!defined('ABSPATH')) {
    exit;
}

// Définir une constante pour le chemin du plugin, très utile pour la robustesse
define('WC_PAWAPAY_PLUGIN_FILE', __FILE__);

/**
 * Fonction principale d'initialisation du plugin.
 */
function wc_pawapay_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    // Charger les classes principales
    require_once __DIR__ . '/includes/class-pawapay-api.php';
    require_once __DIR__ . '/includes/class-wc-gateway-pawapay.php';

    // Ajouter la passerelle à la liste de WooCommerce
    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_PawaPay';
        return $gateways;
    });
}
add_action('plugins_loaded', 'wc_pawapay_init_gateway');

/**
 * Enregistrement de l'intégration avec les Blocs WooCommerce.
 * C'est la section cruciale qui résout le problème.
 */
function wc_pawapay_blocks_support()
{
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once __DIR__ . '/includes/class-wc-pawapay-blocks-support.php';

    // Le hook que vous suspectiez ! Celui-ci est le bon pour enregistrer notre classe de support.
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_PawaPay_Blocks_Support());
        }
    );
}
// Nous utilisons 'woocommerce_blocks_loaded' pour nous assurer que tout est prêt avant d'essayer d'enregistrer notre méthode.
add_action('woocommerce_blocks_loaded', 'wc_pawapay_blocks_support');

/**
 * Enregistrement de l'endpoint pour le webhook.
 */
function pawapay_register_webhook_route()
{
    register_rest_route('pawapay/v1', '/webhook', [
        'methods'             => 'POST',
        'callback'            => 'pawapay_handle_webhook',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'pawapay_register_webhook_route');

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
// Dans wc-pawapay-gateway.php ou dans une autre classe d'initialisation
add_action('wp_enqueue_scripts', 'pawapay_add_styles');
function pawapay_add_styles()
{
    if (is_checkout()) {
        wp_enqueue_style('pawapay-checkout-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', [], '1.0.0');
    }
}
