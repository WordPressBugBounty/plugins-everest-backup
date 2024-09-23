<?php
/**
 * Base class for cron jobs.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

use Everest_Backup\Traits\Singleton;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for cron jobs.
 *
 * @since 1.0.0
 */
class Cron {

	/**
	 * Init
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'everest_backup_before_settings_save', array( $this, 'before_settings_save' ), 12, 2 );
		add_filter( 'cron_schedules', array( $this, 'add_cron_intervals' ) );
		add_action( 'admin_init', array( $this, 'schedule_events' ), 50 );

		add_action( 'admin_init', array( $this, 'maybe_send_data_if_backup_scheduled_to_gdrive' ), 100 );
	}

	/**
	 * Send user data to backup server, if user has scheduled to update backup to google drive.
	 * Our next google drive extension backup will have a breaking change.
	 * Collected data will be used to notify user about the changes.
	 */
	public function maybe_send_data_if_backup_scheduled_to_gdrive() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schedule_backup = everest_backup_get_settings( 'schedule_backup' );

		if ( $schedule_backup && is_array( $schedule_backup ) ) {
			if ( array_key_exists( 'enable', $schedule_backup ) && '0' === $schedule_backup['enable'] ) {
				return;
			}
			if ( array_key_exists( 'save_to', $schedule_backup ) && 'google_drive' === $schedule_backup['save_to'] ) {
				if ( everest_backup_cloud_get_option( __FUNCTION__ ) ) {
					return;
				}
				if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' ) { // @phpcs:ignore
					return;
				}
				$user_id   = get_current_user_id();
				$user_info = get_userdata( $user_id );

				$data['email']        = $user_info->user_email;
				$data['name']         = $user_info->display_name;
				$data['website_url']  = home_url();
				$send_data['body']    = $data;
				$send_data['timeout'] = 2;

				$return = wp_remote_post(
					'https://stats.everestthemes.com/wp-json/everestthemes/v1/backup-gdrive-users',
					$send_data
				);

				if ( is_array( $return ) && array_key_exists( 'body', $return ) ) {
					$return = json_decode( $return['body'], true );

					if ( array_key_exists( 'success', $return ) && $return['success'] ) {
						everest_backup_cloud_update_option( __FUNCTION__, true );
					}
				}
			}
		}
	}

	/**
	 * Unschedule events on settings save.
	 *
	 * @param array   $settings Settings.
	 * @param boolean $has_changes If settings has new changes.
	 * @return void
	 * @since 1.1.2
	 */
	public function before_settings_save( $settings, $has_changes ) {

		if ( ! $has_changes ) {
			return;
		}

		$get = everest_backup_get_submitted_data( 'get' );

		if ( empty( $get['tab'] ) ) {
			return;
		}

		if ( 'schedule_backup' !== $get['tab'] ) {
			return;
		}

		$this->unschedule_events();
	}


	/**
	 * Custom schedules list.
	 *
	 * @abstract
	 * @return array
	 * @since 1.0.0
	 */
	protected function cron_schedules() {
		return array();
	}

	/**
	 * Create dynamic timestamp for cron events according to the time selected by the user.
	 *
	 * @param int   $interval Cron schedule interval.
	 * @param array $schedule_backup Schedule Backup settings value.
	 * @return int
	 * @since 1.0.0
	 */
	protected function cron_event_timestamp( $interval, $schedule_backup ) {

		if ( ! $interval ) {
			$interval = 86400;
		}

		if ( empty( $schedule_backup ) ) {
			return $interval;
		}

		$next_day = time() + $interval;
		$datetime = wp_date( 'Y-m-d', $next_day ) . ' ' . $schedule_backup['cron_cycle_time'] . ':00';

		return strtotime( $datetime );
	}

	/**
	 * Handle cron events.
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.1.2 Added `everest_backup_filter_schedule_event_args` filter hook.
	 */
	public function schedule_events() {
		$events = everest_backup_cron_cycles();

		if ( ! $events ) {
			return;
		}

		$schedule_backup = everest_backup_get_settings( 'schedule_backup' );

		if ( is_array( $events ) && ! empty( $events ) ) {
			foreach ( $events as $recurrence => $event ) {

				if ( ! $event['interval'] ) {
					continue;
				}

				/**
				 * Filter hook for schedule event args.
				 *
				 * @since 1.1.2
				 */
				$args = apply_filters(
					'everest_backup_filter_schedule_event_args',
					array(
						'hook'      => "{$recurrence}_hook",
						'interval'  => $event['interval'],
						'timestamp' => $this->cron_event_timestamp( $event['interval'], $schedule_backup ),
					),
					$schedule_backup
				);

				if ( ! wp_next_scheduled( $args['hook'] ) ) {
					wp_schedule_event( $args['timestamp'], $recurrence, $args['hook'] );
				}
			}
		}
	}

	/**
	 * Unschedule events on settings save.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function unschedule_events() {
		$events = everest_backup_cron_cycles();

		if ( ! $events ) {
			return;
		}

		if ( is_array( $events ) && ! empty( $events ) ) {
			foreach ( $events as $recurrence => $event ) {

				if ( ! $event['interval'] ) {
					continue;
				}

				$hook      = "{$recurrence}_hook";
				$timestamp = wp_next_scheduled( $hook );

				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Add cron intervals.
	 *
	 * @param array $schedules WordPress cron schedules.
	 * @return array $schedules WordPress cron schedules.
	 * @since 1.0.0
	 */
	public function add_cron_intervals( $schedules ) {
		$custom_schedules = $this->cron_schedules();

		if ( ! $custom_schedules ) {
			return $schedules;
		}

		$schedules = array_merge( $schedules, $custom_schedules );

		return $schedules;
	}
}
