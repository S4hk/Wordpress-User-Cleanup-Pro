<?php
/*
Plugin Name: WordPress Bulk Cleanup Pro
Description: Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders by status, in safe batches with progress bar. Supports millions of users/orders with memory-efficient scanning. Never deletes administrators.
Version: 1.2.4
Author: s4hk
Author URI: https://github.com/S4hk
Requires at least: 5.6
License: GPL2+
GitHub Plugin URI: S4hk/wordpress-bulk-cleanup-pro
GitHub Branch: main
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define( 'WBCP_VERSION', '1.2.4' );
define( 'WBCP_PLUGIN_FILE', __FILE__ );
define( 'WBCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WBCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCP_GITHUB_REPO', 'S4hk/wordpress-bulk-cleanup-pro' );

// Autoloader
require_once WBCP_PLUGIN_DIR . 'includes/class-autoloader.php';
WBCP_Autoloader::init();

// Plugin activation hook
register_activation_hook( __FILE__, 'wbcp_activate_plugin' );

// Plugin deactivation hook
register_deactivation_hook( __FILE__, 'wbcp_deactivate_plugin' );

/**
 * Plugin activation callback
 */
function wbcp_activate_plugin() {
    // Check WordPress version
    if ( version_compare( get_bloginfo( 'version' ), '5.6', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 'WordPress Bulk Cleanup Pro requires WordPress 5.6 or higher.' );
    }
    
    // Check user capabilities
    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }
}

/**
 * Plugin deactivation callback
 */
function wbcp_deactivate_plugin() {
    // Cleanup transients on deactivation
    if ( class_exists( 'WBCP_Utils' ) ) {
        WBCP_Utils::cleanup_transients();
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', 'wbcp_init_plugin' );

function wbcp_init_plugin() {
    if ( class_exists( 'WBCP_Plugin' ) ) {
        WBCP_Plugin::get_instance();
    }
}