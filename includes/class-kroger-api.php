<?php
/**
 * Kroger API Integration
 * Documentation: https://developer.kroger.com/
 */

class RallyShopper_Kroger_API {
    private $client_id;
    private $client_secret;
    private $access_token;
    private $base_url = 'https://api.kroger.com/v1';
    private $auth_url = 'https://api.kroger.com/v1/connect/oauth2';
    
    public function __construct() {
        $this->client_id = get_option( 'rallyshopper_kroger_client_id' );
        $this->client_secret = get_option( 'rallyshopper_kroger_client_secret' );
        $this->access_token = get_option( 'rallyshopper_kroger_access_token' );
    }
    
    // Check if API is configured
    public function is_configured() {
        return ! empty( $this->client_id ) && ! empty( $this->client_secret );
    }
    
    // Check if authenticated
    public function is_authenticated() {
        return ! empty( $this->access_token );
    }
    
    // Get authorization URL for OAuth
    public function get_auth_url( $redirect_uri ) {
        $state = wp_create_nonce( 'rallyshopper_kroger_auth' );
        
        $params = array(
            'client_id'     => $this->client_id,
            'redirect_uri'  => $redirect_uri,
            'response_type' => 'code',
            'scope'         => 'product.compact cart.basic:write profile.compact',
            'state'         => $state,
        );
        
        return $this->auth_url . '/authorize?' . http_build_query( $params );
    }
    
    // Exchange authorization code for tokens
    public function exchange_code( $code, $redirect_uri ) {
        $response = wp_remote_post( $this->auth_url . '/token', array(
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
            ),
            'body' => array(
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $redirect_uri,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Debug logging
        $db = new RallyShopper_Database();
        $db->add_log( 'debug', 'kroger_auth_exchange', 'Token exchange response', array(
            'status' => wp_remote_retrieve_response_code( $response ),
            'body' => $body,
            'redirect_uri' => $redirect_uri,
        ) );
        
        if ( isset( $body['access_token'] ) ) {
            update_option( 'rallyshopper_kroger_access_token', $body['access_token'] );
            update_option( 'rallyshopper_kroger_refresh_token', $body['refresh_token'] );
            update_option( 'rallyshopper_kroger_token_expires', time() + $body['expires_in'] );
            $this->access_token = $body['access_token'];
            return true;
        }
        
        return new WP_Error( 'auth_failed', $body['error_description'] ?? 'Authentication failed' );
    }
    
    // Refresh access token
    public function refresh_token() {
        $refresh_token = get_option( 'rallyshopper_kroger_refresh_token' );
        
        if ( ! $refresh_token ) {
            return new WP_Error( 'no_refresh_token', 'No refresh token available' );
        }
        
        $response = wp_remote_post( $this->auth_url . '/token', array(
            'headers' => array(
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
            ),
            'body' => array(
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['access_token'] ) ) {
            update_option( 'rallyshopper_kroger_access_token', $body['access_token'] );
            update_option( 'rallyshopper_kroger_token_expires', time() + $body['expires_in'] );
            
            if ( isset( $body['refresh_token'] ) ) {
                update_option( 'rallyshopper_kroger_refresh_token', $body['refresh_token'] );
            }
            
            $this->access_token = $body['access_token'];
            return true;
        }
        
        return new WP_Error( 'refresh_failed', $body['error_description'] ?? 'Token refresh failed' );
    }
    
    // Make authenticated API request
    private function request( $endpoint, $method = 'GET', $body = null, $params = array() ) {
        if ( ! $this->is_authenticated() ) {
            return new WP_Error( 'not_authenticated', 'Kroger API not authenticated' );
        }
        
        // Check if token needs refresh
        $expires = get_option( 'rallyshopper_kroger_token_expires' );
        if ( $expires && $expires < time() + 300 ) { // Refresh if expires in 5 minutes
            $refresh = $this->refresh_token();
            if ( is_wp_error( $refresh ) ) {
                return $refresh;
            }
        }
        
        $url = $this->base_url . $endpoint;
        
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token,
                'Accept'        => 'application/json',
            ),
            'method'  => $method,
        );
        
        if ( $body ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = json_encode( $body );
        }
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $status = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status >= 400 ) {
            $error_message = $body['error_description'] ?? ( $body['error'] ?? 'API request failed' );
            $raw_body = wp_remote_retrieve_body( $response );
            $full_error = "HTTP {$status}: {$error_message} | Response: {$raw_body}";
            
            // Log the error
            $db = new RallyShopper_Database();
            $db->add_log( 'error', 'kroger_api', $full_error, array(
                'endpoint' => $endpoint,
                'method' => $method,
                'url' => $url,
                'request_body' => $args['body'] ?? null,
                'status' => $status,
                'response' => $body,
                'raw_body' => $raw_body,
            ) );
            
            return new WP_Error( 
                'api_error', 
                $full_error, 
                array( 'status' => $status, 'body' => $body ) 
            );
        }
        
        return $body;
    }
    
