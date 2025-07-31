<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Autoloader for WBCP classes
 */
class WBCP_Autoloader {

    /**
     * Initialize the autoloader
     */
    public static function init() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload classes
     *
     * @param string $class_name The class name to load
     */
    public static function autoload( $class_name ) {
        if ( strpos( $class_name, 'WBCP_' ) !== 0 ) {
            return;
        }

        $file_name = 'class-' . strtolower( str_replace( '_', '-', substr( $class_name, 5 ) ) ) . '.php';
        $file_path = WBCP_PLUGIN_DIR . 'includes/' . $file_name;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}
            WBCP_PLUGIN_DIR . 'includes/deleter/' . $file_name,
        );
        
        foreach ( $possible_paths as $file_path ) {
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
                break;
            }
        }
    }
}
