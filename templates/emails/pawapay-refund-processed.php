<?php

/**
 * Remboursement PawaPay traité
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

<p><?php
    printf(
        esc_html__('Un remboursement de %s a été effectué pour votre commande #%s.', 'wc-pawapay'),
        $refund_amount,
        esc_html($order->get_order_number())
    );
    ?></p>

<?php if (!empty($order->get_customer_note())): ?>
    <p><strong><?php esc_html_e('Raison du remboursement :', 'wc-pawapay'); ?></strong> <?php echo esc_html($order->get_customer_note()); ?></p>
<?php endif; ?>

<p><?php esc_html_e('Le remboursement sera crédité sur votre compte sous 3 à 5 jours ouvrables.', 'wc-pawapay'); ?></p>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_footer', $email);
