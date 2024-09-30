<?php
/**
 * Manual backup tab contents.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_excludes = $args['backup_excludes'];

everest_backup_render_view( 'template-parts/message-box' );

?>

<form method="post" class="postbox" id="backup-form">

	<div class="backup-additional-settings">

		<h2 class="title"><?php esc_html_e( 'Exclude Modules', 'everest-backup' ); ?></h2>

		<?php everest_backup_tooltip( __( 'Unchecked modules will be ignored during the backup.', 'everest-backup' ) ); ?>

		<ul class="backup-files-wrapper">
			<?php
			if ( is_array( $everest_backup_excludes ) && ! empty( $everest_backup_excludes ) ) {
				foreach ( $everest_backup_excludes as $everest_backup_exclude_key => $everest_backup_exclude ) {
					?>
					<li title="<?php echo esc_attr( $everest_backup_exclude['description'] ); ?>">
						<div class="left">
							<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="19.437" height="13.193" viewBox="0 0 19.437 13.193">
								<defs>
									<linearGradient id="linear-gradient" x1="0.5" x2="0.5" y2="1" gradientUnits="objectBoundingBox">
										<stop offset="0" stop-color="#e81186" />
										<stop offset="1" stop-color="#0c61dd" />
									</linearGradient>
								</defs>
								<path
									id="Icon_awesome-folder-open"
									data-name="Icon awesome-folder-open"
									d="M19.325,12.337,16.882,16.6a2.153,2.153,0,0,1-1.865,1.091H1.519a.825.825,0,0,1-.7-1.24l2.444-4.266A2.153,2.153,0,0,1,5.129,11.1h13.5a.825.825,0,0,1,.7,1.24ZM5.129,10H16.2V8.348A1.635,1.635,0,0,0,14.578,6.7h-5.4L7.019,4.5H1.62A1.635,1.635,0,0,0,0,6.149V15.7l2.331-4.069A3.24,3.24,0,0,1,5.129,10Z"
									transform="translate(0 -4.5)"
									fill="url(#linear-gradient)"
									/>
							</svg>

							<?php echo esc_html( $everest_backup_exclude['label'] ); ?>
						</div>

						<div class="right">
							<?php
							everest_backup_switch(
								array(
									'name'            => $everest_backup_exclude_key,
									'value_checked'   => 0,
									'value_unchecked' => 1,
									'label_checked'   => __( 'Included', 'everest-backup' ),
									'label_unchecked' => __( 'Ignored', 'everest-backup' ),
									'checked'         => true,
								)
							);
							?>
						</div>
					</li>

					<?php
				}
			}
			?>

		</ul>

		<h2 class="title"><?php esc_html_e( 'Backup Location', 'everest-backup' ); ?></h2>

		<?php everest_backup_tooltip( __( 'Select the backup storage location.', 'everest-backup' ) ); ?>

		<ul class="backup-files-wrapper backup-location">
			<li id="backup-location-dropdown">
				<div class="left">
					<?php esc_html_e( 'Location', 'everest-backup' ); ?>
					<?php everest_backup_tooltip( __( 'Primary location to save a copy of created backup file. Choosing “Local Web Server” will keep the backups only in the web server i.e "wp-content > ebwp-backups" folder.', 'everest-backup' ) ); ?>
				</div>
				<div class="right">
					<label>
						<?php
						everest_backup_package_location_dropdown(
							array(
								'name' => 'save_to',
							)
						);
						?>
					</label>
				</div>
			</li>

			<li id="custom-name-tag-wrapper">
				<div class="left">
					<?php
					esc_html_e( 'Custom name tag', 'everest-backup' );

					everest_backup_tooltip( __( 'Set custom name tag for backup file. Your custom name tag will be displayed as: "ebwp-CUSTOM-NAME-TAG-xxxx-xxxx.ebwp"', 'everest-backup' ) );
					?>
				</div>
				<div class="right">
					<label>
						<input name="custom_name_tag" id="custom-name-tag" type="text" value="" autocomplete="off" placeholder="<?php esc_html_e( '( Optional )', 'everest-backup' ); ?>">
					</label>
				</div>
			</li>

			<li id="delete-from-server" style="display: none;">
				<div class="left">
					<?php
					esc_html_e( 'Delete from Local Web Server', 'everest-backup' );

					everest_backup_tooltip( __( 'Delete the backup file from the server after uploading the file to the cloud.', 'everest-backup' ) );
					?>
				</div>
				<div class="right">
					<label>
						<?php
						everest_backup_switch(
							array(
								'name'            => 'delete_from_server',
								'label_checked'   => __( 'Yes', 'everest-backup' ),
								'label_unchecked' => __( 'No', 'everest-backup' ),
							)
						);
						?>
					</label>
				</div>
			</li>

		</ul>
	</div>

	<div class="everest-backup-btn-wrapper" id="backup-wrapper">
		<?php
		if ( everest_backup_pro_active() && is_multisite() && is_network_admin() ) {
			$sites = get_sites( array( 'deleted' => 0 ) );
			?>
			<label for="ebwp_site_db_prefix">Select site to backup: </label>
			<select name="ebwp_site_db_prefix" id="ebwp_site_db_prefix">
			<?php foreach ( $sites as $site ) {
				$site_name = trim( $site->path, '/' );
				if ( '' === $site_name ) {
					$site_name = 'All';
				}
				$prefix = ( 1 === $site->blog_id ) ? $wpdb->base_prefix : $wpdb->get_blog_prefix($site->blog_id);
				?>
				<option value="<?php echo esc_attr( $prefix )?>"><?php echo esc_attr( $site_name )?></option>
			<?php }	?>
			</select>
			<?php
		}
		?>
		<button class="button button-primary button-hero" id="btn-backup">
			<svg width="65.049" height="43.366" viewBox="0 0 65.049 43.366">
				<path d="M52.446,22.371A20.308,20.308,0,0,0,14.5,16.95a16.256,16.256,0,0,0,1.762,32.416H51.5a13.513,13.513,0,0,0,.949-27Zm-14.5,8.023V41.235H27.1V30.394H18.973L32.525,16.842,46.077,30.394Z" transform="translate(0 -6)" fill="#fff"/>
			</svg>
			<span><?php esc_html_e( 'Backup Now!', 'everest-backup' ); ?></span>
		</button>

		<details class="card hidden" id="everest-backup-logs-container"></details>
	</div>

</form>
