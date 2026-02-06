<?php
/**
 * Restore class for plugins folder.
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
 * Restore class for plugins folder.
 *
 * @since 1.0.0
 */
class Restore_Plugins {

	use Restore;

	/**
	 * Start restore.
	 *
	 * @since 1.0.0
	 */
	protected static function restore() {
		$plugins = self::get_module_files( 'plugins' );

		if ( ! $plugins ) {
			return;
		}

		$info_message = __( 'Restoring plugins.', 'everest-backup' );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 70,
				'message'  => $info_message,
			)
		);

		Logs::info( $info_message );

		$filesystem = self::$filesystem;

		if ( is_array( $plugins ) && ! empty( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				$upload_to = str_replace( self::$storage_dir, self::$wp_content_dir, $plugin );

				$filesystem->move_file( $plugin, $upload_to );
			}
		}

		wp_clean_plugins_cache();

		Logs::info( __( 'Plugins restored.', 'everest-backup' ) );

		everest_backup_log_memory_used();
	}
}
