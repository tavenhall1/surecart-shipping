<?php
/**
 * Shippo API Client.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Core;

use SureCartShippo\Diagnostics\Logger;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shippo API client class.
 */
class ShippoClient
{
    /**
     * Shippo API base URL for live mode.
     *
     * @var string
     */
    const API_BASE_LIVE = 'https://api.goshippo.com';

    /**
     * Shippo API base URL for test mode.
     *
     * @var string
     */
    const API_BASE_TEST = 'https://api.goshippo.com';

    /**
     * API version.
     *
     * @var string
     */
    const API_VERSION = '2018-02-08';

    /**
     * API token.
     *
     * @var string
     */
    private $api_token;

    /**
     * API mode (test or live).
     *
     * @var string
     */
    private $mode;

    /**
     * Logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->mode = get_option('surecart_shippo_mode', 'test');
        $this->api_token = $this->get_api_token();
        $this->logger = surecart_shippo()->logger;
    }

    /**
     * Get decrypted API token.
     *
     * @return string
     */
    private function get_api_token()
    {
        $encrypted_token = get_option('surecart_shippo_api_token_' . $this->mode, '');

        if (empty($encrypted_token)) {
            return '';
        }

        return Encryption::decrypt($encrypted_token);
    }

    /**
     * Get API base URL.
     *
     * @return string
     */
    private function get_api_base()
    {
        return $this->mode === 'live' ? self::API_BASE_LIVE : self::API_BASE_TEST;
    }

    /**
     * Make an API request.
     *
     * @param string $endpoint API endpoint.
     * @param string $method HTTP method.
     * @param array  $data Request data.
     * @return array|WP_Error Response data or error.
     */
    private function request($endpoint, $method = 'GET', $data = [])
    {
        if (empty($this->api_token)) {
            $error = new \WP_Error(
                'no_api_token',
                __('Shippo API token is not configured.', 'surecart-shippo')
            );
            $this->logger->error('API request failed: No API token configured');
            return $error;
        }

        $url = $this->get_api_base() . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'ShippoToken ' . $this->api_token,
                'Content-Type' => 'application/json',
                'Shippo-API-Version' => self::API_VERSION,
            ],
            'timeout' => 30,
        ];

        if (!empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $this->logger->debug('Shippo API Request', [
            'url' => $url,
            'method' => $method,
            'data' => $data,
        ]);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->logger->error('Shippo API Request Failed', [
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        $this->logger->debug('Shippo API Response', [
            'status' => $status_code,
            'body' => $decoded,
        ]);

        if ($status_code >= 400) {
            $error_message = isset($decoded['detail']) ? $decoded['detail'] : __('Unknown API error', 'surecart-shippo');
            $error = new \WP_Error(
                'api_error',
                $error_message,
                ['status' => $status_code, 'response' => $decoded]
            );
            $this->logger->error('Shippo API Error', [
                'status' => $status_code,
                'message' => $error_message,
                'response' => $decoded,
            ]);
            return $error;
        }

        return $decoded;
    }

    /**
     * Validate an address.
     *
     * @param array $address Address data.
     * @return array|WP_Error Validation result or error.
     */
    public function validateAddress($address)
    {
        $data = [
            'street1' => $address['street1'] ?? '',
            'street2' => $address['street2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['zip'] ?? '',
            'country' => $address['country'] ?? 'US',
            'validate' => true,
        ];

        return $this->request('/addresses/', 'POST', $data);
    }

    /**
     * Create a shipment and get rates.
     *
     * @param array $shipment_data Shipment data.
     * @return array|WP_Error Shipment data with rates or error.
     */
    public function createShipment($shipment_data)
    {
        return $this->request('/shipments/', 'POST', $shipment_data);
    }

    /**
     * Get shipment by ID.
     *
     * @param string $shipment_id Shipment ID.
     * @return array|WP_Error Shipment data or error.
     */
    public function getShipment($shipment_id)
    {
        return $this->request('/shipments/' . $shipment_id, 'GET');
    }

    /**
     * Create a transaction (buy label).
     *
     * @param array $transaction_data Transaction data.
     * @return array|WP_Error Transaction data or error.
     */
    public function createTransaction($transaction_data)
    {
        return $this->request('/transactions/', 'POST', $transaction_data);
    }

    /**
     * Get transaction by ID.
     *
     * @param string $transaction_id Transaction ID.
     * @return array|WP_Error Transaction data or error.
     */
    public function getTransaction($transaction_id)
    {
        return $this->request('/transactions/' . $transaction_id, 'GET');
    }

    /**
     * Refund/void a transaction.
     *
     * @param string $transaction_id Transaction ID.
     * @return array|WP_Error Refund result or error.
     */
    public function refundTransaction($transaction_id)
    {
        return $this->request('/transactions/' . $transaction_id . '/refund', 'POST');
    }

    /**
     * Test API connection.
     *
     * @return bool|WP_Error True if connection successful, error otherwise.
     */
    public function testConnection()
    {
        $response = $this->request('/shipments/', 'GET', ['results' => 1]);

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    /**
     * Get carrier accounts.
     *
     * @return array|WP_Error Carrier accounts or error.
     */
    public function getCarrierAccounts()
    {
        return $this->request('/carrier_accounts/', 'GET');
    }
}
