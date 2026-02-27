<?php
/**
 * AJAX Handlers
 */

class RallyShopper_AJAX {
    
    public function __construct() {
        add_action( 'wp_ajax_rallyshopper_save_recipe', array( $this, 'ajax_save_recipe' ) );
        add_action( 'wp_ajax_rallyshopper_delete_recipe', array( $this, 'ajax_delete_recipe' ) );
        add_action( 'wp_ajax_rallyshopper_search_kroger', array( $this, 'ajax_search_kroger' ) );
        add_action( 'wp_ajax_rallyshopper_link_ingredient', array( $this, 'ajax_link_ingredient' ) );
        add_action( 'wp_ajax_rallyshopper_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_rallyshopper_get_cart', array( $this, 'ajax_get_cart' ) );
    }
    
    // Save recipe via AJAX
    public function ajax_save_recipe() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $data = array(
            'title'       => sanitize_text_field( $_POST['title'] ),
            'description' => sanitize_textarea_field( $_POST['description'] ),
            'instructions' => wp_kses_post( $_POST['instructions'] ),
            'servings'    => intval( $_POST['servings'] ),
            'prep_time'   => intval( $_POST['prep_time'] ),
            'cook_time'   => intval( $_POST['cook_time'] ),
            'difficulty'  => sanitize_text_field( $_POST['difficulty'] ),
        );
        
        if ( ! empty( $_POST['post_id'] ) ) {
            $data['post_id'] = intval( $_POST['post_id'] );
        }
        
        if ( ! empty( $_POST['ingredients'] ) ) {
            $data['ingredients'] = array_map( function( $ing ) {
                return array(
                    'name'               => sanitize_text_field( $ing['name'] ),
                    'amount'             => sanitize_text_field( $ing['amount'] ),
                    'unit'               => sanitize_text_field( $ing['unit'] ),
                    'kroger_product_id'  => isset( $ing['kroger_product_id'] ) ? sanitize_text_field( $ing['kroger_product_id'] ) : null,
                    'kroger_upc'         => isset( $ing['kroger_upc'] ) ? sanitize_text_field( $ing['kroger_upc'] ) : null,
                    'kroger_description' => isset( $ing['kroger_description'] ) ? sanitize_text_field( $ing['kroger_description'] ) : null,
                    'kroger_image_url'   => isset( $ing['kroger_image_url'] ) ? esc_url_raw( $ing['kroger_image_url'] ) : null,
                    'kroger_price'       => isset( $ing['kroger_price'] ) ? floatval( $ing['kroger_price'] ) : null,
                );
            }, $_POST['ingredients'] );
        }
        
