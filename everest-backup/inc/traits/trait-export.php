<?php
/**
 * Trait for core export.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Traits;

use Everest_Backup\Logs;
use Everest_Backup\Temp_Directory;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for core export.
 *
 * @since 2.0.0
 */
trait Export {

	private static $LISTFILENAME = 'ebwp-files.ebwplist';

	protected static $params;

	public static function init( $params ) {

		everest_backup_setup_environment();

		$disabled_functions = everest_backup_is_required_functions_enabled();

		if ( is_array( $disabled_functions ) ) {
			throw new \Exception( sprintf( 'Everest Backup required functions disabled: %s', implode( ', ', $disabled_functions ) ) );
		}

		self::$params = apply_filters( 'everest_backup_filter_backup_modules_params', $params );

		self::run();

		if ( ! get_transient( 'everest_backup_wp_cli_express' ) ) {
			$procstat = Logs::get_proc_stat();
			everest_backup_send_json( $procstat );
		}
		set_transient( 'everest_backup_wp_cli_express', true, 60 );
	}

	public static function writefile( $file, $content, $append = false ) {
		$path = everest_backup_current_request_storage_path( $file );
		return Temp_Directory::init()->add_to_temp( $path, $content, $append );
	}

	public static function readfile( $file ) {
		$path = everest_backup_current_request_storage_path( $file );
		if ( ! file_exists( $path ) ) {
			return;
		}
		return @file_get_contents( $path );
	}

	public static function addtolist( $filepathtolist ) {
		return self::writefile( self::$LISTFILENAME, "{$filepathtolist}\n", true );
	}

	public static function read_config( $field = null, $default = null ) {
		$content = self::readfile( 'ebwp-config.json' );
		$config  = $content ? json_decode( $content, true ) : array();

		if ( is_null( $field ) ) {
			return $config;
		}

		return isset( $config[ $field ] ) ? $config[ $field ] : $default;
	}

	public static function get_archive_name() {

		$fileinfo = self::read_config( 'FileInfo' );

		if ( ! empty( $fileinfo['filename'] ) ) {
			return $fileinfo['filename'];
		}

		$name_tag = ! empty( self::$params['custom_name_tag'] ) ? trim( self::$params['custom_name_tag'], '-' ) : site_url();

		$filename_block   = array();
		$filename_block[] = 'ebwp-';
		$filename_block[] = sanitize_title( preg_replace( '#^https?://#i', '', $name_tag ) );
		$filename_block[] = '-' . everest_backup_current_request_timestamp();
		$filename_block[] = '-' . everest_backup_current_request_id();

		$filename = implode( '', $filename_block );

		return "{$filename}.ebwp";
	}

	public static function get_archive_path() {
		$archive_name = self::get_archive_name();

		if ( ! $archive_name ) {
			return;
		}

		return wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . DIRECTORY_SEPARATOR . $archive_name );
	}

	public static function is_ignored( $module ) {
		if ( ! $module ) {
			return true;
		}

		$params = self::read_config( 'Params' );

		return isset( $params["ignore_{$module}"] ) ? absint( $params["ignore_{$module}"] ) : 0;
	}

	public static function set_next( $next, $subtask = null ) {
		$procstat = Logs::get_proc_stat();

		if ( isset( $procstat['log'] ) ) {
			unset( $procstat['log'] );
		}

		$procstat['next']    = $next;
		$procstat['subtask'] = $subtask;

		return Logs::set_proc_stat( $procstat, 0 );
	}

}
