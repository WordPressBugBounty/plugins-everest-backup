<?php
/**
 * Everest Backup constants.
 * Migrated constants from everest-backup.php since 1.0.7
 *
 * @package everest-backup
 * @since 1.0.7
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'EVEREST_BACKUP_VERSION' ) ) {

	/**
	 * Everest Backup version.
	 */
	define( 'EVEREST_BACKUP_VERSION', ( get_file_data( EVEREST_BACKUP_FILE, array( 'Version' => 'Version' ) )['Version'] ) );
}

if ( ! defined( 'EVEREST_BACKUP_URL' ) ) {

	/**
	 * URL to Everest Backup plugin folder.
	 */
	define( 'EVEREST_BACKUP_URL', trailingslashit( plugin_dir_url( EVEREST_BACKUP_FILE ) ) );
}

if ( ! defined( 'EVEREST_BACKUP_BACKUP_FILE_EXTENSION' ) ) {

	/**
	 * Backup file extension.
	 */
	define( 'EVEREST_BACKUP_BACKUP_FILE_EXTENSION', '.ebwp' );
}

if ( ! defined( 'EVEREST_BACKUP_TEMP_DIR_PATH' ) ) {

	/**
	 * Directory path to everest backup temporary folder.
	 *
	 * @since 1.0.7
	 * @since 1.1.2 Temp directory moved to wp-content folder.
	 */
	define( 'EVEREST_BACKUP_TEMP_DIR_PATH', wp_normalize_path( WP_CONTENT_DIR . '/ebwp-temps' ) );
}

if ( ! defined( 'EVEREST_BACKUP_BACKUP_DIR_PATH' ) ) {

	/**
	 * Directory path to backups folder.
	 */
	define( 'EVEREST_BACKUP_BACKUP_DIR_PATH', wp_normalize_path( WP_CONTENT_DIR . '/ebwp-backups' ) );
}

