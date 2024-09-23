<?php
/**
 * Core class for new import feature. This class will initialize required importing classes.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core;

use Everest_Backup\Core\Import\Wrapup;
use Everest_Backup\Core\Import\Check;
use Everest_Backup\Core\Import\Extraction;
use Everest_Backup\Logs;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class for new import feature. This class will initialize required importing classes.
 *
 * @since 2.0.0
 */
class Import {

	/**
	 * Load file.
	 *
	 * @param string $current Current file.
	 */
	public static function load_file( $current ) {
		$path = EVEREST_BACKUP_CORE_DIR_PATH . "import/class-{$current}.php";

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Init import.
	 *
	 * @param  array $params Params.
	 * @return void
	 */
	public static function init( $params = array() ) {

		if ( ! everest_backup_doing_clone() ) {
			if ( everest_backup_doing_rollback() ) {
				Logs::init( 'rollback' );
			} else {
				Logs::init( 'restore' );
			}
		} else {
			Logs::init( 'clone' );
		}

		$params  = $params ? $params : everest_backup_get_ajax_response( EVEREST_BACKUP_IMPORT_ACTION );
		$current = ! empty( $params['next'] ) ? $params['next'] : 'check';

		self::load_file( $current );

		switch ( $current ) {
			case 'check':
				Check::init( $params );
				break;

			case 'extraction':
				Extraction::init( $params );
				break;

			default:
				Wrapup::init( $params );
				break;
		}

		if ( get_transient( 'everest_backup_wp_cli_express' ) && ( defined( 'WP_CLI' ) && \WP_CLI ) ) {
			$procstat = Logs::get_proc_stat();
			self::init( $procstat );
		}

		if ( get_transient( 'everest_backup_wp_cli_express' ) && ( defined( 'WP_CLI' ) && \WP_CLI ) ) {
			\WP_CLI::log( 'Complete.' );
		} else {
			everest_backup_send_json();
		}
	}
}
