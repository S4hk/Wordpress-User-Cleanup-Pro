<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Utils {
    
    const OPTION_KEY = 'wbcp_settings';
    
    public static function get_options() {
        return get_option( self::OPTION_KEY );
    }
    
    public static function sanitize_settings( $input ) {
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
        $output['batch_size'] = in_array( $batch_size, array( 50, 100, 250, 500, 1000 ) ) ? $batch_size : 100;
        
        return $output;
    }
    
    public static function cleanup_transients() {
        delete_transient( 'wbcp_scan_state' );
        delete_transient( 'wbcp_user_ids_to_delete' );
        delete_transient( 'wbcp_wc_order_ids_to_delete' );
        delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
    }
    
    public static function set_execution_time_limit( $seconds = 300 ) {
        if ( ! ini_get( 'safe_mode' ) ) {
            set_time_limit( $seconds );
        }
    }
}
