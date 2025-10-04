<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_PawaPay_Blocks_Support
{

    public function __construct()
    {
        add_action('woocommerce_blocks_loaded', [$this, 'initialize_blocks_support']);
    }

    public function initialize_blocks_support()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        require_once __DIR__ . '/class-wc-gateway-pawapay-blocks.php';

        add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
            $registry->register(new WC_Gateway_PawaPay_Blocks());
        });
    }
}

new WC_Gateway_PawaPay_Blocks_Support();
