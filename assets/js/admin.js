jQuery(document).ready(function($) {
    var total = 0, deleted = 0, batchSize = 100, isRunning = false, isScanning = false;
    
    // Toggle WooCommerce order status options
    $('#delete_wc_orders').on('change', function() {
        if ($(this).is(':checked')) {
            $('#wc_order_statuses_wrapper').show();
        } else {
            $('#wc_order_statuses_wrapper').hide();
        }
    });
    
    // ...existing JavaScript from original file...
    
    function updateProgress() {
        var percent = total > 0 ? Math.round( deleted / total * 100 ) : 0;
        $('#wbcp-progress-inner').css('width', percent + '%');
        if ( isScanning ) {
            $('#wbcp-progress-label').text('Scanning users and orders...');
        } else {
            $('#wbcp-progress-label').text(deleted + ' deleted / ' + total + ' total (' + percent + '%)');
        }
    }
    
    // ...rest of JavaScript methods...
});
