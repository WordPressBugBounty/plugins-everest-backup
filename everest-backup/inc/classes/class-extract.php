<?php
/**
 * Class for extracting the zip file.
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
 * Class for extracting the zip file.
 *
 * @since 1.0.0
 */
class Extract extends Archiver {

	/**
	 * Maximum file upload size.
	 *
	 * @var int
	 */
	protected $max_upload_size;

	/**
	 * Filesystem class object.
	 *
	 * @var \Everest_Backup\Filesystem
	 * @since 1.0.0
	 */
	public $filesystem;

	/**
	 * Path to the storage folder with uniqid for every new restoration process.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $storage_dir;

	/**
	 * File_Uploader object.
	 *
	 * @var File_Uploader|array|null
	 * @since 1.0.0
	 */
	protected $package;

	/**
	 * Data extracted from uploaded zip.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $package_data = array();

	/**
	 * List of files in zip. It returns array with key `root` and `nested`.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $list = array();

	/**
	 * Unique ID.
	 *
	 * @var string
	 */
	protected $uniqid;

	/**
	 * Init class.
	 *
	 * @param array|null $args @since 1.0.7 [Optional] Args to pass in File_Uploader.
	 * @since 1.0.0
	 */
	public function __construct( $args = null ) {

		/**
		 * Create backup directory if it does not exists.
		 */
		Backup_Directory::init()->create();

		$this->package = $args;

		$this->max_upload_size = everest_backup_max_upload_size();

		$this->filesystem = Filesystem::init();

		$this->uniqid = $this->get_uniqid();

		$this->set_package();
		$this->verify_upload();
		$this->set_storage_dir();
		$this->pre_extract_disk_space_check();
		$this->extract_to_storage();
		$this->set_list();

		add_action( 'everest_backup_before_send_json', array( $this, 'done' ) );
	}

	/**
	 * Returns Everest_Backup\Extract::$list data set from Everest_Backup\Extract::set_list.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_list() {
		return $this->list;
	}

	/**
	 * Flush WordPress cache and rewrite rules. Call this method after extraction is completed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function flush_cache() {
		flush_rewrite_rules();
		everest_backup_elementor_cache_flush();

		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
	}

	/**
	 * Activate plugnins.
	 */
	protected function activate_plugins() {

		$ms_blogs = $this->get_temp_data( 'ms_blogs' );

		if ( is_array( $ms_blogs ) && ! empty( $ms_blogs ) ) {
			foreach ( $ms_blogs as $ms_blog_id => $ms_blog ) {
				switch_to_blog( $ms_blog_id );

				$active_plugins = ! empty( $ms_blog['ActivePlugins'] ) ? everest_backup_filter_plugin_list( $ms_blog['ActivePlugins'] ) : array();

				activate_plugins( $active_plugins );

				restore_current_blog();
			}
		} else {

			$config_data = $this->get_temp_data( 'config_data' );

			$active_plugins = ! empty( $config_data['ActivePlugins'] ) ? everest_backup_filter_plugin_list( $config_data['ActivePlugins'] ) : array();

			activate_plugins( $active_plugins );
		}
	}

	/**
	 * Functionalities to run after restore process completed.
	 *
	 * @return void
	 * @since 1.1.4
	 */
	public function done() {
		$this->flush_cache();
		$this->activate_plugins();
		wp_clear_auth_cookie();
	}

