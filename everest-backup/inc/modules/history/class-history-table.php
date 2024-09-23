<?php
/**
 * Create backup history list table using WP_List_Table.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Backup_Directory;
use Everest_Backup\Tags;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Create backup history list table using WP_List_Table.
 *
 * @credit https://gist.github.com/paulund/7659452
 * @since 1.0.0
 */
class History_Table extends \WP_List_Table {

	/**
	 * Gets a list of CSS classes for the WP_List_Table table tag.
	 *
	 * @return string[] Array of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		$mode = get_user_setting( 'posts_list_mode', 'list' );

		$mode_class = esc_attr( 'table-view-' . $mode );

		return array( 'widefat', 'striped', $mode_class, $this->_args['plural'] );
	}

	/**
	 * Check if current page is history page or any other page.
	 * Because this class can also be used in other pages for listing the backup history.
	 *
	 * @return bool
	 */
	private function is_history_page() {
		$get = everest_backup_get_submitted_data( 'get' );

		if ( empty( $get['page'] ) ) {
			return false;
		}

		return 'everest-backup-history' === $get['page'];
	}

	/**
	 * Message to display when no backups are found in the history list.
	 *
	 * @return void
	 */
	public function no_items() {

		$backup_page = network_admin_url( '/admin.php?page=everest-backup-export' );
		$link        = '<a href="' . esc_url( $backup_page ) . '">' . esc_html__( 'Click here', 'everest-backup' ) . '</a>';
		/* translators: %s is the link for Backup page. */
		printf( esc_html__( 'No backup found. %s to create a new backup.', 'everest-backup' ), wp_kses( $link, 'a' ) );
	}

	/**
	 * Prepare the items for the table to process
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$data = $this->table_data();
		usort( $data, array( &$this, 'sort_data' ) );

		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = count( $data );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return array
	 */
	public function get_columns() {

		$tags_display_type = $this->get_tags_display_type();

		$html = sprintf( '<a href="%1$s">%2$s</a>', add_query_arg( 'tags', ( 'included' === $tags_display_type ? 'excluded' : 'included' ) ), ucfirst( $tags_display_type ) );

		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'filename' => __( 'Name', 'everest-backup' ),
			'size'     => __( 'Size', 'everest-backup' ),
			/* translators: %s is tags display type. */
			'tags'     => sprintf( __( 'Tags ( %s )', 'everest-backup' ), $html ), // @since 1.0.9
			'time'     => __( 'Created On', 'everest-backup' ),
		);

		if ( ! $this->is_history_page() ) {
			unset( $columns['cb'] );
		}

