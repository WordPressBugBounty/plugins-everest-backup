<?php
/**
 * Handle admin menu for this plugin.
 *
 * @package everest-backup
 */

namespace Everest_Backup;

use function cli\err;
use Everest_Backup\Modules\Cron_Actions;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle admin menu for this plugin.
 *
 * @since 1.0.0
 */
class Admin_Menu {

	/**
	 * Create and register admin menus.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'admin_head', '\Everest_Backup\Admin_Menu::upsell_attr', 10 );
		$hook = self::get_admin_menu_hook();
		add_action( $hook, array( __CLASS__, 'register' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_item' ), 100 );
	}

	/**
	 * Register admin menu and sub menus.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function register() {
		self::register_menus();
		self::register_submenus();
		self::maybe_manual_run_scheduled_backup();
	}

	private static function get_admin_menu_hook() {
		if ( is_multisite() ) {
			$settings = everest_backup_get_settings( 'general' );
			if ( ! is_network_admin() && isset( $settings['show_menu_in_site_admin_dashboard'] ) && 'yes' === $settings['show_menu_in_site_admin_dashboard'] ) {
				return 'admin_menu';
			} else {
				return 'network_admin_menu';
			}
		}
		return 'admin_menu';
	}

	private static function maybe_manual_run_scheduled_backup() {
		$get = everest_backup_get_submitted_data( 'get' );
		if ( isset( $get['page'] ) && ( 'everest-backup-export' === $get['page'] ) && isset( $get['manual_scheduled_backup'] ) && ( 'run' === $get['manual_scheduled_backup'] ) ) {
			include_once EVEREST_BACKUP_PATH . 'inc/modules/cron/class-cron-actions.php';
			$cron_actions = new Cron_Actions();
			$cron_actions->schedule_backup( true );
			unset( $get['manual_scheduled_backup'] );
			wp_redirect( admin_url(sprintf('admin.php?%s', http_build_query( $get ) ) ) );exit();
		}
	}

	/**
	 * Add Everest Backup related menu items to admin bar for user ease.
	 *
	 * @param \WP_Admin_Bar $admin_bar WP_Admin_Bar class object.
	 * @return void
	 */
	public static function admin_bar_item( \WP_Admin_Bar $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$admin_menus = self::get_menus();

		if ( is_array( $admin_menus ) && ! empty( $admin_menus ) ) {
			foreach ( $admin_menus as $slug => $admin_menu ) {
				$main_menu_slug = "everest-backup-{$slug}";

				$admin_bar->add_menu(
					array(
						'id'     => $main_menu_slug,
						'parent' => null,
						'group'  => null,
						'title'  => ! empty( $admin_menu['menu_title'] ) ? $admin_menu['menu_title'] : '',
						'href'   => network_admin_url( "admin.php?page={$main_menu_slug}" ),
					)
				);

			}
		}

		$submenus = self::get_submenus( true );

		if ( is_array( $submenus ) && ! empty( $submenus ) ) {
			foreach ( $submenus as $slug => $submenu ) {
				$menu_slug = "everest-backup-{$slug}";

				$admin_bar->add_menu(
					array(
						'id'     => 'everest-backup-export' === $menu_slug ? "$menu_slug-2" : $menu_slug,
						'parent' => ! empty( $submenu['parent_slug'] ) ? $submenu['parent_slug'] : 'everest-backup-export',
						'group'  => null,
						'title'  => ! empty( $submenu['menu_title'] ) ? $submenu['menu_title'] : '',
						'href'   => ! empty( $submenu['href'] ) ? $submenu['href'] : network_admin_url( "admin.php?page={$menu_slug}" ),
					)
				);

			}
		}
	}

