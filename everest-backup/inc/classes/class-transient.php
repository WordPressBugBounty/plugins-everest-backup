<?php
/**
 * Transient helper class.
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
 * Transient helper class.
 *
 * @since 1.0.0
 */
class Transient {

	/**
	 * Transient name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $transient;

	/**
	 * Init class.
	 *
	 * @param string $transient Transient name. No need to prefix the as the passed value will be prefixed automatically as `'ebwp_' . $transient`.
	 * @since 1.0.0
	 */
	public function __construct( $transient ) {
		$this->transient = 'ebwp_' . $transient;
	}

	/**
	 * Set transient value.
	 *
	 * @param mixed $value Transient value.
	 * @param int   $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
	 * @return bool
	 * @since 1.0.0
	 */
	public function set( $value, $expiration = 0 ) {
		return set_transient( $this->transient, $value, $expiration );
	}

	/**
	 * Retrieves the value of a transient.
	 *
	 * @return mixed Value of transient.
	 * @since 1.0.0
	 */
	public function get() {
		return get_transient( $this->transient );
	}

	/**
	 * Deletes a transient.
	 *
	 * @return bool True if the transient was deleted, false otherwise.
	 * @since 1.0.0
	 */
	public function delete() {
		return delete_transient( $this->transient );
	}

	/**
	 * Get transient time out.
	 *
	 * @return timestamp|false Returns unix timestamp if timeout is set.
	 * @since 1.0.0s
	 */
	public function get_timeout() {
		$transient_timeout = '_transient_timeout_' . $this->transient;

		return get_option( $transient_timeout );
	}
}
