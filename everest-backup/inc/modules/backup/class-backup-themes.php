<?php
/**
 * Backup class for themes folder.
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
 * Backup class for themes folder.
 *
 * @since 1.0.0
 */
class Backup_Themes {

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
				'progress' => 35.7,
				'message'  => __( 'Listing themes', 'everest-backup' ),
			)
		);

		Logs::info( __( 'Adding themes files.', 'everest-backup' ) );

		$themes_dir = WP_CONTENT_DIR . '/themes/';

		if ( empty( $themes_dir ) ) {
			return;
		}

		return self::$filesystem->list_files( $themes_dir );
	}

	/**
	 * Script to run after files added to the list.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_addfiles() {
		$files = self::$files;
		if ( is_array( $files ) && ! empty( $files ) ) {
			Logs::info( __( 'Themes files added successfully.', 'everest-backup' ) );
		} else {
			Logs::warn( __( 'Unable to add themes files.', 'everest-backup' ) );
		}
	}
}
