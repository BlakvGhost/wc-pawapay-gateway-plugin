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
        add_action('pawapay_payment_failed', array($this, 'trigger_payment_failed'), 10, 3);
        add_action('pawapay_refund_processed', array($this, 'trigger_refund_processed'), 10, 4);

        add_filter('woocommerce_locate_template', array($this, 'locate_template'), 10, 3);
    }

    public function register_emails($email_classes)
    {
        // Emails clients
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-success.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-failed.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-refund-processed.php';

        // Emails admin
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-success-admin.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-payment-failed-admin.php';
        require_once WC_PAWAPAY_PLUGIN_DIR . 'includes/emails/class-wc-email-pawapay-refund-processed-admin.php';

        // Clients
        $email_classes['WC_Email_PawaPay_Payment_Success'] = new WC_Email_PawaPay_Payment_Success();
        $email_classes['WC_Email_PawaPay_Payment_Failed'] = new WC_Email_PawaPay_Payment_Failed();
        $email_classes['WC_Email_PawaPay_Refund_Processed'] = new WC_Email_PawaPay_Refund_Processed();

        // Admin
        $email_classes['WC_Email_PawaPay_Payment_Success_Admin'] = new WC_Email_PawaPay_Payment_Success_Admin();
        $email_classes['WC_Email_PawaPay_Payment_Failed_Admin'] = new WC_Email_PawaPay_Payment_Failed_Admin();
        $email_classes['WC_Email_PawaPay_Refund_Processed_Admin'] = new WC_Email_PawaPay_Refund_Processed_Admin();

        return $email_classes;
    }

    public function trigger_payment_success($order_id, $order)
    {
        $mailer = WC()->mailer();

        // Email client
        $customer_email = $mailer->emails['WC_Email_PawaPay_Payment_Success'];
        if ($customer_email->is_enabled()) {
            $customer_email->trigger($order_id, $order);
        }

        // Email admin
        $admin_email = $mailer->emails['WC_Email_PawaPay_Payment_Success_Admin'];
        if ($admin_email->is_enabled()) {
            $admin_email->trigger($order_id, $order);
        }
    }

    public function trigger_payment_failed($order_id, $order, $failure_reason = '')
    {
        $mailer = WC()->mailer();

        // Email client
        $customer_email = $mailer->emails['WC_Email_PawaPay_Payment_Failed'];
        if ($customer_email->is_enabled()) {
            $customer_email->trigger($order_id, $order);
        }

        // Email admin
        $admin_email = $mailer->emails['WC_Email_PawaPay_Payment_Failed_Admin'];
        if ($admin_email->is_enabled()) {
            $admin_email->trigger($order_id, $order, $failure_reason);
        }
    }

    public function trigger_refund_processed($order_id, $order = false, $refund_amount = '', $refund_reason = '')
    {
        if (!function_exists('WC') || !is_object(WC()->mailer)) {
            error_log('PawaPay Emails Error: WooCommerce mailer not available');
            return;
        }

        $mailer = WC()->mailer();

        if (isset($mailer->emails['WC_Email_PawaPay_Refund_Processed_Admin'])) {
            $admin_email = $mailer->emails['WC_Email_PawaPay_Refund_Processed_Admin'];
            if ($admin_email->is_enabled()) {
                if (!$order && $order_id) {
                    $order = wc_get_order($order_id);
                }

                if (is_a($order, 'WC_Order')) {
                    $admin_email->trigger($order_id, $order, $refund_amount, $refund_reason);
                } else {
                    error_log('PawaPay Refund Email Error: Invalid order for ID: ' . $order_id);
                }
            }
        }

        if (isset($mailer->emails['WC_Email_PawaPay_Refund_Processed'])) {
            $customer_email = $mailer->emails['WC_Email_PawaPay_Refund_Processed'];
            if ($customer_email->is_enabled()) {
                if (!$order && $order_id) {
                    $order = wc_get_order($order_id);
                }

                if (is_a($order, 'WC_Order')) {
                    $customer_email->trigger($order_id, $order, $refund_amount);
                }
            }
        }
    }

    public function locate_template($template, $template_name, $template_path)
    {
        if (strpos($template_name, 'pawapay-') === 0) {
            $plugin_template_path = WC_PAWAPAY_PLUGIN_DIR . 'templates/' . $template_name;

            if (file_exists($plugin_template_path)) {
                return $plugin_template_path;
            }
        }

        return $template;
    }
}

new WC_PawaPay_Emails();
