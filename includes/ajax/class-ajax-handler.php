<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Ajax_Handler {
    
    public function __construct() {
        add_action( 'wp_ajax_wbcp_start_deletion', array( $this, 'start_deletion' ) );
        add_action( 'wp_ajax_wbcp_scan_batch', array( $this, 'scan_batch' ) );
        add_action( 'wp_ajax_wbcp_run_batch', array( $this, 'run_batch' ) );
    }
    
    public function start_deletion() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        
        $scanner = new WBCP_Scanner();
        $scanner->start_scan();
    }
    
    public function scan_batch() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        
        $scanner = new WBCP_Scanner();
        $scanner->scan_batch();
    }
    
    public function run_batch() {
        check_ajax_referer( 'wbcp_ajax_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        
        $deleter = new WBCP_Deleter();
        $deleter->run_batch();
    }
}
