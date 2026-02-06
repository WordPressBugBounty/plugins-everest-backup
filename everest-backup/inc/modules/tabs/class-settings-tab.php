<?php
/**
 * Class for creating the tabs in settings page.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Server_Information;
use Everest_Backup\Tabs_Factory;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for creating the tabs in settings page.
 *
 * @since 1.0.0
 */
class Settings_Tab extends Tabs_Factory {

	/**
	 * Arguments for the settings callbacks.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $settings_args = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->settings_args = array(
			'settings'          => everest_backup_get_settings(),
			'backup_excludes'   => everest_backup_get_backup_excludes(),
			'cron_cycles'       => everest_backup_cron_cycles(),
			'package_locations' => everest_backup_package_locations(),
		);

		parent::__construct();
	}

	/**
	 * Set tab items array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function set_items() {
		$items = array(
			'general'     => array(
				'label'    => __( 'General', 'everest-backup' ),
				'callback' => array( $this, 'general_cb' ),
				'priority' => 10,
			),
			'cloud'       => array(
				'label'    => __( 'Cloud', 'everest-backup' ),
				'callback' => array( $this, 'cloud_cb' ),
				'priority' => 30,
			),
			'information' => array(
				'label'    => __( 'Information', 'everest-backup' ),
				'callback' => array( $this, 'information_cb' ),
				'priority' => 40,
			),
		);

		if ( everest_backup_is_debug_on() ) {
			$items['debug'] = array(
				'label'    => __( 'Debug', 'everest-backup' ),
				'callback' => array( $this, 'debug_cb' ),
				'priority' => 50,
			);
		}

		return $items;
	}

	/**
	 * Callback for general tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function general_cb() {
		everest_backup_render_view( 'settings/general', $this->settings_args );
	}

	/**
	 * Callback for cloud tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function cloud_cb() {
		everest_backup_render_view( 'settings/cloud', $this->settings_args );
	}

	/**
	 * Callback for information tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function information_cb() {
		Server_Information::init()->display();
	}

	/**
	 * Callback for debug tab.
	 *
	 * @return void
	 * @since 1.0.5
	 */
	public function debug_cb() {
		everest_backup_render_view( 'settings/debug', $this->settings_args );
	}
}
