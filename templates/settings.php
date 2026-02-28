<?php
/**
 * Settings Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <h1>RallyShopper Settings</h1>
    
    <form method="post" class="rallyshopper-form" style="max-width: 600px;">
        <?php wp_nonce_field( 'rallyshopper_settings' ); ?>
        
        <h2>Kroger API Credentials</h2>
        <p class="description">Get these from the <a href="https://developer.kroger.com/" target="_blank">Kroger Developer Portal</a>.</p>
        
        <div class="form-field">
            <label for="kroger_client_id">Client ID</label>
            <input type="text" id="kroger_client_id" name="kroger_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text">
        </div>
        
        <div class="form-field">
            <label for="kroger_client_secret">Client Secret</label>
            <input type="password" id="kroger_client_secret" name="kroger_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text">
            <p class="description">Your client secret is stored securely in the WordPress options table.</p>
        </div>
        
        <h2>Recipe Defaults</h2>
        
        <div class="form-field">
            <label for="default_servings">Default Servings</label>
            <input type="number" id="default_servings" name="default_servings" value="<?php echo esc_attr( $default_servings ); ?>" min="1" style="width: 100px;">
        </div>

        <h2>Store Location</h2>
        <p class="description">Select your preferred King Soopers store for local pricing and product selection. This helps show accurate prices and products available in your area. Delivery availability is determined by Kroger at checkout based on your delivery address.</p>

        <div class="form-field">
            <label for="kroger_location_id">Store Location ID</label>
            <input type="text" id="kroger_location_id" name="kroger_location_id" value="<?php echo esc_attr( get_option( 'rallyshopper_kroger_location_id', '' ) ); ?>" class="regular-text">
            <p class="description">Enter your store location ID for local pricing, or leave blank to search without location filtering.</p>
        </div>

        <div class="form-field">
            <label for="kroger_zip_code">Zip Code (for store search)</label>
            <input type="text" id="kroger_zip_code" name="kroger_zip_code" value="<?php echo esc_attr( get_option( 'rallyshopper_kroger_zip', '' ) ); ?>" class="regular-text" placeholder="80202">
            <button type="button" id="rallyshopper-find-stores" class="button">Find Nearby Stores</button>
            <div id="rallyshopper-store-results"></div>
            <p class="description"><strong>Note:</strong> Store selection affects pricing and local product availability only. Actual delivery availability is confirmed by Kroger at checkout when you select your delivery address.</p>
        </div>

        <div class="form-field">
            <button type="submit" name="rallyshopper_save_settings" class="button button-primary">Save Settings</button>
        </div>
    </form>
    
    <h2>Setup Instructions</h2>
    <ol>
        <li>Go to <a href="https://developer.kroger.com/" target="_blank">Kroger Developer Portal</a></li>
        <li>Create an account and sign in</li>
        <li>Create a new application
            <ul>
                <li>Name: RallyShopper</li>
                <li>Redirect URI: <code><?php echo admin_url( 'admin.php?page=rallyshopper-auth' ); ?></code></li>
                <li>Client Type: Confidential</li>
            </ul>
        </li>
        <li>Copy the Client ID and Client Secret to the fields above</li>
        <li>Save settings</li>
        <li>Go to <strong>Kroger Auth</strong> and connect your account</li>
        <li>Start adding recipes!</li>
    </ol>
</div>
