<?php
/**
 * Template file for displaying the list of previous backups.
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

$everest_backup_history_table_obj = ! empty( $args['history_table_obj'] ) ? $args['history_table_obj'] : false;

if ( ! is_object( $everest_backup_history_table_obj ) ) {
	return;
}

$everest_backup_history_table_obj->prepare_items();

?>
<div class="wrap">
	<style>
	</style>
	<hr class="wp-header-end">

	<?php
		everest_backup_render_view( 'template-parts/header' );
	?>
	<div class="everest-backup-loader-overlay" style="z-index: 99988;">
		<div class="everest-backup-loader"></div>
	</div>

	<main class="everest-backup-wrapper">
		<?php
		if ( everest_backup_2fa_active() && isset( $_GET['cloud'] ) && ( 'server' !== $_GET['cloud'] ) ) {
			if ( ! empty( $_POST['everest_backup_auth_totp'] ) ) {
				$otp = (int) $_POST['everest_backup_auth_totp'];
				$response = everest_backup_2fa_check_otp( $otp );
				if ( isset( $response['success'] ) && $response['success'] ) {
					set_transient( 'everest_backup_2fa_checked', true, 600 );
				} elseif( isset( $response['success'] ) && ! empty( $response['message'] ) ) {
					echo $response['message'];
				}
			}
			if ( ! empty( $_POST['everest_backup_auth_recovery_code'] ) ) {
				$recovery_code = $_POST['everest_backup_auth_recovery_code'];
				$response = everest_backup_2fa_check_recovery_code( $recovery_code );
				if ( isset( $response['success'] ) && $response['success'] ) {
					set_transient( 'everest_backup_2fa_checked', true, 600 );
				} elseif( isset( $response['success'] ) && ! empty( $response['message'] ) ) {
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
		<form id="everest-backup-container" method="get">
			<?php
			if ( empty( $args['proc_lock'] ) ) {
				?>
				<input type="hidden" name="page" value="<?php echo esc_attr( $args['page'] ); ?>">
				<?php
				$everest_backup_history_table_obj->display();
			} else {
				everest_backup_render_view( 'template-parts/proc-lock-info', $args['proc_lock'] );
			}
			?>
		</form>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>

		<!-- custom modal -->
		<div id="everestBackupCustomModal" class="everest-backup-modal">
			<div class="everest-backup-modal-content">
				<div class="w-75 float-left">
					<h2 id="everestBackupHeaderText">Choose a cloud platform</h2>
				</div>
				<div class="w-25 float-right">
					<span class="everest-backup-modal-close everest-backup-close-modal">&times;</span>
				</div>
				<br>
				<p id="everest-backup-active-plugins" style="width: 100%;"></p>
				<h2 id="everestBackupFooterText"></h2>
				<button class="everest-backup-close-modal float-right">Close</button>
			</div>
		</div>
		<!-- custom modal -->

		<!-- list file modal -->
		<div id="everestBackupListFilesModal" class="everest-backup-modal">
			<div class="everest-backup-modal-content">
				<header>File List from Backup: <span id="everestBackupBackupName"></span></header>
				<hr>
				<div id="everestBackupFileList" class="everest-backup-file-list"></div>
				<button class="everest-backup-close-modal float-right">Close</button>
			</div>
		</div>
		<!-- list file modal -->

	</main>
</div>
