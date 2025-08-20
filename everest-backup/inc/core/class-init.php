<?php
/**
 * =============================================================================
 *
 * Initialize our all the functionalities from the core folder.
 * Since Everest Backup version 2.0.0, we have changed our core architecture.
 * All the new codes and architecture will be inside core folder.
 *
 * =============================================================================
 *
 * @since 2.0.0
 * @package Everest_Backup
 */

namespace Everest_Backup\Core;

use Everest_Backup\Traits\Singleton;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core directory initializer.
 *
 * @since 2.0.0
 */
class Init {

	use Singleton;

	/**
	 * Core path.
	 *
	 * @var $core_path
	 */
	protected $core_path;

	/**
	 * Init class.
	 */
	public function __construct() {
		$this->core_path = plugin_dir_path( __FILE__ );

		$this->constants();
		$this->includes();
		$this->hooks();
	}

	/**
	 * Define constants for core directory.
	 *
	 * @return void
	 */
	protected function constants() {

		if ( ! defined( 'EVEREST_BACKUP_CORE_DIR_PATH' ) ) {

			/**
			 * Core directory path.
			 */
			define( 'EVEREST_BACKUP_CORE_DIR_PATH', $this->core_path );
		}
	}

	/**
	 * Include core directory files.
	 *
	 * @return void
	 */
	protected function includes() {
		$files = array(
			'class-archiver.php',
			'class-archiver-v2.php',
			'class-export.php',
			'class-import.php',
			'class-api.php',
		);

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				require_once EVEREST_BACKUP_CORE_DIR_PATH . $file;
			}
		}
	}

	/**
	 * Initialize required hooks.
	 *
	 * @return void
	 */
	protected function hooks() {
		add_action( 'wp_ajax_' . EVEREST_BACKUP_EXPORT_ACTION, '\Everest_Backup\Core\Export::init' );
		add_action( 'wp_ajax_' . EVEREST_BACKUP_IMPORT_ACTION, '\Everest_Backup\Core\Import::init' );
		add_action(
			'wp_ajax_' . EVEREST_BACKUP_PROCESS_RUNNING,
			function () {
				echo wp_json_encode( array( 'process_already_running' => everest_backup_process_running_currently() ) );
				die;
			}
		);
		add_action( 'rest_api_init', '\Everest_Backup\Core\API::init' );
	}
}

Init::init();
