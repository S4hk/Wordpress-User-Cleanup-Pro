<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main plugin class
 */
class WBCP_Plugin {

    private static $instance = null;

    public function __construct() {
        add_action( 'init', array( $this, 'init_components' ) );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init_components() {
        // Initialize components only if user has proper capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Initialize Ajax handler
        new WBCP_Ajax_Handler();

        // Initialize admin pages
        if ( is_admin() ) {
            new WBCP_Admin();
        }
    }

    public function cleanup_transients() {
        WBCP_Utils::cleanup_transients();
    }
}
}
