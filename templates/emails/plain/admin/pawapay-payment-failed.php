<?php

/**
 * Paiement échoué PawaPay - Admin - Plain Text
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

echo esc_html__('Un paiement PawaPay a échoué.', 'wc-pawapay') . "\n\n";

echo esc_html__('DÉTAILS DE LA COMMANDE', 'wc-pawapay') . "\n";
echo "----------------------------------------\n";
echo esc_html__('Numéro de commande :', 'wc-pawapay') . ' #' . esc_html($order->get_order_number()) . "\n";
echo esc_html__('Date :', 'wc-pawapay') . ' ' . esc_html(wc_format_datetime($order->get_date_created())) . "\n";
echo esc_html__('Montant :', 'wc-pawapay') . ' ' . wp_strip_all_tags($order->get_formatted_order_total()) . "\n";
echo esc_html__('Client :', 'wc-pawapay') . ' ' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . "\n";
echo esc_html__('Raison de l\'échec :', 'wc-pawapay') . ' ' . esc_html($failure_reason) . "\n\n";

echo esc_html__('Lien vers la commande :', 'wc-pawapay') . "\n";
echo admin_url('post.php?post=' . $order->get_id() . '&action=edit') . "\n\n";

echo esc_html__('Action recommandée : Contacter le client pour l\'aider à finaliser sa commande.', 'wc-pawapay') . "\n\n";

echo "\n\n----------------------------------------\n\n";

echo wp_kses_post(wpautop(wptexturize(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')))));
