<?php
/**
 * Handles ajax requests.
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

use Everest_Backup\Modules\Cloner;
use Everest_Backup\Modules\Restore_Config;
use Everest_Backup\Modules\Restore_Content;
use Everest_Backup\Modules\Restore_Database;
use Everest_Backup\Modules\Restore_Multisite;
use Everest_Backup\Modules\Restore_Plugins;
use Everest_Backup\Modules\Restore_Themes;
use Everest_Backup\Modules\Restore_Uploads;
use Everest_Backup\Modules\Restore_Users;
use Exception;

/**
 * Handles ajax requests.
 *
 * @since 1.0.0
 */
class Ajax {


	/**
	 * Init ajax.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {

		add_action( 'wp_ajax_everest_backup_addon', array( $this, 'install_addon' ) );

		add_action( 'wp_ajax_' . EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION, array( $this, 'upload_package' ) );
		add_action( 'wp_ajax_' . EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION, array( $this, 'save_uploaded_package' ) );
		add_action( 'wp_ajax_' . EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION, array( $this, 'remove_uploaded_package' ) );

		add_action( 'everest_backup_before_restore_init', array( $this, 'clone_init' ) );

		add_action( 'wp_ajax_nopriv_everest_process_status', array( $this, 'process_status' ) );
		add_action( 'wp_ajax_everest_process_status', array( $this, 'process_status' ) );

		add_action( 'wp_ajax_nopriv_everest_backup_cloud_available_storage', array( $this, 'cloud_available_storage' ) );
		add_action( 'wp_ajax_everest_backup_cloud_available_storage', array( $this, 'cloud_available_storage' ) );

		add_action( 'wp_ajax_nopriv_everest_backup_process_status_unlink', array( $this, 'process_status_unlink' ) );
		add_action( 'wp_ajax_everest_backup_process_status_unlink', array( $this, 'process_status_unlink' ) );

		add_action( 'wp_ajax_nopriv_everest_backup_activate_plugin', array( $this, 'activate_plugin' ) );
		add_action( 'wp_ajax_everest_backup_activate_plugin', array( $this, 'activate_plugin' ) );
	}

	/**
	 * Send process status.
	 *
	 * @return void
	 */
	public function process_status() {
		wp_send_json( Logs::get_proc_stat() );
	}

	/**
	 * Unlink process status file after process complete.
	 *
	 * @return void
	 */
	public function process_status_unlink() {
		if ( file_exists( EVEREST_BACKUP_PROC_STAT_PATH ) ) {
			unlink( EVEREST_BACKUP_PROC_STAT_PATH ); // @phpcs:ignore
		}
		die;
	}

	/**
	 * Install and activate free addon from the addon page.
	 *
	 * @return void
	 */
	public function install_addon() {

		$plugins_dir = WP_PLUGIN_DIR;
		$response    = everest_backup_get_ajax_response( 'everest_backup_addon' );

		$addon_category = ! empty( $response['addon_category'] ) ? $response['addon_category'] : '';
		$addon_slug     = ! empty( $response['addon_slug'] ) ? $response['addon_slug'] : '';

		$addon_info = everest_backup_addon_info( $addon_category, $addon_slug );

		$package = $addon_info['package'];

		$plugin_folder = $plugins_dir . DIRECTORY_SEPARATOR . $addon_slug;
		$plugin_zip    = $plugin_folder . '.zip';
		$plugin        = $addon_slug . '/' . $addon_slug . '.php';

		$data = wp_remote_get(
			$package,
			array(
				'sslverify' => false,
			)
		);

		$content = wp_remote_retrieve_body( $data );

		if ( ! $content ) {
			wp_send_json_error();
		}

		if ( file_exists( $plugin_zip ) ) {
			unlink( $plugin_zip ); // @phpcs:ignore
		}

		Filesystem::init()->writefile( $plugin_zip, $content );

		if ( ! file_exists( $plugin_zip ) ) {
			wp_send_json_error();
		}

		if ( is_dir( $plugin_folder ) ) {
			/**
			 * Plugin directory already exists, then delete the existing plugin directory first.
			 */
			Filesystem::init()->delete( $plugin_folder, true );
		}

		unzip_file( $plugin_zip, $plugins_dir );

		everest_backup_activate_ebwp_addon( $plugin );

		unlink( $plugin_zip );// @phpcs:ignore

		wp_send_json_success();
	}

