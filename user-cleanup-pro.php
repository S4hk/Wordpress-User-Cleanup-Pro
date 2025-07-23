<?php
/*
Plugin Name: User Cleanup Pro
Description: Advanced user cleanup tool for administrators. Delete users by missing names, email domains, or roles, in safe batches with progress bar. Supports millions of users with memory-efficient scanning. Never deletes administrators.
Version: 1.1.0
Author: OpenAI GPT-4
Requires at least: 5.6
License: GPL2+
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'UCP_User_Cleanup_Pro' ) ) :

class UCP_User_Cleanup_Pro {

    const OPTION_KEY = 'ucp_settings';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_ucp_start_deletion', array( $this, 'ajax_start_deletion' ) );
        add_action( 'wp_ajax_ucp_scan_batch', array( $this, 'ajax_scan_batch' ) );
        add_action( 'wp_ajax_ucp_run_batch', array( $this, 'ajax_run_batch' ) );
        
        // Cleanup transients on plugin deactivation
        register_deactivation_hook( __FILE__, array( $this, 'cleanup_transients' ) );
    }

    public function add_settings_page() {
        add_users_page(
            __( 'User Cleanup Pro', 'user-cleanup-pro' ),
            __( 'User Cleanup Pro', 'user-cleanup-pro' ),
            'manage_options',
            'user-cleanup-pro',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'ucp_settings_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
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
        $batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'User Cleanup Pro', 'user-cleanup-pro' ); ?></h1>
            <form method="post" action="options.php" id="ucp-settings-form">
                <?php
                settings_fields( 'ucp_settings_group' );
                ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Delete users without first & last name', 'user-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_no_name]" value="1" <?php checked( $delete_no_name, 1 ); ?> />
                            <span><?php esc_html_e( 'Users missing both first and last name will be deleted.', 'user-cleanup-pro' ); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Delete users with email domains NOT matching', 'user-cleanup-pro' ); ?></th>
                        <td>
                            <input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[delete_unlisted_domains]" value="1" <?php checked( $delete_unlisted_domains, 1 ); ?> />
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[allowed_domains]" value="<?php echo $allowed_domains; ?>" style="width:350px;" placeholder="e.g. mycompany.com,partner.org" />
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Delete all users of a specific role', 'user-cleanup-pro' ); ?></th>
                        <td>
                            <?php $this->role_dropdown( $delete_role ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Batch size for deletion', 'user-cleanup-pro' ); ?></th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[batch_size]">
                                <option value="50" <?php selected( $batch_size, 50 ); ?>>50 (Slower, safest)</option>
                                <option value="100" <?php selected( $batch_size, 100 ); ?>>100 (Recommended)</option>
                                <option value="250" <?php selected( $batch_size, 250 ); ?>>250 (Faster)</option>
                                <option value="500" <?php selected( $batch_size, 500 ); ?>>500 (Fast)</option>
                                <option value="1000" <?php selected( $batch_size, 1000 ); ?>>1000 (Very fast, advanced)</option>
                            </select>
                            <p class="description"><?php esc_html_e( 'Number of users to delete per batch. Higher numbers are faster but use more server resources.', 'user-cleanup-pro' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2><?php esc_html_e( 'Start User Cleanup', 'user-cleanup-pro' ); ?></h2>
            <div class="notice notice-warning inline">
                <p><strong><?php esc_html_e( 'Warning:', 'user-cleanup-pro' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. Please backup your database before proceeding. The process will scan all users first, then delete them in batches.', 'user-cleanup-pro' ); ?></p>
            </div>
            <button id="ucp-start-deletion" class="button button-primary"><?php esc_html_e( 'Start Deletion Process', 'user-cleanup-pro' ); ?></button>
            <div id="ucp-progress-wrapper" style="display:none; margin-top:10px;">
                <div id="ucp-progress-bar" style="background:#e0e0e0; border-radius:5px; height:25px; width:400px; margin-bottom:10px;">
                    <div id="ucp-progress-inner" style="background:#0073aa; height:100%; width:0%; border-radius:5px;"></div>
                </div>
                <span id="ucp-progress-label"></span>
            </div>
            <div id="ucp-log" style="margin-top:15px; max-height:120px; overflow-y:auto; color:#23282d; font-size:14px;"></div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var total = 0, deleted = 0, batchSize = 100, isRunning = false, isScanning = false;
            
            function updateProgress() {
                var percent = total > 0 ? Math.round( deleted / total * 100 ) : 0;
                $('#ucp-progress-inner').css('width', percent + '%');
                if ( isScanning ) {
                    $('#ucp-progress-label').text('Scanning users...');
                } else {
                    $('#ucp-progress-label').text(deleted + ' deleted / ' + total + ' total (' + percent + '%)');
                }
            }
            
            function logMessage(msg) {
                $('#ucp-log').append($('<div/>').text(msg));
                $('#ucp-log').scrollTop(99999);
            }
            
            $('#ucp-start-deletion').on('click', function(e){
                e.preventDefault();
                if ( isRunning ) return;
                
                isRunning = true;
                isScanning = true;
                deleted = 0;
                total = 0;
                
                $('#ucp-log').empty();
                $('#ucp-progress-label').text('Starting scan...');
                $('#ucp-progress-inner').css('width','0%');
                $('#ucp-progress-wrapper').show();
                $(this).prop('disabled', true);
                
                $.post(
                    ajaxurl,
                    { action:'ucp_start_deletion', _ajax_nonce:'<?php echo wp_create_nonce( 'ucp_ajax_nonce' ); ?>' },
                    function(resp) {
                        if ( ! resp.success ) {
                            logMessage('Failed: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                            isRunning = false;
                            isScanning = false;
                            $('#ucp-start-deletion').prop('disabled', false);
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
                    $('#ucp-start-deletion').prop('disabled', false);
                });
            });
            
            function runScan() {
                if ( ! isScanning ) return;
                
                $.post(
                    ajaxurl,
                    { action:'ucp_scan_batch', _ajax_nonce:'<?php echo wp_create_nonce( 'ucp_ajax_nonce' ); ?>' },
                    function(resp) {
                        if ( ! resp.success ) {
                            logMessage('Scan error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                            isRunning = false;
                            isScanning = false;
                            $('#ucp-start-deletion').prop('disabled', false);
                            return;
                        }
                        
                        if ( resp.data.scan_complete ) {
                            // Scanning finished
                            isScanning = false;
                            total = resp.data.total || 0;
                            batchSize = resp.data.batch_size || 100;
                            
                            logMessage(resp.data.message || 'Scan completed.');
                            
                            if ( total === 0 ) {
                                $('#ucp-progress-label').text('No users to delete.');
                                isRunning = false;
                                $('#ucp-start-deletion').prop('disabled', false);
                            } else {
                                updateProgress();
                                logMessage('Starting deletion process...');
                                setTimeout(runDeletion, 500);
                            }
                        } else {
                            // Continue scanning
                            if ( resp.data.message ) {
                                var lastMessage = $('#ucp-log div:last').text();
                                if ( lastMessage.indexOf('Scanned') === 0 ) {
                                    $('#ucp-log div:last').text(resp.data.message);
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
                    $('#ucp-start-deletion').prop('disabled', false);
                });
            }
            
            function runDeletion() {
                if ( isScanning ) return;
                
                $.post(
                    ajaxurl,
                    { action:'ucp_run_batch', _ajax_nonce:'<?php echo wp_create_nonce( 'ucp_ajax_nonce' ); ?>', batch_size: batchSize },
                    function(resp) {
                        if ( ! resp.success ) {
                            logMessage('Deletion error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                            isRunning = false;
                            $('#ucp-start-deletion').prop('disabled', false);
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
                            $('#ucp-progress-label').text('Completed: ' + deleted + ' users deleted.');
                            logMessage('Deletion process completed.');
                            isRunning = false;
                            $('#ucp-start-deletion').prop('disabled', false);
                        } else {
                            setTimeout(runDeletion, 500); // Half-second delay between deletion batches
                        }
                    }
                ).fail(function() {
                    logMessage('Network error during deletion.');
                    isRunning = false;
                    $('#ucp-start-deletion').prop('disabled', false);
                });
            }
        });
        </script>
        <style>
        #ucp-progress-bar { max-width:400px; margin-top:5px; }
        #ucp-progress-inner { transition: width 0.3s; }
        #ucp-log { background:#f6f6f6; border:1px solid #e0e0e0; border-radius:4px; padding:8px; }
        </style>
        <?php
    }

    private function role_dropdown( $selected = '' ) {
        global $wp_roles;
        $roles = $wp_roles->roles;
        echo '<select name="' . self::OPTION_KEY . '[delete_role]">';
        echo '<option value="">' . esc_html__( '— Select Role —', 'user-cleanup-pro' ) . '</option>';
        foreach ( $roles as $role_key => $role ) {
            if ( $role_key === 'administrator' ) continue;
            echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $selected, $role_key, false ) . '>' . esc_html( $role['name'] ) . '</option>';
        }
        echo '</select>';
    }

    public function ajax_start_deletion() {
        check_ajax_referer( 'ucp_ajax_nonce' );
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
        $batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;

        // Clean allowed domains array
        $allowed_domains = array_filter( array_map( 'trim', $allowed_domains ) );

        // Start scanning process - store scanning state in transient
        delete_transient( 'ucp_scan_state' );
        delete_transient( 'ucp_user_ids_to_delete' );
        
        $scan_state = array(
            'offset' => 0,
            'total_scanned' => 0,
            'total_to_delete' => 0,
            'scan_batch_size' => 1000, // Scan in smaller batches for memory efficiency
            'criteria' => array(
                'delete_no_name' => $delete_no_name,
                'delete_unlisted_domains' => $delete_unlisted_domains,
                'allowed_domains' => $allowed_domains,
                'delete_role' => $delete_role
            )
        );
        
        set_transient( 'ucp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scanning' => true,
            'batch_size' => $batch_size,
            'message' => 'Starting user scan...'
        ) );
    }

    public function ajax_scan_batch() {
        check_ajax_referer( 'ucp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        
        // Set longer execution time
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 60 ); // 1 minute per scan batch
        }
        
        $scan_state = get_transient( 'ucp_scan_state' );
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
            // Scanning complete
            $existing_ids = get_transient( 'ucp_user_ids_to_delete' );
            $total_to_delete = is_array( $existing_ids ) ? count( $existing_ids ) : 0;
            
            delete_transient( 'ucp_scan_state' );
            
            wp_send_json_success( array(
                'scan_complete' => true,
                'total' => $total_to_delete,
                'batch_size' => get_option( self::OPTION_KEY )['batch_size'] ?? 100,
                'message' => $total_to_delete > 0 
                    ? "Scan complete. Found {$total_to_delete} users to delete." 
                    : 'Scan complete. No users found matching criteria.'
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
            $existing_ids = get_transient( 'ucp_user_ids_to_delete' );
            if ( ! is_array( $existing_ids ) ) {
                $existing_ids = array();
            }
            $existing_ids = array_merge( $existing_ids, $current_batch_ids );
            set_transient( 'ucp_user_ids_to_delete', $existing_ids, HOUR_IN_SECONDS );
        }
        
        // Update scan state
        $scan_state['offset'] += $scan_batch_size;
        $scan_state['total_scanned'] += count( $users );
        $scan_state['total_to_delete'] += count( $current_batch_ids );
        set_transient( 'ucp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $scan_state['total_scanned'],
            'found' => $scan_state['total_to_delete'],
            'batch_found' => count( $current_batch_ids ),
            'message' => "Scanned {$scan_state['total_scanned']} users, found {$scan_state['total_to_delete']} to delete..."
        ) );
    }

    public function ajax_run_batch() {
        check_ajax_referer( 'ucp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        
        // Set execution time limit
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( 120 ); // 2 minutes per deletion batch
        }
        
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;
        
        // Get IDs from transient storage
        $all_ids = get_transient( 'ucp_user_ids_to_delete' );
        if ( ! is_array( $all_ids ) || empty( $all_ids ) ) {
            wp_send_json_success( array(
                'deleted' => 0,
                'remaining' => 0,
                'log' => array( 'No users left to delete.' ),
                'complete' => true
            ) );
        }
        
        $to_delete = array_slice( $all_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_ids, $batch_size );
        
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
            set_transient( 'ucp_user_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'ucp_user_ids_to_delete' );
        }
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => count( $remaining_ids ),
            'log' => $log,
            'complete' => empty( $remaining_ids )
        ) );
    }

    public function cleanup_transients() {
        delete_transient( 'ucp_scan_state' );
        delete_transient( 'ucp_user_ids_to_delete' );
    }

}
endif;

new UCP_User_Cleanup_Pro();