        $result = RallyShopper_Recipe::save_recipe( $data );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( array(
            'post_id' => $result,
            'message' => 'Recipe saved successfully!',
        ) );
    }
    
    // Delete recipe
    public function ajax_delete_recipe() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $post_id = intval( $_POST['post_id'] );
        RallyShopper_Recipe::delete_recipe( $post_id );
        
        wp_send_json_success( 'Recipe deleted' );
    }
    
    // Search Kroger products
    public function ajax_search_kroger() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $query = sanitize_text_field( $_POST['query'] );
        
        if ( empty( $query ) ) {
            wp_send_json_error( 'Search query required' );
        }
        
        $kroger = new RallyShopper_Kroger_API();
        
        if ( ! $kroger->is_authenticated() ) {
            wp_send_json_error( 'Kroger not authenticated' );
        }
        
        $results = $kroger->search_products( $query, 10 );
        
        if ( is_wp_error( $results ) ) {
            error_log( 'Kroger search error: ' . $results->get_error_message() );
            wp_send_json_error( $results->get_error_message() );
        }
        
        error_log( 'Kroger search results: ' . json_encode( $results ) );
        
        $products = isset( $results['data'] ) ? $results['data'] : array();
        
        // Format products for response
        $formatted = array_map( function( $product ) {
            return array(
                'productId'   => $product['productId'],
                'upc'         => $product['upc'],
                'description' => $product['description'],
                'brand'       => $product['brand'] ?? '',
                'image'       => $product['images'][0]['sizes'][0]['url'] ?? '',
                'price'       => $product['items'][0]['price']['regular'] ?? null,
                'size'        => $product['items'][0]['size'] ?? '',
            );
        }, $products );
        
        wp_send_json_success( $formatted );
    }
    
    // Link ingredient to Kroger product
    public function ajax_link_ingredient() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $ingredient_id = intval( $_POST['ingredient_id'] );
        $product_id = sanitize_text_field( $_POST['product_id'] );
        $upc = sanitize_text_field( $_POST['upc'] );
        $description = sanitize_text_field( $_POST['description'] );
        $image_url = esc_url_raw( $_POST['image_url'] );
        $price = floatval( $_POST['price'] );
        
        $db = new RallyShopper_Database();
        
        // Get existing ingredient
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $ingredient_id ) );
        
        if ( ! $existing ) {
            wp_send_json_error( 'Ingredient not found' );
        }
        
        // Update with Kroger data
        $result = $db->save_ingredient( array(
            'id'                  => $ingredient_id,
            'recipe_id'           => $existing->recipe_id,
            'name'                => $existing->name,
            'amount'              => $existing->amount,
            'unit'                => $existing->unit,
            'kroger_product_id'   => $product_id,
            'kroger_upc'          => $upc,
            'kroger_description'  => $description,
            'kroger_image_url'    => $image_url,
            'kroger_price'        => $price,
            'sort_order'          => $existing->sort_order,
        ) );
        
        wp_send_json_success( 'Ingredient linked successfully' );
    }
    
    // Add recipe to cart
    public function ajax_add_to_cart() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $recipe_id = intval( $_POST['recipe_id'] );
        
        $db = new RallyShopper_Database();
        $kroger = new RallyShopper_Kroger_API();
        
        if ( ! $kroger->is_authenticated() ) {
            $db->add_log( 'error', 'add_to_cart', 'Kroger not authenticated', array(
                'recipe_id' => $recipe_id,
            ), $recipe_id );
            wp_send_json_error( 'Kroger not authenticated' );
        }
        
        // Get recipe ingredients
        $db = new RallyShopper_Database();
        $recipe_data = $db->get_recipe( $recipe_id );
        
        if ( ! $recipe_data ) {
            wp_send_json_error( 'Recipe not found' );
        }
        
        $ingredients = $db->get_ingredients( $recipe_data->id );
        
        // Build cart items
        $items = array();
        $errors = array();
        $added = array();
        
        foreach ( $ingredients as $ingredient ) {
            if ( ! $ingredient->kroger_product_id ) {
                $errors[] = $ingredient->name . ' - No Kroger product linked';
                continue;
            }
            
            $quantity = $this->parse_quantity( $ingredient->amount );
            
            $items[] = array(
                'productId' => $ingredient->kroger_product_id,
                'quantity'  => $quantity,
            );
            
            $added[] = array(
                'name'     => $ingredient->name,
                'quantity' => $quantity,
            );
            
            // Record purchase intent
            $db->record_purchase( array(
                'ingredient_id'     => $ingredient->id,
                'kroger_product_id' => $ingredient->kroger_product_id,
                'kroger_upc'        => $ingredient->kroger_upc,
                'quantity'          => $quantity,
                'price_paid'        => $ingredient->kroger_price ?? 0,
            ) );
        }
        
        if ( empty( $items ) ) {
            $db->add_log( 'warning', 'add_to_cart', 'No items to add to cart', array(
                'recipe_id' => $recipe_id,
                'errors' => $errors,
            ), $recipe_id );
            wp_send_json_error( array(
                'message' => 'No items to add to cart',
                'errors'  => $errors,
            ) );
        }
        
        // Add to Kroger cart
        $db->add_log( 'debug', 'add_to_cart', 'Sending ' . count( $items ) . ' items to Kroger cart', array(
            'recipe_id' => $recipe_id,
            'items' => $items,
        ), $recipe_id );
        
        $result = $kroger->add_to_cart( $items );
        
        if ( is_wp_error( $result ) ) {
            $db->add_log( 'error', 'add_to_cart', 'Kroger API error: ' . $result->get_error_message(), array(
                'recipe_id' => $recipe_id,
                'items' => $items,
                'error_data' => $result->get_error_data(),
            ), $recipe_id );
            wp_send_json_error( $result->get_error_message() );
        }
        
        $db->add_log( 'info', 'add_to_cart', 'Successfully added ' . count( $items ) . ' items to Kroger cart', array(
            'recipe_id' => $recipe_id,
            'items' => $items,
        ), $recipe_id );
        
        wp_send_json_success( array(
            'message' => 'Added ' . count( $items ) . ' items to cart',
            'added'   => $added,
            'errors'  => $errors,
        ) );
    }
    
    // Get cart contents
    public function ajax_get_cart() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $kroger = new RallyShopper_Kroger_API();
        
        if ( ! $kroger->is_authenticated() ) {
            wp_send_json_error( 'Kroger not authenticated' );
        }
        
        $cart = $kroger->get_cart();
        
        if ( is_wp_error( $cart ) ) {
            wp_send_json_error( $cart->get_error_message() );
        }
        
        wp_send_json_success( $cart );
    }
    
    // Parse quantity helper
    private function parse_quantity( $amount ) {
        if ( ! $amount ) {
            return 1;
        }
        
        if ( preg_match( '/^(\d+\.?\d*)/', $amount, $matches ) ) {
            return max( 1, ceil( floatval( $matches[1] ) ) );
        }
        
        return 1;
    }
}
