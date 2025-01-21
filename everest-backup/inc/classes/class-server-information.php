<?php
/**
 * Class for displaying server related information.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

use Everest_Backup\Traits\Singleton;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for displaying server related information.
 *
 * @since 1.0.0
 */
class Server_Information {


	use Singleton;

	/**
	 * Submitted data.
	 *
	 * @var array
	 */
	protected $request = array();

	/**
	 * Information data.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected $infos = array();

	/**
	 * Init class.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->request = everest_backup_get_submitted_data();

		Backup_Directory::init()->create();
		Temp_Directory::init()->create();

		$this->set_domain_info();
		$this->set_server_info();
		$this->set_wp_info();
		$this->set_ebwp_info();
	}

	/**
	 * Returns yes or no string text.
	 *
	 * @param bool $condition True or false value.
	 * @return string
	 */
	private function yes_no( $condition ) {
		return $condition ? __( 'Yes', 'everest-backup' ) : __( 'No', 'everest-backup' );
	}

	/**
	 * Information about domain.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_domain_info() {
		$this->infos['is_ssl'] = array(
			'label' => __( 'SSL', 'everest-backup' ),
			'value' => $this->yes_no( is_ssl() ),
		);

		$this->infos['home_url'] = array(
			'label' => __( 'Home Url', 'everest-backup' ),
			'value' => home_url( '/' ),
		);

		$this->infos['site_url'] = array(
			'label' => __( 'Site Url', 'everest-backup' ),
			'value' => site_url( '/' ),
		);
	}

	/**
	 * Information about server and host.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_server_info() {

		$activity_url = everest_backup_get_activity_log_url();

		/** @since 2.2.0 */
		$this->infos['activity_log_url'] = array(
			'label' => __( 'Activity Log URL', 'everest-backup' ),
			'value' => ( 'json' === $this->get_view_type() ) ? $activity_url : "<a href='{$activity_url}' target='_blank'>{$activity_url}</a>",
		);

		$this->infos['is_writable'] = array(
			'label' => __( 'Writable', 'everest-backup' ),
			'value' => $this->yes_no( wp_is_writable( ABSPATH ) ),
		);

		$this->infos['php_version'] = array(
			'label' => __( 'PHP Version', 'everest-backup' ),
			'value' => PHP_VERSION,
		);

		$this->infos['server_engine'] = array(
			'label' => __( 'Server Engine', 'everest-backup' ),
			'value' => ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A',
		);

		$this->infos['total_space'] = array(
			'label' => __( 'Total Space', 'everest-backup' ),
			'value' =>  everest_backup_is_php_function_enabled( 'disk_total_space' ) ? everest_backup_format_size( disk_total_space( ABSPATH ) ) : 'N/A',
		);

		$this->infos['available_space'] = array(
			'label' => __( 'Available Space', 'everest-backup' ),
			'value' => everest_backup_format_size( everest_backup_disk_free_space( ABSPATH ) ),
		);

		$this->infos['memory_limit'] = array(
			'label' => __( 'Memory Limit', 'everest-backup' ),
			'value' => ini_get( 'memory_limit' ),
		);

		$this->infos['max_upload_size'] = array(
			'label' => __( 'Maximum Upload Size', 'everest-backup' ),
			'value' => everest_backup_format_size( wp_max_upload_size() ),
		);

		$this->infos['operating_system'] = array(
			'label' => __( 'Operating System', 'everest-backup' ),
			'value' => PHP_OS,
		);

