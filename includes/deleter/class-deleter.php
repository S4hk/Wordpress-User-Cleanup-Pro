<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Deleter {
    
    public function run_batch() {
        WBCP_Utils::set_execution_time_limit( 120 );
        
        $batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 100;
        
        // Get IDs from transient storage - prioritize users first, then orders, then coupons
        $all_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
        $all_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
        $all_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        
        if ( is_array( $all_user_ids ) && ! empty( $all_user_ids ) ) {
            $this->delete_users_batch( $all_user_ids, $batch_size );
        } elseif ( is_array( $all_order_ids ) && ! empty( $all_order_ids ) ) {
            $this->delete_orders_batch( $all_order_ids, $batch_size );
        } elseif ( is_array( $all_coupon_ids ) && ! empty( $all_coupon_ids ) ) {
            $this->delete_coupons_batch( $all_coupon_ids, $batch_size );
        } else {
            wp_send_json_success( array(
                'deleted' => 0,
                'remaining' => 0,
                'log' => array( 'No users, orders, or coupons left to delete.' ),
                'complete' => true
            ) );
        }
    }
    
    private function delete_users_batch( $all_user_ids, $batch_size ) {
        $to_delete = array_slice( $all_user_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_user_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $user_id ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                $log[] = "User ID {$user_id} not found (already deleted?).";
                continue;
            }
            
            // Final safety check - never delete administrators
            if ( in_array( 'administrator', (array)$user->roles, true ) ) {
                $log[] = "SAFETY: Skipped admin user ID {$user_id}.";
                continue;
            }
            
            // Perform deletion with error handling
            try {
                // Delete user metadata first
                global $wpdb;
                $wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );
                
                // Delete the user
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $result = wp_delete_user( $user_id );
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted user ID {$user_id} ({$user->user_login}).";
                } else {
                    $log[] = "Failed to delete user ID {$user_id} ({$user->user_login}).";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting user ID {$user_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $user_id, 'users' );
            wp_cache_delete( $user_id, 'user_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_user_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_user_ids_to_delete' );
        }
        
        // Check if there are orders or coupons to delete after users are done
        $remaining_orders = get_transient( 'wbcp_wc_order_ids_to_delete' );
        $remaining_coupons = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        $total_remaining = count( $remaining_ids ) + 
                          ( is_array( $remaining_orders ) ? count( $remaining_orders ) : 0 ) +
                          ( is_array( $remaining_coupons ) ? count( $remaining_coupons ) : 0 );
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => $total_remaining,
            'log' => $log,
            'complete' => $total_remaining === 0
        ) );
    }

    private function delete_orders_batch( $all_order_ids, $batch_size ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $to_delete = array_slice( $all_order_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_order_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $order_id ) {
            try {
                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                    $log[] = "Order ID {$order_id} not found (already deleted?).";
                    continue;
                }
                
                // Delete order completely with all metadata
                $result = $order->delete( true ); // true = force delete
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted order ID {$order_id}.";
                } else {
                    $log[] = "Failed to delete order ID {$order_id}.";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting order ID {$order_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $order_id, 'posts' );
            wp_cache_delete( $order_id, 'post_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_wc_order_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_wc_order_ids_to_delete' );
        }
        
        // Check if there are coupons to delete after orders are done
        $remaining_coupons = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        $total_remaining = count( $remaining_ids ) + ( is_array( $remaining_coupons ) ? count( $remaining_coupons ) : 0 );
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => $total_remaining,
            'log' => $log,
            'complete' => $total_remaining === 0
        ) );
    }

    private function delete_coupons_batch( $all_coupon_ids, $batch_size ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_send_json_error( array( 'message' => 'WooCommerce not found.' ) );
        }
        
        $to_delete = array_slice( $all_coupon_ids, 0, $batch_size );
        $remaining_ids = array_slice( $all_coupon_ids, $batch_size );
        
        $deleted_count = 0;
        $log = array();
        
        foreach ( $to_delete as $coupon_id ) {
            try {
                $coupon = new WC_Coupon( $coupon_id );
                if ( ! $coupon->get_id() ) {
                    $log[] = "Coupon ID {$coupon_id} not found (already deleted?).";
                    continue;
                }
                
                // Get coupon code for logging
                $coupon_code = $coupon->get_code();
                
                // Delete coupon completely with all metadata
                $result = wp_delete_post( $coupon_id, true ); // true = force delete, bypass trash
                
                if ( $result ) {
                    $deleted_count++;
                    $log[] = "Deleted coupon ID {$coupon_id} ('{$coupon_code}').";
                } else {
                    $log[] = "Failed to delete coupon ID {$coupon_id} ('{$coupon_code}').";
                }
            } catch ( Exception $e ) {
                $log[] = "Error deleting coupon ID {$coupon_id}: " . $e->getMessage();
            }
            
            // Memory cleanup
            wp_cache_delete( $coupon_id, 'posts' );
            wp_cache_delete( $coupon_id, 'post_meta' );
        }
        
        // Update stored IDs
        if ( ! empty( $remaining_ids ) ) {
            set_transient( 'wbcp_wc_coupon_ids_to_delete', $remaining_ids, HOUR_IN_SECONDS );
        } else {
            delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
        }
        
        wp_send_json_success( array(
            'deleted' => $deleted_count,
            'remaining' => count( $remaining_ids ),
            'log' => $log,
            'complete' => empty( $remaining_ids )
        ) );
    }
}