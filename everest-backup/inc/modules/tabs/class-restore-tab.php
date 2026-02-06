<?php
/**
 * Class for creating the tabs in settings page.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Tabs_Factory;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for creating the tabs in settings page.
 *
 * @since 1.0.0
 */
class Restore_Tab extends Tabs_Factory {

	/**
	 * Arguments for the restore callbacks.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $restore_args = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$max_upload_size = everest_backup_max_upload_size();

		$this->restore_args = array(
			'settings'        => everest_backup_get_settings(),
			'request'         => everest_backup_get_submitted_data(),
			'max_upload_size' => $max_upload_size ? everest_backup_format_size( $max_upload_size ) : __( 'Unlimited', 'everest-backup' ),
		);

		parent::__construct();
	}

	/**
	 * Set tab items array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected function set_items() {
		return array(
			'upload_file'     => array(
				'label'    => __( 'Upload File', 'everest-backup' ),
				'callback' => array( $this, 'upload_file_cb' ),
				'priority' => 10,
			),
			'available_files' => array(
				'label'    => __( 'Available Files', 'everest-backup' ),
				'callback' => array( $this, 'available_files_cb' ),
				'priority' => 20,
			),
		);
	}

	/**
	 * Callback for upload tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function upload_file_cb() {
		everest_backup_render_view( 'restore/upload-file', $this->restore_args );
	}

	/**
	 * Callback for available files tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function available_files_cb() {
		$history_table_obj = new History_Table();
		$history_table_obj->prepare_items();
		?>

		<form id="everest-backup-container" method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( $this->restore_args['request']['page'] ); ?>">
			<input type="hidden" name="tab" value="available_files">
			<?php $history_table_obj->display(); ?>
		</form>
		<?php
	}
}

