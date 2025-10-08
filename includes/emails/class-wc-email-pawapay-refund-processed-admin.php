<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_PawaPay_Refund_Processed_Admin extends WC_Email
{

    public function __construct()
    {
        $this->id = 'pawapay_refund_processed_admin';
        $this->title = __('PawaPay - Remboursement effectué (Admin)', 'wc-pawapay');
        $this->description = __('Email envoyé à l\'administrateur lorsqu\'un remboursement PawaPay est traité.', 'wc-pawapay');
        $this->customer_email = false;

        $this->placeholders = array(
            '{order_date}' => '',
            '{order_number}' => '',
            '{refund_amount}' => '',
            '{refund_reason}' => '',
        );

        $this->template_html = 'emails/admin/pawapay-refund-processed.php';
        $this->template_plain = 'emails/plain/admin/pawapay-refund-processed.php';
        $this->template_base = WC_PAWAPAY_PLUGIN_DIR . 'templates/';

        parent::__construct();

        $this->recipient = $this->get_option('recipient', get_option('admin_email'));
    }

    public function get_default_subject()
    {
        return __('[PawaPay] Remboursement effectué - Commande #{order_number}', 'wc-pawapay');
    }

    public function get_default_heading()
    {
        return __('Remboursement PawaPay effectué', 'wc-pawapay');
    }

    public function trigger($order_id, $order = false, $refund_amount = '', $refund_reason = '')
    {
        $this->setup_locale();

        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        if (!is_a($order, 'WC_Order')) {
            error_log('PawaPay Refund Email Error: Order not found for ID: ' . $order_id);
            $this->restore_locale();
            return;
        }

        $this->object = $order;
        $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
        $this->placeholders['{order_number}'] = $this->object->get_order_number();
        $this->placeholders['{refund_amount}'] = $refund_amount ? wc_price($refund_amount) : wc_price($order->get_total());
        $this->placeholders['{refund_reason}'] = $refund_reason ?: __('Non spécifié', 'wc-pawapay');

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

        $this->restore_locale();
    }

    public function get_content_html()
    {
        return wc_get_template_html(
            $this->template_html,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
                'plain_text' => false,
                'email' => $this,
                'refund_amount' => $this->placeholders['{refund_amount}'],
                'refund_reason' => $this->placeholders['{refund_reason}'],
            ),
            '',
            $this->template_base
        );
    }

    public function get_content_plain()
    {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => true,
                'plain_text' => true,
                'email' => $this,
                'refund_amount' => $this->placeholders['{refund_amount}'],
                'refund_reason' => $this->placeholders['{refund_reason}'],
            ),
            '',
            $this->template_base
        );
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Activer/Désactiver', 'wc-pawapay'),
                'type' => 'checkbox',
                'label' => __('Activer cet email', 'wc-pawapay'),
                'default' => 'yes',
            ),
            'recipient' => array(
                'title' => __('Destinataire', 'wc-pawapay'),
                'type' => 'text',
                'description' => __('Adresses email des destinataires, séparées par des virgules. Par défaut: ', 'wc-pawapay') . get_option('admin_email'),
                'placeholder' => '',
                'default' => '',
                'desc_tip' => true,
            ),
            'subject' => array(
                'title' => __('Sujet', 'wc-pawapay'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('Sujet de l\'email. Les placeholders disponibles: {site_title}, {order_date}, {order_number}, {refund_amount}, {refund_reason}', 'wc-pawapay'),
                'placeholder' => $this->get_default_subject(),
                'default' => '',
            ),
            'heading' => array(
                'title' => __('En-tête', 'wc-pawapay'),
                'type' => 'text',
                'desc_tip' => true,
                'description' => __('En-tête principal de l\'email. Les placeholders disponibles: {site_title}, {order_date}, {order_number}, {refund_amount}, {refund_reason}', 'wc-pawapay'),
                'placeholder' => $this->get_default_heading(),
                'default' => '',
            ),
            'email_type' => array(
                'title' => __('Type d\'email', 'wc-pawapay'),
                'type' => 'select',
                'description' => __('Choisir le format de l\'email à envoyer.', 'wc-pawapay'),
                'default' => 'html',
                'class' => 'email_type',
                'options' => array(
                    'html' => __('HTML', 'wc-pawapay'),
                    'plain' => __('Plain text', 'wc-pawapay'),
                ),
            ),
        );
    }
}
