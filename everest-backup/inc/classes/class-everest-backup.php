<?php
/**
 * Main class that initialize everything.
 *
 * @package everest-backup
 */

use Everest_Backup\Backup_Directory;
use Everest_Backup\Logs;
use Everest_Backup\Modules\Cron_Handler;
use Everest_Backup\Proc_Lock;
use Everest_Backup\Temp_Directory;
use Everest_Backup\Traits\Singleton;
use Everest_Backup\Transient;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Everest_Backup' ) ) {

	/**
	 * Main class that initialize everything.
	 *
	 * @since 1.0.0
	 */
	class Everest_Backup {

		use Singleton;

		/**
		 * Init class.
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			do_action( 'everest_backup_init', $this );

			add_action( 'upgrader_process_complete', array( $this, 'on_update' ), 12, 2 );
			register_activation_hook( EVEREST_BACKUP_FILE, array( $this, 'on_activation' ) );
			register_deactivation_hook( EVEREST_BACKUP_FILE, array( $this, 'on_deactivation' ) );

			$this->init_hooks();

			$this->custom_hooks();
		}

		/**
		 * Initialize hooks.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		private function init_hooks() {
			add_action( 'init', array( $this, 'handle_usage_stats' ) );
			add_action( 'admin_init', array( $this, 'on_admin_init' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );

			add_action( 'admin_notices', array( $this, 'print_admin_notices' ) );
			add_action( 'admin_notices', array( $this, 'print_addons_license_notices' ), 10000 );
		}

		/**
		 * Custom hooks.
		 */
		private function custom_hooks() {
			add_action( 'everest_backup_check_values_before_settings_save', array( $this, 'before_settings_save' ), 11 );
		}

		/**
		 * Function for before settings save hook.
		 */
		public function before_settings_save() {
			// @phpcs:disable
			if ( array_key_exists( EVEREST_BACKUP_SETTINGS_KEY, $_POST ) && array_key_exists( 'cloud', $_POST[ EVEREST_BACKUP_SETTINGS_KEY ] ) ) {
				if ( array_key_exists( 'pcloud_keep_recent_n_files', $_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud'] ) ) {
					$_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud']['pcloud_keep_recent_n_files'] = max( 0, (int) $_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud']['pcloud_keep_recent_n_files'] );
				}
				if ( array_key_exists( 'pcloud_remove_x_days_old', $_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud'] ) ) {
					$_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud']['pcloud_remove_x_days_old'] = max( 0, (int) $_POST[ EVEREST_BACKUP_SETTINGS_KEY ]['cloud']['pcloud_remove_x_days_old'] );
				}
			}
			// @phpcs:enable
		}

		/**
		 * Function executes on plugins loaded action hook.
		 */
		public function on_plugins_loaded() {
			do_action( 'everest_backup_loaded', $this );
		}

		/**
		 * Get stats object.
		 */
		private function get_stats_object() {
			if ( ! class_exists( 'EverestThemes_Stats' ) ) {
				require_once EVEREST_BACKUP_PATH . 'inc/stats/class-stats.php';
			}

			return EverestThemes_Stats::get_instance( EVEREST_BACKUP_FILE, 'https://ps.w.org/everest-backup/assets/icon-128X128.gif' );
		}

		/**
		 * Handle usage stats.
		 */
		public function handle_usage_stats() {

			if ( everest_backup_is_test_site() ) {
				return;
			}

			$stats = $this->get_stats_object();
			$post  = everest_backup_get_submitted_data( 'post' );

			if ( ! empty( $post['everest_backup_consent_optin'] ) ) {
				if ( wp_verify_nonce( $post['everest_backup_consent_optin'], 'everest_backup_consent_optin' ) ) {
					update_option( 'everest_backup_consent_optin', 'yes' );
				}
			}

			if ( ! empty( $post['everest_backup_consent_skip'] ) ) {
				if ( wp_verify_nonce( $post['everest_backup_consent_skip'], 'everest_backup_consent_skip' ) ) {
					set_transient( 'everest_backup_consent_skip', 'yes', MONTH_IN_SECONDS );
				}
			}

			if ( 'yes' === get_option( 'everest_backup_consent_optin' ) ) {
				$stats->init();
			}
		}

		/**
		 * On plugin activation.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function on_activation() {
			Temp_Directory::init()->create();
			Backup_Directory::init()->create();

			if ( ! everest_backup_is_test_site() ) {
				$this->get_stats_object()->send();
			}
		}

		/**
		 * On plugin deactivation.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function on_deactivation() {
			Temp_Directory::init()->clean_temp_dir();

			$cron_handler = new Cron_Handler();
			$cron_handler->unschedule_events();
		}

		/**
		 * On update.
		 *
		 * @param \WP_Upgrader $upgrader Upgrader.
		 * @param array        $hook_extra Extra hooks.
		 */
		public function on_update( \WP_Upgrader $upgrader, $hook_extra ) {
			if ( ! empty( $hook_extra['action'] ) && 'update' !== $hook_extra['action'] ) {
				return;
			}

			if ( ! empty( $hook_extra['type'] ) && 'plugin' !== $hook_extra['type'] ) {
				return;
			}

			if ( ! empty( $upgrader->result['destination_name'] ) && pathinfo( EVEREST_BACKUP_FILE, PATHINFO_FILENAME ) !== $upgrader->result['destination_name'] ) {
				return;
			}

			Backup_Directory::init()->create( true );

			if ( ! everest_backup_is_test_site() ) {
				$this->get_stats_object()->send();
			}
		}

		/**
		 * On admin_init hooks.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function on_admin_init() {

			load_plugin_textdomain( 'everest-backup', false, EVEREST_BACKUP_PATH . 'languages' );

			$this->force_reload();
			$this->dismiss_upsell_notice();
			$this->set_debug_mode_ondemand();
			$this->create_litespeed_htacces_files();
			$this->generate_fake_lockfile();
			$this->addons_compatibility_check();
			$this->set_headers();
			$this->force_abort_proc_lock();
			$this->terminate_proc_lock();
			$this->lock_ebwp_plugins();
			$this->activate_addon();
			$this->save_settings();
			$this->remove_backup_file();
			$this->setup_clone_init();
			$this->restore_rollback();
			$this->bulk_remove_logs();
			$this->upload_backup_to_cloud();
		}

		/**
		 * Force reload url.
		 *
		 * @since 2.0.0
		 */
		private function force_reload() {
			$get = everest_backup_get_submitted_data( 'get' );

			if ( ! isset( $get['force_reload'] ) ) {
				return;
			}

			if ( ! everest_backup_is_ebwp_page() ) {
				return;
			}

			if ( wp_safe_redirect( remove_query_arg( 'force_reload' ) ) ) {
				exit;
			}
		}

		/**
		 * Dismiss upsell notice.
		 */
		private function dismiss_upsell_notice() {
			$get = everest_backup_get_submitted_data( 'get' );

			if ( empty( $get['ebwp-upsell-dimiss'] ) ) {
				return;
			}

			if ( ! everest_backup_verify_nonce( '_ebwp-upsell-dimiss-nonce' ) ) {
				return;
			}

			$transient = new Transient( 'upsell_dimiss' );

			$transient->set( true, DAY_IN_SECONDS * 3 ); // Three day expiry.

			if ( wp_safe_redirect( remove_query_arg( array( 'ebwp-upsell-dimiss', '_ebwp-upsell-dimiss-nonce' ) ) ) ) {
				exit;
			}
		}

		/**
		 * Sets debug mode on or off on demand using query args value.
		 *
		 * @return void
		 * @since 1.1.6
		 */
		private function set_debug_mode_ondemand() {

			if ( ! everest_backup_is_ebwp_page() ) {
				return;
			}

			$submitted_data = everest_backup_get_submitted_data( 'get' );

			if ( empty( $submitted_data['debug'] ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			return Backup_Directory::init()->force_debug( 'on' === $submitted_data['debug'] );
		}

		/**
		 * Create .htaccess file for litespeed.
		 *
		 * @return void
		 * @since 1.1.4
		 */
		private function create_litespeed_htacces_files() {

			if ( ! extension_loaded( 'litespeed' ) ) {
				return;
			}

			insert_with_markers(
				EVEREST_BACKUP_HTACCESS_PATH,
				'LiteSpeed',
				array(
					'<IfModule Litespeed>',
					'SetEnv noabort 1',
					'</IfModule>',
				)
			);
		}

		/**
		 * Generates fake lockfile.
		 *
		 * @return void
		 * @since 1.1.1
		 */
		private function generate_fake_lockfile() {
			if ( ! everest_backup_is_debug_on() ) {
				return;
			}

			$get = everest_backup_get_submitted_data( 'get' );

			if ( empty( $get['lockfile'] ) ) {
				return;
			}

			if ( 'generate' !== $get['lockfile'] ) {
				return;
			}

			if ( empty( $get['_noncefakelockfile'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $get['_noncefakelockfile'], 'fakelockfile-' . get_current_user_id() ) ) {
				return;
			}

			$lockfile_time = ( time() - EVEREST_BACKUP_LOCKFILE_STALE_THRESHOLD ) - HOUR_IN_SECONDS;

			Proc_Lock::set( 'debug', $lockfile_time );  // Create fake stale lockfile.
		}

		/**
		 * Checks addons for version compatibility using `Everest Backup:` in addon header.
		 *
		 * @since 1.1.1
		 */
		private function addons_compatibility_check() {
			$get             = everest_backup_get_submitted_data( 'get' );
			$plugin_root     = WP_PLUGIN_DIR;
			$active_addons   = everest_backup_installed_addons( 'active' );
			$default_headers = array(
				'plugin_name' => 'Plugin Name',
				'eb_version'  => 'Everest Backup',
			);

			if ( is_array( $active_addons ) && ! empty( $active_addons ) ) {
				foreach ( $active_addons as $active_addon ) {
					$plugin_file = "{$plugin_root}/{$active_addon}";

					$data = get_file_data( $plugin_file, $default_headers );

					if ( empty( $data['eb_version'] ) ) {
						continue;
					}

					if ( version_compare( EVEREST_BACKUP_VERSION, $data['eb_version'], '>=' ) ) {
						continue;
					}

					add_action(
						'admin_notices',
						function () use ( $data ) {
							?>
							<div class="notice notice-error is-dismissible">
								<p>
									<?php
									printf(
										/* translators: %1$s is Addon name, %2$s is Everest Backup required version and %3$s is Everest Backup plugin name. */
										esc_html__( '%1$s plugin requires %2$s or later. Please update your existing %3$s plugin to the latest version.', 'everest-backup' ),
										'<strong>' . esc_html( $data['plugin_name'] ) . '</strong>',
										'<strong>Everest Backup ' . esc_html( "v{$data['eb_version']}" ) . '</strong>',
										'<strong>Everest Backup</strong>',
									);
									?>
								</p>
							</div>
							<?php
						}
					);

					if ( isset( $get['activate'] ) ) {
						unset( $get['activate'] );
					}

					deactivate_plugins( plugin_basename( $plugin_file ) );

				}
			}
		}

		/**
		 * Set PHP Headers.
		 *
		 * @return void
		 */
		private function set_headers() {
			if ( ! wp_doing_ajax() || ! everest_backup_is_ebwp_page() ) {
				return;
			}

			if ( extension_loaded( 'litespeed' ) ) {
				header( 'X-LiteSpeed-Cache-Control:no-cache', true );
			}
		}

		/**
		 * Remove plugin actions if we are doing the process.
		 */
		private function lock_ebwp_plugins() {

			$basenames = array();

			$basenames[] = plugin_basename( EVEREST_BACKUP_FILE );

			$basenames = array_merge( $basenames, everest_backup_installed_addons( 'active' ) );

			if ( is_array( $basenames ) && ! empty( $basenames ) ) {
				foreach ( $basenames as $basename ) {
					$hook = is_multisite() ? "network_admin_plugin_action_links_{$basename}" : "plugin_action_links_{$basename}";

					/**
					 * Filters the action links displayed for each plugin in the Plugins list table.
					 *
					 * @param string[] $actions     An array of plugin action links. By default this can include
					 *                              'activate', 'deactivate', and 'delete'. With Multisite active
					 *                              this can also include 'network_active' and 'network_only' items.
					 */
					add_filter(
						$hook,
						function ( $actions ) {
							$proc_lock = Proc_Lock::get();

							if ( empty( $proc_lock ) ) {
								return $actions;
							}

							return array(
								'ebwp_in_process' => '<img width="20" src="' . esc_url( EVEREST_BACKUP_URL . 'assets/images/ebwp-loading.gif' ) . '">',
							);
						},
						12
					);
				}
			}
		}

		/**
		 * Forcefully abort stale proc lock.
		 *
		 * @return void
		 * @since 1.1.1
		 */
		private function force_abort_proc_lock() {
			$get = everest_backup_get_submitted_data( 'get' );

			if ( empty( $get['force-abort'] ) ) {
				return;
			}

			if ( empty( $get['_wpnonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( $get['_wpnonce'] ) ) {
				return;
			}

			$proc_lock = Proc_Lock::get();

			if ( ! empty( $proc_lock['uid'] ) ) {
				/**
				 * Delete incomplete backup file if exists.
				 */
				$content = @file_get_contents( everest_backup_current_request_storage_path( 'ebwp-config.json' ) );
				if ( ! empty( $content ) ) {
					$config  = $content ? json_decode( $content, true ) : array();
			
					$fileinfo =  isset( $config[ 'FileInfo' ] ) ? $config[ 'FileInfo' ] : false;
	
					if ( $fileinfo && ! empty( $fileinfo['filename'] ) ) {
						$backup_file_path = wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . DIRECTORY_SEPARATOR . $fileinfo['filename'] );
						if ( file_exists( $backup_file_path ) ) {
							@unlink( $backup_file_path );
						}
					}
				}


				/**
				 * Send email notification to user who initiated the process.
				 */

				$user_initiator = get_userdata( $proc_lock['uid'] );
				$user_aborter   = get_userdata( $get['uid'] );

				$to      = $user_initiator->user_email;
				$subject = esc_html__( 'Everest Backup: Force Abort', 'everest-backup' );
				$message = sprintf(
					/* translators: %1$s is Human time difference and %2$s is username. */
					esc_html__( 'Everest Backup process that was running since %1$s has been forcefully aborted by: %2$s', 'everest-backup' ),
					'<strong>' . human_time_diff( $proc_lock['time'] ) . '</strong>',
					'<strong>' . $user_aborter->display_name . '</strong>'
				);

				wp_mail( $to, $subject, $message );

			}

			Proc_Lock::delete();

			if ( wp_safe_redirect( network_admin_url( '/admin.php?page=everest-backup-export' ) ) ) {
				exit;
			}
		}

		/**
		 * Terminate current running process if user reloads the Everest Backup page.
		 *
		 * It is helpful for the scenarios where user starts a process then reloads the page.
		 *
		 * @return void
		 */
		private function terminate_proc_lock() {

			if ( ! everest_backup_is_ebwp_page() ) {
				return;
			}

			$is_reloading = everest_backup_is_reloading();

			if ( $is_reloading ) {
				$user_id   = get_current_user_id();
				$proc_lock = Proc_Lock::get();

				if ( ! isset( $proc_lock['uid'] ) ) {
					return;
				}

				if ( $user_id === $proc_lock['uid'] ) {
					Proc_Lock::delete();
				}
			}
		}

		/**
		 * Activate the selected addon if it is submitted from the Everest backup addon page.
		 *
		 * @return void
		 */
		private function activate_addon() {
			$data = everest_backup_get_submitted_data( 'post' );

			if ( empty( $data['page'] ) ) {
				return;
			}

			if ( 'everest-backup-addons' !== $data['page'] ) {
				return;
			}

			if ( empty( $data['plugin'] ) ) {
				everest_backup_set_notice( __( 'Plugin slug empty.', 'everest-backup' ), 'notice-error' );
				return;
			}

			$activate = everest_backup_activate_ebwp_addon( $data['plugin'] );

			if ( ! is_wp_error( $activate ) ) {
				everest_backup_set_notice( __( 'Addon activated.', 'everest-backup' ), 'notice-success' );
			} else {
				$err_msg = $activate->get_error_message();
				everest_backup_set_notice( $err_msg, 'notice-error' );
			}
		}

		/**
		 * Save settings data.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		private function save_settings() {
			do_action( 'everest_backup_check_values_before_settings_save', array() );
			$settings_data = everest_backup_get_submitted_data( 'post' );

			$submitted_data = ! empty( $settings_data[ EVEREST_BACKUP_SETTINGS_KEY ] ) ? $settings_data[ EVEREST_BACKUP_SETTINGS_KEY ] : array();

			if ( ! $submitted_data ) {
				return;
			}

			if ( ! everest_backup_verify_nonce( EVEREST_BACKUP_SETTINGS_KEY . '_nonce' ) ) {
				everest_backup_set_notice( __( 'Nonce verification failed.', 'everest-backup' ), 'notice-error' );
				return;
			}

			$saved_settings = everest_backup_get_settings();

			$settings = array_merge( $saved_settings, $submitted_data );

			$has_changes = $saved_settings !== $settings; // @since 1.1.2

			do_action( 'everest_backup_before_settings_save', $settings, $has_changes );

			everest_backup_update_settings( $settings );

			do_action( 'everest_backup_after_settings_save', $settings, $has_changes );

			everest_backup_set_notice( __( 'Settings saved.', 'everest-backup' ), 'notice-success' );
		}

		/**
		 * Remove backup file.
		 *
		 * @return void
		 * @since 1.0.0
		 * @since 2.2.1 Fix: https://www.pluginvulnerabilities.com/2023/11/06/wordfences-false-claim-of-vulnerability-in-wordpress-plugin-everest-backup-leads-to-serious-real-vulnerability/
		 */
		private function remove_backup_file() {
			$get = everest_backup_get_submitted_data( 'get' );

			$page = ! empty( $get['page'] ) ? $get['page'] : '';

			if ( 'everest-backup-history' !== $page ) {
				return;
			}

			$bulk_action = isset( $get['action2'] ) ? $get['action2'] : '';
			$cloud       = isset( $get['cloud'] ) ? $get['cloud'] : 'server';

			if ( '-1' === $bulk_action ) {
				return;
			}

			if ( 'server' !== $cloud ) {
				return do_action( 'everest_backup_override_file_remove', $get );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$history_page_url = network_admin_url( "/admin.php?page={$page}" );

			if ( $bulk_action ) {
				/**
				 * If we are here, we are removing files in bulk.
				 */

				$files = isset( $get['remove'] ) ? $get['remove'] : '';

				if ( is_array( $files ) && ! empty( $files ) ) {
					foreach ( $files as $file ) {
						$file_path = everest_backup_get_backup_full_path( basename( $file ), true );

						if ( ! $file_path ) {
							continue;
						}

						if ( ! is_file( $file_path ) ) {
							continue;
						}
						// @phpcs:disable
						unlink( $file_path );
						// @phpcs:enable
					}
				}
			} else {

				$action = ! empty( $get['action'] ) ? $get['action'] : '';
				$file   = ! empty( $get['file'] ) ? $get['file'] : '';

				if ( 'remove' !== $action || empty( $file ) ) {
					return;
				}

				$file_path = everest_backup_get_backup_full_path( basename( $file ), true );

				if ( ! $file_path || ! is_file( $file_path ) ) {
					everest_backup_set_notice(
						'<strong>' . $file . '</strong> ' . __( 'does not exists.', 'everest-backup' ),
						'notice-error'
					);

					$redirect = remove_query_arg( array( 'action', 'file' ), $history_page_url );
					if ( wp_safe_redirect( $redirect ) ) {
						exit;
					}

					return;
				}

				// @phpcs:disable
				if ( unlink( $file_path ) ) {
					// @phpcs:enable
					everest_backup_set_notice(
						'<strong>' . $file . '</strong> ' . __( 'successfully removed from the server.', 'everest-backup' ),
						'notice-success'
					);

					$redirect = remove_query_arg( array( 'action', 'file' ), $history_page_url );
					if ( wp_safe_redirect( $redirect ) ) {
						exit;
					}

					return;
				}

				everest_backup_set_notice(
					__( 'Unable to remove file', 'everest-backup' ) . ' <strong>' . $file . '</strong>',
					'notice-error'
				);
			}

			$redirect = remove_query_arg( array( 'action', 'file' ), $history_page_url );
			if ( wp_safe_redirect( $redirect ) ) {
				exit;
			}
		}

		/**
		 * Setup environment for cloning process.
		 *
		 * @return void
		 * @since 1.0.4
		 */
		private function setup_clone_init() {
			$response = everest_backup_get_submitted_data( 'get', true );

			$page = ! empty( $response['page'] ) ? $response['page'] : '';

			if ( 'everest-backup-migration_clone' !== $page ) {
				return;
			}

			if ( empty( $response['download_url'] ) ) {
				return;
			}

			define( 'EVEREST_BACKUP_DOING_CLONE', true );
			define( 'EVEREST_BACKUP_DOING_ROLLBACK', true );
		}

		/**
		 * Roll back to the previous selected backup version.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		private function restore_rollback() {
			$response = everest_backup_get_submitted_data( 'get', true );

			$page    = ! empty( $response['page'] ) ? $response['page'] : '';
			$action  = ! empty( $response['action'] ) ? $response['action'] : '';
			$_action = ! empty( $response['_action'] ) ? $response['_action'] : '';

			if ( 'everest-backup-import' !== $page ) {
				return;
			}

			if ( ( 'rollback' !== $action ) && ( 'rollback' !== $_action ) ) {
				return;
			}

			define( 'EVEREST_BACKUP_DOING_ROLLBACK', true );
		}

		/**
		 * Start file upload to cloud.
		 */
		private function upload_backup_to_cloud() {
			$response = everest_backup_get_submitted_data( 'get', true );

			$page    = ! empty( $response['page'] ) ? $response['page'] : '';
			$cloud   = ! empty( $response['cloud'] ) ? $response['cloud'] : '';
			$action  = ! empty( $response['action'] ) ? $response['action'] : '';
			$_action = ! empty( $response['_action'] ) ? $response['_action'] : '';

			if ( 'everest-backup-history' !== $page ) {
				return;
			}

			if ( ( 'upload-to-cloud' !== $action ) && ( 'upload-to-cloud' !== $_action ) ) {
				return;
			}

			define( 'EVEREST_BACKUP_UPLOADING_TO_CLOUD', true );
			define( 'EVEREST_BACKUP_UPLOADING_TO', $cloud );
		}

		/**
		 * Remove logs from the database.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		private function bulk_remove_logs() {
			$get = everest_backup_get_submitted_data( 'get' );

			$page = ! empty( $get['page'] ) ? $get['page'] : '';

			if ( 'everest-backup-logs' !== $page ) {
				return;
			}

			if ( isset( $get['clear_all_logs'] ) ) {
				return Logs::delete_all_logs();
			}

			$bulk_action = isset( $get['action2'] ) ? $get['action2'] : '';

			if ( 'remove' === $bulk_action ) {
				$keys = isset( $get['remove'] ) ? $get['remove'] : '';

				Logs::delete( $keys );
			}
		}

		/**
		 * Args for the pluploader.
		 *
		 * @return array
		 * @since 1.0.0
		 */
		private function plupload_args() {

			$action = EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION;
			$nonce  = everest_backup_create_nonce( 'everest_backup_ajax_nonce' );
			$url    = admin_url( "/admin-ajax.php?action={$action}&everest_backup_ajax_nonce={$nonce}" );

			return array(
				'runtimes'         => 'html5',
				'browse_button'    => 'plupload-browse-button',
				'container'        => 'plupload-upload-ui',
				'drop_element'     => 'drag-drop-area',
				'file_data_name'   => 'file',
				'multiple_queues'  => false,
				'multi_selection'  => false,
				'url'              => $url,
				'filters'          => array(
					'mime_types' => array(
						array(
							'title'      => __( 'EBWP File', 'everest-backup' ),
							'extensions' => str_replace( '.', '', EVEREST_BACKUP_BACKUP_FILE_EXTENSION ),
						),
					),
				),
				'multipart'        => true,
				'urlstream_upload' => true,
			);
		}

		/**
		 * Localized data.
		 */
		private function localized_data() {

			$max_upload_size = everest_backup_max_upload_size();

			$addons_page_link = '<a href="' . esc_url( network_admin_url( '/admin.php?page=everest-backup-addons&cat=Upload+Limit' ) ) . '">' . esc_html__( 'Addons', 'everest-backup' ) . '</a>';

			$data = array(
				'_nonce'        => everest_backup_create_nonce( 'everest_backup_ajax_nonce' ),
				'ajaxUrl'       => admin_url( '/admin-ajax.php' ),
				'sseURL'        => everest_backup_get_sse_url(),
				'doingRollback' => everest_backup_doing_rollback(),
				'maxUploadSize' => $max_upload_size,
				'resInterval'   => everest_backup_get_logger_speed(), // In milliseconds, the interval between each ajax responses for restore/backup/clone.
				'fileExtension' => ltrim( EVEREST_BACKUP_BACKUP_FILE_EXTENSION, '.' ),
				'pluploadArgs'  => $this->plupload_args(),
				'locale'        => array(
					/* translators: Here, %1$s is the size limit set by the server and %2$s is link to addons page. */
					'fileSizeExceedMessage' => sprintf( __( 'The file size is larger than %1$s. View %2$s to bypass server upload limit.', 'everest-backup' ), everest_backup_format_size( $max_upload_size ), $addons_page_link ),
					'zipDownloadBtn'        => __( 'Download File', 'everest-backup' ),
					'migrationPageBtn'      => __( 'Generate Migration Key', 'everest-backup' ),
					'initializingBackup'    => __( 'Initializing backup', 'everest-backup' ),
					'backupMessage'         => __( 'Please wait while we are doing the backup. You will get a detailed log after the backup is completed.', 'everest-backup' ),
					'restoreMessage'        => __( 'Restoration is in progress, please do not close this tab or window.', 'everest-backup' ),
					'uploadingPackage'      => __( 'Uploading package...', 'everest-backup' ),
					'packageUploaded'       => __( 'Package uploaded. Click "Restore" to start the restore.', 'everest-backup' ),
					'abortAlert'            => __( 'Are you sure you want to stop this backup process?', 'everest-backup' ),
					'viewLogs'              => __( 'View Logs', 'everest-backup' ),
					'cloudLogos'            => wp_json_encode( apply_filters( 'everest_backup_cloud_icon_text', array() ) ),
					'uploadToCloudURL'      => everest_backup_upload_to_cloud_url(),
					'UploadProcessComplete' => ( ! everest_backup_cloud_get_option( 'manual_backup_continued' ) ) && ( everest_backup_cloud_get_option( 'cloud_upload_error' ) || everest_backup_cloud_get_option( 'finished' ) ),
					'loadingGifURL'         => everest_backup_get_ebwp_loading_gif(),
					'ajaxGetCloudStorage'   => 'everest_backup_cloud_available_storage',
				),
				'adminPages'    => array(
					'dashboard' => network_admin_url(),
					'backup'    => network_admin_url( 'admin.php?page=everest-backup-export' ),
					'import'    => network_admin_url( '/admin.php?page=everest-backup-import' ),
					'history'   => network_admin_url( '/admin.php?page=everest-backup-history' ),
					'logs'      => network_admin_url( '/admin.php?page=everest-backup-logs' ),
					'settings'  => network_admin_url( '/admin.php?page=everest-backup-settings' ),
				),
				'actions'       => array(
					'export'                => EVEREST_BACKUP_EXPORT_ACTION,
					'clone'                 => EVEREST_BACKUP_CLONE_ACTION,
					'import'                => EVEREST_BACKUP_IMPORT_ACTION,
					'uploadPackage'         => EVEREST_BACKUP_UPLOAD_PACKAGE_ACTION,
					'removeUploadedPackage' => EVEREST_BACKUP_REMOVE_UPLOADED_PACKAGE_ACTION,
					'saveUploadedPackage'   => EVEREST_BACKUP_SAVE_UPLOADED_PACKAGE_ACTION,
					'processStatusAction'   => EVEREST_BACKUP_PROCESS_STATUS_ACTION,
				),
			);

			return apply_filters( 'everest_backup_filter_localized_data', $data );
		}

		/**
		 * Prints admin notices.
		 *
		 * @return void
		 */
		public function print_admin_notices() {

			$disabled_functions = everest_backup_is_required_functions_enabled();

			if ( is_array( $disabled_functions ) ) {
				?>
				<div class="notice notice-error" style="margin:0 0 10px; padding: 20px;">
					<h3><?php esc_html_e( 'Warning!', 'everest-backup' ); ?></h3>
					<p style="font-size: 16px"><?php /* translators: */ printf( esc_html__( 'Everest Backup requires these functions to work: %s <br>Please contact your host to enable the mentioned functions.', 'everest-backup' ), '<strong>' . esc_html( implode( ', ', $disabled_functions ) ) . '</strong>' ); ?></p>
				</div>
				<?php
			}

			everest_backup_render_view( 'template-parts/upsells' );

			everest_backup_render_view(
				'template-parts/proc-lock-info',
				array_merge(
					Proc_Lock::get(),
					array(
						'class' => 'notice',
					)
				)
			);
		}

		/**
		 * Load admin scripts.
		 *
		 * @param string $hook Current page slug id.
		 */
		public function admin_scripts( $hook ) {

			if ( false === strstr( $hook, 'everest-backup' ) ) {
				return;
			}

			$version = time(); // To tackle issues caused by cache plugins.

			wp_enqueue_style( 'everest-backup-admin-styles', EVEREST_BACKUP_URL . 'assets/css/admin.css', array(), $version, 'all' );
			wp_enqueue_script( 'everest-backup-index', EVEREST_BACKUP_URL . 'assets/js/index.js', array(), $version, true );

			switch ( $hook ) {
				case 'toplevel_page_everest-backup-export':
					$filetype = 'backup';
					break;

				case 'everest-backup_page_everest-backup-import':
					wp_enqueue_script( 'plupload-all' );

					$filetype = 'restore';
					break;

				case 'everest-backup_page_everest-backup-migration_clone':
					$filetype = 'migration-clone';
					break;

				case 'everest-backup_page_everest-backup-settings':
					$filetype = 'settings';
					break;

				case 'everest-backup_page_everest-backup-addons':
					$filetype = 'addons';
					break;

				case 'everest-backup_page_everest-backup-history':
					$filetype = 'upload-to-cloud';
					break;

				default:
					$filetype = '';
					break;
			}

			if ( ! $filetype ) {
				return;
			}

			if ( 'backup' === $filetype || 'restore' === $filetype || 'migration-clone' === $filetype ) {

				// We don't want heartbeat to occur when importing/exporting.
				wp_deregister_script( 'heartbeat' );

				// We don't want auth check for monitoring whether the user is still logged in.
				remove_action( 'admin_enqueue_scripts', 'wp_auth_check_load' );

			}

			$handle   = "everest-backup-{$filetype}-script";
			$filepath = "assets/js/{$filetype}.js";

			$localized_data = $this->localized_data();

			wp_register_script( $handle, EVEREST_BACKUP_URL . $filepath, array(), $version, true );

			wp_localize_script( $handle, '_everest_backup', $localized_data );

			wp_enqueue_script( $handle );
		}

		/**
		 * Print addons license notices.
		 */
		public function print_addons_license_notices() {
			$plugins = apply_filters( 'everest_backup_inactive_license_addons', array() );
			if ( ! empty( $plugins ) ) {
				?>
				<div class="notice notice-error">
					<p class=""><?php printf( 'Your %s license key is inactive. Please click the link below to activate your license.', implode( ', ', $plugins ) ); ?></p>
		
					<p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=everest-backup-license' ) ); ?>">Activate Your License</a>
					</p>
				</div>
				<?php
			}
		}
	}
}
