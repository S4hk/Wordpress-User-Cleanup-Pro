<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Plugin {
    
    private static $instance = null;
    private $admin = null;
    private $ajax = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    private function init_hooks() {
        register_deactivation_hook( WBCP_PLUGIN_FILE, array( $this, 'cleanup_transients' ) );
        
        // Initialize GitHub updater
        if ( is_admin() ) {
            new WBCP_GitHub_Updater( WBCP_PLUGIN_FILE, WBCP_GITHUB_REPO, WBCP_VERSION );
        }
    }
    
    private function init_components() {
        if ( is_admin() ) {
            $this->admin = new WBCP_Admin();
            $this->ajax = new WBCP_Ajax_Handler();
        }
    }
    
    public function cleanup_transients() {
        WBCP_Utils::cleanup_transients();
    }
}
