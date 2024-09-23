<?php
/**
 * Restore class for uploads folder.
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
 * Restore class for uploads folder.
 *
 * @since 1.0.0
 */
class Restore_Uploads {

	use Restore;

	/**
	 * Start restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {
		$uploads = self::get_module_files( 'uploads' );

		if ( ! $uploads ) {
			return;
		}

		$info_message = __( 'Restoring media files.', 'everest-backup' );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 50,
				'message'  => $info_message,
			)
		);

		Logs::info( $info_message );

		$filesystem = self::$filesystem;

		$ms_blogs = self::$extract->get_temp_data( 'ms_blogs' );

		$storage_dir    = trailingslashit( self::$storage_dir );
		$wp_content_dir = trailingslashit( self::$wp_content_dir );

		if ( is_array( $ms_blogs ) && ! empty( $ms_blogs ) ) {

			$uploads_dir = wp_normalize_path( everest_backup_get_uploads_dir() );

			foreach ( $ms_blogs as $ms_blog_id => $ms_blog ) {
				switch_to_blog( $ms_blog_id );

				$blog_upload_dir = wp_normalize_path( everest_backup_get_uploads_dir() );

				if ( is_array( $uploads ) && ! empty( $uploads ) ) {
					foreach ( $uploads as $upload ) {

						$upload_to = str_replace( $storage_dir, $wp_content_dir, $upload );
						$upload_to = str_replace( $uploads_dir, $blog_upload_dir, $upload_to );

						$filesystem->move_file( $upload, $upload_to );
					}
				}

				restore_current_blog();

			}
		} else {
			if ( is_array( $uploads ) && ! empty( $uploads ) ) {
				foreach ( $uploads as $upload ) {
					$upload_to = str_replace( $storage_dir, $wp_content_dir, $upload );

					$filesystem->move_file( $upload, $upload_to );
				}
			}
		}

		Logs::info( __( 'Media files restored.', 'everest-backup' ) );

		everest_backup_log_memory_used();

	}
}
