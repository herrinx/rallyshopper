<?php
/**
 * Admin Interface
 */

class RallyShopper_Admin {
    
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post', array( $this, 'save_recipe_meta' ), 10, 2 );
        add_action( 'admin_footer', array( $this, 'render_modal' ) );
    }
    
    // Render search modal in footer for post editor
    public function render_modal() {
        $screen = get_current_screen();
        if ( !$screen || ($screen->id !== 'rallyshopper_recipe' && $screen->id !== 'edit-rallyshopper_recipe') ) {
            return;
        }
        ?>
        <!-- Kroger Product Search Modal -->
        <div id="kroger-search-modal" class="kroger-search-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Search Kroger Products</h2>
                    <span class="modal-close dashicons dashicons-no-alt"></span>
                </div>
                <div class="modal-body">
                    <div class="search-box">
                        <input type="text" id="kroger-search-input" placeholder="Search for ingredients (e.g., 'milk', 'eggs', 'flour')...">
                        <button type="button" class="button button-primary" id="kroger-search-btn">Search</button>
                    </div>
                    <div id="kroger-search-results" class="search-results">
                        <p>Search for a product to add as an ingredient.</p>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #c3c4c7;">
                    <button type="button" class="button modal-cancel">Cancel</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function add_menu_pages() {
        // Main menu (Recipes list)
        add_menu_page(
            'RallyShopper',
            'RallyShopper',
            'manage_options',
            'rallyshopper_recipes',
            array( $this, 'render_recipes_list' ),
            'dashicons-food',
            25
        );
        
        // Submenu: Add New
        add_submenu_page(
            'rallyshopper_recipes',
            'Add New Recipe',
            'Add New',
            'manage_options',
            'rallyshopper-add',
            array( $this, 'render_add_recipe' )
        );
        
        // Submenu: Kroger Auth
        add_submenu_page(
            'rallyshopper_recipes',
            'Kroger Connection',
            'Kroger Auth',
            'manage_options',
            'rallyshopper-auth',
            array( $this, 'render_kroger_auth' )
        );
        
        // Submenu: Purchase History
        add_submenu_page(
            'rallyshopper_recipes',
            'Purchase History',
            'Purchases',
            'manage_options',
            'rallyshopper-purchases',
            array( $this, 'render_purchases' )
        );
        
        // Submenu: Staples
        add_submenu_page(
            'rallyshopper_recipes',
            'Staple Items',
            'Staples',
            'manage_options',
            'rallyshopper-staples',
            array( $this, 'render_staples' )
        );
        
        // Submenu: Settings
        add_submenu_page(
            'rallyshopper_recipes',
            'Settings',
            'Settings',
            'manage_options',
            'rallyshopper-settings',
            array( $this, 'render_settings' )
        );
        
        // Submenu: Logs
        add_submenu_page(
            'rallyshopper_recipes',
            'Logs',
            'Logs',
            'manage_options',
            'rallyshopper-logs',
            array( $this, 'render_logs' )
        );
    }
    
    // Render recipes list
    public function render_recipes_list() {
        $recipes = RallyShopper_Recipe::get_recipes();
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/recipe-list.php';
    }
    
    // Render add/edit recipe
    public function render_add_recipe() {
        $recipe = null;
        $edit_mode = false;
        
        if ( isset( $_GET['edit'] ) ) {
            $recipe = RallyShopper_Recipe::get_recipe( intval( $_GET['edit'] ) );
            $edit_mode = true;
        }
        
        // Check Kroger auth status
        $kroger = new RallyShopper_Kroger_API();
        $kroger_connected = $kroger->is_authenticated();
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/recipe-edit.php';
    }
    
    // Render Kroger auth page
    public function render_kroger_auth() {
        $kroger = new RallyShopper_Kroger_API();
        $configured = $kroger->is_configured();
        $authenticated = $kroger->is_authenticated();
        
        // Handle disconnect
        if ( isset( $_GET['disconnect'] ) && check_admin_referer( 'rallyshopper_disconnect' ) ) {
            RallyShopper_Auth::disconnect();
            $authenticated = false;
            echo '<div class="notice notice-success"><p>Disconnected from Kroger.</p></div>';
        }
        
        // Handle OAuth callback
        if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
            if ( wp_verify_nonce( $_GET['state'], 'rallyshopper_kroger_auth' ) ) {
                $redirect_uri = admin_url( 'admin.php?page=rallyshopper-auth' );
                $result = $kroger->exchange_code( sanitize_text_field( $_GET['code'] ), $redirect_uri );
                
                if ( ! is_wp_error( $result ) ) {
                    echo '<div class="notice notice-success"><p>Successfully connected to Kroger!</p></div>';
                    $authenticated = true;
                } else {
                    echo '<div class="notice notice-error"><p>Error: ' . esc_html( $result->get_error_message() ) . '</p></div>';
                }
            }
        }
        
        // Get auth URL if configured
        $auth_url = '';
        if ( $configured && ! $authenticated ) {
            $redirect_uri = admin_url( 'admin.php?page=rallyshopper-auth' );
            $auth_url = $kroger->get_auth_url( $redirect_uri );
        }
        
        // Get profile if authenticated
        $profile = null;
        if ( $authenticated ) {
            $profile = $kroger->get_profile();
            if ( is_wp_error( $profile ) ) {
                $profile = null;
            }
        }
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/kroger-auth.php';
    }
    
    // Render purchases page
    public function render_purchases() {
        $db = new RallyShopper_Database();
        $purchases = $db->get_purchases( null, 100 );
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/purchases.php';
    }
    
    // Render staples page
    public function render_staples() {
        $db = new RallyShopper_Database();
        $staples = $db->get_staples( 2 ); // Show items with 2+ purchases
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/staples.php';
    }
    
    // Render settings page
    public function render_settings() {
        // Save settings
        if ( isset( $_POST['rallyshopper_save_settings'] ) && check_admin_referer( 'rallyshopper_settings' ) ) {
            update_option( 'rallyshopper_kroger_client_id', sanitize_text_field( $_POST['kroger_client_id'] ) );
            update_option( 'rallyshopper_kroger_client_secret', sanitize_text_field( $_POST['kroger_client_secret'] ) );
            update_option( 'rallyshopper_default_servings', intval( $_POST['default_servings'] ) );
            update_option( 'rallyshopper_kroger_location_id', sanitize_text_field( $_POST['kroger_location_id'] ) );
            update_option( 'rallyshopper_kroger_zip', sanitize_text_field( $_POST['kroger_zip_code'] ) );
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $client_id = get_option( 'rallyshopper_kroger_client_id' );
        $client_secret = get_option( 'rallyshopper_kroger_client_secret' );
        $default_servings = get_option( 'rallyshopper_default_servings', 4 );
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/settings.php';
    }
    
    // Add meta boxes to recipe post type
    public function add_meta_boxes() {
        add_meta_box(
            'rallyshopper_meta',
            'Recipe Details',
            array( $this, 'render_recipe_meta_box' ),
            'rallyshopper_recipe',
            'normal',
            'high'
        );
        
        add_meta_box(
            'rallyshopper_ingredients',
            'Ingredients',
            array( $this, 'render_ingredients_meta_box' ),
            'rallyshopper_recipe',
            'normal',
            'high'
        );
        
        add_meta_box(
            'rallyshopper_actions',
            'Actions',
            array( $this, 'render_actions_meta_box' ),
            'rallyshopper_recipe',
            'side',
            'high'
        );
    }
    
    // Render recipe details meta box
    public function render_recipe_meta_box( $post ) {
        wp_nonce_field( 'rallyshopper_save_meta', 'rallyshopper_meta_nonce' );
        
        $db = new RallyShopper_Database();
        $recipe_data = $db->get_recipe( $post->ID );
        
        $servings = $recipe_data ? $recipe_data->servings : 4;
        $prep_time = $recipe_data ? $recipe_data->prep_time : 0;
        $cook_time = $recipe_data ? $recipe_data->cook_time : 0;
        $difficulty = $recipe_data ? $recipe_data->difficulty : 'medium';
        ?>
        <p>
            <label>Servings:</label>
            <input type="number" name="rallyshopper_servings" value="<?php echo esc_attr( $servings ); ?>" min="1" style="width: 80px;">
        </p>
        <p>
            <label>Prep Time (minutes):</label>
            <input type="number" name="rallyshopper_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" min="0" style="width: 100px;">
        </p>
        <p>
            <label>Cook Time (minutes):</label>
            <input type="number" name="rallyshopper_cook_time" value="<?php echo esc_attr( $cook_time ); ?>" min="0" style="width: 100px;">
        </p>
        <p>
            <label>Difficulty:</label>
            <select name="rallyshopper_difficulty">
                <option value="easy" <?php selected( $difficulty, 'easy' ); ?>>Easy</option>
                <option value="medium" <?php selected( $difficulty, 'medium' ); ?>>Medium</option>
                <option value="hard" <?php selected( $difficulty, 'hard' ); ?>>Hard</option>
            </select>
        </p>
        <?php
    }
    
    // Render ingredients meta box
    public function render_ingredients_meta_box( $post ) {
        $db = new RallyShopper_Database();
        $recipe_data = $db->get_recipe( $post->ID );
        $ingredients = $recipe_data ? $db->get_ingredients( $recipe_data->id ) : array();
        
        $kroger = new RallyShopper_Kroger_API();
        $connected = $kroger->is_authenticated();
        ?>
        <div id="rallyshopper-ingredients-list">
            <?php if ( empty( $ingredients ) ) : ?>
                <p class="no-ingredients">No ingredients added yet.</p>
            <?php else : ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Amount</th>
                            <th>Kroger Product</th>
                            <th>Price</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $ingredients as $index => $ingredient ) : 
                            $staple_count = $ingredient->kroger_product_id ? $db->is_staple( $ingredient->kroger_product_id ) : 0;
                        ?>
                            <tr class="ingredient-row"
                                data-kroger-product-id="<?php echo esc_attr( $ingredient->kroger_product_id ); ?>"
                                data-kroger-upc="<?php echo esc_attr( $ingredient->kroger_upc ); ?>"
                                data-kroger-description="<?php echo htmlspecialchars( $ingredient->kroger_description, ENT_QUOTES, 'UTF-8' ); ?>"
                                data-kroger-image="<?php echo esc_attr( $ingredient->kroger_image_url ); ?>"
                                data-kroger-price="<?php echo esc_attr( $ingredient->kroger_price ); ?>">
                                <td>
                                    <span class="ingredient-name-display"><?php echo esc_html( $ingredient->name ); ?></span>
                                    <input type="hidden" class="ingredient-name" value="<?php echo esc_attr( $ingredient->name ); ?>">
                                </td>
                                <td><input type="text" class="ingredient-amount" value="<?php echo esc_attr( $ingredient->amount ); ?>" placeholder="e.g. 2 cups" style="width:100px"></td>
                                <td>
                                    <?php if ( $ingredient->kroger_product_id ) : ?>
                                        <div class="kroger-product-display">
                                            <img src="<?php echo esc_url( $ingredient->kroger_image_url ); ?>" alt="">
                                            <div class="product-info">
                                                <?php if ( $staple_count > 0 ) : ?>
                                                    <span class="staple-badge" title="Purchased <?php echo $staple_count; ?> times">★</span>
                                                <?php endif; ?>
                                                <span class="product-name"><?php echo esc_html( $ingredient->kroger_description ?: $ingredient->name ); ?></span>
                                            </div>
                                        </div>
                                    <?php else : ?>
                                        <em>Not linked</em>
                                    <?php endif; ?>
                                </td>
                                <td class="price-cell"><?php echo $ingredient->kroger_price ? '$' . number_format( $ingredient->kroger_price, 2 ) : '-'; ?></td>
                                <td class="actions-cell">
                                    <button type="button" class="button rallyshopper-link-ingredient" data-ingredient="<?php echo $ingredient->id; ?>" <?php echo ! $connected ? 'disabled' : ''; ?>>
                                        <?php echo $ingredient->kroger_product_id ? 'Change' : 'Link'; ?>
                                    </button>
                                    <span class="remove-ingredient dashicons dashicons-trash" title="Remove ingredient"></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <?php if ( $connected ) : ?>
            <p>
                <button type="button" class="button button-primary rallyshopper-add-ingredient" onclick="jQuery('#kroger-search-modal').addClass('active'); jQuery('#kroger-search-input').val('').focus();">Add Ingredient</button>
            </p>
        <?php else : ?>
            <p class="description">Connect to Kroger to add and link ingredients.</p>
        <?php endif; ?>
        <?php
    }
    
    // Render actions meta box
    public function render_actions_meta_box( $post ) {
        $db = new RallyShopper_Database();
        $recipe_data = $db->get_recipe( $post->ID );
        $ingredients = $recipe_data ? $db->get_ingredients( $recipe_data->id ) : array();
        
        $linked_count = 0;
        foreach ( $ingredients as $ing ) {
            if ( $ing->kroger_product_id ) {
                $linked_count++;
            }
        }
        
        $kroger = new RallyShopper_Kroger_API();
        $connected = $kroger->is_authenticated();
        ?>
        <p>
            <strong>Ingredients:</strong> <?php echo count( $ingredients ); ?> total, <?php echo $linked_count; ?> linked to Kroger
        </p>
        
        <?php if ( $connected && $linked_count > 0 ) : ?>
            <p>
                <button type="button" class="button button-primary rallyshopper-add-to-cart" data-recipe="<?php echo $post->ID; ?>" style="width: 100%;">
                    Add to Kroger Cart
                </button>
            </p>
            <p class="description">Adds all linked ingredients to your Kroger cart.</p>
        <?php elseif ( ! $connected ) : ?>
            <p class="description">Connect to Kroger to add items to cart.</p>
        <?php else : ?>
            <p class="description">Link ingredients to Kroger products to add to cart.</p>
        <?php endif; ?>
        <?php
    }
    
    // Save recipe meta
    public function save_recipe_meta( $post_id, $post ) {
        if ( ! isset( $_POST['rallyshopper_meta_nonce'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( $_POST['rallyshopper_meta_nonce'], 'rallyshopper_save_meta' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( $post->post_type !== 'rallyshopper_recipe' ) {
            return;
        }
        
        // Save meta
        $db = new RallyShopper_Database();
        $data = array(
            'post_id'    => $post_id,
            'servings'   => isset( $_POST['rallyshopper_servings'] ) ? intval( $_POST['rallyshopper_servings'] ) : 4,
            'prep_time'  => isset( $_POST['rallyshopper_prep_time'] ) ? intval( $_POST['rallyshopper_prep_time'] ) : 0,
            'cook_time'  => isset( $_POST['rallyshopper_cook_time'] ) ? intval( $_POST['rallyshopper_cook_time'] ) : 0,
            'difficulty' => isset( $_POST['rallyshopper_difficulty'] ) ? sanitize_text_field( $_POST['rallyshopper_difficulty'] ) : 'medium',
        );
        
        $existing = $db->get_recipe( $post_id );
        if ( $existing ) {
            $data['id'] = $existing->id;
        }
        
        $recipe_id = $db->save_recipe( $data );
        
        // Log the recipe save result
        $db->add_log( 'debug', 'save_recipe_debug', 'Recipe save result: ID=' . $recipe_id . ', Data: ' . json_encode($data) . ', Existing: ' . ($existing ? 'yes' : 'no'), array(
            'post_id' => $post_id,
            'recipe_id' => $recipe_id,
            'data' => $data,
        ));
        
        // Save ingredients from post editor
        if ( isset( $_POST['rallyshopper_ingredients'] ) && is_array( $_POST['rallyshopper_ingredients'] ) ) {
            // Delete existing ingredients
            $db->delete_recipe_ingredients( $recipe_id );
            
            foreach ( $_POST['rallyshopper_ingredients'] as $index => $ing ) {
                $db->save_ingredient( array(
                    'recipe_id'           => $recipe_id,
                    'name'                => sanitize_text_field( $ing['name'] ),
                    'amount'              => sanitize_text_field( $ing['amount'] ),
                    'unit'                => isset( $ing['unit'] ) ? sanitize_text_field( $ing['unit'] ) : '',
                    'kroger_product_id'   => isset( $ing['kroger_product_id'] ) ? sanitize_text_field( $ing['kroger_product_id'] ) : null,
                    'kroger_upc'          => isset( $ing['kroger_upc'] ) ? sanitize_text_field( $ing['kroger_upc'] ) : null,
                    'kroger_description'  => isset( $ing['kroger_description'] ) ? sanitize_text_field( $ing['kroger_description'] ) : null,
                    'kroger_image_url'    => isset( $ing['kroger_image_url'] ) ? esc_url_raw( $ing['kroger_image_url'] ) : null,
                    'kroger_price'        => isset( $ing['kroger_price'] ) ? floatval( $ing['kroger_price'] ) : null,
                    'is_staple'           => isset( $ing['is_staple'] ) ? intval( $ing['is_staple'] ) : 0,
                    'sort_order'          => $index,
                ) );
            }
            
            // Log successful save
            RallyShopper::log( 'Saved ' . count( $_POST['rallyshopper_ingredients'] ) . ' ingredients for recipe ' . $recipe_id, 'debug' );
        } elseif ( isset( $_POST['rallyshopper_ingredients_empty'] ) ) {
            // CRITICAL FIX: All ingredients were deleted - clear them from DB
            $db->delete_recipe_ingredients( $recipe_id );
            RallyShopper::log( 'All ingredients deleted for recipe ' . $recipe_id, 'debug' );
        }
    }
    
    // Render logs page
    public function render_logs() {
        $db = new RallyShopper_Database();
        
        // Handle clear logs
        if ( isset( $_POST['clear_logs'] ) && check_admin_referer( 'rallyshopper_clear_logs' ) ) {
            $db->clear_logs();
            echo '<div class="notice notice-success"><p>Logs cleared.</p></div>';
        }
        
        // Handle create tables
        if ( isset( $_POST['create_tables'] ) && check_admin_referer( 'rallyshopper_create_tables' ) ) {
            $db->install();
            echo '<div class="notice notice-success"><p>Tables created/updated.</p></div>';
        }
        
        // Get filter params
        $level = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : null;
        $action = isset( $_GET['action_filter'] ) ? sanitize_text_field( $_GET['action_filter'] ) : null;
        $page = isset( $_GET['log_page'] ) ? intval( $_GET['log_page'] ) : 1;
        $per_page = 50;
        $offset = ( $page - 1 ) * $per_page;
        
        // Get logs
        $logs = $db->get_logs( $per_page, $offset, $level, $action );
        $total = $db->get_logs_count( $level, $action );
        $total_pages = ceil( $total / $per_page );
        
        // Get unique actions for filter
        global $wpdb;
        $logs_table = $wpdb->prefix . 'rallyshopper_logs';
        $actions = $wpdb->get_col( "SELECT DISTINCT action FROM {$logs_table} ORDER BY action ASC" );
        
        include RALLYSHOPPER_PLUGIN_DIR . 'templates/logs.php';
    }
}
