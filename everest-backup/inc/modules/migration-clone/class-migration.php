<?php
/**
 * Child class for handling migration.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Migration_Clone;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Child class for handling migration.
 *
 * @since 1.0.0
 */
class Migration extends Migration_Clone {

	/**
	 * Init class.
	 *
	 * @param array $args Custom args for generating migration key, $_GET value will be used as default.
	 * @since 1.0.0
	 */
	public function __construct( $args = array() ) {

		$auto_nonce = ! empty( $args['auto_nonce'] );

		if ( ! $auto_nonce ) {
			if ( ! $this->verify_nonce() ) {
				return;
			}
		}

		$get = ! empty( $args ) ? $args : everest_backup_get_submitted_data( 'get' );

		if ( ! isset( $get['file'] ) ) {
			return;
		}

		$file = $get['file'];

		$filepath = everest_backup_get_backup_full_path( $file );

		if ( ! $filepath ) {
			return;
		}

		$file_info = everest_backup_get_backup_file_info( $filepath );

		$this->file_info = $file_info;

		$this->file      = sanitize_file_name( $file );
		$this->file_url  = esc_url_raw( $file_info['url'] );
		$this->file_time = absint( $file_info['time'] );
		$this->filesize  = absint( $file_info['size'] );
		$this->nonce     = $auto_nonce ? everest_backup_create_nonce( $this->nonce_key ) : $get[ $this->nonce_key ];

		parent::__construct();
	}

	/**
	 * Returns a direct url to the migration page for generating migration key.
	 *
	 * @return string
	 * @since 1.0.1
	 */
	public function get_url() {
		if ( ! $this->get_migration_key() ) {
			return;
		}

		$args = array(
			'tab'            => 'migration',
			'file'           => $this->file,
			$this->nonce_key => $this->nonce,
		);

		return add_query_arg(
			$args,
			network_admin_url( '/admin.php?page=everest-backup-migration_clone' )
		);
	}
}
