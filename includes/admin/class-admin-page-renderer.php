<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Admin_Page_Renderer {
    
    public function render() {
        $options = WBCP_Utils::get_options();
        $this->extract_options( $options );
        
        include WBCP_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    private function extract_options( $options ) {
        $this->delete_no_name          = ! empty( $options['delete_no_name'] ) ? 1 : 0;
        $this->delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] ) ? 1 : 0;
        $this->allowed_domains         = isset( $options['allowed_domains'] ) ? esc_attr( $options['allowed_domains'] ) : '';
        $this->delete_role             = isset( $options['delete_role'] ) ? esc_attr( $options['delete_role'] ) : '';
        $this->delete_wc_orders        = ! empty( $options['delete_wc_orders'] ) ? 1 : 0;
        $this->wc_order_statuses       = isset( $options['wc_order_statuses'] ) ? $options['wc_order_statuses'] : array();
        $this->delete_wc_coupons       = ! empty( $options['delete_wc_coupons'] ) ? 1 : 0;
        $this->wc_coupon_statuses      = isset( $options['wc_coupon_statuses'] ) ? $options['wc_coupon_statuses'] : array();
        $this->batch_size              = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;
    }
    
    public function role_dropdown( $selected = '' ) {
        global $wp_roles;
        $roles = $wp_roles->roles;
        echo '<select name="' . WBCP_Utils::OPTION_KEY . '[delete_role]">';
        echo '<option value="">' . esc_html__( '— Select Role —', 'wordpress-bulk-cleanup-pro' ) . '</option>';
        foreach ( $roles as $role_key => $role ) {
            if ( $role_key === 'administrator' ) continue;
            echo '<option value="' . esc_attr( $role_key ) . '"' . selected( $selected, $role_key, false ) . '>' . esc_html( $role['name'] ) . '</option>';
        }
        echo '</select>';
    }
    
    public function wc_order_status_checkboxes( $selected = array() ) {
        if ( ! class_exists( 'WooCommerce' ) ) return;
        
        $order_statuses = wc_get_order_statuses();
        
        foreach ( $order_statuses as $status_key => $status_name ) {
            $checked = in_array( $status_key, $selected ) ? 'checked' : '';
            echo '<label style="display:inline-block; margin-right:15px; margin-bottom:5px;">';
            echo '<input type="checkbox" name="' . WBCP_Utils::OPTION_KEY . '[wc_order_statuses][]" value="' . esc_attr( $status_key ) . '" ' . $checked . '> ';
            echo esc_html( $status_name );
            echo '</label>';
        }
    }
    
    public function wc_coupon_status_checkboxes( $selected = array() ) {
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
            echo '<input type="checkbox" name="' . WBCP_Utils::OPTION_KEY . '[wc_coupon_statuses][]" value="' . esc_attr( $status_key ) . '" ' . $checked . '> ';
            echo esc_html( $status_name );
            echo '</label>';
        }
    }
}