	/**
	 * Uploaded temp file data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_package() {

		Logs::info( __( 'Setting up package.', 'everest-backup' ) );

		if ( everest_backup_doing_rollback() ) {
			$response = everest_backup_get_ajax_response( EVEREST_BACKUP_IMPORT_ACTION );

			$file     = ! empty( $response['file'] ) ? $response['file'] : '';
			$filename = ! empty( $response['filename'] ) ? $response['filename'] : '';
			$cloud    = ! empty( $response['cloud'] ) ? $response['cloud'] : 'server';

			if ( ( 'server' !== $cloud ) && ( $file !== $filename ) ) {

				/**
				 * If we are doing rollback from cloud file.
				 */
				$filename = $file;
			}

			if ( everest_backup_doing_clone() && $file ) {

				/**
				 * If we are doing clone.
				 */
				$filename = $file;
			}

			$package = everest_backup_get_backup_full_path( $filename, false );

			$package = apply_filters( 'everest_backup_filter_rollback_args', compact( 'package', 'filename', 'cloud' ) );

			Logs::info( __( 'Rollback started.', 'everest-backup' ) );

		} else { // @phpcs:ignore

			if ( ! empty( $this->package['package'] ) ) {
				// If args has package.
				$package = new File_Uploader( $this->package );

			} else {

				// Else package property is null so initiate file uploader internally.
				$package = new File_Uploader(
					array(
						'form'      => 'file',
						'urlholder' => 'ebwp_package',
					)
				);
			}
		}

		$this->package = $package;

		$this->package_data = wp_parse_args(
			$package,
			array(
				'package'  => '',
				'filename' => '',
				'id'       => 0,
			)
		);
	}

	/**
	 * Verify the uploaded file. If fails, it stops the script, otherwise it returns boolean true.
	 *
	 * @return true
	 * @since 1.0.0
	 */
	protected function verify_upload() {

		Logs::info( __( 'Verifying package.', 'everest-backup' ) );

		$package = $this->package_data['package'];

		$ext = pathinfo( $package, PATHINFO_EXTENSION );

		if ( ".$ext" !== $this->extension ) {
			$message = __( 'Invalid file provided. Please provide a valid file.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		$max_upload_size = $this->max_upload_size;
		$package_size    = filesize( $package );

		$is_valid_size = 0 !== $max_upload_size ? $max_upload_size > $package_size : true;

		if ( ! $is_valid_size ) {

			/* translators: Here, %s is the size limit set by the server. */
			$message = sprintf( __( 'The file size is larger than %s', 'everest-backup' ), everest_backup_format_size( $max_upload_size ) );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true ); // @phpcs:ignore
		}

		Logs::info( __( 'Package verified.', 'everest-backup' ) );

		return true;
	}

	/**
	 * Create and set storage directory path for current extraction process.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_storage_dir() {

		Logs::info( __( 'Creating storage directory.', 'everest-backup' ) );

		$filesystem = $this->filesystem;
		$folder     = $this->get_storage_dir();

		if ( ! $filesystem->mkdir_p( $folder ) ) {
			$message = __( 'Unable to create storage folder.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'Storage directory created.', 'everest-backup' ) );

		$this->storage_dir = wp_normalize_path( $folder );
	}

	/**
	 * Check storage disk space before extracting the files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function pre_extract_disk_space_check() {

		Logs::info( __( 'Checking disk available space.', 'everest-backup' ) );

		$disk_free_space = everest_backup_disk_free_space( $this->storage_dir );
		$package_size    = filesize( $this->package_data['package'] ) * 1.5;

		/* translators: %1$s is total filesize and %2$s is server available free disk space. */
		$message = sprintf( __( 'Required disk space: %1$s. Available disk space: %2$s', 'everest-backup' ), esc_html( everest_backup_format_size( $package_size ) ), esc_html( everest_backup_format_size( $disk_free_space ) ) );

		if ( everest_backup_is_space_available( $this->storage_dir, $package_size ) ) {
			Logs::info( __( 'Disk space available.', 'everest-backup' ) );
			Logs::info( $message );
			return;
		}

		Logs::error( $message );
		everest_backup_send_error( $message );
	}

	/**
	 * Extract the contents of zip to storage directory then clean the package attachment.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function extract_to_storage() {

		Logs::info( __( 'Extracting package to the storage directory.', 'everest-backup' ) );

		$unzip = $this->unzip( $this->package_data['package'], $this->storage_dir );

		if ( is_object( $this->package ) && method_exists( $this->package, 'cleanup' ) ) {

			/**
			 * Clean the package after extracting the file in storage directory.
			 */
			$this->package->cleanup();
		}

		if ( ! $unzip ) {
			$message = esc_html__( 'Failed to extract backup file.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'Package extracted.', 'everest-backup' ) );
	}

	/**
	 * List the extracted files to Everest_Backup\Extract::$list.
	 *
	 * @return void
	 * @since
	 */
	protected function set_list() {
		$default_dir = array(
			'plugins',
			'themes',
			'uploads',
		);

		$storage_dir = trailingslashit( $this->storage_dir );
		$files       = $this->filesystem->list_files( $storage_dir );

		$files = array_map( 'wp_normalize_path', $files );

		$root_and_nested = array();

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {

				$this->add( $file );

				$abspath = str_replace( $storage_dir, '', $file );

				$explode = explode( '/', $abspath, 2 );

				if ( ! is_array( $explode ) ) {
					continue;
				}

				if ( ! ( count( $explode ) > 1 ) ) {
					$root_and_nested['root'][] = $file;
				} else {
					$folder = $explode[0];

					if ( in_array( $folder, $default_dir, true ) ) {
						$root_and_nested['nested'][ $folder ][] = $file;
					} else {
						$root_and_nested['nested']['content'][] = $file;
					}
				}
			}
		}

		$this->list = $root_and_nested;
	}
}
