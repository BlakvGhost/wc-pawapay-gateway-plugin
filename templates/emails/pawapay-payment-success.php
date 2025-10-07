<?php

/**
 * Paiement réussi PawaPay
 *
 * @package WooCommerce\Templates\Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php printf(esc_html__('Bonjour %s,', 'wc-pawapay'), esc_html($order->get_billing_first_name())); ?></p>

<p><?php printf(esc_html__('Votre paiement pour la commande #%s a été confirmé avec succès.', 'wc-pawapay'), esc_html($order->get_order_number())); ?></p>

<p><?php esc_html_e('Merci pour votre confiance ! Votre commande est en cours de préparation.', 'wc-pawapay'); ?></p>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_footer', $email);
