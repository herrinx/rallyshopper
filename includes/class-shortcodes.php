<?php
/**
 * Unified Recipe Manager Shortcode
 * All-in-one: list, edit, create recipes on one page
 */

class RallyShopper_Shortcodes {
    
    public function __construct() {
        add_shortcode( 'rallyshopper_recipes', array( $this, 'recipes_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        // Unique handlers (not in class-ajax.php)
        add_action( 'wp_ajax_rallyshopper_get_recipe', array( $this, 'ajax_get_recipe' ) );
        add_action( 'wp_ajax_rallyshopper_check_staple', array( $this, 'ajax_check_staple' ) );
        add_action( 'wp_ajax_rallyshopper_upload_image', array( $this, 'ajax_upload_image' ) );
    }
    
    public function enqueue_assets() {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) return;
        if ( ! has_shortcode( $post->post_content, 'rallyshopper_recipes' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        wp_enqueue_style( 'rallyshopper-frontend', RALLYSHOPPER_PLUGIN_URL . 'assets/css/frontend.css', array(), RALLYSHOPPER_VERSION );
        wp_enqueue_script( 'rallyshopper-frontend', RALLYSHOPPER_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), RALLYSHOPPER_VERSION, true );
        wp_localize_script( 'rallyshopper-frontend', 'rallyshopper_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rallyshopper_nonce' ),
        ) );
    }
    
