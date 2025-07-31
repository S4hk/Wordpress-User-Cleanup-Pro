<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin functionality for WBCP
 */
class WBCP_Admin {

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_admin_menu() {
        add_management_page(
            'WordPress Bulk Cleanup Pro',
            'Bulk Cleanup Pro',
            'manage_options',
            'wordpress-bulk-cleanup-pro',
            array( $this, 'admin_page' )
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( 'tools_page_wordpress-bulk-cleanup-pro' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_localize_script( 'jquery', 'wbcp_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wbcp_nonce' )
        ) );
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>WordPress Bulk Cleanup Pro</h1>
            <p>Advanced bulk cleanup tool for administrators.</p>
            <div class="notice notice-info">
                <p><strong>Note:</strong> This plugin is designed for advanced users. Always backup your database before performing bulk operations.</p>
            </div>
            
            <h2>User Cleanup</h2>
            <div class="wbcp-section">
                <p>Scan and delete users based on various criteria.</p>
                <button type="button" class="button button-secondary" id="scan-users">Scan Users</button>
                <button type="button" class="button button-primary" id="delete-users" disabled>Delete Selected Users</button>
            </div>

            <h2>Order Cleanup</h2>
            <div class="wbcp-section">
                <p>Scan and delete WooCommerce orders by status.</p>
                <button type="button" class="button button-secondary" id="scan-orders">Scan Orders</button>
                <button type="button" class="button button-primary" id="delete-orders" disabled>Delete Selected Orders</button>
            </div>

            <div id="wbcp-results"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#scan-users').on('click', function() {
                $.post(wbcp_ajax.ajax_url, {
                    action: 'wbcp_scan_users',
                    nonce: wbcp_ajax.nonce
                }, function(response) {
                    $('#wbcp-results').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                });
            });

            $('#scan-orders').on('click', function() {
                $.post(wbcp_ajax.ajax_url, {
                    action: 'wbcp_scan_orders',
                    nonce: wbcp_ajax.nonce
                }, function(response) {
                    $('#wbcp-results').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                });
            });
        });
        </script>

        <style>
        .wbcp-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            background: #f9f9f9;
        }
        </style>
        <?php
    }
}
