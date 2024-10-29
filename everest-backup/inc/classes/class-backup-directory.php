<?php
/**
 * Handles the backup storage directory "ebwp-backups".
 *
 * @package everest-backup
 */

namespace Everest_Backup;

use Everest_Backup\Traits\Singleton;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the backup storage directory "ebwp-backups".
 *
 * @since 1.0.0
 */
class Backup_Directory {

	use Singleton;

	/**
	 * Directory path to backups folder.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $backup_directory_path = EVEREST_BACKUP_BACKUP_DIR_PATH;

	/**
	 * Cached the backup files temporarily.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $cached_backups = array();

	/**
	 * File system class object.
	 *
	 * @var Filesystem
	 * @since 1.0.0
	 */
	private $filesystem;

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->filesystem = Filesystem::init();
	}

	/**
	 * Creates debug file and sets debug enable/disable on demand.
	 *
	 * @return void
	 * @since 1.1.6
	 */
	public function force_debug( $enable = false ) {

		$file = wp_normalize_path( $this->backup_directory_path . '/DEBUGMODEON' );

		if ( $enable ) {
			return $this->filesystem->writefile( $file, '' );
		} else {
			if ( file_exists( $file ) ) {
				return $this->filesystem->delete( $file );
			}
		}
	}

	/**
	 * Create backup directory and its required security files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create( $regenerate = false ) {

		if ( $regenerate ) {
			$files = $this->get_files();
			if ( is_array( $files ) && ! empty( $files ) ) {
				foreach ( $files as $file ) {
					if ( EVEREST_BACKUP_BACKUP_FILE_EXTENSION === '.' . pathinfo( $file, PATHINFO_EXTENSION ) ) {
						continue;
					}

					wp_delete_file( $file );
				}
			}
		}

		if ( $this->is_backup_dir_exists() && $this->is_security_files_valid() ) {
			return;
		}

		$this->create_backup_dir();
		$this->create_security_files();
	}

	/**
	 * Returns true if backup directory is created and exists.
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	public function is_backup_dir_exists() {
		return $this->filesystem->is_dir( $this->backup_directory_path );
	}

	/**
	 * Checks if all security files exists in backup directory.
	 *
	 * @return boolean
	 * @since 1.0.9
	 */
	public function is_security_files_valid() {

		/**
		 * Hook: everest_backup_filter_security_files_whitelists
		 *
		 * @since 1.1.1
		 */
		$whitelists = apply_filters(
			'everest_backup_filter_security_files_whitelists',
			array(
				'.PROCSTAT',
				'.LOCKFILE',
			)
		);

		$backup_directory_path = $this->backup_directory_path;
		$files_and_contents    = $this->get_security_files();

		if ( is_array( $files_and_contents ) && ! empty( $files_and_contents ) ) {
			foreach ( $files_and_contents as $filename => $content ) {
				$file = wp_normalize_path( $backup_directory_path . '/' . $filename );
				if ( ! file_exists( $file ) ) {

					if ( in_array( $filename, $whitelists, true ) ) {
						continue;
					}

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Create backup directory.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function create_backup_dir() {
		$this->filesystem->mkdir_p( $this->backup_directory_path );
	}

	/**
	 * Returns the array list of backups in ascending or descending order.
	 *
	 * @param string $order Accepts: `asc` or `desc`.
	 * @return array
	 */
	public function get_backups_by_order( $order = 'asc' ) {
		$backups = $this->get_backups( true );

		if ( ! $backups ) {
			return array();
		}

		$sorted = usort(
			$backups,
			function( $a, $b ) use ( $order ) {
				$time_a = filemtime( $a );
				$time_b = filemtime( $b );
				return 'asc' === $order ? strcmp( $time_b, $time_a ) : strcmp( $time_a, $time_b );
			}
		);

		return $sorted ? $backups : array();
	}

	/**
	 * Returns the path to the latest backup file in the backup directory.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_latest_backup() {
		$backups = $this->get_backups_by_order();

		return ! empty( $backups[0] ) ? $backups[0] : '';
	}

	/**
	 * Returns the array of backup files older than `$days`.
	 *
	 * @param int $days Number of days.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_backups_older_than( $days ) {

		if ( ! $days ) {
			return;
		}

		$now = time();

		$backups = $this->get_backups();

		$older_backups = array();

		if ( is_array( $backups ) && ! empty( $backups ) ) {
			foreach ( $backups as $backup ) {
				if ( empty( $backup['time'] ) ) {
					continue;
				}

				$diff = (int) abs( $backup['time'] - $now );

				$backup_days = round( $diff / DAY_IN_SECONDS );

				if ( $backup_days <= 1 ) {
					$backup_days = 1;
				}

				if ( $days >= $backup_days ) {
					continue;
				}

				$older_backups[] = $backup;
			}
		}

		return $older_backups;

	}

	/**
	 * List all the files from the backup directory.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_files() {

		if ( ! is_dir( $this->backup_directory_path ) ) {
			return array();
		}

		$files = $this->cached_backups;

		if ( ! $files ) {
			$files = $this->filesystem->list_files_all( $this->backup_directory_path );

			$this->cached_backups = $files;
		}

		return $files;
	}

	/**
	 * Returns the array of previous backup files from backup directory.
	 *
	 * @param bool $files_only If passed true then it returns array of files only.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_backups( $files_only = false ) {

		$backups = array();

		/**
		 * Bail if backup directory is not created yet.
		 */
		if ( ! $this->is_backup_dir_exists() ) {
			return $backups;
		}

		$ext_type = EVEREST_BACKUP_BACKUP_FILE_EXTENSION;
		$files    = $this->get_files();

		/**
		 * Filter out non backup files.
		 */
		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				$ext = $this->filesystem->get_file_extension( $file );

				if ( ".{$ext}" !== $ext_type ) {
					continue;
				}

				$backups[] = $file;
			}
		}

		if ( ! $backups ) {
			return $backups;
		}

		if ( $files_only ) {
			return $backups;
		}

		$backups_data = array();

		if ( is_array( $backups ) && ! empty( $backups ) ) {
			foreach ( $backups as $backup ) {
				$backups_data[] = everest_backup_get_backup_file_info( $backup );
			}
		}

		return $backups_data;

	}

	/**
	 * Returns an array of files not related to backup directory.
	 *
	 * @param int $days As Days, pass 0 if you don't want to exclude latest files.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_misc_files( $days = 0 ) {

		$misc_files = array();

		$ext_type = EVEREST_BACKUP_BACKUP_FILE_EXTENSION;
		$files    = $this->get_files();

		$security_files = array_keys( $this->get_security_files() );

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {

				$fileinfo = pathinfo( $file );

				$basename  = $fileinfo['basename'];
				$extension = ! empty( $fileinfo['extension'] ) ? $fileinfo['extension'] : '';

				// Ignore default security files.
				if ( in_array( $basename, $security_files, true ) ) {
					continue;
				}

				if ( ! $extension ) {
					continue;
				}

				// Ignore backup file.
				if ( ".{$extension}" === $ext_type ) {
					continue;
				}

				/**
				 * Filter: `everest_backup_is_not_misc_file`.
				 * This filter hook can be used to shortcircuit miscellaneous file listing.
				 * Return boolean true to ignore the current $file as miscellaneous file.
				 *
				 * @param string $file Full path to file.
				 * @since 1.1.1
				 */
				if ( true === apply_filters( 'everest_backup_is_not_misc_file', $file ) ) {
					continue;
				}

				$misc_files[] = $file;
			}
		}

		if ( ! $days ) {
			return $misc_files;
		}

		$now = time();

		$old_misc_files = array();

		if ( is_array( $misc_files ) && ! empty( $misc_files ) ) {
			foreach ( $misc_files as $misc_file ) {
				$backup = everest_backup_get_backup_file_info( $misc_file );

				if ( empty( $backup['time'] ) ) {
					continue;
				}

				$diff = (int) abs( $backup['time'] - $now );

				$backup_days = round( $diff / DAY_IN_SECONDS );

				if ( $backup_days <= 1 ) {
					$backup_days = 1;
				}

				if ( $days >= $backup_days ) {
					continue;
				}

				$old_misc_files[] = $misc_file;
			}
		}

		return $old_misc_files;

	}

	/**
	 * Returns array of security files with file contents.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function get_security_files() {
		$files_and_contents = array(
			'.htaccess'                              => $this->htaccess_content(),
			'index.php'                              => '<?php',
			'index.html'                             => '',
			'.PROCSTAT'                               => '{}',
			'.LOCKFILE'                               => '',
			basename( EVEREST_BACKUP_ACTIVITY_PATH ) => '',                          // In this file, logs will be placed during the process.
		);

		return $files_and_contents;
	}

	/**
	 * Create security files inside backup directory folder.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function create_security_files() {

		$backup_directory_path = $this->backup_directory_path;
		$files_and_contents    = $this->get_security_files();

		if ( is_array( $files_and_contents ) && ! empty( $files_and_contents ) ) {
			foreach ( $files_and_contents as $filename => $content ) {

				$file = wp_normalize_path( $backup_directory_path . '/' . $filename );
				$this->filesystem->writefile( $file, $content );
			}
		}
	}

	/**
	 * Content for htaccess files.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function htaccess_content() {
		return implode(
			PHP_EOL,
			array(
				'<IfModule mod_mime.c>',
				'AddType application/octet-stream ' . EVEREST_BACKUP_BACKUP_FILE_EXTENSION,
				'</IfModule>',
				'<IfModule mod_dir.c>',
				'DirectoryIndex index.php',
				'</IfModule>',
				'<IfModule mod_autoindex.c>',
				'Options -Indexes',
				'</IfModule>',
				'<FilesMatch ".*">',
				'Order Deny,Allow',
				'Deny from all',
				'</FilesMatch>',
				'<FilesMatch "^[^.]+$">',
				'Order Deny,Allow',
				'Deny from all',
				'</FilesMatch>',
				'<FilesMatch "\.(txt|php|ebwp)$">',
				'Order Allow,Deny',
				'Allow from all',
				'</FilesMatch>',
			)
		);
	}

	/**
	 * Content for tags.php
	 *
	 * @return string
	 */
	protected function tags_content() {
		return implode(
			PHP_EOL,
			array(
				'<?php',
				"if ( ! defined( 'ABSPATH' ) ) {",
				'exit;',
				'}',
			)
		);
	}
}
