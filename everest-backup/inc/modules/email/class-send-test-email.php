<?php
/**
 * Class for sending test emails.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Email;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for sending test emails.
 *
 * @since 1.0.0
 */
class Send_Test_Email extends Email {

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
	}

	/**
	 * Init test email.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		$get = everest_backup_get_submitted_data( 'get' );

		if ( ! isset( $get['email-test'] ) ) {
			return;
		}

		if ( 'sending' === $get['email-test'] ) {
			$this->to = everest_backup_get_admin_email();

			$this->subject = __( 'Everest Backup: Test Email', 'everest-backup' );

			$this->message = __( 'Hi there, the email functionality is working successfully.', 'everest-backup' );

			parent::__construct();
		}

		$redirect_url = remove_query_arg( 'email-test' );

		wp_safe_redirect( $redirect_url );

		if ( $this->email_sent ) {
			everest_backup_set_notice( __( 'Test email has been sent to your provided email address.', 'everest-backup' ), 'notice-success' );
		} else {
			everest_backup_set_notice( __( 'There was some issue sending email. Please try again later.', 'everest-backup' ), 'notice-error' );
		}

		exit;

	}
}

new Send_Test_Email();
