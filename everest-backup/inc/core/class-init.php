<?php

/**
 * =============================================================================
 *
 * Initialize our all the functionalities from the core folder.
 * Since Everest Backup version 2.0.0, we have changed our core architecture.
 * All the new codes and architecture will be inside core folder.
 *
 * =============================================================================
 *
 * @since 2.0.0
 * @package Everest_Backup
 */

namespace Everest_Backup\Core;

use Everest_Backup\Traits\Singleton;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core directory initializer.
 *
 * @since 2.0.0
 */
class Init {


	use Singleton;

	/**
	 * Core path.
	 *
	 * @var $core_path
	 */
	protected $core_path;

	/**
	 * Init class.
	 */
	public function __construct() {
		$this->core_path = plugin_dir_path( __FILE__ );

		$this->constants();
		$this->includes();
		$this->hooks();
	}

	/**
	 * Define constants for core directory.
	 *
	 * @return void
	 */
	protected function constants() {

		if ( ! defined( 'EVEREST_BACKUP_CORE_DIR_PATH' ) ) {

			/**
			 * Core directory path.
			 */
			define( 'EVEREST_BACKUP_CORE_DIR_PATH', $this->core_path );
		}
	}

	/**
	 * Include core directory files.
	 *
	 * @return void
	 */
	protected function includes() {
		$files = array(
			'class-archiver.php',
			'class-archiver-v2.php',
			'class-export.php',
			'class-import.php',
			'class-api.php',
		);

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {
				require_once EVEREST_BACKUP_CORE_DIR_PATH . $file;
			}
		}
	}

	/**
	 * Initialize required hooks.
	 *
	 * @return void
	 */
	protected function hooks() {
		add_action( 'wp_ajax_' . EVEREST_BACKUP_EXPORT_ACTION, '\Everest_Backup\Core\Export::init' );

		// Add both authenticated and non-authenticated handlers for import
		// This allows restore to continue even after user logout (which happens during database restore)
		add_action( 'wp_ajax_' . EVEREST_BACKUP_IMPORT_ACTION, array( $this, 'import_with_auth_check' ) );
		add_action( 'wp_ajax_nopriv_' . EVEREST_BACKUP_IMPORT_ACTION, array( $this, 'import_with_auth_check' ) );
		add_action( 'rest_api_init', '\Everest_Backup\Core\API::init' );

		add_action(
			'wp_ajax_' . EVEREST_BACKUP_PROCESS_RUNNING,
			function () {
				echo wp_json_encode( array( 'process_already_running' => everest_backup_process_running_currently() ) );
				die;
			}
		);
	}

	/**
	 * Import with authentication check.
	 * Validates either admin capability or restore token before allowing import.
	 * This allows the restore process to continue even after user logout.
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public function import_with_auth_check() {
		// Check if user is admin with valid nonce
		$is_admin = current_user_can( 'manage_options' ) &&
			wp_verify_nonce( $_REQUEST['everest_backup_ajax_nonce'] ?? '', 'everest_backup_ajax_nonce' );

		// Check for valid restore token if not admin
		$restore_token = isset( $_REQUEST['restore_token'] ) ?
			sanitize_text_field( wp_unslash( $_REQUEST['restore_token'] ) ) : '';

		$has_valid_token = false;
		if ( ! $is_admin && $restore_token ) {
			$has_valid_token = $this->validate_restore_token( $restore_token );
		}

		// Deny access if neither condition is met
		if ( ! $is_admin && ! $has_valid_token ) {
			\Everest_Backup\Logs::error( 'Unauthorized import attempt - no valid credentials or token' );
			wp_send_json_error(
				array(
					'message' => __( 'Unauthorized. You must be logged in as admin or provide a valid restore token.', 'everest-backup' ),
					'code'    => 'unauthorized',
				),
				403
			);
			die;
		}

		// Log authentication method used
		if ( $is_admin ) {
			\Everest_Backup\Logs::info( 'Import authenticated via admin credentials' );
		} else {
			\Everest_Backup\Logs::info( 'Import authenticated via restore token (user logged out)' );
		}

		// Proceed with import
		\Everest_Backup\Core\Import::init();
	}

	/**
	 * Validate the provided restore token against the stored file.
	 *
	 * @param string $token The token to validate.
	 * @return bool True if valid, false otherwise.
	 * @since 2.0.0
	 */
	private function validate_restore_token( $token ) {

		if ( ! defined( 'EVEREST_BACKUP_RESTORE_TOKEN_PATH' ) ) {
			return false;
		}

		if ( ! file_exists( EVEREST_BACKUP_RESTORE_TOKEN_PATH ) ) {
			return false;
		}

		$stored_token = file_get_contents( EVEREST_BACKUP_RESTORE_TOKEN_PATH );

		// Use hash_equals to prevent timing attacks
		return hash_equals( $stored_token, $token );
	}
}
