<?php
/**
 * Backup class for database.
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
 * Backup class for database.
 *
 * @since 1.0.0
 */
class Backup_Database {

	use Backup;

	/**
	 * Temp path to database file.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $database_file_path;

	/**
	 * Scritps to run before adding files to the list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_addfiles() {

		global $wpdb;

		Logs::info( __( 'Exporting database.', 'everest-backup' ) );

		$storage_dir = self::$storage_dir;

		$database_file_path = wp_normalize_path( $storage_dir . '/' . EVEREST_BACKUP_DB_FILENAME );

		$export_database = new Export_Database( $database_file_path );

		$export_database->add_table_prefix_filter( $wpdb->prefix );

		$additional_table_prefixes = apply_filters(
			'everest_backup_filter_additional_table_prefixes',
			array(
				'wbk_services',
				'wbk_days_on_off',
				'wbk_locked_time_slots',
				'wbk_appointments',
				'wbk_cancelled_appointments',
				'wbk_email_templates',
				'wbk_service_categories',
				'wbk_gg_calendars',
				'wbk_coupons',
			)
		);

		if ( is_array( $additional_table_prefixes ) && ! empty( $additional_table_prefixes ) ) {
			foreach ( $additional_table_prefixes as $additional_table_prefix ) {
				$export_database->add_table_prefix_filter( $additional_table_prefix );
			}
		}

		$created = $export_database->export();

		$temp_data = array(
			'db_tables' => $export_database->get_tables(),
		);

		self::$compress->set_temp_data( 'config_data', $temp_data );

		$filesystem = self::$filesystem;

		if ( $created && $filesystem->is_file( $database_file_path ) ) {
			self::$database_file_path = $database_file_path;
		}
	}

	/**
	 * Add database file to the list.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function files() {
		return (array) self::$database_file_path;
	}

	/**
	 * Scripts to run after files added to the list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_addfiles() {
		if ( self::$filesystem->is_file( self::$database_file_path ) ) {

			Logs::set_proc_stat(
				array(
					'status'   => 'in-process',
					'progress' => 14.28,
					/* translators: %s is the size of the database file. */
					'message'  => sprintf( __( 'Listing database ( %s )', 'everest-backup' ), everest_backup_format_size( filesize( self::$database_file_path ) ) ),
				)
			);

			$explode = explode( EVEREST_BACKUP_DB_FILENAME, self::$database_file_path );
			self::$compress->add_paths_to_replace( $explode[0] );

			Logs::info( __( 'Database file created successfully.', 'everest-backup' ) );
		} else {
			Logs::warn( __( 'Unable to create Database file.', 'everest-backup' ) );
		}
	}
}