if ( ! defined( 'EVEREST_BACKUP_TAGS_PATH' ) ) {

	/**
	 * Path to tags.php file.
	 *
	 * @since 1.0.9
	 */
	define( 'EVEREST_BACKUP_TAGS_PATH', wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/tags.php' ) );
}

if ( ! defined( 'EVEREST_BACKUP_ACTIVITY_PATH' ) ) {

	$activity_path = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/activity.txt' );

	if ( file_exists( $activity_path ) ) {
		// phpcs:disable
		@unlink( $activity_path );
		// phpcs:enable
	}

	if ( defined( 'AUTH_KEY' ) ) {
		$activity_path = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/activity-' . md5( AUTH_KEY ) . '.txt' );
	}

	/**
	 * Path to activity.txt file.
	 */
	define( 'EVEREST_BACKUP_ACTIVITY_PATH', $activity_path );
}

if ( ! defined( 'EVEREST_BACKUP_PROC_STAT_PATH' ) ) {

	/**
	 * Path to PROCSTAT file.
	 */
	define( 'EVEREST_BACKUP_PROC_STAT_PATH', wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/PROCSTAT' ) );
}

if ( ! defined( 'EVEREST_BACKUP_TEMP_JSON_PATH' ) ) {

	/**
	 * Path to PROCSTAT file.
	 */
	define( 'EVEREST_BACKUP_TEMP_JSON_PATH', wp_normalize_path( EVEREST_BACKUP_TEMP_DIR_PATH . '/temp.json' ) );
}

if ( ! defined( 'EVEREST_BACKUP_LOCKFILE_PATH' ) ) {

	/**
	 * Path to LOCKFILE file.
	 */
	define( 'EVEREST_BACKUP_LOCKFILE_PATH', wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/LOCKFILE' ) );
}

if ( ! defined( 'EVEREST_BACKUP_LOCKFILE_STALE_THRESHOLD' ) ) {

	/**
	 * Stale threshold time for lockfile.
	 *
	 * @since 1.1.1
	 */
	define( 'EVEREST_BACKUP_LOCKFILE_STALE_THRESHOLD', HOUR_IN_SECONDS * 4 );
}

if ( ! defined( 'EVEREST_BACKUP_HTACCESS_PATH' ) ) {

	/**
	 * Path to htaccess file.
	 */
	define( 'EVEREST_BACKUP_HTACCESS_PATH', wp_normalize_path( ABSPATH . DIRECTORY_SEPARATOR . '.htaccess' ) );
}

if ( ! defined( 'EVEREST_BACKUP_BACKUP_DIR_URL' ) ) {

	/**
	 * Directory path to backups folder.
	 */
	define( 'EVEREST_BACKUP_BACKUP_DIR_URL', wp_normalize_path( WP_CONTENT_URL . '/ebwp-backups' ) );
}

if ( ! defined( 'EVEREST_BACKUP_LOGS_KEY' ) ) {

	/**
	 * Options table option name for the logs data.
	 */
	define( 'EVEREST_BACKUP_LOGS_KEY', 'everest_backup_logs' );
}

if ( ! defined( 'EVEREST_BACKUP_SETTINGS_KEY' ) ) {

	/**
	 * Options table option name for the settings data.
	 */
	define( 'EVEREST_BACKUP_SETTINGS_KEY', 'everest_backup_settings' );
}

if ( ! defined( 'EVEREST_BACKUP_EXPORT_ACTION' ) ) {

	/**
	 * Everest Backup export action.
	 *
	 * @since 1.0.7
	 * @since 2.0.0 Replaced `everest_backup_export` with `everest_backup_export_core`.
	 */
	define( 'EVEREST_BACKUP_EXPORT_ACTION', 'everest_backup_export_core' );
}

if ( ! defined( 'EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION' ) ) {

	/**
	 * Everest Backup upload package action.
	 *
	 * @since 1.0.7
	 */
	define( 'EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION', 'everest_backup_upload_package' );
}

if ( ! defined( 'EVEREST_BACKUP_PROCESS_STATUS_ACTION' ) ) {

	/**
	 * Everest Backup process status action.
	 *
	 * @since 1.0.7
	 */
	define( 'EVEREST_BACKUP_PROCESS_STATUS_ACTION', 'everest_process_status' );
}


if ( ! defined( 'EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION' ) ) {
	define( 'EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION', 'everest_backup_save_upload_package' );
}


if ( ! defined( 'EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION' ) ) {

	/**
	 * Everest Backup remove uploaded package action.
	 *
	 * @since 1.0.7
	 */
	define( 'EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION', 'everest_backup_remove_upload_package' );
}

if ( ! defined( 'EVEREST_BACKUP_IMPORT_ACTION' ) ) {

	/**
	 * Everest Backup import action.
	 *
	 * @since 1.0.7
	 * @since 2.0.0 Replaced `everest_backup_import` with `everest_backup_import_core`.
	 */
	define( 'EVEREST_BACKUP_IMPORT_ACTION', 'everest_backup_import_core' );
}

if ( ! defined( 'EVEREST_BACKUP_CLONE_ACTION' ) ) {

	/**
	 * Everest Backup clone action.
	 *
	 * @since 1.0.7
	 */
	define( 'EVEREST_BACKUP_CLONE_ACTION', 'everest_backup_clone_init' );
}

if ( ! defined( 'EVEREST_BACKUP_VIEWS_DIR' ) ) {

	/**
	 * Path to Everest Backup views folder.
	 */
	define( 'EVEREST_BACKUP_VIEWS_DIR', trailingslashit( EVEREST_BACKUP_PATH . 'inc/views' ) );
}

if ( ! defined( 'EVEREST_BACKUP_CONFIG_FILENAME' ) ) {

	/**
	 * FIlename of everest-backup config file.
	 */
	define( 'EVEREST_BACKUP_CONFIG_FILENAME', 'ebwp-config.json' );
}

if ( ! defined( 'EVEREST_BACKUP_DB_FILENAME' ) ) {

	/**
	 * Filename of everest-backup database file.
	 */
	define( 'EVEREST_BACKUP_DB_FILENAME', 'ebwp-database.sql' );
}

if ( ! defined( 'EVEREST_BACKUP_ELEMENTOR_CSS_CACHE_FILES' ) ) {

	/**
	 * Elementor CSS cache files.
	 *
	 * @since 1.0.7
	 */
	define( 'EVEREST_BACKUP_ELEMENTOR_CSS_CACHE_FILES', 'uploads' . DIRECTORY_SEPARATOR . 'elementor' . DIRECTORY_SEPARATOR . 'css' );

}

if ( ! defined( 'EVEREST_BACKUP_AUTH_REDIRECT_URL' ) ) {

	/**
	 * API redirect url.
	 */
	define( 'EVEREST_BACKUP_AUTH_REDIRECT_URL', set_url_scheme( 'https://auth.wpeverestbackup.com' ) );
}

if ( ! defined( 'EVEREST_BACKUP_ADDONS_JSON_URL' ) ) {

	/**
	 * Addons json file url for addons data.
	 */
	define( 'EVEREST_BACKUP_ADDONS_JSON_URL', set_url_scheme( 'https://wpeverestbackup.com/addons/addons.json' ) );
}

if ( ! defined( 'EVEREST_BACKUP_SIDEBAR_JSON_URL' ) ) {

	/**
	 * Sidebar json file url for sidebar data.
	 */
	define( 'EVEREST_BACKUP_SIDEBAR_JSON_URL', set_url_scheme( 'https://wpeverestbackup.com/addons/sidebar.json' ) );
}

if ( ! defined( 'EVEREST_BACKUP_EXCLUDED_FOLDERS_JSON_URL' ) ) {

	/**
	 * Fetch a list of excluded folders.
	 *
	 * @since 1.1.6
	 */
	define( 'EVEREST_BACKUP_EXCLUDED_FOLDERS_JSON_URL', set_url_scheme( 'https://wpeverestbackup.com/addons/excluded-folders.json' ) );
}

if ( ! defined( 'EVEREST_BACKUP_CLOUD_REST_API_PREFIX' ) ) {

	/**
	 * REST API prefix.
	 */
	define( 'EVEREST_BACKUP_CLOUD_REST_API_PREFIX', 'everest_backup_upload_cloud_backup_' );
}
