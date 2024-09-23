<?php
/**
 * Template file for the main backup page.
 *
 * @package everest-backup
 */

use Everest_Backup\Modules\Migration_Clone_Tab;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_migration_clone_tab = new Migration_Clone_Tab();

everest_backup_render_view(
	'template-parts/modal',
	array(
		'on_success'     => array(
			'title'   => __( 'Website Cloned', 'everest-backup' ),
			'content' => function () {
				?>
				<p style="font-size: initial; font-weight: bold;"><?php esc_html_e( 'Please do not forget to save the permalink from sites settings.', 'everest-backup' ); ?></p>
				<a href="<?php echo esc_url( network_admin_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Go To Dashboard', 'everest-backup' ); ?></a>
				<?php
			},
		),
		'on_error'       => array(
			'title'   => __( 'Cloning Aborted', 'everest-backup' ),
			'content' => function () {

				everest_backup_render_view( 'template-parts/on-error-modal' );
				?>
				<a href="<?php echo esc_url( network_admin_url() ); ?>" class="button button-primary"><?php esc_html_e( 'Go To Dashboard', 'everest-backup' ); ?></a>
				<?php
			},
		),
		'on_process_msg' => function () {
			?>
			<div id="import-on-process">

				<div id="process-info">
					<strong>
						<p class="process-message">
							<?php esc_html_e( 'Initialized cloning. Downloading file from host...', 'everest-backup' ); ?>
						</p>
					</strong>

					<details class="process-details hidden" style="margin-bottom: 10px;cursor: pointer;">
						<summary><?php esc_html_e( 'Click to view details', 'everest-backup' ); ?></summary>
						<textarea wrap="off" readonly style="height: 125px;width: 100%;background: #000;color: #01e901;margin-top:10px;"></textarea>
					</details>

					<div class="progress progress-striped active">
						<div role="progressbar" style="width: 0%;" class="progress-bar progress-bar-success text-left">
							<span></span>
						</div>
					</div>
				</div>

				<strong><?php esc_html_e( 'The cloning is in progress. Please do not close this window or tab.', 'everest-backup' ); ?></strong>

			</div>
			<?php
		},
	)
);


?>
<div class="wrap">

	<hr class="wp-header-end">

	<?php
		everest_backup_render_view( 'template-parts/header' );
	?>
	<main class="everest-backup-wrapper">
		<div id="everest-backup-container">
			<?php
			if ( empty( $args['proc_lock'] ) ) {
				$everest_backup_migration_clone_tab->display();
			} else {
				everest_backup_render_view( 'template-parts/proc-lock-info', $args['proc_lock'] );
			}
			?>
		</div>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>
	</main>
</div>
