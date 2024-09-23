<?php
/**
 * Deprecated functions since Everest Backup 1.1.2.
 *
 * @since 1.1.2
 * @phpcs:disable
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set current archive process file name with full path.
 *
 * @param string $zipname Archive zip filename with full path.
 * @return void
 * @since 1.0.0
 * @deprecated 1.1.2
 */
function everest_backup_set_current_archive( $zipname ) {
	_deprecated_function( __FUNCTION__, '1.1.2' );
}

/**
 * Get current archive process file name with full path.
 *
 * @return string
 * @since 1.0.0
 * @deprecated 1.1.2
 */
function everest_backup_get_current_archive() {
	_deprecated_function( __FUNCTION__, '1.1.2' );
}
