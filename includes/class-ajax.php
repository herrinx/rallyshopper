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
        add_action( 'wp_ajax_rallyshopper_find_stores', array( $this, 'ajax_find_stores' ) );
        add_action( 'wp_ajax_rallyshopper_search_recipes_live', array( $this, 'ajax_search_recipes_live' ) );
        add_action( 'wp_ajax_rallyshopper_get_categories', array( $this, 'ajax_get_categories' ) );
        add_action( 'wp_ajax_rallyshopper_save_category', array( $this, 'ajax_save_category' ) );
        add_action( 'wp_ajax_rallyshopper_delete_category', array( $this, 'ajax_delete_category' ) );
        add_action( 'wp_ajax_rallyshopper_get_meal_plans', array( $this, 'ajax_get_meal_plans' ) );
        add_action( 'wp_ajax_rallyshopper_save_meal_plan', array( $this, 'ajax_save_meal_plan' ) );
        add_action( 'wp_ajax_rallyshopper_delete_meal_plan', array( $this, 'ajax_delete_meal_plan' ) );
        add_action( 'wp_ajax_rallyshopper_add_to_meal_plan', array( $this, 'ajax_add_to_meal_plan' ) );
        add_action( 'wp_ajax_rallyshopper_remove_from_meal_plan', array( $this, 'ajax_remove_from_meal_plan' ) );
        add_action( 'wp_ajax_rallyshopper_get_meal_plan_recipes', array( $this, 'ajax_get_meal_plan_recipes' ) );
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
        
        // Get ingredients from JSON or array
        $ingredients = array();
        if ( ! empty( $_POST['ingredients_json'] ) ) {
            // WordPress may have already stripped slashes, so check first
            $json_data = $_POST['ingredients_json'];
            if ( strpos( $json_data, '\\"' ) !== false || strpos( $json_data, '\\n' ) !== false ) {
                $json_data = stripslashes( $json_data );
            }
            $ingredients = json_decode( $json_data, true );
        } elseif ( ! empty( $_POST['ingredients'] ) ) {
            $ingredients = $_POST['ingredients'];
        }
        
        if ( ! empty( $ingredients ) ) {
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
                    'is_staple'          => isset( $ing['is_staple'] ) ? intval( $ing['is_staple'] ) : 0,
                );
            }, $ingredients );
        }
        
        $result = RallyShopper_Recipe::save_recipe( $data );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }
        
        wp_send_json_success( array(
            'post_id' => $result,
            'message' => 'Recipe saved successfully!',
            'debug' => isset( $data['ingredients'] ) ? json_encode( $data['ingredients'] ) : 'No ingredients',
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
        
        try {
            $kroger = new RallyShopper_Kroger_API();
            
            if ( ! $kroger->is_authenticated() ) {
                wp_send_json_error( 'Kroger not authenticated' );
            }
            
            // Get filter parameters
            $filters = isset( $_POST['filters'] ) ? $_POST['filters'] : array();
            
            $results = $kroger->search_products( $query, 10, 0, null, 'delivery', $filters );
            
            if ( is_wp_error( $results ) ) {
                $db = new RallyShopper_Database();
                $db->add_log( 'error', 'kroger_search', $results->get_error_message(), array('query' => $query) );
                wp_send_json_error( $results->get_error_message() );
            }
            
            $products = isset( $results['data'] ) ? $results['data'] : array();
        
        // Format products for response
        $formatted = array_map( function( $product ) {
            // Get all available image sizes
            $images = array();
            if ( isset( $product['images'] ) && is_array( $product['images'] ) ) {
                foreach ( $product['images'] as $image ) {
                    if ( isset( $image['sizes'] ) && is_array( $image['sizes'] ) ) {
                        foreach ( $image['sizes'] as $size ) {
                            $images[$size['size']] = $size['url'];
                        }
                    }
                }
            }

            // Get inventory/stock level
            $stock_level = null;
            $inventory = null;
            if ( isset( $product['items'][0]['inventory'] ) ) {
                $inventory = $product['items'][0]['inventory'];
                $stock_level = $inventory['stockLevel'] ?? null;
            }

            // Get price info
            $price = null;
            $promo_price = null;
            if ( isset( $product['items'][0]['price'] ) ) {
                $price = $product['items'][0]['price']['regular'] ?? null;
                $promo_price = $product['items'][0]['price']['promo'] ?? null;
            }

            // Get fulfillment availability
            $fulfillment = array();
            if ( isset( $product['items'][0]['fulfillment'] ) ) {
                $fulfillment = $product['items'][0]['fulfillment'];
            }

            return array(
                'productId'      => $product['productId'],
                'upc'            => $product['upc'],
                'description'    => $product['description'],
                'brand'          => $product['brand'] ?? '',
                'images'         => $images,
                'image'          => $images['medium'] ?? $images['small'] ?? $images['large'] ?? $images['xlarge'] ?? $images['thumbnail'] ?? '',
                'price'          => $price,
                'promo_price'    => $promo_price,
                'size'           => $product['items'][0]['size'] ?? '',
                'stock_level'    => $stock_level,
                'inventory'      => $inventory,
                'fulfillment'    => $fulfillment,
                'sold_by'        => $product['items'][0]['soldBy'] ?? '',
                'aisle_location' => $product['items'][0]['aisleLocation'] ?? '',
            );
        }, $products );
        
            wp_send_json_success( $formatted );
        } catch ( Exception $e ) {
            $db = new RallyShopper_Database();
            $db->add_log( 'error', 'kroger_search_exception', $e->getMessage(), array('query' => $query, 'trace' => $e->getTraceAsString()) );
            wp_send_json_error( 'Search failed: ' . $e->getMessage() );
        }
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
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_recipes';
        
        // Debug logging
        $db->add_log( 'debug', 'add_to_cart', 'Looking for recipe ' . $recipe_id . ' in table ' . $table );
        
        $recipe_data = $db->get_recipe( $recipe_id );
        
        if ( ! $recipe_data ) {
            $db->add_log( 'error', 'add_to_cart', 'Recipe not found: ' . $recipe_id . ' (table: ' . $table . ')' );
            wp_send_json_error( 'Recipe not found' );
        }
        
        // Build cart items
        $items = array();
        $errors = array();
        $added = array();
        
        // Check if items were provided (for staples flow)
        if ( ! empty( $_POST['items'] ) && is_array( $_POST['items'] ) ) {
            // Use provided items (staples flow)
            foreach ( $_POST['items'] as $item ) {
                if ( ! empty( $item['upc'] ) ) {
                    $items[] = array(
                        'productId' => sanitize_text_field( $item['upc'] ),
                        'quantity'  => intval( $item['quantity'] ?? 1 ),
                    );
                    $added[] = array(
                        'name'     => sanitize_text_field( $item['name'] ?? 'Item' ),
                        'quantity' => intval( $item['quantity'] ?? 1 ),
                    );
                }
            }
        } else {
            // Look up all ingredients from database (normal flow)
            $ingredients = $db->get_ingredients( $recipe_data->id );
            
            foreach ( $ingredients as $ingredient ) {
                if ( ! $ingredient->kroger_product_id ) {
                    $errors[] = $ingredient->name . ' - No Kroger product linked';
                    continue;
                }
                
                // Staples: always quantity 1 (just add the product)
                // Regular ingredients: parse quantity from amount
                if ( $ingredient->is_staple ) {
                    $quantity = 1;
                } else {
                    $quantity = $this->parse_quantity( $ingredient->amount );
                }
                
                $db->add_log( 'debug', 'add_to_cart', 'Ingredient: ' . $ingredient->name . ' amount=' . $ingredient->amount . ' qty=' . $quantity . ' staple=' . $ingredient->is_staple );
                
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

    // Live recipe search with Levenshtein fuzzy matching
    public function ajax_search_recipes_live() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $query = strtolower( sanitize_text_field( $_POST['query'] ?? '' ) );

        if ( empty( $query ) ) {
            wp_send_json_success( array( 'results' => array(), 'all_ids' => array() ) );
        }

        $db = new RallyShopper_Database();
        $all_recipes = RallyShopper_Recipe::get_recipes();
        $results = array();
        $all_ids = array();

        foreach ( $all_recipes as $recipe ) {
            $post = $recipe['post'];
            $all_ids[] = $post->ID;

            $title = strtolower( $post->post_title );
            $content = strtolower( $post->post_content );
            $excerpt = strtolower( $post->post_excerpt );

            // Direct string match
            if ( strpos( $title, $query ) !== false ||
                 strpos( $content, $query ) !== false ||
                 strpos( $excerpt, $query ) !== false ) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'score' => 100,
                );
                continue;
            }

            // Check ingredients with Levenshtein fuzzy matching
            $ingredient_match = false;
            $best_score = PHP_INT_MAX;

            foreach ( $recipe['ingredients'] as $ing ) {
                $ing_name = strtolower( $ing->kroger_description ?? $ing->name ?? '' );

                // Direct ingredient match
                if ( strpos( $ing_name, $query ) !== false ) {
                    $ingredient_match = true;
                    $best_score = 0;
                    break;
                }

                // Levenshtein distance for fuzzy match
                if ( strlen( $ing_name ) > 0 && strlen( $query ) > 0 ) {
                    $distance = levenshtein( $query, $ing_name );
                    $max_len = max( strlen( $query ), strlen( $ing_name ) );
                    $similarity = 100 - ( $distance / $max_len * 100 );

                    if ( $similarity > 60 ) { // 60% similarity threshold
                        $ingredient_match = true;
                        if ( $distance < $best_score ) {
                            $best_score = $distance;
                        }
                    }
                }
            }

            if ( $ingredient_match ) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'score' => $best_score,
                );
            }
        }

        // Sort by score (lower is better for Levenshtein)
        usort( $results, function( $a, $b ) {
            return $a['score'] - $b['score'];
        } );

        wp_send_json_success( array(
            'results' => $results,
            'all_ids' => $all_ids,
        ) );
    }

    // Find nearby Kroger stores
    public function ajax_find_stores() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $zip = sanitize_text_field( $_POST['zip'] );
        
        if ( empty( $zip ) ) {
            wp_send_json_error( 'Zip code required' );
        }
        
        $kroger = new RallyShopper_Kroger_API();
        
        if ( ! $kroger->is_authenticated() ) {
            wp_send_json_error( 'Kroger not authenticated' );
        }
        
        $results = $kroger->get_locations( $zip, 25 );
        
        if ( is_wp_error( $results ) ) {
            wp_send_json_error( $results->get_error_message() );
        }
        
        $locations = isset( $results['data'] ) ? $results['data'] : array();
        
        // Format for response
        $formatted = array_map( function( $location ) {
            return array(
                'locationId' => $location['locationId'],
                'name'       => $location['name'],
                'address'    => $location['address'],
            );
        }, $locations );
        
        wp_send_json_success( $formatted );
    }

    // Category AJAX handlers
    public function ajax_get_categories() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $categories = $db->get_categories();
        wp_send_json_success( $categories );
    }

    public function ajax_save_category() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = array(
            'name' => sanitize_text_field( $_POST['name'] ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'color' => sanitize_text_field( $_POST['color'] ?? '#0073aa' ),
            'sort_order' => intval( $_POST['sort_order'] ?? 0 ),
        );

        if ( ! empty( $_POST['id'] ) ) {
            $data['id'] = intval( $_POST['id'] );
        }

        $db = new RallyShopper_Database();
        $id = $db->save_category( $data );
        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_delete_category() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $db->delete_category( intval( $_POST['id'] ) );
        wp_send_json_success();
    }

    // Meal Plan AJAX handlers
    public function ajax_get_meal_plans() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $plans = $db->get_meal_plans( false );
        wp_send_json_success( $plans );
    }

    public function ajax_save_meal_plan() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $data = array(
            'name' => sanitize_text_field( $_POST['name'] ),
            'week_start' => sanitize_text_field( $_POST['week_start'] ?? null ),
            'is_active' => intval( $_POST['is_active'] ?? 1 ),
        );

        if ( ! empty( $_POST['id'] ) ) {
            $data['id'] = intval( $_POST['id'] );
        }

        $db = new RallyShopper_Database();
        $id = $db->save_meal_plan( $data );
        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_delete_meal_plan() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $db->delete_meal_plan( intval( $_POST['id'] ) );
        wp_send_json_success();
    }

    public function ajax_add_to_meal_plan() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $id = $db->add_recipe_to_meal_plan(
            intval( $_POST['plan_id'] ),
            intval( $_POST['recipe_id'] ),
            sanitize_text_field( $_POST['day_of_week'] ?? null ),
            sanitize_text_field( $_POST['meal_type'] ?? null ),
            sanitize_textarea_field( $_POST['notes'] ?? '' )
        );
        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_remove_from_meal_plan() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $db->remove_recipe_from_meal_plan(
            intval( $_POST['plan_id'] ),
            intval( $_POST['recipe_id'] )
        );
        wp_send_json_success();
    }

    public function ajax_get_meal_plan_recipes() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

        $db = new RallyShopper_Database();
        $recipes = $db->get_meal_plan_recipes( intval( $_POST['plan_id'] ) );
        wp_send_json_success( $recipes );
    }
}
