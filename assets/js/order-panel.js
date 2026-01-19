/**
 * SureCart Shippo Order Panel JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $panel = $('.surecart-shippo-order-panel');
        var $message = $panel.find('.surecart-shippo-message');

        function showMessage(text, type) {
            $message.removeClass('success error')
                .addClass(type)
                .text(text)
                .show();
        }

        // Purchase label
        $panel.on('click', '[data-action="purchase-label"]', function() {
            var $button = $(this);
            var orderId = $button.data('order-id');

            $button.prop('disabled', true).text(surecartShippoOrder.strings.purchasingLabel);

            $.ajax({
                url: surecartShippoOrder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_purchase_label',
                    nonce: surecartShippoOrder.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(surecartShippoOrder.strings.labelPurchased, 'success');

                        // Reload page to show label info
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Buy/Print Label');
                        showMessage(surecartShippoOrder.strings.purchaseFailed + ' ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Buy/Print Label');
                    showMessage(surecartShippoOrder.strings.purchaseFailed, 'error');
                }
            });
        });

        // Refund label
        $panel.on('click', '[data-action="refund-label"]', function() {
            var $button = $(this);
            var orderId = $button.data('order-id');

            if (!confirm(surecartShippoOrder.strings.confirmRefund)) {
                return;
            }

            $button.prop('disabled', true).text(surecartShippoOrder.strings.refundingLabel);

            $.ajax({
                url: surecartShippoOrder.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_refund_label',
                    nonce: surecartShippoOrder.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(surecartShippoOrder.strings.labelRefunded, 'success');

                        // Reload page to update UI
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $button.prop('disabled', false).text('Refund Label');
                        showMessage(surecartShippoOrder.strings.refundFailed + ' ' + response.data.message, 'error');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Refund Label');
                    showMessage(surecartShippoOrder.strings.refundFailed, 'error');
                }
            });
        });
    });

})(jQuery);
