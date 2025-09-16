<?php
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

final class WC_PawaPay_Blocks_Support extends AbstractPaymentMethodType
{

    protected $name = 'pawapay';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_pawapay_settings', []);

        // Ajout du hook pour sauvegarder les données lors de la mise à jour du checkout
        add_action('woocommerce_store_api_checkout_update_order_from_cart', [$this, 'update_order_meta_from_blocks_data'], 10, 2);
    }

    /**
     * Cette fonction intercepte les données envoyées par le composant JS (via setData)
     * et les sauvegarde comme métadonnées de la commande.
     * C'est l'étape cruciale qui manquait.
     */
    public function update_order_meta_from_blocks_data($order, $request)
    {
        // On récupère les données envoyées par le frontend
        $payment_data = $request->get_param('payment_method_data');

        if ($order->get_payment_method() === $this->name && !empty($payment_data)) {
            if (isset($payment_data['pawapay_country'])) {
                $order->update_meta_data('pawapay_country', sanitize_text_field($payment_data['pawapay_country']));
            }
            if (isset($payment_data['pawapay_currency'])) {
                $order->update_meta_data('pawapay_currency', sanitize_text_field($payment_data['pawapay_currency']));
            }
        }
    }


    public function is_active()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        return isset($gateways[$this->name]) && $gateways[$this->name]->is_available();
    }

    public function get_payment_method_script_handles()
    {
        $script_path = dirname(WC_PAWAPAY_PLUGIN_FILE) . '/assets/js/blocks-checkout.js';
        $script_url = plugin_dir_url(WC_PAWAPAY_PLUGIN_FILE) . '/assets/js/blocks-checkout.js';
        $script_asset_path = dirname(WC_PAWAPAY_PLUGIN_FILE) . '/assets/js/blocks-checkout.asset.php';

        $dependencies = ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wc-blocks-checkout'];
        $version = '1.0.2'; // Incrémenter la version pour forcer le rafraîchissement du cache

        if (file_exists($script_asset_path)) {
            $asset = require($script_asset_path);
            $dependencies = $asset['dependencies'];
            $version = $asset['version'];
        }

        wp_register_script(
            'wc-pawapay-blocks',
            $script_url,
            $dependencies,
            $version,
            true
        );

        return ['wc-pawapay-blocks'];
    }

    public function get_payment_method_data()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $pawapay_gateway = $gateways[$this->name];
        $active_config = $pawapay_gateway->get_active_configuration_countries();

        return [
            'title'            => $this->get_setting('title', 'Mobile Money (PawaPay)'),
            'description'      => $this->get_setting('description', 'Vous serez redirigé pour payer via PawaPay.'),
            'countries'        => !is_wp_error($active_config) ? $active_config['countries'] : [],
            'total_price'      => WC()->cart->get_total('edit'),
            'current_currency' => get_woocommerce_currency(),
            'nonce'            => wp_create_nonce('pawapay_nonce'),
        ];
    }
}
