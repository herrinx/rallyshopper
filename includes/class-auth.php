<?php
/**
 * Kroger Authentication Handler
 */

class RallyShopper_Auth {
    
    // Refresh token cron job
    public static function refresh_token() {
        $expires = get_option( 'rallyshopper_kroger_token_expires' );
        
        // Only refresh if token expires in less than 1 hour
        if ( $expires && $expires < time() + 3600 ) {
            $kroger = new RallyShopper_Kroger_API();
            $result = $kroger->refresh_token();
            
            if ( is_wp_error( $result ) ) {
                Herrecipes::log( 'Token refresh failed: ' . $result->get_error_message(), 'error' );
            } else {
                Herrecipes::log( 'Token refreshed successfully' );
            }
        }
    }
    
    // Disconnect Kroger account
    public static function disconnect() {
        delete_option( 'rallyshopper_kroger_access_token' );
        delete_option( 'rallyshopper_kroger_refresh_token' );
        delete_option( 'rallyshopper_kroger_token_expires' );
        
        return true;
    }
    
    // Get connection status
    public static function get_status() {
        $configured = ! empty( get_option( 'rallyshopper_kroger_client_id' ) ) && ! empty( get_option( 'rallyshopper_kroger_client_secret' ) );
        $authenticated = ! empty( get_option( 'rallyshopper_kroger_access_token' ) );
        $expires = get_option( 'rallyshopper_kroger_token_expires' );
        
        return array(
            'configured'    => $configured,
            'authenticated' => $authenticated,
            'expires'       => $expires,
            'expires_human' => $expires ? human_time_diff( time(), $expires ) : null,
        );
    }
}
