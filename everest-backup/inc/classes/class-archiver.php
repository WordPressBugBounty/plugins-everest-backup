<?php
/**
 * Core abstract class for handling zip files.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core abstract class for handling zip files.
 *
 * @since 1.0.0
 */
abstract class Archiver {

	/**
	 * Temporary data added during import or export process, so that other modules can use it during the process.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $temps = array();

	/**
	 * Type of library we are currenty using.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $type;

	/**
	 * Compression library object.
	 *
	 * @var \ZipArchive|\PhpZip\ZipFile
	 * @since 1.0.0
	 */
	protected $lib;

	/**
	 * Zipfile custom extension.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $extension = EVEREST_BACKUP_BACKUP_FILE_EXTENSION;

	/**
	 * Full path to the zip file with filename and extension concatenated.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $zipname;

	/**
	 * Files to add for compression.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $files;

	/**
	 * Estimated total file size of all the files in the list.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private $total_files_size = 0;

	/**
	 * Files paths to replace inside zip.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $paths_to_replace;

	/**
	 * Init class.
	 *
	 * @param string $zipname Full path to the zip file with filename but without file extension.
	 * @since 1.0.0
	 */
	public function __construct( $zipname ) {

		if ( ! $zipname ) {
			return;
		}

		$this->zipname = wp_normalize_path( $zipname . $this->extension );

		$paths_to_replace = wp_parse_args(
			$this->paths_to_replace,
			array(
				WP_CONTENT_DIR,
			)
		);

		$paths_to_replace       = array_map( 'trailingslashit', $paths_to_replace );
		$this->paths_to_replace = array_map( 'wp_normalize_path', $paths_to_replace );

		$this->archiver_init();

	}

	/**
	 * Returns unique id key for current process.
	 *
	 * @return string
	 * @since 1.0.7
	 */
	public function get_uniqid() {
		return everest_backup_current_request_id();
	}

	/**
	 * Get current process unique ID storage directory path.
	 *
	 * @return string
	 * @since 1.0.7
	 */
	public function get_storage_dir() {
		return EVEREST_BACKUP_TEMP_DIR_PATH . DIRECTORY_SEPARATOR . $this->get_uniqid();
	}

	/**
	 * Recursively deletes the files and folders from temporary storage directory.
	 *
	 * @return void
	 */
	public function clean_storage_dir() {

		$directory  = $this->get_storage_dir();
		$filesystem = Filesystem::init();

		if ( ! $filesystem->is_dir( $directory ) ) {
			return;
		}

		$filesystem->delete( $directory, true );
	}

	/**
	 * Initialized required archiver.
	 *
	 * @return void
	 * @since 1.1.4
	 */
	public function archiver_init() {

		$archiver = everest_backup_get_archiver();

		$this->type = $archiver['type'];
		$this->lib  = $archiver['lib'];

	}

	/**
	 * Unzips file.
	 *
	 * @param string $file Full path and filename of ZIP archive.
	 * @param string $to   Full path on the filesystem to extract archive to.
	 * @return bool
	 * @since 1.1.4
	 */
	public function unzip( $file, $to ) {
		if ( ! $this->type ) {
			$this->archiver_init();
		}

		@ini_set( 'memory_limit', '-1' ); // @phpcs::ignore

		if ( ! everest_backup_use_fallback_archiver() ) {
			return $this->unzip_using_ziparchiver( $file, $to );
		}

		return $this->unzip_using_fallback_archiver( $file, $to );

	}

	/**
	 * Unzip using ziparchiver.
	 *
	 * @param string $file Source file path.
	 * @param string $to   Destination path.
	 * @return bool
	 * @since 1.1.4
	 */
	protected function unzip_using_ziparchiver( $file, $to ) {

		/**
		 * @var \ZipArchive
		 */
		$z = $this->lib;

		if ( $z->open( $file ) ) {
			$z->extractTo( $to );
			$z->close();

			return true;
		}

		return false;
	}

	/**
	 * Unzip using fallback archiver.
	 *
	 * @param string $file Source file path.
	 * @param string $to   Destination path.
	 * @return bool
	 * @since 1.1.4
	 */
	protected function unzip_using_fallback_archiver( $file, $to ) {

		/**
		 * @var \PhpZip\ZipFile
		 */
		$z = $this->lib;

		try {
			$z->openFile( $file );

			$z->extractTo( $to );

			$z->close();

			return true;
		} catch ( \PhpZip\Exception\ZipException $e ) {
			return false;
		}
	}

	/**
	 * Set data to Everest_Backup\Archiver::$temps.
	 *
	 * @param string $key Array key for the `Everest_Backup\Archiver::$temps`.
	 * @param mixed  $value Value to set according to the `$key`.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_temp_data( $key, $value ) {
		$this->temps[ $key ] = $value;
	}

	/**
	 * Returns temp data according to the `$key`.
	 *
	 * @param string $key Array key for the `Everest_Backup\Archiver::$temps`.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_temp_data( $key ) {
		if ( isset( $this->temps[ $key ] ) ) {
			return $this->temps[ $key ];
		}
	}

	/**
	 * Add the pass file size to total filesize.
	 *
	 * @param string $file Filename with path.
	 * @return void
	 * @since 1.0.0
	 */
	private function add_total_filesize( $file ) {

		if ( ! $file ) {
			return;
		}

		$filesize = filesize( $file );

		if ( is_int( $filesize ) ) {
			$this->total_files_size += $filesize;
		}

	}

