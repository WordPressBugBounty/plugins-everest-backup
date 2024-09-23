<?php
/**
 * Backup class for uploads folder.
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
 * Backup class for uploads folder.
 *
 * @since 1.0.0
 */
class Backup_Uploads {

	use Backup;

	/**
	 * List all the files from wp-content/uploads folder.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function files() {

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 28.56,
				'message'  => __( 'Listing media files', 'everest-backup' ),
			)
		);

		Logs::info( __( 'Adding media files.', 'everest-backup' ) );

		$uploads_dir = everest_backup_get_uploads_dir();

		if ( empty( $uploads_dir ) ) {
			return;
		}

		return self::$filesystem->list_files( $uploads_dir );
	}

	/**
	 * Scripts to run after files added to the list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_addfiles() {
		$files = self::$files;
		if ( is_array( $files ) && ! empty( $files ) ) {
			Logs::info( __( 'Media files added successfully.', 'everest-backup' ) );
		} else {
			Logs::warn( __( 'Unable to add media files.', 'everest-backup' ) );
		}
	}

}
