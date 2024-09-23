<?php
/**
 * Sidebar content cards.
 *
 * @package everest-backup
 */

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="everest-backup_card">
	<h3 class="everest-backup_card_title"><?php echo wp_kses_post( $args['title'] ); ?></h3>
	<?php if ( ! empty( $args['description'] ) ) { ?>
		<p class="everest-backup_card_content"><?php echo wp_kses_post( $args['description'] ); ?></p>
	<?php } ?>
	<?php if ( ! empty( $args['youtube_id'] ) && ! everest_backup_is_localhost() ) { ?>
		<iframe
			class="youtube-iframe"
			data-id="<?php echo esc_attr( $args['youtube_id'] ); ?>"
			frameborder="0"
			width="100%"
			allowfullscreen
			loading="lazy"></iframe>
	<?php } ?>
</div>
