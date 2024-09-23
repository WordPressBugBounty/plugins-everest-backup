<?php
/**
 * Base class for controllers.
 */

namespace Everest_Backup\Core\Controllers;

use Everest_Backup\Proc_Lock;
use Everest_Backup\Transient;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for controllers.
 *
 * @since 2.1.0
 */
class Base extends \WP_REST_Controller {

	/**
	 * Class construct.
	 *
	 * @param string $namespace API namespace.
	 * @param string $rest_base API rest or route base.
	 */
	public function __construct( $namespace, $rest_base ) {
		$this->namespace = $namespace;
		$this->rest_base = $rest_base;

		$this->register_routes();
	}

	public function verify_access_token( $access_token ) {
		if ( ! $access_token ) {
			return false;
		}

		if ( false === strpos( $access_token, ':' ) ) {
			return false;
		}

		list( $nonce, $hash ) = explode( ':', $access_token );

		return wp_verify_nonce( $nonce, $hash );
	}

	/**
	 * Register Routes.
	 */
	public function register_rest_route( $args, $base = '' ) {
		$route = $this->rest_base;

		if ( ! empty( $base ) ) {
			$route .= "/{$base}";
		}

		register_rest_route( $this->namespace, $route, $args );
	}

	protected function error( $message, $status ) {
		return new \WP_Error( 'everest-backup-api-error-' . $status, $message, array( 'status' => $status ) );
	}

	/**
	 * Alias for permission check methods
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	protected function permission_checks_alias( $request ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error(
				__( 'Unauthorized access. You must be logged in or use WordPress application password for authorization.', 'everest-backup' ),
				401
			);
		}

		$access_token = sanitize_text_field( wp_unslash( $request->get_param( 'access_token' ) ) );

		if ( ! $access_token ) {
			return $this->error(
				__( 'Access token missing. You must provide a access_token field.', 'everest-backup' ),
				400
			);
		}

		if ( ! $this->verify_access_token( $access_token ) ) {
			return $this->error(
				__( 'Cannot verify access token. Access token either expired or invalid.', 'everest-backup' ),
				400
			);
		}

		if ( $request->get_method() !== \WP_REST_Server::READABLE ) {

			$proc_lock = Proc_Lock::get();

			if ( ! empty( $proc_lock['type'] ) ) {

				$types = everest_backup_get_process_types();

				return $this->error(
					sprintf( __( 'Everest Backup is currently doing %s. Please try again later.', 'everest-backup' ), esc_html( $types[ $proc_lock['type'] ] ) ),
					500
				);
			}
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_items_permissions_check( $request ) {
		return $this->permission_checks_alias( $request );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->permission_checks_alias( $request );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_item_permissions_check( $request ) {
		return $this->permission_checks_alias( $request );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->permission_checks_alias( $request );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->permission_checks_alias( $request );
	}
}
