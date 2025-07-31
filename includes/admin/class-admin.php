<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Admin {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }
    
    public function add_settings_page() {
        add_users_page(
            __( 'WordPress Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ),
            __( 'Bulk Cleanup Pro', 'wordpress-bulk-cleanup-pro' ),
            'manage_options',
            'wordpress-bulk-cleanup-pro',
            array( $this, 'render_settings_page' )
        );
    }
    
    public function register_settings() {
        register_setting( 'wbcp_settings_group', WBCP_Utils::OPTION_KEY, array( 'WBCP_Utils', 'sanitize_settings' ) );
    }
    
    public function enqueue_assets( $hook ) {
        if ( $hook !== 'users_page_wordpress-bulk-cleanup-pro' ) return;
        
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 
            'wbcp-admin-js', 
            WBCP_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery' ), 
            WBCP_VERSION, 
            true 
        );
        
        wp_localize_script( 'wbcp-admin-js', 'wbcp_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wbcp_ajax_nonce' )
        ));
        
        wp_enqueue_style( 
            'wbcp-admin-css', 
            WBCP_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            WBCP_VERSION 
        );
    }
    
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $page_renderer = new WBCP_Admin_Page_Renderer();
        $page_renderer->render();
    }
}
