<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Utility class for WBCP
 */
class WBCP_Utils {

    const OPTION_KEY = 'wbcp_settings';
    const TRANSIENT_SCAN_STATE = 'wbcp_scan_state';
    const TRANSIENT_DELETE_STATE = 'wbcp_delete_state';

    /**
     * Get plugin settings
     */
    public static function get_settings() {
        $defaults = array(
            'delete_no_name' => 0,
            'delete_unlisted_domains' => 0,
            'allowed_domains' => '',
            'delete_role' => '',
            'delete_wc_orders' => 0,
            'wc_order_statuses' => array(),
            'delete_wc_coupons' => 0,
            'wc_coupon_statuses' => array(),
            'batch_size' => 100
        );
        
        return array_merge( $defaults, get_option( self::OPTION_KEY, array() ) );
    }

    /**
     * Save plugin settings
     */
    public static function save_settings( $settings ) {
        return update_option( self::OPTION_KEY, $settings );
    }

    /**
     * Cleanup all plugin transients
     */
    public static function cleanup_transients() {
        delete_transient( self::TRANSIENT_SCAN_STATE );
        delete_transient( self::TRANSIENT_DELETE_STATE );
        
        // Clean up any batch transients
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wbcp_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wbcp_%'" );
    }

    /**
     * Get allowed domains as array
     */
    public static function get_allowed_domains() {
        $settings = self::get_settings();
        $domains = $settings['allowed_domains'];
        
        if ( empty( $domains ) ) {
            return array();
        }
        
        return array_map( 'trim', explode( ',', $domains ) );
    }
}
    }
}
