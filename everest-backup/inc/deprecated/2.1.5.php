<?php
/**
 * Deprecated functions since Everest Backup 2.1.5.
 *
 * @since 2.1.5
 *
 * @phpcs:disable
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Download helper to download files in chunks and save it.
 *
 * @link https://gist.github.com/irazasyed/7533127
 *
 * @param  string  $source       Source Path/URL to the file you want to download.
 * @param  string  $dest         Destination Path to save your file.
 * @param  integer $chunk_size   (Optional) How many bytes to download per chunk (In MB). Defaults to 5 MB.
 * @param  integer $total_size   (Optional) @since 1.0.7 Total size of the file.
 * @param  boolean $return_bytes (Optional) Return number of bytes saved. Default: true.
 *
 * @return integer               Returns number of bytes delivered.
 * @deprecated 2.1.5
 */
function everest_backup_chunk_download_file( $source, $dest, $chunk_size = 5, $total_size = 0, $return_bytes = true ) {
	_deprecated_function( __FUNCTION__, '2.1.5' );
}
