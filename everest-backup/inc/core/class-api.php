<?php
/**
 * Core file for api.
 *
 * @since 2.1.0
 */

namespace Everest_Backup\Core;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {

	protected static $controllers = array();

	/**
	 * Init api.
	 *
	 * @return void
	 */
	public static function init() {
		self::includes();

		$namespaces = apply_filters( 'everest_backup_api_namespaces', self::get_namespaces() );

		if ( is_array( $namespaces ) && ! empty( $namespaces ) ) {
			foreach ( $namespaces as $namespace => $controllers ) {
				if ( is_array( $controllers ) && ! empty( $controllers ) ) {
					foreach ( $controllers as $rest_base => $controller ) {
						self::$controllers[ $namespace ][ $rest_base ] = new $controller( $namespace, $rest_base );
						self::$controllers[ $namespace ][ $rest_base ]->register_routes();
					}
				}
			}
		}
	}

	protected static function includes() {
		require_once EVEREST_BACKUP_CORE_DIR_PATH . 'controllers/class-base.php';

		self::includes_v1_files();
	}

	protected static function includes_v1_files() {
		$files = array(
			'class-access-token-controller.php',
			'class-manual-backup-controller.php',
			'class-schedule-backup-controller.php',
		);

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				require_once EVEREST_BACKUP_CORE_DIR_PATH . 'controllers/v1/' . $file;
			}
		}
	}

	protected static function get_v1_controllers() {
		return array(
			'access-token'    => __NAMESPACE__ . '\\Controllers\\V1\\Access_Token_Controller',
			'manual-backup'   => __NAMESPACE__ . '\\Controllers\\V1\\Manual_Backup_Controller',
			'schedule-backup' => __NAMESPACE__ . '\\Controllers\\V1\\Schedule_Backup_Controller',
		);
	}

	protected static function get_namespaces() {
		return array(
			'everest-backup/v1' => self::get_v1_controllers(),
		);
	}
}