		return $columns;
	}

	/**
	 * Callback function for checkbox field.
	 *
	 * @param array $item Columns items.
	 * @return string
	 * @since 1.0.0
	 */
	public function column_cb( $item ) {
		$value = ! empty( $item['file_id'] ) ? $item['file_id'] : $item['filename'];
		return sprintf(
			'<input type="checkbox" name="remove[]" value="%s" />',
			rawurlencode( $value )
		);
	}

	/**
	 * Bulk action items.
	 *
	 * @return array $actions Bulk actions.
	 * @since 1.0.0
	 */
	public function get_bulk_actions() {

		if ( ! $this->is_history_page() ) {
			return array();
		}

		$actions = array();

		$actions['remove'] = __( 'Remove', 'everest-backup' );

		return $actions;
	}

	/**
	 * Returns the currently selected cloud key.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function get_selected_cloud() {
		$get = everest_backup_get_submitted_data( 'get' );

		return ! empty( $get['cloud'] ) ? sanitize_text_field( wp_unslash( $get['cloud'] ) ) : 'server';
	}

	/**
	 * Returns tag display type.
	 *
	 * @return string
	 * @since 1.1.0
	 */
	private function get_tags_display_type() {
		$get = everest_backup_get_submitted_data( 'get' );

		$general = everest_backup_get_settings( 'general' );

		$tags_display_type = ! empty( $general['tags_display_type'] ) ? $general['tags_display_type'] : 'included';

		if ( isset( $get['tags'] ) ) {
			$tags_display_type = $get['tags'];
		}

		return $tags_display_type;
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which Which table navigation is it... Is it top or bottom.
	 * @return void
	 * @since 1.0.0
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$cloud             = $this->get_selected_cloud();
		$package_locations = everest_backup_package_locations();

		?>
		<div class="alignleft actions">
			<?php

			do_action( 'everest_backup_history_before_filters', $cloud );

			if ( count( $package_locations ) > 1 ) {
				everest_backup_package_location_dropdown(
					array(
						'name'              => 'cloud',
						'selected'          => $this->get_selected_cloud(),
						'package_locations' => $package_locations,
					)
				);

				submit_button( __( 'Filter', 'everest-backup' ), '', 'filter_action', false );
			}

			do_action( 'everest_backup_history_after_filters', $cloud );
			?>

		</div>
		<?php
	}

	/**
	 * Generates the table navigation above or below the table
	 *
	 * @param string $which Is it top or bottom of the table.
	 */
	protected function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php if ( $this->has_items() ) : ?>
			<div class="alignleft actions bulkactions">
				<?php $this->bulk_actions( $which ); ?>
			</div>
				<?php
			endif;
			$this->extra_tablenav( $which );
			$this->pagination( $which );

			if ( 'bottom' === $which ) {
				?>
				<script>
					(function() {
						var deleteButtons = document.querySelectorAll( '.ebwp-remove.trash .submitdelete' );
						deleteButtons.forEach(function(deleteButton){
							deleteButton.addEventListener('click', function(event){
								event.preventDefault();
								if ( confirm( "<?php esc_html_e( 'Are you sure you want to delete this file?', 'everest-backup' ); ?>" ) ) {
									window.location.href = event.target.href;
								}
							});
						});
					})();
				</script>
				<?php
			}
			?>

			<br class="clear" />
		</div>
		<?php

		if ( 'top' === $which ) {

			$cloud = $this->get_selected_cloud();

			if ( 'server' !== $cloud ) {

				$backup_location = everest_backup_get_cloud_backup_location( $cloud, 'display', false );

				if ( $backup_location ) {
					?>
					<div class="ebwp-cloud-backup-location" style="margin-bottom: 15px;padding: 10px 10px;border: 1px solid;">
						<strong><?php esc_html_e( 'Backup Location', 'everest-backup' ); ?>:</strong>
						<code><?php echo esc_html( $backup_location ); ?></code>
						<a href="<?php echo esc_url( network_admin_url( '/admin.php?page=everest-backup-settings&tab=cloud' ) ); ?>"><?php esc_html_e( 'Change?', 'everest-backup' ); ?></a>
					</div>
					<?php
				}
			}
		}
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return array
	 */
	public function get_hidden_columns() {
		return array();
	}

	/**
	 * Define the sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'size' => array( 'size', false ),
			'time' => array( 'time', false ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data() {

		/**
		 * Helps to shortcircuit table data listing.
		 * Useful in a case such as listing the cloud files.
		 */
		$backups = apply_filters( 'everest_backup_history_table_data', null, $this->get_selected_cloud() );

		if ( is_array( $backups ) ) {
			return $backups;
		}

		return Backup_Directory::init()->get_backups();
	}

	/**
	 * Returns backup file remove link.
	 *
	 * @param array $item Row item array.
	 * @return string
	 * @since 1.0.0
	 */
	private function get_remove_link( $item ) {

		$file = ! empty( $item['file_id'] ) ? $item['file_id'] : $item['filename'];

		$admin_url = network_admin_url( '/admin.php' );
		$url       = add_query_arg(
			array(
				'page'   => 'everest-backup-history',
				'action' => 'remove',
				'cloud'  => $this->get_selected_cloud(),
				'file'   => rawurlencode( $file ),
			),
			$admin_url
		);

		return $url;
	}

	/**
	 * Returns backup file restore link.
	 *
	 * @param array $item Row item array.
	 * @return string
	 * @since 1.0.0
	 */
	private function get_restore_link( $item ) {

		$file = ! empty( $item['file_id'] ) ? $item['file_id'] : $item['filename'];

		$admin_url = network_admin_url( '/admin.php' );
		$url       = add_query_arg(
			array(
				'page'   => 'everest-backup-import',
				'action' => 'rollback',
				'cloud'  => $this->get_selected_cloud(),
				'file'   => rawurlencode( $file ),
			),
			$admin_url
		);

		return $url;
	}

	/**
	 * Returns backup file restore link.
	 *
	 * @param array $item Row item array.
	 * @return string
	 * @since 1.1.2
	 */
	private function get_download_zip_link( $item ) {

		$file = ! empty( $item['file_id'] ) ? $item['file_id'] : $item['filename'];

		$admin_url = network_admin_url( '/admin.php' );
		$url       = add_query_arg(
			array(
				'page'   => 'everest-backup-history',
				'action' => 'download-as-zip',
				'file'   => rawurlencode( $file ),
				'_nonce' => everest_backup_create_nonce( $file ),
			),
			$admin_url
		);

		return $url;
	}

	/**
	 * Returns array of row actions for packages.
	 *
	 * @param array $item Items passed for the table columns.
	 * @return array
	 * @since 1.0.0
	 */
	protected function package_row_actions( $item ) {
		$row_actions = array(
			'ebwp-download'     => sprintf( '<a href="%1$s" class="button button-success" target="_blank">%2$s</a>', esc_url( $item['url'] ), esc_html__( 'Download', 'everest-backup' ) ),
			'ebwp-download-zip' => null,
			'ebwp-migrate'      => null,
			'ebwp-rollback'     => sprintf( '<a href="%1$s" class="button-secondary">%2$s</a>', esc_url( $this->get_restore_link( $item ) ), esc_html__( 'Rollback', 'everest-backup' ) ),
			'ebwp-remove trash' => sprintf( '<a href="%1$s" class="submitdelete">%2$s</a>', esc_url( $this->get_remove_link( $item ) ), esc_html__( 'Remove', 'everest-backup' ) ),
		);

		$selected_cloud = $this->get_selected_cloud();

		if ( 'server' === $selected_cloud ) {
			$available_clouds = apply_filters( 'everest_backup_available_clouds', array() );
			if ( ! empty( $available_clouds ) ) {
				$file = ! empty( $item['file_id'] ) ? $item['file_id'] : $item['filename'];

				$row_actions['ebwp-upload-to-cloud'] = '<button type="button" class="button button-success everest-backup-upload-to-cloud-btn" data-file="' . $file . '" data-active-plugins="' . implode( ',', $available_clouds ) . '" data-file_size="' . $item['size'] . '">Upload to Cloud</button>';
			}
		}

		// @since 1.1.2
		// phpcs:disable
		if ( everest_backup_is_debug_on() ) {
			if ( 'server' === $selected_cloud ) {
				// $row_actions['ebwp-download-zip'] = sprintf( '<a href="%1$s" class="button-primary" target="_blank">%2$s</a>', esc_url( $this->get_download_zip_link( $item ) ), esc_html__( 'Download As ZIP', 'everest-backup' ) );
			}
		}
		// phpcs:enable

		if ( $this->is_history_page() && ( 'server' === $selected_cloud ) ) {

			$everest_backup_migration = new Migration(
				array(
					'file'       => $item['filename'],
					'auto_nonce' => true,
				)
			);

			$migration_url = $everest_backup_migration->get_url();

			$row_actions['ebwp-migrate'] = sprintf( '<a href="%1$s" class="button-primary">%2$s</a>', esc_url( $migration_url ), esc_html__( 'Migration Key', 'everest-backup' ) );
		}

		$args = compact( 'item', 'selected_cloud' );

		$actions = apply_filters( 'everest_backup_history_row_actions', $row_actions, $args );

		if ( ! $this->is_history_page() ) {
			$action_keys = array_keys( $row_actions );

			if ( is_array( $action_keys ) && ! empty( $action_keys ) ) {
				foreach ( $action_keys as $action_key ) {
					if ( 'ebwp-rollback' === $action_key ) {
						continue;
					}

					unset( $actions[ $action_key ] );
				}
			}
		}

		return array_filter( $actions );
	}

	/**
	 * Prints tags.
	 *
	 * @param array $item Column data.
	 * @return string
	 */
	protected function print_tags( $item ) {
		$filename = $item['filename'];

		$tags_display_type = $this->get_tags_display_type();

		$tags_obj = new Tags( $filename );

		$tags = $tags_obj->get( $tags_display_type );

		if ( ! is_array( $tags ) ) {
			return '<span style="border-width:1px;border-style:solid;padding:5px">N/A</span> ';
		}

		if ( ! $tags ) {
			return;
		}

		$info = __( 'Included', 'everest-backup' );

		if ( 'excluded' === $tags_display_type ) {
			$info = __( 'Excluded', 'everest-backup' );
		}

		$html = '<p style="color: #ffffff;">' . $info . '</p><ul style="color: #ffffff;">';

		if ( is_array( $tags ) && ! empty( $tags ) ) {
			foreach ( $tags as $tag ) {
				$html .= '<li style="border-width:1px;border-style:solid;padding:5px">' . ucfirst( $tag ) . '</li>';
			}
		}

		$html .= '</ul>';

		ob_start();

		everest_backup_tooltip( $html );

		return ob_get_clean();
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param array  $item        Column data.
	 * @param string $column_name Current column name.
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'filename':
				return '<strong>' . $item[ $column_name ] . '</strong>' . $this->row_actions( $this->package_row_actions( $item ) );
			case 'size':
				return everest_backup_format_size( $item[ $column_name ] );
			case 'tags':
				return $this->print_tags( $item );
			case 'time':
				$file_time = $item[ $column_name ];
				$time_diff = human_time_diff( $file_time );

				/* translators: %s is the human time difference result. */
				return '<span title="' . sprintf( __( '%s ago', 'everest-backup' ), esc_attr( $time_diff ) ) . '">' . wp_date( 'h:i:s A [F j, Y]', $file_time ) . '</span>';

			default:
				return;
		}
	}

	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @param array $data1 Data one to compare to.
	 * @param array $data2 Data two to compare with.
	 * @return mixed
	 * @since 1.0.0
	 */
	private function sort_data( $data1, $data2 ) {

		$get = everest_backup_get_submitted_data( 'get' );

		// Set defaults.
		$orderby = 'time';
		$order   = 'desc';

		// If orderby is set, use this as the sort column.
		if ( ! empty( $get['orderby'] ) ) {
			$orderby = $get['orderby'];
		}

		// If order is set use this as the order.
		if ( ! empty( $get['order'] ) ) {
			$order = $get['order'];
		}

		$result = strcmp( $data1[ $orderby ], $data2[ $orderby ] );

		if ( 'asc' === $order ) {
			return $result;
		}

		return -$result;
	}
}
