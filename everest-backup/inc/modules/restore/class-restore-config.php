<?php
/**
 * Restore class for config.
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
 * Restore class for config.
 *
 * @since 1.0.0
 */
class Restore_Config {

	use Restore;

	/**
	 * Path to the backup config file.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $config_file = '';

	/**
	 * Extracted data from the config.json file.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $config = array();

	/**
	 * Scripts to run before starting restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function before_restore() {

		$info_message = __( 'Reading config file.', 'everest-backup' );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 10,
				'message'  => $info_message,
			)
		);

		Logs::info( $info_message );

		$root_files = (array) self::$root;

		$config_file = self::get_filepath_from_list( EVEREST_BACKUP_CONFIG_FILENAME, $root_files );

		$config_content = is_file( $config_file ) ? self::$filesystem->get_file_content( $config_file ) : ''; // @phpcs:ignore

		if ( ! $config_content ) {
			$message = __( 'Config file is either empty or does not exist.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		$config = $config_content ? json_decode( $config_content, true ) : array();

		$list = self::$extract->get_list();

		$ignored = array();

		$ignorable_dirs = array(
			'plugins',
			'themes',
			'uploads',
		);

		if ( is_array( $ignorable_dirs ) && ! empty( $ignorable_dirs ) ) {
			foreach ( $ignorable_dirs as $ignorable_dir ) {
				if ( empty( $list['nested'][ $ignorable_dir ] ) ) {
					$ignored[ $ignorable_dir ] = true;

					switch ( $ignorable_dir ) {
						case 'plugins':
							$config ['ActivePlugins'] = get_option( 'active_plugins', array() );
							break;

						case 'themes':
							$config['Template']   = get_option( 'template' );
							$config['Stylesheet'] = get_option( 'stylesheet' );
							break;

						default:
							break;
					}
				}
			}
		}

		$config['ignored'] = $ignored;

		self::$extract->set_temp_data( 'config_data', $config );

		self::$config_file = $config_file;
		self::$config      = $config;

	}

	/**
	 * Restore htaccess data.
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.0.2 Ignored restoring htaccess file.
	 */
	private static function htaccess() {

		if ( is_multisite() ) {
			return;
		}

		if ( empty( self::$config['Server']['.htaccess'] ) ) {
			return;
		}

		$htaccess = EVEREST_BACKUP_HTACCESS_PATH;

		$htaccess_content = everest_backup_hex2str( self::$config['Server']['.htaccess'] );

		unlink( $htaccess );

		self::$filesystem->writefile( $htaccess, $htaccess_content );
	}

	/**
	 * Start restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {

		everest_backup_log_memory_used();

	}

	/**
	 * Scripts to run before starting restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function after_restore() {
		if ( is_file( self::$config_file ) ) {
			unlink( self::$config_file );
		}
	}
}
