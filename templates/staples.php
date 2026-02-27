<?php
/**
 * Staples Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <h1>Staple Items</h1>
    <p class="description">These are items you've purchased multiple times. They're considered staples in your pantry.</p>
    
    <?php if ( empty( $staples ) ) : ?>
        <div class="notice notice-info">
            <p>No staples identified yet. Items you purchase 3 or more times will appear here.</p>
        </div>
    <?php else : ?>
        <div class="staples-grid">
            <?php foreach ( $staples as $staple ) : ?>
                <div class="staple-card">
                    <div class="staple-info">
                        <div class="staple-name"><?php echo esc_html( $staple->name ); ?></div>
                        <div class="staple-count">
                            ★ Purchased <?php echo intval( $staple->purchase_count ); ?> times
                        </div>
                        <div class="staple-price">
                            Avg price: $<?php echo number_format( $staple->avg_price, 2 ); ?> | 
                            Last: <?php echo human_time_diff( strtotime( $staple->last_purchased ), time() ); ?> ago
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
