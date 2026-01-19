<?php
/**
 * Admin order panel for shipping labels.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Admin;

use SureCartShippo\Services\LabelService;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order panel class.
 */
class OrderPanel
{
    /**
     * Label service instance.
     *
     * @var LabelService
     */
    private $label_service;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->label_service = new LabelService();

        // Add meta box to SureCart orders.
        add_action('add_meta_boxes', [$this, 'addMetaBox']);

        // AJAX handlers.
        add_action('wp_ajax_surecart_shippo_purchase_label', [$this, 'ajaxPurchaseLabel']);
        add_action('wp_ajax_surecart_shippo_refund_label', [$this, 'ajaxRefundLabel']);

        // Enqueue assets.
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Add meta box to order edit screen.
     *
     * @param string $post_type Post type.
     */
    public function addMetaBox($post_type)
    {
        if ($post_type !== 'sc_order') {
            return;
        }

        add_meta_box(
            'surecart_shippo_shipping',
            __('Shipping (Shippo)', 'surecart-shippo'),
            [$this, 'renderMetaBox'],
            'sc_order',
            'side',
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
        $order_id = $post->ID;

        // Get shipping data.
        $provider = get_post_meta($order_id, '_sc_shippo_provider', true);
        $service = get_post_meta($order_id, '_sc_shippo_service_level', true);
        $packaging_status = get_post_meta($order_id, '_sc_shippo_packaging_status', true);

        // Get label data.
        $label_data = $this->label_service->getLabelData($order_id);

        wp_nonce_field('surecart_shippo_order_panel', 'surecart_shippo_nonce');

        ?>
        <div class="surecart-shippo-order-panel">
            <?php if (!empty($provider) && !empty($service)) : ?>
                <p>
                    <strong><?php esc_html_e('Selected Service:', 'surecart-shippo'); ?></strong><br>
                    <?php echo esc_html($provider . ' - ' . $service); ?>
                </p>
            <?php endif; ?>

            <?php if ($packaging_status === 'NEEDS_REVIEW') : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Packaging needs review before purchasing label.', 'surecart-shippo'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($label_data) : ?>
                <div class="surecart-shippo-label-info">
                    <p>
                        <strong><?php esc_html_e('Tracking Number:', 'surecart-shippo'); ?></strong><br>
                        <a href="<?php echo esc_url($label_data['tracking_url']); ?>" target="_blank">
                            <?php echo esc_html($label_data['tracking_number']); ?>
                        </a>
                    </p>

                    <p>
                        <strong><?php esc_html_e('Label Cost:', 'surecart-shippo'); ?></strong><br>
                        <?php echo esc_html('$' . number_format($label_data['cost'], 2)); ?>
                    </p>

                    <p>
                        <a href="<?php echo esc_url($label_data['label_url']); ?>" target="_blank" class="button button-primary">
                            <?php esc_html_e('Open 4×6 PDF Label', 'surecart-shippo'); ?>
                        </a>
                    </p>

                    <?php if ($label_data['refunded']) : ?>
                        <p class="description"><?php esc_html_e('This label has been refunded.', 'surecart-shippo'); ?></p>
                    <?php else : ?>
                        <p>
                            <button type="button" class="button" data-action="refund-label" data-order-id="<?php echo esc_attr($order_id); ?>">
                                <?php esc_html_e('Refund Label', 'surecart-shippo'); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <p>
                    <button type="button" class="button button-primary" data-action="purchase-label" data-order-id="<?php echo esc_attr($order_id); ?>">
                        <?php esc_html_e('Buy/Print Label', 'surecart-shippo'); ?>
                    </button>
                </p>
            <?php endif; ?>

            <div class="surecart-shippo-message" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueueAssets($hook)
    {
        if ($hook !== 'post.php' || get_post_type() !== 'sc_order') {
            return;
        }

        wp_enqueue_script(
            'surecart-shippo-order-panel',
            SURECART_SHIPPO_PLUGIN_URL . 'assets/js/order-panel.js',
            ['jquery'],
            SURECART_SHIPPO_VERSION,
            true
        );

        wp_localize_script('surecart-shippo-order-panel', 'surecartShippoOrder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('surecart_shippo_order'),
            'strings' => [
                'purchasingLabel' => __('Purchasing label...', 'surecart-shippo'),
                'labelPurchased' => __('Label purchased successfully!', 'surecart-shippo'),
                'purchaseFailed' => __('Label purchase failed:', 'surecart-shippo'),
                'refundingLabel' => __('Refunding label...', 'surecart-shippo'),
                'labelRefunded' => __('Label refunded successfully!', 'surecart-shippo'),
                'refundFailed' => __('Label refund failed:', 'surecart-shippo'),
                'confirmRefund' => __('Are you sure you want to refund this label? This action cannot be undone.', 'surecart-shippo'),
            ],
        ]);
    }

    /**
     * AJAX: Purchase label.
     */
    public function ajaxPurchaseLabel()
    {
        check_ajax_referer('surecart_shippo_order', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id || !current_user_can('edit_post', $order_id)) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        $result = $this->label_service->purchaseLabel($order_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Label purchased successfully!', 'surecart-shippo'),
            'label' => $result,
        ]);
    }

    /**
     * AJAX: Refund label.
     */
    public function ajaxRefundLabel()
    {
        check_ajax_referer('surecart_shippo_order', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if (!$order_id || !current_user_can('edit_post', $order_id)) {
            wp_send_json_error(['message' => __('Unauthorized', 'surecart-shippo')]);
        }

        $result = $this->label_service->refundLabel($order_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Label refunded successfully!', 'surecart-shippo')]);
    }
}
