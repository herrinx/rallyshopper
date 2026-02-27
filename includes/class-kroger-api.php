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
            $full_error = "HTTP {$status}: {$error_message}";
            
            // Log the error
            $db = new RallyShopper_Database();
            $db->add_log( 'error', 'kroger_api', $full_error, array(
                'endpoint' => $endpoint,
                'method' => $method,
                'url' => $url,
                'request_body' => $args['body'] ?? null,
                'status' => $status,
                'response' => $body,
                'raw_body' => wp_remote_retrieve_body( $response ),
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
    public function search_products( $query, $limit = 10, $offset = 0 ) {
        $params = array(
            'filter.term'   => sanitize_text_field( $query ),
            'filter.limit'  => intval( $limit ),
            'filter.offset' => intval( $offset ),
        );
        
        return $this->request( '/products', 'GET', null, $params );
    }
    
    // Get product details
    public function get_product( $product_id ) {
        $result = $this->request( '/products/' . sanitize_text_field( $product_id ) );
        if ( is_wp_error( $result ) ) {
            return null;
        }
        return isset( $result['data'] ) ? $result['data'] : null;
    }
    
    // Get cart
    public function get_cart() {
        return $this->request( '/cart' );
    }
    
    // Add items to cart
    public function add_to_cart( $items ) {
        // Items should be array of arrays with 'productId' and 'quantity'
        $body = array( 'items' => $items );
        return $this->request( '/cart/add', 'PUT', $body );
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