    // Search products
    public function search_products( $query, $limit = 10, $offset = 0, $location_id = null, $fulfillment_type = 'delivery', $filters = array() ) {
        $params = array(
            'filter.term'   => sanitize_text_field( $query ),
            'filter.limit'  => intval( $limit ),
            'filter.offset' => intval( $offset ),
        );

        // Add location filter if set and valid (must be 8 alphanumeric characters)
        if ( ! $location_id ) {
            $location_id = get_option( 'rallyshopper_kroger_location_id' );
        }

        if ( $location_id && preg_match( '/^[a-zA-Z0-9]{8}$/', $location_id ) ) {
            $params['filter.locationId'] = sanitize_text_field( $location_id );
        }

        // Add fulfillment type (delivery, pickup, instore)
        $params['filter.fulfillmentType'] = sanitize_text_field( $fulfillment_type );

        // Add optional filters
        if ( ! empty( $filters['brand'] ) ) {
            $params['filter.brand'] = sanitize_text_field( $filters['brand'] );
        }
        if ( ! empty( $filters['category'] ) ) {
            $params['filter.category'] = sanitize_text_field( $filters['category'] );
        }
        if ( ! empty( $filters['priceMin'] ) ) {
            $params['filter.price.gte'] = floatval( $filters['priceMin'] );
        }
        if ( ! empty( $filters['priceMax'] ) ) {
            $params['filter.price.lte'] = floatval( $filters['priceMax'] );
        }
        if ( ! empty( $filters['size'] ) ) {
            $params['filter.size'] = sanitize_text_field( $filters['size'] );
        }

        return $this->request( '/products', 'GET', null, $params );
    }

    // Get store locations near a zip code
    public function get_locations( $zip = null, $radius = 25 ) {
        $params = array(
            'filter.radiusInMiles' => intval( $radius ),
            'filter.limit' => 10,
        );

        if ( $zip ) {
            $params['filter.zipCode.near'] = sanitize_text_field( $zip );
        }

        return $this->request( '/locations', 'GET', null, $params );
    }
    
    // Get product details
    public function get_product( $product_id ) {
        $result = $this->request( '/products/' . sanitize_text_field( $product_id ) );
        if ( is_wp_error( $result ) ) {
            return null;
        }
        return isset( $result['data'] ) ? $result['data'] : null;
    }
    
    // Add items to cart using PUBLIC API (adds directly, no cart management)
    public function add_to_cart( $items, $modality = 'DELIVERY' ) {
        $db = new RallyShopper_Database();
        $added = array();
        $errors = array();
        
        // Add items individually to avoid batch conflicts
        foreach ( $items as $item ) {
            // Determine UPC - prefer upc if available, otherwise use productId if it looks like UPC
            $upc = $item['upc'] ?? '';
            if ( ! $upc && isset( $item['productId'] ) ) {
                if ( preg_match( '/^\d{12,14}$/', $item['productId'] ) ) {
                    $upc = $item['productId'];
                }
            }
            
            if ( ! $upc ) {
                $db->add_log( 'error', 'cart_add', 'No UPC for item', $item );
                $errors[] = 'No UPC for ' . ( $item['name'] ?? 'item' );
                continue;
            }
            
            $body = array(
                'items' => array(
                    array(
                        'upc' => $upc,
                        'quantity' => intval( $item['quantity'] ?? 1 ),
                    )
                )
            );
            
            // Add individually to avoid conflicts
            $result = $this->request( '/cart/add', 'PUT', $body );
            
            // Retry on 409 conflict (up to 3 times with backoff)
            $retries = 0;
            $max_retries = 3;
            while ( is_wp_error( $result ) && $retries < $max_retries ) {
                $error_data = $result->get_error_data();
                $status = $error_data['status'] ?? 0;
                
                if ( $status === 409 || $status === 503 ) {
                    $retries++;
                    $delay = $retries * 500000; // 0.5s, 1s, 1.5s
                    usleep( $delay );
                    $db->add_log( 'debug', 'cart_add', 'Retry ' . $retries . ' for ' . ( $item['name'] ?? $upc ) . ' after ' . $status );
                    $result = $this->request( '/cart/add', 'PUT', $body );
                } else {
                    break;
                }
            }
            
            if ( is_wp_error( $result ) ) {
                $error_data = $result->get_error_data();
                $status = $error_data['status'] ?? 0;
                
                // On 409 conflict, check product availability
                if ( $status === 409 ) {
                    // Search by UPC to get stock level (requires location ID for inventory)
                    $stock_level = 'Unknown';
                    $search_result = $this->search_products( $upc, 1 );
                    
                    if ( ! is_wp_error( $search_result ) && isset( $search_result['data'][0] ) ) {
                        $product_data = $search_result['data'][0];
                        $stock_level = $product_data['inventory']['stockLevel'] ?? 'Unknown';
                    }
                    
                    $error_msg = $result->get_error_message();
                    $detailed_error = ( $item['name'] ?? $upc ) . ': ' . $error_msg . ' [Stock: ' . $stock_level . ']';
                    $db->add_log( 'error', 'cart_add', 'Failed to add ' . ( $item['name'] ?? $upc ) . ' after ' . $retries . ' retries: ' . $error_msg . ' | Stock: ' . $stock_level );
                    $errors[] = $detailed_error;
                } else {
                    $error_msg = $result->get_error_message();
                    $db->add_log( 'error', 'cart_add', 'Failed to add ' . ( $item['name'] ?? $upc ) . ': ' . $error_msg );
                    $errors[] = ( $item['name'] ?? $upc ) . ': ' . $error_msg;
                }
            } else {
                $added[] = array(
                    'name' => $item['name'] ?? $upc,
                    'upc' => $upc,
                );
            }
            
            // Small delay to avoid rate limiting
            usleep( 100000 ); // 100ms
        }
        
        if ( empty( $added ) && ! empty( $errors ) ) {
            return new WP_Error( 'add_failed', implode( '; ', $errors ) );
        }
        
        return array( 
            'success' => true, 
            'added' => count( $added ), 
            'items' => $added,
            'errors' => $errors,
        );
    }
    
