/**
 * WP Weebly Stripe - Admin JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Product sync button
        $('.wpwa-sync-product-btn').on('click', function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var productId = $btn.data('product-id');
            
            if (!confirm(wpwaStripe.confirmSync)) {
                return;
            }
            
            $btn.prop('disabled', true).text('Syncing...');
            
            $.post(wpwaStripe.ajaxurl, {
                action: 'wpwa_stripe_sync_product',
                nonce: wpwaStripe.nonce,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    alert('Product synced successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text('Sync to Stripe');
                }
            }).fail(function() {
                alert('Request failed. Please try again.');
                $btn.prop('disabled', false).text('Sync to Stripe');
            });
        });
        
        // Toggle recurring fields
        $('#wpwa_is_recurring').on('change', function() {
            if ($(this).is(':checked')) {
                $('.wpwa-recurring-fields').slideDown();
            } else {
                $('.wpwa-recurring-fields').slideUp();
            }
        }).trigger('change');
        
        // Copy to clipboard
        $('.wpwa-copy-btn').on('click', function(e) {
            e.preventDefault();
            
            var text = $(this).data('copy');
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied to clipboard!');
                });
            } else {
                // Fallback for older browsers
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                alert('Copied to clipboard!');
            }
        });
    });
    
})(jQuery);