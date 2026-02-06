<?php
/**
 * Multisite archive import.
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
class Multisite {

	use Import;

	/**
	 * Run.
	 */
	private static function run( $params ) {

		if ( ! is_multisite() ) {
			self::set_next( 'wrapup' );
		}

		$metadata = self::get_metadata();

		if ( empty( $metadata['config'] ) ) {
			return;
		}

		$config = $metadata['config'];

		if ( $config['WordPress']['Multisite'] ) {
			$message = __( 'Cannot create a subsite from a multisite backup.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
			return;
		}

		Logs::info( __( 'Creating subsite..', 'everest-backup' ) );

		$new_subsite_url = self::get_new_subsite_url( $config['HomeURL'] );

		$blog_id = self::get_blog_id( $new_subsite_url );

		$blog[ $blog_id ]['SubsiteURL']    = $new_subsite_url;
		$blog[ $blog_id ]['ActivePlugins'] = ! empty( $config['Multisites']['ActivePlugins'] ) ? $config['Multisites']['ActivePlugins'] : $config['ActivePlugins'];
		$blog[ $blog_id ]['Stylesheet']    = ! empty( $config['Multisites']['Stylesheet'] ) ? $config['Multisites']['Stylesheet'] : $config['Stylesheet'];
		$blog[ $blog_id ]['Template']      = ! empty( $config['Multisites']['Template'] ) ? $config['Multisites']['Template'] : $config['Template'];

		\everest_backup_set_temp_values_during_backup( array( 'blog' => $blog ) );

		Logs::info( __( 'Subsite created: ' . $new_subsite_url, 'everest-backup' ) );

		self::set_next( 'wrapup' );
	}

	/**
	 * Converts the package url to subsite compatible url for current multisite.
	 *
	 * @param string $package_home_url Home URL of the exported package.
	 * @return string
	 * @since 1.0.0
	 */
	private static function get_new_subsite_url( $package_home_url ) {

		if ( ! $package_home_url ) {
			return;
		}

		$home_url = home_url();

		// Get new subsite domain.
		$new_subsite_domain = $home_url;

		// Get new subsite path.
		$new_subsite_path = wp_parse_url( $home_url, PHP_URL_PATH );

		// Get old blog domain.
		$old_blog_domain = wp_parse_url( $package_home_url, PHP_URL_HOST );

		// Get old blog path.
		$old_blog_path = wp_parse_url( $package_home_url, PHP_URL_PATH );

		// Get new blog domain.
		$new_blog_domain = wp_parse_url( $home_url, PHP_URL_HOST );

		// Get new blog path.
		$new_blog_path = wp_parse_url( $home_url, PHP_URL_PATH );

		// Get old blog domain without www subdomain.
		$old_home_url_www_inversion = wp_parse_url( str_ireplace( '//www.', '//', $package_home_url ), PHP_URL_HOST );

		// Get new blog domain without www subdomain.
		$new_home_url_www_inversion = wp_parse_url( str_ireplace( '//www.', '//', $home_url ), PHP_URL_HOST );

		$old_blog_subdomain = explode( '.', untrailingslashit( $old_home_url_www_inversion ) );

		// Get blog sub domain.
		if ( $old_blog_subdomain ) {

			$old_blog_subdomain = array_filter( $old_blog_subdomain );

			if ( $old_blog_subdomain ) {

				$new_blog_name = array_shift( $old_blog_subdomain );

				if ( $new_blog_name ) {
					if ( is_subdomain_install() ) {
						$new_subsite_domain = sprintf( '%s.%s', strtolower( $new_blog_name ), strtolower( $new_home_url_www_inversion ) );
						$new_subsite_path   = sprintf( '%s', untrailingslashit( $new_blog_path ) );
					} else {
						$new_subsite_domain = sprintf( '%s', strtolower( $new_blog_domain ) );
						$new_subsite_path   = sprintf( '%s/%s', untrailingslashit( $new_blog_path ), strtolower( $new_blog_name ) );
					}
				}
			}
		}

		// Get blog sub path.
		$old_blog_subpath = explode( '/', untrailingslashit( $old_blog_path ) );

		if ( $old_blog_subpath ) {

			$old_blog_subpath = array_filter( $old_blog_subpath );

			if ( $old_blog_subpath ) {

				$new_blog_name = array_pop( $old_blog_subpath );

				if ( $new_blog_name ) {

					if ( is_subdomain_install() ) {
						$new_subsite_domain = sprintf( '%s.%s', strtolower( $new_blog_name ), strtolower( $new_home_url_www_inversion ) );
						$new_subsite_path   = sprintf( '%s', untrailingslashit( $new_blog_path ) );
					} else {
						$new_subsite_domain = sprintf( '%s', strtolower( $new_blog_domain ) );
						$new_subsite_path   = sprintf( '%s/%s', untrailingslashit( $new_blog_path ), strtolower( $new_blog_name ) );
					}
				}
			}
		}

		$new_subsite_url = null;

		// Set subsite scheme.
		$new_subsite_scheme = wp_parse_url( $home_url, PHP_URL_SCHEME );
		if ( $new_subsite_scheme ) {
			$new_subsite_url .= "{$new_subsite_scheme}://";
		}

		// Set subsite domain.
		$new_subsite_url .= $new_subsite_domain;

		// Set subsite port.
		$new_subsite_port = wp_parse_url( $home_url, PHP_URL_PORT );
		if ( $new_subsite_port ) {
			$new_subsite_url .= ":{$new_subsite_port}";
		}

		// Set subsite path.
		$new_subsite_url .= $new_subsite_path;

		return trim( $new_subsite_url );
	}

	/**
	 * Returns blog id if domain already exists otherwise creates the subsite then returns its blog id.
	 *
	 * @param string $new_subsite_url Subsite url converted from package home url.
	 * @return int
	 * @since 1.0.0
	 */
	private static function get_blog_id( $new_subsite_url ) {

		if ( ! $new_subsite_url ) {
			return;
		}

		// Get blog domain.
		$domain = wp_parse_url( $new_subsite_url, PHP_URL_HOST );
		$post   = wp_parse_url( $new_subsite_url, PHP_URL_PORT );
		if ( $post ) {
			$domain .= ":{$post}";
		}

		// Get blog path.
		$path = wp_parse_url( $new_subsite_url, PHP_URL_PATH );

		if ( domain_exists( $domain, $path ) ) {
			/* translators: %s is the subsite url or domain name. */
			Logs::info( sprintf( __( 'Domain %s already exists.', 'everest-backup' ), $new_subsite_url ) );

			$blog_id = get_blog_id_from_url( $domain, trailingslashit( $path ) );
		} else {
			/* translators: %s is the subsite url or domain name. */
			Logs::info( sprintf( __( 'Domain %s does not exist. Creating a new subsite.', 'everest-backup' ), $new_subsite_url ) );

			$data = wp_normalize_site_data(
				array(
					'domain' => $domain,
					'path'   => $path,
				)
			);

			$blog_id = wp_insert_site( $data );
		}

		$id = isset( $blog_id ) && is_int( $blog_id ) ? $blog_id : 0;

		if ( ! $id ) {
			$message = __( 'Failed to get the blog id.', 'everest-backup' );
			Logs::error( $message );
			everest_backup_send_error( $message );
		}

		return $id;
	}
}