	/**
	 * Return available storage.
	 */
	public function cloud_available_storage() {
		$response = everest_backup_get_ajax_response( 'everest_backup_cloud_available_storage' );

		$storage_available = array();
		if ( ! empty( $response['cloud_info'] ) ) {
			$cloud_info = json_decode( urldecode( $response['cloud_info'] ) );
			if ( ! empty( $cloud_info ) && is_array( $cloud_info ) ) {
				foreach ( $cloud_info as $cloud ) {
					$storage_available[ $cloud ] = $this->get_available_storage( $cloud );
				}
			}
		}

		wp_send_json_success( $storage_available );
	}

	/**
	 * Get available storage.
	 *
	 * @param string $cloud Cloud name.
	 *
	 * @throws \Exception Class not found exception.
	 */
	private function get_available_storage( $cloud ) {
		switch ( $cloud ) {
			case 'pcloud':
				if ( class_exists( 'Everest_Backup_Pcloud\Everest_Backup_Pcloud_Upload' ) ) {
					try {
						$pcloud = new \Everest_Backup_Pcloud\Everest_Backup_Pcloud_Upload();
						if ( empty( $pcloud ) ) {
							return 0;
						}
						$pcloud->calculate_available_space();
						return $pcloud->space_available;
					} catch ( \Exception $e ) {
						return 0;
					}
				} else {
					throw new \Exception( 'Class not found: (Everest_Backup_Pcloud\Everest_Backup_Pcloud_Upload)' );
				}
				break;
			case 'google-drive':
				if ( class_exists( 'Everest_Backup_Google_Drive\GDrive_Handler' ) ) {
					try {
						$gdrive        = new \Everest_Backup_Google_Drive\GDrive_Handler();
						$storage_quota = $gdrive->is_space_available_for_upload( 0 );
						if ( empty( $storage_quota ) ) {
							return 0;
						}
						return absint( $storage_quota );
					} catch ( \Exception $e ) {
						return 0;
					}
				} else {
					throw new \Exception( 'Class not found: (Everest_Backup_Google_Drive\Drive_Handler)' );
				}
				break;
			case 'dropbox':
				if ( class_exists( 'Everest_Backup_Dropbox\Dropbox_Handler' ) ) {
					try {
						$storage_usage = \Everest_Backup_Dropbox\Dropbox_Handler::init()->get_space_usage();
						if ( empty( $storage_usage ) ) {
							return 0;
						}
						return absint( $storage_usage['allocation']['allocated'] ) - absint( $storage_usage['used'] );
					} catch ( \Exception $e ) {
						return 0;
					}
				} else {
					throw new \Exception( 'Class not found: (Everest_Backup_Dropbox\Dropbox_Handler)' );
				}
				break;
			case 'onedrive':
				if ( class_exists( 'Everest_Backup_OneDrive\OneDrive_Handler' ) ) {
					try {
						$storage_quota = \Everest_Backup_OneDrive\OneDrive_Handler::init();
						if ( empty( $storage_quota ) ) {
							return 0;
						}
						return $storage_quota->get_available_storage();
					} catch ( \Exception $e ) {
						return 0;
					}
				} else {
					throw new \Exception( 'Class not found: (Everest_Backup_OneDrive\OneDrive_Handler)' );
				}
			case 'aws-amazon-s3':
				return 5497558138880;
			default:
				return -1;
		}
	}


	/**
	 * ====================================
	 *
	 * Restore/Rollback/Clone related methods.
	 *
	 * ====================================
	 */




