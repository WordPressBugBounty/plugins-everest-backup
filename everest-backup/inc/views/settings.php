<?php
/**
 * Template file for the settings page.
 *
 * @package everest-backup
 */

use Everest_Backup\Modules\Settings_Tab;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$everest_backup_setings_tab = new Settings_Tab();

?>
<div class="wrap">
	<hr class="wp-header-end">

	<?php
		everest_backup_render_view( 'template-parts/header' );
	?>
	<main class="everest-backup-wrapper">
		<div id="everest-backup-container">
			<?php $everest_backup_setings_tab->display(); ?>
		</div>

		<?php everest_backup_render_view( 'template-parts/sidebar' ); ?>
	</main>

</div>
