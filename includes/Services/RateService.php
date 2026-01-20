<?php
/**
 * Rate service for shipping rate calculations.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Services;

use SureCartShippo\Packaging\PackagingEngine;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rate service class.
 */
class RateService
{
    /**
     * Packaging engine instance.
     *
     * @var PackagingEngine
     */
    private $packaging_engine;

    /**
     * Shippo client instance.
     *
     * @var \SureCartShippo\Core\ShippoClient
     */
    private $shippo_client;

    /**
     * Logger instance.
     *
     * @var \SureCartShippo\Diagnostics\Logger
     */
    private $logger;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->packaging_engine = new PackagingEngine();
        $this->shippo_client = \surecart_shippo()->shippo_client;
        $this->logger = \surecart_shippo()->logger;
    }

    /**
     * Get shipping rates for a cart.
     *
     * @param array $cart_items Cart items.
     * @param array $destination Destination address.
     * @return array|WP_Error Rates or error.
     */
    public function getRates($cart_items, $destination)
    {
        $this->logger->debug('Getting shipping rates', [
            'items' => count($cart_items),
            'destination' => $destination,
        ]);

        // Check cache first.
        $cache_key = $this->getCacheKey($cart_items, $destination);
        $cached_rates = $this->getCachedRates($cache_key);

        if ($cached_rates !== false) {
            $this->logger->debug('Returning cached rates');
            return $cached_rates;
        }

        // Estimate packaging.
        $packaging_result = $this->packaging_engine->estimatePackaging($cart_items);

        if ($packaging_result['status'] === PackagingEngine::STATUS_NEEDS_REVIEW) {
            $this->logger->info('Packaging needs review', ['message' => $packaging_result['message']]);

            // Check if fallback is enabled.
            if ($this->shouldUseFallback('review')) {
                return $this->getFallbackRates();
            }

            return new \WP_Error(
                'packaging_needs_review',
                $packaging_result['message']
            );
        }

        // Get ship-from address.
        $origin = $this->getOriginAddress();
        if (is_wp_error($origin)) {
            return $origin;
        }

        // Create Shippo shipment.
        $shipment_data = $this->buildShipmentData($origin, $destination, $packaging_result['parcels']);
        $shipment = $this->shippo_client->createShipment($shipment_data);

        if (is_wp_error($shipment)) {
            $this->logger->error('Failed to create shipment', [
                'error' => $shipment->get_error_message(),
            ]);

            if ($this->shouldUseFallback('api_error')) {
                return $this->getFallbackRates();
            }

            return $shipment;
        }

        // Extract and filter rates.
        $rates = $this->extractRates($shipment);
        $filtered_rates = $this->filterRates($rates);

        // Add packaging metadata to rates.
        $rates_with_metadata = array_map(function ($rate) use ($packaging_result, $shipment) {
            $rate['packaging'] = $packaging_result;
            $rate['shipment_id'] = $shipment['object_id'];
            return $rate;
        }, $filtered_rates);

        // Cache rates.
        $this->cacheRates($cache_key, $rates_with_metadata);

        $this->logger->debug('Returning ' . count($rates_with_metadata) . ' rates');

        return $rates_with_metadata;
    }

    /**
     * Build Shippo shipment data.
     *
     * @param array $origin Origin address.
     * @param array $destination Destination address.
     * @param array $parcels Parcel data.
     * @return array
     */
    private function buildShipmentData($origin, $destination, $parcels)
    {
        $shipment_parcels = [];

        foreach ($parcels as $parcel) {
            $shipment_parcels[] = [
                'length' => (string) $parcel['length'],
                'width' => (string) $parcel['width'],
                'height' => (string) $parcel['height'],
                'distance_unit' => 'in',
                'weight' => (string) $parcel['weight'],
                'mass_unit' => 'lb',
            ];
        }

        return [
            'address_from' => [
                'name' => $origin['name'] ?? '',
                'company' => $origin['company'] ?? '',
                'street1' => $origin['street1'],
                'street2' => $origin['street2'] ?? '',
                'city' => $origin['city'],
                'state' => $origin['state'],
                'zip' => $origin['zip'],
                'country' => $origin['country'],
                'phone' => $origin['phone'] ?? '',
                'email' => $origin['email'] ?? '',
            ],
            'address_to' => [
                'name' => $destination['name'] ?? '',
                'company' => $destination['company'] ?? '',
                'street1' => $destination['street1'],
                'street2' => $destination['street2'] ?? '',
                'city' => $destination['city'],
                'state' => $destination['state'] ?? '',
                'zip' => $destination['zip'],
                'country' => $destination['country'],
                'phone' => $destination['phone'] ?? '',
                'email' => $destination['email'] ?? '',
            ],
            'parcels' => $shipment_parcels,
            'async' => false,
        ];
    }

    /**
     * Get origin address from settings.
     *
     * @return array|WP_Error
     */
    private function getOriginAddress()
    {
        $origin = [
            'name' => get_option('surecart_shippo_origin_name', ''),
            'company' => get_option('surecart_shippo_origin_company', ''),
            'street1' => get_option('surecart_shippo_origin_street1', ''),
            'street2' => get_option('surecart_shippo_origin_street2', ''),
            'city' => get_option('surecart_shippo_origin_city', ''),
            'state' => get_option('surecart_shippo_origin_state', ''),
            'zip' => get_option('surecart_shippo_origin_zip', ''),
            'country' => get_option('surecart_shippo_origin_country', 'US'),
            'phone' => get_option('surecart_shippo_origin_phone', ''),
            'email' => get_option('surecart_shippo_origin_email', ''),
        ];

        // Validate required fields.
        if (empty($origin['street1']) || empty($origin['city']) || empty($origin['zip'])) {
            return new \WP_Error(
                'invalid_origin',
                __('Ship-from address is not fully configured.', 'surecart-shippo')
            );
        }

        return $origin;
    }

    /**
     * Extract rates from Shippo shipment response.
     *
     * @param array $shipment Shipment data.
     * @return array
     */
    private function extractRates($shipment)
    {
        if (empty($shipment['rates']) || !is_array($shipment['rates'])) {
            return [];
        }

        return array_map(function ($rate) {
            return [
                'rate_id' => $rate['object_id'],
                'provider' => $rate['provider'],
                'service_level' => $rate['servicelevel']['name'] ?? '',
                'amount' => (float) $rate['amount'],
                'currency' => $rate['currency'],
                'estimated_days' => $rate['estimated_days'] ?? null,
                'duration_terms' => $rate['duration_terms'] ?? '',
                'carrier_account' => $rate['carrier_account'] ?? '',
            ];
        }, $shipment['rates']);
    }

    /**
     * Filter rates based on allowed carriers/services.
     *
     * @param array $rates All rates.
     * @return array Filtered rates.
     */
    private function filterRates($rates)
    {
        $allowed_carriers = get_option('surecart_shippo_allowed_carriers', []);

        // If no filters set, return all rates.
        if (empty($allowed_carriers)) {
            return $rates;
        }

        return array_filter($rates, function ($rate) use ($allowed_carriers) {
            return in_array($rate['provider'], $allowed_carriers, true);
        });
    }

    /**
     * Get cache key for rates.
     *
     * @param array $cart_items Cart items.
     * @param array $destination Destination address.
     * @return string
     */
    private function getCacheKey($cart_items, $destination)
    {
        $cart_signature = md5(wp_json_encode($cart_items));
        $dest_signature = md5(wp_json_encode([
            $destination['country'],
            $destination['state'] ?? '',
            $destination['zip'],
        ]));

        return 'surecart_shippo_rates_' . $cart_signature . '_' . $dest_signature;
    }

    /**
     * Get cached rates.
     *
     * @param string $cache_key Cache key.
     * @return array|false Rates or false if not cached.
     */
    private function getCachedRates($cache_key)
    {
        return get_transient($cache_key);
    }

    /**
     * Cache rates.
     *
     * @param string $cache_key Cache key.
     * @param array  $rates Rates to cache.
     */
    private function cacheRates($cache_key, $rates)
    {
        $ttl = (int) get_option('surecart_shippo_cache_ttl', 600);
        set_transient($cache_key, $rates, $ttl);
    }

    /**
     * Check if fallback should be used.
     *
     * @param string $reason Reason for fallback.
     * @return bool
     */
    private function shouldUseFallback($reason)
    {
        $fallback_enabled = get_option('surecart_shippo_fallback_enabled', true);

        if (!$fallback_enabled) {
            return false;
        }

        if ($reason === 'review') {
            return get_option('surecart_shippo_fallback_on_review', true);
        }

        return true;
    }

    /**
     * Get fallback rates.
     *
     * @return array
     */
    private function getFallbackRates()
    {
        $fallback_label = get_option('surecart_shippo_fallback_label', __('Standard Shipping', 'surecart-shippo'));
        $fallback_amount = (float) get_option('surecart_shippo_fallback_amount', 10.00);

        return [
            [
                'rate_id' => 'fallback',
                'provider' => 'fallback',
                'service_level' => $fallback_label,
                'amount' => $fallback_amount,
                'currency' => 'USD',
                'estimated_days' => null,
                'duration_terms' => '',
                'carrier_account' => '',
                'is_fallback' => true,
            ],
        ];
    }

    /**
     * Clear rate cache.
     */
    public function clearCache()
    {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_surecart_shippo_rates_%'
            OR option_name LIKE '_transient_timeout_surecart_shippo_rates_%'"
        );
    }
}
