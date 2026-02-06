<?php
/**
 * Class for managing files and folders.
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
 * Class for managing files and folders.
 *
 * @since 1.0.0
 */
class Filesystem {

	use Singleton;

	/**
	 * WordPress filesystem object.
	 *
	 * @var \WP_Filesystem_Direct
	 * @since 1.0.0
	 */
	protected $filesystem;

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once wp_normalize_path( ABSPATH . 'wp-admin/includes/file.php' );
		}

		WP_Filesystem();
		global $wp_filesystem;

		$this->filesystem = $wp_filesystem;
	}

	/**
	 * Returns WordPress Filesystem objects.
	 *
	 * @since 1.0.7
	 * @return \WP_Filesystem_Direct
	 */
	public function get_wp_fs() {
		return $this->filesystem;
	}

	/**
	 * Wrapper for wp_mkdir_p.
	 *
	 * @param string $target Full path to attempt to create.
	 * @return bool Whether the path was created. True if path already exists.
	 */
	public function mkdir_p( $target ) {
		return wp_mkdir_p( $target );
	}

	/**
	 * Writes content to the file. If file doesn't exits, it creates file then writes the content to it.
	 *
	 * @param string $file    Remote path to the file where to write the data.
	 * @param string $content The data to write.
	 * @param string $append  If set to true, the content will be appended to the end of the file else whole content will be replaced.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function writefile( $file, $content, $append = false ) {
		// @phpcs:disable
		if ( $append ) {
			$resource = fopen( $file, 'a' );
			$write    = fwrite( $resource, $content );
			fclose( $resource );
			$resource = false;
			return is_int( $write );
		}
		// @phpcs:enable

		return $this->filesystem->put_contents( $file, $content );
	}

	/**
	 * Reads file data and return it according to the $type.
	 *
	 * @param string $file Path to the file where to get the data.
	 * @param string $type Whether to return file content as `string` or `array`.
	 * @return string|array
	 * @since 1.0.0
	 */
	public function get_file_content( $file, $type = 'string' ) {
		if ( ! is_file( $file ) ) {
			return;
		}

		if ( 'string' === $type ) {
			return file_get_contents( $file ); // @phpcs:ignore
		}

		return file( $file );
	}

	/**
	 * Wrapper for $wp_filesystem->is_dir()
	 *
	 * @param string $path Directory path.
	 * @return boolean
	 * @since 1.0.0
	 */
	public function is_dir( $path ) {
		return $this->filesystem->is_dir( $path );
	}

	/**
	 * Checks if the provided path is of file or not.
	 * Wrapper for $wp_filesystem->is_file()
	 *
	 * @param string $file Path to the file.
	 * @return boolean
	 * @since 1.0.0
	 */
	public function is_file( $file ) {
		return $this->filesystem->is_file( $file );
	}

	/**
	 * Returns file extension type.
	 *
	 * @param string $file Path to the file.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_file_extension( $file ) {
		if ( ! $this->is_file( $file ) ) {
			return;
		}

		$file_parts = pathinfo( $file );

		return ! empty( $file_parts['extension'] ) ? $file_parts['extension'] : '';
	}

	/**
	 * Move file from one folder to another. If file already exists in destination folder, it will delete that file first.
	 *
	 * @param string $from File with full path in source folder.
	 * @param string $to File with full path to destination folder.
	 * @return bool
	 * @since 1.0.0
	 */
	public function move_file( $from, $to ) {

		if ( $from === $to ) {
			$message = __( 'Source and destination same (during upload save).', 'everest-backup' );
			Logs::error( $message );
			return false;
		}

		if ( ! $this->is_file( $from ) ) {
			$message = __( 'Uploaded file cannot be verified as a file (during upload save).', 'everest-backup' );
			Logs::error( $message );
			return false;
		}

		$to_dir = dirname( $to );

		/**
		 * Create destination directory if it doesn't exist.
		 */
		if ( ! $this->is_dir( $to_dir ) ) {
			$this->mkdir_p( $to_dir );
		}

		/**
		 * Delete if same file already exists in destination folder.
		 */
		if ( file_exists( $to ) ) {
			$message = __( 'File with same name already exists (during upload save).', 'everest-backup' );
			Logs::error( $message );
			wp_delete_file( $to );
		}

		return $this->filesystem->move( $from, $to, true );
	}

	/**
	 * Wrapper for WP_Filesystem_Direct::delete. Deletes a file or directory.
	 *
	 * @param string $file Path to the file or directory.
	 * @param string $recursive If set to true, deletes files and folders recursively.
	 *
	 * @return boolean
	 * @since 1.0.0
	 */
	public function delete( $file, $recursive = false ) {

		/**
		 * Avoid current and parent directories by any chance.
		 */
		if ( '.' === $file || '..' === $file ) {
			return;
		}

		return $this->filesystem->delete( $file, $recursive );
	}

	/**
	 * List all files without skipping the hidden files.
	 *
	 * @param string   $folder Full path to folder. Default empty.
	 * @param int      $levels Optional. Levels of folders to follow, Default 100 (PHP Loop limit).
	 * @param string[] $exclusions Optional. Array of folder names in $folder directory.
	 *
	 * @return string[]|false Array of files on success, false on failure.
	 * @since 1.0.0
	 */
	public function list_files_all( $folder, $levels = 100, $exclusions = array() ) {
		// @phpcs:disable

		if ( empty( $folder ) ) {
			return false;
		}

		$folder = trailingslashit( $folder );

		if ( ! $levels ) {
			return false;
		}

		$files = array();

		$dir = opendir( $folder );

		if ( $dir ) {
			while ( ( $file = readdir( $dir ) ) !== false ) {
				// Skip current and parent folder links.
				if ( in_array( $file, array( '.', '..' ), true ) ) {
					continue;
				}

				// Skip excluded files.
				if ( in_array( $file, $exclusions, true ) ) {
					continue;
				}

				if ( is_dir( $folder . $file ) ) {
					$files2 = $this->list_files_all( $folder . $file, $levels - 1 );
					if ( $files2 ) {
						$files = array_merge( $files, $files2 );
					} else {
						$files[] = $folder . $file . '/';
					}
				} else {
					$files[] = $folder . $file;
				}
			}

			closedir( $dir );
		}

		// @phpcs:enable

		return $files;
	}

	/**
	 * Wrapper for `Everest_Backup\Filesystem::list_files_all` function.
	 *
	 * @param string   $folder Full path to folder. Default empty.
	 * @param string[] $exclusions Optional. Array of folder names in $folder directory.
	 *
	 * @return string[]|false Array of files on success, false on failure.
	 * @since 1.0.0
	 */
	public function list_files( $folder, $exclusions = array() ) {

		$folder = trailingslashit( $folder );

		$all_files = $this->list_files_all( $folder, 100, $exclusions );

		$debug = everest_backup_get_settings( 'debug' );

		/**
		 * Filter out the node_modules and normalize path.
		 */
		$files = array_filter(
			$all_files,
			function ( $file ) use ( $debug ) {
				if ( ( false === strpos( $file, 'node_modules' ) ) ) {

					if ( empty( $debug['exclude_languages_folder'] ) ) {
						return wp_normalize_path( $file );
					}

					if ( false === strpos( $file, WP_LANG_DIR ) ) {
						return wp_normalize_path( $file );
					}
				}
			}
		);

		return $files;
	}

	/**
	 * Wrapper for `Everest_Backup\Filesystem::list_files_all` function.
	 *
	 * @param string              $folder Full path to folder. Default empty.
	 * @param string[] (optional) $exclusion_folders Optional. Array of folder names in $folder directory.
	 * @param string[] (optional) $exclude_files Optional. Array of file names in $folder directory.
	 *
	 * @return string[]|false Array of files on success, false on failure.
	 * @since 1.0.0
	 */
	public function list_files_with_mtime( $folder, $exclusion_folders = array(), &$exclude_files = array() ) {

		$folder = trailingslashit( $folder );

		$all_files = $this->list_files_all( $folder, 100, $exclusion_folders );

		$debug = everest_backup_get_settings( 'debug' );

		/**
		 * Filter out the node_modules and normalize path.
		 */
		$files = array_filter(
			$all_files,
			function ( $file ) use ( $debug, $exclude_files ) {
				if ( ( false === strpos( $file, 'node_modules' ) ) && ! array_key_exists( $file, $exclude_files ) ) {

					if ( empty( $debug['exclude_languages_folder'] ) ) {
						return wp_normalize_path( $file );
					}

					if ( false === strpos( $file, WP_LANG_DIR ) ) {
						return wp_normalize_path( $file );
					}
				}
			}
		);

		$file_with_size = array();
		foreach ( $files as $file ) {
			$file_with_size[ $file ] = filemtime( $file );
		}

		return $file_with_size;
	}

	/**
	 * Custom function to check disk space if disk_free_space function disabled by the server.
	 *
	 * @param string $directory A directory of the filesystem or disk partition.
	 * @param string $size Size to check in directory.
	 *
	 * @return bool
	 * @since 1.0.9
	 */
	public function custom_check_free_space( $directory, $size ) {
		try {

			// @phpcs:disable
			$total = $size;

			$file = "{$directory}/.spacecheck";

			$fh    = fopen( $file, 'w' );
			$chunk = 1024;
			while ( $size > 0 ) {
				if ( false === fputs( $fh, str_pad( '', min( $chunk, $size ) ) ) ) {
					if ( file_exists( $file ) ) {
						@unlink( $file );
					}
					throw new \Exception( __( 'Failed to check free space using custom function, probably no space left on device. Aborting process', 'everest-backup' ) );
				}
				$size -= $chunk;
			}
			fclose( $fh );

			$fs = filesize( $file );
			@unlink( $file );

			if ( $fs > ( $total - 100 ) ) {
				return true;
			} else {
				return false;
			}

			// @phpcs:enable

		} catch ( \Exception $e ) {

			if ( file_exists( $file ) ) {
				unlink( $file );
			}

			return false;

		} catch ( \Throwable $e ) {

			if ( file_exists( $file ) ) {
				unlink( $file );
			}

			return false;

		}
	}
}
