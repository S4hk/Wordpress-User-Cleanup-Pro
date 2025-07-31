<?php

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Handles the bulk deletion process
 */
class WBCP_Deletion_Processor {

    const SCAN_BATCH_SIZE = 1000;
    
    private $settings;

    public function __construct() {
        $this->settings = WBCP_Utils::get_settings();
    }

    /**
     * Start the scanning process
     */
    public function start_scan() {
        WBCP_Utils::cleanup_transients();
        
        $scan_state = array(
            'phase' => 'users',
            'offset' => 0,
            'total_found' => 0,
            'user_ids' => array(),
            'order_ids' => array(),
            'coupon_ids' => array(),
            'complete' => false
        );
        
        set_transient( WBCP_Utils::TRANSIENT_SCAN_STATE, $scan_state, HOUR_IN_SECONDS );
        
        return array(
            'scanning' => true,
            'message' => 'Starting scan...',
            'batch_size' => $this->settings['batch_size']
        );
    }

    /**
     * Run a batch of scanning
     */
    public function scan_batch() {
        $scan_state = get_transient( WBCP_Utils::TRANSIENT_SCAN_STATE );
        
        if ( ! $scan_state ) {
            return array( 'error' => 'Scan state lost. Please restart.' );
        }

        if ( $scan_state['phase'] === 'users' ) {
            $result = $this->scan_users_batch( $scan_state );
        } elseif ( $scan_state['phase'] === 'orders' ) {
            $result = $this->scan_orders_batch( $scan_state );
        } elseif ( $scan_state['phase'] === 'coupons' ) {
            $result = $this->scan_coupons_batch( $scan_state );
        } else {
            $result = $this->complete_scan( $scan_state );
        }

        set_transient( WBCP_Utils::TRANSIENT_SCAN_STATE, $scan_state, HOUR_IN_SECONDS );
        
        return $result;
    }

    private function scan_users_batch( &$scan_state ) {
        global $wpdb;

        $users = get_users( array(
            'offset' => $scan_state['offset'],
            'number' => self::SCAN_BATCH_SIZE,
            'fields' => array( 'ID', 'user_email', 'user_login' )
        ) );

        $found_this_batch = 0;

        foreach ( $users as $user ) {
            if ( $this->should_delete_user( $user ) ) {
                $scan_state['user_ids'][] = $user->ID;
                $found_this_batch++;
            }
        }

        $scan_state['offset'] += self::SCAN_BATCH_SIZE;
        $scan_state['total_found'] += $found_this_batch;

        if ( count( $users ) < self::SCAN_BATCH_SIZE ) {
            // Move to next phase
            if ( $this->settings['delete_wc_orders'] && class_exists( 'WooCommerce' ) ) {
                $scan_state['phase'] = 'orders';
                $scan_state['offset'] = 0;
            } elseif ( $this->settings['delete_wc_coupons'] && class_exists( 'WooCommerce' ) ) {
                $scan_state['phase'] = 'coupons';
                $scan_state['offset'] = 0;
            } else {
                return $this->complete_scan( $scan_state );
            }
        }

        return array(
            'scan_complete' => false,
            'message' => sprintf( 'Scanned %d users, found %d to delete...', $scan_state['offset'], $scan_state['total_found'] )
        );
    }

    private function scan_orders_batch( &$scan_state ) {
        if ( ! class_exists( 'WooCommerce' ) || ! $this->settings['delete_wc_orders'] ) {
            $scan_state['phase'] = 'coupons';
            return $this->scan_batch();
        }

        $orders = wc_get_orders( array(
            'limit' => self::SCAN_BATCH_SIZE,
            'offset' => $scan_state['offset'],
            'status' => $this->settings['wc_order_statuses'],
            'return' => 'ids'
        ) );

        $scan_state['order_ids'] = array_merge( $scan_state['order_ids'], $orders );
        $scan_state['offset'] += self::SCAN_BATCH_SIZE;
        $scan_state['total_found'] += count( $orders );

        if ( count( $orders ) < self::SCAN_BATCH_SIZE ) {
            if ( $this->settings['delete_wc_coupons'] ) {
                $scan_state['phase'] = 'coupons';
                $scan_state['offset'] = 0;
            } else {
                return $this->complete_scan( $scan_state );
            }
        }

        return array(
            'scan_complete' => false,
            'message' => sprintf( 'Scanned orders, found %d total items to delete...', $scan_state['total_found'] )
        );
    }

    private function scan_coupons_batch( &$scan_state ) {
        if ( ! class_exists( 'WooCommerce' ) || ! $this->settings['delete_wc_coupons'] ) {
            return $this->complete_scan( $scan_state );
        }

        $coupons = get_posts( array(
            'post_type' => 'shop_coupon',
            'post_status' => $this->settings['wc_coupon_statuses'],
            'posts_per_page' => self::SCAN_BATCH_SIZE,
            'offset' => $scan_state['offset'],
            'fields' => 'ids'
        ) );

        $scan_state['coupon_ids'] = array_merge( $scan_state['coupon_ids'], $coupons );
        $scan_state['offset'] += self::SCAN_BATCH_SIZE;
        $scan_state['total_found'] += count( $coupons );

        if ( count( $coupons ) < self::SCAN_BATCH_SIZE ) {
            return $this->complete_scan( $scan_state );
        }

        return array(
            'scan_complete' => false,
            'message' => sprintf( 'Scanned coupons, found %d total items to delete...', $scan_state['total_found'] )
        );
    }

