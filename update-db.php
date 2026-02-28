<?php
// Trigger database update for RallyShopper - upload to WordPress root and run once
require_once 'wp-load.php';

// Update DB version to force reinstall
update_option('rallyshopper_db_version', '1.0.0');

// Include and run install
require_once 'wp-content/plugins/rallyshopper/includes/class-database.php';
$db = new RallyShopper_Database();
$db->install();

echo "Database tables updated successfully! Delete this file.\n";
