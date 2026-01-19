<?php
/**
 * Label service for purchasing and managing shipping labels.
 *
 * @package SureCartShippo
 */

namespace SureCartShippo\Services;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Label service class.
 */
class LabelService
{
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
     * Lock timeout in seconds.
     *
     * @var int
     */
    const LOCK_TIMEOUT = 60;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->shippo_client = surecart_shippo()->shippo_client;
        $this->logger = surecart_shippo()->logger;
    }

    /**
     * Purchase a shipping label for an order.
     *
     * @param int   $order_id Order ID.
     * @param array $options Purchase options.
     * @return array|WP_Error Label data or error.
     */
    public function purchaseLabel($order_id, $options = [])
    {
        $this->logger->info('Attempting to purchase label', ['order_id' => $order_id]);

        // Check if order is paid.
        if (!$this->isOrderPaid($order_id)) {
            return new \WP_Error(
                'order_not_paid',
                __('Cannot purchase label for unpaid order.', 'surecart-shippo')
            );
        }

        // Check for existing transaction (idempotency).
        $existing_transaction_id = get_post_meta($order_id, '_sc_shippo_transaction_id', true);

        if (!empty($existing_transaction_id)) {
            $this->logger->info('Found existing transaction', ['transaction_id' => $existing_transaction_id]);

            // Verify transaction is successful.
            $transaction = $this->shippo_client->getTransaction($existing_transaction_id);

            if (!is_wp_error($transaction) && $transaction['status'] === 'SUCCESS') {
                $this->logger->info('Reusing existing successful transaction');
                return $this->formatTransactionData($transaction);
            }
        }

        // Acquire lock to prevent double-clicks.
        if (!$this->acquireLock($order_id)) {
            return new \WP_Error(
                'purchase_in_progress',
                __('Label purchase already in progress. Please wait.', 'surecart-shippo')
            );
        }

        try {
            // Get rate ID.
            $rate_id = $options['rate_id'] ?? get_post_meta($order_id, '_sc_shippo_selected_rate_id', true);

            if (empty($rate_id)) {
                throw new \Exception(__('No shipping rate selected for this order.', 'surecart-shippo'));
            }

            // Build transaction data.
            $transaction_data = [
                'rate' => $rate_id,
                'label_file_type' => 'PDF_4x6',
                'async' => false,
            ];

            // Add customs declaration if needed.
            if (!empty($options['customs_declaration'])) {
                $transaction_data['customs_declaration'] = $options['customs_declaration'];
            }

            // Create transaction.
            $transaction = $this->shippo_client->createTransaction($transaction_data);

            if (is_wp_error($transaction)) {
                $this->logger->error('Failed to create transaction', [
                    'error' => $transaction->get_error_message(),
                    'order_id' => $order_id,
                ]);
                throw new \Exception($transaction->get_error_message());
            }

            // Check transaction status.
            if ($transaction['status'] !== 'SUCCESS') {
                $error_message = $transaction['messages'][0]['text'] ?? __('Transaction failed', 'surecart-shippo');
                $this->logger->error('Transaction not successful', [
                    'status' => $transaction['status'],
                    'messages' => $transaction['messages'] ?? [],
                ]);
                throw new \Exception($error_message);
            }

            // Store transaction data.
            $this->storeTransactionData($order_id, $transaction);

            // Create audit log entry.
            $this->createAuditLog($order_id, 'label_purchased', [
                'transaction_id' => $transaction['object_id'],
                'tracking_number' => $transaction['tracking_number'],
                'carrier' => $transaction['rate']['provider'] ?? '',
                'service' => $transaction['rate']['servicelevel']['name'] ?? '',
                'cost' => $transaction['rate']['amount'] ?? 0,
            ]);

            $this->logger->info('Label purchased successfully', [
                'order_id' => $order_id,
                'transaction_id' => $transaction['object_id'],
                'tracking_number' => $transaction['tracking_number'],
            ]);

            return $this->formatTransactionData($transaction);
        } catch (\Exception $e) {
            $this->logger->error('Label purchase failed', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
            ]);
            return new \WP_Error('purchase_failed', $e->getMessage());
        } finally {
            // Always release lock.
            $this->releaseLock($order_id);
        }
    }

    /**
     * Refund/void a label.
     *
     * @param int $order_id Order ID.
     * @return bool|WP_Error True on success, error on failure.
     */
    public function refundLabel($order_id)
    {
        $transaction_id = get_post_meta($order_id, '_sc_shippo_transaction_id', true);

        if (empty($transaction_id)) {
            return new \WP_Error(
                'no_transaction',
                __('No transaction found for this order.', 'surecart-shippo')
            );
        }

        $result = $this->shippo_client->refundTransaction($transaction_id);

        if (is_wp_error($result)) {
            $this->logger->error('Label refund failed', [
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
                'error' => $result->get_error_message(),
            ]);
            return $result;
        }

        // Update order meta.
        update_post_meta($order_id, '_sc_shippo_label_refunded', true);
        update_post_meta($order_id, '_sc_shippo_label_refund_date', current_time('mysql'));

        // Create audit log entry.
        $this->createAuditLog($order_id, 'label_refunded', [
            'transaction_id' => $transaction_id,
        ]);

        $this->logger->info('Label refunded successfully', [
            'order_id' => $order_id,
            'transaction_id' => $transaction_id,
        ]);

        return true;
    }

    /**
     * Get label data for an order.
     *
     * @param int $order_id Order ID.
     * @return array|null Label data or null if no label.
     */
    public function getLabelData($order_id)
    {
        $transaction_id = get_post_meta($order_id, '_sc_shippo_transaction_id', true);

        if (empty($transaction_id)) {
            return null;
        }

        return [
            'transaction_id' => $transaction_id,
            'label_url' => get_post_meta($order_id, '_sc_shippo_label_url', true),
            'tracking_number' => get_post_meta($order_id, '_sc_shippo_tracking_number', true),
            'tracking_url' => get_post_meta($order_id, '_sc_shippo_tracking_url', true),
            'carrier' => get_post_meta($order_id, '_sc_shippo_carrier', true),
            'service' => get_post_meta($order_id, '_sc_shippo_service', true),
            'cost' => get_post_meta($order_id, '_sc_shippo_label_cost', true),
            'refunded' => (bool) get_post_meta($order_id, '_sc_shippo_label_refunded', true),
            'purchase_date' => get_post_meta($order_id, '_sc_shippo_label_purchase_date', true),
        ];
    }

    /**
     * Check if order is paid.
     *
     * @param int $order_id Order ID.
     * @return bool
     */
    private function isOrderPaid($order_id)
    {
        // This will depend on SureCart's order status structure.
        // For now, use a simple check.
        $order_status = get_post_meta($order_id, '_sc_order_status', true);
        return in_array($order_status, ['paid', 'processing', 'completed'], true);
    }

    /**
     * Acquire purchase lock.
     *
     * @param int $order_id Order ID.
     * @return bool True if lock acquired.
     */
    private function acquireLock($order_id)
    {
        $lock_key = 'surecart_shippo_purchase_lock_' . $order_id;

        // Try to set transient with NX flag (only if not exists).
        $locked = get_transient($lock_key);

        if ($locked !== false) {
            return false;
        }

        return set_transient($lock_key, time(), self::LOCK_TIMEOUT);
    }

    /**
     * Release purchase lock.
     *
     * @param int $order_id Order ID.
     */
    private function releaseLock($order_id)
    {
        $lock_key = 'surecart_shippo_purchase_lock_' . $order_id;
        delete_transient($lock_key);
    }

    /**
     * Store transaction data in order meta.
     *
     * @param int   $order_id Order ID.
     * @param array $transaction Transaction data.
     */
    private function storeTransactionData($order_id, $transaction)
    {
        update_post_meta($order_id, '_sc_shippo_transaction_id', $transaction['object_id']);
        update_post_meta($order_id, '_sc_shippo_label_url', $transaction['label_url']);
        update_post_meta($order_id, '_sc_shippo_tracking_number', $transaction['tracking_number']);
        update_post_meta($order_id, '_sc_shippo_tracking_url', $transaction['tracking_url_provider']);
        update_post_meta($order_id, '_sc_shippo_carrier', $transaction['rate']['provider'] ?? '');
        update_post_meta($order_id, '_sc_shippo_service', $transaction['rate']['servicelevel']['name'] ?? '');
        update_post_meta($order_id, '_sc_shippo_label_cost', $transaction['rate']['amount'] ?? 0);
        update_post_meta($order_id, '_sc_shippo_label_purchase_date', current_time('mysql'));
        update_post_meta($order_id, '_sc_shippo_label_refunded', false);
    }

    /**
     * Format transaction data for response.
     *
     * @param array $transaction Transaction data.
     * @return array
     */
    private function formatTransactionData($transaction)
    {
        return [
            'transaction_id' => $transaction['object_id'],
            'label_url' => $transaction['label_url'],
            'tracking_number' => $transaction['tracking_number'],
            'tracking_url' => $transaction['tracking_url_provider'],
            'carrier' => $transaction['rate']['provider'] ?? '',
            'service' => $transaction['rate']['servicelevel']['name'] ?? '',
            'cost' => $transaction['rate']['amount'] ?? 0,
            'currency' => $transaction['rate']['currency'] ?? 'USD',
            'status' => $transaction['status'],
        ];
    }

    /**
     * Create audit log entry.
     *
     * @param int    $order_id Order ID.
     * @param string $action Action performed.
     * @param array  $data Additional data.
     */
    private function createAuditLog($order_id, $action, $data = [])
    {
        $user = wp_get_current_user();

        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'action' => $action,
            'data' => $data,
        ];

        $logs = get_post_meta($order_id, '_sc_shippo_audit_log', true);
        if (!is_array($logs)) {
            $logs = [];
        }

        array_unshift($logs, $log_entry);

        update_post_meta($order_id, '_sc_shippo_audit_log', $logs);
    }
}
