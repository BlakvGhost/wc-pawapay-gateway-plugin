<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_PawaPay extends WC_Payment_Gateway
{
    public $api_token;
    public $environment;
    public $client;
    public $merchant_name;
    public $supported_countries = ['BJ', 'BF', 'CI', 'CM', 'ML', 'NE', 'SN', 'TG', 'GH', 'NG', 'ZM', 'EU', 'US', 'FR'];

    public function __construct()
    {
        $this->id = 'pawapay';
        $this->icon = '';
        $this->method_title = 'PawaPay';
        $this->method_description = 'Paiement mobile via la page de paiement PawaPay.';

        $this->has_fields = false; // C'est la ligne importante pour les Blocks

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_token = $this->get_option('api_token');
        $this->environment = $this->get_option('environment', 'sandbox');
        $this->merchant_name = $this->get_option('name', 'Votre Entreprise');

        // Charger la classe de l'API ici pour qu'elle soit toujours disponible
        require_once __DIR__ . '/class-pawapay-api.php';
        $this->client = new PawaPay_Api($this->environment, $this->api_token);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_ajax_pawapay_convert_currency', array($this, 'ajax_convert_currency'));
        add_action('wp_ajax_nopriv_pawapay_convert_currency', array($this, 'ajax_convert_currency'));
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Activer/Désactiver',
                'type'    => 'checkbox',
                'label'   => 'Activer PawaPay',
                'default' => 'no'
            ],
            'title' => [
                'title'       => 'Titre',
                'type'        => 'text',
                'default'     => 'Mobile Money (PawaPay)',
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'default'     => 'Vous serez redirigé vers une page sécurisée pour finaliser votre paiement.',
            ],
            'api_token' => [
                'title'       => 'API Token',
                'type'        => 'password',
            ],
            'environment' => [
                'title'       => 'Environnement',
                'type'        => 'select',
                'options'     => [
                    'sandbox'    => 'Sandbox',
                    'production' => 'Production'
                ],
                'default' => 'sandbox',
            ],
            'name' => [
                'title'       => 'Nom du marchand',
                'type'        => 'text',
                'default'     => 'Votre Entreprise',
                'description' => 'Nom affiché sur la page de paiement et le relevé du client (max 22 caractères).',
            ]
        ];
    }

    public function get_active_configuration_countries()
    {
        $url = $this->client->get_base_url() . '/active-conf';
        $args = [
            'headers' => $this->client->get_headers(),
            'timeout' => 30,
        ];
        $response = wp_remote_get($url, $args);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('pawapay_api_error', 'Impossible de récupérer la configuration PawaPay.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $country_code = isset($_POST['pawapay_country']) ? sanitize_text_field($_POST['pawapay_country']) : null;
        $currency_code = isset($_POST['pawapay_currency']) ? sanitize_text_field($_POST['pawapay_currency']) : null;
        var_dump($country_code, $currency_code);
        exit();
        error_log('PawaPay - Pays reçu: ' . $country_code);
        error_log('PawaPay - Devise reçue: ' . $currency_code);

        if (empty($country_code) || empty($currency_code)) {
            wc_add_notice(__('Veuillez sélectionner un pays et une devise.', 'woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        $order_total = $order->get_total();
        $converted_amount = $this->convert_currency(get_woocommerce_currency(), $currency_code, $order_total);
        if (is_wp_error($converted_amount)) {
            wc_add_notice(__('Erreur de conversion de devise.', 'woocommerce'), 'error');
            return ['result' => 'failure'];
        }

        $payment_page_id = (string) $order->get_id() . '_' . time();

        $payload = [
            "paymentPageId" => $payment_page_id,
            "amountDetails" => [
                "amount" => (float) $converted_amount,
                "currency" => $currency_code
            ],
            "country" => $country_code,
            "reason" => "Paiement pour la commande #" . $order->get_id(),
            "returnUrl" => $this->get_return_url($order),
        ];

        $resp = $this->client->create_payment_page($payload);

        if (is_wp_error($resp)) {
            wc_add_notice(__('Erreur de communication avec PawaPay: ', 'woocommerce') . $resp->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code !== 201 || empty($data['redirectUrl'])) {
            $error_message = __('Le paiement a été rejeté par PawaPay.', 'woocommerce');
            if (!empty($data['message'])) {
                $error_message .= ' Raison: ' . esc_html($data['message']);
            }
            wc_add_notice($error_message, 'error');
            return ['result' => 'failure'];
        }

        $order->update_meta_data('_pawapay_payment_page_id', $payment_page_id);
        $order->update_status('pending', __('En attente de paiement sur la page PawaPay.', 'woocommerce'));
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => $data['redirectUrl']
        ];
    }

    public function convert_currency($from, $to, $amount)
    {
        $api_key = '9fdc3cd76b46c0adc2c34523';
        $url = "https://api.exchangerate-api.com/v4/latest/{$from}";
        $response = wp_remote_get($url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('conversion_error', 'Erreur de conversion de devise.');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['rates'][$to])) {
            return new WP_Error('conversion_error', 'Devise cible non disponible.');
        }

        $rate = $data['rates'][$to];
        $converted_amount = $amount * $rate;
        return ceil($converted_amount);
    }

    // Ajoutez cette méthode à votre classe WC_Gateway_PawaPay
    public function ajax_convert_currency()
    {
        // Vérifier la nonce de sécurité
        if (!check_ajax_referer('pawapay_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce de sécurité invalide.');
            wp_die();
        }

        $from = sanitize_text_field($_POST['from']);
        $to = sanitize_text_field($_POST['to']);
        $amount = floatval($_POST['amount']);

        $converted = $this->convert_currency($from, $to, $amount);

        if (is_wp_error($converted)) {
            wp_send_json_error($converted->get_error_message());
        } else {
            wp_send_json_success($converted);
        }

        wp_die(); // Toujours terminer avec wp_die() pour les requêtes AJAX
    }
}
