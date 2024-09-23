<?php
/**
 * Class to manage cron hook actions.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Backup_Directory;
use Everest_Backup\Logs;
use Everest_Backup\Temp_Directory;
use Everest_Backup\Core\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage cron hook actions.
 *
 * @since 1.0.0
 */
class Cron_Actions {

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_version_check', 'everest_backup_parsed_changelogs' );
		add_action( 'wp_scheduled_delete', array( $this, 'cron_delete_files' ) ); // Triggers once daily.
		add_action( 'wp_ajax_nopriv_everest_backup_schedule_backup_create_item', array( $this, 'create_item_ajax' ) );
		$this->init_schedule_backup();
	}

	/**
	 * Handle backup files deletion related actions.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function cron_delete_files() {
		Temp_Directory::init()->clean_temp_dir();
		$this->delete_misc_files();
		$this->auto_remove();
	}

	/**
	 * Delete non backup directory related files.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function delete_misc_files() {

		/**
		 * All misc files older than 1 day.
		 */
		$files = Backup_Directory::init()->get_misc_files( 1 );

		if ( is_array( $files ) && ! empty( $files ) ) {
			foreach ( $files as $file ) {

				if ( ! is_file( $file ) ) {
					continue;
				}

				unlink( $file ); // phpcs:ignore
			}
		}
	}

	/**
	 * Auto remove archive files from the server.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function auto_remove() {
		$general = everest_backup_get_settings( 'general' );

		$auto_remove = ! empty( $general['auto_remove_older_than'] ) && $general['auto_remove_older_than'] > 0 ? absint( $general['auto_remove_older_than'] ) : 0;

		if ( ! $auto_remove ) {
			return;
		}

		$backups = Backup_Directory::init()->get_backups_older_than( $auto_remove );

		if ( is_array( $backups ) && ! empty( $backups ) ) {
			foreach ( $backups as $backup ) {
				if ( empty( $backup['path'] ) ) {
					continue;
				}

				if ( ! is_file( $backup['path'] ) ) {
					continue;
				}

				unlink( $backup['path'] ); // phpcs:ignore
			}
		}
	}

	/**
	 * Init schedule backup cron.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function init_schedule_backup() {
		$schedule_backup = everest_backup_get_settings( 'schedule_backup' );

		if ( empty( $schedule_backup['enable'] ) ) {
			return;
		}

		if ( empty( $schedule_backup['cron_cycle'] ) ) {
			return;
		}

		$cron_cycle = $schedule_backup['cron_cycle'];

		$hook = "{$cron_cycle}_hook";

		add_action( $hook, array( $this, 'schedule_backup' ) );
	}

	/**
	 * Do schedule backup.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function schedule_backup() {

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( file_exists( EVEREST_BACKUP_PROC_STAT_PATH ) ) {
			unlink( EVEREST_BACKUP_PROC_STAT_PATH ); // @phpcs:ignore
		}

		Logs::init( 'schedule_backup' );

		$cron_cycles = everest_backup_cron_cycles();

		$settings        = everest_backup_get_settings();
		$backup_excludes = array_keys( everest_backup_get_backup_excludes() );

		if ( empty( $settings['schedule_backup'] ) ) {
			return;
		}

		$schedule_backup = $settings['schedule_backup'];
		$cron_cycle_key  = $schedule_backup['cron_cycle'];

		$cron_cycle = ! empty( $cron_cycles[ $cron_cycle_key ]['display'] ) ? $cron_cycles[ $cron_cycle_key ]['display'] : '';

		/* translators: Here, %s is the schedule type or cron cycle. */
		Logs::info( sprintf( __( 'Schedule type: %s', 'everest-backup' ), $cron_cycle ) );

		$params = array();

		$params['t']                         = time();
		$params['action']                    = EVEREST_BACKUP_EXPORT_ACTION;
		$params['save_to']                   = isset( $schedule_backup['save_to'] ) && $schedule_backup['save_to'] ? $schedule_backup['save_to'] : 'server';
		$params['custom_name_tag']           = isset( $schedule_backup['custom_name_tag'] ) ? $schedule_backup['custom_name_tag'] : '';
		$params['delete_from_server']        = isset( $schedule_backup['delete_from_server'] ) && $schedule_backup['delete_from_server'];
		$params['everest_backup_ajax_nonce'] = everest_backup_create_nonce( 'everest_backup_ajax_nonce' );

		if ( is_array( $backup_excludes ) && ! empty( $backup_excludes ) ) {
			foreach ( $backup_excludes as $backup_exclude ) {
				if ( ! empty( $schedule_backup[ $backup_exclude ] ) ) {
					$params[ $backup_exclude ] = 1;
				}
			}
		}
		$params['action'] = 'everest_backup_schedule_backup_create_item';

		wp_remote_post(
			admin_url( '/admin-ajax.php' ),
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

		die;
	}

	/**
	 * Run scheduled backup.
	 *
	 * @param object $request Request.
	 */
	public function create_item_ajax() {
		if ( ! wp_verify_nonce( $_REQUEST['everest_backup_ajax_nonce'], 'everest_backup_ajax_nonce' ) ) {
			Logs::error( __( 'Verification failed.', 'everest-backup' ) );
			return;
		}

		sleep( 2 );

		set_transient( 'everest_backup_doing_scheduled_backup', true, 120 );

		$params = json_decode( @file_get_contents( EVEREST_BACKUP_PROC_STAT_PATH ), true ); // @phpcs:ignore

		if ( ! $params ) {
			$params = $_REQUEST; // @phpcs:ignore
		}

		if ( empty( $params ) ) {
			return;
		}

		if ( isset( $params['status'] ) && ( 'done' === $params['status'] ) ) {
			delete_transient( 'everest_backup_doing_scheduled_backup' );
			everest_backup_send_success();
			return;
		}

		if ( isset( $params['task'] ) && ( 'cloud' === $params['task'] ) ) {
			delete_transient( 'everest_backup_doing_scheduled_backup' );
			return;
		}

		add_filter( 'everest_backup_disable_send_json', '__return_true' );

		Export::init( $params );

		$params = json_decode( @file_get_contents( EVEREST_BACKUP_PROC_STAT_PATH ), true ); // @phpcs:ignore

		$params['everest_backup_ajax_nonce'] = everest_backup_create_nonce( 'everest_backup_ajax_nonce' );
		$params['action'] = 'everest_backup_schedule_backup_create_item';

		wp_remote_post(
			admin_url( '/admin-ajax.php' ),
			array(
				'body'      => $params,
				'timeout'   => 1,
				'blocking'  => false,
				'sslverify' => false,
				'headers'   => array(
					'Connection' => 'close',
				),
			)
		);
		die;
	}
}

new Cron_Actions();
