<?php
/**
 * Core export Themes class file.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Export;

use Everest_Backup\Core\Archiver;
use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Traits\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Themes {

	use Export;

	private static function run() {
		$params = self::read_config( 'Params' );

		if ( ( isset( $params['incremental'] ) && $params['incremental'] ) || ( self::is_ignored( 'themes' ) && ! isset( $params['parent_incremental'] ) ) ) {

			Logs::set_proc_stat(
				array(
					'status'   => 'in-process',
					'progress' => 49,
					'message'  => __( 'Themes ignored.', 'everest-backup' ),
					'log'      => 'warn'
				)
			);

			return self::set_next( 'content' );
		}

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 49,
				'message'  => __( 'Listing theme files', 'everest-backup' ),
				'log'      => 'info'
			)
		);

		$files = Filesystem::init()->list_files( get_theme_root() );

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
						'progress' => round( $progress * 0.07 + 49, 2 ), // Starts at 49 and ends at 56.
						'message'  => sprintf(
							__( 'Listing theme files: %d%% completed', 'everest-backup' ),
							esc_html( $progress )
						),
						'detail' => sprintf( __( 'Listing theme file: %s', 'everest-backup' ), basename( $file ) )
					)
				);

				$total_size += filesize( $file );

			}
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 56,
				'message'  => sprintf(
					__( 'Themes listed. Total files: %1$s [ %2$s ]', 'everest-backup' ),
					esc_html( $total_files ),
					esc_html( everest_backup_format_size( $total_size ) )
				),
			)
		);

		return self::set_next( 'content' );

	}

}
