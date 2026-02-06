<?php
/**
 * Class for creating zip files.
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
 * Class for creating zip files.
 *
 * @since 1.0.0
 */
class Compress extends Archiver {

	/**
	 * List of excluded files.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $excluded = array();

	/**
	 * Parameters to manipulate backup modules.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $params = array();

	/**
	 * Unique ID for the zip file.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $zip_uniqid;

	/**
	 * Current unix timestamp.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private $timestamp;

	/**
	 * Directory path to backups folder.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $backup_directory_path = EVEREST_BACKUP_BACKUP_DIR_PATH;

	/**
	 * Init class.
	 *
	 * @param array $params Parameters to manipulate backup modules.
	 * @since 1.0.0
	 */
	public function __construct( $params = array() ) {

		$this->timestamp  = time();
		$this->zip_uniqid = $this->get_uniqid();

		$this->params = apply_filters( 'everest_backup_filter_backup_modules_params', $params );

		/**
		 * Create backup directory if it does not exists.
		 */
		Backup_Directory::init()->create();

		Logs::info( __( 'Initializing new archive.', 'everest-backup' ) );

		/**
		 * You can set custom info log using `everest_backup_filter_backup_modules_params` filter or passing as parameter to this class.
		 *
		 * @since 1.1.2
		 */
		if ( ! empty( $this->params['info'] ) ) {
			Logs::info( $this->params['info'] );
		}

		$zipname = $this->get_zipname();

		parent::__construct( $zipname );

		Logs::set_infostat( 'params', $this->get_params() );
	}

	/**
	 * Returns params.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_params() {
		return $this->params;
	}

	/**
	 * Add path to list that needs to be replaced.
	 *
	 * @param string $path Path to add in the list.
	 * @return void
	 * @since 1.0.0
	 */
	public function add_paths_to_replace( $path ) {
		$this->paths_to_replace[] = $path;
	}

	/**
	 * It creates a dynamic zipname with full path without file extension.
	 * The zip file extension is being handled from parent class.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function get_zipname() {

		$params   = $this->get_params();
		$name_tag = ! empty( $params['custom_name_tag'] ) ? trim( $params['custom_name_tag'], '-' ) : site_url();

		$backup_directory_path = $this->backup_directory_path;

		$filename_block   = array();
		$filename_block[] = 'ebwp-';
		$filename_block[] = sanitize_title( preg_replace( '#^https?://#i', '', $name_tag ) );
		$filename_block[] = '-' . $this->timestamp;
		$filename_block[] = '-' . $this->zip_uniqid;

		$filename = implode( '', $filename_block );

		$zipname = $backup_directory_path . '/' . $filename;

		return $zipname;
	}

	/**
	 * Add files to the list.
	 *
	 * @param array $files Array of files to add.
	 * @return array $files_add Array of files added to the lists, else empty array on failure.
	 *
	 * @since 1.0.0
	 */
	public function addfiles( $files ) {
		$files_add = array();

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {

				/**
				 * Hook to exclude file. Return boolean true if you want to exclude a particular file.
				 *
				 * @since 1.1.2
				 */
				if ( apply_filters( 'everest_backup_exclude_file', false, $file ) ) {
					$this->excluded[] = $file;
					continue;
				}

				if ( everest_backup_is_extension_excluded( $file ) ) {
					$this->excluded[] = $file;
					continue;
				}

				if ( $this->add( $file ) ) {
					$files_add[] = $file;
				}
			}
		}

		Logs::info( sprintf( ( __( 'Total files:', 'everest-backup' ) . ' %d' ), count( $files_add ) ) );

		return $files_add;
	}

	/**
	 * Returns uniqid of current archive process.
	 *
	 * @return string Unique ID string.
	 * @since 1.0.0
	 */
	public function zip_uniqid() {
		return $this->zip_uniqid;
	}

	/**
	 * Returns current unix timestamp.
	 *
	 * @return int Unique ID string.
	 * @since 1.0.0
	 */
	public function timestamp() {
		return $this->timestamp;
	}

	/**
	 * Check disk space before archiving the files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function prezip_disk_space_check() {

		Logs::info( __( 'Checking disk available space.', 'everest-backup' ) );

		$disk_free_space = everest_backup_disk_free_space( $this->backup_directory_path );
		$total_filesize  = $this->get_total_filesize();

		/* translators: %1$s is total filesize and %2$s is server available free disk space. */
		$message = sprintf( __( 'Required disk space: %1$s. Available disk space: %2$s', 'everest-backup' ), esc_html( everest_backup_format_size( $total_filesize ) ), esc_html( everest_backup_format_size( $disk_free_space ) ) );

		if ( everest_backup_is_space_available( $this->backup_directory_path, $total_filesize ) ) {
			Logs::info( __( 'Disk space available.', 'everest-backup' ) );
			Logs::info( $message );

			Logs::set_proc_stat(
				array(
					'status'   => 'in-process',
					'progress' => 57.12,
					'message'  => $message,
				)
			);
			return;
		}

		Logs::error( $message );
		everest_backup_send_error( $message );
	}

	/**
	 * Compress and zip the files and return zip path.
	 * This method must be call at the last, after all the files has been added to the list.
	 *
	 * @return string Full path to zip file with extension.
	 */
	public function zip() {
		$this->prezip_disk_space_check();

		Logs::info( __( 'Wrapping things up.', 'everest-backup' ) );

		$zippath = 'ziparchive' === $this->type ? $this->zip_using_ziparchive() : $this->zip_using_fallback_archiver();
		$zip     = is_file( $zippath ) && 'ebwp' === pathinfo( $zippath, PATHINFO_EXTENSION ) ? $zippath : '';

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 92.82,
				/* translators: %s is size of backup file. */
				'message'  => $zip ? sprintf( __( 'Backup created of size %s', 'everest-backup' ), everest_backup_format_size( filesize( $zip ) ) ) : __( 'Failed to created backup', 'everest-backup' ),
			)
		);

		if ( $zip ) {

			/**
			 * Set tags.
			 *
			 * @since 1.0.9
			 */
			$tags = new Tags( $zip );
			$tags->set( $this->get_params() );

			Logs::set_infostat( 'modules_included', $tags->get( 'included' ) );
			Logs::set_infostat( 'saved_to', everest_backup_is_saving_to() );
			Logs::set_infostat( 'zip_size', filesize( $zip ) );
			Logs::set_infostat( 'zip_path', $zip );
			Logs::set_infostat( 'zipname', basename( $zip ) );

			return $zip;
		}
	}

	/**
	 * Deletes the zip file from the server.
	 * This method is supposed to be called after `everest_backup_after_zip_done` hook. Or after cloud upload process has been completed.
	 *
	 * @return void
	 */
	public function delete_from_server() {
		$params = $this->get_params();

		/**
		 * Filter hook to avoid delete from server if the cloud upload fails.
		 * Return true if you want to avoid the delete from server.
		 *
		 * @since 1.1.5
		 */
		if ( true === apply_filters( 'everest_backup_avoid_delete_from_server', false ) ) {
			return;
		}

		if ( empty( $params['save_to'] ) ) {
			return;
		}

		if ( 'server' === $params['save_to'] ) {
			return;
		}

		if ( empty( $params['delete_from_server'] ) ) {
			return;
		}

		$zipname = $this->zipname;

		if ( ! file_exists( $zipname ) ) {
			return;
		}

		Logs::info( __( 'Deleting the backup file from the server.', 'everest-backup' ) );

		/**
		 * Filesystem class object.
		 *
		 * @var Filesystem
		 */
		$filesystem = Filesystem::init();

		$filesystem->delete( $zipname );
	}
}
