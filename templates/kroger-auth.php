<?php
/**
 * Kroger Auth Template
 */
?>
<div class="wrap rallyshopper-wrap">
    <h1>Kroger Connection</h1>
    
    <?php if ( ! $configured ) : ?>
        <div class="auth-status not-configured">
            <h3>⚠️ Kroger API Not Configured</h3>
            <p>You need to configure your Kroger API credentials before connecting.</p>
            <ol>
                <li>Go to <a href="https://developer.kroger.com/" target="_blank">Kroger Developer Portal</a></li>
                <li>Create a new application</li>
                <li>Get your Client ID and Client Secret</li>
                <li>Enter them in the <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-settings' ); ?>">Settings</a> page</li>
                <li>Return here to connect your account</li>
            </ol>
            <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-settings' ); ?>" class="button button-primary">Go to Settings</a>
        </div>
    <?php elseif ( $authenticated ) : ?>
        <div class="auth-status connected">
            <h3>✅ Connected to Kroger</h3>
            <p>Your account is successfully connected to Kroger.</p>
            
            <?php if ( $profile && isset( $profile['data'] ) ) : ?>
                <p><strong>Account:</strong> <?php echo esc_html( $profile['data']['firstName'] . ' ' . $profile['data']['lastName'] ); ?></p>
            <?php endif; ?>
            
            <?php $expires = get_option( 'rallyshopper_kroger_token_expires' ); ?>
            <?php if ( $expires ) : ?>
                <p><strong>Token expires:</strong> <?php echo human_time_diff( time(), $expires ); ?></p>
            <?php endif; ?>
            
            <form method="post" action="<?php echo admin_url( 'admin.php?page=rallyshopper-auth&disconnect=1' ); ?>">
                <?php wp_nonce_field( 'rallyshopper_disconnect' ); ?>
                <button type="submit" class="button">Disconnect Account</button>
            </form>
        </div>
        
        <h2>Actions</h2>
        <p>
            <button type="button" class="button" id="rallyshopper-view-cart">View Cart</button>
            <span class="description">Check your current Kroger cart</span>
        </p>
    <?php else : ?>
        <div class="auth-status">
            <h3>Kroger API Configured</h3>
            <p>Click the button below to connect your Kroger account and authorize this plugin.</p>
            <a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">Connect to Kroger</a>
        </div>
        
        <h3>What you'll authorize:</h3>
        <ul>
            <li>✓ Read your profile information</li>
            <li>✓ Search Kroger products</li>
            <li>✓ Add items to your shopping cart</li>
        </ul>
    <?php endif; ?>
</div>
