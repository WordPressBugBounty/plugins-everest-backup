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
	 * Cipher.
	 */
	private $cipher = 'aes-256-cbc';

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
	 * Get IV length.
	 *
	 * @return int
	 */
	public function getIVLength() {
		return openssl_cipher_iv_length( $this->cipher );
	}

	/**
	 * Add files to backup.
	 *
	 * @param string $file Current file name to be copied.
	 * @param array  $subtask Misc values.
	 * @param bool   $encode  Encode if true, default otherwise.
	 * @return bool|array If file write complete, returns true for succcess and false on failure. Returns file pointer and name as array if incomplete.
	 */
	public function add_file( $file, $subtask = array(), $encode = false, $key = '' ) {

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
			$fwrite = fread( $handle, self::$read_limit );
			if ( isset( $encode ) && $encode ) {
				$fwrite = $this->encrypt( $fwrite, $key );
			}
			fwrite( $this->ziphandle, $fwrite ); // @phpcs:ignore

			if ( ( time() - $timestart ) > ( EVEREST_BACKUP_PHP_EXECUTION_PARKHINE / 2 ) ) {
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
	 * Add files to backup.
	 *
	 * @param string $file Current file name to be copied.
	 * @param array  $subtask Misc values.
	 * @param bool   $encode  Encode if true, default otherwise.
	 * @return bool|array If file write complete, returns true for succcess and false on failure. Returns file pointer and name as array if incomplete.
	 */
	public function add_remove_file( $file ) {
		$path = $this->get_entryname( $file );

		return fwrite( $this->ziphandle, "EBWPFILE_DELETE:{$path}\n" ); // @phpcs:ignore
	}

	/**
	 * Encrypt string.
	 *
	 * @param string $string String to encrypt.
	 * @return string OpenSSL Encrypted string.
	 */
	public function encrypt( $string, $key = '' ) {
		$ivlen = $this->getIVLength();
		$iv    = openssl_random_pseudo_bytes( $ivlen );
		return $iv . openssl_encrypt( $string, $this->cipher, $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Decrypt string.
	 *
	 * @param string $encrypt String to decrypt.
	 * @return string OpenSSL Decrypted string.
	 */
	public function decrypt( $encrypt, $key = '' ) {
		$ivlen   = $this->getIVLength();
		$iv      = substr( $encrypt, 0, $ivlen );
		$encrypt = substr( $encrypt, $ivlen );
		return openssl_decrypt( $encrypt, $this->cipher, $key, OPENSSL_RAW_DATA, $iv );
	}

	/**
	 * Get next encrypted chunk with IV used to encrypt it.
	 *
	 * @param  string $line_seek Before line seek.
	 * @return string|false
	 */
	public function get_next_encrypted_chunk( $line_seek = false ) {
		if ( $line_seek ) {
			fseek( $this->ziphandle, $line_seek );
		}
		$ivlen     = $this->getIVLength();
		$curr_s    = ftell( $this->ziphandle );
		$read_size = $ivlen + self::$read_limit + $ivlen;
		$string    = fread( $this->ziphandle, $read_size );
		if ( ! empty( $string ) ) {
			$end_pos = strpos( $string, 'EBWPFILE_END:' );
			if ( -1 < $end_pos ) {
				$string = substr( $string, 0, $end_pos );
				fseek( $this->ziphandle, $curr_s + $end_pos );
				if ( "\n" === substr( $string, -1 ) ) {
					$string = substr( $string, 0, -1 );
				}
			}
			return $string;
		}
		return false;
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

	/**
	 * Scan the backup file for the list of files and their positions.
	 *
	 * @return array{
	 *  path: string,
	 *  start: int,
	 *  end: int
	 * }[] Array of files and their positions.
	 */
	function scan_file( $resume = false, $c_seek = 0 ) {
		$file_data       = array();
		$start_positions = array();

		$start_time = time();
		if ( $resume && $c_seek ) {
			fseek( $this->ziphandle, $c_seek );
		}
		while ( ( $line = fgets( $this->ziphandle ) ) !== false ) {
			$current_position = ftell( $this->ziphandle );

			// Match start marker
			if ( preg_match( '/^EBWPFILE_START:(.+?)$/', trim( $line ), $matches ) ) {
				$file_path                     = $matches[1];
				$start_positions[ $file_path ] = $current_position;
			}

			// Match end marker
			if ( preg_match( '/^EBWPFILE_END:(.+?)$/', trim( $line ), $matches ) ) {
				$file_path = $matches[1];

				if ( isset( $start_positions[ $file_path ] ) ) {
					$file_data[] = array(
						'path'  => $file_path,
						'start' => $start_positions[ $file_path ],
						'end'   => $current_position - strlen( $line ), // End of the file data
					);

					// Remove the start position once matched
					unset( $start_positions[ $file_path ] );
				}
			}

			if ( ( time() - $start_time ) > EVEREST_BACKUP_PHP_EXECUTION_PARKHINE ) {
				$c_seek = ftell( $this->get_ziphandle() );
				$this->close();
				echo wp_json_encode(
					array(
						'files'     => $file_data,
						'continued' => true,
						'c_seek'    => $c_seek,
					)
				);
				die();
			}
		}

		$this->close();

		return $file_data;
	}
}
