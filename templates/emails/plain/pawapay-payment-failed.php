<?php

/**
 * Paiement échoué PawaPay - Plain Text
 *
 * @package WooCommerce\Templates\Emails\Plain
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf(esc_html__('Bonjour %s,', 'wc-pawapay'), esc_html($order->get_billing_first_name())) . "\n\n";

echo sprintf(esc_html__('Votre paiement pour la commande #%s a échoué.', 'wc-pawapay'), esc_html($order->get_order_number())) . "\n\n";

echo esc_html__('Veuillez réessayer votre paiement ou contacter notre service client si le problème persiste.', 'wc-pawapay') . "\n\n";

echo esc_html__('Vous pouvez réessayer votre commande ici :', 'wc-pawapay') . "\n";
echo esc_url($order->get_checkout_payment_url()) . "\n\n";

echo "----------------------------------------\n\n";

if ($sent_to_admin) {
    echo "\n\n";
    echo esc_html__('Note de commande :', 'wc-pawapay') . "\n";
    echo esc_html__('Cette notification a été envoyée à un administrateur.', 'wc-pawapay') . "\n";
}

echo "\n----------------------------------------\n\n";

echo wp_kses_post(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')))));
