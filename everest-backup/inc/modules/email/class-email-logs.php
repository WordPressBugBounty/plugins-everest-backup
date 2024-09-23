<?php
/**
 * Class for sending email of schedule backup logs.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Email;
use Everest_Backup\Logs;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for sending email of schedule backup logs.
 *
 * @since 1.0.0
 */
class Email_Logs extends Email {

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$settings = everest_backup_get_settings();

		if ( empty( $settings['schedule_backup']['notify'] ) ) {
			return;
		}

		add_action( 'everest_backup_after_logs_save', array( $this, 'init' ) );
	}

	/**
	 * Send email log.
	 *
	 * @param array $temp_logs Array of temporary logs.
	 * @return void
	 * @since 1.0.0
	 */
	public function init( $temp_logs ) {

		$process_types = Logs::get_process_type( $temp_logs );

		$key  = ! empty( $process_types['key'] ) ? $process_types['key'] : '';
		$type = ! empty( $process_types['type'] ) ? $process_types['type'] : '';

		if ( 'schedule_backup' !== $type ) {
			return;
		}

		if ( $type ) {
			unset( $temp_logs[ $key ] );
		}

		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

		$logs = array_values( $temp_logs );

		$log_msg_li = '';
		if ( is_array( $logs ) && ! empty( $logs ) ) {
			foreach ( $logs as $log ) {

				if ( empty( $log['type'] ) ) {
					continue;
				}

				if ( empty( $log['message'] ) ) {
					continue;
				}

				$log_type = ucfirst( $log['type'] );
				$log_msg  = ucfirst( $log['message'] );

				$log_msg_li .= "<li>{$log_type}: {$log_msg}</li>";
			}
		}

		$this->to = everest_backup_get_admin_email();

		$this->subject = __( 'Schedule Backup Logs', 'everest-backup' );

		$this->message = $this->message_content();

		$this->headers = $headers;

		$this->tags_and_values = array(
			'{{SCHEDULE_BACKUP_LOGS}}' => $log_msg_li,
			'{{HISTORY_PAGE_LINK}}'    => network_admin_url( '/admin.php?page=everest-backup-history' ),
			'{{LOGS_PAGE_LINK}}'       => network_admin_url( '/admin.php?page=everest-backup-logs' ),
		);

		parent::__construct();
	}

	/**
	 * Content for email message.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	protected function message_content() {
		$message = array();

		$message[] = esc_html__( 'Hi, the schedule backup has been completed.', 'everest-backup' );
		$message[] = esc_html__( 'Please find the process log below.', 'everest-backup' );
		$message[] = '<ul>{{SCHEDULE_BACKUP_LOGS}}</ul>';
		$message[] = sprintf( '<a href="{{HISTORY_PAGE_LINK}}">%s</a>', esc_html__( 'Backup History', 'everest-backup' ) );
		$message[] = sprintf( '<a href="{{LOGS_PAGE_LINK}}">%s</a>', esc_html__( 'All Logs', 'everest-backup' ) );

		return implode( "\n", $message );

	}
}

new Email_Logs();
