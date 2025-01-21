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
use Everest_Backup\Traits\Export as ExportTrait;
use Everest_Backup\Transient;
use WP_Error;

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
		$single_run_hook = "{$hook}_single_run_hook";

		add_action( $single_run_hook, array( $this, 'schedule_backup' ) );

		add_action( $hook, array( $this, 'schedule_backup' ) );

		if ( empty( $schedule_backup['increment_cycle'] ) ) {
			return;
		}

		$increment_cycle = $schedule_backup['increment_cycle'];

		$hook = "{$increment_cycle}_hook";
		$single_run_hook = "{$hook}_single_run_hook";

		add_action( $single_run_hook, array( $this, 'schedule_increment' ) );

		add_action( $hook, array( $this, 'schedule_increment' ) );
	}

	/**
	 * Do schedule backup.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function schedule_backup( $return = false ) {

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( everest_backup_process_running_currently() ) {
			return $this->reschedule_in_half_an_hour_if_not_scheduled_within_an_hour();
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
		$params['delete_from_server']        = isset( $schedule_backup['delete_from_server'] ) && $schedule_backup['delete_from_server'] && ( $params['save_to'] !== 'server' );
		$params['everest_backup_ajax_nonce'] = $this->get_password_hash();

		if ( everest_backup_pro_active() && ! empty( $schedule_backup['set_incremental_backup'] ) ) {
			$params['parent_incremental'] = 1;
		}

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
		if ( ! $return ) {
			die;
		}
		return;
	}

	/**
	 * Do schedule incremental backup.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function schedule_increment() {

		if ( wp_doing_ajax() ) {
			return;
		}

		if ( ! everest_backup_pro_active() ) {
			return;
		}

		if ( everest_backup_process_running_currently() ) {
			return $this->reschedule_increment_in_half_an_hour_if_not_scheduled_within_an_hour();
		}

		if ( file_exists( EVEREST_BACKUP_PROC_STAT_PATH ) ) {
			unlink( EVEREST_BACKUP_PROC_STAT_PATH ); // @phpcs:ignore
		}

		Logs::init( 'schedule_increment' );

		$cron_cycles = everest_backup_cron_cycles();

		$settings        = everest_backup_get_settings();
		$backup_excludes = array_keys( everest_backup_get_backup_excludes() );

		if ( empty( $settings['schedule_backup'] ) ) {
			return;
		}

		$schedule_backup     = $settings['schedule_backup'];
		$increment_cycle_key = $schedule_backup['increment_cycle'];

		$increment_cycle = ! empty( $cron_cycles[ $increment_cycle_key ]['display'] ) ? $cron_cycles[ $increment_cycle_key ]['display'] : '';

		/* translators: Here, %s is the schedule type or cron cycle. */
		Logs::info( sprintf( __( 'Schedule type: %s', 'everest-backup' ), $increment_cycle ) );

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
		$parent_backup = $this->get_parent_backup_name( $params['save_to'] );
		if ( is_wp_error( $parent_backup ) ) {
			Logs::error( $parent_backup->get_error_message() );
			everest_backup_send_error();
		}
		$params['incremental']    = 1;
		$params['parent_backup']  = $parent_backup['filename'];
		$params['children_count'] = $parent_backup['children_count'];
		$params['action']         = 'everest_backup_schedule_backup_create_item';

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
		$request = everest_backup_get_submitted_data();

		if ( ! everest_backup_verify_nonce( 'everest_backup_ajax_nonce' ) ) {
			Logs::error( __( 'Verification failed.', 'everest-backup' ) );
			return;
		}

		$params = json_decode( @file_get_contents( EVEREST_BACKUP_PROC_STAT_PATH ), true ); // @phpcs:ignore

		if ( ! $params ) {
			$params = $request; // @phpcs:ignore
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

		$params['everest_backup_ajax_nonce'] = $this->get_password_hash();

		$params['action'] = 'everest_backup_schedule_backup_create_item';

		set_transient( 'everest_backup_doing_scheduled_backup', true, 120 );

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

	/**
	 * Get password hash.
	 *
	 * Uses random_bytes() to generate a random key and stores it in the database.
	 * Then, uses password_hash() to generate a hash of the key, which is then stored.
	 *
	 * @return string The password hash.
	 */
	private function get_password_hash() {
		update_option( 'everest_backup_ajax_manual_nonce', bin2hex( random_bytes( 32 ) ) );
		return password_hash( get_option( 'everest_backup_ajax_manual_nonce' ), PASSWORD_BCRYPT );
	}

	use ExportTrait;

	/**
	 * Get parent backup file name for incremental backup.
	 */
	private function get_parent_backup_name( $cloud ) {
		$last_backup_file_name = self::read_last_backup_file_name();

		$file_list = $this->get_backup_file_list( $cloud );

		if ( ! $file_list || empty( $file_list ) ) {
			return new WP_Error( 'file_not_found', 'No backup files found in cloud.' );
		}

		if ( 0 === strpos( $last_backup_file_name, 'ebwpbuwa-' ) ) {
			foreach ( $file_list as $file ) {
				if ( $file['filename'] === $last_backup_file_name ) {
					return array( 'filename' => $last_backup_file_name, 'children_count' => 0 );
				}
			}
			return new WP_Error( 'file_not_found', 'Parent not found in cloud.' );
		}
		$parent_backup_file = substr_replace( $last_backup_file_name, 'ebwpbuwa-', 0, strlen( 'ebwpinc-' ) );
		$parent_backup_file = implode( '-', explode( '-', $parent_backup_file, -1 ) );
		$backup_file = implode( '-', explode( '-', $last_backup_file_name, -1 ) );
		$parent = false;
		$children = array();

		foreach ( $file_list as $file ) {
			if ( $file['filename'] === $parent_backup_file . EVEREST_BACKUP_BACKUP_FILE_EXTENSION ) {
				$parent = $parent_backup_file;
				continue;
			}

			if ( 0 === strpos( $file['filename'], $backup_file ) ) {
				$children[] = $file['filename'];
			}
		}

		if ( ! $parent ) {
			return new WP_Error( 'file_not_found', 'Parent not found in cloud..' );
		}

		if ( empty( $children ) ) {
			return array( 'filename' => $parent, 'children_count' => 0 );
		}

		if ( $this->children_increments_status_ok( $children, $backup_file ) ) {
			return array( 'filename' => $parent, 'children_count' => count( $children ) );
		}
		return new WP_Error( 'inconsistent_children', 'Inconsistency found in increment files. Please run a full scheduled backup to resume incremental backup. For more details, refer documentation.' );
	}

	/**
	 * Retrieve the list of backup files from the specified cloud location.
	 *
	 * @param string $cloud The cloud location identifier ('server' or cloud key).
	 *
	 * @return array|null The list of backup files, or null if not available.
	 */
	private function get_backup_file_list( $cloud ) {
		if ( 'server' === $cloud ) {
			return Backup_Directory::init()->get_backups();
		}
		$transient = new Transient( $cloud . '_folder_contents' );
		$transient->delete();
		return apply_filters( 'everest_backup_history_table_data', null, $cloud );
	}

	/**
	 * Checks if the given children are valid incremental backup files.
	 *
	 * This function expects the children to be named in the format:
	 * ebwpinc-<backup_file>-<index>.<extension>
	 *
	 * @param array  $children    The list of children files.
	 * @param string $backup_file The name of the backup file.
	 *
	 * @return bool True if the children are valid incremental backup files, false otherwise.
	 */
	private function children_increments_status_ok( $children, $backup_file ) {
		$children_count = count( $children );
		$expected_files = [];
    
		for ($i = 0; $i < $children_count; $i++) {
			$expected_files[] = $backup_file . '-' . $i . EVEREST_BACKUP_BACKUP_FILE_EXTENSION;
		}
		
		$children_set = array_flip( $children );
		foreach ( $expected_files as $file ) {
			if ( ! isset( $children_set[$file] ) ) {
				return false;
			}
		}
		
		return true;
	}

	/**
	 * Reschedules the incremental parent backup to run in half an hour if it's not scheduled
	 * to run within an hour.
	 */
	private function reschedule_in_half_an_hour_if_not_scheduled_within_an_hour() {
		$schedule_backup = everest_backup_get_settings( 'schedule_backup' );

		if ( empty( $schedule_backup['enable'] ) ) {
			return;
		}

		if ( empty( $schedule_backup['cron_cycle'] ) ) {
			return;
		}

		$cron_cycle = $schedule_backup['cron_cycle'];

		$hook = "{$cron_cycle}_hook";

		$next_scheduled = wp_next_scheduled( $hook );

		if ( $next_scheduled ) {
			$one_hour_later = time() + 3600;

			if ( $next_scheduled > $one_hour_later ) {
				$single_run_hook = "{$hook}_single_run_hook";

				if ( ! wp_next_scheduled( $single_run_hook ) ) {
					wp_schedule_single_event(time() + 1800, $single_run_hook);
				}
			}
		}
	}

	/**
	 * Reschedules the incremental backup to run in half an hour if it's not scheduled
	 * to run within an hour.
	 */
	private function reschedule_increment_in_half_an_hour_if_not_scheduled_within_an_hour() {
		$schedule_backup = everest_backup_get_settings( 'schedule_backup' );

		if ( empty( $schedule_backup['enable'] ) ) {
			return;
		}

		if ( empty( $schedule_backup['cron_cycle'] ) ) {
			return;
		}

		if ( empty( $schedule_backup['increment_cycle'] ) ) {
			return;
		}

		$increment_cycle = $schedule_backup['increment_cycle'];

		$hook = "{$increment_cycle}_hook";

		$next_scheduled = wp_next_scheduled( $hook );

		if ( $next_scheduled ) {
			$one_hour_later = time() + 3600;

			if ( $next_scheduled > $one_hour_later ) {
				$single_run_hook = "{$hook}_single_run_hook";

				if ( ! wp_next_scheduled( $single_run_hook ) ) {
					wp_schedule_single_event(time() + 1800, $single_run_hook);
				}
			}
		}
	}
}

new Cron_Actions();
