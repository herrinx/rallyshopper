<?php
/**
 * Settings Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <h1>Herrecipes Settings</h1>
    
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
                <li>Name: Herrecipes</li>
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
