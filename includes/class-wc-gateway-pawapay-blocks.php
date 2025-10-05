<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_PawaPay_Blocks extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType
{

    protected $name = 'pawapay';
    protected $settings;

    public function initialize()
    {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
    }

    public function is_active()
    {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles()
    {
        if (!wp_script_is('wc-pawapay-blocks-integration', 'registered')) {
            wp_register_script(
                'wc-pawapay-blocks-integration',
                plugin_dir_url(WC_PAWAPAY_PLUGIN_FILE) . 'assets/js/blocks-integration.js',
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                    'wc-blocks-checkout',
                ],
                '1.0.0',
                true
            );
        }

        return ['wc-pawapay-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $gateway = $gateways[$this->name] ?? null;

        $countries = [];
        if ($gateway && method_exists($gateway, 'get_active_configuration_countries')) {
            $config = $gateway->get_active_configuration_countries();
            if (!is_wp_error($config) && isset($config['countries'])) {
                $countries = $config['countries'];
            }
        }

        return [
            'title' => $this->settings['title'] ?? __('Mobile Money (PawaPay)', 'wc-pawapay'),
            'description' => $this->settings['description'] ?? __('Vous serez redirigé vers une page sécurisée pour finaliser votre paiement.', 'wc-pawapay'),
            'supports' => $this->get_supported_features(),
            'countries' => $countries,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pawapay_nonce'),
            'current_currency' => get_woocommerce_currency(),
            'order_total' => WC()->cart ? WC()->cart->get_total('edit') : 0,
            'i18n' => [
                'select_country' => __('Sélectionnez un pays', 'wc-pawapay'),
                'select_currency' => __('Sélectionnez une devise', 'wc-pawapay'),
                'converted_amount' => __('Le montant total de votre commande de %s sera converti et payé en %s', 'wc-pawapay'),
                'country_required' => __('Veuillez sélectionner un pays', 'wc-pawapay'),
                'currency_required' => __('Veuillez sélectionner une devise', 'wc-pawapay'),
                'form_error' => __('Veuillez corriger les erreurs dans le formulaire PawaPay.', 'wc-pawapay'),
                'conversion_in_progress' => __('Conversion en cours...', 'wc-pawapay'),
                'conversion_not_working' => __('Service de conversion temporairement indisponible', 'wc-pawapay'),
                'pay_button_label' => __('Payer avec PawaPay', 'wc-pawapay'),
                'country_label' => __('Pays', 'wc-pawapay'),
                'currency_label' => __('Devise', 'wc-pawapay'),
            ]
        ];
    }

    public function get_supported_features()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $gateway = $gateways[$this->name] ?? null;

        if ($gateway) {
            return $gateway->supports;
        }

        return ['products'];
    }
}
