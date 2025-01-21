<?php
/**
 * Core export Plugins class file.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Export;

use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Traits\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Incremental {

	use Export;

	/**
	 * List of excluded plugins.
	 *
	 * @since 2.3.0
	 *
	 * @return array
	 */
	private static function excluded_plugins() {
		$excluded_plugins = array();

		$excluded_plugins[] = 'everest-backup';

		$addons = everest_backup_installed_addons();

		if ( is_array( $addons ) && ! empty( $addons ) ) {
			foreach ( $addons as $addon ) {
				$excluded_plugins[] = explode( '/', $addon )[0];
			}
		}

		return apply_filters( 'everest_backup_excluded_plugins', $excluded_plugins );
	}

	/**
	 * Runs the incremental export.
	 *
	 * @since 2.3.0
	 *
	 * @return void
	 */
    private static function run() {
		$params = self::read_config( 'Params' );

		if ( ! isset( $params['incremental'] ) ) {
			return self::set_next( 'wrapup' );
		}

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 71,
				'message'  => __( 'Listing incremental files', 'everest-backup' ),
				'log'      => 'info'
			)
		);

		$file_list = array();

		$files = Filesystem::init()->list_files( get_theme_root() );
		self::put_current_backup_file_info( $files );
		$file_list = array_merge( $file_list, $files );

		$files = Filesystem::init()->list_files( WP_PLUGIN_DIR, self::excluded_plugins() );
		self::put_current_backup_file_info( $files );
		$file_list = array_merge( $file_list, $files );

		$files = Filesystem::init()->list_files( WP_CONTENT_DIR, everest_backup_get_excluded_folders() );
		self::put_current_backup_file_info( $files );
		$file_list = array_merge( $file_list, $files );

		$files = Filesystem::init()->list_files( everest_backup_get_uploads_dir() );
		self::put_current_backup_file_info( $files );
		$file_list = array_merge( $file_list, $files );


		$total_files = count( $file_list );
		$total_size  = 0;

		if ( is_array( $file_list ) && ! empty( $file_list ) ) {
			$prev_file_list = self::read_last_backup_file_info();
			$count = 0;
			foreach ( $file_list as $index => $file ) {

				++$count;

				if ( ! @is_readable( $file ) ) { // @phpcs:ignore
					continue;
				}

				if ( array_key_exists( $file, $prev_file_list ) && ( $prev_file_list[ $file ] === filemtime( $file ) ) ) {
					unset( $file_list[ $index ], $prev_file_list[ $file ] );
					continue;
				}
				self::addtolist( $file );
				unset( $file_list[ $index ] );

				$progress = ( $count / $total_files ) * 100;

				Logs::set_proc_stat(
					array(
						'status'   => 'in-process',
						'progress' => round( $progress * 0.09 + 70, 2 ),
						'message'  => sprintf(
							/* translators: */
							__( 'Listing incremental content files: %d%% completed', 'everest-backup' ),
							esc_html( $progress )
						),
						/* translators: */
						'detail'   => sprintf( __( 'Listing content file: %s', 'everest-backup' ), basename( $file ) ),
					)
				);

				$total_size += filesize( $file );
			}

			if ( ! empty( $prev_file_list ) ) {
				foreach ( $prev_file_list as $file => $_ ) {
					self::addtoremovelist( $file );
				}
			}
		}

		everest_backup_export_wp_database();

		return self::set_next( 'wrapup' );
    }
}