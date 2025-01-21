<?php
/**
 * Wrap up archive import.
 *
 * @package EverestBackup
 */

namespace Everest_Backup\Core\Import;

use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Modules\Import_Database;
use Everest_Backup\Traits\Import;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrap up import.
 */
class Wrapup {

	use Import;

	public static function get_critical_tables( $prefix = '' ) {
		return array_map(
			function ( $table ) use ( $prefix ) {
				return $prefix . $table . '.sql';
			},
			apply_filters(
				'everest_backup_critical_tables_for_import',
					array(
					'users',
					'usermeta',
					'options',
					'sessions',
				)
			)
		);
	}

	/**
	 * Import databases.
	 *
	 * @param array $db_configs Database configs.
	 */
	private static function import_databases( $db_configs, $params ) {

		if ( empty( $db_configs['Tables'] ) ) {
			return;
		}

		$database_files = apply_filters(
			'everest_backup_tables_for_import',
			Filesystem::init()->list_files( everest_backup_current_request_storage_path( 'ebwp-files/ebwp-database' ) )
		);

		$total_tables = $params['total_tables'] ?? count( $database_files );

		$critical = false;

		$critical_tables = self::get_critical_tables( $db_configs['Prefix'] );

		if ( ! isset( $params['critical'] ) ) {
			$database_files = array_filter( $database_files, function ( $val ) use ( $critical_tables ) {
				foreach ( $critical_tables as $table ) {
					if ( false !== strpos( $val, $table ) ) {
						return false;
					}
				}
				return true;
			} );
		} else {
			$critical = true;
		}

		if ( empty( $database_files ) ) {
			return;
		}

		if ( is_array( $database_files ) && ! empty( $database_files ) ) {
			Logs::info( 'Importing databases', 'everest-backup' );
			$find_replace = self::get_find_replace();

			$start_time = time();
			$current_key = $params['current_key'] ?? 0;
			foreach ( $database_files as $database_file ) {

				$progress = ( ( $current_key + 1 ) / $total_tables ) * 100;

				$proc_stat_args = array(
					'status'   => 'in-process',
					'progress' => round( $progress * 0.25 + 65, 2 ), // At the end, it is always going to be 90%.
					'message'  => sprintf(
						/* translators: progress, current table number and total tables. */
						__( 'Importing database: %1$d%% completed [ %2$s out of %3$s ]', 'everest-backup' ),
						esc_html( $progress ),
						esc_html( $current_key + 1 ),
						esc_html( $total_tables )
					),
					'current_key' => $current_key,
					'total_tables' => $total_tables,
				);

				$blog = \everest_backup_get_temp_values_during_backup( 'blog' );

				if ( ! empty( $blog ) ) {
					foreach ( $blog as $blog_id => $blog_val ) {
						\switch_to_blog( $blog_id );

						$metadata = self::get_metadata();
						$config_data = $metadata['config'];

						$find_replace = array();

						$old_site_url = str_replace( array( 'http://', 'https://' ), '', $config_data['SiteURL'] );
						$old_home_url = str_replace( array( 'http://', 'https://' ), '', $config_data['HomeURL'] );

						$new_site_url = str_replace( array( 'http://', 'https://' ), '', $blog_val['SubsiteURL'] );
						$new_home_url = str_replace( array( 'http://', 'https://' ), '', $blog_val['SubsiteURL'] );

						$old_upload_dir = $config_data['WordPress']['UploadsDIR'];
						$new_upload_dir = everest_backup_get_uploads_dir();

						$old_upload_url = str_replace( array( 'http://', 'https://' ), '', $config_data['WordPress']['UploadsURL'] );
						$new_upload_url = str_replace( array( 'http://', 'https://' ), '', everest_backup_get_uploads_url() );

						$find_replace[ $old_site_url ]   = $new_site_url;
						$find_replace[ $old_home_url ]   = $new_home_url;
						$find_replace[ $old_upload_dir ] = $new_upload_dir;
						$find_replace[ $old_upload_url ] = $new_upload_url;
						$import_database = new Import_Database( $database_file, $db_configs['Tables'], $find_replace );
						$import_database->import_table(
							function ( $query_count ) use ( $proc_stat_args ) {
								/* translators: query count. */
								$proc_stat_args['detail'] = __( 'Queries count: ', 'everest-backup' ) . $query_count;
								return Logs::set_proc_stat( $proc_stat_args );
							}
						);
						\restore_current_blog();
					}
				} else {
					$import_database = new Import_Database( $database_file, $db_configs['Tables'], $find_replace );
					$import_database->import_table(
						function ( $query_count ) use ( $proc_stat_args ) {
							/* translators: query count. */
							$proc_stat_args['detail'] = __( 'Queries count: ', 'everest-backup' ) . $query_count;
							return Logs::set_proc_stat( $proc_stat_args );
						}
					);
				}

				/**
				 * Remove the imported database files.
				 */
				unlink( $database_file ); //phpcs:ignore
				if ( ( $start_time + 20 ) < time() ) {
					self::set_next( 'wrapup' );
					return true;
				}
				++$current_key;
			}

			if ( ! $critical ) {
				$procstat = Logs::get_proc_stat();
				if ( isset( $procstat['log'] ) ) {
					unset( $procstat['log'] );
				}
				$procstat['next']     = 'wrapup';
				$procstat['critical'] = true;
				return Logs::set_proc_stat( $procstat );
			}
		}

		update_option( 'template', '' );
		update_option( 'stylesheet', '' );
		update_option( 'active_plugins', array() );
		return false;
	}

