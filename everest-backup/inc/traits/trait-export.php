<?php
/**
 * Trait for core export.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Traits;

use Everest_Backup\Logs;
use Everest_Backup\Temp_Directory;
use Exception;

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

	private static $REMOVELISTFILENAME = 'ebwp-files-remove.ebwplist';

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

	/**
	 * Writes a file to a temp directory.
	 *
	 * @param string $file    Filename.
	 * @param string $content Content to write.
	 * @param bool   $append  If true, append to the file.
	 */
	public static function writefile( $file, $content, $append = false ) {
		$path = everest_backup_current_request_storage_path( $file );
		return Temp_Directory::init()->add_to_temp( $path, $content, $append );
	}

	/**
	 * Reads a file from a temp directory.
	 *
	 * @param string $file File name.
	 * @return string|false File content or false if the file does not exist.
	 */
	public static function readfile( $file ) {
		$path = everest_backup_current_request_storage_path( $file );
		if ( ! file_exists( $path ) ) {
			return;
		}
		return @file_get_contents( $path );
	}

	/**
	 * Adds a file to the list of files to be included in the backup.
	 *
	 * @param string $filepathtolist File path to add to the list.
	 *
	 * @return bool True if the file was successfully added to the list.
	 */
	public static function addtolist( $filepathtolist ) {
		return self::writefile( self::$LISTFILENAME, "{$filepathtolist}\n", true );
	}

	/**
	 * Adds a file to the list of files to be removed from the backup.
	 *
	 * @param string $filepathtolist File path to add to the list.
	 *
	 * @return bool True if the file was successfully added to the list.
	 */
	public static function addtoremovelist( $filepathtolist ) {
		return self::writefile( self::$REMOVELISTFILENAME, "{$filepathtolist}\n", true );
	}

	/**
	 * Reads the configuration from the 'ebwp-config.json' file.
	 *
	 * @param string|null $field Optional. The specific field to retrieve from the configuration.
	 * @param mixed       $default Optional. The default value to return if the field is not set.
	 *
	 * @return array|mixed The entire configuration array if no field is specified,
	 *                     or the value of the specified field, or the default value if the field is not set.
	 */
	public static function read_config( $field = null, $default = null ) {
		$content = self::readfile( 'ebwp-config.json' );
		$config  = $content ? json_decode( $content, true ) : array();

		if ( is_null( $field ) ) {
			return $config;
		}

		return isset( $config[ $field ] ) ? $config[ $field ] : $default;
	}

	/**
	 * Generate a filename for the archive.
	 *
	 * The filename is composed of the following parts:
	 * - a prefix ('ebwp-'/'ebwpbuwa-')
	 * - a sanitized version of the site URL
	 * - a timestamp representing the current datetime
	 * - a unique identifier for the current request
	 * - the file extension
	 *
	 * @return string The generated filename.
	 */
	public static function get_archive_name() {
		$fileinfo = self::read_config( 'FileInfo' );

		if ( ! empty( $fileinfo['filename'] ) ) {
			return $fileinfo['filename'];
		}

		$name_tag = ! empty( self::$params['custom_name_tag'] ) ? trim( self::$params['custom_name_tag'], '-' ) : site_url();

		$filename_block = array();

		$settings                = everest_backup_get_settings();
		$schedule_backup_setting = $settings['schedule_backup'] ?? array();

		/**
		 * checking if admin is logged in coz transient occassionally remains after backup is terminated.
		 * All incremental backups are scheduled till date(2025-1-3).
		 */
		if ( everest_backup_pro_active() && ! current_user_can( 'manage_options' ) && ! empty( $schedule_backup_setting['set_incremental_backup'] ) && isset( self::$params['parent_incremental'] ) ) {
			$filename_block[] = 'ebwpbuwa-';
		} else {
			$filename_block[] = 'ebwp-';
		}
		$filename_block[] = sanitize_title( preg_replace( '#^https?://#i', '', $name_tag ) );
		$filename_block[] = '-' . everest_backup_current_request_timestamp();
		$filename_block[] = '-' . everest_backup_current_request_id();

		$filename = implode( '', $filename_block );

		return $filename . EVEREST_BACKUP_BACKUP_FILE_EXTENSION;
	}

	/**
	 * Return the full path of the archive file.
	 *
	 * @return string The full path of the archive file.
	 */
	public static function get_archive_path() {
		$archive_name = self::get_archive_name();

		if ( ! $archive_name ) {
			return;
		}

		return wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . DIRECTORY_SEPARATOR . $archive_name );
	}

	/**
	 * Checks if a module should be ignored in the current backup.
	 *
	 * @param string $module The module to check.
	 * @return int 1 if the module should be ignored, 0 otherwise.
	 */
	public static function is_ignored( $module ) {
		if ( ! $module ) {
			return true;
		}

		$params = self::read_config( 'Params' );

		if ( isset( $params['increment'] ) ) {
			return true;
		}

		return isset( $params[ "ignore_{$module}" ] ) ? absint( $params[ "ignore_{$module}" ] ) : 0;
	}

	/**
	 * When doing scheduled/incremental backup, create current file list file for next incremental backup.
	 */
	public static function create_current_backup_file_info( $filename ) {
		if ( isset( self::$params['parent_incremental'] ) || isset( self::$params['incremental'] ) ) {
			$inc_f = fopen( \EVEREST_BACKUP_CURRENT_BACKUP_FILE_INFO_PATH, 'wb' );

			fwrite( $inc_f, $filename . PHP_EOL ); // write file name on first line.

			fclose( $inc_f );
		}
	}

	/**
	 * When doing scheduled/incremental backup, save current file list for next incremental backup.
	 */
	public static function put_current_backup_file_info( $files ) {
		if ( get_transient( 'everest_backup_doing_scheduled_backup' ) ) {
			if ( is_array( $files ) && ! empty( $files ) ) {
				$inc_f = fopen( \EVEREST_BACKUP_CURRENT_BACKUP_FILE_INFO_PATH, 'ab' );

				foreach ( $files as $file ) {
					$line = filemtime( $file ) . ' - ' . $file . PHP_EOL;
					fwrite( $inc_f, $line );
				}
				fclose( $inc_f );
			}
		}
	}

	/**
	 * Read last backup file list for incremental backup.
	 */
	public static function read_last_backup_file_info() {
		$files = array();
		try {
			$inc_f = fopen( \EVEREST_BACKUP_LAST_BACKUP_FILE_INFO_PATH, 'rb' );
			if ( ! $inc_f ) {
				return false;
			}
			$line = fgets( $inc_f ); // skip first line, is last backup file name.
			while ( false !== ( $line = fgets( $inc_f ) ) ) {
				$explode = explode( ' - ', $line, 2 );
				if ( count( $explode ) === 2 ) {
					$files[ trim( $explode[1] ) ] = (int) $explode[0];
				}
			}
			fclose( $inc_f );
		} catch ( Exception $_ ) {
			return false;
		}
		return $files;
	}

	/**
	 * Read last backup file name for incremental backup.
	 *
	 * @return string
	 */
	public static function read_last_backup_file_name() {
		try {
			$inc_f = fopen( \EVEREST_BACKUP_LAST_BACKUP_FILE_INFO_PATH, 'rb' );
			if ( ! $inc_f ) {
				return false;
			}
			$line = trim( fgets( $inc_f ) );
			fclose( $inc_f );
			return $line;
		} catch ( Exception $_ ) {
			return false;
		}
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