	/**
	 * Returns total added file size.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_total_filesize() {
		return $this->total_files_size;
	}

	/**
	 * Adds files to the list for the compression.
	 *
	 * @param string $file Filename with path.
	 * @return bool|void Return true on success.
	 * @since 1.0.0
	 */
	protected function add( $file ) {

		if ( ! is_file( $file ) ) {
			return;
		}

		/**
		 * Check if file exists.
		 *
		 * @since 1.1.2
		 */
		if ( ! file_exists( $file ) ) {
			return;
		}

		/**
		 * Check if file is readable or not.
		 *
		 * @since 1.1.2
		 */
		if ( ! is_readable( $file ) ) {
			/* translators: %s is unreadable file basename. */
			Logs::error( sprintf( __( 'Skipping file "%s" because it is unreadable.', 'everest-backup' ), basename( $file ) ) );
			return;
		}

		$this->add_total_filesize( $file );

		$this->files[] = wp_normalize_path( $file );

		return true;
	}

	/**
	 * Returns an array of all the added files.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_files() {
		return $this->files;
	}

	/**
	 * Write temp log for the folders being archived.
	 *
	 * @param string $file Full path to file.
	 * @param int    $progress Progress percent.
	 * @return void
	 * @since 1.0.7
	 */
	protected function procstat_foldername( $file, $progress ) {

		$entryname = $this->generate_entryname( $file );

		$foldername = substr( $entryname, 0, strpos( $entryname, '/' ) );

		if ( ! $foldername ) {
			return;
		}

		switch ( $foldername ) {

			case 'uploads':
				$procstat = array(
					'status'   => 'in-process',
					'progress' => 64.26,
					/* translators: %d is the progress percent. */
					'message'  => sprintf( __( 'Archiving media files ( %d%% )', 'everest-backup' ), $progress ),
				);
				break;

			case 'themes':
				$procstat = array(
					'status'   => 'in-process',
					'progress' => 71.4,
					/* translators: %d is the progress percent. */
					'message'  => sprintf( __( 'Archiving theme files ( %d%% )', 'everest-backup' ), $progress ),
				);
				break;

			case 'plugins':
				$procstat = array(
					'status'   => 'in-process',
					'progress' => 78.54,
					/* translators: %d is the progress percent. */
					'message'  => sprintf( __( 'Archiving plugin files ( %d%% )', 'everest-backup' ), $progress ),
				);
				break;

			default:
				$procstat = array(
					'status'   => 'in-process',
					'progress' => 85.68,
					/* translators: %d is the progress percent. */
					'message'  => sprintf( __( 'Archiving wp-content files ( %d%% )', 'everest-backup' ), $progress ),
				);
				break;
		}

		Logs::set_proc_stat( $procstat, 0 );

	}

	/**
	 * Set process stats message for total number of files just before closing archive.
	 *
	 * @param array $files Total files array.
	 * @return void
	 * @since 1.0.7
	 */
	protected function procstat_files_count( $files ) {
		if ( ! is_array( $files ) ) {
			return;
		}

		sleep( 2 );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 88,
				/* translators: %d is the total number of files. */
				'message'  => sprintf( __( 'Archiving %d files. This step can take some time.', 'everest-backup' ), count( $files ) ),
			),
			0
		);

	}

	/**
	 * Generates archiver entryname from file full path.
	 *
	 * @param string $file Full path to file.
	 * @return string
	 * @since 1.1.2
	 */
	protected function generate_entryname( $file ) {
		return strtr(
			$file,
			array_combine(
				$this->paths_to_replace,
				array_fill( 0, count( $this->paths_to_replace ), '' )
			)
		);
	}

	/**
	 * If ZipArchive class exists then handle compression using it.
	 *
	 * @return string Path to the zip with zipname.
	 * @since 1.0.0
	 */
	protected function zip_using_ziparchive() {

		$files      = $this->get_files();
		$ziparchive = $this->lib;
		$zipname    = $this->zipname;

		$total_files = count( $files );

		$ziparchive->open( $zipname, $ziparchive::CREATE );

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $index => $file ) {

				$entryname = $this->generate_entryname( $file );

				$progress = ( ( ( $index + 1 ) / $total_files ) * 100 );

				$this->procstat_foldername( $file, $progress );

				$ziparchive->addFile( $file, $entryname );
			}
		}

		$this->procstat_files_count( $files );

		$compressed = $ziparchive->close();

		return $compressed && file_exists( $zipname ) ? $zipname : '';
	}

	/**
	 * Archive using fallback archive class, i.e PhpZip\ZipFile
	 *
	 * @return string Path to the zip with zipname.
	 * @since 1.0.7
	 */
	protected function zip_using_fallback_archiver() {
		$files             = $this->get_files();
		$fallback_archiver = $this->lib;
		$zipname           = $this->zipname;

		$total_files = count( $files );

		try {
			if ( is_array( $files ) && ! empty( $files ) ) {
				foreach ( $files as $index => $file ) {

					$entryname = $this->generate_entryname( $file );

					$progress = ( ( ( $index + 1 ) / $total_files ) * 100 );

					$this->procstat_foldername( $file, $progress );

					$fallback_archiver->addFile( $file, $entryname );
				}
			}

			$this->procstat_files_count( $files );

			$fallback_archiver->saveAsFile( $zipname );
			$fallback_archiver->close();

			return $zipname && file_exists( $zipname ) ? $zipname : '';

		} catch ( \Exception $th ) {
			/* translators: %s is the error message. */
			Logs::error( sprintf( __( 'Backup failed. Reason: %s', 'everest-backup-google-drive' ), $th->getMessage() ) );
			everest_backup_send_error();
		}
	}
}
