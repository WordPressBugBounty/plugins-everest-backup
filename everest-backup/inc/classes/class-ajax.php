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
	 * Initialize AJAX handlers and register WordPress action hooks.
	 *
	 * Registers all AJAX endpoints for backup, restore, addon management,
	 * and cloud storage operations. Some actions are available to both
	 * authenticated and non-authenticated users (nopriv) for specific workflows.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {

		// Addon installation and activation.
		add_action( 'wp_ajax_everest_backup_addon', array( $this, 'install_addon' ) );

		// Package upload handlers for restore/rollback operations.
		add_action( 'wp_ajax_' . EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION, array( $this, 'upload_package' ) );
		add_action( 'wp_ajax_' . EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION, array( $this, 'save_uploaded_package' ) );
		add_action( 'wp_ajax_' . EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION, array( $this, 'remove_uploaded_package' ) );

		// Hook for initializing clone process before restore.
		add_action( 'everest_backup_before_restore_init', array( $this, 'clone_init' ) );

		// Process status monitoring (available to both authenticated and non-authenticated users).
		add_action( 'wp_ajax_nopriv_everest_process_status', array( $this, 'process_status' ) );
		add_action( 'wp_ajax_everest_process_status', array( $this, 'process_status' ) );

		// Cloud storage availability check (available to both authenticated and non-authenticated users).
		add_action( 'wp_ajax_nopriv_everest_backup_cloud_available_storage', array( $this, 'cloud_available_storage' ) );
		add_action( 'wp_ajax_everest_backup_cloud_available_storage', array( $this, 'cloud_available_storage' ) );

		// Security Note: nopriv action is disabled to prevent non-privileged users from unlinking process status files.
		// add_action( 'wp_ajax_nopriv_everest_backup_process_status_unlink', array( $this, 'process_status_unlink' ) );

		// Process status file cleanup (admin only).
		add_action( 'wp_ajax_everest_backup_process_status_unlink', array( $this, 'process_status_unlink' ) );

		// Security Note: nopriv action is disabled to prevent non-privileged users from activating plugins.
		// add_action( 'wp_ajax_nopriv_everest_backup_activate_plugin', array( $this, 'activate_plugin' ) );

		// Plugin activation handler (admin only).
		add_action( 'wp_ajax_everest_backup_activate_plugin', array( $this, 'activate_plugin' ) );

		// Staging site creation (available to both authenticated and non-authenticated users).
		add_action( 'wp_ajax_nopriv_everest_backup_create_new_staging', array( $this, 'create_new_staging' ) );
		add_action( 'wp_ajax_everest_backup_create_new_staging', array( $this, 'create_new_staging' ) );
	}

	/**
	 * Retrieve and send the current process status.
	 *
	 * This endpoint returns the current backup/restore process status including
	 * progress percentage and status messages. Access is granted to:
	 * 1. Administrators with valid nonce
	 * 2. Any user if restore is completed (for post-restore verification)
	 *
	 * @return void
	 */
	public function process_status() {
		// Retrieve process logs and authentication tokens.
		$logs                 = Logs::get_proc_stat();
		$is_restore_completed = get_transient( 'is_restore_completed' );

		// Check if user is admin with valid nonce.
		$can_access = current_user_can( 'manage_options' ) && wp_verify_nonce( $_GET['everest_backup_ajax_nonce'], 'everest_backup_ajax_nonce' );

		// Allow access for verification only within 1 minute after the restore completes; otherwise, it remains false or undefined. This temporary access lets us display the “Restore Completed” message even though users are logged out at the end of the process—without it, the UI would get stuck at the final step.
		if ( ! $can_access ) {
			$can_access = $is_restore_completed;
		}

		// Deny access if neither condition is met.
		if ( ! $can_access ) {
			wp_send_json_error( -1, 403 );
			return;
		}

		$logs = Logs::get_proc_stat();

		// Deleted the transient when send the success response to the user.
		if ( $logs['status'] ) {
			delete_transient( 'is_restore_completed' );
		}

		wp_send_json( $logs );
	}

	/**
	 * Clean up process status files and transients after backup/restore completion.
	 *
	 * Removes process status file, lock file, and temporary nonces.
	 * Only accessible to administrators or when CAN_DELETE_LOGS constant is true.
	 *
	 * @return void
	 */
	public function process_status_unlink() {
		check_ajax_referer( 'everest_backup_ajax_nonce', 'everest_backup_ajax_nonce' );

		// Verify user has permission to delete logs.

		if ( ! current_user_can( 'manage_options' ) ) {
			die;
		}

		// Removed is_restore_completed transient which can give access to the user to see the restore completed message.
		if ( get_transient( 'is_restore_completed' ) ) {
			delete_transient( 'is_restore_completed' );
		}

		// Delete process status file if it exists.
		if ( file_exists( EVEREST_BACKUP_PROC_STAT_PATH ) ) {
			@unlink( EVEREST_BACKUP_PROC_STAT_PATH ); // @phpcs:ignore
		}

		// Delete lock file if it exists.
		if ( file_exists( EVEREST_BACKUP_LOCKFILE_PATH ) ) {
			@unlink( EVEREST_BACKUP_LOCKFILE_PATH ); // @phpcs:ignore
		}

		// Prevent further log deletions.
		define( 'CAN_DELETE_LOGS', false );

		// Clean up REST API properties.
		everest_backup_unset_rest_properties();
		die;
	}

	/**
	 * Install and activate a free addon from the addon management page.
	 *
	 * Downloads the addon package from remote server, extracts it to the plugins
	 * directory, and activates it. Handles cleanup of existing installations.
	 *
	 * @return void
	 */
	public function install_addon() {

		$plugins_dir = WP_PLUGIN_DIR;
		$response    = everest_backup_get_ajax_response( 'everest_backup_addon' );

		// Extract addon information from AJAX request.
		$addon_category = ! empty( $response['addon_category'] ) ? $response['addon_category'] : '';
		$addon_slug     = ! empty( $response['addon_slug'] ) ? $response['addon_slug'] : '';

		// Retrieve addon metadata including download URL.
		$addon_info = everest_backup_addon_info( $addon_category, $addon_slug );

		$package = $addon_info['package'];

		// Define plugin paths.
		$plugin_folder = $plugins_dir . DIRECTORY_SEPARATOR . $addon_slug;
		$plugin_zip    = $plugin_folder . '.zip';
		$plugin        = $addon_slug . '/' . $addon_slug . '.php';

		// Download addon package from remote server.
		$data = wp_remote_get(
			$package,
			array(
				'sslverify' => false,
			)
		);

		$content = wp_remote_retrieve_body( $data );

		// Verify download was successful.
		if ( ! $content ) {
			wp_send_json_error();
		}

		// Remove old zip file if it exists.
		if ( file_exists( $plugin_zip ) ) {
			unlink( $plugin_zip ); // @phpcs:ignore
		}

		// Write downloaded content to zip file.
		Filesystem::init()->writefile( $plugin_zip, $content );

		// Verify zip file was created successfully.
		if ( ! file_exists( $plugin_zip ) ) {
			wp_send_json_error();
		}

		// Remove existing plugin directory to prevent conflicts.
		if ( is_dir( $plugin_folder ) ) {
			Filesystem::init()->delete( $plugin_folder, true );
		}

		// Extract plugin files.
		unzip_file( $plugin_zip, $plugins_dir );

		// Activate the newly installed addon.
		everest_backup_activate_ebwp_addon( $plugin );

		// Clean up zip file.
		unlink( $plugin_zip );// @phpcs:ignore

		wp_send_json_success();
	}

	/**
	 * Retrieve available storage information for configured cloud providers.
	 *
	 * Checks available storage space for multiple cloud storage providers
	 * (pCloud, Google Drive, Dropbox, OneDrive, AWS S3) and returns the data.
	 *
	 * @return void
	 */
	public function cloud_available_storage() {
		$response = everest_backup_get_ajax_response( 'everest_backup_cloud_available_storage' );

		$storage_available = array();

		// Parse cloud provider information from request.
		if ( ! empty( $response['cloud_info'] ) ) {
			$cloud_info = json_decode( urldecode( $response['cloud_info'] ) );

			// Query storage availability for each cloud provider.
			if ( ! empty( $cloud_info ) && is_array( $cloud_info ) ) {
				foreach ( $cloud_info as $cloud ) {
					$storage_available[ $cloud ] = $this->get_available_storage( $cloud );
				}
			}
		}

		wp_send_json_success( $storage_available );
	}

	/**
	 * Calculate available storage space for a specific cloud provider.
	 *
	 * Queries the cloud provider's API to determine remaining storage capacity.
	 * Returns storage in bytes, 0 on error, or -1 for unknown providers.
	 *
	 * @param string $cloud Cloud provider name (pcloud, google-drive, dropbox, onedrive, aws-amazon-s3).
	 * @return int Available storage in bytes.
	 * @throws \Exception If required cloud provider class is not found.
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
				// AWS S3 has virtually unlimited storage (returning 5TB as placeholder).
				return 5497558138880;
			default:
				// Unknown cloud provider.
				return -1;
		}
	}

	/**
	 * Create a new staging site for testing purposes.
	 *
	 * Sends site information (PHP version, WordPress version, site URL) to the
	 * staging server to provision a new staging environment.
	 *
	 * @since 2.3.1
	 * @return void
	 */
	public function create_new_staging() {
		// Verify user has admin privileges.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// Collect site environment information.
		$php_version = phpversion();
		$wp_version  = get_bloginfo( 'version' );
		$site_url    = site_url();

		$request = array(
			'php_version' => $php_version,
			'wp_version'  => $wp_version,
			'site_url'    => $site_url,
		);

		// Send request to staging server.
		$response = wp_remote_post( EVEREST_BACKUP_STAGING_LINK, array( 'body' => $request ) );

		$response_data = wp_remote_retrieve_body( $response );

		wp_send_json_success( $response_data );
	}


	/**
	 * ====================================
	 *
	 * Restore/Rollback/Clone related methods.
	 *
	 * ====================================
	 */




	/**
	 * Initialize the site cloning process.
	 *
	 * Downloads the backup package from the source site to enable cloning.
	 * Validates required PHP functions are enabled and download URL is present.
	 * This method is triggered before the restore process begins.
	 *
	 * @param array $response AJAX response containing clone configuration and download URL.
	 * @return void
	 * @throws \Exception If required PHP functions are disabled or download fails.
	 */
	public function clone_init( $response ) {

		// Skip if not cloning or if using non-pCloud cloud storage.
		if ( ! everest_backup_doing_clone() && array_key_exists( 'cloud', $response ) && 'pcloud' !== $response['cloud'] ) {
			return;
		}

		// Verify required PHP functions are enabled.
		$disabled_functions = everest_backup_is_required_functions_enabled();

		if ( is_array( $disabled_functions ) ) {
			throw new \Exception( esc_html( sprintf( 'Everest Backup required functions disabled: %s', implode( ', ', $disabled_functions ) ) ) );
		}

		// Validate download URL is present.
		if ( empty( $response['download_url'] ) ) {
			$message = __( 'Clone failed because package download url is missing.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'Downloading the file from the host site.', 'everest-backup' ) );

		// Download backup package from source site.
		$everest_backup_cloner = new Cloner();
		$file                  = $everest_backup_cloner->handle_package_clone( $response );

		// Verify download was successful.
		if ( ! $file ) {
			$message = __( 'Failed to download the file from the host site.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		Logs::info( __( 'File downloaded successfully.', 'everest-backup' ) );
	}


	/**
	 * Handle backup package upload for restore/rollback operations.
	 *
	 * Validates user permissions, verifies file extension is .ebwp,
	 * and processes the uploaded backup package file.
	 *
	 * @return void
	 */
	public function upload_package() {

		// Verify user has upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		// Validate file extension for blob uploads.
		if ( 'blob' === $_FILES['file']['name'] ) { // @phpcs:ignore
			if ( 'ebwp' !== pathinfo( $_POST['name'], PATHINFO_EXTENSION ) ) { // @phpcs:ignore
				$message = __( 'The current uploaded file seems to be tampered with.', 'everest-backup' );
				Logs::error( $message );
				everest_backup_send_error( $message );
			}
		} elseif ( 'ebwp' !== pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION ) ) { // @phpcs:ignore
			// Validate file extension for regular uploads.
			$message = __( 'The current uploaded file seems to be tampered with.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_get_ajax_response( EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION );

		// Initialize file uploader.
		$package = new File_Uploader(
			array(
				'form'      => 'file',
				'urlholder' => 'ebwp_package',
			)
		);

		wp_send_json( $package );
	}

	/**
	 * Save the uploaded backup package to the backup directory.
	 *
	 * Moves the temporary uploaded file to the permanent backup storage location.
	 * Creates the backup directory if it doesn't exist.
	 *
	 * @return void
	 */
	public function save_uploaded_package() {

		// Verify user has upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION );

		// Validate package data is present.
		if ( empty( $response['package'] ) ) {
			everest_backup_send_json();
		}

		// Ensure backup directory exists.
		Backup_Directory::init()->create();

		$package = new File_Uploader( $response );

		// Verify filename was generated.
		if ( empty( $package->filename ) ) {
			everest_backup_send_json( false );
		}

		// Define destination path and move file.
		$dest = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/' . $package->filename );

		everest_backup_send_json( $package->move( $dest ) );
	}

	/**
	 * Remove an uploaded backup package and clean up temporary files.
	 *
	 * Deletes the uploaded package file and any associated temporary data.
	 * Used when user cancels upload or needs to remove a package.
	 *
	 * @return void
	 */
	public function remove_uploaded_package() {

		// Verify user has upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION );

		// Validate package data is present.
		if ( empty( $response['package'] ) ) {
			everest_backup_send_json();
		}

		$package = new File_Uploader( $response );

		// Remove package and temporary files.
		$package->cleanup();

		wp_send_json( $package );
	}

	/**
	 * Initialize and execute the backup import/restore process.
	 *
	 * Main entry point for restore, rollback, and clone operations.
	 * Extracts the backup package and restores database, files, plugins,
	 * themes, uploads, and other WordPress components.
	 *
	 * @return void
	 */
	public function import_files() {

		// Initialize logging based on operation type.
		if ( ! everest_backup_doing_clone() ) {
			if ( everest_backup_doing_rollback() ) {
				Logs::init( 'rollback' );
			} else {
				Logs::init( 'restore' );
			}
		} else {
			Logs::init( 'clone' );
		}

		// Verify user has upload permissions.
		if ( ! current_user_can( 'upload_files' ) ) {
			$message = __( 'Current user does not have permission to upload files.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		everest_backup_setup_environment();

		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_IMPORT_ACTION );

		$timer_start = time();

		/**
		 * Hook: everest_backup_before_restore_init
		 *
		 * Fires before the restore process begins. Cloud storage modules
		 * use this to download backup files and update process status.
		 *
		 * @param array $response AJAX response containing restore configuration.
		 * @since 1.0.7
		 */
		do_action( 'everest_backup_before_restore_init', $response );

		/* translators: %s is the restore start time. */
		Logs::info( sprintf( __( 'Restore started at: %s', 'everest-backup' ), wp_date( 'h:i:s A', $timer_start ) ) );

		// Update progress: Starting extraction.
		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 5,
				'message'  => __( 'Extracting package', 'everest-backup' ),
			)
		);

		// Extract backup package.
		$extract = new Extract( $response ); // @phpcs:ignore

		// Restore WordPress components in sequence.
		Restore_Config::init( $extract );      // WordPress configuration.
		Restore_Multisite::init( $extract );   // Multisite settings.
		Restore_Database::init( $extract );    // Database tables.
		Restore_Users::init( $extract );       // User accounts.
		Restore_Uploads::init( $extract );     // Media files.
		Restore_Themes::init( $extract );      // Theme files.
		Restore_Plugins::init( $extract );     // Plugin files.
		Restore_Content::init( $extract );     // Content files.

		// Update progress: Cleanup phase.
		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 92,
				'message'  => __( 'Cleaning remaining extracted files', 'everest-backup' ),
			)
		);

		// Remove temporary extraction directory.
		$extract->clean_storage_dir();

		/* translators: %s is the restore completed time. */
		Logs::info( sprintf( __( 'Restore completed at: %s', 'everest-backup' ), wp_date( 'h:i:s A' ) ) );

		/* translators: %s is the total restore time. */
		Logs::info( sprintf( __( 'Total time: %s', 'everest-backup' ), human_time_diff( $timer_start ) ) );

		Logs::done( __( 'Restore completed.', 'everest-backup' ) );

		/**
		 * Hook: everest_backup_after_restore_done
		 *
		 * Fires after the restore process completes successfully.
		 *
		 * @param array $response AJAX response containing restore configuration.
		 */
		do_action( 'everest_backup_after_restore_done', $response );

		everest_backup_send_success();
	}

	/**
	 * Activate a WordPress plugin using a secure token.
	 *
	 * Used during restore operations to reactivate plugins that were
	 * active in the backup. Validates a one-time token for security.
	 *
	 * @return void
	 */
	public function activate_plugin() {
		$request = $_GET; // @phpcs:ignore

		// Verify nonce is present.
		if ( empty( $request['nonce'] ) ) {
			http_response_code( 500 );
			wp_send_json_error( 'Unauthorized' );
		}

		// Validate nonce against stored token.
		$nonce = sanitize_text_field( wp_unslash( $request['nonce'] ) );
		if ( get_option( 'everest_backup_enable_plugin_token' ) !== $nonce ) {
			http_response_code( 403 );
			wp_send_json_error();
		}

		// Sanitize and activate plugin.
		$plugin = isset( $request['plugin'] ) ? sanitize_text_field( wp_unslash( $request['plugin'] ) ) : '';
		if ( ! empty( $plugin ) ) {
			$active = activate_plugin( $plugin );
			if ( ! is_wp_error( $active ) ) {
				http_response_code( 200 );
				wp_send_json_success();
			}
		}

		// Activation failed.
		http_response_code( 500 );
		wp_send_json_error();
	}
}

new Ajax();
