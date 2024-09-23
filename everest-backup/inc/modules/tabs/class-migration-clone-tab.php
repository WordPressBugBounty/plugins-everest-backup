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
class Migration_Clone_Tab extends Tabs_Factory {

	/**
	 * Arguments for the settings callbacks.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $migration_clone_args = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->migration_clone_args = array(
			'request' => everest_backup_get_submitted_data(),
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
			'migration' => array(
				'label'    => __( 'Migration', 'everest-backup' ),
				'callback' => array( $this, 'migration_cb' ),
				'priority' => 10,
			),
			'clone'     => array(
				'label'    => __( 'Clone', 'everest-backup' ),
				'callback' => array( $this, 'clone_cb' ),
				'priority' => 20,
			),
		);
	}

	/**
	 * Callback for migration tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function migration_cb() {
		everest_backup_render_view( 'migration-clone/migration', $this->migration_clone_args );
	}

	/**
	 * Callback for clone tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function clone_cb() {
		everest_backup_render_view( 'migration-clone/clone', $this->migration_clone_args );
	}

}

