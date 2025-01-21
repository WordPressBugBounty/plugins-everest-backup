<?php
/**
 * Core class for new export feature. This class will initialize required exporting classes.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core;

use Everest_Backup\Core\Export\Contents;
use Everest_Backup\Core\Export\Database;
use Everest_Backup\Core\Export\Media;
use Everest_Backup\Core\Export\Plugins;
use Everest_Backup\Core\Export\Setup;
use Everest_Backup\Core\Export\Themes;
use Everest_Backup\Core\Export\Incremental;
use Everest_Backup\Core\Export\Wrapup;
use Everest_Backup\Logs;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class for new export feature. This class will initialize required exporting classes.
 *
 * @since 2.0.0
 */
class Export {

	/**
	 * Load file.
	 *
	 * @param string $current Current file.
	 */
	public static function load_file( $current ) {
		$path = EVEREST_BACKUP_CORE_DIR_PATH . "export/class-{$current}.php";

		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}

	/**
	 * Init export.
	 *
	 * @param array $params Params.
	 * @return void
	 */
	public static function init( $params = array() ) {

		$params  = $params ? $params : everest_backup_get_ajax_response( EVEREST_BACKUP_EXPORT_ACTION );
		$current = ! empty( $params['next'] ) ? $params['next'] : 'setup';

		$procstat = Logs::get_proc_stat();

		if ( ( isset( $procstat['task'] ) && 'cloud' === $procstat['task'] ) || ( isset( $procstat['status'] ) && ( 'done' === $procstat['status'] ) ) ) {
			everest_backup_send_json( $procstat );
		}

		self::load_file( $current );

		switch ( $current ) {
			case 'setup':
				Setup::init( $params );
				break;

			case 'database':
				Database::init( $params );
				break;

			case 'plugins':
				Plugins::init( $params );
				break;

			case 'media':
				Media::init( $params );
				break;

			case 'themes':
				Themes::init( $params );
				break;

			case 'content':
				Contents::init( $params );
				break;

			case 'incremental':
				Incremental::init( $params );
				break;

			case 'wrapup':
				Wrapup::init( $params );
				break;

			default:
				break;
		}

		$procstat = Logs::get_proc_stat();

		if ( get_transient( 'everest_backup_wp_cli_express' ) && defined( 'WP_CLI' ) && \WP_CLI ) {
			self::init( $procstat );
		}

		everest_backup_send_json( $procstat );
	}
}
