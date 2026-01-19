/**
 * SureCart Shippo Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Test Shippo connection
        $('#test-shippo-connection').on('click', function() {
            var $button = $(this);
            var $result = $('#test-connection-result');

            $button.prop('disabled', true);
            $result.removeClass('success error').text(surecartShippoAdmin.strings.testingConnection);

            $.ajax({
                url: surecartShippoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_test_connection',
                    nonce: surecartShippoAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);

                    if (response.success) {
                        $result.addClass('success').text(surecartShippoAdmin.strings.connectionSuccess);
                    } else {
                        $result.addClass('error').text(surecartShippoAdmin.strings.connectionFailed + ' ' + response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $result.addClass('error').text(surecartShippoAdmin.strings.connectionFailed);
                }
            });
        });

        // Seed default boxes
        $('#seed-default-boxes').on('click', function() {
            var $button = $(this);

            $button.prop('disabled', true).text(surecartShippoAdmin.strings.seedingBoxes);

            $.ajax({
                url: surecartShippoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_seed_boxes',
                    nonce: surecartShippoAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Seed Default Boxes');

                    if (response.success) {
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Seed Default Boxes');
                    alert('An error occurred');
                }
            });
        });

        // Clear cache
        $('#clear-shippo-cache').on('click', function() {
            var $button = $(this);

            if (!confirm('Are you sure you want to clear the rate cache?')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: surecartShippoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_clear_cache',
                    nonce: surecartShippoAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);

                    if (response.success) {
                        alert(surecartShippoAdmin.strings.cacheCleared);
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    alert('An error occurred');
                }
            });
        });

        // Clear logs
        $('#clear-shippo-logs').on('click', function() {
            var $button = $(this);

            if (!confirm('Are you sure you want to clear all logs?')) {
                return;
            }

            $button.prop('disabled', true);

            $.ajax({
                url: surecartShippoAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'surecart_shippo_clear_logs',
                    nonce: surecartShippoAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false);

                    if (response.success) {
                        alert(surecartShippoAdmin.strings.logsCleared);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    alert('An error occurred');
                }
            });
        });
    });

})(jQuery);
