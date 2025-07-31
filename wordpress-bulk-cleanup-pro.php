<?php
/*
Plugin Name: WordPress Bulk Cleanup Pro
Description: Advanced bulk cleanup tool for administrators. Delete users by missing names, email domains, or roles, and WooCommerce orders by status, in safe batches with progress bar. Supports millions of users/orders with memory-efficient scanning. Never deletes administrators.
Version: 1.2.1
Author: s4hk
Author URI: https://github.com/S4hk
Requires at least: 5.6
License: GPL2+
GitHub Plugin URI: S4hk/wordpress-bulk-cleanup-pro
GitHub Branch: main
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define( 'WBCP_VERSION', '1.2.2' );
define( 'WBCP_PLUGIN_FILE', __FILE__ );
define( 'WBCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WBCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WBCP_GITHUB_REPO', 'S4hk/wordpress-bulk-cleanup-pro' );

// Autoloader
require_once WBCP_PLUGIN_DIR . 'includes/class-autoloader.php';
WBCP_Autoloader::init();

// Initialize the plugin
WBCP_Plugin::get_instance();