<?php
/**
 * HTML content for the settings schedule backup tab.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_settings    = ! empty( $args['settings'] ) ? $args['settings'] : array();
$everest_backup_excludes    = ! empty( $args['backup_excludes'] ) ? $args['backup_excludes'] : array();
$everest_backup_cron_cycles = ! empty( $args['cron_cycles'] ) ? $args['cron_cycles'] : array();

$everest_backup_schedule_backup_enabled = isset( $everest_backup_settings['schedule_backup']['enable'] ) && $everest_backup_settings['schedule_backup']['enable'];
$everest_backup_tr_rows_class           = $everest_backup_schedule_backup_enabled ? 'schedule-backup-table-rows' : 'schedule-backup-table-rows hidden';

$everest_backup_cron_cycle_hook    = ! empty( $everest_backup_settings['schedule_backup']['cron_cycle'] ) ? $everest_backup_settings['schedule_backup']['cron_cycle'] . '_hook' : '';
$everest_backup_cron_next_schedule = wp_next_scheduled( $everest_backup_cron_cycle_hook );

$everest_backup_increment_cron_cycle_hook    = ! empty( $everest_backup_settings['schedule_backup']['increment_cycle'] ) ? $everest_backup_settings['schedule_backup']['increment_cycle'] . '_hook' : '';
$everest_backup_increment_cron_next_schedule = wp_next_scheduled( $everest_backup_increment_cron_cycle_hook );

$everest_backup_custom_name_tag = ! empty( $everest_backup_settings['schedule_backup']['custom_name_tag'] ) ? $everest_backup_settings['schedule_backup']['custom_name_tag'] : '';

$incremental_enabled = isset( $everest_backup_settings['schedule_backup']['set_incremental_backup'] ) && $everest_backup_settings['schedule_backup']['set_incremental_backup'];
?>
<form method="post" class="postbox">
	<div class="schedule-backup-wrapper">

		<p class="description"><?php esc_html_e( 'Backup your website automatically in your set schedule.', 'everest-backup' ); ?></p>

		<table class="form-table" id="schedule-backup">
			<tbody>

				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'everest-backup' ); ?></th>
					<td>
						<label>
							<?php
							everest_backup_switch(
								array(
									'name'    => 'everest_backup_settings[schedule_backup][enable]',
									'id'      => 'enable-disable',
									'checked' => $everest_backup_schedule_backup_enabled,
								)
							);
							?>
							<span><?php esc_html_e( 'Enable/Disable schedule backup.', 'everest-backup' ); ?></span>
						</label>
					</td>
				</tr>
				<tr class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>">
					<td></td>
					<td>
					<?php
					$get                            = everest_backup_get_submitted_data( 'get' );
					$get['manual_scheduled_backup'] = 'run';
					$current_url                    = admin_url( sprintf( 'admin.php?%s', http_build_query( $get ) ) );
					echo '<a class="everest_bs_manual_backup" href="' . esc_url( $current_url ) . '">Create Scheduled Backup Now</a>';
					?>
					</td>
				</tr>

				<?php

				/**
				 * Action hook after tbody opening tag.
				 *
				 * @since 1.1.2
				 */
				do_action(
					'everest_backup_schedule_backup_after_tbody_open',
					$args,
					$everest_backup_tr_rows_class
				);
				?>

				<tr class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>">
					<th scope="row"><?php esc_html_e( 'Schedule Cycle', 'everest-backup' ); ?></th>
					<td>
						<label>
							<span><?php esc_html_e( 'Backup', 'everest-backup' ); ?> </span>

							<select id="everest_backup_settings_schedule_backup_cron_cycle" name="everest_backup_settings[schedule_backup][cron_cycle]">
								<?php
								if ( is_array( $everest_backup_cron_cycles ) && ! empty( $everest_backup_cron_cycles ) ) {
									foreach ( $everest_backup_cron_cycles as $everest_backup_cron_cycle_key => $everest_backup_cron_cycle ) {
										if ( ! $everest_backup_cron_cycle['interval'] ) {
											?>
											<option disabled><?php echo esc_html( $everest_backup_cron_cycle['display'] ); ?></option>
											<?php
										} else {
											?>
											<option 
												<?php isset( $everest_backup_settings['schedule_backup']['cron_cycle'] ) && $everest_backup_settings['schedule_backup']['cron_cycle'] ? selected( $everest_backup_settings['schedule_backup']['cron_cycle'], $everest_backup_cron_cycle_key ) : ''; ?>
												value="<?php echo esc_attr( $everest_backup_cron_cycle_key ); ?>"><?php echo esc_html( $everest_backup_cron_cycle['display'] ); ?></option>
											<?php
										}
									}
								}
								?>
							</select>

							<span><?php esc_html_e( 'at', 'everest-backup' ); ?> </span>

							<input type="time" value="<?php echo ! empty( $everest_backup_settings['schedule_backup']['cron_cycle_time'] ) ? esc_attr( $everest_backup_settings['schedule_backup']['cron_cycle_time'] ) : '00:00'; ?>" name="everest_backup_settings[schedule_backup][cron_cycle_time]">
						</label>
						<p>
							<?php esc_html_e( 'Server Time:', 'everest-backup' ); ?>
							<code><?php echo esc_html( wp_date( 'h:i:s A e' ) ); ?></code>
							<small><a href="<?php echo esc_url( admin_url( 'options-general.php#timezone_string' ) ); ?>">Change Timezone?</a></small>
						</p>

						<p><?php esc_html_e( 'Next Backup In:', 'everest-backup' ); ?> <code><?php echo $everest_backup_cron_next_schedule ? esc_html( human_time_diff( $everest_backup_cron_next_schedule ) ) : 'N/A'; ?></code></p>
					</td>
				</tr>

				<tr id="backup-location-dropdown" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>">
					<th scope="row"><?php esc_html_e( 'Save To', 'everest-backup' ); ?></th>
					<td>
						<label>
							<span><?php esc_html_e( 'Save backup file to', 'everest-backup' ); ?> </span>
							<?php
							everest_backup_package_location_dropdown(
								array(
									'name'     => 'everest_backup_settings[schedule_backup][save_to]',
									'selected' => isset( $everest_backup_settings['schedule_backup']['save_to'] ) && $everest_backup_settings['schedule_backup']['save_to'] ? $everest_backup_settings['schedule_backup']['save_to'] : '',
								)
							);
							?>
							<span><?php esc_html_e( 'after schedule backup is completed.', 'everest-backup' ); ?></span>
						</label>
					</td>
				</tr>

				<?php if ( everest_backup_pro_active() ) { ?>
					<tr id="incremental-backup" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>">
						<th scope="row">
							<?php
								esc_html_e( 'Set Incremental Backup', 'everest-backup' );
								everest_backup_tooltip( __( 'Please refer to documentation for more info on incremental backups.', 'everest-backup' ) );
							?>
						</th>

						<td>
							<label>
								<?php
								everest_backup_switch(
									array(
										'id'              => 'everest_backup_settings_schedule_backup_set_incremental_backup',
										'name'            => 'everest_backup_settings[schedule_backup][set_incremental_backup]',
										'checked'         => $incremental_enabled,
										'label_checked'   => __( 'Yes', 'everest-backup' ),
										'label_unchecked' => __( 'No', 'everest-backup' ),
									)
								);
								?>
							</label>
						</td>
					</tr>

					<tr id="increment_cycle" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>" <?php echo $incremental_enabled ? '' : 'style="display:none;"'; ?> >
						<th scope="row"><?php esc_html_e( 'Increment Cycle', 'everest-backup' ); ?></th>
						<td>
							<label>
								<span><?php esc_html_e( 'Backup', 'everest-backup' ); ?> </span>

								<select id="everest_backup_settings_schedule_backup_increment_cycle" name="everest_backup_settings[schedule_backup][increment_cycle]">
									<?php
									if ( is_array( $everest_backup_cron_cycles ) && ! empty( $everest_backup_cron_cycles ) ) {
										foreach ( $everest_backup_cron_cycles as $everest_backup_cron_cycle_key => $everest_backup_cron_cycle ) {
											if ( ! $everest_backup_cron_cycle['interval'] ) {
												?>
												<option disabled><?php echo esc_html( $everest_backup_cron_cycle['display'] ); ?></option>
												<?php
											} else {
												?>
												<option 
													<?php isset( $everest_backup_settings['schedule_backup']['increment_cycle'] ) && $everest_backup_settings['schedule_backup']['increment_cycle'] ? selected( $everest_backup_settings['schedule_backup']['increment_cycle'], $everest_backup_cron_cycle_key ) : ''; ?>
													value="<?php echo esc_attr( $everest_backup_cron_cycle_key ); ?>"><?php echo esc_html( $everest_backup_cron_cycle['display'] ); ?></option>
												<?php
											}
										}
									}
									?>
								</select>

								<span><?php esc_html_e( 'at', 'everest-backup' ); ?> </span>

								<input type="time" value="<?php echo ! empty( $everest_backup_settings['schedule_backup']['increment_cycle_time'] ) ? esc_attr( $everest_backup_settings['schedule_backup']['increment_cycle_time'] ) : '00:00'; ?>" name="everest_backup_settings[schedule_backup][increment_cycle_time]">
							</label>
							<p><?php esc_html_e( 'Next Backup In:', 'everest-backup' ); ?> <code><?php echo $everest_backup_increment_cron_next_schedule ? esc_html( human_time_diff( $everest_backup_increment_cron_next_schedule ) ) : 'N/A'; ?></code></p>
						</td>
					</tr>
					<script>
						window.addEventListener('DOMContentLoaded', () => {
							const cycle = JSON.parse('<?php echo is_array( $everest_backup_cron_cycles ) ? json_encode( $everest_backup_cron_cycles ) : array(); ?>');
							const keys = Object.keys(cycle)
							const schedule_cycle = document.getElementById('everest_backup_settings_schedule_backup_cron_cycle')
							const tag_wrapper = document.getElementById('custom-name-tag-wrapper')
							const exclude_wrapper = document.getElementById('everest-backup-exclude-wrapper')
							const increment_cycle = document.getElementById('everest_backup_settings_schedule_backup_increment_cycle')
							const increment_cycle_value = '<?php echo isset( $everest_backup_settings['schedule_backup']['increment_cycle'] ) ? $everest_backup_settings['schedule_backup']['increment_cycle'] : ''; ?>'
							const set_incremental_backup = document.getElementById('everest_backup_settings_schedule_backup_set_incremental_backup')

							set_incremental_backup.addEventListener('change', updateIncrementCycleValues)
							set_incremental_backup.addEventListener('change', showHideCustomNameTagAndExclude)

							schedule_cycle.addEventListener('change', updateIncrementCycleValues)

							function updateIncrementCycleValues() {
								if (set_incremental_backup.checked) {
									const schedule_cycle_value = schedule_cycle.value
									let html = ''
									increment_cycle.innerHTML = html
									for ( let i=0; i < keys.length; i++ ) {
										if (schedule_cycle_value == keys[i]) {
											break
										}
										html = increment_cycle.innerHTML;
										let selected = ''
										if (keys[i] == increment_cycle_value) {
											selected = 'selected'
										}
										html += '<option value="'+keys[i]+'" '+selected+'>'+cycle[keys[i]].display+'</option>'
										increment_cycle.innerHTML = html
									}
									document.getElementById('increment_cycle').style.display = 'contents'
								} else {
									document.getElementById('increment_cycle').style.display = 'none'
								}
							}

							function showHideCustomNameTagAndExclude() {
								if (this.checked) {
									tag_wrapper.style.display = 'none'
									exclude_wrapper.style.display = 'none'
								} else {
									tag_wrapper.style.display = ''
									exclude_wrapper.style.display = ''
								}
							}
						})
					</script>
				<?php } ?>

				<tr id="delete-from-server" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>" style="display:none;">

					<th scope="row">
						<?php
							esc_html_e( 'Delete from Local Web Server', 'everest-backup' );
							everest_backup_tooltip( __( 'Delete the backup file from the server after uploading the file to the cloud.', 'everest-backup' ) );
						?>
					</th>

					<td>
						<label>
							<?php
							everest_backup_switch(
								array(
									'name'            => 'everest_backup_settings[schedule_backup][delete_from_server]',
									'checked'         => isset( $everest_backup_settings['schedule_backup']['delete_from_server'] ) && $everest_backup_settings['schedule_backup']['delete_from_server'],
									'label_checked'   => __( 'Yes', 'everest-backup' ),
									'label_unchecked' => __( 'No', 'everest-backup' ),
								)
							);
							?>
						</label>
					</td>
				</tr>

				<tr id="custom-name-tag-wrapper" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>" <?php echo $incremental_enabled ? 'style="display:none;"' : ''; ?>>

					<th scope="row">
						<?php
							esc_html_e( 'Custom Name Tag', 'everest-backup' );
							everest_backup_tooltip( __( 'Set custom name tag for backup file. Your custom name tag will be displayed as: "ebwp-CUSTOM-NAME-TAG-xxxx-xxxx.ebwp"', 'everest-backup' ) );
						?>
					</th>

					<td>
					<label>
						<input name="everest_backup_settings[schedule_backup][custom_name_tag]" id="custom-name-tag" type="text" value="<?php echo esc_attr( $everest_backup_custom_name_tag ); ?>" autocomplete="off" placeholder="<?php esc_html_e( '( Optional )', 'everest-backup' ); ?>">
					</label>
					</td>
				</tr>

				<tr id="everest-backup-exclude-wrapper" class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>" <?php echo $incremental_enabled ? 'style="display:none;"' : ''; ?>>
					<th scope="row"><?php esc_html_e( 'Exclude', 'everest-backup' ); ?></th>
					<td>
						<details open>

							<summary style="cursor:pointer;">
								<span class="description"><?php esc_html_e( 'Unchecked modules will be ignored during the backup.', 'everest-backup' ); ?></span>
							</summary>

							<table class="form-table">
								<tbody>

									<?php
									if ( is_array( $everest_backup_excludes ) && ! empty( $everest_backup_excludes ) ) {
										foreach ( $everest_backup_excludes as $everest_backup_exclude_key => $everest_backup_exclude ) {

											if ( isset( $everest_backup_settings['schedule_backup'][ $everest_backup_exclude_key ] ) ) {
												$enable_schedule_module = ! $everest_backup_settings['schedule_backup'][ $everest_backup_exclude_key ];
											} else {
												$enable_schedule_module = true; // Set true as default.
											}

											?>
											<tr title="<?php echo esc_attr( $everest_backup_exclude['description'] ); ?>">
												<th scope="row"><?php echo esc_html( $everest_backup_exclude['label'] ); ?></th>
												<td>
													<label>
														<?php
														everest_backup_switch(
															array(
																'name'            => "everest_backup_settings[schedule_backup][$everest_backup_exclude_key]",
																'value_checked'   => 0,
																'value_unchecked' => 1,
																'label_checked'   => __( 'Included', 'everest-backup' ),
																'label_unchecked' => __( 'Ignored', 'everest-backup' ),
																'checked'         => $enable_schedule_module,
															)
														);
														?>
													</label>
												</td>
											</tr>
											<?php
										}
									}
									?>

								</tbody>
							</table>
						</details>

					</td>
				</tr>

				<tr class="<?php echo esc_attr( $everest_backup_tr_rows_class ); ?>">
					<th scope="row"><?php esc_html_e( 'Notify', 'everest-backup' ); ?></th>
					<td>
						<label>
							<?php
							everest_backup_switch(
								array(
									'name'    => 'everest_backup_settings[schedule_backup][notify]',
									'checked' => isset( $everest_backup_settings['schedule_backup']['notify'] ) && $everest_backup_settings['schedule_backup']['notify'],
								)
							);
							?>
							<span><?php esc_html_e( 'Send me an email log after schedule backup is completed.', 'everest-backup' ); ?></span>
						</label>
					</td>
				</tr>

				<?php

				/**
				 * Action hook before tbody closing tag.
				 *
				 * @since 1.1.2
				 */
				do_action(
					'everest_backup_schedule_backup_before_tbody_close',
					$args,
					$everest_backup_tr_rows_class
				);
				?>

			</tbody>
		</table>
	</div>

	<?php
	everest_backup_nonce_field( EVEREST_BACKUP_SETTINGS_KEY . '_nonce' );
	submit_button( __( 'Save Settings', 'everest-backup' ) );
	?>
</form>
<?php
