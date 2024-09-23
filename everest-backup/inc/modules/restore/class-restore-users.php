<?php
/**
 * Restore class for users management, especially for multisite restoration.
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
 * Restore class for users management, especially for multisite restoration.
 *
 * @since 1.0.0
 */
class Restore_Users {

	use Restore;

	/**
	 * Init restore.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	protected static function restore() {

		// @phpcs:disable

		if ( ! is_multisite() ) {
			return;
		}

		Logs::set_proc_stat(
			array(
				'status'   => 'in-process',
				'progress' => 40,
				'message'  => __( 'Restoring subsite users', 'everest-backup' ),
			)
		);

		global $wpdb;

		$users_table    = $wpdb->prefix . 'users';
		$usermeta_table = $wpdb->prefix . 'usermeta';

		$ms_blogs = self::$extract->get_temp_data( 'ms_blogs' );

		if ( is_array( $ms_blogs ) && ! empty( $ms_blogs ) ) {
			foreach ( $ms_blogs as $ms_blog_id => $ms_blog ) {
				switch_to_blog( $ms_blog_id );

				$blog_users_table    = $wpdb->prefix . 'users';
				$blog_usermeta_table = $wpdb->prefix . 'usermeta';

				restore_current_blog();

				$users = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %1s;', $blog_users_table ), ARRAY_A );

				$user_ids = array();

				/**
				 * First insert old users to new site.
				 */
				if ( is_array( $users ) && ! empty( $users ) ) {
					foreach ( $users as $index => $user ) {
						if ( ! username_exists( $user['user_login'] ) && ! email_exists( $user['user_email'] ) ) {
							$user_inserted = $wpdb->insert(
								$users_table,
								array(
									'user_login'          => $user['user_login'],
									'user_pass'           => $user['user_pass'],
									'user_nicename'       => $user['user_nicename'],
									'user_email'          => $user['user_email'],
									'user_url'            => $user['user_url'],
									'user_registered'     => $user['user_registered'],
									'user_activation_key' => $user['user_activation_key'],
									'user_status'         => $user['user_status'],
									'display_name'        => $user['display_name'],
								)
							);

							if ( is_int( $user_inserted ) ) {
								$user_ids[ $index ]['Old'] = $user['ID'];
								$user_ids[ $index ]['New'] = $wpdb->insert_id;
							}
						} else {
							$userdata = get_user_by( 'email', $user['user_email'] );

							$user_ids[ $index ]['Old'] = $user['ID'];
							$user_ids[ $index ]['New'] = isset( $userdata->ID ) ? $userdata->ID : 1;
						}
					}
				}

				/**
				 * Then insert usermeta according to the new user id.
				 */
				if ( is_array( $user_ids ) && ! empty( $user_ids ) ) {
					foreach ( $user_ids as $user_id ) {

						$old_usermetas = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %1s WHERE user_id = %d;', $blog_usermeta_table, $user_id['Old'] ), ARRAY_A );

						if ( is_array( $old_usermetas ) && ! empty( $old_usermetas ) ) {
							foreach ( $old_usermetas as $old_usermeta ) {

								$wpdb->insert(
									$usermeta_table,
									array(
										'user_id'    => $user_id['New'],
										'meta_key'   => $old_usermeta['meta_key'],
										'meta_value' => $old_usermeta['meta_value'],
									)
								);
							}
						}
					}
				}

				/**
				 * Finally, delete the unnecessary user and usermeta tables from current subsite.
				 */
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %1s;', $blog_users_table ) );
				$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %1s;', $blog_usermeta_table ) );
			}
		}

		everest_backup_log_memory_used();

		// @phpcs:enable
	}
}
