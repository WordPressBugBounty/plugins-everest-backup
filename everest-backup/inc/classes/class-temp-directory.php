<?php
/**
 * Handles the everest backup temporary directory "ebwp-temps".
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
 * Handles the everest backup temporary directory "ebwp-temps".
 *
 * @since 1.0.7
 */
class Temp_Directory {

	use Singleton;

	/**
	 * Directory path to temps folder.
	 *
	 * @var string
	 */
	private $temporary_dir_path = EVEREST_BACKUP_TEMP_DIR_PATH;

	/**
	 * File system class object.
	 *
	 * @var Filesystem
	 */
	private $filesystem;

	/**
	 * Init class.
	 */
	public function __construct() {
		$this->filesystem = Filesystem::init();

		$this->remove_legacy_tmp_directories();
	}

	/**
	 * Create backup directory and its required security files.
	 *
	 * @return void
	 */
	public function create() {
		if ( $this->is_temp_directory_exists() ) {
			return;
		}
		$this->create_temp_dir();
		$this->create_security_files();
	}

	/**
	 * This method first clears everything from temp directory and then re-create files in temp.
	 *
	 * @return void
	 */
	public function reset() {
		$this->clean_temp_dir();
		$this->create();
	}

	/**
	 * Returns true if backup directory is created and exists.
	 *
	 * @return boolean
	 */
	public function is_temp_directory_exists() {
		return $this->filesystem->is_dir( $this->temporary_dir_path )
		&& $this->filesystem->is_file( wp_normalize_path( $this->temporary_dir_path . '/.htaccess' ) );
	}

	/**
	 * Create backup directory.
	 *
	 * @return void
	 */
	private function create_temp_dir() {
		$this->filesystem->mkdir_p( $this->temporary_dir_path );
	}

	/**
	 * Recursively deletes the files and folders from temporary directory.
	 *
	 * @return void
	 */
	public function clean_temp_dir() {

		if ( everest_backup_is_process_running() ) {
			return;
		}

		if ( ! $this->filesystem->is_dir( $this->temporary_dir_path ) ) {
			return;
		}

		$this->filesystem->delete( $this->temporary_dir_path, true );
	}

	/**
	 * Returns array of security files with file contents.
	 *
	 * @return array
	 */
	private function get_security_files() {
		$files_and_contents = array(
			'.htaccess'  => 'deny from all',
			'index.php'  => '<?php',
			'index.html' => '',
		);

		return $files_and_contents;
	}

	/**
	 * Create security files inside temporary directory folder.
	 *
	 * @return void
	 */
	private function create_security_files() {

		$temporary_dir_path = $this->temporary_dir_path;
		$files_and_contents = $this->get_security_files();

		if ( is_array( $files_and_contents ) && ! empty( $files_and_contents ) ) {
			foreach ( $files_and_contents as $filename => $content ) {

				$file = wp_normalize_path( $temporary_dir_path . '/' . $filename );
				$this->filesystem->writefile( $file, $content );
			}
		}
	}

	public function join_path( $path ) {
		return wp_normalize_path( $this->temporary_dir_path . '/' . str_replace( $this->temporary_dir_path, '', wp_normalize_path( $path ) ) );
	}

	/**
	 * Add file to temp directory.
	 *
	 * @param string $file
	 * @param string $content
	 * @param boolean $append
	 * @return boolean
	 * @since 2.0.0
	 */
	public function add_to_temp( $file, $content, $append = false ) {

		$path = $this->join_path( $file );

		$this->create();

		return $this->filesystem->writefile( $path, $content, $append );
	}

	/**
	 * Remove legacy temporary directories.
	 *
	 * @return void
	 */
	public function remove_legacy_tmp_directories() {
		$versions = array(
			'1.0.8',
			'1.1.2',
		);

		if ( is_array( $versions ) && ! empty( $versions ) ) {
			foreach ( $versions as $version ) {
				$dir_path = $this->get_legacy_tmp_directory( $version );

				if ( ! $dir_path ) {
					continue;
				}

				if ( ! is_dir( $dir_path ) ) {
					continue;
				}

				if ( file_exists( $dir_path ) ) {
					$this->filesystem->delete( $dir_path, true );
				}
			}
		}
	}

	/**
	 * Returns path to legacy tmp directory.
	 *
	 * @param string $version Version of Everest Backup plugin.
	 * @return string
	 * @since 1.0.8
	 * @since 1.1.2 Added $version parameter.
	 */
	public function get_legacy_tmp_directory( $version ) {

		$storage_dir = '';

		if ( ! $version ) {
			return $storage_dir;
		}

		switch ( $version ) {

			case '1.0.8':
				$storage_dir = trailingslashit( get_temp_dir() ) . 'ebwp-storage';
				break;

			case '1.1.2':
				$storage_dir = ABSPATH . 'ebwp-temps';
				break;

			default:
				break;
		}

		return wp_normalize_path( $storage_dir );
	}

}
