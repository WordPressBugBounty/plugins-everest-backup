<?php
/**
 * HTML content for migration tab.
 *
 * @package everest-backup
 */

use Everest_Backup\Modules\Migration;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_migration     = new Migration();
$everest_backup_migration_key = $everest_backup_migration->get_migration_key();

?>

<div class="migration-clone-container <?php echo $everest_backup_migration_key ? 'migration-key-generated' : ''; ?>">

	<div class="migration-wrapper">

		<div id="col-container" class="wp-clearfix">

			<div id="col-left" class="">

				<div class="col-wrap">

					<div class="form-wrap">

						<h2><?php esc_html_e( 'Generate Migration Key', 'everest-backup' ); ?></h2>

						<form method="get" class="validate">

							<input type="hidden" name="page" value="everest-backup-migration_clone">
							<input type="hidden" name="tab" value="migration">
							<?php everest_backup_nonce_field( $everest_backup_migration->nonce_key, false ); ?>

							<div class="form-field form-required">
								<label><?php esc_html_e( 'Select from available backup files', 'everest-backup' ); ?></label>
								<?php
								everest_backup_backup_files_dropdown(
									array(
										'name'     => 'file',
										'id'       => 'backup-files-dropdown',
										'required' => true,
										'selected' => ! empty( $args['request']['file'] ) ? $args['request']['file'] : '',
									)
								);
								?>
							</div>

							<?php
							if ( $everest_backup_migration_key ) {
								?>
								<div class="form-field form-required">
									<label><?php esc_html_e( 'Migration Key', 'everest-backup' ); ?></label>
									<div class="copy-key-wrapper">
										<input type="text" class="text" readonly value="<?php echo esc_attr( $everest_backup_migration_key ); ?>">
										<div class="copy-button">
											<span class="dashicons dashicons-admin-page"></span>
											<span class="copy-text">Copy Key</span>
										</div>
									</div>
									<?php printf( '<strong>%1$s : %2$s</strong>', esc_html__( 'Migration Key Length', 'everest-backup' ), absint( $everest_backup_migration->get_key_length() ) ); ?>
									<p class="description"><?php esc_html_e( 'Please copy above migration key and paste it in the "Clone" tab of your destination website.', 'everest-backup' ); ?></p>
								</div>
								<?php
							}
							?>
							<p id="generate-migration-key" class="submit <?php echo $everest_backup_migration_key ? esc_attr( 'hidden ' ) : ''; ?>">
								<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Generate', 'everest-backup' ); ?>">
							</p>

						</form>

					</div>

				</div>

			</div>

			<div id="col-right" class="">

				<div class="col-wrap">

					<div class="form-wrap">

						<h2><?php esc_html_e( 'Create New Backup', 'everest-backup' ); ?></h2>

						<a href="<?php echo esc_url( network_admin_url( '/admin.php?page=everest-backup-export' ) ); ?>" class="button-primary"><?php esc_html_e( 'Go To Backup Page', 'everest-backup' ); ?></a>

					</div>

				</div>

			</div>

			<!-- <div class="ebwp-center">
				<h1><?php esc_html_e( '--- OR ---', 'everest-backup' ); ?></h1>
			</div> -->

		</div>
	</div>
</div>
