<?php
/**
 * Database operations for RallyShopper
 */

class RallyShopper_Database {
    
    public function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_recipes = $wpdb->prefix . 'rallyshopper_recipes';
        $table_ingredients = $wpdb->prefix . 'rallyshopper_ingredients';
        $table_purchases = $wpdb->prefix . 'rallyshopper_purchases';
        $table_staples = $wpdb->prefix . 'rallyshopper_staples';
        $table_logs = $wpdb->prefix . 'rallyshopper_logs';

        // Log the prefix being used
        $this->add_log( 'debug', 'db_install', 'Creating tables with prefix: ' . $wpdb->prefix );

        $sql = "
            CREATE TABLE {$table_recipes} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) NOT NULL,
                servings int(11) DEFAULT 4,
                prep_time int(11) DEFAULT 0,
                cook_time int(11) DEFAULT 0,
                difficulty varchar(20) DEFAULT 'medium',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY post_id (post_id)
            ) {$charset_collate};
            
            CREATE TABLE {$table_ingredients} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                recipe_id bigint(20) NOT NULL,
                name varchar(255) NOT NULL,
                amount varchar(100) DEFAULT NULL,
                unit varchar(50) DEFAULT NULL,
                kroger_product_id varchar(100) DEFAULT NULL,
                kroger_upc varchar(50) DEFAULT NULL,
                kroger_description text DEFAULT NULL,
                kroger_image_url text DEFAULT NULL,
                kroger_price decimal(10,2) DEFAULT NULL,
                is_staple tinyint(1) DEFAULT 0,
                sort_order int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY recipe_id (recipe_id),
                KEY kroger_product_id (kroger_product_id)
            ) {$charset_collate};
            
            CREATE TABLE {$table_purchases} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                ingredient_id bigint(20) NOT NULL,
                kroger_product_id varchar(100) NOT NULL,
                kroger_upc varchar(50) NOT NULL,
                quantity int(11) DEFAULT 1,
                price_paid decimal(10,2) DEFAULT NULL,
                purchased_at datetime DEFAULT CURRENT_TIMESTAMP,
                order_id varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ingredient_id (ingredient_id),
                KEY kroger_product_id (kroger_product_id),
                KEY purchased_at (purchased_at)
            ) {$charset_collate};
            
            CREATE TABLE {$table_staples} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                kroger_product_id varchar(100) NOT NULL,
                kroger_upc varchar(50) NOT NULL,
                name varchar(255) NOT NULL,
                purchase_count int(11) DEFAULT 1,
                last_purchased datetime DEFAULT CURRENT_TIMESTAMP,
                avg_price decimal(10,2) DEFAULT NULL,
                is_active tinyint(1) DEFAULT 1,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY kroger_product_id (kroger_product_id),
                KEY kroger_upc (kroger_upc)
            ) {$charset_collate};
            
            CREATE TABLE {$table_logs} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL DEFAULT 'info',
                action varchar(100) NOT NULL,
                message text NOT NULL,
                context longtext DEFAULT NULL,
                user_id bigint(20) DEFAULT NULL,
                recipe_id bigint(20) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY level (level),
                KEY action (action),
                KEY created_at (created_at)
            ) {$charset_collate};
        ";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $result = dbDelta( $sql );

        // Log dbDelta results
        $this->add_log( 'debug', 'db_install_result', 'dbDelta result: ' . json_encode( $result ) );

        update_option( 'rallyshopper_db_version', RALLYSHOPPER_DB_VERSION );
    }
    
    // Recipe methods
    public function get_recipe( $recipe_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_recipes';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d OR post_id = %d", $recipe_id, $recipe_id ) );
    }
    
    public function save_recipe( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_recipes';
        
        if ( isset( $data['id'] ) ) {
            $wpdb->update( $table, $data, array( 'id' => $data['id'] ) );
            return $data['id'];
        } else {
            $result = $wpdb->insert( $table, $data );
            if ( $result === false ) {
                $this->add_log( 'error', 'db_insert_recipe', 'Insert failed: ' . $wpdb->last_error . ' | Data: ' . json_encode($data) );
                return 0;
            }
            return $wpdb->insert_id;
        }
    }
    
    // Ingredient methods
    public function get_ingredients( $recipe_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE recipe_id = %d ORDER BY sort_order ASC", 
            $recipe_id 
        ) );
    }
    
    public function save_ingredient( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        
        $this->add_log( 'debug', 'save_ingredient', 'Saving: ' . $data['name'] . ' is_staple=' . ($data['is_staple'] ?? 'NOT SET') );
        
        if ( isset( $data['id'] ) && $data['id'] ) {
            $wpdb->update( $table, $data, array( 'id' => $data['id'] ) );
            return $data['id'];
        } else {
            $wpdb->insert( $table, $data );
            return $wpdb->insert_id;
        }
    }
    
    public function delete_ingredient( $ingredient_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        return $wpdb->delete( $table, array( 'id' => $ingredient_id ) );
    }
    
    public function delete_recipe_ingredients( $recipe_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_ingredients';
        return $wpdb->delete( $table, array( 'recipe_id' => $recipe_id ) );
    }
    
    // Purchase tracking
    public function record_purchase( $data ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_purchases';
        $wpdb->insert( $table, $data );
        
        // Update staple tracking
        $this->update_staple( $data['kroger_product_id'], $data['kroger_upc'], $data['price_paid'] );
        
        return $wpdb->insert_id;
    }
    
    public function get_purchases( $product_id = null, $limit = 50 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_purchases';
        
        if ( $product_id ) {
            return $wpdb->get_results( $wpdb->prepare( 
                "SELECT * FROM {$table} WHERE kroger_product_id = %s ORDER BY purchased_at DESC LIMIT %d", 
                $product_id, $limit 
            ) );
        }
        
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY purchased_at DESC LIMIT {$limit}" );
    }
    
    // Staple methods
    public function update_staple( $product_id, $upc, $price ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_staples';
        
        $existing = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE kroger_product_id = %s", 
            $product_id 
        ) );
        
        if ( $existing ) {
            $new_count = $existing->purchase_count + 1;
            $new_avg = ( ( $existing->avg_price * $existing->purchase_count ) + $price ) / $new_count;
            
            $wpdb->update( $table, array(
                'purchase_count' => $new_count,
                'last_purchased' => current_time( 'mysql' ),
                'avg_price'      => $new_avg,
            ), array( 'id' => $existing->id ) );
        } else {
            // Get product info from Kroger API
            $kroger = new RallyShopper_Kroger_API();
            $product = $kroger->get_product( $product_id );
            $name = $product ? $product['description'] : 'Unknown Product';
            
            $wpdb->insert( $table, array(
                'kroger_product_id' => $product_id,
                'kroger_upc'        => $upc,
                'name'              => $name,
                'purchase_count'    => 1,
                'avg_price'         => $price,
            ) );
        }
    }
    
    public function get_staples( $min_purchases = 3 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_staples';
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE purchase_count >= %d AND is_active = 1 ORDER BY purchase_count DESC", 
            $min_purchases 
        ) );
    }
    
    public function is_staple( $product_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_staples';
        $count = $wpdb->get_var( $wpdb->prepare( 
            "SELECT purchase_count FROM {$table} WHERE kroger_product_id = %s AND is_active = 1", 
            $product_id 
        ) );
        return $count ? intval( $count ) : 0;
    }
    
    // Logging methods
    public function add_log( $level, $action, $message, $context = null, $recipe_id = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_logs';
        
        $data = array(
            'level'     => sanitize_text_field( $level ),
            'action'    => sanitize_text_field( $action ),
            'message'   => sanitize_textarea_field( $message ),
            'user_id'   => get_current_user_id(),
            'recipe_id' => $recipe_id ? intval( $recipe_id ) : null,
        );
        
        if ( $context ) {
            $data['context'] = json_encode( $context );
        }
        
        $wpdb->insert( $table, $data );
        return $wpdb->insert_id;
    }
    
    public function get_logs( $limit = 100, $offset = 0, $level = null, $action = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_logs';
        
        $where = array( '1=1' );
        if ( $level ) {
            $where[] = $wpdb->prepare( 'level = %s', $level );
        }
        if ( $action ) {
            $where[] = $wpdb->prepare( 'action = %s', $action );
        }
        
        $where_clause = implode( ' AND ', $where );
        
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", 
            $limit, 
            $offset 
        ) );
    }
    
    public function get_logs_count( $level = null, $action = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_logs';
        
        $where = array( '1=1' );
        if ( $level ) {
            $where[] = $wpdb->prepare( 'level = %s', $level );
        }
        if ( $action ) {
            $where[] = $wpdb->prepare( 'action = %s', $action );
        }
        
        $where_clause = implode( ' AND ', $where );
        return intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" ) );
    }
    
    public function clear_logs( $level = null, $days = null ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rallyshopper_logs';
        
        $where = array();
        if ( $level ) {
            $where[] = $wpdb->prepare( 'level = %s', $level );
        }
        if ( $days ) {
            $where[] = $wpdb->prepare( 'created_at < DATE_SUB(NOW(), INTERVAL %d DAY)', intval( $days ) );
        }
        
        if ( empty( $where ) ) {
            $wpdb->query( "TRUNCATE TABLE {$table}" );
        } else {
            $where_clause = implode( ' AND ', $where );
            $wpdb->query( "DELETE FROM {$table} WHERE {$where_clause}" );
        }
    }
}
