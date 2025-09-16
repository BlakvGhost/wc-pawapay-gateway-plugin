<?php
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_PawaPay_Blocks_Support extends AbstractPaymentMethodType
{

    protected $name = 'pawapay';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_pawapay_settings', []);
    }

    public function is_active()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        return isset($gateways[$this->name]) && $gateways[$this->name]->is_available();
    }

    public function get_payment_method_script_handles()
    {
        $script_path = dirname(WC_PAWAPAY_PLUGIN_FILE) . '/assets/js/blocks-checkout.js';
        $script_url = plugin_dir_url(WC_PAWAPAY_PLUGIN_FILE) . 'assets/js/blocks-checkout.js';
        $script_asset_path = dirname(WC_PAWAPAY_PLUGIN_FILE) . '/assets/js/blocks-checkout.asset.php';

        $dependencies = ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'];
        $version = '1.0.0';
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
        return [
            'title'       => $this->get_setting('title', 'Mobile Money (PawaPay)'),
            'description' => $this->get_setting('description', 'Vous serez redirigé pour payer via PawaPay.'),
        ];
    }
}
