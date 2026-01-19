<?php
/**
 * Product meta box for shipping metadata.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Admin;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product meta box class.
 */
class ProductMetaBox
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('save_post', [$this, 'saveMeta']);
    }

    /**
     * Add meta box to product edit screen.
     */
    public function addMetaBox()
    {
        add_meta_box(
            'surecart_shippo_product_shipping',
            __('Shipping (Shippo)', 'surecart-shippo'),
            [$this, 'renderMetaBox'],
            'sc_product',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content.
     *
     * @param WP_Post $post Post object.
     */
    public function renderMetaBox($post)
    {
        wp_nonce_field('surecart_shippo_product_meta', 'surecart_shippo_product_nonce');

        $weight = get_post_meta($post->ID, '_shipping_weight_lb', true);
        $length = get_post_meta($post->ID, '_shipping_dim_in_length', true);
        $width = get_post_meta($post->ID, '_shipping_dim_in_width', true);
        $height = get_post_meta($post->ID, '_shipping_dim_in_height', true);
        $profile = get_post_meta($post->ID, '_shipping_profile', true);
        $ship_alone = get_post_meta($post->ID, '_ship_alone', true);
        $fragile = get_post_meta($post->ID, '_fragile', true);
        $irregular = get_post_meta($post->ID, '_irregular_shape', true);
        $manual_only = get_post_meta($post->ID, '_manual_only', true);
        $declared_value = get_post_meta($post->ID, '_declared_value_usd', true);

        ?>
        <div class="surecart-shippo-product-meta">
            <p>
                <label for="shipping_weight_lb"><?php esc_html_e('Weight (lb)', 'surecart-shippo'); ?></label><br>
                <input type="number" step="0.01" id="shipping_weight_lb" name="shipping_weight_lb" value="<?php echo esc_attr($weight); ?>" class="regular-text" required />
            </p>

            <p>
                <label><?php esc_html_e('Dimensions (inches)', 'surecart-shippo'); ?></label><br>
                <input type="number" step="0.01" name="shipping_dim_in_length" value="<?php echo esc_attr($length); ?>" placeholder="Length" style="width: 32%;" required />
                <input type="number" step="0.01" name="shipping_dim_in_width" value="<?php echo esc_attr($width); ?>" placeholder="Width" style="width: 32%;" required />
                <input type="number" step="0.01" name="shipping_dim_in_height" value="<?php echo esc_attr($height); ?>" placeholder="Height" style="width: 32%;" required />
            </p>

            <p>
                <label for="shipping_profile"><?php esc_html_e('Shipping Profile', 'surecart-shippo'); ?></label><br>
                <select id="shipping_profile" name="shipping_profile" class="regular-text">
                    <option value="SMALL_RUGGED" <?php selected($profile, 'SMALL_RUGGED'); ?>><?php esc_html_e('Small Rugged', 'surecart-shippo'); ?></option>
                    <option value="SMALL_FRAGILE" <?php selected($profile, 'SMALL_FRAGILE'); ?>><?php esc_html_e('Small Fragile', 'surecart-shippo'); ?></option>
                    <option value="MEDIUM_RUGGED" <?php selected($profile, 'MEDIUM_RUGGED'); ?>><?php esc_html_e('Medium Rugged', 'surecart-shippo'); ?></option>
                    <option value="MEDIUM_FRAGILE" <?php selected($profile, 'MEDIUM_FRAGILE'); ?>><?php esc_html_e('Medium Fragile', 'surecart-shippo'); ?></option>
                    <option value="LARGE_RUGGED" <?php selected($profile, 'LARGE_RUGGED'); ?>><?php esc_html_e('Large Rugged', 'surecart-shippo'); ?></option>
                    <option value="LARGE_FRAGILE" <?php selected($profile, 'LARGE_FRAGILE'); ?>><?php esc_html_e('Large Fragile', 'surecart-shippo'); ?></option>
                    <option value="LONG_ITEM" <?php selected($profile, 'LONG_ITEM'); ?>><?php esc_html_e('Long Item', 'surecart-shippo'); ?></option>
                    <option value="IRREGULAR" <?php selected($profile, 'IRREGULAR'); ?>><?php esc_html_e('Irregular', 'surecart-shippo'); ?></option>
                </select>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="ship_alone" value="1" <?php checked($ship_alone, '1'); ?> />
                    <?php esc_html_e('Ship alone (requires separate package)', 'surecart-shippo'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="fragile" value="1" <?php checked($fragile, '1'); ?> />
                    <?php esc_html_e('Fragile item', 'surecart-shippo'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="irregular_shape" value="1" <?php checked($irregular, '1'); ?> />
                    <?php esc_html_e('Irregular shape', 'surecart-shippo'); ?>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="manual_only" value="1" <?php checked($manual_only, '1'); ?> />
                    <?php esc_html_e('Manual packaging only (blocks automatic rate calculation)', 'surecart-shippo'); ?>
                </label>
            </p>

            <p>
                <label for="declared_value_usd"><?php esc_html_e('Declared Value (USD)', 'surecart-shippo'); ?></label><br>
                <input type="number" step="0.01" id="declared_value_usd" name="declared_value_usd" value="<?php echo esc_attr($declared_value); ?>" class="regular-text" />
                <span class="description"><?php esc_html_e('For international customs', 'surecart-shippo'); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * Save product meta.
     *
     * @param int $post_id Post ID.
     */
    public function saveMeta($post_id)
    {
        // Check nonce.
        if (!isset($_POST['surecart_shippo_product_nonce']) ||
            !wp_verify_nonce($_POST['surecart_shippo_product_nonce'], 'surecart_shippo_product_meta')) {
            return;
        }

        // Check autosave.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions.
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save metadata.
        $fields = [
            '_shipping_weight_lb' => 'sanitize_text_field',
            '_shipping_dim_in_length' => 'sanitize_text_field',
            '_shipping_dim_in_width' => 'sanitize_text_field',
            '_shipping_dim_in_height' => 'sanitize_text_field',
            '_shipping_profile' => 'sanitize_text_field',
            '_declared_value_usd' => 'sanitize_text_field',
        ];

        foreach ($fields as $field => $sanitize_callback) {
            $key = ltrim($field, '_');
            if (isset($_POST[$key])) {
                update_post_meta($post_id, $field, call_user_func($sanitize_callback, $_POST[$key]));
            }
        }

        // Save checkboxes.
        $checkboxes = ['_ship_alone', '_fragile', '_irregular_shape', '_manual_only'];

        foreach ($checkboxes as $checkbox) {
            $key = ltrim($checkbox, '_');
            update_post_meta($post_id, $checkbox, isset($_POST[$key]) ? '1' : '');
        }
    }
}
