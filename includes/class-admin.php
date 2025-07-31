<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality for WBCP
 */
class WBCP_Admin {

    private $delete_no_name;
    private $delete_unlisted_domains;
    private $allowed_domains;
    private $delete_role;
    private $delete_wc_orders;
    private $wc_order_statuses;
    private $delete_wc_coupons;
    private $wc_coupon_statuses;
    private $batch_size;

    public function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }

    private function load_settings() {
        $settings = WBCP_Utils::get_settings();
        $this->delete_no_name = $settings['delete_no_name'];
        $this->delete_unlisted_domains = $settings['delete_unlisted_domains'];
        $this->allowed_domains = $settings['allowed_domains'];
        $this->delete_role = $settings['delete_role'];
        $this->delete_wc_orders = $settings['delete_wc_orders'];
        $this->wc_order_statuses = $settings['wc_order_statuses'];
        $this->delete_wc_coupons = $settings['delete_wc_coupons'];
        $this->wc_coupon_statuses = $settings['wc_coupon_statuses'];
        $this->batch_size = $settings['batch_size'];
    }

    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_users_page(
            'WordPress Bulk Cleanup Pro',
            'Bulk Cleanup Pro',
            'manage_options',
            'wordpress-bulk-cleanup-pro',
            array( $this, 'admin_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wbcp_settings_group', WBCP_Utils::OPTION_KEY );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'users_page_wordpress-bulk-cleanup-pro' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_localize_script( 'jquery', 'wbcp_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wbcp_ajax_nonce' )
        ) );
    }

    public function admin_page() {
        include WBCP_PLUGIN_DIR . 'templates/admin-page.php';
    }

    public function role_dropdown( $selected = '' ) {
        $roles = get_editable_roles();
        echo '<select name="' . WBCP_Utils::OPTION_KEY . '[delete_role]">';
        echo '<option value="">' . esc_html__( 'Select a role to delete', 'wordpress-bulk-cleanup-pro' ) . '</option>';
        
        foreach ( $roles as $role_key => $role ) {
            if ( $role_key === 'administrator' ) continue; // Skip administrators
            echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $selected, $role_key, false ) . '>';
            echo esc_html( $role['name'] );
            echo '</option>';
        }
        echo '</select>';
    }

    public function wc_order_status_checkboxes( $selected = array() ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce is not active.', 'wordpress-bulk-cleanup-pro' ) . '</p>';
            return;
        }

        $statuses = wc_get_order_statuses();
        foreach ( $statuses as $status_key => $status_name ) {
            $checked = in_array( $status_key, (array) $selected );
            echo '<label style="display:block;margin-bottom:5px;">';
            echo '<input type="checkbox" name="' . WBCP_Utils::OPTION_KEY . '[wc_order_statuses][]" value="' . esc_attr( $status_key ) . '"';
            echo checked( $checked, true, false ) . ' />';
            echo ' ' . esc_html( $status_name );
            echo '</label>';
        }
    }

    public function wc_coupon_status_checkboxes( $selected = array() ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce is not active.', 'wordpress-bulk-cleanup-pro' ) . '</p>';
            return;
        }

        $statuses = array(
            'publish' => __( 'Published', 'wordpress-bulk-cleanup-pro' ),
            'draft' => __( 'Draft', 'wordpress-bulk-cleanup-pro' ),
            'trash' => __( 'Trash', 'wordpress-bulk-cleanup-pro' ),
            'private' => __( 'Private', 'wordpress-bulk-cleanup-pro' ),
            'pending' => __( 'Pending', 'wordpress-bulk-cleanup-pro' ),
        );

        foreach ( $statuses as $status_key => $status_name ) {
            $checked = in_array( $status_key, (array) $selected );
            echo '<label style="display:block;margin-bottom:5px;">';
            echo '<input type="checkbox" name="' . WBCP_Utils::OPTION_KEY . '[wc_coupon_statuses][]" value="' . esc_attr( $status_key ) . '"';
            echo checked( $checked, true, false ) . ' />';
            echo ' ' . esc_html( $status_name );
            echo '</label>';
        }
    }
}
