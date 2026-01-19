<?php
/**
 * Frontend address validation.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Frontend;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Address validation class.
 */
class AddressValidation
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Hook into SureCart checkout address validation.
        add_filter('surecart/checkout/validate_address', [$this, 'validateAddress'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Validate address via Shippo.
     *
     * @param array $address Address data.
     * @param string $context Context (billing or shipping).
     * @return array|WP_Error Validated address or error.
     */
    public function validateAddress($address, $context = 'shipping')
    {
        // Only validate shipping addresses.
        if ($context !== 'shipping') {
            return $address;
        }

        $shippo_client = surecart_shippo()->shippo_client;
        $logger = surecart_shippo()->logger;

        $logger->debug('Validating address', ['address' => $address]);

        $result = $shippo_client->validateAddress([
            'street1' => $address['line1'] ?? '',
            'street2' => $address['line2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'US',
        ]);

        if (is_wp_error($result)) {
            $logger->error('Address validation failed', ['error' => $result->get_error_message()]);
            return $address; // Allow to proceed with unvalidated address.
        }

        // Check validation results.
        if (!empty($result['validation_results'])) {
            $validation = $result['validation_results'];

            if (!$validation['is_valid']) {
                $logger->info('Address is invalid', ['messages' => $validation['messages'] ?? []]);
            }

            // If there's a suggested address, store it for user review.
            if (!empty($validation['suggested_address'])) {
                $logger->info('Address suggestion available');
                // Store suggestion in session for display.
                WP_Session::set('shippo_address_suggestion', [
                    'original' => $address,
                    'suggested' => $validation['suggested_address'],
                ]);
            }
        }

        return $address;
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueueAssets()
    {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'surecart-shippo-address-validation',
            SURECART_SHIPPO_PLUGIN_URL . 'assets/js/address-validation.js',
            ['jquery'],
            SURECART_SHIPPO_VERSION,
            true
        );

        wp_localize_script('surecart-shippo-address-validation', 'surecartShippoAddress', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('surecart_shippo_address'),
        ]);
    }
}
