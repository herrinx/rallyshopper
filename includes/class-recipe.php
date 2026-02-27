<?php
/**
 * Recipe Post Type and CRUD Operations
 */

class RallyShopper_Recipe {
    
    public static function register_post_type() {
        $labels = array(
            'name'                  => 'Recipes',
            'singular_name'         => 'Recipe',
            'menu_name'             => 'Herrecipes',
            'add_new'               => 'Add New Recipe',
            'add_new_item'          => 'Add New Recipe',
            'edit_item'             => 'Edit Recipe',
            'new_item'              => 'New Recipe',
            'view_item'             => 'View Recipe',
            'search_items'          => 'Search Recipes',
            'not_found'             => 'No recipes found',
            'not_found_in_trash'    => 'No recipes found in trash',
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'has_archive'         => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => 'rallyshopper_recipes',
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-food',
            'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'rewrite'             => array( 'slug' => 'recipes' ),
            'show_in_rest'        => true,
        );
        
        register_post_type( 'rallyshopper_recipe', $args );
    }
    
    // Get recipe with all metadata
    public static function get_recipe( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post || $post->post_type !== 'rallyshopper_recipe' ) {
            return null;
        }
        
        $db = new RallyShopper_Database();
        $recipe_data = $db->get_recipe( $post_id );
        $ingredients = $db->get_ingredients( $recipe_data ? $recipe_data->id : 0 );
        
        // Add staple info to ingredients
        foreach ( $ingredients as &$ingredient ) {
            if ( $ingredient->kroger_product_id ) {
                $ingredient->staple_count = $db->is_staple( $ingredient->kroger_product_id );
            } else {
                $ingredient->staple_count = 0;
            }
        }
        
