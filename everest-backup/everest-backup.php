<?php
/**
 * Plugin Name: Everest Backup
 * Plugin URI: https://wpeverestbackup.com/
 * Description: Everest Backup is a modern tool that will take care of your website's backups, restoration, migration and cloning.
 * Author: everestthemes
 * Author URI: https://everestthemes.com/
 * Version: 2.3.2
 * Text Domain: everest-backup
 * License: GPLv3 or later
 * License URI: LICENSE
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EVEREST_BACKUP_FILE' ) ) {

	/**
	 * Everest Backup core file.
	 */
	define( 'EVEREST_BACKUP_FILE', __FILE__ );
}

if ( ! defined( 'EVEREST_BACKUP_PATH' ) ) {

	/**
	 * Path to Everest Backup plugin folder.
	 */
	define( 'EVEREST_BACKUP_PATH', trailingslashit( plugin_dir_path( EVEREST_BACKUP_FILE ) ) );
}

/**
 * Bootstrap our files.
 */
require_once EVEREST_BACKUP_PATH . '/inc/constants.php';
require_once EVEREST_BACKUP_PATH . '/inc/require.php';

/**
 * Init our plugin.
 *
 * @return Everest_Backup
 * @since 1.0.0
 */
function everest_backup() {
	return Everest_Backup::init();
}
everest_backup();
