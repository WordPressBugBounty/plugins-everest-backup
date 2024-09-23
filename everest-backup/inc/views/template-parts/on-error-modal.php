<?php

$issue_reporter_link = everest_backup_get_issue_reporter_url();
$issue_reporter_data = everest_backup_get_issue_reporter_data();
?>
<div>
	<?php
	if ( $issue_reporter_link ) {
		?>
		<p><?php esc_html_e( 'Please click on the button below to enable us to investigate.', 'everest-backup' ); ?></p>

		<form action="<?php echo esc_url( $issue_reporter_link ); ?>" method="POST" target="_blank">
			<?php
			if ( is_array( $issue_reporter_data ) && ! empty( $issue_reporter_data ) ) {
				foreach ( $issue_reporter_data as $key => $value ) {
					?>
					<input type="hidden" name="ebwp_issue_reporter[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
					<?php
				}
			}
			?>
			<button type="submit" class="button button-primary share-debut"><?php esc_html_e( 'Share debug infos with Everest Backup Team', 'everest-backup' ); ?></button>
		</form>

		<p><?php esc_html_e( "You'll share: Website URL, backup logs and your Email address to contact you back. No confidential data such as get shared.", 'everest-backup' ); ?></p>
		<?php
	}
	?>
	<a class="activity-log-btn" href="<?php echo esc_url( everest_backup_get_activity_log_url() ); ?>" target="_blank"><?php esc_html_e( 'Click here to View Activity Log' ); ?></a>

</div>
