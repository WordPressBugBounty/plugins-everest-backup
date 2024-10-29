<?php
/**
 * Core export database class file.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Export;

use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Modules\Export_Database;
use Everest_Backup\Traits\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class database.
 */
class Database {

	use Export;

	/**
	 * Export database.
	 *
	 * @param string $sql_file Sql file path.
	 */
	private static function export_database( $sql_file = '' ) {

		$params = self::read_config( 'Params' );

		if ( isset( $params['ebwp_site_db_prefix'] ) ) {
			$prefix = $params['ebwp_site_db_prefix'];
		} else {
			global $wpdb;
			$prefix = $wpdb->prefix;
		}

		$export_database = new Export_Database( $sql_file );

		$export_database->add_table_prefix_filter( $prefix );

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

		return $export_database;
	}

	/**
	 * List tables.
	 */
	private static function list_tables() {

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 14,
				'message'  => __( 'Listing database tables.', 'everest-backup' ),
			)
		);

		$tables = array();

		$export_database = self::export_database();

		$query_tables = $export_database->query( $export_database->get_tables_query() );

		while ( $table = $query_tables->fetch_row() ) { // @phpcs:ignore
			$table_name = $table[0];

			$tables[] = $table_name;

			Logs::set_proc_stat(
				array(
					'status'   => 'in-process',
					'progress' => 14,
					'message'  => __( 'Listing database tables.', 'everest-backup' ),
					/* translators: table name */
					'detail'   => sprintf( __( 'Table listed: %s', 'everest-backup' ), $table_name ),
				)
			);

		}

		$query_tables->free_result();

		$config = self::read_config();

		if ( ! $config ) {
			$config = array();
		}

		$config['Database']['Tables'] = $tables;

		if ( ! self::writefile( EVEREST_BACKUP_CONFIG_FILENAME, wp_json_encode( $config ) ) ) {
			$error = __( 'Could not write tables to config file. Write error, aborting backup.', 'everest-backup' );
			Logs::error( $error );
			everest_backup_send_error();
			die;
		}
		if ( json_last_error() ) {
			Logs::error( json_last_error_msg() );
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 16,
				/* translators: number of tables */
				'message'  => sprintf( __( 'Total %s database tables listed.', 'everest-backup' ), esc_html( count( $tables ) ) ),
			)
		);

		return self::set_next( 'database', 'export_tables' );
	}

	/**
	 * Export tables.
	 *
	 * @param array  $tables Tables array.
	 * @param string $dirpath Dir path.
	 */
	private static function export_tables( $tables, $dirpath ) {

		if ( ! is_dir( $dirpath ) ) {
			Filesystem::init()->mkdir_p( $dirpath );
		}

		$total_tables = count( $tables );

		$explode     = explode( ':', self::$params['subtask'] );
		$current_key = ! empty( $explode[1] ) && is_int( absint( $explode[1] ) ) ? absint( $explode[1] ) : 0;
		$next_key    = $current_key + 1;

		if ( ! $current_key ) {
			Logs::info( __( 'Exporting database', 'everest-backup' ) );
		}

		$progress = ( ( $next_key ) / $total_tables ) * 100;

		$proc_stat_args = array(
			'status'   => 'in-process',
			'progress' => round( ( $progress * 0.02 + 16 ), 2 ),
			'message'  => sprintf(
				/* translators: number of tables */
				__( 'Exporting database: %1$d%% completed [ %2$s out of %3$s ]', 'everest-backup' ),
				esc_html( $progress ),
				esc_html( $next_key ),
				esc_html( $total_tables )
			),
		);

		if ( $total_tables !== $current_key ) {

			$table_name = $tables[ $current_key ];

			$sql_file = wp_normalize_path( $dirpath . DIRECTORY_SEPARATOR . "{$table_name}.sql" );

			$export_database = self::export_database( $sql_file );

			$params = self::read_config( 'Params' );
	
			if ( isset( $params['ebwp_site_db_prefix'] ) ) {
				$prefix = $params['ebwp_site_db_prefix'];
			} else {
				global $wpdb;
				$prefix = $wpdb->prefix;
			}

			$export_database->export_table(
				$table_name,
				function ( $query_count ) use ( &$proc_stat_args ) {
					/* translators: number of queries */
					$proc_stat_args['detail'] = sprintf( 'Queries count: %s', $query_count );

					return Logs::set_proc_stat( $proc_stat_args );
				},
				$prefix
			);

			self::addtolist( $sql_file );

			$proc_stat_args['next']    = 'database';
			$proc_stat_args['subtask'] = "export_tables:{$next_key}";

			Logs::set_proc_stat( $proc_stat_args );

		} else {

			Logs::set_proc_stat(
				array(
					'log'      => 'info',
					'status'   => 'in-process',
					'progress' => 18,
					'message'  => sprintf( __( 'Database tables exported.', 'everest-backup' ), esc_html( $total_tables ) ),
					'next'     => 'plugins',
				)
			);

		}
	}

	/**
	 * Function to run database export related tasks.
	 */
	private static function run() {

		if ( self::is_ignored( 'database' ) ) {

			Logs::set_proc_stat(
				array(
					'log'      => 'warn',
					'status'   => 'in-process',
					'progress' => 14,
					'message'  => __( 'Database ignored.', 'everest-backup' ),
				)
			);

			return self::set_next( 'plugins' );
		}

		$dirpath = everest_backup_current_request_storage_path( 'ebwp-database' );

		if ( ! empty( self::$params['subtask'] ) ) {
			switch ( explode( ':', self::$params['subtask'] )[0] ) {
				case 'export_tables':
					$config_database = self::read_config( 'Database' );

					$error = '';

					if ( empty( $config_database ) ) {
						$error = __( 'Could not read Database data from config file, aborting backup.', 'everest-backup' );
					}

					if ( empty( $config_database['Tables'] ) ) {
						$error = __( 'Could not read Database Tables data from config file, aborting backup.', 'everest-backup' );
					}

					if ( ! empty( $error ) ) {
						Logs::error( $error );
						everest_backup_send_error();
						die;
					}

					self::export_tables( $config_database['Tables'], $dirpath );
					break;

				default:
					return self::set_next( 'plugins' );
			}
		} else {
			self::list_tables();
		}
	}
}