	/**
	 * Init cloning process.
	 *
	 * @param array $response Ajax response.
	 * @return void
	 * @throws \Exception Required functions exception.
	 */
	public function clone_init( $response ) {

		if ( ! everest_backup_doing_clone() && array_key_exists( 'cloud', $response ) && 'pcloud' !== $response['cloud'] ) {
			return;
		}

		$disabled_functions = everest_backup_is_required_functions_enabled();

		if ( is_array( $disabled_functions ) ) {
			throw new \Exception( esc_html( sprintf( 'Everest Backup required functions disabled: %s', implode( ', ', $disabled_functions ) ) ) );
		}

		if ( empty( $response['download_url'] ) ) {
			$message = __( 'Clone failed because package download url is missing.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'Downloading the file from the host site.', 'everest-backup' ) );

		$everest_backup_cloner = new Cloner();
		$file                  = $everest_backup_cloner->handle_package_clone( $response );

		if ( ! $file ) {
			$message = __( 'Failed to download the file from the host site.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'File downloaded successfully.', 'everest-backup' ) );
	}


	/**
	 * Pre restore method, works for uploading package.
	 *
	 * @return void
	 */
	public function upload_package() {

		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		if ( 'blob' === $_FILES['file']['name'] ) { // @phpcs:ignore
			if ( 'ebwp' !== pathinfo( $_POST['name'], PATHINFO_EXTENSION ) ) { // @phpcs:ignore
				$message = __( 'The current uploaded file seems to be tampered with.', 'everest-backup' );
				Logs::error( $message );
				everest_backup_send_error( $message );
			}
		} elseif ( 'ebwp' !== pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) ) { // @phpcs:ignore
			$message = __( 'The current uploaded file seems to be tampered with.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_get_ajax_response( EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION );

		$package = new File_Uploader(
			array(
				'form'      => 'file',
				'urlholder' => 'ebwp_package',
			)
		);

		wp_send_json( $package );
	}

	/**
	 * Save uploaded package.
	 */
	public function save_uploaded_package() {

		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION );

		if ( empty( $response['package'] ) ) {
			everest_backup_send_json();
		}

		Backup_Directory::init()->create();

		$package = new File_Uploader( $response );

		if ( empty( $package->filename ) ) {
			everest_backup_send_json( false );
		}

		$dest = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/' . $package->filename );

		everest_backup_send_json( $package->move( $dest ) );
	}

	/**
	 * Delete uploaded package.
	 *
	 * @return void
	 */
	public function remove_uploaded_package() {

		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION );

		if ( empty( $response['package'] ) ) {
			everest_backup_send_json();
		}

		$package = new File_Uploader( $response );

		$package->cleanup();

		wp_send_json( $package );
	}

	/**
	 * Initialize import.
	 *
	 * @return void
	 */
	public function import_files() {

		if ( ! everest_backup_doing_clone() ) {
			if ( everest_backup_doing_rollback() ) {
				Logs::init( 'rollback' );
			} else {
				Logs::init( 'restore' );
			}
		} else {
			Logs::init( 'clone' );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_IMPORT_ACTION );

		$timer_start = time();

		/**
		 * Action just before restore starts.
		 * Useful for the cloud modules for downloading files and set process status.
		 *
		 * @param array $response Ajax response.
		 *
		 * @since 1.0.7
		 */
		do_action( 'everest_backup_before_restore_init', $response );

		/* translators: %s is the restore start time. */
		Logs::info( sprintf( __( 'Restore started at: %s', 'everest-backup' ), wp_date( 'h:i:s A', $timer_start ) ) );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 5,
				'message'  => __( 'Extracting package', 'everest-backup' ),
			)
		);

		$extract = new Extract( $response ); // @phpcs:ignore

		Restore_Config::init( $extract );
		Restore_Multisite::init( $extract );
		Restore_Database::init( $extract );
		Restore_Users::init( $extract );
		Restore_Uploads::init( $extract );
		Restore_Themes::init( $extract );
		Restore_Plugins::init( $extract );
		Restore_Content::init( $extract );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 92,
				'message'  => __( 'Cleaning remaining extracted files', 'everest-backup' ),
			)
		);

		$extract->clean_storage_dir();

		/* translators: %s is the restore completed time. */
		Logs::info( sprintf( __( 'Restore completed at: %s', 'everest-backup' ), wp_date( 'h:i:s A' ) ) );

		/* translators: %s is the total restore time. */
		Logs::info( sprintf( __( 'Total time: %s', 'everest-backup' ), human_time_diff( $timer_start ) ) );

		Logs::done( __( 'Restore completed.', 'everest-backup' ) );

		do_action( 'everest_backup_after_restore_done', $response );

		everest_backup_send_success();
	}

	/**
	 * Activate plugin.
	 */
	public function activate_plugin() {
		$request = $_GET; // @phpcs:ignore
		if ( empty( $request['nonce'] ) ) {
			http_response_code( 500 );
			wp_send_json_error( 'Unauthorized' );
		}
		$nonce = sanitize_text_field( wp_unslash( $request['nonce'] ) );
		if ( get_option( 'everest_backup_enable_plugin_token' ) !== $nonce ) {
			http_response_code( 403 );
			wp_send_json_error();
		}
		$plugin = isset( $request['plugin'] ) ? sanitize_text_field( wp_unslash( $request['plugin'] ) ) : '';
		if ( ! empty( $plugin ) ) {
			$active = activate_plugin( $plugin );
			if ( ! is_wp_error( $active ) ) {
				http_response_code( 200 );
				wp_send_json_success();
			}
		}
		http_response_code( 500 );
		wp_send_json_error();
	}
}

new Ajax();
