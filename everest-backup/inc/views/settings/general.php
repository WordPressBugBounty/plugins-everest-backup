<?php
/**
 * HTML content for the settings general tab.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_settings = ! empty( $args['settings'] ) ? $args['settings'] : array();

$tags_display_type                 = ! empty( $everest_backup_settings['general']['tags_display_type'] ) ? $everest_backup_settings['general']['tags_display_type'] : 'included';
$delete_after_restore              = ! empty( $everest_backup_settings['general']['delete_after_restore'] ) ? $everest_backup_settings['general']['delete_after_restore'] : 'yes';
$encrypt_backup                    = ! empty( $everest_backup_settings['general']['encrypt_backup'] ) ? $everest_backup_settings['general']['encrypt_backup'] : 'no';
$logger_speed                      = ! empty( $everest_backup_settings['general']['logger_speed'] ) ? absint( $everest_backup_settings['general']['logger_speed'] ) : 200;
$show_menu_in_site_admin_dashboard = ! empty( $everest_backup_settings['general']['show_menu_in_site_admin_dashboard'] ) ? $everest_backup_settings['general']['show_menu_in_site_admin_dashboard'] : 'no';

?>
<form method="post">

	<p class="description"><?php esc_html_e( 'General configuration for your Everest Backup plugin.', 'everest-backup' ); ?></p>

	<table class="form-table" id="general">
		<tbody>

			<?php

			/**
			 * Action hook after tbody opening tag.
			 *
			 * @since 1.1.2
			 */
			do_action( 'everest_backup_settings_general_after_tbody_open', $args );
			?>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Admin Email', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Email address that will be used by Everest Backup plugin. WordPress admin email will be used as default.', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<input type="email" value="<?php echo esc_attr( everest_backup_get_admin_email() ); ?>" name="everest_backup_settings[general][admin_email]">
						<a href="<?php echo esc_url( add_query_arg( 'email-test', 'sending', network_admin_url( '/admin.php?page=everest-backup-settings' ) ) ); ?>" class="button button-primary"><?php esc_html_e( 'Send Test Email', 'everest-backup' ); ?></a>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Tags Display Type', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Display "Included" modules or "Excluded" modules as tags in backup files listings. Ex: In history page.', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<select name="everest_backup_settings[general][tags_display_type]">
							<option <?php selected( $tags_display_type, 'included' ); ?> value="included"><?php esc_html_e( 'Included', 'everest-backup' ); ?></option>
							<option <?php selected( $tags_display_type, 'excluded' ); ?> value="excluded"><?php esc_html_e( 'Excluded', 'everest-backup' ); ?></option>
						</select>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Auto Remove', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Auto remove backup files after defined number of days, set 0 to keep all the files.', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<input type="number" value="<?php echo ! empty( $everest_backup_settings['general']['auto_remove_older_than'] ) ? absint( $everest_backup_settings['general']['auto_remove_older_than'] ) : 0; ?>" min="0" name="everest_backup_settings[general][auto_remove_older_than]">
						<span><?php esc_html_e( 'days.', 'everest-backup' ); ?></span>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Exclude Files', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'The file extension must be separated by single comma, without the dot as: zip, lock.', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<input type="text" value="<?php echo ! empty( $everest_backup_settings['general']['exclude_files_by_extension'] ) ? esc_attr( $everest_backup_settings['general']['exclude_files_by_extension'] ) : ''; ?>" placeholder="Ex: zip, lock" name="everest_backup_settings[general][exclude_files_by_extension]">
					</label>
				</td>
			</tr>

			<!-- From v2.0.0 -->

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Backup Encrypt', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Encrypt Backup File Content', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<?php
						if ( everest_backup_pro_active() ) {
							if ( ! extension_loaded( 'openssl' ) ) {
								echo '<input type="hidden" name="everest_backup_settings[general][encrypt_backup]" value="no">';
								echo __( 'OpenSSL extension required for file content encryption is missing. Please refer to <a target="_blank" href="https://www.php.net/manual/en/openssl.installation.php">PHP documentation</a> for info on how to install OpenSSL library.', 'everest-backup' );
							} else { ?>
								<select name="everest_backup_settings[general][encrypt_backup]" id="everest_backup_settings_general_encrypt_backup">
									<option <?php selected( $encrypt_backup, 'yes' ); ?> value="yes"><?php esc_html_e( 'Yes', 'everest-backup' ); ?></option>
									<option <?php selected( $encrypt_backup, 'no' ); ?> value="no"><?php esc_html_e( 'No', 'everest-backup' ); ?></option>
								</select>
								<!-- <div
									id="everest_backup_settings_general_encrypt_backup_password_div"
									style="<?php //echo ( $encrypt_backup === 'yes' ) ? '': 'display:none'; ?>"
								>
									Password?
									<input
										type="text"
										name="everest_backup_settings[general][encrypt_backup_password]"
										id="everest_backup_settings_general_encrypt_backup_password"
										value="<?php //echo ! empty( $everest_backup_settings['general']['encrypt_backup_password'] ) ? esc_attr( $everest_backup_settings['general']['encrypt_backup_password'] ) : ''; ?>"
									/>
								</div>
								<script>
									window.addEventListener('DOMContentLoaded', function () {
										document.getElementById('everest_backup_settings_general_encrypt_backup').addEventListener('change', function () {
											if ( this.value === 'yes' ) {
												document.getElementById('everest_backup_settings_general_encrypt_backup_password_div').style.display = 'block';
											} else {
												document.getElementById('everest_backup_settings_general_encrypt_backup_password_div').style.display = 'none';
											}
										})
									})
								</script> -->
							<?php }
						} else {
							?>
							<select name="" disabled>
								<option value="no"><?php esc_html_e( 'No', 'everest-backup' ); ?></option>
							</select>
							<span>Premium Feature only available with <a target="_blank" href="https://wpeverestbackup.com/bundle-comparison/">Everest Backup Pro</a> Addon </span>
							<?php
						}
						?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Delete After Restore', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Auto delete the backup file after restore, clone and rollback.', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<select name="everest_backup_settings[general][delete_after_restore]">
							<option <?php selected( $delete_after_restore, 'yes' ); ?> value="yes"><?php esc_html_e( 'Yes', 'everest-backup' ); ?></option>
							<option <?php selected( $delete_after_restore, 'no' ); ?> value="no"><?php esc_html_e( 'No', 'everest-backup' ); ?></option>
						</select>
					</label>
				</td>
			</tr>
			<?php
			if ( everest_backup_pro_active() && is_multisite() && is_network_admin() ) {
				?>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Show menu in Site Admin Dashboard', 'everest-backup' ); ?>
					</th>
					<td>
						<label>
							<select name="everest_backup_settings[general][show_menu_in_site_admin_dashboard]">
								<option <?php selected( $show_menu_in_site_admin_dashboard, 'yes' ); ?> value="yes"><?php esc_html_e( 'Yes', 'everest-backup' ); ?></option>
								<option <?php selected( $show_menu_in_site_admin_dashboard, 'no' ); ?> value="no"><?php esc_html_e( 'No', 'everest-backup' ); ?></option>
							</select>
						</label>
					</td>
				</tr>
				<?php
			}
			?>

			<tr>
				<th scope="row">
					<?php esc_html_e( 'Logger Speed', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Speed for fetching process details and logs. This can also affect the overall process time. </br>Range: From 50ms delay (Fastest) To 4000ms delay (Slowest)', 'everest-backup' ) ); ?>
				</th>
				<td>
					<label>
						<span title="<?php esc_attr_e( '50 milliseconds delay' ); ?>"><?php esc_html_e( 'Fastest ( More server load )', 'everest-backup' ); ?></span>
						<input id="logger_speed_range" type="range" step="50" min="50" max="4000" value="<?php echo esc_attr( $logger_speed ); ?>" placeholder="Ex: zip, lock" name="everest_backup_settings[general][logger_speed]">
						<span title="<?php esc_attr_e( '4000 milliseconds delay' ); ?>"><?php esc_html_e( 'Slowest ( Less server load )', 'everest-backup' ); ?></span>
					</label>
					<p class="description"><?php echo sprintf( esc_html__( 'Delay: %s milliseconds', 'everest-backup' ), '<span id="logger_speed_display">' . esc_html( $logger_speed ) . '</span>' ); ?></p>
				</td>
			</tr>

			<?php

			/**
			 * Action hook before tbody closing tag.
			 *
			 * @since 1.1.2
			 */
			do_action( 'everest_backup_settings_general_before_tbody_close', $args );
			?>

		</tbody>
	</table>
	<?php
	everest_backup_nonce_field( EVEREST_BACKUP_SETTINGS_KEY . '_nonce' );
	submit_button( __( 'Save Settings', 'everest-backup' ) );
	?>
</form>
