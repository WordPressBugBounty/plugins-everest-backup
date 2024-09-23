<?php
/**
 * Backup class for handling backup of wp-content folder. It backup other files except plugins, themes and uploads.
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
 * Backup class for handling backup of wp-content folder. It backup all the files except plugins, themes and uploads.
 *
 * @since 1.0.0
 */
class Backup_Content {

	use Backup;

	/**
	 * FIltered wp-contents files for backup.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $contents = array();

	/**
	 * Excluded folders from wp-content directory.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function excluded_folders() {
		return everest_backup_get_excluded_folders();
	}

	/**
	 * Scripts to run before adding files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_addfiles() {
		Logs::info( __( 'Sorting some remaining files from wp-content folders.', 'everest-backup' ) );

		$content_dir = WP_CONTENT_DIR;

		if ( empty( $content_dir ) ) {
			return;
		}

		$excluded_folders = self::excluded_folders();
		self::$contents   = self::$filesystem->list_files( $content_dir, $excluded_folders );

	}

	/**
	 * List all the remaining files from wp-content folder.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function files() {

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 49.98,
				'message'  => __( 'Listing wp-content files', 'everest-backup' ),
			)
		);

		return self::$contents;
	}

}
