<?php
/**
 * Hook template function related admin page.
 * Most of the functions here are called dynamically.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Template_Functions;

use Everest_Backup\Modules\History_Table;
use Everest_Backup\Modules\Logs_Table;
use Everest_Backup\Proc_Lock;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template function for the export admin menu.
 *
 * @return void
 * @since 1.0.0
 */
function export_page_template_cb() {
	$args = array();

	$args['proc_lock'] = Proc_Lock::get();

	everest_backup_render_view( 'backup', $args );
}

/**
 * Template function for the inport admin menu.
 *
 * @return void
 * @since 1.0.0
 */
function import_page_template_cb() {
	$args = array();
	$get  = everest_backup_get_submitted_data( 'get' );

	if ( everest_backup_doing_rollback() ) {
		$filename = ! empty( $get['file'] ) ? sanitize_file_name( $get['file'] ) : '';
		$package  = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/' . $filename );
		$args     = everest_backup_get_backup_file_info( $package );
	}

	$args = array_merge( $get, $args );

	$args['proc_lock'] = Proc_Lock::get();

	everest_backup_render_view( 'restore', $args );
}

/**
 * Template function for the migration clone page.
 *
 * @return void
 * @since 1.0.0
 */
function migration_clone_page_template_cb() {
	$args = array();

	$args['proc_lock'] = Proc_Lock::get();

	everest_backup_render_view( 'migration-clone', $args );
}

/**
 * Template function for the inport admin menu.
 *
 * @return void
 * @since 1.0.0
 */
function history_page_template_cb() {
	$request = everest_backup_get_submitted_data();

	$history_table_obj = new History_Table();

	$args = array(
		'page'              => isset( $request['page'] ) ? $request['page'] : '',
		'history_table_obj' => $history_table_obj,
	);

	$args['proc_lock'] = Proc_Lock::get();

	everest_backup_render_view( 'history', $args );
}

/**
 * Template function for the inport admin menu.
 *
 * @return void
 * @since 1.0.0
 */
function logs_page_template_cb() {
	$request = everest_backup_get_submitted_data();

	$logs_table_obj = new Logs_Table();

	$args = array(
		'page'           => isset( $request['page'] ) ? $request['page'] : '',
		'logs_table_obj' => $logs_table_obj,
	);

	everest_backup_render_view( 'logs', $args );
}

/**
 * Template function for the settings page.
 *
 * @return void
 * @since 1.0.0
 */
function settings_page_template_cb() {
	everest_backup_render_view( 'settings' );
}

/**
 * Template function for the settings page.
 *
 * @return void
 * @since 1.0.0
 */
function addons_page_template_cb() {
	everest_backup_render_view( 'addons' );
}

/**
 * Template function for the changelogs page.
 *
 * @return void
 * @since 1.1.7
 */
function changelogs_page_template_cb() {
	everest_backup_render_view( 'changelogs' );
}

/**
 * Function for the upgradetopro page.
 *
 * @return void
 * @since 2.2.5
 */
function upgradetopro_page_template_cb() {
	$pro_url = 'https://wpeverestbackup.com/pricing/?utm_medium=wpd&utm_source=eb&utm_campaign=upgradetopro';
	if ( ! headers_sent() ) {
		wp_safe_redirect( $pro_url );
	}
	?>
	<script>
		window.location.href = "<?php echo esc_url( $pro_url ); ?>"
	</script>
	<p>If you are not redirected, <a href="<?php echo esc_url( $pro_url ); ?>">click here</a>.</p>
	<?php
	exit;
}
