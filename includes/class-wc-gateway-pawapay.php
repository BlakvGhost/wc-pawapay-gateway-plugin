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
    public $exchange_api_key;
    public $supported_countries = ['BJ', 'BF', 'CI', 'CM', 'ML', 'NE', 'SN', 'TG', 'GH', 'NG', 'ZM', 'EU', 'US', 'FR'];

    public function __construct()
    {
        $this->id = 'pawapay';
        $this->icon = plugins_url('pawapay.png', WC_PAWAPAY_PLUGIN_FILE);
        $this->method_title = 'PawaPay';
        $this->method_description = 'Acceptez les paiements Mobile Money via la passerelle sécurisée PawaPay. Compatible multi-pays et multi-opérateurs.';

        $this->has_fields = true;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_token = $this->get_option('api_token');
        $this->environment = $this->get_option('environment', 'sandbox');
        $this->merchant_name = $this->get_option('name', 'Votre Entreprise');
        $this->exchange_api_key = $this->get_option('exchange_api_key');

        require_once __DIR__ . '/class-pawapay-api.php';
        $this->client = new PawaPay_Api($this->environment, $this->api_token);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_pawapay_convert_currency', [$this, 'ajax_convert_currency']);
        add_action('wp_ajax_nopriv_pawapay_convert_currency', [$this, 'ajax_convert_currency']);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Activer/Désactiver', 'wc-pawapay'),
                'type'    => 'checkbox',
                'label'   => __('Activer PawaPay', 'wc-pawapay'),
                'default' => 'no'
            ],
            'title' => [
                'title'       => __('Titre', 'wc-pawapay'),
                'type'        => 'text',
                'default'     => __('Mobile Money (PawaPay)', 'wc-pawapay'),
            ],
            'description' => [
                'title'       => __('Description', 'wc-pawapay'),
                'type'        => 'textarea',
                'default'     => __('Vous serez redirigé vers une page sécurisée pour finaliser votre paiement.', 'wc-pawapay'),
            ],
            'api_token' => [
                'title'       => __('API Token', 'wc-pawapay'),
                'type'        => 'password',
            ],
            'environment' => [
                'title'       => __('Environnement', 'wc-pawapay'),
                'type'        => 'select',
                'options'     => [
                    'sandbox'    => __('Sandbox', 'wc-pawapay'),
                    'production' => __('Production', 'wc-pawapay')
                ],
                'default' => 'sandbox',
            ],
            'language' => [
                'title'       => __('Langue de la page de paiement', 'wc-pawapay'),
                'type'        => 'select',
                'options'     => [
                    'fr'    => __('Français', 'wc-pawapay'),
                    'en' => __('Anglais', 'wc-pawapay')
                ],
                'default' => 'fr',
            ],
            'exchange_api_key' => [
                'title'       => __('Clé API ExchangeRate', 'wc-pawapay'),
                'type'        => 'password',
                'description' => __('Entrez votre clé API ExchangeRate. Laissez vide pour utiliser la version gratuite (non garantie en production).', 'wc-pawapay'),
                'default'     => '',
                'desc_tip'    => true,
            ],
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

    public function enqueue_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'wc-pawapay-checkout',
            plugin_dir_url(__FILE__) . '../assets/js/checkout.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'wc-pawapay-checkout-style',
            plugin_dir_url(__FILE__) . '../assets/css/style.css',
            [],
            '1.0.0'
        );

        $config = $this->get_active_configuration_countries();
        $countries = is_wp_error($config) ? [] : ($config['countries'] ?? []);

        wp_localize_script('wc-pawapay-checkout', 'pawapayData', [
            'countries' => $countries,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pawapay_nonce'),
            'order_total' => WC()->cart->get_total('edit'),
            'current_currency' => get_woocommerce_currency(),
            'i18n' => [
                'select_currency' => __('Sélectionnez une devise', 'wc-pawapay'),
            ],
        ]);
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        $config = $this->get_active_configuration_countries();
        if (is_wp_error($config)) {
            echo '<p>' . esc_html__('Erreur: Impossible de charger les pays supportés.', 'wc-pawapay') . '</p>';
            return;
        }

        $countries = $config['countries'] ?? [];
        $country_options = [];
        foreach ($countries as $country) {
            $country_code = $country['country'] ?? '';
            $display_name = $country['displayName']['fr'] ?? $country['displayName']['en'] ?? $country_code;
            if (!empty($country_code)) {
                $country_options[$country_code] = $display_name;
            }
        }

?>
        <p class="form-row form-row-wide">
            <label for="pawapay_country"><?php esc_html_e('Pays', 'wc-pawapay'); ?> <span class="required">*</span></label>
            <select id="pawapay_country" name="wc-pawapay-new-payment-method[pawapay_country]" class="wc-pawapay-country-select" required>
                <option value=""><?php esc_html_e('Sélectionnez un pays', 'wc-pawapay'); ?></option>
                <?php foreach ($country_options as $code => $name) : ?>
                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="form-row form-row-wide">
            <label for="pawapay_currency"><?php esc_html_e('Devise', 'wc-pawapay'); ?> <span class="required">*</span></label>
            <select id="pawapay_currency" name="wc-pawapay-new-payment-method[pawapay_currency]" class="wc-pawapay-currency-select" required>
                <option value=""><?php esc_html_e('Sélectionnez une devise', 'wc-pawapay'); ?></option>
            </select>
        </p>
        <div class="pawapay-converted-amount" style="display: none;">
            <p><?php esc_html_e('Le montant total de votre commande de ', 'wc-pawapay'); ?>
                <span class="pawapay-order-total"></span>
                <?php esc_html_e(' sera converti et payé en ', 'wc-pawapay'); ?>
                <strong class="pawapay-converted-total"></strong>
            </p>
        </div>