    // Get cart - Public API uses different endpoint
    public function get_cart() {
        // Try to get cart - Public API returns 404 if cart is empty
        $result = $this->request( '/cart', 'GET' );
        
        if ( is_wp_error( $result ) && $result->get_error_code() === 'api_error' ) {
            $data = $result->get_error_data();
            if ( isset( $data['status'] ) && $data['status'] === 404 ) {
                // Cart doesn't exist yet - return empty cart
                return array( 'data' => array( 'items' => array() ) );
            }
        }
        
        return $result;
    }

    // Add single item to cart
    public function add_item_to_cart( $product_id, $quantity = 1, $upc = null ) {
        $item = array(
            'productId' => sanitize_text_field( $product_id ),
            'quantity'  => intval( $quantity ),
        );

        if ( $upc ) {
            $item['upc'] = sanitize_text_field( $upc );
        }

        return $this->add_to_cart( array( $item ) );
    }
    
    // Add recipe ingredients to cart
    public function add_recipe_to_cart( $recipe_id ) {
        $db = new RallyShopper_Database();
        $ingredients = $db->get_ingredients( $recipe_id );
        
        $items = array();
        $errors = array();
        $item_details = array();
        
        foreach ( $ingredients as $ingredient ) {
            if ( ! $ingredient->kroger_product_id ) {
                $errors[] = $ingredient->name . ' - No Kroger product linked';
                continue;
            }
            
            // Parse amount to get quantity
            $quantity = $this->parse_quantity( $ingredient->amount );
            $qty = max( 1, $quantity );
            
            $items[] = array(
                'productId' => $ingredient->kroger_product_id,
                'quantity'  => $qty,
            );
            
            $item_details[] = array(
                'name' => $ingredient->name,
                'product_id' => $ingredient->kroger_product_id,
                'quantity' => $qty,
            );
            
            // Track this as a purchase intent
            $db->record_purchase( array(
                'ingredient_id'     => $ingredient->id,
                'kroger_product_id' => $ingredient->kroger_product_id,
                'kroger_upc'        => $ingredient->kroger_upc,
                'quantity'          => $qty,
                'price_paid'        => $ingredient->kroger_price ?? 0,
            ) );
        }
        
        if ( empty( $items ) ) {
            $error_msg = 'No items to add to cart' . ( ! empty( $errors ) ? ': ' . implode( ', ', $errors ) : '' );
            $db->add_log( 'warning', 'add_to_cart', $error_msg, array(
                'recipe_id' => $recipe_id,
                'errors' => $errors,
            ) );
            return new WP_Error( 'no_items', $error_msg, array( 'errors' => $errors ) );
        }
        
        $result = $this->add_to_cart( $items );
        
        if ( is_wp_error( $result ) ) {
            $db->add_log( 'error', 'add_to_cart', 'Failed to add items to cart: ' . $result->get_error_message(), array(
                'recipe_id' => $recipe_id,
                'items' => $item_details,
                'error' => $result->get_error_data(),
            ), $recipe_id );
            return $result;
        }
        
        // Log success
        $db->add_log( 'info', 'add_to_cart', 'Added ' . count( $items ) . ' items to Kroger cart', array(
            'recipe_id' => $recipe_id,
            'items' => $item_details,
            'errors' => $errors,
        ), $recipe_id );
        
        return array(
            'success'  => true,
            'added'    => count( $items ),
            'errors'   => $errors,
            'cart'     => $result,
        );
    }
    
    // Parse quantity from amount string
    private function parse_quantity( $amount ) {
        if ( ! $amount ) {
            return 1;
        }
        
        // Extract number from strings like "2 cups", "1.5 lbs", "3"
        if ( preg_match( '/^(\d+\.?\d*)/', $amount, $matches ) ) {
            return ceil( floatval( $matches[1] ) );
        }
        
        return 1;
    }
    
    // Get user profile
    public function get_profile() {
        return $this->request( '/identity/profile' );
    }
}
