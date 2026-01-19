<?php
/**
 * Frontend shipping rates display.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Frontend;

use SureCartShippo\Services\RateService;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping rates class.
 */
class ShippingRates
{
    /**
     * Rate service instance.
     *
     * @var RateService
     */
    private $rate_service;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->rate_service = new RateService();

        // Hook into SureCart shipping methods.
        add_filter('surecart/shipping/methods', [$this, 'addShippingMethod'], 10);
        add_filter('surecart/shipping/calculate_rates', [$this, 'calculateRates'], 10, 2);
        add_action('surecart/checkout/order_created', [$this, 'saveSelectedRate'], 10, 2);
    }

    /**
     * Add Shippo as a shipping method.
     *
     * @param array $methods Existing methods.
     * @return array
     */
    public function addShippingMethod($methods)
    {
        $methods['shippo'] = [
            'id' => 'shippo',
            'label' => __('Shippo Live Rates', 'surecart-shippo'),
            'description' => __('Real-time shipping rates from multiple carriers', 'surecart-shippo'),
            'type' => 'dynamic',
        ];

        return $methods;
    }

    /**
     * Calculate shipping rates.
     *
     * @param array $rates Existing rates.
     * @param array $context Calculation context (cart, address).
     * @return array
     */
    public function calculateRates($rates, $context)
    {
        $cart_items = $context['cart_items'] ?? [];
        $destination = $context['destination'] ?? [];

        if (empty($cart_items) || empty($destination)) {
            return $rates;
        }

        $shippo_rates = $this->rate_service->getRates($cart_items, $destination);

        if (is_wp_error($shippo_rates)) {
            surecart_shippo()->logger->error('Failed to get rates', [
                'error' => $shippo_rates->get_error_message(),
            ]);
            return $rates;
        }

        // Convert Shippo rates to SureCart format.
        foreach ($shippo_rates as $shippo_rate) {
            $rates[] = [
                'id' => 'shippo_' . $shippo_rate['rate_id'],
                'method_id' => 'shippo',
                'label' => $this->formatRateLabel($shippo_rate),
                'amount' => $shippo_rate['amount'] * 100, // Convert to cents.
                'currency' => $shippo_rate['currency'],
                'meta' => [
                    'shippo_rate_id' => $shippo_rate['rate_id'],
                    'shippo_shipment_id' => $shippo_rate['shipment_id'] ?? '',
                    'shippo_packaging' => $shippo_rate['packaging'] ?? [],
                    'provider' => $shippo_rate['provider'],
                    'service_level' => $shippo_rate['service_level'],
                ],
            ];
        }

        return $rates;
    }

    /**
     * Format rate label.
     *
     * @param array $rate Rate data.
     * @return string
     */
    private function formatRateLabel($rate)
    {
        $label = $rate['provider'] . ' - ' . $rate['service_level'];

        if (!empty($rate['estimated_days'])) {
            $label .= sprintf(
                ' (%d %s)',
                $rate['estimated_days'],
                _n('day', 'days', $rate['estimated_days'], 'surecart-shippo')
            );
        }

        return $label;
    }

    /**
     * Save selected rate to order meta.
     *
     * @param int   $order_id Order ID.
     * @param array $order_data Order data.
     */
    public function saveSelectedRate($order_id, $order_data)
    {
        if (empty($order_data['shipping_rate'])) {
            return;
        }

        $rate = $order_data['shipping_rate'];

        if (empty($rate['meta']['shippo_rate_id'])) {
            return;
        }

        // Save Shippo metadata to order.
        update_post_meta($order_id, '_sc_shippo_selected_rate_id', $rate['meta']['shippo_rate_id']);
        update_post_meta($order_id, '_sc_shippo_shipment_id', $rate['meta']['shippo_shipment_id'] ?? '');
        update_post_meta($order_id, '_sc_shippo_packaging_snapshot', wp_json_encode($rate['meta']['shippo_packaging'] ?? []));
        update_post_meta($order_id, '_sc_shippo_packaging_status', 'OK');
        update_post_meta($order_id, '_sc_shippo_provider', $rate['meta']['provider'] ?? '');
        update_post_meta($order_id, '_sc_shippo_service_level', $rate['meta']['service_level'] ?? '');

        surecart_shippo()->logger->info('Saved shipping rate to order', [
            'order_id' => $order_id,
            'rate_id' => $rate['meta']['shippo_rate_id'],
        ]);
    }
}
