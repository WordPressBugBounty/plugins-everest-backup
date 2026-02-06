<?php
/**
 * Template part file for the user consent dialog box.
 *
 * @package everest-backup
 * @since 2.1.3
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( everest_backup_is_test_site() ) {
	return;
}

if ( ( ! everest_backup_is_localhost() ) && ( 'yes' !== get_option( 'everest_backup_consent_optin' ) ) && ( 'yes' !== get_transient( 'everest_backup_consent_skip' ) ) ) {
	?>

	<dialog id="everest-backup-consent-dialog">
		<div class="consent-header">
			<h2><?php esc_html_e( 'Welcome to Everest Backup!', 'everest-backup' ); ?> <br> <span><?php esc_html_e( 'Count me in', 'everest-backup' ); ?></span> <?php esc_html_e( 'for important updates.', 'everest-backup' ); ?> </h2>
		</div>

		<div class="consent-body">
			<p><?php esc_html_e( 'Stay informed about important', 'everest-backup' ); ?> <span> <?php esc_html_e( 'security updates, new features', 'everest-backup' ); ?></span> <?php esc_html_e( 'exclusive deals, and allow non sensitive diagnostic tracking.', 'everest-backup' ); ?> </p>

			<form method="post">
				<button class="button button-primary" type="submit"><?php esc_html_e( 'Allow and Continue', 'everest-backup' ); ?></button>
				<?php wp_nonce_field( 'everest_backup_consent_optin', 'everest_backup_consent_optin' ); ?>
			</form>
			
			<details>
				<summary><?php esc_html_e( 'Learn more', 'everest-backup' ); ?></summary>
				<div class="details-wrap">
				<h4><?php esc_html_e( 'You are granting these permissions.', 'everest-backup' ); ?></h4>
					<ul>
						<li><?php esc_html_e( 'Your Profile Information', 'everest-backup' ); ?></li>
						<li><?php esc_html_e( 'Your site Information ( URL, WP Version, PHP info, plugins & Themes )', 'everest-backup' ); ?></li>
						<li><?php esc_html_e( 'Plugin notices ( updates, announcements, marketing, no spam )', 'everest-backup' ); ?></li>
						<li><?php esc_html_e( 'Plugin events ( activation, deactivation, and uninstall )', 'everest-backup' ); ?></li>
					</ul>
				</div>

				<form method="post">
					<button class="button button-link" type="submit"><?php esc_html_e( 'Skip Now', 'everest-backup' ); ?></button>
					<?php wp_nonce_field( 'everest_backup_consent_skip', 'everest_backup_consent_skip' ); ?>
				</form>
			</details>
		</div>
	</dialog>

	<?php
}