    private function complete_scan( &$scan_state ) {
        $scan_state['complete'] = true;
        
        // Store IDs in transients for deletion
        if ( ! empty( $scan_state['user_ids'] ) ) {
            set_transient( 'wbcp_user_ids', $scan_state['user_ids'], HOUR_IN_SECONDS );
        }
        if ( ! empty( $scan_state['order_ids'] ) ) {
            set_transient( 'wbcp_order_ids', $scan_state['order_ids'], HOUR_IN_SECONDS );
        }
        if ( ! empty( $scan_state['coupon_ids'] ) ) {
            set_transient( 'wbcp_coupon_ids', $scan_state['coupon_ids'], HOUR_IN_SECONDS );
        }

        return array(
            'scan_complete' => true,
            'total' => $scan_state['total_found'],
            'batch_size' => $this->settings['batch_size'],
            'message' => sprintf( 'Scan complete. Found %d items to delete.', $scan_state['total_found'] )
        );
    }

    /**
     * Run deletion batch
     */
    public function run_deletion_batch() {
        $deleted = 0;
        $log = array();
        $batch_size = $this->settings['batch_size'];

        // Delete users first
        $user_ids = get_transient( 'wbcp_user_ids' );
        if ( $user_ids && ! empty( $user_ids ) ) {
            $batch = array_splice( $user_ids, 0, $batch_size );
            foreach ( $batch as $user_id ) {
                if ( $this->delete_user_safely( $user_id ) ) {
                    $deleted++;
                    $log[] = "Deleted user ID: {$user_id}";
                }
            }
            set_transient( 'wbcp_user_ids', $user_ids, HOUR_IN_SECONDS );
        }

        // Delete orders if no users left
        if ( empty( $user_ids ) ) {
            $order_ids = get_transient( 'wbcp_order_ids' );
            if ( $order_ids && ! empty( $order_ids ) ) {
                $batch = array_splice( $order_ids, 0, $batch_size );
                foreach ( $batch as $order_id ) {
                    if ( $this->delete_order_safely( $order_id ) ) {
                        $deleted++;
                        $log[] = "Deleted order ID: {$order_id}";
                    }
                }
                set_transient( 'wbcp_order_ids', $order_ids, HOUR_IN_SECONDS );
            }
        }

        // Delete coupons if no orders left
        if ( empty( $user_ids ) && empty( get_transient( 'wbcp_order_ids' ) ) ) {
            $coupon_ids = get_transient( 'wbcp_coupon_ids' );
            if ( $coupon_ids && ! empty( $coupon_ids ) ) {
                $batch = array_splice( $coupon_ids, 0, $batch_size );
                foreach ( $batch as $coupon_id ) {
                    if ( $this->delete_coupon_safely( $coupon_id ) ) {
                        $deleted++;
                        $log[] = "Deleted coupon ID: {$coupon_id}";
                    }
                }
                set_transient( 'wbcp_coupon_ids', $coupon_ids, HOUR_IN_SECONDS );
            }
        }

        $complete = empty( get_transient( 'wbcp_user_ids' ) ) && 
                   empty( get_transient( 'wbcp_order_ids' ) ) && 
                   empty( get_transient( 'wbcp_coupon_ids' ) );

        if ( $complete ) {
            WBCP_Utils::cleanup_transients();
        }

        return array(
            'deleted' => $deleted,
            'log' => $log,
            'complete' => $complete
        );
    }

    private function should_delete_user( $user ) {
        // Never delete administrators
        if ( user_can( $user->ID, 'manage_options' ) ) {
            return false;
        }

        // Check role deletion
        if ( ! empty( $this->settings['delete_role'] ) ) {
            $user_obj = new WP_User( $user->ID );
            if ( in_array( $this->settings['delete_role'], $user_obj->roles ) ) {
                return true;
            }
        }

        // Check name deletion
        if ( $this->settings['delete_no_name'] ) {
            $first_name = get_user_meta( $user->ID, 'first_name', true );
            $last_name = get_user_meta( $user->ID, 'last_name', true );
            if ( empty( $first_name ) && empty( $last_name ) ) {
                return true;
            }
        }

        // Check domain deletion
        if ( $this->settings['delete_unlisted_domains'] ) {
            $allowed_domains = WBCP_Utils::get_allowed_domains();
            if ( ! empty( $allowed_domains ) ) {
                $email_domain = substr( strrchr( $user->user_email, '@' ), 1 );
                if ( ! in_array( $email_domain, $allowed_domains ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function delete_user_safely( $user_id ) {
        // Final safety check
        if ( user_can( $user_id, 'manage_options' ) ) {
            return false;
        }

        require_once( ABSPATH . 'wp-admin/includes/user.php' );
        return wp_delete_user( $user_id );
    }

    private function delete_order_safely( $order_id ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        return $order->delete( true );
    }

    private function delete_coupon_safely( $coupon_id ) {
        return wp_delete_post( $coupon_id, true );
    }
}
