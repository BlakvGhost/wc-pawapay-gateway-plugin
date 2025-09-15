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
    public $supported_countries = ['BJ', 'BF', 'CI', 'CM', 'ML', 'NE', 'SN', 'TG'];

    public function __construct()
    {
        $this->id = 'pawapay';
        $this->icon = '';
        $this->method_title = 'PawaPay';
        $this->method_description = 'Paiement mobile via PawaPay';
        $this->has_fields = true;

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
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
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
                'default'     => 'Payer avec Mobile Money via PawaPay.',
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
                'description' => 'Nom affiché sur le relevé bancaire du client (max 22 caractères).',
            ]
        ];
    }

    public function enqueue_scripts()
    {
        if (is_checkout()) {
            wp_enqueue_script('pawapay-checkout', plugin_dir_url(__FILE__) . '../assets/js/pawapay-checkout.js', ['jquery'], '1.0.0', true);
            wp_enqueue_style('pawapay-checkout', plugin_dir_url(__FILE__) . '../assets/css/pawapay-checkout.css', [], '1.0.0');
        }
    }

    public function payment_fields()
    {
        echo '<div class="pawapay-payment-fields">';
        echo '<p>' . esc_html($this->description) . '</p>';

        // Champ pays
        $billing_country = WC()->customer->get_billing_country();
        $default_country = !empty($billing_country) && in_array($billing_country, $this->supported_countries) ? $billing_country : 'BJ';

        echo '<p class="form-row form-row-wide">';
        echo '<label for="pawapay_country">' . __('Pays', 'woocommerce') . ' <span class="required">*</span></label>';
        echo '<select name="pawapay_country" id="pawapay_country" class="pawapay-country-select">';
        foreach ($this->supported_countries as $country_code) {
            $selected = $country_code === $default_country ? 'selected' : '';
            $country_name = $this->get_country_name($country_code);
            echo '<option value="' . esc_attr($country_code) . '" ' . $selected . '>' . esc_html($country_name) . '</option>';
        }
        echo '</select>';
        echo '</p>';

        // Champ opérateur (sera rempli dynamiquement via JS)
        echo '<p class="form-row form-row-wide">';
        echo '<label for="pawapay_provider">' . __('Opérateur', 'woocommerce') . ' <span class="required">*</span></label>';
        echo '<select name="pawapay_provider" id="pawapay_provider" class="pawapay-provider-select">';
        echo '<option value="">' . __('Chargement des opérateurs...', 'woocommerce') . '</option>';
        echo '</select>';
        echo '</p>';

        // Champ numéro de téléphone
        $billing_phone = WC()->customer->get_billing_phone();
        echo '<p class="form-row form-row-wide">';
        echo '<label for="pawapay_phone">' . __('Numéro Mobile Money', 'woocommerce') . ' <span class="required">*</span></label>';
        echo '<input type="tel" name="pawapay_phone" id="pawapay_phone" value="' . esc_attr($billing_phone) . '" placeholder="' . __('Ex: 22961234567', 'woocommerce') . '" />';
        echo '</p>';

        echo '</div>';
    }

    public function validate_fields()
    {
        if (empty($_POST['pawapay_country'])) {
            wc_add_notice(__('Veuillez sélectionner votre pays.', 'woocommerce'), 'error');
            return false;
        }

        if (empty($_POST['pawapay_provider'])) {
            wc_add_notice(__('Veuillez sélectionner votre opérateur mobile.', 'woocommerce'), 'error');
            return false;
        }

        if (empty($_POST['pawapay_phone'])) {
            wc_add_notice(__('Veuillez saisir votre numéro de téléphone.', 'woocommerce'), 'error');
            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $country = sanitize_text_field($_POST['pawapay_country']);
        $provider = sanitize_text_field($_POST['pawapay_provider']);
        $phone = sanitize_text_field($_POST['pawapay_phone']);

        // Nettoyer le numéro de téléphone
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $payload = [
            'depositId' => uniqid('wc_', true),
            'customerTimestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'statementDescription' => substr($this->merchant_name, 0, 22),
            'country' => $country,
            'amount' => '',
            'currency' => '',
            'customerPhoneNumber' => $phone,
            'provider' => $provider,
        ];

        $amount = wc_format_decimal($order->get_total(), 2);
        $currency = $order->get_currency();

        $converted = $this->convert_amount_if_needed($amount, $currency, $country);
        $payload['amount'] = $converted['amount'];
        $payload['currency'] = $converted['currency'];

        $resp = $this->client->initiate_deposit($payload);

        if (is_wp_error($resp)) {
            wc_add_notice(__('Erreur de communication avec PawaPay: ', 'woocommerce') . $resp->get_error_message(), 'error');
            return;
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if ($code !== 200 && $code !== 201) {
            $error_message = __('Paiement rejeté par PawaPay', 'woocommerce');

            try {
                $response_data = json_decode($body, true);
                if (isset($response_data['message'])) {
                    $error_message .= ': ' . $response_data['message'];
                }
            } catch (Exception $e) {
                // Ignorer si le parsing échoue
            }

            wc_add_notice($error_message, 'error');
            return;
        }

        // Enregistrer les métadonnées de la transaction
        $order->update_meta_data('_pawapay_deposit_id', $payload['depositId']);
        $order->update_meta_data('_pawapay_country', $country);
        $order->update_meta_data('_pawapay_provider', $provider);
        $order->update_meta_data('_pawapay_phone', $phone);

        $order->update_status('on-hold', __('En attente de confirmation PawaPay.', 'woocommerce'));
        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }

    private function convert_amount_if_needed($amount, $currency, $country)
    {
        $logger = wc_get_logger();
        $context = ['source' => 'pawapay'];

        // Récupérer les informations sur les providers disponibles
        $resp = $this->client->provider_availability($country);
        if (is_wp_error($resp)) {
            $logger->error('Erreur lors de la récupération des providers: ' . $resp->get_error_message(), $context);
            return [
                'amount'   => wc_format_decimal($amount, wc_get_price_decimals()),
                'currency' => $currency,
            ];
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data) || !isset($data[0]['providers']) || empty($data[0]['providers'])) {
            $logger->error('Aucun provider disponible pour le pays: ' . $country, $context);
            return [
                'amount'   => wc_format_decimal($amount, wc_get_price_decimals()),
                'currency' => $currency,
            ];
        }

        // Utiliser le premier provider comme référence
        $providerCurrency = strtoupper($data[0]['providers'][0]['currency']);
        $providerDecimals = intval($data[0]['providers'][0]['decimals']);

        $sourceCurrency = strtoupper($currency);

        // Conversion uniquement si nécessaire (EUR/USD vers XOF/XAF)
        if (in_array($sourceCurrency, ['EUR', 'USD']) && in_array($providerCurrency, ['XOF', 'XAF'])) {
            $url = add_query_arg([
                'from'   => $sourceCurrency,
                'to'     => $providerCurrency,
                'amount' => (float) $amount,
            ], 'https://api.exchangerate.host/convert');

            $resp = wp_remote_get($url, ['timeout' => 15]);
            if (!is_wp_error($resp)) {
                $body = json_decode(wp_remote_retrieve_body($resp), true);
                if (!empty($body['success']) && isset($body['result'])) {
                    $converted = number_format((float)$body['result'], $providerDecimals, '.', '');
                    $logger->info("Conversion {$amount} {$sourceCurrency} => {$converted} {$providerCurrency}", $context);
                    return [
                        'amount'   => $converted,
                        'currency' => $providerCurrency,
                    ];
                }
            }
            $logger->warning('Échec de la conversion, utilisation du montant original', $context);
        }

        return [
            'amount'   => wc_format_decimal($amount, wc_get_price_decimals()),
            'currency' => $currency,
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

        // Vérifier la devise
        $currency = get_woocommerce_currency();
        if (!in_array($currency, ['XOF', 'XAF', 'EUR', 'USD'])) {
            return false;
        }

        // Vérifier le pays de facturation
        $billing_country = WC()->customer ? WC()->customer->get_billing_country() : null;
        if ($billing_country && !in_array($billing_country, $this->supported_countries)) {
            return false;
        }

        return true;
    }

    private function get_country_name($country_code)
    {
        $countries = [
            'BJ' => __('Bénin', 'woocommerce'),
            'BF' => __('Burkina Faso', 'woocommerce'),
            'CI' => __('Côte d\'Ivoire', 'woocommerce'),
            'CM' => __('Cameroun', 'woocommerce'),
            'ML' => __('Mali', 'woocommerce'),
            'NE' => __('Niger', 'woocommerce'),
            'SN' => __('Sénégal', 'woocommerce'),
            'TG' => __('Togo', 'woocommerce'),
        ];

        return isset($countries[$country_code]) ? $countries[$country_code] : $country_code;
    }
}
