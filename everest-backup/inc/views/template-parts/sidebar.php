<?php
/**
 * Everest Backup sidebar.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$get = everest_backup_sanitize_array( everest_backup_get_submitted_data( 'get' ) );

if ( empty( $get['page'] ) ) {
	return;
}

$contents    = everest_backup_fetch_sidebar( $get['page'] );
$current_tab = ! empty( $args['current_tab'] ) ? $args['current_tab'] : '';

if ( ! isset( $contents['global'] ) && ! isset( $contents['paged'] ) ) {
	return;
}

$global_contents = $contents['global'];
$paged_contents  = $contents['paged'];

$paged_global_contents = ! empty( $paged_contents['global'] ) ? $paged_contents['global'] : array();
$paged_tab_contents    = ! empty( $paged_contents[ $current_tab ] ) ? $paged_contents[ $current_tab ] : array();

$template = 'template-parts/sidebar-card';

?>

<aside class="everest-backup-sidebar">

	<?php

	/**
	 * Tab specific.
	 */
	if ( is_array( $paged_tab_contents ) && ! empty( $paged_tab_contents ) ) {
		foreach ( $paged_tab_contents as $paged_tab_content ) {
			everest_backup_render_view( $template, $paged_tab_content );
		}
	}

	/**
	 * Per page global.
	 */
	if ( is_array( $paged_global_contents ) && ! empty( $paged_global_contents ) ) {
		foreach ( $paged_global_contents as $paged_global_content ) {
			everest_backup_render_view( $template, $paged_global_content );
		}
	}

	/**
	 * All global.
	 */
	if ( is_array( $global_contents ) && ! empty( $global_contents ) ) {
		foreach ( $global_contents as $global_content ) {
			everest_backup_render_view( $template, $global_content );
		}
	}

	?>

</aside>