		$this->infos['gzip_lib_enabled'] = array(
			'label' => __( 'GZip Library Enabled', 'everest-backup' ),
			'value' => $this->yes_no( everest_backup_is_gzip_lib_enabled() ), // Class names.
		);

	}

	/**
	 * Information about WordPress
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_wp_info() {
		global $wp_version, $wp_db_version;

		$this->infos['wp_version'] = array(
			'label' => __( 'WordPress Version', 'everest-backup' ),
			'value' => $wp_version,
		);

		$this->infos['db_version'] = array(
			'label' => __( 'Database Version', 'everest-backup' ),
			'value' => $wp_db_version,
		);

		$this->infos['is_multisite'] = array(
			'label' => __( 'Multisite', 'everest-backup' ),
			'value' => $this->yes_no( is_multisite() ),
		);
	}

	/**
	 * Set Everest Backup related information.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_ebwp_info() {

		$backup_dir_path = EVEREST_BACKUP_BACKUP_DIR_PATH;
		$temp_dir_path   = EVEREST_BACKUP_TEMP_DIR_PATH;

		$this->infos['ebwp_backup_dir_path'] = array(
			'label' => __( 'Backup Directory Path', 'everest-backup' ),
			'value' => $backup_dir_path,
		);

		$this->infos['ebwp_backup_dir_writable'] = array(
			'label' => __( 'Backup Directory Writable', 'everest-backup' ),
			'value' => $this->yes_no( $backup_dir_path ),
		);

		$this->infos['ebwp_temp_dir_path'] = array(
			'label' => __( 'Temporary Directory Path', 'everest-backup' ),
			'value' => $temp_dir_path,
		);

		$this->infos['ebwp_temp_dir_writable'] = array(
			'label' => __( 'Temporary Directory Writable', 'everest-backup' ),
			'value' => $this->yes_no( wp_is_writable( $temp_dir_path ) ),
		);

		$this->infos['ebwp_version'] = array(
			'label' => __( 'EBWP Version', 'everest-backup' ),
			'value' => EVEREST_BACKUP_VERSION,
		);

		$this->infos['ebwp_encrypt'] = array(
			'label' => __( 'EBWP Encrypt', 'everest-backup' ),
			'value' => extension_loaded( 'openssl' ) ? 'openssl' : 'N/A',
		);

		$this->infos['ebwp_addons'] = array(
			'label' => __( 'EBWP Addons', 'everest-backup' ),
			'value' => $this->addons_info(),
		);

		$this->infos['ebwp_active_addons'] = array(
			'label' => __( 'EBWP Active Addons', 'everest-backup' ),
			'value' => $this->addons_info( 'active' ),
		);

		$this->infos['ebwp_paused_addons'] = array(
			'label' => __( 'EBWP Paused Addons', 'everest-backup' ),
			'value' => $this->addons_info( 'paused' ),
		);
	}

	/**
	 * Returns addons info.
	 *
	 * @param string $filter Filter addon. Supports all, active, and paused.
	 * @return array
	 */
	protected function addons_info( $filter = 'all' ) {
		$addons      = array();
		$ebwp_addons = everest_backup_installed_addons( $filter );

		if ( is_array( $ebwp_addons ) && ! empty( $ebwp_addons ) ) {
			foreach ( $ebwp_addons as $ebwp_addon ) {
				$plugin_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $ebwp_addon;
				$data        = get_plugin_data( $plugin_file, false, false );

				$addons[] = "{$data['Name']} [{$data['Version']}]";
			}
		}

		return $addons;
	}

	/**
	 * Returns view type.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	protected function get_view_type() {
		if ( empty( $this->request['view'] ) ) {
			return 'table';
		}

		return $this->request['view'];
	}

	/**
	 * Returns information array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_infos() {
		return $this->infos;
	}

	/**
	 * Print information HTML table.
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 1.1.0 Add option to view data as json.
	 */
	public function display() {
		$infos = $this->get_infos();
		$view  = $this->get_view_type();

		?>
		<div class="everest-backup-information-container">

			<a class="button-small" href="<?php echo esc_url( add_query_arg( 'view', ( 'json' === $view ? 'table' : 'json' ) ) ); ?>"><?php echo 'json' === $view ? esc_html__( 'View Table', 'everest-backup' ) : esc_html__( 'View JSON', 'everest-backup' ); ?></a>

			<hr>

			<?php
			if ( 'json' === $view ) {
				?>
				<div class="container">
					<textarea readonly style="resize:both; width:100%; height:100vh;"><?php echo wp_json_encode( $infos, JSON_PRETTY_PRINT ); ?></textarea>
				</div>
				<?php
			} else {
				?>
				<table class="widefat striped" role="presentation">
					<tbody>

						<?php
						if ( is_array( $infos ) && ! empty( $infos ) ) {
							foreach ( $infos as $key => $info ) {
								?>
								<tr id="<?php echo esc_attr( $key ); ?>">
									<td><?php echo wp_kses_post( $info['label'] ); ?></td>

									<?php
									if ( is_array( $info['value'] ) ) {
										?>
										<td>
											<?php
											if ( $info['value'] ) {
												?>
												<p><span class="dashicons dashicons-arrow-right"></span> <?php echo wp_kses_post( implode( '</p><p><span class="dashicons dashicons-arrow-right"></span> ', $info['value'] ) ); ?></p>
												<?php
											}
											?>
										</td>
										<?php
									} else {
										?>
										<td><?php echo wp_kses_post( $info['value'] ); ?></td>
										<?php
									}
									?>
								</tr>
								<?php
							}
						}
						?>

					</tbody>
				</table>
				<?php
			}

			?>

		</div>
		<?php
	}
}
