<?php
/**
 * Class to handle cron schedules and intervals.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Cron;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle cron schedules and intervals.
 *
 * @since 1.0.0
 */
class Cron_Handler extends Cron {

	/**
	 * Set cron schedules.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function cron_schedules() {

		$schedules   = array();
		$cron_cycles = everest_backup_cron_cycles();

		if ( is_array( $cron_cycles ) && ! empty( $cron_cycles ) ) {
			foreach ( $cron_cycles as $cron_name => $cron_cycle ) {

				if ( ! $cron_cycle['interval'] ) {
					continue;
				}

				$schedules[ $cron_name ] = array(
					'interval' => $cron_cycle['interval'],
					'display'  => $cron_cycle['display'],
				);
			}
		}

		return $schedules;
	}
}

new Cron_Handler();
