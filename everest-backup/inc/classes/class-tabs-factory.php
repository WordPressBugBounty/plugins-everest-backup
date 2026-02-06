<?php
/**
 * Abstract class for handling tabs element.
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
 * Abstract class for handling tabs element.
 *
 * @abstract
 * @since 1.0.0
 */
class Tabs_Factory {

	/**
	 * Tab id.
	 *
	 * @use `Everest_Backup\Tabs_Factory::get_id()` to get the ID.
	 * @var string
	 * @since 1.0.0
	 */
	private $id;

	/**
	 * Current active tab.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private $current;

	/**
	 * Tab items array.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $items = array();

	/**
	 * URL $_GET queries.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	private $get_queries = array();

	/**
	 * Current page url.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $current_url;

	/**
	 * Init.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->get_queries = everest_backup_get_submitted_data( 'get' );

		$this->items = apply_filters( 'everest_backup_filter_tab_factory_items', $this->set_items(), $this->get_id() );

		$this->set_current_url();

		$this->set_id();
		$this->set_current();
	}

	/**
	 * Set current page url if it is not being set by child classes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_current_url() {

		if ( $this->current_url ) {
			return;
		}

		$whitelists = array( 'page', 'tab' );
		$query_keys = array_keys( $this->get_queries );
		$removables = array_diff( $query_keys, $whitelists );

		$this->current_url = remove_query_arg( $removables );
	}

	/**
	 * Set the tab id.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_id() {
		$class = get_class( $this );

		$this->id = strtolower( str_replace( '\\', '_', $class ) );
	}

	/**
	 * Get current tab id.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set current active tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function set_current() {
		$current = ! empty( $this->get_queries['tab'] ) ? $this->get_queries['tab'] : '';

		if ( ! $current ) {

			$item_keys = array_keys( $this->get_items() );

			/**
			 * If no current item is set then select the first item from the array as default.
			 */
			if ( is_array( $item_keys ) && ! empty( $item_keys ) ) {
				foreach ( $item_keys as $item_key ) {
					$current = $item_key;
					break;
				}
			}
		}

		$this->current = $current;
	}

	/**
	 * Returns current active tab.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_current() {
		return $this->current;
	}

	/**
	 * Set tab items array.
	 *
	 * @abstract
	 * @return array
	 * @since 1.0.0
	 */
	protected function set_items() {
		return array();
	}

	/**
	 * Returns sorted tab items array.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_items() {
		static $sorted = array();

		if ( $sorted ) {

			/**
			 * Return cached items.
			 */
			return $sorted;
		}

		$items = $this->items;

		uasort(
			$items,
			function ( $a, $b ) {
				return ( $a['priority'] - $b['priority'] );
			}
		);

		/**
		 * Cache our sorted items.
		 */
		$sorted = $items;

		return $items;
	}

	/**
	 * Prints tab heads.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function tab_heads() {
		$items       = $this->get_items();
		$current_tab = $this->get_current();

		?>
			<div class="wp-filter">
				<ul class="filter-links">
					<?php
					if ( is_array( $items ) && ! empty( $items ) ) {
						foreach ( $items as $key => $item ) {
							$current = $current_tab === $key;

							?>
							<li>
								<a class="<?php echo $current ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $this->current_url ) ); ?>"><?php echo wp_kses_post( $item['label'] ); ?></a>
							</li>
							<?php
						}
					}
					?>
				</ul>
			</div>
		<?php
	}

	/**
	 * Prints tab contents.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected function tab_content() {
		$items       = $this->get_items();
		$current_tab = $this->get_current();

		$callback = $items[ $current_tab ]['callback'];

		call_user_func( $callback );
	}

	/**
	 * Display tab.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function display() {

		$tab_id = str_replace( '_', '-', $this->get_id() );
		?>
		<div class="everest-backup-tab" id="<?php echo esc_attr( $tab_id ); ?>">
			<div class="tab-head">
				<?php $this->tab_heads(); ?>
			</div>

			<div class="tab-content">
				<?php $this->tab_content(); ?>
			</div>
		</div>
		<?php
	}
}
