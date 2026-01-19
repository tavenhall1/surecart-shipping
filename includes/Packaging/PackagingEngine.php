<?php
/**
 * Packaging estimation engine.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Packaging;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Packaging engine class.
 */
class PackagingEngine
{
    /**
     * Shipping profiles.
     */
    const PROFILE_SMALL_RUGGED = 'SMALL_RUGGED';
    const PROFILE_SMALL_FRAGILE = 'SMALL_FRAGILE';
    const PROFILE_MEDIUM_RUGGED = 'MEDIUM_RUGGED';
    const PROFILE_MEDIUM_FRAGILE = 'MEDIUM_FRAGILE';
    const PROFILE_LARGE_RUGGED = 'LARGE_RUGGED';
    const PROFILE_LARGE_FRAGILE = 'LARGE_FRAGILE';
    const PROFILE_LONG_ITEM = 'LONG_ITEM';
    const PROFILE_IRREGULAR = 'IRREGULAR';

    /**
     * Packaging status codes.
     */
    const STATUS_OK = 'OK';
    const STATUS_NEEDS_REVIEW = 'NEEDS_REVIEW';

    /**
     * Box catalog instance.
     *
     * @var BoxCatalog
     */
    private $box_catalog;

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
        $this->box_catalog = new BoxCatalog();
        $this->logger = surecart_shippo()->logger;
    }

    /**
     * Estimate packaging for a cart.
     *
     * @param array $cart_items Cart items with product data.
     * @return array Packaging estimate result.
     */
    public function estimatePackaging($cart_items)
    {
        $this->logger->debug('Starting packaging estimation', ['items' => count($cart_items)]);

        // Validate cart items have required metadata.
        $validation = $this->validateCartMetadata($cart_items);
        if ($validation['status'] === self::STATUS_NEEDS_REVIEW) {
            return $validation;
        }

        // Separate ship-alone items.
        $ship_alone_items = [];
        $regular_items = [];

        foreach ($cart_items as $item) {
            $metadata = $this->getItemMetadata($item);

            if ($metadata['ship_alone'] || $metadata['manual_only']) {
                $ship_alone_items[] = $item;
            } else {
                $regular_items[] = $item;
            }
        }

        $parcels = [];

        // Process ship-alone items.
        foreach ($ship_alone_items as $item) {
            $parcel = $this->createParcelForItem($item);
            if (is_wp_error($parcel)) {
                return [
                    'status' => self::STATUS_NEEDS_REVIEW,
                    'message' => $parcel->get_error_message(),
                    'parcels' => [],
                ];
            }
            $parcels[] = $parcel;
        }

        // Process regular items together.
        if (!empty($regular_items)) {
            $result = $this->packRegularItems($regular_items);
            if ($result['status'] === self::STATUS_NEEDS_REVIEW) {
                return $result;
            }
            $parcels = array_merge($parcels, $result['parcels']);
        }

        return [
            'status' => self::STATUS_OK,
            'parcels' => $parcels,
            'message' => sprintf(
                __('Successfully estimated %d parcel(s)', 'surecart-shippo'),
                count($parcels)
            ),
        ];
    }

    /**
     * Validate cart items have required metadata.
     *
     * @param array $cart_items Cart items.
     * @return array Validation result.
     */
    private function validateCartMetadata($cart_items)
    {
        $missing_metadata = [];

        foreach ($cart_items as $item) {
            $metadata = $this->getItemMetadata($item);

            if (empty($metadata['weight_lb']) ||
                empty($metadata['dim_in_length']) ||
                empty($metadata['dim_in_width']) ||
                empty($metadata['dim_in_height'])) {
                $missing_metadata[] = $item['name'] ?? __('Unknown product', 'surecart-shippo');
            }

            if ($metadata['manual_only']) {
                return [
                    'status' => self::STATUS_NEEDS_REVIEW,
                    'message' => sprintf(
                        __('Product "%s" requires manual packaging review', 'surecart-shippo'),
                        $item['name'] ?? __('Unknown', 'surecart-shippo')
                    ),
                    'parcels' => [],
                ];
            }
        }

        if (!empty($missing_metadata)) {
            return [
                'status' => self::STATUS_NEEDS_REVIEW,
                'message' => sprintf(
                    __('Missing shipping metadata for: %s', 'surecart-shippo'),
                    implode(', ', $missing_metadata)
                ),
                'parcels' => [],
            ];
        }

        return ['status' => self::STATUS_OK];
    }

    /**
     * Get item metadata with defaults.
     *
     * @param array $item Cart item.
     * @return array Metadata.
     */
    private function getItemMetadata($item)
    {
        $product_id = $item['product_id'] ?? 0;

        return [
            'weight_lb' => (float) get_post_meta($product_id, '_shipping_weight_lb', true),
            'dim_in_length' => (float) get_post_meta($product_id, '_shipping_dim_in_length', true),
            'dim_in_width' => (float) get_post_meta($product_id, '_shipping_dim_in_width', true),
            'dim_in_height' => (float) get_post_meta($product_id, '_shipping_dim_in_height', true),
            'shipping_profile' => get_post_meta($product_id, '_shipping_profile', true) ?: self::PROFILE_MEDIUM_RUGGED,
            'ship_alone' => (bool) get_post_meta($product_id, '_ship_alone', true),
            'fragile' => (bool) get_post_meta($product_id, '_fragile', true),
            'irregular_shape' => (bool) get_post_meta($product_id, '_irregular_shape', true),
            'manual_only' => (bool) get_post_meta($product_id, '_manual_only', true),
            'declared_value_usd' => (float) get_post_meta($product_id, '_declared_value_usd', true),
        ];
    }

    /**
     * Create a parcel for a single item.
     *
     * @param array $item Cart item.
     * @return array|WP_Error Parcel data or error.
     */
    private function createParcelForItem($item)
    {
        $metadata = $this->getItemMetadata($item);
        $quantity = $item['quantity'] ?? 1;

        // Calculate buffered dimensions.
        $padding = $this->getPaddingForProfile($metadata['shipping_profile']);
        $pack_factor = $this->getPackFactorForProfile($metadata['shipping_profile']);

        $buffered_length = $metadata['dim_in_length'] + (2 * $padding);
        $buffered_width = $metadata['dim_in_width'] + (2 * $padding);
        $buffered_height = $metadata['dim_in_height'] + (2 * $padding);

        // Apply pack factor.
        $buffered_length *= $pack_factor;
        $buffered_width *= $pack_factor;
        $buffered_height *= $pack_factor;

        // For multiple quantities, stack them (simple approach - use height).
        if ($quantity > 1) {
            $buffered_height *= $quantity;
        }

        // Sort dimensions.
        $dims = [$buffered_length, $buffered_width, $buffered_height];
        rsort($dims);

        $total_weight = $metadata['weight_lb'] * $quantity;

        // Find fitting box.
        $box = $this->box_catalog->findFittingBox($dims[0], $dims[1], $dims[2], $total_weight);

        if (!$box) {
            return new \WP_Error(
                'no_fitting_box',
                sprintf(
                    __('No box found for dimensions: %.2f x %.2f x %.2f (%.2f lb)', 'surecart-shippo'),
                    $dims[0],
                    $dims[1],
                    $dims[2],
                    $total_weight
                )
            );
        }

        // Add packaging weight.
        $packaging_weight = $this->calculatePackagingWeight($total_weight);
        $final_weight = $total_weight + $box['empty_weight'] + $packaging_weight;

        return [
            'box_id' => $box['box_id'],
            'box_name' => $box['name'],
            'length' => $box['internal_length'],
            'width' => $box['internal_width'],
            'height' => $box['internal_height'],
            'weight' => $final_weight,
            'items' => [$item],
        ];
    }

    /**
     * Pack regular items together.
     *
     * @param array $items Regular cart items.
     * @return array Packaging result.
     */
    private function packRegularItems($items)
    {
        // Calculate total volume and determine if splitting is needed.
        $total_volume = 0;
        $total_weight = 0;
        $max_length = 0;
        $has_fragile = false;
        $has_irregular = false;

        foreach ($items as $item) {
            $metadata = $this->getItemMetadata($item);
            $quantity = $item['quantity'] ?? 1;

            $padding = $this->getPaddingForProfile($metadata['shipping_profile']);

            $buffered_length = $metadata['dim_in_length'] + (2 * $padding);
            $buffered_width = $metadata['dim_in_width'] + (2 * $padding);
            $buffered_height = $metadata['dim_in_height'] + (2 * $padding);

            $item_volume = $buffered_length * $buffered_width * $buffered_height * $quantity;
            $total_volume += $item_volume;
            $total_weight += $metadata['weight_lb'] * $quantity;

            $max_length = max($max_length, $buffered_length);

            if ($metadata['fragile']) {
                $has_fragile = true;
            }
            if ($metadata['irregular_shape']) {
                $has_irregular = true;
            }
        }

        // Determine cart-level pack factor.
        $cart_pack_factor = $this->getCartPackFactor($has_fragile, $has_irregular);
        $effective_volume = $total_volume * $cart_pack_factor;

        // Check if we need to split.
        $split_threshold = (float) get_option('surecart_shippo_split_threshold', 40);

        if ($total_weight > $split_threshold) {
            return $this->splitItems($items);
        }

        // Calculate required box dimensions.
        $cross_section_area = $effective_volume / $max_length;
        $required_width = ceil(sqrt($cross_section_area));
        $required_height = ceil($cross_section_area / $required_width);

        // Sort dimensions.
        $dims = [$max_length, $required_width, $required_height];
        rsort($dims);

        // Find fitting box.
        $box = $this->box_catalog->findFittingBox($dims[0], $dims[1], $dims[2], $total_weight);

        if (!$box) {
            return $this->splitItems($items);
        }

        // Add packaging weight.
        $packaging_weight = $this->calculatePackagingWeight($total_weight);
        $final_weight = $total_weight + $box['empty_weight'] + $packaging_weight;

        return [
            'status' => self::STATUS_OK,
            'parcels' => [
                [
                    'box_id' => $box['box_id'],
                    'box_name' => $box['name'],
                    'length' => $box['internal_length'],
                    'width' => $box['internal_width'],
                    'height' => $box['internal_height'],
                    'weight' => $final_weight,
                    'items' => $items,
                ],
            ],
        ];
    }

    /**
     * Split items into multiple parcels (greedy approach).
     *
     * @param array $items Cart items.
     * @return array Packaging result.
     */
    private function splitItems($items)
    {
        // Sort items by weight descending.
        usort($items, function ($a, $b) {
            $meta_a = $this->getItemMetadata($a);
            $meta_b = $this->getItemMetadata($b);
            return $meta_b['weight_lb'] <=> $meta_a['weight_lb'];
        });

        $parcels = [];

        foreach ($items as $item) {
            $parcel = $this->createParcelForItem($item);
            if (is_wp_error($parcel)) {
                return [
                    'status' => self::STATUS_NEEDS_REVIEW,
                    'message' => $parcel->get_error_message(),
                    'parcels' => [],
                ];
            }
            $parcels[] = $parcel;
        }

        return [
            'status' => self::STATUS_OK,
            'parcels' => $parcels,
        ];
    }

    /**
     * Get padding for shipping profile.
     *
     * @param string $profile Shipping profile.
     * @return float Padding in inches.
     */
    private function getPaddingForProfile($profile)
    {
        $defaults = [
            self::PROFILE_SMALL_RUGGED => get_option('surecart_shippo_padding_small', 0.5),
            self::PROFILE_SMALL_FRAGILE => get_option('surecart_shippo_padding_small', 0.5),
            self::PROFILE_MEDIUM_RUGGED => get_option('surecart_shippo_padding_medium', 0.75),
            self::PROFILE_MEDIUM_FRAGILE => get_option('surecart_shippo_padding_medium', 0.75),
            self::PROFILE_LARGE_RUGGED => get_option('surecart_shippo_padding_large', 1.0),
            self::PROFILE_LARGE_FRAGILE => get_option('surecart_shippo_padding_large', 1.0),
            self::PROFILE_LONG_ITEM => get_option('surecart_shippo_padding_long', 1.25),
            self::PROFILE_IRREGULAR => get_option('surecart_shippo_padding_long', 1.25),
        ];

        return $defaults[$profile] ?? 0.75;
    }

    /**
     * Get pack factor for shipping profile.
     *
     * @param string $profile Shipping profile.
     * @return float Pack factor.
     */
    private function getPackFactorForProfile($profile)
    {
        if (strpos($profile, 'FRAGILE') !== false) {
            return (float) get_option('surecart_shippo_pack_factor_fragile', 1.25);
        }

        if (strpos($profile, 'IRREGULAR') !== false || strpos($profile, 'LONG') !== false) {
            return (float) get_option('surecart_shippo_pack_factor_irregular', 1.30);
        }

        return (float) get_option('surecart_shippo_pack_factor_rugged', 1.15);
    }

    /**
     * Get cart-level pack factor.
     *
     * @param bool $has_fragile Has fragile items.
     * @param bool $has_irregular Has irregular items.
     * @return float Pack factor.
     */
    private function getCartPackFactor($has_fragile, $has_irregular)
    {
        if ($has_irregular) {
            return (float) get_option('surecart_shippo_pack_factor_irregular', 1.30);
        }

        if ($has_fragile) {
            return (float) get_option('surecart_shippo_pack_factor_fragile', 1.25);
        }

        return (float) get_option('surecart_shippo_pack_factor_rugged', 1.15);
    }

    /**
     * Calculate packaging weight.
     *
     * @param float $item_weight Total item weight.
     * @return float Packaging weight.
     */
    private function calculatePackagingWeight($item_weight)
    {
        $percent = (float) get_option('surecart_shippo_packaging_weight_percent', 2);
        $min = (float) get_option('surecart_shippo_packaging_weight_min', 0.2);

        $calculated = $item_weight * ($percent / 100);

        return max($calculated, $min);
    }
}
