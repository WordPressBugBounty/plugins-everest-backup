<?php
/**
 * Trait for core import.
 *
 * @package Everest_Backup
 * @since 2.0.0
 */

namespace Everest_Backup\Traits;

use Everest_Backup\Logs;
use Everest_Backup\Temp_Directory;

/**
 * Trait for core import.
 *
 * @since 2.0.0
 */
trait Import {

	protected static $params;

	public static function init( $params ) {

		everest_backup_setup_environment();

		$disabled_functions = everest_backup_is_required_functions_enabled();

		if ( is_array( $disabled_functions ) ) {
			throw new \Exception( sprintf( 'Everest Backup required functions disabled: %s', implode( ', ', $disabled_functions ) ) );
		}

		self::$params = apply_filters( 'everest_backup_filter_restore_params', $params );

		self::run( self::$params );

		if ( ! get_transient( 'everest_backup_wp_cli_express' ) ) {
			everest_backup_send_json( self::$params );
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

	public static function get_metadata() {
		static $metadata;

		if ( ! $metadata ) {
			$json = self::readfile( 'ebwp-metadata.json' );

			if ( ! $json ) {
				return array();
			}

			$metadata = json_decode( $json, true );
		}

		return $metadata;
	}

	public static function get_find_replace() {

		$metadata = self::get_metadata();

		if ( empty( $metadata['config'] ) ) {
			return array();
		}

		$find_replace = array();

		$old_site_url = str_replace( array( 'http://', 'https://' ), '', $metadata['config']['SiteURL'] );
		$old_home_url = str_replace( array( 'http://', 'https://' ), '', $metadata['config']['HomeURL'] );

		$new_site_url = str_replace( array( 'http://', 'https://' ), '', site_url() );
		$new_home_url = str_replace( array( 'http://', 'https://' ), '', home_url() );

		$old_upload_dir = $metadata['config']['WordPress']['UploadsDIR'];
		$new_upload_dir = everest_backup_get_uploads_dir();

		$old_upload_url = str_replace( array( 'http://', 'https://' ), '', $metadata['config']['WordPress']['UploadsURL'] );
		$new_upload_url = str_replace( array( 'http://', 'https://' ), '', everest_backup_get_uploads_url() );

		$old_content_dir = $metadata['config']['WordPress']['Content'];
		$new_content_dir = WP_CONTENT_DIR;

		$old_content_url = str_replace( array( 'http://', 'https://' ), '', str_replace( '/uploads/', '', $old_upload_dir ) );
		$new_content_url = str_replace( array( 'http://', 'https://' ), '', WP_CONTENT_URL );

		if ( ( ! is_ssl() || everest_backup_is_localhost() ) ) {

			/**
			 * Fixes for ssl issue in localhosts.
			 */
			$find_replace['https://'] = 'http://';
		}

		$find_replace[ $old_site_url ]    = $new_site_url;
		$find_replace[ $old_home_url ]    = $new_home_url;
		$find_replace[ $old_upload_dir ]  = $new_upload_dir;
		$find_replace[ $old_upload_url ]  = $new_upload_url;
		$find_replace[ $old_content_dir ] = $new_content_dir;
		$find_replace[ $old_content_url ] = $new_content_url;

		return $find_replace;

	}

	public static function set_next( $next, $subtask = null ) {
		$procstat = Logs::get_proc_stat();

		if ( isset( $procstat['log'] ) ) {
			unset( $procstat['log'] );
		}

		$procstat['next']    = $next;
		$procstat['subtask'] = $subtask;

		return Logs::set_proc_stat( $procstat );
	}
}
