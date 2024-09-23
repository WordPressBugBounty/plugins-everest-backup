<?php
/**
 * Trait file for providing singleton functionality to the class.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Traits;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton trait that can be used by classes to initialize singleton functionality.
 *
 * @since 1.0.0
 */
trait Singleton {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function __construct() {}

	/**
	 * Initialize singleton instance of the class. will return this instance if created otherwise create new instance first.
	 *
	 * @since 1.0.0
	 * @return object Main singleton instance.
	 */
	final public static function init() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {}
}
