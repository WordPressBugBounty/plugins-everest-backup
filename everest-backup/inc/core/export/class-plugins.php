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

class Plugins {

	use Export;

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

	private static function run() {
		$params = self::read_config( 'Params' );

		if ( ( isset( $params['incremental'] ) && $params['incremental'] ) || ( self::is_ignored( 'plugins' ) && ! isset( $params['parent_incremental'] ) ) ) {

			Logs::set_proc_stat(
				array(
					'log'      => 'warn',
					'status'   => 'in-process',
					'progress' => 21,
					'message'  => __( 'Plugins ignored.', 'everest-backup' ),
				)
			);

			return self::set_next( 'media' );
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 21,
				'message'  => __( 'Listing plugin files', 'everest-backup' ),
			)
		);

		$files = Filesystem::init()->list_files( WP_PLUGIN_DIR, self::excluded_plugins() );

		self::put_current_backup_file_info( $files );

		$total_files = count( $files );
		$total_size  = 0;

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $index => $file ) {

				$count = $index + 1;

				if ( ! @is_readable( $file ) ) {
					continue;
				}

				self::addtolist( $file );

				$progress = ( $count / $total_files ) * 100;

				Logs::set_proc_stat(
					array(
						'status'   => 'in-process',
						'progress' => round( $progress * 0.07 + 21, 2 ), // Starts at 21% and ends at 28%.
						'message'  => sprintf(
							__( 'Listing plugin files: %d%% completed', 'everest-backup' ),
							esc_html( $progress )
						),
						'detail'   => sprintf( __( 'Listing plugin file: %s', 'everest-backup' ), basename( $file ) ),
					)
				);

				$total_size += filesize( $file );

			}
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 28,
				'message'  => sprintf(
					__( 'Plugins listed. Total files: %1$s [ %2$s ]', 'everest-backup' ),
					esc_html( $total_files ),
					esc_html( everest_backup_format_size( $total_size ) )
				),
			)
		);

		return self::set_next( 'media' );
	}
}
