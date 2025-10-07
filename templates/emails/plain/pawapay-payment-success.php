<?php
if (!defined('ABSPATH')) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html(wp_strip_all_tags($email_heading));
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf(__('Bonjour %s,', 'wc-pawapay'), $order->get_billing_first_name()) . "\n\n";

echo sprintf(__('Votre paiement pour la commande #%s a été confirmé avec succès.', 'wc-pawapay'), $order->get_order_number()) . "\n\n";

echo "\n----------------------------------------\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
