<?php
/**
 * Abstract class for handling migration and clone functionality.
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
 * Abstract class for handling migration and clone functionality.
 *
 * @since 1.0.0
 */
class Migration_Clone {

	/**
	 * Nonce key.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public $nonce_key = 'ebwp_migration_nonce';

	/**
	 * Selected file name.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $file;

	/**
	 * File information.
	 *
	 * @var array
	 * @since 2.0.2
	 */
	protected $file_info;

	/**
	 * Selected file url.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $file_url;

	/**
	 * Selected file size.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	protected $filesize;

	/**
	 * File created on.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	protected $file_time;

	/**
	 * Generated nonce key.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $nonce;

	/**
	 * Migration key for cloning.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $migration_key;

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->set_migration_key();
	}

	/**
	 * Verify migration/clone nonce.
	 *
	 * @return bool
	 */
	public function verify_nonce() {

		if ( everest_backup_verify_nonce( $this->nonce_key ) ) {
			return true;
		}
	}

	/**
	 * Generate migration key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function generate_migration_key() {
		$args = array(
			'name'  => $this->file,
			'url'   => $this->file_url,
			'size'  => $this->filesize,
			'time'  => $this->file_time,
			'nonce' => $this->nonce,
		);

		if ( ! array_filter( $args ) ) {
			return;
		}

		$args['is_localhost'] = everest_backup_is_localhost();
		$args['is_multisite'] = is_multisite();

		$json = wp_json_encode( $args );

		return everest_backup_str2hex( $json );
	}

	/**
	 * Extracts and returns information from the migration key hash.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_key_info() {
		$migration_key = $this->migration_key;

		if ( ! $migration_key ) {
			return;
		}

		/**
		 * Bail if migration key is not a hexdecimal string.
		 */
		if ( ! ctype_xdigit( $migration_key ) ) {
			return;
		}

		/**
		 * Bail if length of the migration key is in odd number because valid migration key is always in even number.
		 */
		if ( $this->get_key_length() % 2 ) {
			return;
		}

		$extract = everest_backup_hex2str( $migration_key );

		if ( ! $extract ) {
			return;
		}

		$data = json_decode( $extract, true );

		return $data;
	}

	/**
	 * Sets the generated migration key. Override it if needed.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_migration_key() {
		$this->migration_key = $this->generate_migration_key();
	}

	/**
	 * Returns migration key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_migration_key() {
		return $this->migration_key;
	}

	/**
	 * Returns length of the migration key.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public function get_key_length() {
		if ( ! $this->migration_key ) {
			return 0;
		}

		return strlen( $this->migration_key );
	}
}
