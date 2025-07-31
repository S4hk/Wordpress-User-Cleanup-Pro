<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Scanner {
    
    public function start_scan() {
        WBCP_Utils::set_execution_time_limit( 300 );
        
        $options = WBCP_Utils::get_options();
        $criteria = $this->extract_criteria( $options );
        $batch_size = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;
        
        // Initialize scan state
        $this->cleanup_previous_scan();
        $scan_state = $this->create_initial_scan_state( $criteria );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scanning' => true,
            'batch_size' => $batch_size,
            'message' => 'Starting user scan...'
        ) );
    }
    
    public function scan_batch() {
        WBCP_Utils::set_execution_time_limit( 60 );
        
        $scan_state = get_transient( 'wbcp_scan_state' );
        if ( ! $scan_state ) {
            wp_send_json_error( array( 'message' => 'Scan state lost. Please restart.' ) );
        }
        
        // Determine which type of scan to run
        if ( $scan_state['offset'] >= 0 ) {
            $this->scan_users_batch( $scan_state );
        } elseif ( $scan_state['wc_offset'] >= 0 && $scan_state['criteria']['delete_wc_orders'] ) {
            $this->scan_wc_orders_batch( $scan_state );
        } elseif ( $scan_state['wc_coupon_offset'] >= 0 && $scan_state['criteria']['delete_wc_coupons'] ) {
            $this->scan_wc_coupons_batch( $scan_state );
        } else {
            $this->complete_scan( $scan_state );
        }
    }
    
    private function extract_criteria( $options ) {
        $delete_no_name          = ! empty( $options['delete_no_name'] );
        $delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] );
        $allowed_domains         = isset( $options['allowed_domains'] ) ? explode( ',', strtolower( $options['allowed_domains'] ) ) : array();
        $delete_role             = isset( $options['delete_role'] ) ? sanitize_text_field( $options['delete_role'] ) : '';
        $delete_wc_orders        = ! empty( $options['delete_wc_orders'] );
        $wc_order_statuses       = isset( $options['wc_order_statuses'] ) && is_array( $options['wc_order_statuses'] ) ? $options['wc_order_statuses'] : array();
        $delete_wc_coupons       = ! empty( $options['delete_wc_coupons'] );
        $wc_coupon_statuses      = isset( $options['wc_coupon_statuses'] ) && is_array( $options['wc_coupon_statuses'] ) ? $options['wc_coupon_statuses'] : array();
        
        $allowed_domains = array_filter( array_map( 'trim', $allowed_domains ) );
        
        return array(
            'delete_no_name' => $delete_no_name,
            'delete_unlisted_domains' => $delete_unlisted_domains,
            'allowed_domains' => $allowed_domains,
            'delete_role' => $delete_role,
            'delete_wc_orders' => $delete_wc_orders,
            'wc_order_statuses' => $wc_order_statuses,
            'delete_wc_coupons' => $delete_wc_coupons,
            'wc_coupon_statuses' => $wc_coupon_statuses
        );
    }
    
    private function cleanup_previous_scan() {
        delete_transient( 'wbcp_scan_state' );
        delete_transient( 'wbcp_user_ids_to_delete' );
        delete_transient( 'wbcp_wc_order_ids_to_delete' );
        delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
    }
    
    private function create_initial_scan_state( $criteria ) {
        return array(
            'offset' => 0,
            'total_scanned' => 0,
            'total_to_delete' => 0,
            'wc_offset' => 0,
            'wc_total_scanned' => 0,
            'wc_total_to_delete' => 0,
            'wc_coupon_offset' => 0,
            'wc_coupon_total_scanned' => 0,
            'wc_coupon_total_to_delete' => 0,
            'scan_batch_size' => 1000,
            'criteria' => $criteria
        );
    }
    
    private function scan_users_batch( $scan_state ) {
        // Implementation from original ajax_scan_batch method
        // ...existing code...
    }
    
    private function scan_wc_orders_batch( $scan_state ) {
        // Implementation from original scan_wc_orders_batch method
        // ...existing code...
    }
    
    private function scan_wc_coupons_batch( $scan_state ) {
        // Implementation from original scan_wc_coupons_batch method
        // ...existing code...
    }
    
    private function complete_scan( $scan_state ) {
        // Complete scan logic
        // ...existing code...
    }
}