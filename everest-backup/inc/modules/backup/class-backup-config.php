<?php
/**
 * Create system configuration backup file.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Logs;
use Everest_Backup\Traits\Backup;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create system configuration backup file.
 *
 * @since 1.0.0
 */
class Backup_Config {

	use Backup;

	/**
	 * Temp path to config json path.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $config_json_path;

	/**
	 * System configuration array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function config() {
		global $table_prefix, $wp_version, $wpdb;

		$config_data = self::$compress->get_temp_data( 'config_data' );

		$config = array();

		$config['FileInfo'] = array(
			'uniqid'    => self::$compress->zip_uniqid(),
			'timestamp' => self::$compress->timestamp(),
		);

		// Set site URL.
		$config['SiteURL'] = site_url();

		// Set home URL.
		$config['HomeURL'] = home_url();

		$config['NavMenus'] = get_nav_menu_locations(); // @since 1.1.4

		$config['Widgets'] = get_option( 'sidebars_widgets', array() ); // @since 1.1.4

		/**
		 * Set everest backup info.
		 */
		$config['Plugin'] = array(
			'Version'         => EVEREST_BACKUP_VERSION,
			'InstalledAddons' => everest_backup_installed_addons(),
			'ActiveAddons'    => everest_backup_installed_addons( 'active' ),
		);

		// Set WordPress version and content.
		$config['WordPress'] = array(
			'Multisite'  => is_multisite(),
			'Version'    => $wp_version,
			'Content'    => WP_CONTENT_DIR,
			'Plugins'    => WP_PLUGIN_DIR,
			'Themes'     => get_theme_root(),
			'UploadsDIR' => everest_backup_get_uploads_dir(),
			'UploadsURL' => everest_backup_get_uploads_url(),
		);

		$config['Database'] = array(
			'Version' => $wpdb->db_version(),
			'Charset' => DB_CHARSET,
			'Collate' => DB_COLLATE,
			'Prefix'  => $table_prefix,
			'Tables'  => isset( $config_data['db_tables'] ) ? $config_data['db_tables'] : array(),
		);

		$config['Template']      = get_option( 'template' );
		$config['Stylesheet']    = get_option( 'stylesheet' );
		$config['ActivePlugins'] = get_option( 'active_plugins', array() );

		$config['PHP'] = array(
			'Version' => PHP_VERSION,
			'System'  => PHP_OS,
			'Integer' => PHP_INT_SIZE,
		);

		$config['Server'] = array(
			'.htaccess' => everest_backup_str2hex( everest_backup_get_htaccess() ),
		);

		return $config;

	}

	/**
	 * Scripts to run before adding files to archive.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_addfiles() {

		Logs::info( __( 'Creating config file.', 'everest-backup' ) );

		$config_json = wp_json_encode( self::config() );
		$filesystem  = self::$filesystem;
		$storage_dir = self::$storage_dir;

		$config_json_path = wp_normalize_path( $storage_dir . '/' . EVEREST_BACKUP_CONFIG_FILENAME );

		$created = $filesystem->writefile( $config_json_path, $config_json );

		if ( $created && $filesystem->is_file( $config_json_path ) ) {
			self::$config_json_path = $config_json_path;
		}

	}

	/**
	 * List files.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function files() {

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 21.42,
				'message'  => __( 'Creating backup config file', 'everest-backup' ),
			)
		);

		return (array) self::$config_json_path;
	}

	/**
	 * Scripts to run after files added to the list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_addfiles() {
		if ( self::$filesystem->is_file( self::$config_json_path ) ) {

			$explode = explode( EVEREST_BACKUP_CONFIG_FILENAME, self::$config_json_path );
			self::$compress->add_paths_to_replace( $explode[0] );

			Logs::info( __( 'Config file created successfully.', 'everest-backup' ) );
		} else {
			Logs::warn( __( 'Unable to create config file.', 'everest-backup' ) );
		}
	}

}
