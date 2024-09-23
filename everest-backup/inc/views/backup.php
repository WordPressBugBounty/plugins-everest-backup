<?php
/**
 * Template file for the main backup page.
 *
 * @package everest-backup
 */

use Everest_Backup\Modules\Backup_Tab;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_backup_tab = new Backup_Tab();

everest_backup_render_view(
	'template-parts/modal',
	array(
		'is_dismissible' => true,
		'on_process_msg' => function () {
			?>
			<div id="backup-on-process">

				<div id="process-info">
					<strong>
						<p class="process-message"></p>
					</strong>
					<details class="process-details" style="margin-bottom: 10px;cursor: pointer;">
						<summary><?php esc_html_e( 'Click to view details', 'everest-backup' ); ?></summary>
						<textarea wrap="off" readonly style="height: 125px;width: 100%;background: #000;color: #01e901;margin-top:10px;"></textarea>
					</details>
					<div class="progress progress-striped active">
						<div role="progressbar" style="width: 0%;" class="progress-bar progress-bar-success text-left">
							<span></span>
						</div>
					</div>
				</div>

				<strong><?php esc_html_e( 'We are creating backup of your website. Please do not close this window.', 'everest-backup' ); ?></strong>

				<button class="button button-danger " id="btn-abort">
					<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15">
						<path
						id="Icon_metro-cross"
						data-name="Icon metro-cross"
						d="M17.434,13.979h0l-4.55-4.551,4.55-4.551h0a.47.47,0,0,0,0-.663l-2.15-2.15a.47.47,0,0,0-.663,0h0L10.071,6.616,5.52,2.065h0a.47.47,0,0,0-.663,0l-2.15,2.15a.47.47,0,0,0,0,.663h0L7.258,9.428,2.708,13.979h0a.47.47,0,0,0,0,.663l2.15,2.15a.47.47,0,0,0,.663,0h0l4.551-4.551,4.551,4.551h0a.47.47,0,0,0,.663,0l2.15-2.15a.47.47,0,0,0,0-.663Z"
						transform="translate(-2.571 -1.928)"
						fill="#D14E39"/>
					</svg>
					<span><?php esc_html_e( 'Abort', 'everest-backup' ); ?></span>
				</button>
			</div>
			<?php
		},
		'on_success'     => array(
			'title'   => __( 'Backup Completed', 'everest-backup' ),
			'content' => function () {
				?>
				<strong id="extra-message" class="hidden">
					<p class="process-message"></p>
				</strong>
				<div id="backup-complete-modal-footer"></div>
							<?php
			},
		),
		'on_error'       => array(
			'title'   => __( 'Backup Failed...!', 'everest-backup' ),
			'content' => function () {
				everest_backup_render_view( 'template-parts/on-error-modal' );
			},
		),
	)
);

?>

<div class="wrap">
	<hr class="wp-header-end">

	<?php everest_backup_render_view( 'template-parts/header' ); ?>

	<main class="everest-backup-wrapper">
		<div id="everest-backup-container">
			<?php
			if ( empty( $args['proc_lock'] ) ) {
				$everest_backup_backup_tab->display();
			} else {
				everest_backup_render_view( 'template-parts/proc-lock-info', $args['proc_lock'] );
			}
			?>
		</div>

		<?php
		everest_backup_render_view(
			'template-parts/sidebar',
			array(
				'current_tab' => $everest_backup_backup_tab->get_current(),
			)
		);
		?>
	</main>
</div>