	/**
	 * Run.
	 */
	private static function run( $params ) {

		$metadata = self::get_metadata();

		if ( empty( $metadata['config'] ) ) {
			return;
		}

		if ( isset( $params['incremental'] ) && isset( $params['skip_database'] ) ) {
			$msg = __( 'Next step...', 'everest-backup' );
			Logs::set_proc_stat(
				array(
					'log'      => 'info',
					'status'   => 'in-process',
					'progress' => 65,
					'message'  => $msg,
					'detail'   =>  __( 'Database skipped.', 'everest-backup' ),
				)
			);
			Logs::info( $msg );

			everest_backup_send_success();
	
			die();
		} else {
			if ( ! empty( $metadata['config']['Database'] ) ) {
				if ( self::import_databases( $metadata['config']['Database'], $params ) ) {
					return;
				}
				Logs::set_proc_stat(
					array(
						'log'      => 'info',
						'status'   => 'in-process',
						'progress' => 65,
						'message'  => __( 'Database Imported...', 'everest-backup' ),
						'detail'   =>  __( 'Database imported.', 'everest-backup' ),
					)
				);
			}
		}

		$general_settings     = everest_backup_get_settings( 'general' );
		$delete_after_restore = ! empty( $general_settings['delete_after_restore'] ) ? $general_settings['delete_after_restore'] : 'yes';

		Logs::set_proc_stat(
			array(
				'log'      => 'info',
				'status'   => 'in-process',
				'progress' => 65,
				'message'  => __( 'Restoration almost complete...', 'everest-backup' ),
				'detail'   => 'yes' === $delete_after_restore ? __( 'Uploaded archive file removed', 'everest-backup' ) : __( 'Uploaded archive file kept', 'everest-backup' ),
			)
		);

		/**
		 * Activate themes.
		 */
		Logs::info( 'Activating theme', 'everest-backup' );
		wp_clean_themes_cache();
		switch_theme( $metadata['config']['Stylesheet'] );

		/**
		 * Activate plugins.
		 */
		Logs::info( 'Activating plugins', 'everest-backup' );
		wp_clean_plugins_cache();
		$active_plugins = ! empty( $metadata['config']['ActivePlugins'] ) ? everest_backup_filter_plugin_list( $metadata['config']['ActivePlugins'] ) : array();
		if ( ! empty( $active_plugins ) ) {
			if ( ! empty( $metadata['config']['Database'] ) && ! empty( $metadata['config']['Database']['Tables'] ) ) {
				$current = array();
				foreach ( $active_plugins as $plugin ) {
					$current[] = $plugin;
					sort( $current );
				}
				update_option( 'active_plugins', $current );
			} else {
				$error_in_plugin = array();
				foreach ( $active_plugins as $plugin ) {
					if ( is_plugin_active( $plugin ) ) {
						continue;
					}
					$nonce = wp_rand( 100000000, 999999999 );
					update_option( 'everest_backup_enable_plugin_token', $nonce );
					$response = wp_remote_post(
						add_query_arg(
							array(
								'nonce'  => $nonce,
								'action' => 'everest_backup_activate_plugin',
								'plugin' => $plugin,
							),
							admin_url( 'admin-ajax.php' )
						)
					);
					if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
						$error_in_plugin[] = $plugin;
					}
				}
				delete_option( 'everest_backup_enable_plugin_token' );
				if ( ! empty( $error_in_plugin ) ) {
					Logs::error( 'Following plugins were not activated due to error during their activation: "' . implode( '", "', $error_in_plugin ) . '"' );
				}
			}
		}

		if ( isset( $metadata['config']['NavMenus'] ) ) {
			Logs::info( 'Setting up navigation menus', 'everest-backup' );
			set_theme_mod( 'nav_menu_locations', $metadata['config']['NavMenus'] );
		}

		if ( isset( $metadata['config']['Widgets'] ) ) {
			Logs::info( 'Setting up widgets', 'everest-backup' );
			update_option( 'sidebars_widgets', $metadata['config']['Widgets'] );
		}

		if ( ! empty( $metadata['config']['Plugin']['ActiveAddons'] ) ) {
			activate_plugins( $metadata['config']['Plugin']['ActiveAddons'], '', false, true );
		}

		everest_backup_activate_our_plugins();

		Logs::info( __( 'Flushing cache and clearing temporary files', 'everest-backup' ) );

		self::set_permalinks();

		flush_rewrite_rules();
		everest_backup_elementor_cache_flush();

		if ( empty( $metadata['config']['Database'] ) || ! ( isset( $params['skip_database'] ) && isset( $params['incremental'] ) ) ) {
			Logs::info( __( 'Auth cleared.', 'everest-backup' ) );
			wp_clear_auth_cookie();
		}

		Logs::done( __( 'Restore completed.', 'everest-backup' ) );

		do_action( 'everest_backup_after_restore_done', $metadata );

		if ( get_transient( 'everest_backup_wp_cli_express' ) ) {
			add_filter( 'everest_backup_disable_send_json', '__return_true' );
		}

		everest_backup_send_success();

		die();
	}

	public static function set_permalinks() {
		// Set the general permalink structure to the default value (e.g., /%postname%/)
		$default_structure = '/%postname%/';
		update_option( 'permalink_structure', $default_structure );

		// Set WooCommerce specific permalinks
		$woocommerce_permalinks = array(
			'product_base'           => '/product',
			'category_base'          => '/product-category',
			'tag_base'               => '/product-tag',
		);
		$serialized_woocommerce_permalinks = maybe_serialize( $woocommerce_permalinks );
		update_option( 'woocommerce_permalinks', $serialized_woocommerce_permalinks );
	}
}
