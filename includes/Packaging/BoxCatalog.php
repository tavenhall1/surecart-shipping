<?php
/**
 * Box catalog management.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Packaging;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Box catalog class.
 */
class BoxCatalog
{
    /**
     * Get all boxes.
     *
     * @param bool $enabled_only Return only enabled boxes.
     * @return array
     */
    public function getAll($enabled_only = true)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        $where = $enabled_only ? 'WHERE enabled = 1' : '';

        $boxes = $wpdb->get_results(
            "SELECT * FROM $table_name $where ORDER BY internal_length * internal_width * internal_height ASC",
            ARRAY_A
        );

        return $boxes ?: [];
    }

    /**
     * Get box by ID.
     *
     * @param string $box_id Box ID.
     * @return array|null
     */
    public function getById($box_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE box_id = %s", $box_id),
            ARRAY_A
        );
    }

    /**
     * Create a new box.
     *
     * @param array $data Box data.
     * @return int|false Box ID or false on failure.
     */
    public function create($data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        $result = $wpdb->insert(
            $table_name,
            [
                'box_id' => $data['box_id'],
                'name' => $data['name'],
                'internal_length' => $data['internal_length'],
                'internal_width' => $data['internal_width'],
                'internal_height' => $data['internal_height'],
                'empty_weight' => $data['empty_weight'] ?? 0,
                'max_weight' => $data['max_weight'],
                'box_type' => $data['box_type'] ?? 'RSC',
                'enabled' => $data['enabled'] ?? 1,
            ],
            ['%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a box.
     *
     * @param string $box_id Box ID.
     * @param array  $data Box data.
     * @return bool
     */
    public function update($box_id, $data)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        $update_data = [];
        $format = [];

        if (isset($data['name'])) {
            $update_data['name'] = $data['name'];
            $format[] = '%s';
        }
        if (isset($data['internal_length'])) {
            $update_data['internal_length'] = $data['internal_length'];
            $format[] = '%f';
        }
        if (isset($data['internal_width'])) {
            $update_data['internal_width'] = $data['internal_width'];
            $format[] = '%f';
        }
        if (isset($data['internal_height'])) {
            $update_data['internal_height'] = $data['internal_height'];
            $format[] = '%f';
        }
        if (isset($data['empty_weight'])) {
            $update_data['empty_weight'] = $data['empty_weight'];
            $format[] = '%f';
        }
        if (isset($data['max_weight'])) {
            $update_data['max_weight'] = $data['max_weight'];
            $format[] = '%f';
        }
        if (isset($data['box_type'])) {
            $update_data['box_type'] = $data['box_type'];
            $format[] = '%s';
        }
        if (isset($data['enabled'])) {
            $update_data['enabled'] = $data['enabled'];
            $format[] = '%d';
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['box_id' => $box_id],
            $format,
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Delete a box.
     *
     * @param string $box_id Box ID.
     * @return bool
     */
    public function delete($box_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surecart_shippo_boxes';

        $result = $wpdb->delete(
            $table_name,
            ['box_id' => $box_id],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Find a fitting box for given dimensions and weight.
     *
     * @param float $length Required length in inches.
     * @param float $width Required width in inches.
     * @param float $height Required height in inches.
     * @param float $weight Total weight in pounds.
     * @return array|null Box data or null if no fitting box found.
     */
    public function findFittingBox($length, $width, $height, $weight)
    {
        $boxes = $this->getAll(true);

        // Sort dimensions of required space.
        $required_dims = [$length, $width, $height];
        rsort($required_dims);

        foreach ($boxes as $box) {
            // Check weight capacity.
            if ($weight > $box['max_weight']) {
                continue;
            }

            // Get box dimensions and sort them.
            $box_dims = [
                (float) $box['internal_length'],
                (float) $box['internal_width'],
                (float) $box['internal_height'],
            ];
            rsort($box_dims);

            // Check if all dimensions fit.
            if ($required_dims[0] <= $box_dims[0] &&
                $required_dims[1] <= $box_dims[1] &&
                $required_dims[2] <= $box_dims[2]) {
                return $box;
            }
        }

        return null;
    }

    /**
     * Calculate box volume.
     *
     * @param array $box Box data.
     * @return float Volume in cubic inches.
     */
    public function calculateVolume($box)
    {
        return $box['internal_length'] * $box['internal_width'] * $box['internal_height'];
    }

    /**
     * Get box external dimensions.
     *
     * @param array $box Box data.
     * @return array External dimensions.
     */
    public function getExternalDimensions($box)
    {
        $expansion = (float) get_option('surecart_shippo_external_dim_expansion', 0.25);

        return [
            'length' => $box['internal_length'] + $expansion,
            'width' => $box['internal_width'] + $expansion,
            'height' => $box['internal_height'] + $expansion,
        ];
    }

    /**
     * Seed default boxes.
     *
     * @return int Number of boxes created.
     */
    public function seedDefaults()
    {
        $default_boxes = [
            [
                'box_id' => 'small_flat',
                'name' => 'Small Flat Rate Box',
                'internal_length' => 8.625,
                'internal_width' => 5.375,
                'internal_height' => 1.625,
                'empty_weight' => 0.3,
                'max_weight' => 70,
                'box_type' => 'RSC',
            ],
            [
                'box_id' => 'medium_flat',
                'name' => 'Medium Flat Rate Box',
                'internal_length' => 11.0,
                'internal_width' => 8.5,
                'internal_height' => 5.5,
                'empty_weight' => 0.5,
                'max_weight' => 70,
                'box_type' => 'RSC',
            ],
            [
                'box_id' => 'large_flat',
                'name' => 'Large Flat Rate Box',
                'internal_length' => 12.0,
                'internal_width' => 12.0,
                'internal_height' => 5.5,
                'empty_weight' => 0.6,
                'max_weight' => 70,
                'box_type' => 'RSC',
            ],
            [
                'box_id' => 'custom_10x8x6',
                'name' => 'Custom Box 10x8x6',
                'internal_length' => 10.0,
                'internal_width' => 8.0,
                'internal_height' => 6.0,
                'empty_weight' => 0.4,
                'max_weight' => 50,
                'box_type' => 'RSC',
            ],
            [
                'box_id' => 'custom_14x10x8',
                'name' => 'Custom Box 14x10x8',
                'internal_length' => 14.0,
                'internal_width' => 10.0,
                'internal_height' => 8.0,
                'empty_weight' => 0.7,
                'max_weight' => 65,
                'box_type' => 'RSC',
            ],
            [
                'box_id' => 'custom_18x14x12',
                'name' => 'Custom Box 18x14x12',
                'internal_length' => 18.0,
                'internal_width' => 14.0,
                'internal_height' => 12.0,
                'empty_weight' => 1.2,
                'max_weight' => 70,
                'box_type' => 'RSC',
            ],
        ];

        $created = 0;

        foreach ($default_boxes as $box) {
            // Check if box already exists.
            if (!$this->getById($box['box_id'])) {
                if ($this->create($box)) {
                    $created++;
                }
            }
        }

        return $created;
    }
}
