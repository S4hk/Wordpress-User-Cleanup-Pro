<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WBCP_Scanner {
    
    public function start_scan() {
        WBCP_Utils::set_execution_time_limit( 300 );
        
        $options = WBCP_Utils::get_options();
        $criteria = $this->extract_criteria( $options );
        $batch_size = isset( $options['batch_size'] ) ? intval( $options['batch_size'] ) : 100;
        
        // Initialize scan state
        $this->cleanup_previous_scan();
        $scan_state = $this->create_initial_scan_state( $criteria );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scanning' => true,
            'batch_size' => $batch_size,
            'message' => 'Starting user scan...'
        ) );
    }
    
    public function scan_batch() {
        WBCP_Utils::set_execution_time_limit( 60 );
        
        $scan_state = get_transient( 'wbcp_scan_state' );
        if ( ! $scan_state ) {
            wp_send_json_error( array( 'message' => 'Scan state lost. Please restart.' ) );
        }
        
        $criteria = $scan_state['criteria'];
        $offset = $scan_state['offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        // Get batch of users
        $args = array(
            'fields'       => array( 'ID', 'user_email', 'user_login' ),
            'number'       => $scan_batch_size,
            'offset'       => $offset,
            'role__not_in' => array( 'administrator' ),
            'orderby'      => 'ID',
            'order'        => 'ASC'
        );
        
        $user_query = new WP_User_Query( $args );
        $users = $user_query->get_results();
        
        if ( empty( $users ) ) {
            // Users scanning complete, now scan WooCommerce orders if needed
            if ( $criteria['delete_wc_orders'] && class_exists( 'WooCommerce' ) && ! empty( $criteria['wc_order_statuses'] ) ) {
                return $this->scan_wc_orders_batch( $scan_state );
            }
            // Then scan WooCommerce coupons if needed
            elseif ( $criteria['delete_wc_coupons'] && class_exists( 'WooCommerce' ) && ! empty( $criteria['wc_coupon_statuses'] ) ) {
                return $this->scan_wc_coupons_batch( $scan_state );
            }
            
            // All scanning complete
            return $this->complete_scan( $scan_state );
        }
        
        $current_batch_ids = array();
        
        foreach ( $users as $user ) {
            $user_id = $user->ID;
            
            // Double-check not admin (safety measure)
            $user_obj = get_userdata( $user_id );
            if ( ! $user_obj || in_array( 'administrator', (array)$user_obj->roles, true ) ) {
                continue;
            }
            
            $should_delete = false;
            
            // Check role deletion criteria first (most efficient)
            if ( $criteria['delete_role'] && in_array( $criteria['delete_role'], (array)$user_obj->roles, true ) ) {
                $should_delete = true;
            }
            // Check name criteria
            elseif ( $criteria['delete_no_name'] ) {
                $first = get_user_meta( $user_id, 'first_name', true );
                $last  = get_user_meta( $user_id, 'last_name', true );
                if ( ( empty( $first ) || trim( $first ) === '' ) && ( empty( $last ) || trim( $last ) === '' ) ) {
                    $should_delete = true;
                }
            }
            
            // Check domain criteria
            if ( ! $should_delete && $criteria['delete_unlisted_domains'] && ! empty( $criteria['allowed_domains'] ) ) {
                $email = strtolower( $user->user_email );
                $valid = false;
                foreach ( $criteria['allowed_domains'] as $domain ) {
                    if ( $domain === '' ) continue;
                    if ( substr( $email, -strlen( '@' . $domain ) ) === '@' . $domain ) {
                        $valid = true;
                        break;
                    }
                }
                if ( ! $valid ) {
                    $should_delete = true;
                }
            }
            
            if ( $should_delete ) {
                $current_batch_ids[] = $user_id;
            }
        }
        
        // Store found IDs
        if ( ! empty( $current_batch_ids ) ) {
            $existing_ids = get_transient( 'wbcp_user_ids_to_delete' );
            if ( ! is_array( $existing_ids ) ) {
                $existing_ids = array();
            }
            $existing_ids = array_merge( $existing_ids, $current_batch_ids );
            set_transient( 'wbcp_user_ids_to_delete', $existing_ids, HOUR_IN_SECONDS );
        }
        
        // Update scan state
        $scan_state['offset'] += $scan_batch_size;
        $scan_state['total_scanned'] += count( $users );
        $scan_state['total_to_delete'] += count( $current_batch_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $scan_state['total_scanned'],
            'found' => $scan_state['total_to_delete'],
            'batch_found' => count( $current_batch_ids ),
            'message' => "Scanned {$scan_state['total_scanned']} users, found {$scan_state['total_to_delete']} to delete..."
        ) );
    }
    
    private function extract_criteria( $options ) {
        $delete_no_name          = ! empty( $options['delete_no_name'] );
        $delete_unlisted_domains = ! empty( $options['delete_unlisted_domains'] );
        $allowed_domains         = isset( $options['allowed_domains'] ) ? explode( ',', strtolower( $options['allowed_domains'] ) ) : array();
        $delete_role             = isset( $options['delete_role'] ) ? sanitize_text_field( $options['delete_role'] ) : '';
        $delete_wc_orders        = ! empty( $options['delete_wc_orders'] );
        $wc_order_statuses       = isset( $options['wc_order_statuses'] ) && is_array( $options['wc_order_statuses'] ) ? $options['wc_order_statuses'] : array();
        $delete_wc_coupons       = ! empty( $options['delete_wc_coupons'] );
        $wc_coupon_statuses      = isset( $options['wc_coupon_statuses'] ) && is_array( $options['wc_coupon_statuses'] ) ? $options['wc_coupon_statuses'] : array();
        
        $allowed_domains = array_filter( array_map( 'trim', $allowed_domains ) );
        
        return array(
            'delete_no_name' => $delete_no_name,
            'delete_unlisted_domains' => $delete_unlisted_domains,
            'allowed_domains' => $allowed_domains,
            'delete_role' => $delete_role,
            'delete_wc_orders' => $delete_wc_orders,
            'wc_order_statuses' => $wc_order_statuses,
            'delete_wc_coupons' => $delete_wc_coupons,
            'wc_coupon_statuses' => $wc_coupon_statuses
        );
    }
    
    private function cleanup_previous_scan() {
        delete_transient( 'wbcp_scan_state' );
        delete_transient( 'wbcp_user_ids_to_delete' );
        delete_transient( 'wbcp_wc_order_ids_to_delete' );
        delete_transient( 'wbcp_wc_coupon_ids_to_delete' );
    }
    
    private function create_initial_scan_state( $criteria ) {
        return array(
            'offset' => 0,
            'total_scanned' => 0,
            'total_to_delete' => 0,
            'wc_offset' => 0,
            'wc_total_scanned' => 0,
            'wc_total_to_delete' => 0,
            'wc_coupon_offset' => 0,
            'wc_coupon_total_scanned' => 0,
            'wc_coupon_total_to_delete' => 0,
            'scan_batch_size' => 1000,
            'criteria' => $criteria
        );
    }
    
    private function scan_wc_orders_batch( $scan_state ) {
        $criteria = $scan_state['criteria'];
        $offset = $scan_state['wc_offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        // Get batch of WooCommerce orders
        $args = array(
            'limit' => $scan_batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'status' => $criteria['wc_order_statuses']
        );
        
        $orders = wc_get_orders( $args );
        
        if ( empty( $orders ) ) {
            // Orders scanning complete, now scan WooCommerce coupons if needed
            if ( $criteria['delete_wc_coupons'] && class_exists( 'WooCommerce' ) && ! empty( $criteria['wc_coupon_statuses'] ) ) {
                return $this->scan_wc_coupons_batch( $scan_state );
            }
            
            // All scanning complete
            return $this->complete_scan( $scan_state );
        }
        
        $current_batch_ids = array();
        
        foreach ( $orders as $order ) {
            $order_id = $order->get_id();
            
            // Check if order matches criteria for deletion
            $should_delete = true;
            
            // Example criteria check: delete only if order total is zero
            if ( $criteria['delete_wc_orders'] ) {
                $order_total = $order->get_total();
                if ( $order_total > 0 ) {
                    $should_delete = false;
                }
            }
            
            if ( $should_delete ) {
                $current_batch_ids[] = $order_id;
            }
        }
        
        // Store found IDs
        if ( ! empty( $current_batch_ids ) ) {
            $existing_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
            if ( ! is_array( $existing_ids ) ) {
                $existing_ids = array();
            }
            $existing_ids = array_merge( $existing_ids, $current_batch_ids );
            set_transient( 'wbcp_wc_order_ids_to_delete', $existing_ids, HOUR_IN_SECONDS );
        }
        
        // Update scan state
        $scan_state['wc_offset'] += $scan_batch_size;
        $scan_state['wc_total_scanned'] += count( $orders );
        $scan_state['wc_total_to_delete'] += count( $current_batch_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $scan_state['wc_total_scanned'],
            'found' => $scan_state['wc_total_to_delete'],
            'batch_found' => count( $current_batch_ids ),
            'message' => "Scanned {$scan_state['wc_total_scanned']} orders, found {$scan_state['wc_total_to_delete']} to delete..."
        ) );
    }
    
    private function scan_wc_coupons_batch( $scan_state ) {
        $criteria = $scan_state['criteria'];
        $offset = $scan_state['wc_coupon_offset'];
        $scan_batch_size = $scan_state['scan_batch_size'];
        
        // Get batch of WooCommerce coupons
        $args = array(
            'limit' => $scan_batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'status' => $criteria['wc_coupon_statuses']
        );
        
        $coupons = wc_get_coupons( $args );
        
        if ( empty( $coupons ) ) {
            // Coupons scanning complete
            return $this->complete_scan( $scan_state );
        }
        
        $current_batch_ids = array();
        
        foreach ( $coupons as $coupon ) {
            $coupon_id = $coupon->get_id();
            
            // Check if coupon matches criteria for deletion
            $should_delete = true;
            
            // Example criteria check: delete only if coupon amount is zero
            if ( $criteria['delete_wc_coupons'] ) {
                $coupon_amount = $coupon->get_amount();
                if ( $coupon_amount > 0 ) {
                    $should_delete = false;
                }
            }
            
            if ( $should_delete ) {
                $current_batch_ids[] = $coupon_id;
            }
        }
        
        // Store found IDs
        if ( ! empty( $current_batch_ids ) ) {
            $existing_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
            if ( ! is_array( $existing_ids ) ) {
                $existing_ids = array();
            }
            $existing_ids = array_merge( $existing_ids, $current_batch_ids );
            set_transient( 'wbcp_wc_coupon_ids_to_delete', $existing_ids, HOUR_IN_SECONDS );
        }
        
        // Update scan state
        $scan_state['wc_coupon_offset'] += $scan_batch_size;
        $scan_state['wc_coupon_total_scanned'] += count( $coupons );
        $scan_state['wc_coupon_total_to_delete'] += count( $current_batch_ids );
        set_transient( 'wbcp_scan_state', $scan_state, HOUR_IN_SECONDS );
        
        wp_send_json_success( array(
            'scan_complete' => false,
            'scanned' => $scan_state['wc_coupon_total_scanned'],
            'found' => $scan_state['wc_coupon_total_to_delete'],
            'batch_found' => count( $current_batch_ids ),
            'message' => "Scanned {$scan_state['wc_coupon_total_scanned']} coupons, found {$scan_state['wc_coupon_total_to_delete']} to delete..."
        ) );
    }
    
    private function complete_scan( $scan_state ) {
        $existing_user_ids = get_transient( 'wbcp_user_ids_to_delete' );
        $existing_order_ids = get_transient( 'wbcp_wc_order_ids_to_delete' );
        $existing_coupon_ids = get_transient( 'wbcp_wc_coupon_ids_to_delete' );
        $total_users = is_array( $existing_user_ids ) ? count( $existing_user_ids ) : 0;
        $total_orders = is_array( $existing_order_ids ) ? count( $existing_order_ids ) : 0;
        $total_coupons = is_array( $existing_coupon_ids ) ? count( $existing_coupon_ids ) : 0;
        $total_to_delete = $total_users + $total_orders + $total_coupons;
        
        delete_transient( 'wbcp_scan_state' );
        
        $message_parts = array();
        if ( $total_users > 0 ) $message_parts[] = "{$total_users} users";
        if ( $total_orders > 0 ) $message_parts[] = "{$total_orders} orders";
        if ( $total_coupons > 0 ) $message_parts[] = "{$total_coupons} coupons";
        
        if ( ! empty( $message_parts ) ) {
            $message = "Scan complete. Found " . implode( ', ', $message_parts ) . " to delete.";
        } else {
            $message = 'Scan complete. No users, orders, or coupons found matching criteria.';
        }
        
        wp_send_json_success( array(
            'scan_complete' => true,
            'total' => $total_to_delete,
            'batch_size' => WBCP_Utils::get_options()['batch_size'] ?? 100,
            'message' => $message
        ) );
    }
}