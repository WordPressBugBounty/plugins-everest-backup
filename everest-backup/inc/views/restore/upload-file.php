<?php
/**
 * Restore upload tab HTML contents.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugins = get_plugins();
$message = '';
$link    = '';

if ( ! in_array( 'everest-backup-pro/everest-backup-pro.php', array_keys( $plugins ) ) ) {
	$message = 'To bypass your server upload limit, install the Lightupload addon or upgrade to Everest Backup Pro.';
	$link    = '<a href="' . admin_url( 'admin.php?page=everest-backup-addons&cat=Upload+Limit' ) . '">Install Lightupload Addon</a> or <a href="https://wpeverestbackup.com/pricing">Get Everest Backup Pro</a>';

} elseif ( ! is_plugin_active( 'everest-backup-pro/everest-backup-pro.php' ) ) {
	$message = 'Everest Backup Pro is installed but not active. Please activate the plugin and enter your valid license key to bypass your server upload limit.';
	$link    = '<a href="' . admin_url( 'plugins.php' ) . '">Activate Plugin</a>';

} else {
	$message = 'Your Everest Backup Pro license is not active. Please activate your license to bypass your server upload limit.';
	$link    = '<a href="' . admin_url( 'admin.php?page=everest-backup-license' ) . '">Activate License</a>';
}


?>

<div class="restore-container">

	<div id="plupload-upload-ui" class="hide-if-no-js">
		<div id="drag-drop-area">
			<div class="drag-drop-inside" style="text-align:center;">
				<svg xmlns="http://www.w3.org/2000/svg" width="75.453" height="52.817" viewBox="0 0 75.453 52.817">
					<path id="Icon_awesome-cloud-upload-alt" data-name="Icon awesome-cloud-upload-alt" d="M63.381,25.192A11.331,11.331,0,0,0,52.817,9.8a11.26,11.26,0,0,0-6.284,1.91,18.865,18.865,0,0,0-35.215,9.408c0,.318.012.637.024.955a16.981,16.981,0,0,0,5.635,33H60.362a15.09,15.09,0,0,0,3.018-29.875Zm-17,7.239H38.67v13.2a1.892,1.892,0,0,1-1.886,1.886H31.124a1.892,1.892,0,0,1-1.886-1.886v-13.2h-7.71A1.883,1.883,0,0,1,20.2,29.213L32.622,16.786a1.893,1.893,0,0,1,2.664,0L47.712,29.213a1.885,1.885,0,0,1-1.332,3.219Z" transform="translate(0 -2.25)" />
				</svg>

				<p class="drag-drop-info"><?php esc_html_e( 'Drop file here', 'everest-backup' ); ?></p>
				<p><?php echo esc_html_x( 'or', 'Uploader: Drop files here - or - Select Files', 'everest-backup' ); ?></p>
				<p class="drag-drop-buttons"><input id="plupload-browse-button" type="button" value="<?php esc_attr_e( 'Select File', 'everest-backup' ); ?>" class="button button-primary button-hero" /></p>
			</div>
		</div>
	</div>

	<div class="direct-restore-checkbox-wrapper" style="margin-top:15px;">
		<label>
			<input type="checkbox" id="direct_restore_checkbox">
			<strong class="font-size: larger;"><?php esc_html_e( 'Direct restore', 'everest-backup' ); ?></strong>
			<?php everest_backup_tooltip( __( 'If checked, Everest Backup will initiate the restore process immediately following the uploading of the archive file, without requiring any further confirmation.', 'everest-backup' ) ); ?>
		</label>
	</div>

	<h2><?php echo esc_html__( 'Maximum upload size:', 'everest-backup' ) . ' ' . esc_html( $args['max_upload_size'] ); ?></h2>

	<?php
	if ( ! defined( 'EVEREST_BACKUP_UNLIMITED_FILE' ) && ! everest_backup_pro_active() ) {
		?>
		<h4 style="color: green;"><?php esc_html_e( $message, 'everest-backup' ); ?>
		<?php echo $link; ?>
		</h4>
		<?php
	}
	?>
</div>
