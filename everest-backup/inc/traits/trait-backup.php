<?php
/**
 * Trait for backup module classes.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Traits;

use Everest_Backup\Filesystem;
use Everest_Backup\Logs;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for backup module classes.
 *
 * @since 1.0.0
 */
trait Backup {

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
	 * Compress class object.
	 *
	 * @var \Everest_Backup\Compress
	 * @since 1.0.0
	 */
	private static $compress;

	/**
	 * Array of files from current module.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private static $files;

	/**
	 * Returns the name of current class without namespace.
	 *
	 * @return string
	 */
	protected static function get_current_class() {
		$classname = __CLASS__;
		return ( substr( $classname, strrpos( $classname, '\\' ) + 1 ) );
	}

	/**
	 * Checks if current module is ignored or not.
	 *
	 * @param array $params Parameters passed to `\Everest_Backup\Compress` class.
	 * @return bool
	 * @since 1.0.0
	 */
	protected static function ignore_current_module( $params ) {
		$current_class = self::get_current_class();

		$checks = array(
			'Backup_Plugins'  => 'ignore_plugins',
			'Backup_Themes'   => 'ignore_themes',
			'Backup_Uploads'  => 'ignore_media',
			'Backup_Database' => 'ignore_database',
			'Backup_Content'  => 'ignore_content', // @since 1.1.2
		);

		if ( is_array( $checks ) && ! empty( $checks ) ) {
			foreach ( $checks as $class => $field ) {

				if ( empty( $params[ $field ] ) ) {
					continue;
				}

				if ( $class === $current_class ) {

					/* translators: Here, %s is the name of current module. */
					Logs::warn( sprintf( __( '%s is ignored.', 'everest-backup' ), str_replace( 'Backup_', '', $class ) ) );

					return $params[ $field ];
				}
			}
		}
	}

	/**
	 * Calculates current module size and sets infostat.
	 *
	 * @param string $files Full path to file.
	 * @return void
	 */
	protected static function calculate_module_size( $files ) {

		if ( ! $files ) {
			return;
		}

		$class = self::get_current_class();

		$module = strtolower( str_replace( 'Backup_', '', $class ) );

		$key = "{$module}_size";

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_readable( $file ) && is_file( $file ) ) {
					$size = absint( Logs::get_infostat( $key, 0 ) );

					$size += filesize( $file );

					Logs::set_infostat( $key, $size );
				}
			}
		}

	}

	/**
	 * Init backup.
	 *
	 * @param \Everest_Backup\Compress $compress Compress class object.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init( $compress ) {

		$params = $compress->get_params();

		$ignored = self::ignore_current_module( $params );

		if ( $ignored ) {
			return;
		}

		if ( everest_backup_has_aborted() ) {
			$message = __( 'Process aborted.', 'everest-backup' );
			Logs::error( $message );
			wp_send_json_error( $message );
			die;
		}

		$filesystem = Filesystem::init();

		self::$compress   = $compress;
		self::$filesystem = $filesystem;

		$storage_dir = $compress->get_storage_dir();

		$filesystem->mkdir_p( $storage_dir );

		self::$storage_dir = $storage_dir;

		if ( method_exists( __CLASS__, 'before_addfiles' ) ) {
			self::before_addfiles();
		}

		$files       = self::files();
		self::$files = $files;

		self::calculate_module_size( $compress->addfiles( $files ) );

		if ( method_exists( __CLASS__, 'after_addfiles' ) ) {
			self::after_addfiles();
		}

	}

}
