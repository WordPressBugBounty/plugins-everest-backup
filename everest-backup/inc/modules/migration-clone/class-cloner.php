<?php
/**
 * Class for handling the clone functionality.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Backup_Directory;
use Everest_Backup\Logs;
use Everest_Backup\Migration_Clone;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling the clone functionality.
 *
 * @since 1.0.0
 */
class Cloner extends Migration_Clone {

	/**
	 * Form submitted data.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $request;

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		if ( ! $this->verify_nonce() ) {
			return;
		}

		$this->request = everest_backup_get_submitted_data();
	}

	/**
	 * Init parent class for migration key verification and key info extraction related work.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function handle_migration_key() {

		if ( empty( $this->request['verify_key'] ) ) {
			return;
		}

		if ( empty( $this->request['migration_key'] ) ) {
			return;
		}

		parent::__construct();
	}

	/**
	 * Download the package from the host site.
	 *
	 * @param array $args Migration key information arguments.
	 * @return string Basename of downloaded file.
	 * @since 1.0.0
	 */
	public function handle_package_clone( $args ) {

		$package = $this->download_package( $args );

		if ( ! $package ) {
			return;
		}

		return basename( $package );
	}

	/**
	 * Download the package from the host website.
	 *
	 * @param array $args Migration key information arguments.
	 * @return string
	 * @since 1.0.0
	 */
	protected function download_package( $args ) {

		if ( empty( $args['download_url'] ) ) {
			return;
		}

		if ( empty( $args['size'] ) ) {
			return;
		}

		$download_url = $args['download_url'];
		$size         = $args['size'];
		$url_parts    = wp_parse_url( $download_url );
		$filename     = basename( $url_parts['path'] );
		$package      = everest_backup_get_backup_full_path( $filename, false );

		if ( empty( $args['seek'] ) ) {
			Logs::set_proc_stat(
				array(
					'status'   => 'in-process',
					'message'  => __( 'Preparing the file for download.', 'everest-backup' ),
					'progress' => 0,
				)
			);
		}

		$bytes = everest_backup_download_file(
			$download_url,
			$package,
			$args
		);

		return $bytes && file_exists( $package ) && filesize( $package ) ? $package : '';
	}

	/**
	 * Sets the generated migration key
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_migration_key() {
		$this->migration_key = $this->request['migration_key'];
	}

	/**
	 * Check if user is trying to clone into same site.
	 *
	 * @param array $key_info Extracted information from migration key.
	 * @return boolean
	 * @since 1.0.2
	 */
	protected function is_same_site( $key_info ) {
		$siteurl = home_url( '/' );
		$url     = $key_info['url'];

		$explode = explode( $siteurl, $url );

		return count( $explode ) > 1;
	}

	/**
	 * Returns true if there's no error, returns error message otherwise.
	 *
	 * @return boolean|string
	 * @since 1.0.0
	 */
	public function is_clonable() {

		if ( ! $this->migration_key ) {
			return __( 'Cannot clone because migration key is not set.', 'everest-backup' );
		}

		$key_info = $this->get_key_info();

		if ( ! is_array( $key_info ) ) {
			return __( 'Unable to extract information from migration key.', 'everest-backup' );
		}

		if ( ! empty( $key_info['is_localhost'] ) ) {

			/**
			 * Error when user is trying to clone from localhost to live website.
			 */
			if ( ! everest_backup_is_localhost() ) {
				return __( 'Cannot clone to live website from localhost.', 'everest-backup' );
			}
		}

		if ( $this->is_same_site( $key_info ) ) {

			/**
			 * Error if user is trying to clone into same site.
			 */
			return __( 'Cannot clone to a same domain. Please enter the migration key in different domain for cloning.', 'everest-backup' );
		}

		$max_upload_size = everest_backup_max_upload_size();

		if ( $max_upload_size && ( $key_info['size'] >= $max_upload_size ) ) {

			return sprintf(
				/* translators: %s is the link to Everest Backup Unlimited. */
				__( 'Package size is larger than allowed maximum upload size. Please increase maximum upload size or %s', 'everest-backup' ),
				'<a href="https://wpeverestbackup.com/unlimited-upload-and-restore">' . __( 'Get Unlimited', 'everest-backup' ) . '</a>'
			);
		}

		Backup_Directory::init()->create();

		$disk_free_space = everest_backup_disk_free_space( EVEREST_BACKUP_BACKUP_DIR_PATH );
		$package_size    = $key_info['size'] * 1.5;

		if ( $package_size >= $disk_free_space ) {

			if ( everest_backup_is_space_available( EVEREST_BACKUP_BACKUP_DIR_PATH, $package_size, false ) ) {
				/**
				 * Cross check using fallback.
				 */
				return true;
			}

			/* translators: %1$s is total filesAvailable disk space:ize and %2$s is server available free disk space. */
			return sprintf( __( 'Required disk space: %1$s. Available disk space: %2$s.', 'everest-backup' ), esc_html( everest_backup_format_size( $package_size ) ), esc_html( everest_backup_format_size( $disk_free_space ) ) );
		}

		if ( 404 === absint( wp_remote_retrieve_response_code( wp_remote_head( $key_info['url'] ) ) ) ) {
			/* translators: file name */
			return sprintf( __( 'File: "%s" does not exists', 'everest-backup' ), esc_html( $key_info['name'] ) );
		}

		//phpcs:disable
		$ch = curl_init();
	
		curl_setopt( $ch, CURLOPT_RANGE, '0-1' );
	
		curl_setopt( $ch, CURLOPT_URL, $key_info['url'] );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
	
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
	
		curl_exec( $ch );
	
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		//phpcs:enable

		if ( 206 !== $http_code ) {
			/* translators: file name */
			return sprintf( __( 'File: "%s" not downloadable', 'everest-backup' ), esc_html( $key_info['name'] ) );
		}

		return true;
	}
}
