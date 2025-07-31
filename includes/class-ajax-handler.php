<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ajax handler for WBCP
 */
class WBCP_Ajax_Handler {

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add Ajax hooks for admin users only
        add_action( 'wp_ajax_wbcp_start_deletion', array( $this, 'start_deletion' ) );
        add_action( 'wp_ajax_wbcp_scan_batch', array( $this, 'scan_batch' ) );
        add_action( 'wp_ajax_wbcp_run_batch', array( $this, 'run_batch' ) );
    }

    public function start_deletion() {
        // Verify nonce and capabilities
        if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'wbcp_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        try {
            $processor = new WBCP_Deletion_Processor();
            $result = $processor->start_scan();
            wp_send_json_success( $result );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    public function scan_batch() {
        // Verify nonce and capabilities
        if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'wbcp_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        try {
            $processor = new WBCP_Deletion_Processor();
            $result = $processor->scan_batch();
            
            if ( isset( $result['error'] ) ) {
                wp_send_json_error( array( 'message' => $result['error'] ) );
            } else {
                wp_send_json_success( $result );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }

    public function run_batch() {
        // Verify nonce and capabilities
        if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'wbcp_ajax_nonce' ) || ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        try {
            $processor = new WBCP_Deletion_Processor();
            $result = $processor->run_deletion_batch();
            wp_send_json_success( $result );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => $e->getMessage() ) );
        }
    }
}
        ) );
    }
}
