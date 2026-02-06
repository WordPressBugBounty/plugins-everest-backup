<?php
/**
 * Template file for displaying conditional upsells.
 *
 * @since 2.0.0
 */

use Everest_Backup\Transient;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$transient = new Transient( 'upsell_dimiss' );

if ( $transient->get() ) {
	return;
}

$upsells = everest_backup_fetch_upsell();

if ( empty( $upsells ) ) {
	return;
}

?>

<style>
	.ebwp-upsell-container {
		border: 0;
	}

	.ebwp-upsell {
		margin: 10px 0 10px;
	}
</style>

<div class="ebwp-upsell-container notice">

	<div class="ebwp-upsell">
		<?php echo wp_kses_post( $upsells[ array_rand( $upsells ) ] ); ?>
	</div>

	<a href="
	<?php
	echo esc_url(
		add_query_arg(
			array(
				'ebwp-upsell-dimiss'        => 1,
				'_ebwp-upsell-dimiss-nonce' => everest_backup_create_nonce( '_ebwp-upsell-dimiss-nonce' ),
			)
		)
	);
	?>
	"><?php esc_html_e( 'Remind Later', 'everest-backup' ); ?></a>

</div>
