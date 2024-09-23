<?php
/**
 * Controller class for Everest Backup access tokens.
 *
 * @since 2.1.1
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Controllers\V1;

use Everest_Backup\Core\Controllers\Base;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller class for Everest Backup access tokens.
 *
 * @since 2.1.1
 */
class Access_Token_Controller extends Base {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$this->register_rest_route(
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function create_item( $request ) {
		$action = sanitize_text_field( wp_unslash( $request->get_param( 'action' ) ) );

		if ( ! $action ) {
			return $this->error( __( 'Action cannot be empty. Please provide a valid "action" to generate access token.', 'everest-backup' ), 400 );
		}

		$key   = sanitize_title( $action . '-' . get_userdata( get_current_user_id() )->user_login . '-' . time() );
		$hash  = wp_hash( $key, 'nonce' );
		$nonce = wp_create_nonce( $hash );

		return array(
			'action'       => $action,
			'access_token' => $nonce . ':' . $hash,
		);
	}
}
