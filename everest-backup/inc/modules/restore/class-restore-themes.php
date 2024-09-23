<?php
/**
 * Restore class for themes folder.
 *
 * @package everest-backup
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Logs;
use Everest_Backup\Traits\Restore;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restore class for themes folder.
 *
 * @since 1.0.0
 */
class Restore_Themes {

	use Restore;

	/**
	 * Start restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {
		$themes = self::get_module_files( 'themes' );

		if ( ! $themes ) {
			return;
		}

		$info_message = __( 'Restoring themes.', 'everest-backup' );

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 60,
				'message'  => $info_message,
			)
		);

		Logs::info( $info_message );

		$filesystem = self::$filesystem;

		if ( is_array( $themes ) && ! empty( $themes ) ) {
			foreach ( $themes as $theme ) {
				$upload_to = str_replace( self::$storage_dir, self::$wp_content_dir, $theme );

				$filesystem->move_file( $theme, $upload_to );
			}
		}

		$ms_blogs = self::$extract->get_temp_data( 'ms_blogs' );

		if ( is_array( $ms_blogs ) && ! empty( $ms_blogs ) ) {
			foreach ( $ms_blogs as $ms_blog_id => $ms_blog ) {
				switch_to_blog( $ms_blog_id );
				switch_theme( $ms_blog['Stylesheet'] );
				restore_current_blog();
			}
		} else {
			$config_data = self::get_config_data();

			switch_theme( $config_data['Stylesheet'] );

			self::after_theme_activated( $config_data );

		}

		Logs::info( __( 'Themes restored.', 'everest-backup' ) );

		everest_backup_log_memory_used();

	}

	/**
	 * @since 1.1.4
	 */
	protected static function after_theme_activated( $data ) {

		if ( isset( $data['NavMenus'] ) ) {
			set_theme_mod( 'nav_menu_locations', $data['NavMenus'] );
		}

		if ( isset( $data['Widgets'] ) ) {
			update_option( 'sidebars_widgets', $data['Widgets'] );
		}
	}

}
