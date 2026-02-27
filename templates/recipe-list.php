<?php
/**
 * Recipe List Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <div class="rallyshopper-header">
        <h1>Recipes</h1>
        <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-add' ); ?>" class="button button-primary">Add New Recipe</a>
    </div>
    
    <?php if ( empty( $recipes ) ) : ?>
        <div class="notice notice-info">
            <p>No recipes yet. <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-add' ); ?>">Create your first recipe</a>.</p>
        </div>
    <?php else : ?>
        <div class="rallyshopper-recipe-grid">
            <?php foreach ( $recipes as $recipe ) : 
                $post = $recipe['post'];
                $meta = $recipe['meta'];
                $ingredients = $recipe['ingredients'];
                $linked_count = 0;
                foreach ( $ingredients as $ing ) {
                    if ( $ing->kroger_product_id ) {
                        $linked_count++;
                    }
                }
            ?>
                <div class="rallyshopper-recipe-card">
                    <div class="card-image">
                        <?php if ( has_post_thumbnail( $post->ID ) ) : ?>
                            <?php echo get_the_post_thumbnail( $post->ID, 'medium' ); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-food" style="font-size: 48px; color: #c3c4c7;"></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-content">
                        <h3><?php echo esc_html( $post->post_title ); ?></h3>
                        <div class="meta">
                            <?php echo RallyShopper_Recipe::format_time( $meta['prep_time'] + $meta['cook_time'] ); ?> | 
                            <?php echo intval( $meta['servings'] ); ?> servings | 
                            <?php echo RallyShopper_Recipe::get_difficulty_label( $meta['difficulty'] ); ?>
                        </div>
                        <div class="meta">
                            <?php echo count( $ingredients ); ?> ingredients, 
                            <?php echo $linked_count; ?> linked
                        </div>
                        <div class="actions">
                            <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-add&edit=' . $post->ID ); ?>" class="button">Edit</a>
                            <a href="<?php echo get_permalink( $post->ID ); ?>" target="_blank" class="button">View</a>
                            <button class="button rallyshopper-delete-recipe" data-id="<?php echo $post->ID; ?>">Delete</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
