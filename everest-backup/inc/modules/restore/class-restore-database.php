<?php
/**
 * Restore class for database.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Logs;
use Everest_Backup\Traits\Restore;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restore class for database.
 *
 * @since 1.0.0
 */
class Restore_Database {

	use Restore;

	/**
	 * Path to the database file.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $database_file;

	/**
	 * List of prefixed tables during export.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $exported_tables;

	/**
	 * Find and replace value from the sql string. Array key as the string to find and array value as string to replace with.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $find_replace;

	/**
	 * Scripts to run before restoreing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_restore() {
		$root_files = (array) self::$root;

		$database_file = self::get_filepath_from_list( EVEREST_BACKUP_DB_FILENAME, $root_files );

		if ( ! $database_file ) {
			return;
		}

		$config_data = self::$extract->get_temp_data( 'config_data' );

		$exported_tables = ! empty( $config_data['Database']['Tables'] ) ? $config_data['Database']['Tables'] : array();

		self::$database_file   = $database_file;
		self::$exported_tables = $exported_tables;
		self::$find_replace    = self::get_find_replace();

	}

	/**
	 * Start restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {

		$info_message = __( 'Restoring database', 'everest-backup' );

		Logs::info( $info_message );

		if ( ! self::$database_file ) {
			Logs::warn( __( 'Database file not found.', 'everest-backup' ) );
			return;
		}

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 30,
				'message'  => $info_message,
			)
		);

		$ms_blogs = self::$extract->get_temp_data( 'ms_blogs' );

		if ( is_array( $ms_blogs ) && ! empty( $ms_blogs ) ) {
			foreach ( $ms_blogs as $ms_blog_id => $ms_blog ) {
				switch_to_blog( $ms_blog_id );

				$config_data = self::$extract->get_temp_data( 'config_data' );

				$find_replace = array();

				$old_site_url = str_replace( array( 'http://', 'https://' ), '', $config_data['SiteURL'] );
				$old_home_url = str_replace( array( 'http://', 'https://' ), '', $config_data['HomeURL'] );

				$new_site_url = str_replace( array( 'http://', 'https://' ), '', $ms_blog['SubsiteURL'] );
				$new_home_url = str_replace( array( 'http://', 'https://' ), '', $ms_blog['SubsiteURL'] );

				$old_upload_dir = $config_data['WordPress']['UploadsDIR'];
				$new_upload_dir = everest_backup_get_uploads_dir();

				$old_upload_url = str_replace( array( 'http://', 'https://' ), '', $config_data['WordPress']['UploadsURL'] );
				$new_upload_url = str_replace( array( 'http://', 'https://' ), '', everest_backup_get_uploads_url() );

				$find_replace[ $old_site_url ]   = $new_site_url;
				$find_replace[ $old_home_url ]   = $new_home_url;
				$find_replace[ $old_upload_dir ] = $new_upload_dir;
				$find_replace[ $old_upload_url ] = $new_upload_url;

				$import_database = new Import_Database( self::$database_file, self::$exported_tables, $find_replace );
				$imported        = $import_database->import();

				restore_current_blog();
			}
		} else {
			$import_database = new Import_Database( self::$database_file, self::$exported_tables, self::$find_replace );
			$imported        = $import_database->import();
		}

		if ( $imported ) {
			Logs::info( __( 'Database restored.', 'everest-backup' ) );
			everest_backup_log_memory_used();
		} else {
			Logs::warn( __( 'Failed to restore database.', 'everest-backup' ) );
		}

	}

	/**
	 * Scripts to run after restoreing.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_restore() {

		if ( ! self::$database_file ) {
			return;
		}

		update_option( 'template', '' );
		update_option( 'stylesheet', '' );
		update_option( 'active_plugins', array() );

		unlink( self::$database_file );
	}
}
