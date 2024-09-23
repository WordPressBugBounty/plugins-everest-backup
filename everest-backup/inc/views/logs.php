<?php
/**
 * Template file for displaying the saved logs.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_array( $args ) ) {
	return;
}

$everest_backup_logs_table_obj = ! empty( $args['logs_table_obj'] ) ? $args['logs_table_obj'] : false;

if ( ! is_object( $everest_backup_logs_table_obj ) ) {
	return;
}

$everest_backup_logs_table_obj->prepare_items();

?>
<div class="wrap">

	<hr class="wp-header-end">

	<?php
		everest_backup_render_view( 'template-parts/header' );
	?>
	<main class="everest-backup-wrapper">
		<form id="everest-backup-container">
			<input type="hidden" name="page" value="<?php echo esc_attr( $args['page'] ); ?>">
			<?php $everest_backup_logs_table_obj->display(); ?>
		</form>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>
	</main>
</div>
