<?php
/**
 * Class for creating the tabs in settings page.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

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
class Backup_Tab extends Tabs_Factory {

	/**
	 * Arguments for the settings callbacks.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $backup_args = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->backup_args = array(
			'settings'        => everest_backup_get_settings(),
			'backup_excludes' => everest_backup_get_backup_excludes(),
			'cron_cycles'     => everest_backup_cron_cycles(),
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
		return array(
			'manual_backup'   => array(
				'label'    => __( 'Manual Backup', 'everest-backup' ),
				'callback' => array( $this, 'manual_backup_cb' ),
				'priority' => 10,
			),
			'schedule_backup' => array(
				'label'    => __( 'Schedule Backup', 'everest-backup' ),
				'callback' => array( $this, 'schedule_backup_cb' ),
				'priority' => 20,
			),
		);
	}

	/**
	 * Callback for manual backup tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function manual_backup_cb() {
		everest_backup_render_view( 'backup/manual-backup', $this->backup_args );
	}

	/**
	 * Callback for schedule backup tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function schedule_backup_cb() {
		everest_backup_render_view( 'backup/schedule-backup', $this->backup_args );
	}
}
