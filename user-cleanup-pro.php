<?php
/*
Plugin Name: User Cleanup Pro
Description: Advanced user cleanup tool for administrators. Delete users by missing names, email domains, or roles, in safe batches with progress bar. Never deletes administrators.
Version: 1.0.1
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
        add_action( 'wp_ajax_ucp_run_batch', array( $this, 'ajax_run_batch' ) );
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
        $output['batch_size'] = in_array( $batch_size, array( 100, 500, 1000 ) ) ? $batch_size : 100;
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
                                <option value="100" <?php selected( $batch_size, 100 ); ?>>100</option>
                                <option value="500" <?php selected( $batch_size, 500 ); ?>>500</option>
                                <option value="1000" <?php selected( $batch_size, 1000 ); ?>>1000</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2><?php esc_html_e( 'Start User Cleanup', 'user-cleanup-pro' ); ?></h2>
            <button id="ucp-start-deletion" class="button button-primary"><?php esc_html_e( 'Start Deletion', 'user-cleanup-pro' ); ?></button>
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
            var allIds = [], total = 0, deleted = 0, batchSize = 100, isRunning = false;
            function updateProgress() {
                var percent = total > 0 ? Math.round( deleted / total * 100 ) : 0;
                $('#ucp-progress-inner').css('width', percent + '%');
                $('#ucp-progress-label').text(deleted + ' deleted / ' + total + ' total');
            }
            function logMessage(msg) {
                $('#ucp-log').append($('<div/>').text(msg));
                $('#ucp-log').scrollTop(99999);
            }
            $('#ucp-start-deletion').on('click', function(e){
                e.preventDefault();
                if ( isRunning ) return;
                isRunning = true;
                $('#ucp-log').empty();
                $('#ucp-progress-label').text('Scanning users...');
                $('#ucp-progress-inner').css('width','0%');
                $('#ucp-progress-wrapper').show();
                $.post(
                    ajaxurl,
                    { action:'ucp_start_deletion', _ajax_nonce:'<?php echo wp_create_nonce( 'ucp_ajax_nonce' ); ?>' },
                    function(resp) {
                        if ( ! resp.success ) {
                            logMessage('Failed: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                            isRunning = false;
                            return;
                        }
                        allIds   = resp.data.ids;
                        total    = resp.data.total;
                        batchSize= resp.data.batch_size;
                        deleted  = 0;
                        updateProgress();
                        if ( total === 0 ) {
                            $('#ucp-progress-label').text('No users to delete.');
                            isRunning = false;
                        } else {
                            logMessage(resp.data.message || 'Ready.');
                            runBatch();
                        }
                    }
                );
            });
            function runBatch() {
                if ( allIds.length === 0 ) {
                    $('#ucp-progress-label').text('Completed: ' + deleted + ' users deleted.');
                    isRunning = false;
                    return;
                }
                $.post(
                    ajaxurl,
                    { action:'ucp_run_batch', _ajax_nonce:'<?php echo wp_create_nonce( 'ucp_ajax_nonce' ); ?>', ids: allIds, batch_size: batchSize },
                    function(resp) {
                        if ( ! resp.success ) {
                            logMessage('Error: ' + (resp.data && resp.data.message ? resp.data.message : 'Unknown error') );
                            isRunning = false;
                            return;
                        }
                        var deletedNow = resp.data.deleted || 0;
                        deleted += deletedNow;
                        if ( resp.data.log && resp.data.log.length ) {
                            for (var i=0;i<resp.data.log.length;i++) {
                                logMessage(resp.data.log[i]);
                            }
                        }
                        allIds = resp.data.remaining_ids;
                        updateProgress();
                        setTimeout(runBatch, 350);
                    }
                );
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
        $options = get_option( self::OPTION_KEY );
        $delete_no_name          = ! empty( $options['delete_no_name'] );
        $delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] );
        $allowed_domains         = isset( $options['allowed_domains'] ) ? explode( ',', strtolower( $options['allowed_domains'] ) ) : array();
        $delete_role             = isset( $options['delete_role'] ) ? sanitize_text_field( $options['delete_role'] ) : '';
        $batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;

        $user_ids_to_delete = array();
        $args = array(
            'fields'     => 'ID',
            'number'     => 999999,
            'role__not_in' => array( 'administrator' ),
        );
        $user_query = new WP_User_Query( $args );
        $all_users  = $user_query->get_results();

        foreach ( $all_users as $user_id ) {
            $user = get_userdata( $user_id );
            if ( in_array( 'administrator', (array)$user->roles, true ) ) continue;
            if ( $delete_role && in_array( $delete_role, (array)$user->roles, true ) ) {
                $user_ids_to_delete[] = $user_id;
                continue;
            }
            if ( $delete_no_name ) {
                $first = get_user_meta( $user_id, 'first_name', true );
                $last  = get_user_meta( $user_id, 'last_name', true );
                if ( ( empty( $first ) || trim( $first ) === '' ) && ( empty( $last ) || trim( $last ) === '' ) ) {
                    $user_ids_to_delete[] = $user_id;
                    continue;
                }
            }
            if ( $delete_unlisted_domains && ! empty( $allowed_domains ) ) {
                $email = strtolower( $user->user_email );
                $valid = false;
                foreach ( $allowed_domains as $domain ) {
                    $domain = trim( $domain );
                    if ( $domain === '' ) continue;
                    if ( substr( $email, -strlen( '@' . $domain ) ) === '@' . $domain ) {
                        $valid = true;
                        break;
                    }
                }
                if ( ! $valid ) {
                    $user_ids_to_delete[] = $user_id;
                    continue;
                }
            }
        }
        $user_ids_to_delete = array_unique( $user_ids_to_delete );
        wp_send_json_success( array(
            'total'      => count( $user_ids_to_delete ),
            'batch_size' => $batch_size,
            'ids'        => $user_ids_to_delete,
            'message'    => count( $user_ids_to_delete ) ? 'Ready to delete ' . count( $user_ids_to_delete ) . ' users.' : 'No users to delete.',
        ) );
    }

    public function ajax_run_batch() {
        check_ajax_referer( 'ucp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        $ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'intval', $_POST['ids'] ) : array();
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;
        if ( empty( $ids ) ) {
            wp_send_json_success( array(
                'deleted' => 0,
                'remaining_ids' => array(),
                'log' => array( 'No users left to delete.' ),
            ) );
        }
        $to_delete = array_slice( $ids, 0, $batch_size );
        $remaining = array_slice( $ids, $batch_size );
        $deleted_count = 0;
        $log = array();
        foreach ( $to_delete as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) continue;
            if ( in_array( 'administrator', (array)$user->roles, true ) ) {
                $log[] = "Skipped admin user ID {$user_id}.";
                continue;
            }
            global $wpdb;
            $wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ) );
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( $user_id );
            $deleted_count++;
            $log[] = "Deleted user ID {$user_id} ({$user->user_login}).";
        }
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining_ids' => $remaining,
            'log' => $log,
        ) );
    }

}
endif;

new UCP_User_Cleanup_Pro();
