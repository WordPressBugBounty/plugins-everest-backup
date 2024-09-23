<?php
/**
 * New core archiver for version 2.0.0 and above.
 *
 * @package Everest_Backup
 *
 * @since 2.2.4
 */

namespace Everest_Backup\Core;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * For binary file read and write.(Output is not compressed)
 *
 * @since 2.2.4
 */
class Archiver_V2 {

	/**
	 * Read limit.
	 *
	 * @var int
	 */
	private static $read_limit = KB_IN_BYTES * 512; // 512 KB

	/**
	 * Path to file.
	 *
	 * @var string
	 */
	private $zippath = null;

	/**
	 * File handle(resource).
	 *
	 * @var resource
	 */
	private $ziphandle = null;

	/**
	 * Constructor.
	 *
	 * @param string $zippath Path to file.
	 */
	public function __construct( $zippath = null ) {
		$this->set_zippath( $zippath );
	}

	/**
	 * Get entry name.
	 *
	 * @param string $file File name.
	 */
	protected function get_entryname( $file ) {

		$file = wp_normalize_path( $file );

		/**
		 * Special treatments for the files generated from Everest Backup.
		 * Handling these things during backup will help us during restore.
		 */
		if ( false !== strpos( $file, EVEREST_BACKUP_TEMP_DIR_PATH ) ) {
			if ( false !== strpos( $file, 'ebwp-database' ) ) {
				return str_replace( trailingslashit( dirname( $file, 2 ) ), 'ebwp-files/', $file );
			}

			return str_replace( trailingslashit( dirname( $file ) ), 'ebwp-files/', $file ); // These are probably our config files.
		}

		return str_replace( trailingslashit( wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) ) ), '', $file );
	}

	/**
	 * Setter for zip path.
	 *
	 * @param string $zippath Path to file.
	 * @return void
	 */
	public function set_zippath( $zippath ) {
		$this->zippath = wp_normalize_path( $zippath );
	}

	/**
	 * Get file handle(file resource).
	 *
	 * @return resource
	 */
	public function get_ziphandle() {
		return $this->ziphandle;
	}

	/**
	 * Open a file in given mode.
	 *
	 * @param string $mode File read/write mode.
	 * @return bool
	 */
	public function open( $mode = 'wb' ) {
		$this->ziphandle = fopen( $this->zippath, $mode ); // @phpcs:ignore

		if ( ! is_resource( $this->ziphandle ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add files to backup.
	 *
	 * @param string $file Current file name to be copied.
	 * @param array  $subtask Misc values.
	 * @return bool|array If file write complete, returns true for succcess and false on failure. Returns file pointer and name as array if incomplete.
	 */
	public function add_file( $file, $subtask = array() ) {

		$timestart = time();

		$file = wp_normalize_path( $file );

		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return false;
		}

		$path = $this->get_entryname( $file );

		$handle = fopen( $file, 'rb' ); // @phpcs:ignore

		if ( ! empty( $subtask['c_f'] ) && ! empty( $subtask['c_ftell'] ) && ( $file === $subtask['c_f'] ) ) {
			fseek( $handle, $subtask['c_ftell'] );
		} else {
			fwrite( $this->ziphandle, "EBWPFILE_START:{$path}\n" ); // @phpcs:ignore
		}

		while ( ! feof( $handle ) ) {
			fwrite( $this->ziphandle, fread( $handle, self::$read_limit ) ); // @phpcs:ignore

			if ( ( time() - $timestart ) > 10 ) {
				return array(
					'current_file_ftell' => ftell( $handle ),
					'file_name'          => $file,
				);
			}
		}

		fwrite( $this->ziphandle, "\nEBWPFILE_END:{$path}\n" ); // @phpcs:ignore

		return fclose( $handle ); // @phpcs:ignore
	}

	/**
	 * Close current open backup handle.
	 *
	 * @return bool True on success, false otherwise.
	 */
	public function close() {
		return fclose( $this->ziphandle ); // @phpcs:ignore
	}

	/**
	 * Set metadata in backup file.
	 *
	 * @param array $metadata Value to be written as metadata.
	 * @return void
	 */
	public function set_metadata( $metadata = array() ) {

		if ( ! is_resource( $this->ziphandle ) ) {
			return;
		}

		$metajson = wp_json_encode( $metadata );
		fwrite( $this->ziphandle, "EBWPFILE_METADATA:{$metajson}\n" ); // @phpcs:ignore
	}

	/**
	 * Get all metadata from backup file.
	 *
	 * @param bool $history_list Trying to list in history page.
	 * @return array
	 */
	public function get_metadatas( $history_list = false ) {

		static $metadata;

		if ( ! $metadata || $history_list ) {
			if ( $this->open( 'r' ) ) {
				$metajson = ltrim( fgets( $this->ziphandle ), 'EBWPFILE_METADATA:' );
				$this->close();

				$metadata = json_decode( $metajson, true );
			}
		}

		return $metadata;
	}

	/**
	 * Get backup file metadata.
	 *
	 * @param string $key          Metadata key.
	 * @param bool   $history_list Trying to list in history page.
	 * @return string|null
	 */
	public function get_metadata( $key, $history_list = false ) {
		$metadata = $this->get_metadatas( $history_list );
		return isset( $metadata[ $key ] ) ? $metadata[ $key ] : null;
	}
}
