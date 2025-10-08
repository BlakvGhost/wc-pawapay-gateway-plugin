<?php

/**
 * Remboursement PawaPay traité - Plain Text
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

echo sprintf(
    esc_html__('Un remboursement de %s a été effectué pour votre commande #%s.', 'wc-pawapay'),
    wp_strip_all_tags($refund_amount),
    esc_html($order->get_order_number())
) . "\n\n";

if (!empty($order->get_customer_note())) {
    echo esc_html__('Raison du remboursement :', 'wc-pawapay') . ' ' . esc_html($order->get_customer_note()) . "\n\n";
}

echo esc_html__('Le remboursement sera crédité sur votre compte sous peu.', 'wc-pawapay') . "\n\n";

echo "----------------------------------------\n\n";

echo esc_html__('DÉTAILS DE LA COMMANDE', 'wc-pawapay') . "\n\n";
echo "----------------------------------------\n\n";

do_action('woocommerce_email_order_details', $order, $sent_to_admin, true, $email);

echo "\n----------------------------------------\n\n";

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, true, $email);

echo "\n\n----------------------------------------\n\n";

echo wp_kses_post(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')))));
