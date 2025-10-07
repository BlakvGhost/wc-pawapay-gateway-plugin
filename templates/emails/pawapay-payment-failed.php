<?php

/**
 * Paiement échoué PawaPay
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

<p><?php printf(esc_html__('Votre paiement pour la commande #%s a échoué.', 'wc-pawapay'), esc_html($order->get_order_number())); ?></p>

<p><?php esc_html_e('Veuillez réessayer votre paiement ou contacter notre service client si le problème persiste.', 'wc-pawapay'); ?></p>

<p><?php esc_html_e('Vous pouvez réessayer votre commande ici :', 'wc-pawapay'); ?></p>
<p style="text-align: center; margin: 20px 0;">
    <a href="<?php echo esc_url($order->get_checkout_payment_url()); ?>" style="background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
        <?php esc_html_e('Réessayer le paiement', 'wc-pawapay'); ?>
    </a>
</p>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_footer', $email);
