<?php
/**
 * Controller class for manual backup.
 *
 * @since 2.1.1
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
 * Controller class for manual backup.
 *
 * @since 2.1.1
 */
class Manual_Backup_Controller extends Base {

	/**
	 * Safe valid fields.
	 */
	protected $valid_fields = array(
		'ignore_database',
		'ignore_plugins',
		'ignore_themes',
		'ignore_media',
		'ignore_content',
		'custom_name_tag',
	);

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$this->register_rest_route(
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' )
				),
			),
		);
	}

	/**
	 * Validate the passed fields to make sure only safe fields are passed into parameters.
	 *
	 * @param array $fields
	 * @return array
	 */
	protected function validate_fields( $fields ) {

		$filtered = array();

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			foreach ( $fields as $key => $value ) {
				if ( in_array( $key, $this->valid_fields, true ) ) {
					if ( false !== strpos( $key, 'ignore_' ) ) {
						$filtered[ $key ] = 'yes' === sanitize_text_field( wp_unslash( $value ) ) ? 1 : 0;
					} else {
						$filtered[ $key ] = sanitize_text_field( wp_unslash( $value ) );
					}
				}
			}
		}

		return $filtered;
	}

	/**
	 * {@inheritDoc}
	 */
	public function create_item( $request ) {
		$params = $request->get_params();

		everest_backup_compress_init(
			array(
				'params'            => wp_parse_args(
					$this->validate_fields( $params ),
					array(
						'ignore_database'    => 1,
						'ignore_plugins'     => 1,
						'ignore_themes'      => 1,
						'ignore_media'       => 1,
						'ignore_content'     => 1,
						'delete_from_server' => 0,
						'custom_name_tag'    => '',
						'save_to'            => 'server',
						'cloud'              => 'server',
					)
				),
				'disable_send_json' => true,
			)
		);

		return json_decode( @file_get_contents( EVEREST_BACKUP_PROC_STAT_PATH ), true );

	}

}