	/**
	 * Register admin menus.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function register_menus() {
		$admin_menus = self::get_menus();

		if ( is_array( $admin_menus ) && ! empty( $admin_menus ) ) {
			foreach ( $admin_menus as $slug => $admin_menu ) {
				$menu_slug = "everest-backup-{$slug}";

				add_menu_page(
					! empty( $admin_menu['page_title'] ) ? $admin_menu['page_title'] : '',
					! empty( $admin_menu['menu_title'] ) ? $admin_menu['menu_title'] : '',
					! empty( $admin_menu['capability'] ) ? $admin_menu['capability'] : '',
					$menu_slug,
					! empty( $admin_menu['function'] ) ? $admin_menu['function'] : "Everest_Backup\Template_Functions\\{$slug}_page_template_cb",
					! empty( $admin_menu['icon_url'] ) ? $admin_menu['icon_url'] : '',
					! empty( $admin_menu['position'] ) ? $admin_menu['position'] : null
				);

			}
		}
	}

	/**
	 * Register submenus.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function register_submenus() {
		$submenus = self::get_submenus();

		if ( is_array( $submenus ) && ! empty( $submenus ) ) {
			foreach ( $submenus as $slug => $submenu ) {
				$menu_slug = "everest-backup-{$slug}";

				add_submenu_page(
					! empty( $submenu['parent_slug'] ) ? $submenu['parent_slug'] : 'everest-backup-export',
					! empty( $submenu['page_title'] ) ? $submenu['page_title'] : '',
					! empty( $submenu['menu_title'] ) ? $submenu['menu_title'] : '',
					! empty( $submenu['capability'] ) ? $submenu['capability'] : '',
					$menu_slug,
					! empty( $submenu['function'] ) ? $submenu['function'] : "Everest_Backup\Template_Functions\\{$slug}_page_template_cb",
					! empty( $submenu['position'] ) ? $submenu['position'] : null
				);

			}
		}
	}

	/**
	 * Return an array of menus arguments.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	protected static function get_menus() {
		$menus = array(
			'export' => array(
				'page_title' => __( 'Backup', 'everest-backup' ),
				'menu_title' => __( 'Everest Backup', 'everest-backup' ),
				'capability' => 'manage_options',
				'function'   => '',
				'icon_url'   => EVEREST_BACKUP_URL . 'assets/images/icon.png',
				'position'   => null,
			),
		);

		return apply_filters( 'everest_backup_filter_admin_menus', $menus );
	}

	/**
	 * Returns an array of submenus arguments.
	 *
	 * @param any $admin_bar Admin bar.
	 * @return array
	 * @since 1.0.0
	 */
	protected static function get_submenus( $admin_bar = false ) {

		$get = everest_backup_get_submitted_data( 'get' );

		$submenus = array(
			'export'          => array(
				'parent_slug' => '',
				'page_title'  => __( 'Backup', 'everest-backup' ),
				'menu_title'  => __( 'Backup', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'import'          => array(
				'parent_slug' => '',
				'page_title'  => __( 'Restore', 'everest-backup' ),
				'menu_title'  => __( 'Restore', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'migration_clone' => array(
				'parent_slug' => '',
				'page_title'  => __( 'Migration / Clone', 'everest-backup' ),
				'menu_title'  => __( 'Migration / Clone', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'history'         => array(
				'parent_slug' => '',
				'page_title'  => __( 'Backup History', 'everest-backup' ),
				'menu_title'  => __( 'History', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'logs'            => array(
				'parent_slug' => '',
				'page_title'  => __( 'Logs', 'everest-backup' ),
				'menu_title'  => __( 'Logs', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'settings'        => array(
				'parent_slug' => '',
				'page_title'  => __( 'Settings', 'everest-backup' ),
				'menu_title'  => __( 'Settings', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
			'addons'          => array(
				'parent_slug' => '',
				'page_title'  => __( 'Addons', 'everest-backup' ),
				'menu_title'  => __( 'Addons', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			),
		);

		/**
		 * Admin bar specific menus.
		 */
		if ( $admin_bar ) {

			// Backup page tabs.
			$submenus['export__manual_backup'] = array(
				'parent_slug' => 'everest-backup-export-2',
				'page_title'  => __( 'Manual Backup', 'everest-backup' ),
				'menu_title'  => __( 'Manual Backup', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-export&tab=manual_backup' ),
				'position'    => null,
			);

			$submenus['export__schedule_backup'] = array(
				'parent_slug' => 'everest-backup-export-2',
				'page_title'  => __( 'Schedule Backup', 'everest-backup' ),
				'menu_title'  => __( 'Schedule Backup', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-export&tab=schedule_backup' ),
				'position'    => null,
			);

			// Restore page tabs.
			$submenus['import__upload_files'] = array(
				'parent_slug' => 'everest-backup-import',
				'page_title'  => __( 'Upload File', 'everest-backup' ),
				'menu_title'  => __( 'Upload File', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-import&tab=upload_file' ),
				'position'    => null,
			);

			$submenus['import__available_files'] = array(
				'parent_slug' => 'everest-backup-import',
				'page_title'  => __( 'Available Files', 'everest-backup' ),
				'menu_title'  => __( 'Available Files', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-import&tab=available_files' ),
				'position'    => null,
			);

			// Migration Clone tabs.
			$submenus['migration_clone__migration'] = array(
				'parent_slug' => 'everest-backup-migration_clone',
				'page_title'  => __( 'Migration', 'everest-backup' ),
				'menu_title'  => __( 'Migration', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-migration_clone&tab=migration' ),
				'position'    => null,
			);

			$submenus['migration_clone__clone'] = array(
				'parent_slug' => 'everest-backup-migration_clone',
				'page_title'  => __( 'Clone', 'everest-backup' ),
				'menu_title'  => __( 'Clone', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-migration_clone&tab=clone' ),
				'position'    => null,
			);

			// Settings tabs.
			$submenus['settings__general'] = array(
				'parent_slug' => 'everest-backup-settings',
				'page_title'  => __( 'General', 'everest-backup' ),
				'menu_title'  => __( 'General', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-settings&tab=general' ),
				'position'    => null,
			);

			$submenus['settings__cloud'] = array(
				'parent_slug' => 'everest-backup-settings',
				'page_title'  => __( 'Cloud', 'everest-backup' ),
				'menu_title'  => __( 'Cloud', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-settings&tab=cloud' ),
				'position'    => null,
			);

			$submenus['settings__information'] = array(
				'parent_slug' => 'everest-backup-settings',
				'page_title'  => __( 'Information', 'everest-backup' ),
				'menu_title'  => __( 'Information', 'everest-backup' ),
				'capability'  => 'manage_options',
				'function'    => '',
				'href'        => network_admin_url( 'admin.php?page=everest-backup-settings&tab=information' ),
				'position'    => null,
			);

			if ( everest_backup_is_debug_on() ) {
				$submenus['settings__debug'] = array(
					'parent_slug' => 'everest-backup-settings',
					'page_title'  => __( 'Debug', 'everest-backup' ),
					'menu_title'  => __( 'Debug', 'everest-backup' ),
					'capability'  => 'manage_options',
					'function'    => '',
					'href'        => network_admin_url( 'admin.php?page=everest-backup-settings&tab=debug' ),
					'position'    => null,
				);
			}
		}

		$submenus = apply_filters( 'everest_backup_filter_admin_submenus', $submenus, $admin_bar );

		/**
		 * Lets make sure these menus stays at the end of stack at any cost.
		 */

		$submenus['changelogs'] = array(
			'parent_slug' => ( $admin_bar || ! empty( $get['page'] ) && 'everest-backup-changelogs' === $get['page'] ) ? '' : 'everest-backup-changelogs', // Hack for hidding sub menu.
			'page_title'  => __( 'Changelogs &#127882;', 'everest-backup' ),
			'menu_title'  => __( 'Changelogs', 'everest-backup' ),
			'capability'  => 'manage_options',
			'function'    => '',
			'position'    => null,
		);

		if ( ! self::is_pro_installed() ) {
			$pro_url = 'https://wpeverestbackup.com/pricing/?utm_medium=wpd&utm_source=eb&utm_campaign=upgradetopro';
			// @since 2.0.0
			$submenus['upgradetopro'] = array(
				'parent_slug' => '',
				'page_title'  => __( 'Upgrade To Pro', 'everest-backup' ),
				'menu_title'  => '<a href="' . $pro_url . '"><strong class="everest-backup-upgradetopro" style="color:#ffffff;">' . __( 'UPGRADE TO PRO', 'everest-backup' ) . '</strong></a>',
				'capability'  => 'manage_options',
				'function'    => '',
				'position'    => null,
			);
		}

		return $submenus;
	}

	/**
	 * Modify menu URL.
	 *
	 * @param  string $url URL to modify.
	 * @return string $url Modified menu URL.
	 */
	public static function modify_menu_url( $url ) {

		if ( self::is_pro_installed() ) {
			return $url;
		}

		if ( false !== strpos( $url, 'page=everest-backup-upgradetopro' ) ) {
			return 'https://wpeverestbackup.com/pricing/?utm_medium=wpd&utm_source=eb&utm_campaign=upgradetopro';
		}

		return $url;
	}

	/**
	 * Upsell attr.
	 */
	public static function upsell_attr() {

		if ( self::is_pro_installed() ) {
			return;
		}

		echo <<<JQUERY
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('.everest-backup-upgradetopro')
				.closest('a')
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.attr('style', 'margin-top: 10px;outline: 5px solid #fcb214;background: #fcb214;padding-bottom: 5px;');
		});
		</script>
		JQUERY;
	}

	/**
	 * Check if pro version is installed.
	 *
	 * @return bool
	 */
	private static function is_pro_installed() {
		return in_array( 'everest-backup-pro/everest-backup-pro.php', everest_backup_installed_addons(), true );
	}
}

Admin_Menu::init();
