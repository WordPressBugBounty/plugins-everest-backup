<?php
/**
 * Class for handling emails.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling emails.
 *
 * @since 1.0.0
 */
class Email {

	/**
	 * Email address of the receiver.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $to;

	/**
	 * Email subject.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $subject;

	/**
	 * Email message.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $message;

	/**
	 * Email message.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $headers = '';

	/**
	 * Email message.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $attachments = array();

	/**
	 * Find and replace template tags with its values from the message before sending the email.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $tags_and_values = array();

	/**
	 * Whether the email was sent successfully
	 *
	 * @var boolean
	 * @since 1.0.0
	 */
	protected $email_sent = false;

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->parse_tags();
		$this->send_email();
	}

	/**
	 * Parse the template tags and its values from the message.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function parse_tags() {
		$tags_and_values = $this->tags_and_values;

		if ( ! $tags_and_values ) {
			return;
		}

		$message = $this->message;

		if ( ! $message ) {
			return;
		}

		$tags   = array_keys( $tags_and_values );
		$values = array_values( $tags_and_values );

		$this->message = str_replace( $tags, $values, $message );
	}

	/**
	 * Send email according to the set properties.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function send_email() {

		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
		$headers .= $this->headers;

		$this->email_sent = wp_mail( $this->to, $this->subject, $this->message, $headers, $this->attachments );
	}
}
