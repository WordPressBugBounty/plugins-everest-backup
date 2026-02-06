<?php
/**
 * Trait for restore module classes.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Traits;

use Everest_Backup\Filesystem;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for restore module classes.
 *
 * @since 1.0.0
 */
trait Restore {

	/**
	 * Normalized path to the wp-content directory with trailing slash.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $wp_content_dir;

	/**
	 * Returns path to storage directory with current export session uniqid.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private static $storage_dir;

	/**
	 * Filesystem class object.
	 *
	 * @var \Everest_Backup\Filesystem
	 * @since 1.0.0
	 */
	private static $filesystem;

	/**
	 * Extract class object.
	 *
	 * @var \Everest_Backup\Extract
	 * @since 1.0.0
	 */
	private static $extract;

	/**
	 * Array of files stored in root of zip.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $root;

	/**
	 * Array of files stored in nested folder of zip.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $nested;

	/**
	 * Init restore.
	 *
	 * @param \Everest_Backup\Extract $extract Extract class object.
	 * @return void
	 * @since 1.0.0
	 */
	public static function init( $extract ) {
		$filesystem = Filesystem::init();

		self::$wp_content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		self::$storage_dir    = $extract->get_storage_dir();

		self::$extract    = $extract;
		self::$filesystem = $filesystem;

		$list = $extract->get_list();

		self::$root   = ! empty( $list['root'] ) ? $list['root'] : array();
		self::$nested = ! empty( $list['nested'] ) ? $list['nested'] : array();

		if ( method_exists( __CLASS__, 'before_restore' ) ) {
			self::before_restore();
		}

		self::restore();

		if ( method_exists( __CLASS__, 'after_restore' ) ) {
			self::after_restore();
		}
	}

	/**
	 * Returns find replace values.
	 *
	 * @return array
	 * @since 1.1.4
	 */
	protected static function get_find_replace() {

		static $find_replace = array();

		if ( $find_replace ) {
			return $find_replace;
		}

		$config_data = self::get_config_data();

		$old_site_url = str_replace( array( 'http://', 'https://' ), '', $config_data['SiteURL'] );
		$old_home_url = str_replace( array( 'http://', 'https://' ), '', $config_data['HomeURL'] );

		$new_site_url = str_replace( array( 'http://', 'https://' ), '', site_url() );
		$new_home_url = str_replace( array( 'http://', 'https://' ), '', home_url() );

		$old_upload_dir = $config_data['WordPress']['UploadsDIR'];
		$new_upload_dir = everest_backup_get_uploads_dir();

		$old_upload_url = str_replace( array( 'http://', 'https://' ), '', $config_data['WordPress']['UploadsURL'] );
		$new_upload_url = str_replace( array( 'http://', 'https://' ), '', everest_backup_get_uploads_url() );

		$old_content_dir = $config_data['WordPress']['Content'];
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

	/**
	 * Fix using find and replace inside files just like database.
	 *
	 * @param string $filepath
	 * @return string
	 * @since 1.1.4
	 */
	protected static function normalize_file_contents( $filepath ) {

		if ( false !== strpos( $filepath, LANGDIR ) ) {
			// Bail if we are inside language directory.
			return;
		}

		if ( ! is_file( $filepath ) ) {
			return;
		}

		return @file_put_contents( $filepath, strtr( @file_get_contents( $filepath ), self::get_find_replace() ) );
	}

	/**
	 * Returns config data for modules. It is only effective if it is called after config module.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function get_config_data() {
		return self::$extract->get_temp_data( 'config_data' );
	}

	/**
	 * Check if current uploaded package is from single site or multisite.
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	protected static function is_package_multisite() {
		$config_data = self::get_config_data();

		return ! empty( $config_data['WordPress']['Multisite'] );
	}


	/**
	 * Returns file path of the matching filename from the array
	 *
	 * @param string $filename Name of the file to look into the $files.
	 * @param array  $files List of file.
	 * @param bool   $check [Optional] Check if file exists or not.
	 * @return string
	 * @since 1.0.0
	 */
	protected static function get_filepath_from_list( $filename, $files, $check = true ) {
		if ( ! $filename || ! $files ) {
			return;
		}

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( false !== strpos( $file, $filename ) ) {

					if ( $check ) {
						return file_exists( $file ) ? $file : '';
					}

					return $file;
				}
			}
		}
	}

	/**
	 * Returns files from module/folder set inside Everest_Backup\Traits\Restore::$nested.
	 *
	 * @param string $module Module or the folder name from wp-content.
	 * @return array
	 * @since 1.0.0
	 */
	protected static function get_module_files( $module ) {
		$nested = self::$nested;

		if ( ! isset( $nested[ $module ] ) ) {
			return array();
		}

		return $nested[ $module ];
	}
}
