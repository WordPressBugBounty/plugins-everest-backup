<?php
/**
 * Restore class for other remaining files inside wp-content folder or package root.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Logs;
use Everest_Backup\Traits\Restore;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restore class for other remaining files inside wp-content folder or package root.
 *
 * @since 1.0.0
 */
class Restore_Content {

	use Restore;

	/**
	 * Restore other folders from wp-content.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function restore_content() {
		$contents = self::get_module_files( 'content' );

		if ( ! $contents ) {
			return;
		}

		$filesystem = self::$filesystem;

		if ( is_array( $contents ) && ! empty( $contents ) ) {
			foreach ( $contents as $content ) {
				$upload_to = wp_normalize_path( str_replace( self::$storage_dir, self::$wp_content_dir, $content ) );

				if ( $filesystem->move_file( $content, $upload_to ) ) {
					self::normalize_file_contents( $upload_to );
				}
			}
		}
	}

	/**
	 * Restore remaining files in package root.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private static function restore_content_root() {
		$root_files = self::$root;

		if ( ! $root_files ) {
			return;
		}

		$filesystem = self::$filesystem;

		if ( is_array( $root_files ) && ! empty( $root_files ) ) {
			foreach ( $root_files as $content ) {
				$upload_to = str_replace( self::$storage_dir, self::$wp_content_dir, $content );

				if ( 'ebwp-tags.json' === wp_basename( $content ) ) {
					continue;
				}

				if ( $filesystem->move_file( $content, $upload_to ) ) {
					self::normalize_file_contents( $upload_to );
				}
			}
		}
	}

	/**
	 * Start restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {

		$info_message = __( 'Restoring wp-content folder.', 'everest-backup' );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 80,
				'message'  => $info_message,
			)
		);

		Logs::info( $info_message );

		self::restore_content();
		self::restore_content_root();

		everest_backup_log_memory_used();

		Logs::info( __( 'Restored wp-content folder.', 'everest-backup' ) );
	}
}
