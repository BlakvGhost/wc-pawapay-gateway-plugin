<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_PawaPay_Emails
{

    public function __construct()
    {
        add_filter('woocommerce_email_classes', array($this, 'register_emails'));
        add_action('pawapay_payment_success', array($this, 'trigger_payment_success'), 10, 2);
        add_action('pawapay_payment_failed', array($this, 'trigger_payment_failed'), 10, 2);
        add_action('pawapay_refund_processed', array($this, 'trigger_refund_processed'), 10, 3);

        // S'assurer que les templates sont chargés depuis le bon dossier
        add_filter('woocommerce_locate_template', array($this, 'locate_template'), 10, 3);
    }

    public function register_emails($email_classes)
    {
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-success.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-failed.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-refund-processed.php';

        $email_classes['WC_Email_PawaPay_Payment_Success'] = new WC_Email_PawaPay_Payment_Success();
        $email_classes['WC_Email_PawaPay_Payment_Failed'] = new WC_Email_PawaPay_Payment_Failed();
        $email_classes['WC_Email_PawaPay_Refund_Processed'] = new WC_Email_PawaPay_Refund_Processed();

        return $email_classes;
    }

    public function trigger_payment_success($order_id, $order)
    {
        $mailer = WC()->mailer();
        $email = $mailer->emails['WC_Email_PawaPay_Payment_Success'];

        if ($email->is_enabled()) {
            $email->trigger($order_id, $order);
        }
    }

    public function trigger_payment_failed($order_id, $order)
    {
        $mailer = WC()->mailer();
        $email = $mailer->emails['WC_Email_PawaPay_Payment_Failed'];

        if ($email->is_enabled()) {
            $email->trigger($order_id, $order);
        }
    }

    public function trigger_refund_processed($order_id, $order, $refund_amount = '')
    {
        $mailer = WC()->mailer();
        $email = $mailer->emails['WC_Email_PawaPay_Refund_Processed'];

        if ($email->is_enabled()) {
            $email->trigger($order_id, $order, $refund_amount);
        }
    }

    public function locate_template($template, $template_name, $template_path)
    {
        // Vérifier si c'est un template PawaPay
        if (strpos($template_name, 'pawapay-') === 0) {
            $plugin_template_path = WC_PAWAPAY_PLUGIN_DIR . 'templates/' . $template_name;

            // Vérifier si le template existe dans le plugin
            if (file_exists($plugin_template_path)) {
                return $plugin_template_path;
            }
        }

        return $template;
    }
}

new WC_PawaPay_Emails();
