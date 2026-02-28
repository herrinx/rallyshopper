<?php
/**
 * Recipe Edit Template
 */
$post_id = $recipe ? $recipe['post']->ID : '';
$post = $recipe ? $recipe['post'] : null;
$meta = $recipe ? $recipe['meta'] : array(
    'servings'   => get_option( 'rallyshopper_default_servings', 4 ),
    'prep_time'  => 0,
    'cook_time'  => 0,
    'difficulty' => 'medium',
);
$ingredients = $recipe ? $recipe['ingredients'] : array();
$db = new RallyShopper_Database();
?>
<div class="wrap rallyshopper-wrap">
    <div class="rallyshopper-header">
        <h1><?php echo $edit_mode ? 'Edit Recipe' : 'Add New Recipe'; ?></h1>
        <a href="<?php echo admin_url( 'admin.php?page=rallyshopper_recipes' ); ?>" class="button">← Back to Recipes</a>
    </div>
    
    <?php if ( ! $kroger_connected ) : ?>
        <div class="notice notice-warning">
            <p>Kroger not connected. <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-auth' ); ?>">Connect to Kroger</a> to add ingredients.</p>
        </div>
    <?php endif; ?>
    
    <div class="rallyshopper-form">
        <input type="hidden" id="recipe-post-id" value="<?php echo esc_attr( $post_id ); ?>">
        
        <div class="form-field">
            <label for="recipe-title">Recipe Title *</label>
            <input type="text" id="recipe-title" value="<?php echo $post ? esc_attr( $post->post_title ) : ''; ?>" required>
        </div>
        
        <div class="form-field">
            <label for="recipe-description">Description</label>
            <textarea id="recipe-description" rows="3"><?php echo $post ? esc_textarea( $post->post_excerpt ) : ''; ?></textarea>
        </div>
        
        <div class="form-field">
            <label>Details</label>
            <div style="display: flex; gap: 20px;">
                <span>
                    Servings: <input type="number" id="recipe-servings" value="<?php echo esc_attr( $meta['servings'] ); ?>" min="1" style="width: 70px;">
                </span>
                <span>
                    Prep (min): <input type="number" id="recipe-prep-time" value="<?php echo esc_attr( $meta['prep_time'] ); ?>" min="0" style="width: 70px;">
                </span>
                <span>
                    Cook (min): <input type="number" id="recipe-cook-time" value="<?php echo esc_attr( $meta['cook_time'] ); ?>" min="0" style="width: 70px;">
                </span>
                <span>
                    Difficulty: 
                    <select id="recipe-difficulty">
                        <option value="easy" <?php selected( $meta['difficulty'], 'easy' ); ?>>Easy</option>
                        <option value="medium" <?php selected( $meta['difficulty'], 'medium' ); ?>>Medium</option>
                        <option value="hard" <?php selected( $meta['difficulty'], 'hard' ); ?>>Hard</option>
                    </select>
                </span>
            </div>
        </div>
        
        <div class="form-field">
            <label for="recipe-instructions">Instructions</label>
            <textarea id="recipe-instructions" rows="10"><?php echo $post ? esc_textarea( $post->post_content ) : ''; ?></textarea>
            <p class="description">Enter the cooking instructions. You can use formatting.</p>
        </div>
        
        <div class="rallyshopper-ingredients-section">
            <h2>Ingredients</h2>
            <p class="description">Click "Add Ingredient" to search Kroger products.</p>
            
            <div id="ingredients-container" class="ingredients-list">
                <?php if ( empty( $ingredients ) ) : ?>
                    <p class="no-ingredients">No ingredients added yet. Click "Add Ingredient" to search Kroger products.</p>
                <?php else : ?>
                    <?php foreach ( $ingredients as $ingredient ) : 
                        $staple_count = $ingredient->kroger_product_id ? $db->is_staple( $ingredient->kroger_product_id ) : 0;
                    ?>
                        <div class="ingredient-row" 
                             data-ingredient-id="<?php echo $ingredient->id; ?>"
                             data-kroger-product-id="<?php echo esc_attr( $ingredient->kroger_product_id ); ?>"
                             data-kroger-upc="<?php echo esc_attr( $ingredient->kroger_upc ); ?>"
                             data-kroger-description="<?php echo htmlspecialchars( $ingredient->kroger_description, ENT_QUOTES, 'UTF-8' ); ?>"
                             data-kroger-image="<?php echo esc_attr( $ingredient->kroger_image_url ); ?>"
                             data-kroger-price="<?php echo esc_attr( $ingredient->kroger_price ); ?>">
                            
                            <div class="ingredient-product-display">
                                <?php if ( $ingredient->kroger_image_url ) : ?>
                                    <img src="<?php echo esc_url( $ingredient->kroger_image_url ); ?>" alt="" class="product-thumb">
                                <?php endif; ?>
                                <div class="product-details">
                                    <div class="product-name">
                                        <?php if ( $staple_count > 0 ) : ?>
                                            <span class="staple-badge" title="Purchased <?php echo $staple_count; ?> times">★</span>
                                        <?php endif; ?>
                                        <?php echo esc_html( $ingredient->kroger_description ?: $ingredient->name ); ?>
                                    </div>
                                    <?php if ( $ingredient->kroger_price ) : ?>
                                        <div class="product-price">$<?php echo number_format( $ingredient->kroger_price, 2 ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="ingredient-inputs">
                                <input type="text" class="ingredient-amount" value="<?php echo esc_attr( $ingredient->amount ); ?>" placeholder="Amount">
                                <label class="staple-label" style="margin-left: 10px;">
                                    <input type="checkbox" class="ingredient-is-staple" <?php checked( $ingredient->is_staple, 1 ); ?>> Staple
                                </label>
                                <input type="hidden" class="ingredient-name" value="<?php echo esc_attr( $ingredient->name ); ?>">
                            </div>
                            
                            <button type="button" class="button rallyshopper-change-product">Change Product</button>
                            <span class="remove-ingredient dashicons dashicons-trash"></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="button" class="button button-primary" id="rallyshopper-add-ingredient" <?php echo ! $kroger_connected ? 'disabled' : ''; ?>>
                + Add Ingredient
            </button>
            <?php if ( ! $kroger_connected ) : ?>
                <p class="description">Connect to Kroger to add ingredients.</p>
            <?php endif; ?>
        </div>
        
        <div class="form-field" style="margin-top: 30px;">
            <button type="button" class="button button-primary button-hero" id="rallyshopper-save-recipe">Save Recipe</button>
            <a href="<?php echo admin_url( 'admin.php?page=rallyshopper_recipes' ); ?>" class="button">Cancel</a>
        </div>
    </div>
</div>

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
