<?php
/**
 * HTML content for clone tab.
 *
 * @package everest-backup
 */

use Everest_Backup\Modules\Cloner;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_cloner = new Cloner();
$everest_backup_cloner->handle_migration_key();

$everest_backup_migration_key = $everest_backup_cloner->get_migration_key();

?>

<div class="migration-clone-container">
	<div class="clone-wrapper">

		<div id="col-container" class="wp-clearfix">

			<div id="col-left" class="">

				<div class="col-wrap">

					<div class="form-wrap">

						<h2><?php esc_html_e( 'Clone Site', 'everest-backup' ); ?></h2>

						<form method="post" class="validate">

							<input type="hidden" name="page" value="everest-backup-migration_clone">
							<input type="hidden" name="tab" value="clone">
							<?php everest_backup_nonce_field( $everest_backup_cloner->nonce_key, false ); ?>

							<div class="form-field form-required">
								<label><?php esc_html_e( 'Paste Migration Key', 'everest-backup' ); ?></label>
								<input required autocomplete="off" type="text" id="migration_key_field" name="migration_key" value="<?php echo esc_attr( $everest_backup_migration_key ); ?>">
								<?php
								if ( $everest_backup_migration_key ) {
									printf( '<strong>%1$s : %2$s</strong>', esc_html__( 'Migration Key Length', 'everest-backup' ), absint( $everest_backup_cloner->get_key_length() ) );
								}
								?>
							</div>

							<?php

							$btn_other_attributes = array();

							if ( $everest_backup_migration_key ) {
								$btn_other_attributes['disabled'] = 'disabled';
							}

							submit_button( __( 'Verify Key', 'everest-backup' ), 'primary', 'verify_key', true, $btn_other_attributes );
							?>

						</form>

					</div>

				</div>

			</div>

			<div id="col-right" class="">

				<div class="col-wrap">

					<div class="form-wrap">

						<h2><?php esc_html_e( 'Information', 'everest-backup' ); ?></h2>

						<?php if ( ! $everest_backup_migration_key ) { ?>
							<p class="description"><?php esc_html_e( 'Please paste your migration key that you have copied from another website, to extract the file information.', 'everest-backup' ); ?></p>
							<?php
						} else {
							$everest_backup_is_clonable        = $everest_backup_cloner->is_clonable();
							$everest_backup_migration_key_info = $everest_backup_cloner->get_key_info();

							if ( is_bool( $everest_backup_is_clonable ) && is_array( $everest_backup_migration_key_info ) ) {

								/**
								 * If there's no error.
								 */
								?>
								<form id='ebwp-clone-form' method="post" class="validate">

									<p class="notice notice-info">
										<?php esc_html_e( 'Please make sure below informations are correct.', 'everest-backup' ); ?>
									</p>
									<ul>
										<li><?php printf( '<strong>%1$s :</strong> %2$s', esc_html__( 'Filename', 'everest-backup' ), esc_html( $everest_backup_migration_key_info['name'] ) ); ?></li>
										<li><?php printf( '<strong>%1$s :</strong> %2$s', esc_html__( 'Created On', 'everest-backup' ), esc_html( wp_date( 'h:i:s A [F j, Y]', $everest_backup_migration_key_info['time'] ) ) ); ?></li>
										<li><?php printf( '<strong>%1$s :</strong> %2$s', esc_html__( 'Size', 'everest-backup' ), esc_html( everest_backup_format_size( $everest_backup_migration_key_info['size'] ) ) ); ?></li>
									</ul>

									<?php everest_backup_nonce_field( $everest_backup_cloner->nonce_key, false ); ?>
									<input type="hidden" name="page" value="<?php echo esc_attr( $args['request']['page'] ); ?>">
									<input type="hidden" name="file" value="<?php echo esc_attr( $everest_backup_migration_key_info['name'] ); ?>">
									<input type="hidden" name="size" value="<?php echo esc_attr( $everest_backup_migration_key_info['size'] ); ?>">
									<input type="hidden" name="download_url" value="<?php echo esc_url( $everest_backup_migration_key_info['url'] ); ?>">

									<?php submit_button( __( 'Clone', 'everest-backup' ), 'primary', 'clone_init' ); ?>

								</form>
								<?php
							} else {
								$everest_backup_clone_issue_tips = array(
									__( 'Make sure you have pasted the valid migration key.', 'everest-backup' ),
									__( 'Make sure the "Migration Key Length" is equal on both of the websites.', 'everest-backup' ),
									__( 'Make sure your server has enough free space available.', 'everest-backup' ),
								);
								?>
								<strong class="notice notice-error">
									<?php echo wp_kses_post( $everest_backup_is_clonable ); ?>
								</strong>
								<?php
								if ( is_array( $everest_backup_clone_issue_tips ) && ! empty( $everest_backup_clone_issue_tips ) ) {
									foreach ( $everest_backup_clone_issue_tips as $everest_backup_clone_issue_tip ) {
										?>
										<ul style="list-style: disc;">
											<li><?php echo esc_html( $everest_backup_clone_issue_tip ); ?></li>
										</ul>
										<?php
									}
								}
							}
						}
						?>

					</div>

				</div>

			</div>

		</div>

	</div>
</div>
