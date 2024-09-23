<?php
/**
 * Core export Contents class file.
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

/**
 * Class Contents.
 */
class Contents {

	use Export;

	/**
	 * Run.
	 */
	private static function run() {

		if ( self::is_ignored( 'content' ) ) {

			Logs::set_proc_stat(
				array(
					'log'      => 'warn',
					'status'   => 'in-process',
					'progress' => 63,
					'message'  => __( 'Contents ignored.', 'everest-backup' ),
				)
			);

			return self::set_next( 'wrapup' );
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 63,
				'message'  => __( 'Listing content files', 'everest-backup' ),
			)
		);

		$files = Filesystem::init()->list_files( WP_CONTENT_DIR, everest_backup_get_excluded_folders() );

		$total_files = count( $files );
		$total_size  = 0;

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $index => $file ) {

				$count = $index + 1;

				if ( ! @is_readable( $file ) ) { // @phpcs:ignore
					continue;
				}

				self::addtolist( $file );

				$progress = ( $count / $total_files ) * 100;

				Logs::set_proc_stat(
					array(
						'status'   => 'in-process',
						'progress' => round( $progress * 0.07 + 63, 2 ),
						'message'  => sprintf(
							/* translators: */
							__( 'Listing content files: %d%% completed', 'everest-backup' ),
							esc_html( $progress )
						),
						/* translators: */
						'detail'   => sprintf( __( 'Listing content file: %s', 'everest-backup' ), basename( $file ) ),
					)
				);

				$total_size += filesize( $file );

			}
		}

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 70,
				'message'  => sprintf(
					/* translators: */
					__( 'Contents listed. Total files: %1$s [ %2$s ]', 'everest-backup' ),
					esc_html( $total_files ),
					esc_html( everest_backup_format_size( $total_size ) )
				),
			)
		);

		return self::set_next( 'wrapup' );
	}
}
