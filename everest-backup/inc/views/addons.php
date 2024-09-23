<?php
/**
 * Template file for the addons page.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_request   = everest_backup_get_submitted_data();
$everest_backup_addon_cat = ! empty( $everest_backup_request['cat'] ) ? $everest_backup_request['cat'] : '';
$everest_backup_addons    = everest_backup_fetch_addons( $everest_backup_addon_cat );

?>
<div class="wrap">
	<hr class="wp-header-end">

	<?php everest_backup_render_view( 'template-parts/header' ); ?>

	<main class="everest-backup-wrapper">
		<div id="everest-backup-container">

			<?php
			if ( $everest_backup_addons ) {
				everest_backup_render_view(
					'addons/listings',
					array(
						'request' => $everest_backup_request,
						'addons'  => $everest_backup_addons,
					)
				);
			} else {
				everest_backup_render_view( 'addons/addon-not-found' );
			}
			?>

		</div>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>
	</main>

</div>
