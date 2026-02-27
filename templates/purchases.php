<?php
/**
 * Purchases Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <h1>Purchase History</h1>
    
    <?php if ( empty( $purchases ) ) : ?>
        <div class="notice notice-info">
            <p>No purchases tracked yet. When you add items to your Kroger cart from recipes, they'll appear here.</p>
        </div>
    <?php else : ?>
        <div class="purchase-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>UPC</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $purchases as $purchase ) : ?>
                        <tr>
                            <td>
                                <?php 
                                $db = new RallyShopper_Database();
                                $ingredient = $wpdb->get_row( $wpdb->prepare( 
                                    "SELECT name FROM {$wpdb->prefix}rallyshopper_ingredients WHERE id = %d", 
                                    $purchase->ingredient_id 
                                ) );
                                echo esc_html( $ingredient ? $ingredient->name : 'Unknown' );
                                ?>
                            </td>
                            <td><code><?php echo esc_html( $purchase->kroger_upc ); ?></code></td>
                            <td><?php echo intval( $purchase->quantity ); ?></td>
                            <td><?php echo $purchase->price_paid ? '$' . number_format( $purchase->price_paid, 2 ) : '-'; ?></td>
                            <td><?php echo human_time_diff( strtotime( $purchase->purchased_at ), time() ) . ' ago'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