<?php
    }

    public function generateUuidV4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $country_code = isset($_POST['wc-pawapay-new-payment-method']['pawapay_country']) ? sanitize_text_field($_POST['wc-pawapay-new-payment-method']['pawapay_country']) : null;
        $currency_code = isset($_POST['wc-pawapay-new-payment-method']['pawapay_currency']) ? sanitize_text_field($_POST['wc-pawapay-new-payment-method']['pawapay_currency']) : null;

        if (empty($country_code) || empty($currency_code)) {
            $country_code = $order->get_meta('pawapay_country');
            $currency_code = $order->get_meta('pawapay_currency');
        }

        if (empty($country_code) || empty($currency_code)) {
            wc_add_notice(__('Veuillez sélectionner un pays et une devise.', 'wc-pawapay'), 'error');
            return ['result' => 'failure'];
        }

        $order_total = $order->get_total();
        $converted_amount = $this->convert_currency(get_woocommerce_currency(), $currency_code, $order_total);
        if (is_wp_error($converted_amount)) {
            wc_add_notice(__('Erreur de conversion de devise.', 'wc-pawapay'), 'error');
            return ['result' => 'failure'];
        }

        $items = $order->get_items();
        $product_names = [];

        foreach ($items as $item) {
            $product_names[] = $item->get_name();
        }

        if (count($product_names) === 1) {
            $reason = sprintf(__('Paiement pour %s', 'wc-pawapay'), $product_names[0]);
        } else {
            $reason = sprintf(
                __('Paiement pour %s et %d autres articles', 'wc-pawapay'),
                $product_names[0],
                count($product_names) - 1
            );
        }

        $payment_page_id = $this->generateUuidV4();
        $payload = [
            'depositId' => $payment_page_id,
            'amountDetails' => [
                'amount' => (string) $converted_amount,
                'currency' => $currency_code,
            ],
            'country' => $country_code,
            'reason' => $reason,
            'returnUrl' => add_query_arg([
                'order_id'   => $order->get_id(),
                'deposit_id' => $payment_page_id,
            ], rest_url('pawapay/v1/return')),
        ];

        $order->update_meta_data('pawapay_country', $country_code);
        $order->update_meta_data('pawapay_currency', $currency_code);
        $order->update_meta_data('pawapay_converted_amount', $converted_amount);
        $order->update_meta_data('pawapay_deposit_id', $payment_page_id);
        $order->save();

        $resp = $this->client->create_payment_page($payload);
        if (is_wp_error($resp)) {
            wc_add_notice(__('Erreur de communication avec PawaPay: ', 'wc-pawapay') . $resp->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $data = json_decode($body, true);

        if ($code !== 201 || empty($data['redirectUrl'])) {
            $error_message = __('Le paiement a été rejeté par PawaPay.', 'wc-pawapay');
            if (!empty($data['message'])) {
                $error_message .= ' ' . __('Raison:', 'wc-pawapay') . ' ' . esc_html($data['message']);
            }
            wc_add_notice($error_message, 'error');
            return ['result' => 'failure'];
        }

        $order->update_meta_data('_pawapay_payment_page_id', $payment_page_id);
        $order->update_status('pending', __('En attente de paiement sur la page PawaPay.', 'wc-pawapay'));
        $order->save();

        return [
            'result'   => 'success',
            'redirect' => $data['redirectUrl'],
        ];
    }

    public function convert_currency($from, $to, $amount)
    {
        $cache_key   = '_fdpawapay_exchange_rate_' . $from . '_' . $to;
        $cached_rate = get_transient($cache_key);

        if ($cached_rate !== false) {
            $rate = $cached_rate;
        } else {
            $api_key  = isset($this->exchange_api_key) ? trim($this->exchange_api_key) : '';

            if (!empty($api_key)) {
                // Endpoint payant (clé API définie)
                $url = "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$from}";
            } else {
                // Endpoint gratuit (fallback)
                $url = "https://api.exchangerate-api.com/v4/latest/{$from}";
            }

            $response = wp_remote_get($url);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return new WP_Error('conversion_error', __('Erreur de conversion de devise.', 'wc-pawapay'));
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($data['rates'][$to]) && !isset($data['conversion_rates'][$to])) {
                return new WP_Error('conversion_error', __('Devise cible non disponible.', 'wc-pawapay'));
            }

            $rate = $data['rates'][$to] ?? $data['conversion_rates'][$to];
            set_transient($cache_key, $rate, 6 * HOUR_IN_SECONDS);
        }

        $converted_amount = $amount * $rate;
        return ceil($converted_amount);
    }


    public function ajax_convert_currency()
    {
        if (!check_ajax_referer('pawapay_nonce', 'nonce', false)) {
            wp_send_json_error(__('Nonce de sécurité invalide.', 'wc-pawapay'));
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

        wp_die();
    }
}
?>