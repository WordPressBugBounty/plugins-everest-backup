<?php
/**
 * HTML content for the settings cloud tab.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_settings          = ! empty( $args['settings'] ) ? $args['settings'] : array();
$everest_backup_package_locations = ! empty( $args['package_locations'] ) ? $args['package_locations'] : array();

if ( everest_backup_2fa_active() ) {
	if ( ! empty( $_POST['everest_backup_auth_totp'] ) ) {
		$otp      = (int) $_POST['everest_backup_auth_totp'];
		$response = everest_backup_2fa_check_otp( $otp );
		if ( isset( $response['success'] ) && $response['success'] ) {
			set_transient( 'everest_backup_2fa_checked', true, 600 );
		} elseif ( isset( $response['success'] ) && ! empty( $response['message'] ) ) {
			echo $response['message'];
		}
	}
	if ( ! empty( $_POST['everest_backup_auth_recovery_code'] ) ) {
		$recovery_code = $_POST['everest_backup_auth_recovery_code'];
		$response      = everest_backup_2fa_check_recovery_code( $recovery_code );
		if ( isset( $response['success'] ) && $response['success'] ) {
			set_transient( 'everest_backup_2fa_checked', true, 600 );
		} elseif ( isset( $response['success'] ) && ! empty( $response['message'] ) ) {
			echo $response['message'];
		}
	}
	if ( ! get_transient( 'everest_backup_2fa_checked' ) ) {
		?>
		<style>
			#everest_backup_2fa_authenticate_form button.a-btn{
				border: none;
				background: none;
			}
			#everest_backup_2fa_authenticate_form button.a-btn:hover{
				text-decoration: underline;
				cursor: pointer;
			}
		</style>
		<form method="POST" id="everest_backup_2fa_authenticate_form">
			<div id="everest_backup_auth_using_otp">
				Please enter your OTP from Authenticator App:
				<input type="text" name="everest_backup_auth_totp" id="everest_backup_auth_totp">
				<button
					type="button"
					class="a-btn"
					onclick="everest_backup_auth_using_recovery_code.style.display = 'block'; everest_backup_auth_using_otp.style.display = 'none';"
				>Click to verify using recovery code</button>
			</div>
			<div id="everest_backup_auth_using_recovery_code" style="display:none">
				Please enter your Recovery Code:
				<input type="text" name="everest_backup_auth_recovery_code" id="everest_backup_auth_recovery_code">
				<button
					type="button"
					class="a-btn"
					onclick="everest_backup_auth_using_otp.style.display = 'block'; everest_backup_auth_using_recovery_code.style.display = 'none';"
				>Click to verify using otp</button>
			</div>
			<button type="submit">Submit</button>
		</form>
		<?php
		return;
	}
}

?>
<form method="post">

	<?php if ( isset( $everest_backup_package_locations['server'] ) && count( $everest_backup_package_locations ) === 1 ) { ?>
		<a href="<?php echo esc_url( network_admin_url( '/admin.php?page=everest-backup-addons&cat=Cloud' ) ); ?>"><?php esc_html_e( 'Install our addons to store your backup files on the cloud.', 'everest-backup' ); ?></a>
	<?php } else { ?>
		<p class="description"><?php esc_html_e( 'Configuration for your cloud storage.', 'everest-backup' ); ?></p>
	<?php } ?>

	<table class="form-table" id="cloud">
		<tbody>
			<?php
			if ( is_array( $everest_backup_package_locations ) && ! empty( $everest_backup_package_locations ) ) {
				foreach ( $everest_backup_package_locations as $everest_backup_package_location_key => $everest_backup_package_location ) {
					echo wp_kses_post( "<!-- Start [Key:{$everest_backup_package_location_key}]: {$everest_backup_package_location['label']} -->" );

					do_action(
						'everest_backup_settings_cloud_content',
						$everest_backup_package_location_key,
						$everest_backup_settings
					);

					echo wp_kses_post( "<!-- End [Key:{$everest_backup_package_location_key}]: {$everest_backup_package_location['label']} -->" );
				}
			}
			?>
		</tbody>
	</table>

	<?php
	everest_backup_nonce_field( EVEREST_BACKUP_SETTINGS_KEY . '_nonce' );
	submit_button( __( 'Save Settings', 'everest-backup' ) );
	?>
</form>
