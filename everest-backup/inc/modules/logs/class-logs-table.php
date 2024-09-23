<?php
/**
 * Create backup history list table using WP_List_Table.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Logs;

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
 * Create logs list table using WP_List_Table.
 *
 * @credit https://gist.github.com/paulund/7659452
 * @since 1.0.0
 */
class Logs_Table extends \WP_List_Table {

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

			<input type="submit" class="button-secondary" name="clear_all_logs" value="<?php esc_attr_e( 'Clear All Logs', 'everest-backup' ); ?>">
				<?php

			endif;
			$this->extra_tablenav( $which );
			$this->pagination( $which );
			?>

			<br class="clear" />
		</div>
		<?php
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
		$columns = array(
			'cb'   => '<input type="checkbox" />',
			'logs' => __( 'Logs', 'everest-backup' ),
			'type' => __( 'Type', 'everest-backup' ),
		);

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
		return sprintf(
			'<input type="checkbox" name="remove[]" value="%s" />',
			rawurlencode( $item['time'] )
		);
	}

	/**
	 * Bulk action items.
	 *
	 * @return array $actions Bulk actions.
	 * @since 1.0.0
	 */
	public function get_bulk_actions() {
		$actions = array();

		$actions['remove'] = __( 'Remove', 'everest-backup' );

		return $actions;
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
			'logs' => array( 'time', false ),
		);
	}

	/**
	 * Get the table data
	 *
	 * @return array
	 */
	private function table_data() {
		return Logs::retrive();
	}

	/**
	 * Returns logs html data.
	 *
	 * @param array $logs Logs array.
	 * @return string $html Logs array converted into html.
	 * @since 1.0.0
	 */
	private function get_logs_html( $logs ) {
		$html = '<ul class="card">';
		if ( is_array( $logs ) && ! empty( $logs ) ) {
			foreach ( $logs as $index => $log ) {

				if ( ! isset( $log['type'] ) ) {
					continue;
				}

				$type = $log['type'];

				if ( 'done' === $log['type'] ) {
					$type = 'success';
				}

				$message = $log['message'];

				$html .= "<li class='logs-list-item item-key-{$index} notice notice-{$type}'>{$message}</li>";
			}
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param array  $item        Column data.
	 * @param string $column_name Current column name.
	 *
	 * @return mixed
	 * @since 1.1.2 Added support for key parameter.
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'logs':
				$time = $item['time'];

				$key = ! empty( $_GET['key'] ) ? absint( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : 0; // @phpcs:ignore

				$log_name = wp_date( 'd F Y h:i:s A', $time );

				$logs = $this->get_logs_html( $item['logs'] );
				return sprintf(
					'<details %1$s><summary style="cursor: pointer;">%2$s</summary>%3$s</details>',
					$key === $time ? 'open' : '',
					$log_name,
					$logs
				);
			case 'type':
				$types = everest_backup_get_process_types();
				$type  = isset( $item['type'] ) && isset( $types[ $item['type'] ] ) ? $types[ $item['type'] ] : 'N/A';

				return '<strong>' . $type . '</strong>';
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
