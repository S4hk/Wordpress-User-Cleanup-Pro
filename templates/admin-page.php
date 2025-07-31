<div class="wrap">
    <h1><?php esc_html_e( 'WordPress Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ); ?> <span style="font-size:14px; color:#666;">v<?php echo WBCP_VERSION; ?></span></h1>
    
    <?php
    // Show update notice if available
    $github_updater = new WBCP_GitHub_Updater( WBCP_PLUGIN_FILE, WBCP_GITHUB_REPO, WBCP_VERSION );
    $update_info = $github_updater->check_for_update();
    if ( $update_info && version_compare( WBCP_VERSION, $update_info['new_version'], '<' ) ) :
    ?>
    <div class="notice notice-info">
        <p><strong><?php esc_html_e( 'Update Available!', 'wordpress-bulk-cleanup-pro' ); ?></strong> 
        <?php printf( 
            esc_html__( 'Version %s is available. Current version: %s', 'wordpress-bulk-cleanup-pro' ), 
            $update_info['new_version'], 
            WBCP_VERSION 
        ); ?>
        <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="button button-primary" style="margin-left:10px;">
            <?php esc_html_e( 'Update Now', 'wordpress-bulk-cleanup-pro' ); ?>
        </a>
        </p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="options.php" id="wbcp-settings-form">
        <?php settings_fields( 'wbcp_settings_group' ); ?>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Delete users without first & last name', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <input type="checkbox" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[delete_no_name]" value="1" <?php checked( $this->delete_no_name, 1 ); ?> />
                    <span><?php esc_html_e( 'Users missing both first and last name will be deleted.', 'wordpress-bulk-cleanup-pro' ); ?></span>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Delete users with email domains NOT matching', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <input type="checkbox" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[delete_unlisted_domains]" value="1" <?php checked( $this->delete_unlisted_domains, 1 ); ?> />
                    <input type="text" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[allowed_domains]" value="<?php echo $this->allowed_domains; ?>" style="width:350px;" placeholder="e.g. mycompany.com,partner.org" />
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Delete all users of a specific role', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <?php $this->role_dropdown( $this->delete_role ); ?>
                </td>
            </tr>
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <tr>
                <th><?php esc_html_e( 'Delete WooCommerce orders', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <input type="checkbox" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[delete_wc_orders]" value="1" <?php checked( $this->delete_wc_orders, 1 ); ?> id="delete_wc_orders" />
                    <label for="delete_wc_orders"><?php esc_html_e( 'Delete WooCommerce orders with all related data', 'wordpress-bulk-cleanup-pro' ); ?></label>
                    <div id="wc_order_statuses_wrapper" style="margin-top:10px; <?php echo $this->delete_wc_orders ? '' : 'display:none;'; ?>">
                        <label><?php esc_html_e( 'Order statuses to delete:', 'wordpress-bulk-cleanup-pro' ); ?></label><br>
                        <?php $this->wc_order_status_checkboxes( $this->wc_order_statuses ); ?>
                        <p class="description"><?php esc_html_e( 'Select which order statuses to delete. This will remove orders, order items, order meta, and all related data.', 'wordpress-bulk-cleanup-pro' ); ?></p>
                    </div>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'Delete WooCommerce coupons', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <input type="checkbox" name="<?php echo WBCP_Utils::OPTION_KEY; ?>[delete_wc_coupons]" value="1" <?php checked( $this->delete_wc_coupons, 1 ); ?> id="delete_wc_coupons" />
                    <label for="delete_wc_coupons"><?php esc_html_e( 'Delete WooCommerce coupons with all related data', 'wordpress-bulk-cleanup-pro' ); ?></label>
                    <div id="wc_coupon_statuses_wrapper" style="margin-top:10px; <?php echo $this->delete_wc_coupons ? '' : 'display:none;'; ?>">
                        <label><?php esc_html_e( 'Coupon statuses to delete:', 'wordpress-bulk-cleanup-pro' ); ?></label><br>
                        <?php $this->wc_coupon_status_checkboxes( $this->wc_coupon_statuses ); ?>
                        <p class="description"><?php esc_html_e( 'Select which coupon statuses to delete. This will remove coupons, coupon meta, and all related data.', 'wordpress-bulk-cleanup-pro' ); ?></p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e( 'Batch size for deletion', 'wordpress-bulk-cleanup-pro' ); ?></th>
                <td>
                    <select name="<?php echo WBCP_Utils::OPTION_KEY; ?>[batch_size]">
                        <option value="50" <?php selected( $this->batch_size, 50 ); ?>>50 (Slower, safest)</option>
                        <option value="100" <?php selected( $this->batch_size, 100 ); ?>>100 (Recommended)</option>
                        <option value="250" <?php selected( $this->batch_size, 250 ); ?>>250 (Faster)</option>
                        <option value="500" <?php selected( $this->batch_size, 500 ); ?>>500 (Fast)</option>
                        <option value="1000" <?php selected( $this->batch_size, 1000 ); ?>>1000 (Very fast, advanced)</option>
                    </select>
                    <p class="description"><?php esc_html_e( 'Number of users to delete per batch. Higher numbers are faster but use more server resources.', 'wordpress-bulk-cleanup-pro' ); ?></p>
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php esc_html_e( 'Start Bulk Cleanup', 'wordpress-bulk-cleanup-pro' ); ?></h2>
    <div class="notice notice-warning inline">
        <p><strong><?php esc_html_e( 'Warning:', 'wordpress-bulk-cleanup-pro' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. Please backup your database before proceeding. The process will scan all users and orders first, then delete them in batches.', 'wordpress-bulk-cleanup-pro' ); ?></p>
    </div>
    
    <button id="wbcp-start-deletion" class="button button-primary"><?php esc_html_e( 'Start Deletion Process', 'wordpress-bulk-cleanup-pro' ); ?></button>
    
    <div id="wbcp-progress-wrapper" style="display:none; margin-top:10px;">
        <div id="wbcp-progress-bar" style="background:#e0e0e0; border-radius:5px; height:25px; width:400px; margin-bottom:10px;">
            <div id="wbcp-progress-inner" style="background:#0073aa; height:100%; width:0%; border-radius:5px;"></div>
        </div>
        <span id="wbcp-progress-label"></span>
    </div>
    
    <div id="wbcp-log" style="margin-top:15px; max-height:120px; overflow-y:auto; color:#23282d; font-size:14px;"></div>
</div>

<script>
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
    
    // Toggle WooCommerce coupon status options
    $('#delete_wc_coupons').on('change', function() {
        if ($(this).is(':checked')) {
            $('#wc_coupon_statuses_wrapper').show();
        } else {
            $('#wc_coupon_statuses_wrapper').hide();
        }
    });
    
    function updateProgress() {
        var percent = total > 0 ? Math.round( deleted / total * 100 ) : 0;
        $('#wbcp-progress-inner').css('width', percent + '%');
        if ( isScanning ) {
            $('#wbcp-progress-label').text('Scanning users and orders...');
        } else {
            $('#wbcp-progress-label').text(deleted + ' deleted / ' + total + ' total (' + percent + '%)');
        }
    }
    
    function logMessage(msg) {
        $('#wbcp-log').append($('<div/>').text(msg));
        $('#wbcp-log').scrollTop(99999);
    }
    
    $('#wbcp-start-deletion').on('click', function(e){
        e.preventDefault();
        if ( isRunning ) return;
        
        isRunning = true;
        isScanning = true;
        deleted = 0;
        total = 0;
        
        $('#wbcp-log').empty();
        $('#wbcp-progress-label').text('Starting scan...');
        $('#wbcp-progress-inner').css('width','0%');
        $('#wbcp-progress-wrapper').show();
        $(this).prop('disabled', true);
        
        $.post(
            wbcp_ajax.ajax_url,
            { action:'wbcp_start_deletion', _ajax_nonce: wbcp_ajax.nonce },
            function(resp) {
                if ( ! resp.success ) {
                    logMessage('Failed: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                    isRunning = false;
                    isScanning = false;
                    $('#wbcp-start-deletion').prop('disabled', false);
                    return;
                }
                
                if ( resp.data.scanning ) {
                    batchSize = resp.data.batch_size;
                    logMessage(resp.data.message || 'Starting scan...');
                    runScan();
                }
            }
        ).fail(function() {
            logMessage('Network error occurred.');
            isRunning = false;
            isScanning = false;
            $('#wbcp-start-deletion').prop('disabled', false);
        });
    });
    
    function runScan() {
        if ( ! isScanning ) return;
        
        $.post(
            wbcp_ajax.ajax_url,
            { action:'wbcp_scan_batch', _ajax_nonce: wbcp_ajax.nonce },
            function(resp) {
                if ( ! resp.success ) {
                    logMessage('Scan error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                    isRunning = false;
                    isScanning = false;
                    $('#wbcp-start-deletion').prop('disabled', false);
                    return;
                }
                
                if ( resp.data.scan_complete ) {
                    // Scanning finished
                    isScanning = false;
                    total = resp.data.total || 0;
                    batchSize = resp.data.batch_size || 100;
                    
                    logMessage(resp.data.message || 'Scan completed.');
                    
                    if ( total === 0 ) {
                        $('#wbcp-progress-label').text('No users to delete.');
                        isRunning = false;
                        $('#wbcp-start-deletion').prop('disabled', false);
                    } else {
                        updateProgress();
                        logMessage('Starting deletion process...');
                        setTimeout(runDeletion, 500);
                    }
                } else {
                    // Continue scanning
                    if ( resp.data.message ) {
                        var lastMessage = $('#wbcp-log div:last').text();
                        if ( lastMessage.indexOf('Scanned') === 0 ) {
                            $('#wbcp-log div:last').text(resp.data.message);
                        } else {
                            logMessage(resp.data.message);
                        }
                    }
                    setTimeout(runScan, 100);
                }
            }
        ).fail(function() {
            logMessage('Network error during scan.');
            isRunning = false;
            isScanning = false;
            $('#wbcp-start-deletion').prop('disabled', false);
        });
    }
    
    function runDeletion() {
        if ( isScanning ) return;
        
        $.post(
            wbcp_ajax.ajax_url,
            { action:'wbcp_run_batch', _ajax_nonce: wbcp_ajax.nonce, batch_size: batchSize },
            function(resp) {
                if ( ! resp.success ) {
                    logMessage('Deletion error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                    isRunning = false;
                    $('#wbcp-start-deletion').prop('disabled', false);
                    return;
                }
                
                var deletedNow = resp.data.deleted || 0;
                deleted += deletedNow;
                
                if ( resp.data.log && resp.data.log.length ) {
                    for (var i = 0; i < resp.data.log.length; i++) {
                        logMessage(resp.data.log[i]);
                    }
                }
                
                updateProgress();
                
                if ( resp.data.complete ) {
                    $('#wbcp-progress-label').text('Completed: ' + deleted + ' items deleted.');
                    logMessage('Deletion process completed.');
                    isRunning = false;
                    $('#wbcp-start-deletion').prop('disabled', false);
                } else {
                    setTimeout(runDeletion, 500);
                }
            }
        ).fail(function() {
            logMessage('Network error during deletion.');
            isRunning = false;
            $('#wbcp-start-deletion').prop('disabled', false);
        });
    }
});
</script>

<style>
#wbcp-progress-bar { max-width:400px; margin-top:5px; }
#wbcp-progress-inner { transition: width 0.3s; }
#wbcp-log { background:#f6f6f6; border:1px solid #e0e0e0; border-radius:4px; padding:8px; }
</style>
