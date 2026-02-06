<?php
/**
 * Core export Media class file.
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

class Media {

	use Export;

	private static function run() {
		$params = self::read_config( 'Params' );

		if ( ( isset( $params['incremental'] ) && $params['incremental'] ) || ( self::is_ignored( 'media' ) && ! isset( $params['parent_incremental'] ) ) ) {

			Logs::set_proc_stat(
				array(
					'log'      => 'warn',
					'status'   => 'in-process',
					'progress' => 35,
					'message'  => __( 'Media ignored.', 'everest-backup' ),
				)
			);

			return self::set_next( 'themes' );
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 35,
				'message'  => __( 'Listing media files', 'everest-backup' ),
			)
		);

		$files = Filesystem::init()->list_files( everest_backup_get_uploads_dir() );

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
						'progress' => round( $progress * 0.07 + 35, 2 ), // At the end, it is always going to be 42%
						'message'  => sprintf(
							__( 'Listing media files: %d%% completed', 'everest-backup' ),
							esc_html( $progress )
						),
						'detail'   => sprintf( __( 'Listing media file: %s', 'everest-backup' ), basename( $file ) ),
					)
				);

				$total_size += filesize( $file );

			}
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 42,
				'message'  => sprintf(
					__( 'Media files listed. Total files: %1$s [ %2$s ]', 'everest-backup' ),
					esc_html( $total_files ),
					esc_html( everest_backup_format_size( $total_size ) )
				),
			)
		);

		return self::set_next( 'themes' );
	}
}
