<?php
/*
Plugin Name: WordPress Bulk Cleanup Pro
Description: Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders by status, in safe batches with progress bar. Supports millions of users/orders with memory-efficient scanning. Never deletes administrators.
Version: 1.2.0
Author: OpenAI GPT-4
Requires at least: 5.6
License: GPL2+
GitHub Plugin URI: S4hk/wordpress-bulk-cleanup-pro
GitHub Branch: main
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define( 'WBCP_VERSION', '1.2.0' );
define( 'WBCP_PLUGIN_FILE', __FILE__ );
define( 'WBCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WBCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCP_GITHUB_REPO', 'S4hk/wordpress-bulk-cleanup-pro' );

// Include the updater class
require_once WBCP_PLUGIN_DIR . 'includes/class-github-updater.php';

if ( ! class_exists( 'WBCP_WordPress_Bulk_Cleanup_Pro' ) ) :

class WBCP_WordPress_Bulk_Cleanup_Pro {

    const OPTION_KEY = 'wbcp_settings';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_wbcp_start_deletion', array( $this, 'ajax_start_deletion' ) );
        add_action( 'wp_ajax_wbcp_scan_batch', array( $this, 'ajax_scan_batch' ) );
        add_action( 'wp_ajax_wbcp_run_batch', array( $this, 'ajax_run_batch' ) );
        
        // Initialize GitHub updater
        if ( is_admin() ) {
            new WBCP_GitHub_Updater( WBCP_PLUGIN_FILE, WBCP_GITHUB_REPO, WBCP_VERSION );
        }
        
        // Cleanup transients on plugin deactivation
        register_deactivation_hook( __FILE__, array( $this, 'cleanup_transients' ) );
    }

    public function add_settings_page() {
        add_users_page(
            __( 'WordPress Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ),
            __( 'Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ),
            'manage_options',
            'wordpress-bulk-cleanup-pro',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wbcp_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $output = array();
        $output['delete_no_name'] = ! empty( $input['delete_no_name'] ) ? 1 : 0;
        if ( isset( $input['allowed_domains'] ) ) {
            $domains = explode( ',', $input['allowed_domains'] );
            $domains = array_map( function( $d ) { return strtolower( trim( sanitize_text_field( $d ) ) ); }, $domains );
            $output['allowed_domains'] = implode( ',', array_filter( $domains ) );
        } else {
            $output['allowed_domains'] = '';
        }
        $output['delete_unlisted_domains'] = ! empty( $input['delete_unlisted_domains'] ) ? 1 : 0;
        $output['delete_role'] = ( isset( $input['delete_role'] ) && $input['delete_role'] !== '' ) ? sanitize_text_field( $input['delete_role'] ) : '';
        $output['delete_wc_orders'] = ! empty( $input['delete_wc_orders'] ) ? 1 : 0;
        if ( isset( $input['wc_order_statuses'] ) && is_array( $input['wc_order_statuses'] ) ) {
            $output['wc_order_statuses'] = array_map( 'sanitize_text_field', $input['wc_order_statuses'] );
        } else {
            $output['wc_order_statuses'] = array();
        }
        $output['delete_wc_coupons'] = ! empty( $input['delete_wc_coupons'] ) ? 1 : 0;
        if ( isset( $input['wc_coupon_statuses'] ) && is_array( $input['wc_coupon_statuses'] ) ) {
            $output['wc_coupon_statuses'] = array_map( 'sanitize_text_field', $input['wc_coupon_statuses'] );
        } else {
            $output['wc_coupon_statuses'] = array();
        }
        $batch_size = intval( $input['batch_size'] );
        // Expanded batch size options for better performance with large datasets
        $output['batch_size'] = in_array( $batch_size, array( 50, 100, 250, 500, 1000 ) ) ? $batch_size : 100;
        return $output;
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== 'users_page_user-cleanup-pro' ) return;
        wp_enqueue_script( 'jquery' );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $options = get_option( self::OPTION_KEY );
        $delete_no_name          = ! empty( $options['delete_no_name'] ) ? 1 : 0;
        $delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] ) ? 1 : 0;
        $allowed_domains         = isset( $options['allowed_domains'] ) ? esc_attr( $options['allowed_domains'] ) : '';
        $delete_role             = isset( $options['delete_role'] ) ? esc_attr( $options['delete_role'] ) : '';
        $delete_wc_orders        = ! empty( $options['delete_wc_orders'] ) ? 1 : 0;
        $wc_order_statuses       = isset( $options['wc_order_statuses'] ) ? $options['wc_order_statuses'] : array();
        $delete_wc_coupons       = ! empty( $options['delete_wc_coupons'] ) ? 1 : 0;
        $wc_coupon_statuses      = isset( $options['wc_coupon_statuses'] ) ? $options['wc_coupon_statuses'] : array();
        $batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;
        ?>
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
                <?php
                settings_fields( 'wbcp_settings_group' );
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Delete users without first & last name', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_no_name]" value="1" <?php checked( $delete_no_name, 1 ); ?> />
                            <span><?php esc_html_e( 'Users missing both first and last name will be deleted.', 'wordpress-bulk-cleanup-pro' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Delete users with email domains NOT matching', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_unlisted_domains]" value="1" <?php checked( $delete_unlisted_domains, 1 ); ?> />
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[allowed_domains]" value="<?php echo $allowed_domains; ?>" style="width:350px;" placeholder="e.g. mycompany.com,partner.org" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Delete all users of a specific role', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <?php $this->role_dropdown( $delete_role ); ?>
                        </td>
                    </tr>
                    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Delete WooCommerce orders', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_wc_orders]" value="1" <?php checked( $delete_wc_orders, 1 ); ?> id="delete_wc_orders" />
                            <label for="delete_wc_orders"><?php esc_html_e( 'Delete WooCommerce orders with all related data', 'wordpress-bulk-cleanup-pro' ); ?></label>
                            <div id="wc_order_statuses_wrapper" style="margin-top:10px; <?php echo $delete_wc_orders ? '' : 'display:none;'; ?>">
                                <label><?php esc_html_e( 'Order statuses to delete:', 'wordpress-bulk-cleanup-pro' ); ?></label><br>
                                <?php $this->wc_order_status_checkboxes( $wc_order_statuses ); ?>
                                <p class="description"><?php esc_html_e( 'Select which order statuses to delete. This will remove orders, order items, order meta, and all related data.', 'wordpress-bulk-cleanup-pro' ); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Delete WooCommerce coupons', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_wc_coupons]" value="1" <?php checked( $delete_wc_coupons, 1 ); ?> id="delete_wc_coupons" />
                            <label for="delete_wc_coupons"><?php esc_html_e( 'Delete WooCommerce coupons with all related data', 'wordpress-bulk-cleanup-pro' ); ?></label>
                            <div id="wc_coupon_statuses_wrapper" style="margin-top:10px; <?php echo $delete_wc_coupons ? '' : 'display:none;'; ?>">
                                <label><?php esc_html_e( 'Coupon statuses to delete:', 'wordpress-bulk-cleanup-pro' ); ?></label><br>
                                <?php $this->wc_coupon_status_checkboxes( $wc_coupon_statuses ); ?>
                                <p class="description"><?php esc_html_e( 'Select which coupon statuses to delete. This will remove coupons, coupon meta, and all related data.', 'wordpress-bulk-cleanup-pro' ); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th><?php esc_html_e( 'Batch size for deletion', 'wordpress-bulk-cleanup-pro' ); ?></th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[batch_size]">
                                <option value="50" <?php selected( $batch_size, 50 ); ?>>50 (Slower, safest)</option>
                                <option value="100" <?php selected( $batch_size, 100 ); ?>>100 (Recommended)</option>
                                <option value="250" <?php selected( $batch_size, 250 ); ?>>250 (Faster)</option>
                                <option value="500" <?php selected( $batch_size, 500 ); ?>>500 (Fast)</option>
                                <option value="1000" <?php selected( $batch_size, 1000 ); ?>>1000 (Very fast, advanced)</option>
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
                    ajaxurl,
                    { action:'wbcp_start_deletion', _ajax_nonce:'<?php echo wp_create_nonce( 'wbcp_ajax_nonce' ); ?>' },
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
                    ajaxurl,
                    { action:'wbcp_scan_batch', _ajax_nonce:'<?php echo wp_create_nonce( 'wbcp_ajax_nonce' ); ?>' },
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
                            setTimeout(runScan, 100); // Short delay between scan batches
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
                    ajaxurl,
                    { action:'wbcp_run_batch', _ajax_nonce:'<?php echo wp_create_nonce( 'wbcp_ajax_nonce' ); ?>', batch_size: batchSize },
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
                            $('#wbcp-progress-label').text('Completed: ' + deleted + ' users deleted.');
                            logMessage('Deletion process completed.');
                            isRunning = false;
                            $('#wbcp-start-deletion').prop('disabled', false);
                        } else {
                            setTimeout(runDeletion, 500); // Half-second delay between deletion batches
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
        <?php
    }

    private function role_dropdown( $selected = '' ) {
        global $wp_roles;
        $roles = $wp_roles->roles;
        echo '<select name="' . self::OPTION_KEY . '[delete_role]">';
        echo '<option value="">' . esc_html__( '— Select Role —', 'wordpress-bulk-cleanup-pro' ) . '</option>';
        foreach ( $roles as $role_key => $role ) {
            if ( $role_key === 'administrator' ) continue;
            echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $selected, $role_key, false ) . '>' . esc_html( $role['name'] ) . '</option>';
        }
        echo '</select>';
    }

    private function wc_order_status_checkboxes( $selected = array() ) {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        
        $order_statuses = wc_get_order_statuses();
        
        foreach ( $order_statuses as $status_key => $status_name ) {
            $checked = in_array( $status_key, $selected ) ? 'checked' : '';
            echo '<label style="display:inline-block; margin-right:15px; margin-bottom:5px;">';
            echo '<input type="checkbox" name="' . self::OPTION_KEY . '[wc_order_statuses][]" value="' . esc_attr( $status_key ) . '" ' . $checked . '> ';
            echo esc_html( $status_name );
            echo '</label>';
        }
    }

    private function wc_coupon_status_checkboxes( $selected = array() ) {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        
        $coupon_statuses = array(
            'publish' => __( 'Published (Active)', 'wordpress-bulk-cleanup-pro' ),
            'draft' => __( 'Draft', 'wordpress-bulk-cleanup-pro' ),
            'pending' => __( 'Pending Review', 'wordpress-bulk-cleanup-pro' ),
            'private' => __( 'Private', 'wordpress-bulk-cleanup-pro' ),
            'trash' => __( 'Trash', 'wordpress-bulk-cleanup-pro' )
        );
        
        foreach ( $coupon_statuses as $status_key => $status_name ) {
            $checked = in_array( $status_key, $selected ) ? 'checked' : '';
            echo '<label style="display:inline-block; margin-right:15px; margin-bottom:5px;">';
            echo '<input type="checkbox" name="' . self::OPTION_KEY . '[wc_coupon_statuses][]" value="' . esc_attr( $status_key ) . '" ' . $checked . '> ';
            echo esc_html( $status_name );
            echo '</label>';
        }
    }

    public function ajax_start_deletion() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        
        // Set longer execution time for large operations
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 300 ); // 5 minutes
        }
        
        $options = get_option( self::OPTION_KEY );
        $delete_no_name          = ! empty( $options['delete_no_name'] );
        $delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] );
        $allowed_domains         = isset( $options['allowed_domains'] ) ? explode( ',', strtolower( $options['allowed_domains'] ) ) : array();
        $delete_role             = isset( $options['delete_role'] ) ? sanitize_text_field( $options['delete_role'] ) : '';
        $delete_wc_orders        = ! empty( $options['delete_wc_orders'] );
        $wc_order_statuses       = isset( $options['wc_order_statuses'] ) && is_array( $options['wc_order_statuses'] ) ? $options['wc_order_statuses'] : array();
        $delete_wc_coupons       = ! empty( $options['delete_wc_coupons'] );
        $wc_coupon_statuses      = isset( $options['wc_coupon_statuses'] ) && is_array( $options['wc_coupon_statuses'] ) ? $options['wc_coupon_statuses'] : array();
        $batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;

        // Clean allowed domains array
        $allowed_domains = array_filter( array_map( 'trim', $allowed_domains ) );

        // Start scanning process - store scanning state in transient
        delete_transient( 'wbcp_scan_state' );
        delete_transient( 'wbcp_user_ids_to_delete' );
        delete_transient( 'wbcp_wc_order_ids_to_delete' );
        delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
        
        $scan_state = array(
            'offset' => 0,
            'total_scanned' => 0,
            'total_to_delete' => 0,
            'wc_offset' => 0,
            'wc_total_scanned' => 0,
            'wc_total_to_delete' => 0,
            'wc_coupon_offset' => 0,
            'wc_coupon_total_scanned' => 0,
            'wc_coupon_total_to_delete' => 0,
            'scan_batch_size' => 1000, // Scan in smaller batches for memory efficiency
            'criteria' => array(
                'delete_no_name' => $delete_no_name,
                'delete_unlisted_domains' => $delete_unlisted_domains,
                'allowed_domains' => $allowed_domains,
                'delete_role' => $delete_role,
                'delete_wc_orders' => $delete_wc_orders,
                'wc_order_statuses' => $wc_order_statuses,
                'delete_wc_coupons' => $delete_wc_coupons,
                'wc_coupon_statuses' => $wc_coupon_statuses
            )
        );
        
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scanning' => true,
            'batch_size' => $batch_size,
            'message' => 'Starting user scan...'
        ) );
    }

    public function ajax_scan_batch() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        
        // Set longer execution time
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 60 ); // 1 minute per scan batch
        }
        
        $scan_state = get_transient( 'wbcp_scan_state' );
        if ( ! $scan_state ) {
            wp_send_json_error( array( 'message' => 'Scan state lost. Please restart.' ) );
        }
        
        $criteria = $scan_state['criteria'];
        $offset = $scan_state['offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        // Get batch of users
        $args = array(
            'fields'       => array( 'ID', 'user_email', 'user_login' ),
            'number'       => $scan_batch_size,
            'offset'       => $offset,
            'role__not_in' => array( 'administrator' ),
            'orderby'      => 'ID',
            'order'        => 'ASC'
        );
        
        $user_query = new WP_User_Query( $args );
        $users = $user_query->get_results();
        
        if ( empty( $users ) ) {
            // Users scanning complete, now scan WooCommerce orders if needed
            if ( $criteria['delete_wc_orders'] && class_exists( 'WooCommerce' ) && ! empty( $criteria['wc_order_statuses'] ) ) {
                return $this->scan_wc_orders_batch( $scan_state );
            }
            // Then scan WooCommerce coupons if needed
            elseif ( $criteria['delete_wc_coupons'] && class_exists( 'WooCommerce' ) && ! empty( $criteria['wc_coupon_statuses'] ) ) {
                return $this->scan_wc_coupons_batch( $scan_state );
            }
            
            // All scanning complete
            $existing_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
            $existing_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
            $existing_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
            $total_users = is_array( $existing_user_ids ) ? count( $existing_user_ids ) : 0;
            $total_orders = is_array( $existing_order_ids ) ? count( $existing_order_ids ) : 0;
            $total_coupons = is_array( $existing_coupon_ids ) ? count( $existing_coupon_ids ) : 0;
            $total_to_delete = $total_users + $total_orders + $total_coupons;
            
            delete_transient( 'wbcp_scan_state' );
            
            $message_parts = array();
            if ( $total_users > 0 ) $message_parts[] = "{$total_users} users";
            if ( $total_orders > 0 ) $message_parts[] = "{$total_orders} orders";
            if ( $total_coupons > 0 ) $message_parts[] = "{$total_coupons} coupons";
            
            if ( ! empty( $message_parts ) ) {
                $message = "Scan complete. Found " . implode( ', ', $message_parts ) . " to delete.";
            } else {
                $message = 'Scan complete. No users, orders, or coupons found matching criteria.';
            }
            
            wp_send_json_success( array(
                'scan_complete' => true,
                'total' => $total_to_delete,
                'batch_size' => get_option( self::OPTION_KEY )['batch_size'] ?? 100,
                'message' => $message
            ) );
        }
        
        $current_batch_ids = array();
        
        foreach ( $users as $user ) {
            $user_id = $user->ID;
            
            // Double-check not admin (safety measure)
            $user_obj = get_userdata( $user_id );
            if ( ! $user_obj || in_array( 'administrator', (array)$user_obj->roles, true ) ) {
                continue;
            }
            
            $should_delete = false;
            
            // Check role deletion criteria first (most efficient)
            if ( $criteria['delete_role'] && in_array( $criteria['delete_role'], (array)$user_obj->roles, true ) ) {
                $should_delete = true;
            }
            // Check name criteria
            elseif ( $criteria['delete_no_name'] ) {
                $first = get_user_meta( $user_id, 'first_name', true );
                $last  = get_user_meta( $user_id, 'last_name', true );
                if ( ( empty( $first ) || trim( $first ) === '' ) && ( empty( $last ) || trim( $last ) === '' ) ) {
                    $should_delete = true;
                }
            }
            
            // Check domain criteria
            if ( ! $should_delete && $criteria['delete_unlisted_domains'] && ! empty( $criteria['allowed_domains'] ) ) {
                $email = strtolower( $user->user_email );
                $valid = false;
                foreach ( $criteria['allowed_domains'] as $domain ) {
                    if ( $domain === '' ) continue;
                    if ( substr( $email, -strlen( '@' . $domain ) ) === '@' . $domain ) {
                        $valid = true;
                        break;
                    }
                }
                if ( ! $valid ) {
                    $should_delete = true;
                }
            }
            
            if ( $should_delete ) {
                $current_batch_ids[] = $user_id;
            }
        }
        
        // Store found IDs
        if ( ! empty( $current_batch_ids ) ) {
            $existing_ids = get_transient( 'wbcp_user_ids_to_delete' );
            if ( ! is_array( $existing_ids ) ) {
                $existing_ids = array();
            }
            $existing_ids = array_merge( $existing_ids, $current_batch_ids );
            set_transient( 'wbcp_user_ids_to_delete', $existing_ids, HOUR_IN_SECONDS );
        }
        
        // Update scan state
        $scan_state['offset'] += $scan_batch_size;
        $scan_state['total_scanned'] += count( $users );
        $scan_state['total_to_delete'] += count( $current_batch_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $scan_state['total_scanned'],
            'found' => $scan_state['total_to_delete'],
            'batch_found' => count( $current_batch_ids ),
            'message' => "Scanned {$scan_state['total_scanned']} users, found {$scan_state['total_to_delete']} to delete..."
        ) );
    }

    private function scan_wc_orders_batch( $scan_state ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $criteria = $scan_state['criteria'];
        $wc_offset = $scan_state['wc_offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        $args = array(
            'limit' => $scan_batch_size,
            'offset' => $wc_offset,
            'status' => $criteria['wc_order_statuses'],
            'return' => 'ids'
        );
        
        $order_ids = wc_get_orders( $args );
        
        if ( empty( $order_ids ) ) {
            // WooCommerce orders scanning complete, check for coupons
            if ( $criteria['delete_wc_coupons'] && ! empty( $criteria['wc_coupon_statuses'] ) ) {
                return $this->scan_wc_coupons_batch( $scan_state );
            }
            
            // All scanning complete
            $existing_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
            $existing_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
            $existing_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
            $total_users = is_array( $existing_user_ids ) ? count( $existing_user_ids ) : 0;
            $total_orders = is_array( $existing_order_ids ) ? count( $existing_order_ids ) : 0;
            $total_coupons = is_array( $existing_coupon_ids ) ? count( $existing_coupon_ids ) : 0;
            $total_to_delete = $total_users + $total_orders + $total_coupons;
            
            delete_transient( 'wbcp_scan_state' );
            
            $message_parts = array();
            if ( $total_users > 0 ) $message_parts[] = "{$total_users} users";
            if ( $total_orders > 0 ) $message_parts[] = "{$total_orders} orders";
            if ( $total_coupons > 0 ) $message_parts[] = "{$total_coupons} coupons";
            
            if ( ! empty( $message_parts ) ) {
                $message = "Scan complete. Found " . implode( ', ', $message_parts ) . " to delete.";
            } else {
                $message = 'Scan complete. No users, orders, or coupons found matching criteria.';
            }
            
            wp_send_json_success( array(
                'scan_complete' => true,
                'total' => $total_to_delete,
                'batch_size' => get_option( self::OPTION_KEY )['batch_size'] ?? 100,
                'message' => $message
            ) );
        }
        
        // Store found order IDs
        $existing_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
        if ( ! is_array( $existing_order_ids ) ) {
            $existing_order_ids = array();
        }
        $existing_order_ids = array_merge( $existing_order_ids, $order_ids );
        set_transient( 'wbcp_wc_order_ids_to_delete', $existing_order_ids, HOUR_IN_SECONDS );
        
        // Update scan state
        $scan_state['wc_offset'] += $scan_batch_size;
        $scan_state['wc_total_scanned'] += count( $order_ids );
        $scan_state['wc_total_to_delete'] += count( $order_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        $total_scanned = $scan_state['total_scanned'] + $scan_state['wc_total_scanned'];
        $total_found = $scan_state['total_to_delete'] + $scan_state['wc_total_to_delete'];
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $total_scanned,
            'found' => $total_found,
            'batch_found' => count( $order_ids ),
            'message' => "Scanned {$scan_state['total_scanned']} users and {$scan_state['wc_total_scanned']} orders, found {$scan_state['total_to_delete']} users and {$scan_state['wc_total_to_delete']} orders to delete..."
        ) );
    }

    private function scan_wc_coupons_batch( $scan_state ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $criteria = $scan_state['criteria'];
        $wc_coupon_offset = $scan_state['wc_coupon_offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        $args = array(
            'post_type' => 'shop_coupon',
            'post_status' => $criteria['wc_coupon_statuses'],
            'posts_per_page' => $scan_batch_size,
            'offset' => $wc_coupon_offset,
            'fields' => 'ids'
        );
        
        $coupon_query = new WP_Query( $args );
        $coupon_ids = $coupon_query->posts;
        
        if ( empty( $coupon_ids ) ) {
            // WooCommerce coupons scanning complete
            $existing_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
            $existing_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
            $existing_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
            $total_users = is_array( $existing_user_ids ) ? count( $existing_user_ids ) : 0;
            $total_orders = is_array( $existing_order_ids ) ? count( $existing_order_ids ) : 0;
            $total_coupons = is_array( $existing_coupon_ids ) ? count( $existing_coupon_ids ) : 0;
            $total_to_delete = $total_users + $total_orders + $total_coupons;
            
            delete_transient( 'wbcp_scan_state' );
            
            $message_parts = array();
            if ( $total_users > 0 ) $message_parts[] = "{$total_users} users";
            if ( $total_orders > 0 ) $message_parts[] = "{$total_orders} orders";
            if ( $total_coupons > 0 ) $message_parts[] = "{$total_coupons} coupons";
            
            if ( ! empty( $message_parts ) ) {
                $message = "Scan complete. Found " . implode( ', ', $message_parts ) . " to delete.";
            } else {
                $message = 'Scan complete. No users, orders, or coupons found matching criteria.';
            }
            
            wp_send_json_success( array(
                'scan_complete' => true,
                'total' => $total_to_delete,
                'batch_size' => get_option( self::OPTION_KEY )['batch_size'] ?? 100,
                'message' => $message
            ) );
        }
        
        // Store found coupon IDs
        $existing_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        if ( ! is_array( $existing_coupon_ids ) ) {
            $existing_coupon_ids = array();
        }
        $existing_coupon_ids = array_merge( $existing_coupon_ids, $coupon_ids );
        set_transient( 'wbcp_wc_coupon_ids_to_delete', $existing_coupon_ids, HOUR_IN_SECONDS );
        
        // Update scan state
        $scan_state['wc_coupon_offset'] += $scan_batch_size;
        $scan_state['wc_coupon_total_scanned'] += count( $coupon_ids );
        $scan_state['wc_coupon_total_to_delete'] += count( $coupon_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        $total_scanned = $scan_state['total_scanned'] + $scan_state['wc_total_scanned'] + $scan_state['wc_coupon_total_scanned'];
        $total_found = $scan_state['total_to_delete'] + $scan_state['wc_total_to_delete'] + $scan_state['wc_coupon_total_to_delete'];
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $total_scanned,
            'found' => $total_found,
            'batch_found' => count( $coupon_ids ),
            'message' => "Scanned {$scan_state['total_scanned']} users, {$scan_state['wc_total_scanned']} orders, and {$scan_state['wc_coupon_total_scanned']} coupons, found {$scan_state['total_to_delete']} users, {$scan_state['wc_total_to_delete']} orders, and {$scan_state['wc_coupon_total_to_delete']} coupons to delete..."
        ) );
    }

    public function ajax_run_batch() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        
        // Set execution time limit
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 120 ); // 2 minutes per deletion batch
        }
        
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;
        
        // Get IDs from transient storage - prioritize users first, then orders, then coupons
        $all_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
        $all_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
        $all_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        
        if ( is_array( $all_user_ids ) && ! empty( $all_user_ids ) ) {
            // Process users first
            return $this->delete_users_batch( $all_user_ids, $batch_size );
        } elseif ( is_array( $all_order_ids ) && ! empty( $all_order_ids ) ) {
            // Process orders after users are done
            return $this->delete_orders_batch( $all_order_ids, $batch_size );
        } elseif ( is_array( $all_coupon_ids ) && ! empty( $all_coupon_ids ) ) {
            // Process coupons after orders are done
            return $this->delete_coupons_batch( $all_coupon_ids, $batch_size );
        } else {
            // Nothing left to delete
            wp_send_json_success( array(
                'deleted' => 0,
                'remaining' => 0,
                'log' => array( 'No users, orders, or coupons left to delete.' ),
                'complete' => true
            ) );
        }
    }

    private function delete_users_batch( $all_user_ids, $batch_size ) {
        $to_delete = array_slice( $all_user_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_user_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                $log[] = "User ID {$user_id} not found (already deleted?).";
                continue;
            }
            
            // Final safety check - never delete administrators
            if ( in_array( 'administrator', (array)$user->roles, true ) ) {
                $log[] = "SAFETY: Skipped admin user ID {$user_id}.";
                continue;
            }
            
            // Perform deletion with error handling
            try {
                // Delete user metadata first
                global $wpdb;
                $wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );
                
                // Delete the user
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $result = wp_delete_user( $user_id );
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted user ID {$user_id} ({$user->user_login}).";
                } else {
                    $log[] = "Failed to delete user ID {$user_id} ({$user->user_login}).";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting user ID {$user_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $user_id, 'users' );
            wp_cache_delete( $user_id, 'user_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_user_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_user_ids_to_delete' );
        }
        
        // Check if there are orders or coupons to delete after users are done
        $remaining_orders = get_transient( 'wbcp_wc_order_ids_to_delete' );
        $remaining_coupons = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        $total_remaining = count( $remaining_ids ) + 
                          ( is_array( $remaining_orders ) ? count( $remaining_orders ) : 0 ) +
                          ( is_array( $remaining_coupons ) ? count( $remaining_coupons ) : 0 );
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => $total_remaining,
            'log' => $log,
            'complete' => $total_remaining === 0
        ) );
    }

    private function delete_orders_batch( $all_order_ids, $batch_size ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $to_delete = array_slice( $all_order_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_order_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $order_id ) {
            try {
                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                    $log[] = "Order ID {$order_id} not found (already deleted?).";
                    continue;
                }
                
                // Delete order completely with all metadata
                $result = $order->delete( true ); // true = force delete
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted order ID {$order_id}.";
                } else {
                    $log[] = "Failed to delete order ID {$order_id}.";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting order ID {$order_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $order_id, 'posts' );
            wp_cache_delete( $order_id, 'post_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_wc_order_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_wc_order_ids_to_delete' );
        }
        
        // Check if there are coupons to delete after orders are done
        $remaining_coupons = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        $total_remaining = count( $remaining_ids ) + ( is_array( $remaining_coupons ) ? count( $remaining_coupons ) : 0 );
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => $total_remaining,
            'log' => $log,
            'complete' => $total_remaining === 0
        ) );
    }

    private function delete_coupons_batch( $all_coupon_ids, $batch_size ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $to_delete = array_slice( $all_coupon_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_coupon_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $coupon_id ) {
            try {
                $coupon = new WC_Coupon( $coupon_id );
                if ( ! $coupon->get_id() ) {
                    $log[] = "Coupon ID {$coupon_id} not found (already deleted?).";
                    continue;
                }
                
                // Get coupon code for logging
                $coupon_code = $coupon->get_code();
                
                // Delete coupon completely with all metadata
                $result = wp_delete_post( $coupon_id, true ); // true = force delete, bypass trash
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted coupon ID {$coupon_id} ('{$coupon_code}').";
                } else {
                    $log[] = "Failed to delete coupon ID {$coupon_id} ('{$coupon_code}').";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting coupon ID {$coupon_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $coupon_id, 'posts' );
            wp_cache_delete( $coupon_id, 'post_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_wc_coupon_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
        }
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => count( $remaining_ids ),
            'log' => $log,
            'complete' => empty( $remaining_ids )
        ) );
    }

    public function cleanup_transients() {
        delete_transient( 'wbcp_scan_state' );
        delete_transient( 'wbcp_user_ids_to_delete' );
        delete_transient( 'wbcp_wc_order_ids_to_delete' );
        delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
    }

}
endif;

new WBCP_WordPress_Bulk_Cleanup_Pro();