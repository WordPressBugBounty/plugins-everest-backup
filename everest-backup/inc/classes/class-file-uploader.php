<?php
/**
 * Class for handling file uploads.
 *
 * @since 1.0.7
 * @package everest-backup
 */

namespace Everest_Backup;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'File_Upload_Upgrader' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-file-upload-upgrader.php';
}

/**
 * Class for handling file uploads.
 *
 * @since 1.0.7
 */
class File_Uploader extends \File_Upload_Upgrader {

	/**
	 * Override parent construct.
	 *
	 * @param array $args Args for file uploader.
	 */
	public function __construct( $args = array() ) {

		/**
		 * Filter to override file upload args.
		 *
		 * @since 1.0.7 Migrated from class-extract.php to class-file-uploader.php
		 */
		$args = apply_filters( 'everest_backup_override_file_uploader_args', $args );

		if ( empty( $args ) ) {
			return;
		}

		if ( ! empty( $args['form'] ) && ! empty( $args['urlholder'] ) ) {
			return parent::__construct( $args['form'], $args['urlholder'] ); // phpcs:ignore
		}

		if ( ! empty( $args['package'] ) ) {
			if ( is_array( $args ) && ! empty( $args ) ) {
				foreach ( $args as $key => $value ) {
					if ( ! $this->is_valid( $key ) ) {
						continue;
					}

					$this->$key = $value;
				}
			}
		}
	}

	/**
	 * Validate $args keys.
	 *
	 * @param string $key Key to validate.
	 * @return bool
	 */
	protected function is_valid( $key ) {

		if ( ! $key ) {
			return;
		}

		$valid_keys = array_flip(
			array(
				'package',
				'filename',
				'id',
			)
		);

		return isset( $valid_keys[ $key ] );
	}

	/**
	 * Move file.
	 *
	 * @param string $to Destination.
	 *
	 * @return bool
	 */
	public function move( $to ) {
		if ( Filesystem::init()->move_file( $this->package, $to ) ) {

			if ( $this->id ) {
				wp_delete_attachment( $this->id );
			} elseif ( file_exists( $this->package ) ) {
				return @unlink( $this->package ); // phpcs:ignore
			}

			return true;
		}

		return false;
	}
}
