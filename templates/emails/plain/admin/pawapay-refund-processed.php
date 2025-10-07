<?php

/**
 * Remboursement PawaPay traité - Admin - Plain Text
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

echo esc_html__('Un remboursement PawaPay a été effectué avec succès.', 'wc-pawapay') . "\n\n";

echo esc_html__('DÉTAILS DU REMBOURSEMENT', 'wc-pawapay') . "\n";
echo "----------------------------------------\n";
echo esc_html__('Numéro de commande :', 'wc-pawapay') . ' #' . esc_html($order->get_order_number()) . "\n";
echo esc_html__('Date de la commande :', 'wc-pawapay') . ' ' . esc_html(wc_format_datetime($order->get_date_created())) . "\n";
echo esc_html__('Montant remboursé :', 'wc-pawapay') . ' ' . wp_strip_all_tags($refund_amount) . "\n";
echo esc_html__('Raison :', 'wc-pawapay') . ' ' . esc_html($refund_reason) . "\n";
echo esc_html__('Client :', 'wc-pawapay') . ' ' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . "\n\n";

echo esc_html__('Lien vers la commande :', 'wc-pawapay') . "\n";
echo admin_url('post.php?post=' . $order->get_id() . '&action=edit') . "\n\n";

echo "\n\n----------------------------------------\n\n";

echo wp_kses_post(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')))));
