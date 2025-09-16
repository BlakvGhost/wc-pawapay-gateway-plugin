<?php
if (!defined('ABSPATH')) {
    exit;
}

class PawaPay_Api
{
    private $base_url;
    private $token;

    public function __construct($env, $token)
    {
        $this->base_url = ($env === 'production')
            ? 'https://api.pawapay.io/v2'
            : 'https://api.sandbox.pawapay.io/v2';
        $this->token = $token;
    }

    private function headers()
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type'  => 'application/json',
        ];
    }

    public function create_payment_page($payload)
    {
        $url = $this->base_url . '/paymentpage';

        $args = [
            'headers' => $this->headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ];

        $response = wp_remote_post($url, $args);

        $logger = wc_get_logger();
        if (is_wp_error($response)) {
            $logger->error('PawaPay API Error (Payment Page): ' . $response->get_error_message(), ['source' => 'pawapay']);
        } else {
            $logger->info('PawaPay Payment Page Request: ' . wp_json_encode($payload), ['source' => 'pawapay']);
            $logger->info('PawaPay Payment Page Response: ' . wp_remote_retrieve_body($response), ['source' => 'pawapay']);
        }

        return $response;
    }
}
