<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_PawaPay_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{
    protected $name = 'pawapay';
    protected $settings;

    public function initialize()
    {
        $this->settings = get_option('woocommerce_pawapay_settings', []);
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'wc-pawapay-blocks',
            plugin_dir_url(__FILE__) . '../assets/js/blocks-checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ],
            '1.0.0',
            true
        );

        return ['wc-pawapay-blocks'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->settings['title'] ?? 'Mobile Money (PawaPay)',
            'description' => $this->settings['description'] ?? 'Payer avec Mobile Money via PawaPay.',
            'supports' => $this->get_supported_features(),
            'countries' => ['BJ', 'BF', 'CI', 'CM', 'ML', 'NE', 'SN', 'TG'],
        ];
    }

    public function get_supported_features()
    {
        return [
            'products',
        ];
    }
}
