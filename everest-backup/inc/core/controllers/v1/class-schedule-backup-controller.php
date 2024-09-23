<?php
/**
 * Controller class for schedule backup.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Controllers\V1;

use Everest_Backup\Core\Controllers\Base;
use Everest_Backup\Core\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedule backup controller.
 */
class Schedule_Backup_Controller extends Base {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes() {
		$this->register_rest_route(
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'stepper' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
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
	 * @param object $request Request.
	 */
	public function create_item_permissions_check( $request ) {
		return wp_verify_nonce( $request->get_param( 'everest_backup_ajax_nonce' ), 'everest_backup_ajax_nonce' );
	}

	/**
	 * Step function for backup create.
	 */
	public function stepper() {

		if ( class_exists( '\LiteSpeed\Purge' ) ) {
			do_action(
				'litespeed_control_set_nocache',
				'Running schedule backup.'
			);
		}

		$params = json_decode( @file_get_contents( EVEREST_BACKUP_PROC_STAT_PATH ), true ); // @phpcs:ignore

		if ( 'done' === $params['status'] ) {
			return;
		}

		if ( isset( $params['task'] ) && 'cloud' === $params['task'] ) {
			return;
		}

		$params['everest_backup_ajax_nonce'] = everest_backup_create_nonce( 'everest_backup_ajax_nonce' );

		wp_remote_post(
			rest_url( '/everest-backup/v1/schedule-backup' ),
			array(
				'body'      => $params,
				'timeout'   => 2,
				'blocking'  => false,
				'sslverify' => false,
				'headers'   => array(
					'Connection' => 'close',
				),
			)
		);

		wp_send_json(
			array(
				't' => microtime( true ) . wp_rand( 1000000000, 9999999999 ),
			)
		);

		die;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $request Request.
	 */
	public function create_item( $request ) {

		$params = $request->get_params();

		if ( isset( $params['task'] ) && 'cloud' === $params['task'] ) {
			return;
		}

		add_filter( 'everest_backup_disable_send_json', '__return_true' );

		Export::init( $params );

		wp_remote_get(
			add_query_arg(
				'everest_backup_ajax_nonce',
				everest_backup_create_nonce( 'everest_backup_ajax_nonce' ),
				rest_url( '/everest-backup/v1/schedule-backup' )
			)
		);

		die;
	}
}
