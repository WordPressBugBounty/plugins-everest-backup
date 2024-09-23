<?php
/**
 * Backup class for plugins folder.
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
 * Backup class for plugins folder.
 *
 * @since 1.0.0
 */
class Backup_Plugins {

	use Backup;

	/**
	 * Filtered plugin files.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $plugin_files = array();

	/**
	 * List of plugins to exclude.
	 *
	 * @return array
	 * @since 1.0.0
	 */
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

	/**
	 * Scripts to run before adding files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_addfiles() {
		Logs::info( __( 'Adding plugins files.', 'everest-backup' ) );

		$plugins_dir = WP_PLUGIN_DIR;

		if ( empty( $plugins_dir ) ) {
			return;
		}

		$excluded_plugins   = self::excluded_plugins();
		self::$plugin_files = self::$filesystem->list_files( $plugins_dir, $excluded_plugins );

	}

	/**
	 * List all the files from wp-content/plugins folder.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function files() {

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 42.84,
				'message'  => __( 'Listing plugins', 'everest-backup' ),
			)
		);

		return self::$plugin_files;
	}

	/**
	 * Scripts to run after adding files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_addfiles() {
		Logs::info( __( 'Plugins files added successfully.', 'everest-backup' ) );
	}
}
