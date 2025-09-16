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
    // La liste des pays supportés est toujours utile pour la visibilité de la passerelle
    public $supported_countries = ['BJ', 'BF', 'CI', 'CM', 'ML', 'NE', 'SN', 'TG', 'GH', 'NG', 'ZM', 'EU', 'US', 'FR'];

    public function __construct()
    {
        $this->id = 'pawapay';
        $this->icon = ''; // Vous pouvez ajouter une icône ici si vous le souhaitez
        $this->method_title = 'PawaPay';
        $this->method_description = 'Paiement mobile via la page de paiement PawaPay.';

        // CHANGED: has_fields passe à false car nous n'affichons plus de champs
        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_token = $this->get_option('api_token');
        $this->environment = $this->get_option('environment', 'sandbox');
        $this->merchant_name = $this->get_option('name', 'Votre Entreprise');

        $this->client = new PawaPay_Api($this->environment, $this->api_token);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // REMOVED: add_action('wp_enqueue_scripts'...) n'est plus nécessaire
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

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Le paymentPageId doit être unique. L'ID de la commande est parfait pour ça.
        $payment_page_id = (string) $order->get_id() . '_' . time();

        $payload = [
            "depositId" => "695776cf-73ba-42ff-b9cb-2b9acc008e22", // A remplacer par un depositId valide
            "returnUrl" => $this->get_return_url($order),
            "amount" => (string) $order->get_total(),
            "country" => "BJ",
            "reason" => "Demo payment"
        ];
        // var_dump($payload);
        // exit();

        $resp = $this->client->create_payment_page($payload);

        if (is_wp_error($resp)) {
            wc_add_notice(__('Erreur de communication avec PawaPay: ', 'woocommerce') . $resp->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);
        var_dump($data, $code, "Erreur ici");
        exit();
        if ($code !== 201 || empty($data['redirectUrl'])) {
            $error_message = __('Le paiement a été rejeté par PawaPay.', 'woocommerce');
            if (!empty($data['message'])) {
                $error_message .= ' Raison: ' . esc_html($data['message']);
            }
            wc_add_notice($error_message, 'error');
            wc_add_notice($data, 'error');
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

    public function is_available()
    {
        if ($this->enabled !== 'yes') {
            return false;
        }

        if (empty($this->api_token)) {
            return false;
        }

        $currency = get_woocommerce_currency();
        if (!in_array($currency, ['XOF', 'XAF', 'EUR', 'USD', 'GHS', 'NGN', 'ZMW'])) { // Liste indicative à vérifier
            return false;
        }

        return true;
    }
}
