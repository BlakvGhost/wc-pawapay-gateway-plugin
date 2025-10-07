<?php

/**
 * Paiement réussi PawaPay - Admin
 *
 * @package WooCommerce\Templates\Emails
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php esc_html_e('Un nouveau paiement PawaPay a été confirmé avec succès.', 'wc-pawapay'); ?></p>

<h2><?php esc_html_e('Détails de la commande', 'wc-pawapay'); ?></h2>

<ul>
    <li><strong><?php esc_html_e('Numéro de commande :', 'wc-pawapay'); ?></strong> #<?php echo esc_html($order->get_order_number()); ?></li>
    <li><strong><?php esc_html_e('Date :', 'wc-pawapay'); ?></strong> <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></li>
    <li><strong><?php esc_html_e('Montant :', 'wc-pawapay'); ?></strong> <?php echo wp_kses_post($order->get_formatted_order_total()); ?></li>
    <li><strong><?php esc_html_e('Client :', 'wc-pawapay'); ?></strong> <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></li>
    <li><strong><?php esc_html_e('Email :', 'wc-pawapay'); ?></strong> <?php echo esc_html($order->get_billing_email()); ?></li>
    <li><strong><?php esc_html_e('Téléphone :', 'wc-pawapay'); ?></strong> <?php echo esc_html($order->get_billing_phone()); ?></li>
</ul>

<p>
    <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>" style="background: #007cba; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">
        <?php esc_html_e('Voir la commande dans l\'admin', 'wc-pawapay'); ?>
    </a>
</p>

<?php
do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);

do_action('woocommerce_email_footer', $email);
