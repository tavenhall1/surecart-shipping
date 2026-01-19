<?php
/**
 * SureCart integration adapter.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Integration;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SureCart integration class.
 */
class SureCartIntegration
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Register Shippo as a shipping provider.
        add_filter('surecart/shipping/providers', [$this, 'registerProvider']);

        // Hook into order lifecycle events.
        add_action('surecart/order/paid', [$this, 'onOrderPaid'], 10, 1);
        add_action('surecart/order/refunded', [$this, 'onOrderRefunded'], 10, 1);
        add_action('surecart/order/canceled', [$this, 'onOrderCanceled'], 10, 1);
    }

    /**
     * Register Shippo as a shipping provider.
     *
     * @param array $providers Existing providers.
     * @return array
     */
    public function registerProvider($providers)
    {
        $providers['shippo'] = [
            'id' => 'shippo',
            'name' => __('Shippo', 'surecart-shippo'),
            'description' => __('Live shipping rates from multiple carriers via Shippo', 'surecart-shippo'),
            'supports' => [
                'live_rates',
                'address_validation',
                'label_printing',
                'tracking',
                'international',
            ],
            'settings_url' => admin_url('admin.php?page=surecart-shippo'),
        ];

        return $providers;
    }

    /**
     * Handle order paid event.
     *
     * @param int $order_id Order ID.
     */
    public function onOrderPaid($order_id)
    {
        surecart_shippo()->logger->info('Order paid', ['order_id' => $order_id]);

        // Mark order as eligible for label purchase.
        update_post_meta($order_id, '_sc_shippo_eligible_for_label', true);

        // Optionally send notification to fulfillment team.
        do_action('surecart_shippo/order/ready_for_fulfillment', $order_id);
    }

    /**
     * Handle order refunded event.
     *
     * @param int $order_id Order ID.
     */
    public function onOrderRefunded($order_id)
    {
        surecart_shippo()->logger->info('Order refunded', ['order_id' => $order_id]);

        // Block label purchase.
        update_post_meta($order_id, '_sc_shippo_eligible_for_label', false);

        // Check if label was already purchased and add note.
        $transaction_id = get_post_meta($order_id, '_sc_shippo_transaction_id', true);
        if (!empty($transaction_id)) {
            surecart_shippo()->logger->warning('Order refunded but label exists', [
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
            ]);

            // Add admin notice meta.
            update_post_meta($order_id, '_sc_shippo_refund_notice',
                __('This order was refunded after a shipping label was purchased. Consider refunding the label.', 'surecart-shippo')
            );
        }
    }

    /**
     * Handle order canceled event.
     *
     * @param int $order_id Order ID.
     */
    public function onOrderCanceled($order_id)
    {
        surecart_shippo()->logger->info('Order canceled', ['order_id' => $order_id]);

        // Block label purchase.
        update_post_meta($order_id, '_sc_shippo_eligible_for_label', false);
    }

    /**
     * Get order shipping address.
     *
     * @param int $order_id Order ID.
     * @return array|null Shipping address or null.
     */
    public static function getOrderShippingAddress($order_id)
    {
        // This will need to be adapted to SureCart's actual data structure.
        $address = get_post_meta($order_id, '_sc_shipping_address', true);

        if (empty($address)) {
            return null;
        }

        return [
            'name' => $address['name'] ?? '',
            'company' => $address['company'] ?? '',
            'street1' => $address['line1'] ?? '',
            'street2' => $address['line2'] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'US',
            'phone' => $address['phone'] ?? '',
            'email' => $address['email'] ?? '',
        ];
    }

    /**
     * Get order items formatted for packaging calculation.
     *
     * @param int $order_id Order ID.
     * @return array Order items.
     */
    public static function getOrderItems($order_id)
    {
        // This will need to be adapted to SureCart's actual data structure.
        $items = get_post_meta($order_id, '_sc_order_items', true);

        if (empty($items) || !is_array($items)) {
            return [];
        }

        $formatted_items = [];

        foreach ($items as $item) {
            $formatted_items[] = [
                'product_id' => $item['product_id'] ?? 0,
                'name' => $item['name'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'price' => $item['price'] ?? 0,
            ];
        }

        return $formatted_items;
    }
}
