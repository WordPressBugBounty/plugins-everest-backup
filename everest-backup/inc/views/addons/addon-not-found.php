<?php
/**
 * Template file for addons.php
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="ebwp-center no-addon-found-wrapper">
	<h1 class="dashicons dashicons-warning"></h1>
	<h1><?php esc_html_e( 'Oops, unable to fetch addon information. Please try again later.', 'everest-backup' ); ?></h1>
	<h1><?php esc_html_e( 'OR', 'everest-backup' ); ?></h1>
	<h1><?php esc_html_e( 'Visit our website: wpeverestbackup.com', 'everest-backup' ); ?></h1>
</div>
