<?php
/**
 * Template file for the addons.php.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_addon_cat   = ! empty( $args['request']['cat'] ) ? $args['request']['cat'] : '';
$everest_backup_addons      = ! empty( $args['addons'] ) ? $args['addons'] : '';
$everest_backup_addons_cats = ! empty( $everest_backup_addons['categories'] ) ? $everest_backup_addons['categories'] : '';
$everest_backup_addons_data = ! empty( $everest_backup_addons['data'] ) ? $everest_backup_addons['data'] : '';

?>
<div class="theme-browser">

	<div class="addons-categories">

		<form method="get">
			<input type="hidden" name="page" value="everest-backup-addons">

			<select name="cat">
				<option value=""><?php esc_html_e( '--- All ----', 'everest-backup' ); ?></option>
				<?php
				if ( is_array( $everest_backup_addons_cats ) && ! empty( $everest_backup_addons_cats ) ) {
					foreach ( $everest_backup_addons_cats as $everest_backup_addons_category ) {
						?>
						<option <?php selected( $everest_backup_addon_cat, $everest_backup_addons_category ); ?> value="<?php echo esc_attr( $everest_backup_addons_category ); ?>">
							<?php echo esc_html( $everest_backup_addons_category ); ?>
						</option>
						<?php
					}
				}
				?>
			</select>
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Filter', 'everest-backup' ); ?>">
		</form>
	</div>

	<div class="themes wp-clearfix">

		<?php
		if ( is_array( $everest_backup_addons_data ) && ! empty( $everest_backup_addons_data ) ) {
			foreach ( $everest_backup_addons_data as $everest_backup_addons_cat => $everest_backup_addon_data ) {

				$addon_slugs = array_keys( $everest_backup_addon_data );
				?>

				<div id="<?php echo esc_attr( 'addon-cat-' . sanitize_title( $everest_backup_addons_cat ) ); ?>" class="<?php echo esc_attr( 'postbox wp-clearfix' ); ?>">

					<div class="postbox-header">
						<h2><?php echo esc_html( $everest_backup_addons_cat ); ?></h2>
					</div>

					<?php
					if ( is_array( $addon_slugs ) && ! empty( $addon_slugs ) ) {
						foreach ( $addon_slugs as $addon_slug ) {
							$addon_data = everest_backup_addon_info( $everest_backup_addons_cat, $addon_slug );

							if ( ! $addon_data ) {
								continue;
							}

							$is_active  = ! empty( $addon_data['active'] );
							$is_premium = ! $is_active && ! empty( $addon_data['is_premium'] );

							$wrapper_class   = array();
							$wrapper_class[] = 'theme';
							$wrapper_class[] = "addon-slug-{$addon_slug}";
							$wrapper_class[] = $is_active ? 'active-addon' : '';
							$wrapper_class[] = $is_premium ? 'premium-addon' : '';
							?>
							<div <?php echo $is_active ? 'title="' . esc_attr__( 'Active', 'everest-backup' ) . '"' : ''; ?> class="<?php echo esc_attr( implode( ' ', $wrapper_class ) ); ?>">

								<?php
								if ( $is_active ) {
									?>
									<div class="active-ribbon"> <?php esc_html_e( 'Active', 'everest-backup' ); ?></div>
									<?php
								}

								if ( $is_premium ) {
									?>
									<a href="<?php echo esc_url( $addon_data['addon_url'] ); ?>" target="_blank" rel="noopener noreferrer">
									<div class="ribbon"><span><?php esc_html_e( 'PREMIUM', 'everest-backup' ); ?></span></div>
									<?php
								}
								?>

								<div class="theme-screenshot">
									<img src="<?php echo esc_url( $addon_data['thumbnail'] ); ?>">
								</div>

								<div class="theme-id-container">
									<h1 class="theme-name"><?php echo esc_html( $addon_data['name'] ); ?></h2>

									<div class="theme-actions">
										<?php
										if ( ! $is_active ) {
											?>
												<?php
												if ( $addon_data['installed'] ) {
													?>

													<form method="post">
														<input type="hidden" name="page" value="everest-backup-addons">
														<input type="hidden" name="plugin" value="<?php echo esc_attr( $addon_data['plugin'] ); ?>">
														<button type="submit" class="button button-primary"><span class="dashicons dashicons-plugins-checked"></span> <?php esc_html_e( 'Activate', 'everest-backup' ); ?></button>
													</form>
													<?php
												} elseif ( ! $is_premium ) {
													?>
														<form class="addon-installer-form" method="post">
															<button class="hidden button button-addon-installing" type="button"><?php esc_html_e( 'Installing...', 'everest-backup' ); ?></button>

															<fieldset>
																<a class="button" href="<?php echo esc_url( $addon_data['addon_url'] ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'View Details', 'everest-backup' ); ?></a>
																<input type="hidden" name="page" value="everest-backup-addons">
																<input type="hidden" name="addon_category" value="<?php echo esc_attr( $everest_backup_addons_cat ); ?>">
																<input type="hidden" name="addon_slug" value="<?php echo esc_attr( $addon_slug ); ?>">
																<input type="submit" name="install_addon" class="button button-primary" value="<?php esc_attr_e( 'Install & Activate', 'everest-backup' ); ?>">
															</fieldset>
														</form>
													<?php } else { ?>
														<a class="button button-primary button-buy-now" href="<?php echo esc_url( $addon_data['addon_url'] ); ?>" rel="noopener noreferrer" target="_blank"><?php esc_html_e( 'Buy Now', 'everest-backup' ); ?></a>
														<?php

													}
													?>
											<?php
										} else {
											$addon_actions = ! empty( $addon_data['actions'] ) ? $addon_data['actions'] : array();

											if ( is_array( $addon_actions ) && ! empty( $addon_actions ) ) {
												foreach ( $addon_actions as $addon_action ) {
													echo wp_kses_post( $addon_action );
												}
											}
										}
										?>
									</div>
					</div>

										<?php
										if ( $is_premium ) {
											?>
											</a>
											<?php
										}
										?>
							</div>
							<?php
						}
					}
					?>
				</div>

				<!-- <div class="section-divider"></div> -->
				<?php
			}
		}
		?>

	</div>

</div>
