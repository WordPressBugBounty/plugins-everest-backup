<?php
/**
 * Plugin updater class for updating Everest Backup addons.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

use stdClass;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin updater class for updating Everest Backup addons.
 *
 * @since 1.0.2
 */
class Updater {

	/**
	 * List of fetched addons.
	 *
	 * @var array
	 */
	public $fetched_addons = array();

	/**
	 * List of installed addons.
	 *
	 * @var array
	 */
	public $installed_addons = array();

	/**
	 * Init class.
	 */
	public function __construct() {

		$this->fetched_addons   = everest_backup_fetch_addons();
		$this->installed_addons = everest_backup_installed_addons();

		$this->init();
	}

	/**
	 * Get addons with its data.
	 *
	 * @return array
	 */
	protected function get_addons() {
		$addons = array();

		$fetched_addons = ! empty( $this->fetched_addons['data'] ) ? $this->fetched_addons['data'] : '';

		if ( ! is_array( $fetched_addons ) ) {
			return $addons;
		}

		$remote_addons = array();

		if ( ! empty( $fetched_addons ) && is_array( $fetched_addons ) ) {
			foreach ( $fetched_addons as $fetched_addon ) {
				$remote_addons = array_merge( $remote_addons, $fetched_addon );
			}
		}

		if ( is_array( $this->installed_addons ) && ! empty( $this->installed_addons ) ) {
			foreach ( $this->installed_addons as $installed_addon ) {
				$slug = basename( $installed_addon, '.php' );
				$info = isset( $remote_addons[ $slug ] ) ? $remote_addons[ $slug ] : array();

				if ( ! $info ) {
					continue;
				}

				if ( $info['is_premium'] ) {
					/**
					 * Ignore the premium addons for now.
					 */
					continue;
				}

				$info['slug']              = $slug;
				$info['plugin']            = $installed_addon;
				$info['installed_version'] = ( get_file_data( WP_PLUGIN_DIR . '/' . $installed_addon, array( 'Version' => 'Version' ) )['Version'] );

				$addons[] = $info;

			}
		}

		return $addons;
	}

	/**
	 * Init upgrade process.
	 *
	 * @return void
	 */
	protected function init() {
		$request = everest_backup_get_submitted_data();
		$addons  = $this->get_addons();

		if ( ! empty( $request['action'] ) && ! empty( $request['plugin'] ) ) {
			if ( ( 'update-plugin' === $request['action'] ) && in_array( $request['plugin'], $this->installed_addons, true ) ) {
				add_filter( 'https_ssl_verify', '__return_false' );
			}
		}

		if ( is_array( $addons ) && ! empty( $addons ) ) {
			foreach ( $addons as $addon ) {

				add_filter(
					'plugins_api',
					function ( $res, $action, $args ) use ( $addon ) {

						// Do nothing if this is not about getting plugin information.
						if ( 'plugin_information' !== $action ) {
							return false;
						}

						// Do nothing if it is not our plugin.
						if ( $addon['slug'] !== $args->slug ) {
							return false;
						}

						$res = new stdClass();

						$res->name          = $addon['name'];
						$res->slug          = $addon['slug'];
						$res->version       = $addon['version'];
						$res->download_link = $addon['package'];
						$res->sections      = array(
							'description' => ! empty( $addon['description'] ) ? $addon['description'] : '',
							'changelog'   => ! empty( $addon['changelog'] ) ? $addon['changelog'] : '',
						);

						return $res;
					},
					20,
					3
				);

				add_filter(
					'site_transient_update_plugins',
					function ( $transient ) use ( $addon ) {

						if ( empty( $transient->checked ) ) {
							return $transient;
						}

						if ( ! version_compare( $addon['installed_version'], $addon['version'], '<' ) ) {
							return $transient;
						}

						$res              = new stdClass();
						$res->slug        = $addon['slug'];
						$res->plugin      = $addon['plugin'];
						$res->new_version = $addon['version'];
						$res->package     = $addon['package'];

						$transient->response[ $res->plugin ] = $res;

						return $transient;
					}
				);

			}
		}
	}
}

new Updater();
