<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Autoloader {
    
    public static function init() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    
    public static function autoload( $class ) {
        if ( strpos( $class, 'WBCP_' ) !== 0 ) {
            return;
        }
        
        $class_file = strtolower( str_replace( '_', '-', $class ) );
        $class_file = str_replace( 'wbcp-', '', $class_file );
        
        $file_paths = array(
            WBCP_PLUGIN_DIR . 'includes/class-' . $class_file . '.php',
            WBCP_PLUGIN_DIR . 'includes/admin/class-' . $class_file . '.php',
            WBCP_PLUGIN_DIR . 'includes/ajax/class-' . $class_file . '.php',
            WBCP_PLUGIN_DIR . 'includes/scanner/class-' . $class_file . '.php',
            WBCP_PLUGIN_DIR . 'includes/deleter/class-' . $class_file . '.php',
        );
        
        foreach ( $file_paths as $file_path ) {
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                break;
            }
        }
    }
}
