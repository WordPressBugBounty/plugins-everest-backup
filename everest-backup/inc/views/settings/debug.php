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

$lockfile_generator_url = wp_nonce_url(
	network_admin_url( 'admin.php?page=everest-backup-settings&tab=debug&lockfile=generate' ),
	'fakelockfile-' . get_current_user_id(),
	'_noncefakelockfile'
);

?>
<form method="post">

	<p class="description"><?php esc_html_e( 'Tweakable parameters for testing and debugging.', 'everest-backup' ); ?></p>

	<table class="form-table" id="debug">
		<tbody>

			<tr>
				<th scope="row">
					<?php
					esc_html_e( 'LOCKFILE', 'everest-backup' );
					everest_backup_tooltip( __( 'Click "Generate" to generate fake lockfile.', 'everest-backup' ) );
					?>
				</th>
				<td>
					<a class="button button-link" href="<?php echo esc_url( $lockfile_generator_url ); ?>"><?php esc_html_e( 'Generate', 'everest-backup' ); ?></a>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Throw Error', 'everest-backup' ); ?></th>
				<td>
					<?php
					everest_backup_switch(
						array(
							'name'    => 'everest_backup_settings[debug][throw_error]',
							'checked' => ( isset( $everest_backup_settings['debug']['throw_error'] ) && $everest_backup_settings['debug']['throw_error'] ),
						)
					);
					?>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Exclude Languages Folder', 'everest-backup' ); ?></th>
				<td>
					<?php
					everest_backup_switch(
						array(
							'name'    => 'everest_backup_settings[debug][exclude_languages_folder]',
							'checked' => ( isset( $everest_backup_settings['debug']['exclude_languages_folder'] ) && $everest_backup_settings['debug']['exclude_languages_folder'] ),
						)
					);
					?>
				</td>
			</tr>

		</tbody>
	</table>
	<?php
	everest_backup_nonce_field( EVEREST_BACKUP_SETTINGS_KEY . '_nonce' );
	submit_button( __( 'Save Settings', 'everest-backup' ) );
	?>
</form>
