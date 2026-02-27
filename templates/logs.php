<?php
/**
 * Logs Template
 */
$db = new RallyShopper_Database();
?>
<div class="wrap rallyshopper-wrap">
    <div class="rallyshopper-header">
        <h1>Logs</h1>
    </div>
    
    <!-- Filters -->
    <form method="get" class="rallyshopper-filters">
        <input type="hidden" name="page" value="rallyshopper-logs">
        
        <label>Level:</label>
        <select name="level">
            <option value="">All</option>
            <option value="error" <?php selected( $level, 'error' ); ?>>Error</option>
            <option value="warning" <?php selected( $level, 'warning' ); ?>>Warning</option>
            <option value="info" <?php selected( $level, 'info' ); ?>>Info</option>
            <option value="debug" <?php selected( $level, 'debug' ); ?>>Debug</option>
        </select>
        
        <label>Action:</label>
        <select name="action_filter">
            <option value="">All</option>
            <?php foreach ( $actions as $act ) : ?>
                <option value="<?php echo esc_attr( $act ); ?>" <?php selected( $action, $act ); ?>>
                    <?php echo esc_html( $act ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="button">Filter</button>
        <a href="<?php echo admin_url( 'admin.php?page=rallyshopper-logs' ); ?>" class="button">Reset</a>
    </form>
    
    <!-- Clear Logs Form -->
    <form method="post" style="margin: 20px 0; display: inline-block;">
        <?php wp_nonce_field( 'rallyshopper_clear_logs' ); ?>
        <button type="submit" name="clear_logs" class="button button-secondary" onclick="return confirm('Clear all logs?');">Clear All Logs</button>
    </form>
    
    <!-- Stats -->
    <div class="rallyshopper-stats" style="margin-bottom: 20px;">
        <span class="stat">Total: <strong><?php echo number_format( $total ); ?></strong></span>
        <?php if ( $total > 0 ) : ?>
            <span class="stat"> | Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
        <?php endif; ?>
    </div>
    
    <!-- Logs Table -->
    <?php if ( empty( $logs ) ) : ?>
        <div class="notice notice-info">
            <p>No logs found.</p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 80px;">Level</th>
                    <th style="width: 150px;">Time</th>
                    <th style="width: 120px;">Action</th>
                    <th>Message</th>
                    <th style="width: 60px;">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : 
                    $level_class = 'log-level-' . sanitize_html_class( $log->level );
                    $context = $log->context ? json_decode( $log->context, true ) : null;
                ?>
                    <tr class="<?php echo $level_class; ?>">
                        <td>
                            <span class="log-badge <?php echo $level_class; ?>">
                                <?php echo esc_html( strtoupper( $log->level ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
                        <td><?php echo esc_html( $log->action ); ?></td>
                        <td><?php echo esc_html( $log->message ); ?></td>
                        <td>
                            <?php if ( $context ) : ?>
                                <button type="button" class="button button-small" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none';">View</button>
                                <pre style="display:none; margin-top:10px; padding:10px; background:#f6f7f7; font-size:11px; max-height:200px; overflow:auto;"><?php echo esc_html( json_encode( $context, JSON_PRETTY_PRINT ) ); ?></pre>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base_url = add_query_arg( array( 'page' => 'rallyshopper-logs' ), admin_url( 'admin.php' ) );
                    if ( $level ) {
                        $base_url = add_query_arg( 'level', $level, $base_url );
                    }
                    if ( $action ) {
                        $base_url = add_query_arg( 'action_filter', $action, $base_url );
                    }
                    
                    echo paginate_links( array(
                        'base'      => add_query_arg( 'log_page', '%#%', $base_url ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $total_pages,
                        'current'   => $page,
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.rallyshopper-filters {
    background: #f6f7f7;
    padding: 15px;
    border: 1px solid #c3c4c7;
    margin-bottom: 20px;
}

.rallyshopper-filters label {
    margin-right: 5px;
    font-weight: 600;
}

.rallyshopper-filters select {
    margin-right: 15px;
}

.log-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.log-level-error .log-badge,
.log-badge.log-level-error {
    background: #d63638;
    color: #fff;
}

.log-level-warning .log-badge,
.log-badge.log-level-warning {
    background: #dba617;
    color: #000;
}

.log-level-info .log-badge,
.log-badge.log-level-info {
    background: #2271b1;
    color: #fff;
}

.log-level-debug .log-badge,
.log-badge.log-level-debug {
    background: #8c8f94;
    color: #fff;
}

.log-level-error {
    background: #fcf0f1;
}

.log-level-warning {
    background: #fcf9e8;
}

.rallyshopper-stats {
    color: #646970;
}

.rallyshopper-stats .stat {
    margin-right: 20px;
}
</style>