    public function recipes_shortcode() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return '<div class="rallyshopper-error">Admin access required.</div>';
        }
        
        $recipes = RallyShopper_Recipe::get_recipes();
        $kroger = new RallyShopper_Kroger_API();
        $connected = $kroger->is_authenticated();
        
        ob_start();
        ?>
        <div class="rallyshopper-app">
            <!-- Header -->
            <div class="app-header">
                <h1>🍳 RallyShopper</h1>
                <div class="header-actions">
                    <button class="button button-primary" id="rs-btn-new">+ New Recipe</button>
                    <button class="button" id="rs-btn-refresh-cart">🛒 Refresh Cart</button>
                </div>
            </div>
            
            <!-- Cart Display -->
            <div class="cart-bar" id="rs-cart-display">
                <span class="cart-label">Cart:</span>
                <span class="cart-contents">Loading...</span>
            </div>
            
            <!-- Recipe List View -->
            <div id="rs-view-list" class="view active">
                <div class="rs-search-bar">
                    <input type="text" id="rs-live-search" placeholder="Search recipes, ingredients..." autocomplete="off">
                    <span class="rs-search-icon">🔍</span>
                </div>
                <div class="rs-filters">
                    <select id="rs-category-filter">
                        <option value="">All Categories</option>
                    </select>
                    <button class="button" id="rs-btn-manage-categories">Manage Categories</button>
                    <button class="button" id="rs-btn-meal-plan">📅 Meal Plan</button>
                </div>
                <div class="recipe-grid" id="rs-recipe-grid">
                    <?php foreach ( $recipes as $recipe ) : ?>
                        <div class="recipe-card" data-id="<?php echo esc_attr( $recipe['post']->ID ); ?>">
                            <?php if ( has_post_thumbnail( $recipe['post']->ID ) ) : ?>
                                <?php echo get_the_post_thumbnail( $recipe['post']->ID, 'medium', array( 'class' => 'recipe-thumb' ) ); ?>
                            <?php else : ?>
                                <div class="recipe-thumb placeholder"></div>
                            <?php endif; ?>
                            <h3><?php echo esc_html( $recipe['post']->post_title ); ?></h3>
                            <div class="recipe-meta">
                                <?php echo intval( count( $recipe['ingredients'] ) ); ?> ingredients · 
                                <?php echo esc_html( RallyShopper_Recipe::format_time( $recipe['meta']['prep_time'] + $recipe['meta']['cook_time'] ) ); ?>
                            </div>
                            <div class="recipe-categories" id="recipe-cats-<?php echo esc_attr( $recipe['post']->ID ); ?>"></div>
                            <div class="recipe-actions">
                                <button class="button rs-btn-edit" data-id="<?php echo esc_attr( $recipe['post']->ID ); ?>">Edit</button>
                                <button class="button" onclick="addToMealPlan(<?php echo esc_attr( $recipe['post']->ID ); ?>, '<?php echo esc_js( $recipe['post']->post_title ); ?>')">📅</button>
                                <?php if ( $connected && ! empty( $recipe['ingredients'] ) ) : ?>
                                    <button class="button button-primary rs-btn-cart" data-id="<?php echo esc_attr( $recipe['post']->ID ); ?>">Add to Cart</button>
                                <?php endif; ?>
                                <button class="button rs-btn-delete" data-id="<?php echo esc_attr( $recipe['post']->ID ); ?>">🗑️</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recipe Editor View -->
            <div id="rs-view-editor" class="view">
                <div class="editor-header">
                    <button class="button" id="rs-btn-back">&larr; Back to Recipes</button>
                    <h2 id="rs-editor-title">Edit Recipe</h2>
                    <button class="button button-primary" id="rs-btn-save">Save Recipe</button>
                </div>
                
                <form id="rs-recipe-form">
                    <input type="hidden" id="rs-recipe-id" value="">
                    
                    <div class="form-row">
                        <div class="form-field title-field">
                            <label>Recipe Title</label>
                            <input type="text" id="rs-title" required>
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label>Featured Image</label>
                        <div class="image-upload-area" id="rs-image-area">
                            <div class="image-preview" id="rs-image-preview"></div>
                            <input type="hidden" id="rs-featured-image" value="">
                            <button type="button" class="button" id="rs-btn-select-image">Select Image</button>
                            <button type="button" class="button" id="rs-btn-remove-image" style="display:none;">Remove Image</button>
                        </div>
                    </div>
                    
                    <div class="form-row four-col">
                        <div class="form-field">
                            <label>Servings</label>
                            <input type="number" id="rs-servings" value="4" min="1">
                        </div>
                        <div class="form-field">
                            <label>Prep (min)</label>
                            <input type="number" id="rs-prep-time" value="0" min="0">
                        </div>
                        <div class="form-field">
                            <label>Cook (min)</label>
                            <input type="number" id="rs-cook-time" value="0" min="0">
                        </div>
                        <div class="form-field">
                            <label>Difficulty</label>
                            <select id="rs-difficulty">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-field">
                        <label>Categories</label>
                        <div class="rs-category-selector" id="rs-categories">
                            <!-- Categories loaded dynamically -->
                        </div>
                    </div>
                    
                    <div class="form-field">
                        <label>Description</label>
                        <textarea id="rs-description" rows="2"></textarea>
                    </div>
                    
                    <div class="form-field">
                        <label>Instructions</label>
                        <textarea id="rs-instructions" rows="6"></textarea>
                    </div>
                    
                    <div class="ingredients-section">
                        <div class="section-header">
                            <label>Ingredients</label>
                            <button type="button" class="button" id="rs-btn-add-ing">+ Add</button>
                        </div>
                        <div id="rs-ingredients-list"></div>
                    </div>
                </form>
            </div>
            
            <!-- Kroger Search Modal -->
            <div id="rs-modal-search" class="rs-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Link to Kroger Product</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="search-layout">
                            <div class="filter-sidebar">
                                <h4>Filters</h4>
                                <button type="button" class="button button-small" id="rs-clear-filters">Clear All</button>
                                
                                <div class="filter-group">
                                    <h5>Fulfillment</h5>
                                    <label><input type="checkbox" class="rs-filter" data-filter="fulfillment" value="delivery" checked> Delivery</label>
                                    <label><input type="checkbox" class="rs-filter" data-filter="fulfillment" value="curbside"> Curbside</label>
                                </div>
                                
                                <div class="filter-group">
                                    <h5>Price Range</h5>
                                    <label><input type="checkbox" class="rs-filter" data-filter="price" value="under5"> Under $5</label>
                                    <label><input type="checkbox" class="rs-filter" data-filter="price" value="5to10"> $5 - $10</label>
                                    <label><input type="checkbox" class="rs-filter" data-filter="price" value="10to20"> $10 - $20</label>
                                    <label><input type="checkbox" class="rs-filter" data-filter="price" value="over20"> $20+</label>
                                </div>
                                
                                <div class="filter-group" id="rs-size-filters" style="display:none;">
                                    <h5>Size</h5>
                                    <div id="rs-size-list"></div>
                                </div>
                                
                                <div class="filter-group" id="rs-brand-filters" style="display:none;">
                                    <h5>Brands</h5>
                                    <div id="rs-brand-list"></div>
                                </div>
                            </div>
                            
                            <div class="search-main">
                                <div class="search-box">
                                    <input type="text" id="rs-search-input" placeholder="Search products...">
                                    <button class="button button-primary" id="rs-btn-search">Search</button>
                                </div>
                                <div id="rs-search-results"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Meal Plan View -->
            <div id="rs-view-meal-plan" class="view">
                <div class="editor-header">
                    <button class="button" id="rs-btn-back-from-plan">&larr; Back to Recipes</button>
                    <h2>📅 Meal Plan</h2>
                    <select id="rs-meal-plan-selector">
                        <option value="">Select Plan...</option>
                    </select>
                    <button class="button" id="rs-btn-new-plan">+ New Plan</button>
                </div>
                <div class="meal-plan-container" id="rs-meal-plan-container">
                    <div class="meal-plan-days">
                        <div class="meal-day" data-day="monday">
                            <h4>Monday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="tuesday">
                            <h4>Tuesday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="wednesday">
                            <h4>Wednesday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="thursday">
                            <h4>Thursday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="friday">
                            <h4>Friday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="saturday">
                            <h4>Saturday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                        <div class="meal-day" data-day="sunday">
                            <h4>Sunday</h4>
                            <div class="meal-slots">
                                <div class="meal-slot" data-meal="breakfast"><span class="meal-label">Breakfast</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="lunch"><span class="meal-label">Lunch</span><div class="meal-recipes"></div></div>
                                <div class="meal-slot" data-meal="dinner"><span class="meal-label">Dinner</span><div class="meal-recipes"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Management Modal -->
            <div id="rs-modal-categories" class="rs-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>🏷️ Manage Categories</h3>
                        <button class="button rs-modal-close" data-modal="rs-modal-categories">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="category-form">
                            <input type="hidden" id="rs-category-id" value="">
                            <input type="text" id="rs-category-name" placeholder="Category Name">
                            <input type="color" id="rs-category-color" value="#0073aa">
                            <button class="button button-primary" id="rs-btn-save-category">Save</button>
                        </div>
                        <div class="category-list" id="rs-category-list"></div>
                    </div>
                </div>
            </div>

            <!-- Add to Meal Plan Modal -->
            <div id="rs-modal-add-to-plan" class="rs-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>📅 Add to Meal Plan</h3>
                        <button class="button rs-modal-close" data-modal="rs-modal-add-to-plan">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="rs-add-plan-recipe-id" value="">
                        <div class="form-field">
                            <label>Select Plan</label>
                            <select id="rs-add-plan-selector"></select>
                        </div>
                        <div class="form-field">
                            <label>Day</label>
                            <select id="rs-add-plan-day">
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Meal</label>
                            <select id="rs-add-plan-meal">
                                <option value="breakfast">Breakfast</option>
                                <option value="lunch">Lunch</option>
                                <option value="dinner">Dinner</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button class="button button-primary" id="rs-btn-confirm-add-to-plan">Add to Plan</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staple Confirmation Modal -->
            <div id="rs-modal-staple" class="rs-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>🥛 Staple Item</h3>
                    </div>
                    <div class="modal-body">
                        <p><strong id="rs-staple-name"></strong> is a staple (purchased <span id="rs-staple-count"></span> times).</p>
                        <p>Do you need this item?</p>
                        <div class="modal-actions">
                            <button class="button button-primary" id="rs-staple-yes">Yes, Add It</button>
                            <button class="button" id="rs-staple-no">Skip</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toast Notifications -->
            <div id="rs-toasts"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // AJAX: Get single recipe
    public function ajax_get_recipe() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
        $recipe = RallyShopper_Recipe::get_recipe( intval( $_POST['recipe_id'] ) );
        if ( ! $recipe ) wp_send_json_error( 'Recipe not found' );
        
        wp_send_json_success( $recipe );
    }
    
    
    // AJAX: Check if ingredient is a staple
    public function ajax_check_staple() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
        $recipe_id = intval( $_POST['recipe_id'] ?? 0 );
        $product_id = sanitize_text_field( $_POST['product_id'] ?? '' );
        
        if ( ! $recipe_id || ! $product_id ) {
            wp_send_json_error( 'Missing recipe_id or product_id' );
        }
        
        $db = new RallyShopper_Database();
        
        // Get recipe (works with both id and post_id)
        $recipe_data = $db->get_recipe( $recipe_id );
        if ( ! $recipe_data ) {
            wp_send_json_error( 'Recipe not found' );
        }
        
        // Look up the ingredient for this recipe with this product
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        $ingredient = $wpdb->get_row( $wpdb->prepare(
            "SELECT is_staple FROM {$table} WHERE recipe_id = %d AND kroger_product_id = %s",
            $recipe_data->id,
            $product_id
        ) );
        
        if ( ! $ingredient ) {
            wp_send_json_error( 'Ingredient not found' );
        }
        
        wp_send_json_success( array(
            'is_staple' => intval( $ingredient->is_staple ) === 1,
            'count'     => 0,
        ) );
    }
    
    // AJAX: Upload image
    public function ajax_upload_image() {
        check_ajax_referer( 'rallyshopper_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );
        
        if ( ! isset( $_FILES['image'] ) ) {
            wp_send_json_error( 'No image uploaded' );
        }
        
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        
        $attachment_id = media_handle_upload( 'image', 0 );
        
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
        }
        
        wp_send_json_success( array(
            'attachment_id' => $attachment_id,
            'url'           => wp_get_attachment_url( $attachment_id ),
            'thumbnail'     => wp_get_attachment_image_src( $attachment_id, 'medium' )[0],
        ) );
    }
}