        return array(
            'post'        => $post,
            'recipe_data' => $recipe_data,
            'ingredients' => $ingredients,
            'meta'        => array(
                'servings'    => $recipe_data ? $recipe_data->servings : 4,
                'prep_time'   => $recipe_data ? $recipe_data->prep_time : 0,
                'cook_time'   => $recipe_data ? $recipe_data->cook_time : 0,
                'difficulty'  => $recipe_data ? $recipe_data->difficulty : 'medium',
            ),
        );
    }
    
    // Save recipe
    public static function save_recipe( $data ) {
        // Validate
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', 'Recipe title is required' );
        }
        
        $post_data = array(
            'post_title'   => sanitize_text_field( $data['title'] ),
            'post_content' => isset( $data['instructions'] ) ? wp_kses_post( $data['instructions'] ) : '',
            'post_excerpt' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
            'post_status'  => 'publish',
            'post_type'    => 'rallyshopper_recipe',
        );
        
        if ( isset( $data['post_id'] ) && $data['post_id'] ) {
            $post_data['ID'] = intval( $data['post_id'] );
            $post_id = wp_update_post( $post_data );
        } else {
            $post_id = wp_insert_post( $post_data );
        }
        
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }
        
        // Save recipe meta
        $db = new RallyShopper_Database();
        $recipe_db_data = array(
            'post_id'    => $post_id,
            'servings'   => isset( $data['servings'] ) ? intval( $data['servings'] ) : 4,
            'prep_time'  => isset( $data['prep_time'] ) ? intval( $data['prep_time'] ) : 0,
            'cook_time'  => isset( $data['cook_time'] ) ? intval( $data['cook_time'] ) : 0,
            'difficulty' => isset( $data['difficulty'] ) ? sanitize_text_field( $data['difficulty'] ) : 'medium',
        );
        
        $existing = $db->get_recipe( $post_id );
        if ( $existing ) {
            $recipe_db_data['id'] = $existing->id;
        }
        
        $recipe_id = $db->save_recipe( $recipe_db_data );
        
        // Save ingredients
        if ( isset( $data['ingredients'] ) && is_array( $data['ingredients'] ) ) {
            // Delete existing ingredients if updating
            if ( $existing ) {
                $db->delete_recipe_ingredients( $existing->id );
            }
            
            foreach ( $data['ingredients'] as $index => $ingredient ) {
                $db->save_ingredient( array(
                    'recipe_id'           => $recipe_id,
                    'name'                => sanitize_text_field( $ingredient['name'] ),
                    'amount'              => isset( $ingredient['amount'] ) ? sanitize_text_field( $ingredient['amount'] ) : '',
                    'unit'                => isset( $ingredient['unit'] ) ? sanitize_text_field( $ingredient['unit'] ) : '',
                    'kroger_product_id'   => isset( $ingredient['kroger_product_id'] ) ? sanitize_text_field( $ingredient['kroger_product_id'] ) : null,
                    'kroger_upc'          => isset( $ingredient['kroger_upc'] ) ? sanitize_text_field( $ingredient['kroger_upc'] ) : null,
                    'kroger_description'  => isset( $ingredient['kroger_description'] ) ? sanitize_text_field( $ingredient['kroger_description'] ) : null,
                    'kroger_image_url'    => isset( $ingredient['kroger_image_url'] ) ? esc_url_raw( $ingredient['kroger_image_url'] ) : null,
                    'kroger_price'        => isset( $ingredient['kroger_price'] ) ? floatval( $ingredient['kroger_price'] ) : null,
                    'sort_order'          => $index,
                ) );
            }
        }
        
        // Set featured image if provided
        if ( isset( $data['featured_image'] ) && $data['featured_image'] ) {
            set_post_thumbnail( $post_id, intval( $data['featured_image'] ) );
        }
        
        return $post_id;
    }
    
    // Delete recipe
    public static function delete_recipe( $post_id ) {
        $db = new RallyShopper_Database();
        $existing = $db->get_recipe( $post_id );
        
        if ( $existing ) {
            $db->delete_recipe_ingredients( $existing->id );
        }
        
        wp_delete_post( $post_id, true );
        
        return true;
    }
    
    // Get all recipes
    public static function get_recipes( $args = array() ) {
        $defaults = array(
            'post_type'      => 'rallyshopper_recipe',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        
        $args = wp_parse_args( $args, $defaults );
        $query = new WP_Query( $args );
        
        $recipes = array();
        foreach ( $query->posts as $post ) {
            $recipes[] = self::get_recipe( $post->ID );
        }
        
        return $recipes;
    }
    
    // Search recipes
    public static function search_recipes( $query ) {
        return self::get_recipes( array(
            's' => sanitize_text_field( $query ),
        ) );
    }
    
    // Get recipes by ingredient
    public static function get_recipes_by_ingredient( $ingredient_name ) {
        global $wpdb;
        
        $ingredients_table = $wpdb->prefix . 'rallyshopper_ingredients';
        $recipes_table = $wpdb->prefix . 'rallyshopper_recipes';
        
        $results = $wpdb->get_results( $wpdb->prepare( 
            "SELECT r.post_id FROM {$recipes_table} r 
             JOIN {$ingredients_table} i ON r.id = i.recipe_id 
             WHERE i.name LIKE %s",
            '%' . $wpdb->esc_like( $ingredient_name ) . '%'
        ) );
        
        $recipes = array();
        foreach ( $results as $result ) {
            $recipe = self::get_recipe( $result->post_id );
            if ( $recipe ) {
                $recipes[] = $recipe;
            }
        }
        
        return $recipes;
    }
    
    // Format time (minutes to readable)
    public static function format_time( $minutes ) {
        if ( $minutes < 60 ) {
            return $minutes . ' min';
        }
        
        $hours = floor( $minutes / 60 );
        $mins = $minutes % 60;
        
        if ( $mins === 0 ) {
            return $hours . ' hr';
        }
        
        return $hours . ' hr ' . $mins . ' min';
    }
    
    // Get difficulty label
    public static function get_difficulty_label( $difficulty ) {
        $labels = array(
            'easy'   => 'Easy',
            'medium' => 'Medium',
            'hard'   => 'Hard',
        );
        
        return isset( $labels[ $difficulty ] ) ? $labels[ $difficulty ] : 'Medium';
    }
}
