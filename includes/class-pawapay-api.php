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

    public function initiate_deposit($payload)
    {
        $url = $this->base_url . '/deposits';

        $args = [
            'headers' => $this->headers(),
            'body'    => wp_json_encode($payload),
            'timeout' => 30,
        ];

        $response = wp_remote_post($url, $args);

        // Logging pour le débogage
        if (is_wp_error($response)) {
            $logger = wc_get_logger();
            $logger->error('PawaPay API Error: ' . $response->get_error_message(), ['source' => 'pawapay']);
        } else {
            $logger = wc_get_logger();
            $logger->info('PawaPay Deposit Request: ' . wp_json_encode($payload), ['source' => 'pawapay']);
            $logger->info('PawaPay Deposit Response: ' . wp_remote_retrieve_body($response), ['source' => 'pawapay']);
        }

        return $response;
    }

    public function provider_availability($country)
    {
        $url = $this->base_url . '/provider-availability?country=' . urlencode($country);

        $args = [
            'headers' => $this->headers(),
            'timeout' => 30,
        ];

        $response = wp_remote_get($url, $args);

        // Logging pour le débogage
        if (is_wp_error($response)) {
            $logger = wc_get_logger();
            $logger->error('PawaPay Provider Availability Error: ' . $response->get_error_message(), ['source' => 'pawapay']);
        }

        return $response;
    }
}
