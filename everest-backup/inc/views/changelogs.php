<?php
/**
 * Template file for changelog submenu page.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$changelogs = everest_backup_parsed_changelogs();

?>

<div class="wrap">
	<hr class="wp-header-end">

	<?php everest_backup_render_view( 'template-parts/header' ); ?>

	<main class="everest-backup-wrapper">
		<div id="everest-backup-container">

			<div class="theme-browser changelog-wrap">
				<?php
				if ( is_array( $changelogs ) && ! empty( $changelogs ) ) {
					foreach ( $changelogs as $version => $changelog ) {

						?>
						<div class="change-log-card">

							<h1><?php echo esc_html( $version ); ?></h1>

							<span style="font-style:italic;">
							<?php
							if ( ! empty( $changelog['release_date'] ) ) {
								printf( __( 'Released Date: %s', 'everest-backup' ), esc_html( wp_date( get_option( 'date_format' ), strtotime( $changelog['release_date'] ) ) ) );
							}
							?>
							</span>

							<?php
							$changes = $changelog['changes'];
							if ( is_array( $changes ) && ! empty( $changes ) ) {
								foreach ( $changes as $log ) {
									$logs_explode = array_map( 'trim', explode( ':', $log, 2 ) );

									if ( isset( $logs_explode[1] ) ) {
										echo wpautop( sprintf( '<strong>%1$s:</strong> %2$s', esc_html( $logs_explode[0] ), esc_html( $logs_explode[1] ) ) );
									} else {
										echo wpautop( esc_html( $log ) );
									}
								}
							}
							?>

							<div class="plugin-actions">
								<a
									href="<?php echo esc_url( sprintf( 'https://downloads.wordpress.org/plugin/everest-backup.%s.zip', str_replace( 'v', '', $version ) ) ); ?>"
									class="plugin-download button download-button button-large"><?php printf( esc_html__( 'Download %s', 'everest-backup' ), esc_html( $version ) ); ?></a>
							</div>

						</div>
						<?php

					}
				}
				?>
			</div>
		</div>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>
	</main>

</div>
