<?php
/**
 * Abstract class for handling cloud storage functionality.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class for handling cloud storage functionality.
 *
 * @abstract
 * @since 1.0.0
 * @since 1.1.0 Other methods related to cloud, added to organize hooks and functionality in a proper way.
 */
#[\AllowDynamicProperties]
class Cloud {

	/**
	 * Cloud key. Ex: google_drive.
	 *
	 * @var string
	 * @since 1.1.0
	 * @since 1.1.2 This property has become optional. See `$this->setup_cloud()` method.
	 */
	protected $cloud;

	/**
	 * Cloud folder contents transient key.
	 *
	 * @var string
	 */
	protected $transient_key;

	/**
	 * Current cloud parameters that will be merged to package locations array.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $cloud_param = array();

	/**
	 * Settings > cloud fields names attributes prefix.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $fields_key = 'everest_backup_settings[cloud]';

	/**
	 * Arguments for rollback.
	 *
	 * @var array
	 */
	private $rollback_args = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_cloud();
		$this->set_settings_key();
		$this->init_view_hooks();
		$this->init_logic_hooks();
	}

	/**
	 * Setup and validate required properties and keys for current cloud.
	 *
	 * @return void
	 * @since 1.1.2
	 */
	protected function setup_cloud() {

		$cloud_param = $this->set_cloud_param();

		if ( is_array( $cloud_param ) && ( count( $cloud_param ) === 1 ) ) {
			$cloud_key = key( $cloud_param ); // Automatically extract cloud key from cloud parameters.

			if ( ! is_string( $cloud_key ) ) {
				// Cloud key needs to be string. For eg: "google_drive".
				return;
			}

			$this->cloud         = $cloud_key;
			$this->transient_key = "{$cloud_key}_folder_contents";
			$this->cloud_param   = $cloud_param;
		}
	}

	/**
	 * Set key in settings array according to the current cloud key.
	 *
	 * @return void
	 * @since 1.1.2
	 */
	protected function set_settings_key() {

		$cloud_key = $this->cloud;

		if ( ! $cloud_key ) {
			return;
		}

		$settings = everest_backup_get_settings();

		if ( isset( $settings['cloud'][ $cloud_key ] ) ) {
			return;
		}

		$settings['cloud'][ $cloud_key ] = array();

		everest_backup_update_settings( $settings );
	}

	/**
	 * Returns settings for current cloud.
	 *
	 * @return array
	 * @since 1.1.5
	 */
	public function get_current_cloud_settings() {

		$cloud_key = $this->cloud;

		if ( ! $cloud_key ) {
			return array();
		}

		$settings = everest_backup_get_settings();

		return isset( $settings['cloud'][ $cloud_key ] ) ? $settings['cloud'][ $cloud_key ] : array();
	}

	/**
	 * Prints fields for the current cloud backup location.
	 *
	 * @return void
	 * @since 2.2.0
	 */
	public function print_current_cloud_backup_location_fields() {

		$cloud_key = $this->cloud;

		if ( ! $cloud_key ) {
			return;
		}

		$settings = $this->get_current_cloud_settings();

		$cloud_root_folder = untrailingslashit( pathinfo( EVEREST_BACKUP_BACKUP_DIR_PATH, PATHINFO_BASENAME ) );

		$backup_location = ! empty( $settings['backup_location'] ) ? $settings['backup_location'] : '';

		$cloud_path = untrailingslashit( "$this->cloud://$cloud_root_folder/$backup_location" );

		?>
		<div class="ebwp-cloud-backup-location" data-cloud="<?php echo esc_attr( $this->cloud ); ?>">
			<strong><?php esc_html_e( 'Backup Location' ); ?>:</strong>
			<code><?php echo esc_html( $cloud_path ); ?></code>
			<input type="hidden" name="<?php echo esc_attr( $this->get_name( '[' . $this->cloud . '][backup_location]' ) ); ?>" value="<?php echo esc_attr( $backup_location ); ?>">
			<a href="javascript:void(0);" class="ebwp-cloud-backup-location-btn" data-cloud="<?php echo esc_attr( $this->cloud ); ?>"><?php esc_html_e( 'Set Backup Location', 'everest-backup' ); ?></a>

			<?php everest_backup_tooltip( __( "Backup locations are custom folders that are created in your cloud accounts. These folders serve as designated spaces for uploading and listing your backup files. If you haven't set a specific backup location, the system will default to using the Everest Backup folder.", 'everest-backup' ) ); ?>

			<dialog>

				<h4><?php printf( esc_html__( '%s Backup Location', 'everest-backup' ), esc_html( $this->cloud_param[ $this->cloud ]['label'] ) ); //phpcs:ignore ?></h4>
				<code><?php echo esc_html( "$this->cloud://$cloud_root_folder/" ); ?></code>
				<input
					type="text"
					name="<?php echo esc_attr( $this->get_name( '[' . $this->cloud . '][backup_location]' ) ); ?>"
					value="<?php echo esc_attr( $backup_location ); ?>"
				>

				<div class="dialog-buttons" style="margin-top: 20px;display: flex;gap: 20px;flex-direction: row-reverse;">
					<button type="button" class="btn-cancel button button-danger"><?php esc_html_e( 'Cancel', 'everest-backup' ); ?></button>
					<button type="submit" class="btn-save button button-primary"><?php esc_html_e( 'Save', 'everest-backup' ); ?></button>
				</div>

			</dialog>
		</div>
		<?php
	}

	/**
	 * Initialize hooks related views.
	 *
	 * @since 1.1.0
	 * @since 1.1.2
	 * @return void
	 */
	protected function init_view_hooks() {
		add_filter( 'everest_backup_filter_package_locations', array( $this, 'merge_package_locations' ) );
		add_action( 'everest_backup_settings_cloud_content', array( $this, 'render' ), 12, 2 );
	}

	/**
	 * Initialize hooks related to process logic.
	 *
	 * @since 1.1.0
	 * @since 1.1.2
	 * @return void
	 */
	protected function init_logic_hooks() {

		$this->reset_cache( isset( $_POST['everest_backup_settings']['cloud'] ) ); //phpcs:ignore

		add_action( 'everest_backup_after_zip_done', array( $this, 'after_zip_done' ), 12, 2 );

		add_filter( 'everest_backup_history_table_data', array( $this, 'history_table_data' ), 12, 2 );
		add_action( 'everest_backup_history_after_filters', array( $this, 'after_history_filters' ) );
		add_action( 'everest_backup_override_file_remove', array( $this, 'remove' ) );

		add_filter( 'everest_backup_filter_view_renderer_args', array( $this, 'override_view_renderer_args' ), 20, 2 );
		if ( array_key_exists( 'cloud', $_POST ) && 'pcloud' !== $_POST['cloud'] ) { // @phpcs:ignore
			add_action( 'everest_backup_before_restore_init', array( $this, 'before_restore_init' ) );
		}
		add_filter( 'everest_backup_filter_rollback_args', array( $this, 'override_rollback_args' ) );

		add_action( 'wp_scheduled_delete', array( $this, 'cloud_auto_remove' ) ); // Triggers once daily.
	}

	/**
	 * Merge cloud parameters to package locations.
	 *
	 * @param array $package_locations Backup package locations.
	 * @return array Backup package locations.
	 * @since 1.0.0
	 * @since 1.1.2
	 */
	public function merge_package_locations( $package_locations ) {
		if ( ! $this->cloud_param ) {
			return $package_locations;
		}

		return array_merge( $package_locations, $this->cloud_param );
	}

	/**
	 * Set current cloud parameters that will be merged to package locations array.
	 *
	 * @abstract
	 * @return void|array
	 */
	protected function set_cloud_param() {
		_doing_it_wrong( __METHOD__, esc_html__( 'This method is supposed to be overridden by subclasses.', 'everest-backup' ), '' );
	}

	/**
	 * Returns current cloud parameters which was set using set_cloud_param from the child class.
	 *
	 * @return array
	 * @since 2.2.0
	 */
	public function get_cloud_param() {
		return isset( $this->cloud_param[ $this->cloud ] ) ? $this->cloud_param[ $this->cloud ] : array();
	}

	/**
	 * Returns fields name for the settings > cloud fields name attributes.
	 * Accepts values as:
	 * * $key = 'your_key';
	 * * $key = '[your_key][sub_key]';
	 *
	 * @param string $key Fields name attribute value.
	 * @return string Processed fields name attribute value for cloulds fields.
	 * @since 1.0.0
	 */
	protected function get_name( $key ) {
		return str_replace( array( '[[', ']]' ), array( '[', ']' ), "{$this->fields_key}[$key]" );
	}

	/**
	 * Render HTML in Settings > Cloud content.
	 *
	 * @param string $cloud_key Array key of cloud ( or package location ) paramaters passed to `everest_backup_filter_package_locations` filter hook.
	 * @param array  $settings Settings data.
	 * @abstract
	 * @return void
	 * @since 1.0.0
	 */
	public function render( $cloud_key, $settings ) { // @phpcs:ignore
		_doing_it_wrong( __METHOD__, esc_html__( 'This method is supposed to be overridden by subclasses.', 'everest-backup' ), '' );
	}

	/**
	 * Trigger zip upload to cloud. Must return boolean.
	 *
	 * @param string $zip Backup package full path.
	 * @return bool
	 * @since 1.1.1
	 * @abstract
	 */
	protected function upload( $zip ) { //phpcs:ignore
		return false;
	}

	/**
	 * Method that runs after backup package is created.
	 *
	 * @param string $zip Backup package full path.
	 * @param string $migration_url Migration URL.
	 * @since 1.1.0
	 * @since 1.1.1 Use `Everest_Backup\Cloud::upload` to upload the backup file using cloud. Other log related process are handled automatically.
	 * @return void
	 */
	public function after_zip_done( $zip, $migration_url ) {

		if ( ! $zip ) {
			return;
		}

		if ( everest_backup_is_saving_to() !== $this->cloud ) {
			return;
		}

		$cloud_label = $this->cloud_param[ $this->cloud ]['label'];

		if (
			(
				'pcloud' === everest_backup_is_saving_to()
				&& defined( 'EVEREST_BACKUP_PCLOUD_VERSION' )
				&& ( everest_backup_compare_version( EVEREST_BACKUP_PCLOUD_VERSION, '1.0.8' ) > 0 )
			)
			||
			(
				'google_drive' === everest_backup_is_saving_to()
				&& ( everest_backup_compare_version( EVEREST_BACKUP_GOOGLE_DRIVE_VERSION, '1.0.9' ) > 0 )
			)
		) {
			everest_backup_cloud_update_option( 'manual_backup_continued', true ); // for not showing uploaded to cloud message after backup.
			$status = 'in-process';
			/* translators: %s is the cloud label name. */
			$message = sprintf( __( "We're uploading your site's backup to %s.", 'everest-backup' ), esc_html( $cloud_label ) );
		} else {
			$status = 'cloud';
			/* translators: %s is the cloud label name. */
			$message = sprintf( __( "We're uploading your site's backup to %s in the background. You may close this popup.", 'everest-backup' ), esc_html( $cloud_label ) );
		}

		Logs::set_proc_stat(
			array(
				'status'  => $status,
				'message' => $message,
				'task'    => 'cloud',
				'data'    => array(
					'zipurl'        => everest_backup_convert_file_path_to_url( $zip ),
					'migration_url' => is_string( $migration_url ) ? $migration_url : '',
				),
			)
		);

		/* translators: %s is the cloud label name. */
		Logs::info( sprintf( __( 'Uploading zip to %s.', 'everest-backup' ), esc_html( $cloud_label ) ) );
		if ( $this->upload( $zip ) ) {
			/* translators: %s is the cloud label name. */
			Logs::info( sprintf( __( 'Zip uploaded to %s.', 'everest-backup' ), esc_html( $cloud_label ) ) );
		} else {
			/* translators: %s is the cloud label name. */
			Logs::info( sprintf( __( 'Failed to upload file to %s.', 'everest-backup' ), esc_html( $cloud_label ) ) );

			/**
			 * Avoid deletion from the server because cloud upload has failed.
			 *
			 * @since 1.1.5
			 */
			add_filter( 'everest_backup_avoid_delete_from_server', '__return_true' );
		}

		$transient = new Transient( $this->transient_key );
		$transient->delete();
	}


	/**
	 * Method to override history table item data.
	 *
	 * @param array  $table_data History table item data.
	 * @param string $selected_cloud Currently selected cloud.
	 * @return array
	 * @since 1.1.0
	 * @abstract
	 */
	public function history_table_data( $table_data, $selected_cloud ) { //phpcs:ignore
		return $table_data;
	}

	/**
	 * Resets transient cache.
	 *
	 * @param bool $force Force reset cache manually. @since 1.1.5.
	 * @return bool
	 */
	protected function reset_cache( $force = false ) {

		$transient = new Transient( $this->transient_key );

		if ( $force ) {
			return $transient->delete();
		}

		$get = everest_backup_get_submitted_data( 'get' );

		if ( ! empty( $get['action'] ) && 'reset-cache' === $get['action'] ) {
			if ( ! empty( $get['cloud'] ) && $this->cloud === $get['cloud'] ) {
				return $transient->delete();
			}
		}
	}

	/**
	 * By default this method prints Cache reset button if seleted cloud is not server.
	 *
	 * @param string $cloud Selected cloud.
	 * @return void
	 * @since 1.1.0
	 */
	public function after_history_filters( $cloud ) {

		if ( ! $this->cloud ) {
			return;
		}

		if ( $this->cloud !== $cloud ) {
			return;
		}

		$get = everest_backup_get_submitted_data( 'get' );
		$cache_reset_link = add_query_arg(
			array(
				'cloud'       => $this->cloud,
				'backup_type' => $get['backup_type'] ?? 'regular',
				'action'      => 'reset-cache',
			),
			network_admin_url( '/admin.php?page=everest-backup-history' )
		);

		$transient = new Transient( $this->transient_key );
		$timeout   = $transient->get_timeout();

		?>
		<a
			href="<?php echo esc_url( $cache_reset_link ); ?>"
			class="button"
			title="
			<?php
			/* translators: %s is human_time_diff result. */
			printf( esc_attr__( 'Cache resets in: %s', 'everest-backup' ), esc_attr( human_time_diff( $timeout ) ) );
			?>
			"
		>
			&#10227; <?php esc_html_e( 'Reset Cache Now', 'everest-backup' ); ?>
		</a>
		<?php
	}

	/**
	 * Method used for removing files from cloud when user clicks remove link or action.
	 *
	 * @param array $args Results from $_GET global variable.
	 * @return void
	 * @since 1.1.0
	 */
	public function remove( $args ) {}

	/**
	 * Method to override view render arguments.
	 *
	 * @param array  $args Arguments that will be passed to the template.
	 * @param string $template Template file name without extension.
	 * @return array
	 * @since 1.1.0
	 */
	final public function override_view_renderer_args( $args, $template ) {

		if ( 'restore' !== $template ) {
			return $args;
		}

		if (
		empty( $args['action'] ) ||
		empty( $args['cloud'] ) ||
		empty( $args['file'] )
		) {
			return $args;
		}

		if ( 'rollback' !== $args['action'] ) {
			return $args;
		}

		if ( $this->cloud !== $args['cloud'] ) {
			return $args;
		}

		$args = $this->rollback_renderer_args( $args );

		return $args;
	}

	/**
	 * Alias for Everest_Backup\Cloud::override_view_renderer_args.
	 *
	 * @param array $args Arguments that will be passed to the template.
	 * @return array
	 */
	protected function rollback_renderer_args( $args ) {
		return $args;
	}

	/**
	 * Method runs before restore/rollback starts
	 *
	 * @param array $args Ajax response.
	 * @return void
	 * @since 1.1.0
	 */
	public function before_restore_init( $args ) {}

	/**
	 * Sets arguments for rollback that will be used by `Everest_Backup\Cloud::override_rollback_args`.
	 *
	 * @param array $args Arguments.
	 * @return void
	 * @throws \Exception Throws exception if required argument is missing.
	 */
	protected function set_rollback_args( $args ) {
		$required_keys = array(
			'filename',
			'package',
		);

		if ( is_array( $required_keys ) && ! empty( $required_keys ) ) {
			foreach ( $required_keys as $required_key ) {
				if ( empty( $args[ $required_key ] ) ) {

					/* translators: %s is the name of rollback args required key. */
					throw new \Exception( esc_html( sprintf( __( '%s is a required argument.', 'everest-backup' ), $required_key ) ), 1 );
				}
			}
		}

		$this->rollback_args = $args;
	}

	/**
	 * Method to override rollback args.
	 *
	 * @param array $args Default arguments.
	 * @return array
	 * @since 1.1.0
	 */
	public function override_rollback_args( $args ) {

		if ( $this->cloud !== $args['cloud'] ) {
			return $args;
		}

		if ( empty( $args['filename'] ) ) {
			return $args;
		}

		if ( empty( $this->rollback_args['package'] ) ) {
			return $args;
		}

		if ( empty( $this->rollback_args['filename'] ) ) {
			return $args;
		}

		$args['package']  = $this->rollback_args['package'];
		$args['filename'] = $this->rollback_args['filename'];

		return $args;
	}

	/**
	 * Triggers auto remove cron, triggers once daily.
	 * Cloud addons can override this method to trigger auto delete functionality.
	 * By default it does nothing.
	 *
	 * @return void
	 * @since 1.1.5
	 */
	final public function cloud_auto_remove() {

		if ( ! $this->cloud ) {
			return;
		}

		$this->_auto_remove();
	}

	/**
	 * Alias for `$this->cloud_auto_remove` method.
	 *
	 * @abstract
	 * @return void
	 * @since 1.1.5
	 */
	protected function _auto_remove() {} //phpcs:ignore
}
