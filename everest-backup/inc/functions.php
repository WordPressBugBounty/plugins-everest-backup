<?php
/**
 * Functions and definitions for Everest Backup plugin.
 *
 * @package everest-backup
 */

use Everest_Backup\Backup_Directory;
use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Modules\Cloner;
use Everest_Backup\Transient;
use Everest_Backup\Proc_Lock;
use Everest_Backup\Temp_Directory;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the backup location by cloud key.
 *
 * @param string $cloud_key Cloud key.
 * @param string $context [`display` or `cloud`] If the backup location is being displayed or is being used for logic. Options: `display` or `cloud`.
 * @param bool   $always_return Always return.
 * @return string
 */
function everest_backup_get_cloud_backup_location( $cloud_key, $context = 'display', $always_return = true ) {

	if ( ! $cloud_key ) {
		return '';
	}

	$settings = everest_backup_get_settings();

	$cloud_settings = isset( $settings['cloud'][ $cloud_key ] ) ? $settings['cloud'][ $cloud_key ] : array();

	$backup_location = ! empty( $cloud_settings['backup_location'] ) ? trim( $cloud_settings['backup_location'] ) : '';

	if ( 'cloud' === $context ) {
		return $backup_location;
	}

	if ( ! $always_return ) {
		if ( ! $backup_location ) {
			return '';
		}
	}

	$cloud_root_folder = untrailingslashit( pathinfo( EVEREST_BACKUP_BACKUP_DIR_PATH, PATHINFO_BASENAME ) );

	return untrailingslashit( "$cloud_key://$cloud_root_folder/$backup_location" );
}

/**
 * Download the file from remote server using cURL method.
 * Replacement for `everest_backup_chunk_download_file` since 2.1.5.
 * Also the fix for issue: #221
 *
 * @param string $source       Source Path/URL to the file you want to download.
 * @param string $destination  Destination path to save the file.
 * @param string $args         Additional arguments.
 * @return int|false
 *
 * @throws \Exception Too slow server, cannot download file.
 *
 * @since 2.1.5
 */
function everest_backup_download_file( $source, $destination, $args = false ) {
	if ( everest_backup_is_localhost() ) {
		$timeout    = 300;
		$range_size = 2 * MB_IN_BYTES;
	} else {
		$timeout    = 20;
		$range_size = 20 * MB_IN_BYTES;
	}
	$error    = false;
	$complete = false;
	$write    = true;
	$success  = false;

	// @phpcs:disable
	$args       = (array) $args;
	$seek       = ! empty( $args['seek'] ) ? $args['seek'] : 0;
	$fo_mode    = 'ab';
	if (array_key_exists('seek', $args) && 0 === $args['seek'] ) {
		$fo_mode = 'wb';
		if (file_exists( $destination)) {
			unlink( $destination);
		}
	}

	$local_file = fopen( $destination, $fo_mode);

	if (false === $local_file) {
		return false;
	}

	// Initialize cURL session.
	$ch = curl_init();

	// set file range for download.
	$seek_to = $seek + $range_size;
	$range   = $seek . '-' . $seek_to;

	curl_setopt( $ch, CURLOPT_RANGE, $range);

	// Set cURL options.
	curl_setopt( $ch, CURLOPT_URL, $source);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout);

	// Disable SSL certificate verification
	curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false);

	// Execute the cURL request.
	$response = curl_exec( $ch);

	$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE);

	if (206 === $http_code) {
		$success = true;
	} elseif (416 === $http_code) {
		$success = true;
		// complete
		$complete = true;

		/**
		 * Do not write to file on HTTP Code: 416.
		 * Means File already downloaded completely.
		 * Writing will add extra at the end causing errors.
		 */
		$write = false;
	} elseif (200 === $http_code) {
		$success = true;
		// complete
		$complete = true;
	} else {
		// error
		$error = 1;
	}
	if (false === $response) {
		// file download error.
		$error = 2;
	} elseif ('' === $response) {
		// file download complete.
		$complete = true;
	}

	$downloaded   = $error ? $seek : $seek_to;
	$size         = $args['size'];
	$download_url = $args['download_url'];
	$progress     = ( $downloaded / $size) * 100;
	$progress     = ( $progress > 100) ? 100 : $progress;

	// Check for curl errors (at this stage it is usually timeout error).
	if (curl_errno( $ch)) {
		$error = 3;
	}

	if ( $success || (!$error && $write)) {
		fwrite( $local_file, $response); // write to file if no error.
	}

	if (!$success && $error) {
		$retry = get_transient('everest_backup_migrate_clone_download_retry');
		$retry = $retry ? ( $retry + 1) : 1;
		if ( $retry > 3) {
			delete_transient('everest_backup_migrate_clone_download_retry');
			$set_proc_array = array(
				'status'       => 'in-process',
				'progress'     => round( $progress, 2),
				'message'      => sprintf(
					/* translators: */
					__('Downloading failure. Please try again later.', 'everest-backup') . ' Error: ' . $error,
				),
				/* translators: */
				'detail'       => sprintf( __( 'Download failure.', 'everest-backup' ), esc_html( $retry ), esc_html( everest_backup_format_size( $size ) ) ),
				'download_url' => $download_url,
				'size'         => $size,
				'seek'         => $downloaded,
				'next'         => 'check', // Set next to same.
			);
			Logs::error(esc_html__('Download failed. Please try again later.', 'everest-backup') . ' Error with response code: ' . $http_code);
			// Close cURL and the local file and delete local file.
			curl_close( $ch );
			fclose( $local_file );
			@unlink( $destination );

			everest_backup_send_error();
			die;
		} else {
			set_transient('everest_backup_migrate_clone_download_retry', $retry);
			$set_proc_array = array(
				'status'       => 'in-process',
				'progress'     => round( $progress, 2),
				'message'      => sprintf(
					/* translators: */
					__('Download failure', 'everest-backup'),
				),
				/* translators: */
				'detail'       => sprintf(__('Download failure. Retrying(%1$s)', 'everest-backup'), esc_html( $retry)),
				'download_url' => $download_url,
				'size'         => $size,
				'seek'         => $downloaded,
				'next'         => 'check', // Set next to same.
			);
		}
		$set_proc_array = array_merge( $args, $set_proc_array);
		Logs::set_proc_stat( $set_proc_array);
	} elseif (!$complete) {
		set_transient('everest_backup_migrate_clone_download_retry', 1);
		$set_proc_array = array(
			'status'       => 'in-process',
			'progress'     => round( $progress, 2),
			'message'      => sprintf(
				/* translators: */
				__('Downloading file [ %1$s / %2$s ] : %3$d%% completed', 'everest-backup'),
				esc_html(esc_html(everest_backup_format_size( $seek + strlen( $response)))),
				esc_html(esc_html(everest_backup_format_size( $size))),
				esc_html( $progress)
			),
			/* translators: */
			'detail'       => sprintf(__('Downloaded: %1$s out of %2$s', 'everest-backup'), esc_html(everest_backup_format_size( $seek + strlen( $response))), esc_html(everest_backup_format_size( $size))),
			'download_url' => $download_url,
			'size'         => $size,
			'seek'         => $downloaded + 1,
			'next'         => 'check', // Set next to same.
		);
		$set_proc_array = array_merge( $args, $set_proc_array);
		Logs::set_proc_stat( $set_proc_array);
	}

	// Close cURL and the local file.
	curl_close( $ch );
	fclose( $local_file );
	// @phpcs:enable

	if (!$complete) {
		set_transient('everest_backup_migrate_clone_download', true);
		die();
	}

	delete_transient('everest_backup_migrate_clone_download');
	delete_transient('everest_backup_migrate_clone_download_retry');
	return file_exists( $destination) ? filesize( $destination) : 0;
}

/**
 * Check if current site is test or demo site.
 *
 * @return bool
 * @since 2.1.3
 */
function everest_backup_is_test_site() {

	$homeurl = home_url();

	foreach ( array( 'instawp', 'tastewp' ) as $needle ) {
		if ( false !== strpos( $homeurl, $needle ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns true if all required functions are enabled else returns array of disabled functions.
 *
 * @return array|true
 */
function everest_backup_is_required_functions_enabled() {
	$required_functions = apply_filters(
		'everest_backup_required_functions',
		array(
			'fopen',
			'unpack',
			'gzopen',
			'ini_set',
			'hex2bin',
			'curl_init',
			'set_error_handler',
			'ignore_user_abort',
			'register_shutdown_function',
		)
	);

	$disabled_functions = array();

	if ( is_array( $required_functions ) && ! empty( $required_functions ) ) {
		foreach ( $required_functions as $required_function ) {
			if ( ! everest_backup_is_php_function_enabled( $required_function ) || ! function_exists( $required_function ) ) {
				$disabled_functions[] = $required_function;
			}
		}
	}

	return $disabled_functions ? $disabled_functions : true;
}

/**
 * Determine if file is Everest Backup gzip.
 *
 * @param string $filename File name.
 * @return string
 * @since 2.1.1
 */
function everest_backup_is_gzip( $filename ) {

	// @phpcs:disable
	$handle = fopen( $filename, 'r');
	$bytes = fread( $handle, 3); // Read the first 3 bytes.
	fclose( $handle);
	// @phpcs:enable

	$signature = '1F8B08'; // Gzip signature.

	return 0 === strpos( $bytes, hex2bin( $signature ) );
}

/**
 * Returns SSE URL conditionally.
 *
 * @return string
 * @since 2.0.0
 */
function everest_backup_get_sse_url() {
	return admin_url( '/admin-ajax.php?action=everest_process_status' );
}

/**
 * Get logger speed.
 *
 * @return string
 */
function everest_backup_get_logger_speed() {
	static $speed;

	if ( ! $speed ) {
		$proc_lock = Proc_Lock::get();
		if ( ! empty( $proc_lock['logger_speed'] ) ) {
			$speed = absint( $proc_lock['logger_speed'] );
		} else {
			$general = everest_backup_get_settings( 'general' );
			$speed   = ! empty( $general['logger_speed'] ) ? absint( $general['logger_speed'] ) : 200;
		}
	}

	return $speed;
}

/**
 * Is gzip enabled.
 *
 * @return string
 */
function everest_backup_is_gzip_lib_enabled() {
	if ( ! function_exists( 'gzopen' ) ) {
		return false;
	}

	return everest_backup_is_php_function_enabled( 'gzopen' );
}

/**
 * Returns current request ID.
 *
 * @return string
 * @since 2.0.0
 */
function everest_backup_current_request_id() {

	static $id;

	if ( ! $id ) {
		$proc_lock = Proc_Lock::get();
		$id        = ! empty( $proc_lock['request_id'] ) ? sanitize_text_field( $proc_lock['request_id'] ) : wp_create_nonce( uniqid() . microtime() );
	}

	return $id;
}

/**
 * Returns current timestamp.
 *
 * @return string
 * @since 2.0.0
 */
function everest_backup_current_request_timestamp() {

	static $timestamp;

	if ( ! $timestamp ) {
		$proc_lock = Proc_Lock::get();
		$timestamp = ! empty( $proc_lock['time'] ) ? absint( $proc_lock['time'] ) : time();
	}

	return $timestamp;
}

/**
 * Returns path to current request storage directory.
 *
 * @param string $to To.
 * @return string
 */
function everest_backup_current_request_storage_path( $to = '' ) {

	static $path;

	if ( ! $path ) {
		$path = untrailingslashit( Temp_Directory::init()->join_path( everest_backup_current_request_id() ) );
		if ( ! is_dir( $path ) ) {
			Filesystem::init()->mkdir_p( $path );
		}
	}

	return wp_normalize_path( trailingslashit( $path ) . $to );
}

/**
 * Returns changelog as array.
 *
 * @return array
 * @since 1.1.7
 */
function everest_backup_parsed_changelogs() {

	$transient = new Transient( 'changelogs' );

	$changelogs = $transient->get();

	if ( $changelogs ) {
		return $changelogs;
	}

	if ( ! $changelogs ) {
		$changelogs = array();
	}

	$tags_matches = array();

	preg_match_all(
		"/(a href\=\")([^\?\"]*)(\")/i", // @phpcs:ignore
		wp_remote_retrieve_body(
			wp_remote_get(
				set_url_scheme( 'http://plugins.svn.wordpress.org/everest-backup/tags' ),
				array(
					'sslverify' => false,
				)
			)
		),
		$tags_matches
	);

	$version_dates = array();

	if ( ! empty( $tags_matches[2] ) ) {
		if ( is_array( $tags_matches[2] ) && ! empty( $tags_matches[2] ) ) {
			foreach ( $tags_matches[2] as $maybe_dir ) {

				if ( ! absint( $maybe_dir[0] ) ) {
					continue;
				}

				$version_response = wp_remote_get(
					set_url_scheme( "http://plugins.svn.wordpress.org/everest-backup/tags/{$maybe_dir}readme.txt" ),
					array(
						'sslverify' => false,
					)
				);

				$version_dates[ 'v' . str_replace( '/', '', $maybe_dir ) ] = wp_remote_retrieve_header( $version_response, 'last-modified' );
			}
		}
	}

	$response = wp_remote_get(
		set_url_scheme( 'https://plugins.svn.wordpress.org/everest-backup/trunk/changelog.txt' ),
		array(
			'sslverify' => false,
		)
	);

	$body     = wp_remote_retrieve_body( $response );
	$body     = str_replace( "\r\n", "\n", $body );
	$contents = $body ? explode( "\n", $body ) : array();

	if ( ! isset( $contents[0] ) ) {
		$contents = Filesystem::init()->get_file_content( EVEREST_BACKUP_PATH . 'changelog.txt', 'array' );
	}

	unset( $contents[0] );

	$contents = array_values( array_filter( array_map( 'trim', $contents ) ) );

	$version = '';

	if ( is_array( $contents ) && ! empty( $contents ) ) {
		foreach ( $contents as $content ) {
			preg_match( '/=([^=]+)=/', $content, $match );

			if ( ! empty( $match[1] ) ) {
				$version = trim( $match[1] );
			} else {
				$changelogs[ $version ]['release_date'] = ! empty( $version_dates[ $version ] ) ? $version_dates[ $version ] : '';
				$changelogs[ $version ]['changes'][]    = $content;
			}
		}
	}

	$transient->set( $changelogs, DAY_IN_SECONDS );

	return $changelogs;
}

/**
 * Returns last key of array.
 *
 * @param array $_array Array.
 * @return string|int|null
 * @since 1.0.4
 */
function everest_backup_array_key_last( $_array ) {
	if ( ! $_array || ! is_array( $_array ) ) {
		return null;
	}

	if ( function_exists( 'array_key_last' ) ) {
		return array_key_last( $_array );
	} else {
		end( $_array );
		return key( $_array );
	}
}

/**
 * Filter out excluded plugins from plugins list.
 *
 * @param any $plugin_lists Plugin list.
 * @return array
 * @since 1.1.4
 */
function everest_backup_filter_plugin_list( $plugin_lists ) {

	if ( ! $plugin_lists || ! is_array( $plugin_lists ) ) {
		return array();
	}

	$excluded = array();

	if ( ! is_ssl() || everest_backup_is_localhost() ) {
		// SSL related plugins.
		$excluded[] = 'really-simple-ssl/rlrsssl-really-simple-ssl.php';
		$excluded[] = 'wordpress-https/wordpress-https.php';
		$excluded[] = 'wp-force-ssl/wp-force-ssl.php';
		$excluded[] = 'force-https-littlebizzy/force-https.php';
	}

	$excluded[] = 'invisible-recaptcha/invisible-recaptcha.php';
	$excluded[] = 'wps-hide-login/wps-hide-login.php';
	$excluded[] = 'hide-my-wp/index.php';
	$excluded[] = 'hide-my-wordpress/index.php';
	$excluded[] = 'mycustomwidget/my_custom_widget.php';
	$excluded[] = 'lockdown-wp-admin/lockdown-wp-admin.php';
	$excluded[] = 'rename-wp-login/rename-wp-login.php';
	$excluded[] = 'wp-simple-firewall/icwp-wpsf.php';
	$excluded[] = 'join-my-multisite/joinmymultisite.php';
	$excluded[] = 'multisite-clone-duplicator/multisite-clone-duplicator.php';
	$excluded[] = 'wordpress-mu-domain-mapping/domain_mapping.php';
	$excluded[] = 'wordpress-starter/siteground-wizard.php';
	$excluded[] = 'pro-sites/pro-sites.php';
	$excluded[] = 'wpide/WPide.php';
	$excluded[] = 'page-optimize/page-optimize.php';
	$excluded[] = 'wp-hide-security-enhancer/wp-hide.php'; // @since 2.0.0
	$excluded[] = 'security-ninja/security-ninja.php';
	$excluded[] = 'security-malware-firewall/security-malware-firewall.php';

	return array_diff( $plugin_lists, $excluded );
}


/**
 * Directly clone and restore package using provided migration key.
 *
 * @param string $migration_key Migration Key.
 * @requires AJAX
 * @since 1.1.4
 */
function everest_backup_direct_clone( $migration_key ) {
	if ( ! $migration_key ) {
		return;
	}

	$_REQUEST['verify_key']           = true;
	$_REQUEST['migration_key']        = $migration_key;
	$_REQUEST['ebwp_migration_nonce'] = everest_backup_create_nonce( 'ebwp_migration_nonce' );

	$cloner = new Cloner();
	$cloner->handle_migration_key();

	$key_info = $cloner->get_key_info();

	if ( ! $key_info ) {
		return;
	}

	$_REQUEST = array(
		'action'                    => EVEREST_BACKUP_IMPORT_ACTION,
		'everest_backup_ajax_nonce' => everest_backup_create_nonce( 'everest_backup_ajax_nonce' ),
		'ebwp_migration_nonce'      => everest_backup_create_nonce( 'ebwp_migration_nonce' ),
		'page'                      => 'everest-backup-migration_clone',
		'file'                      => $key_info['name'],
		'size'                      => $key_info['size'],
		'download_url'              => $key_info['url'],
		'cloud'                     => 'server',
	);

	define( 'EVEREST_BACKUP_DOING_CLONE', true );
	define( 'EVEREST_BACKUP_DOING_ROLLBACK', true );

	do_action( 'wp_ajax_' . EVEREST_BACKUP_IMPORT_ACTION );
}

if ( ! function_exists( 'everest_backup_compress_init' ) ) {

	/**
	 * Pluggable function to initialize backup/compression.
	 *
	 * @param array $args [Optional] Arguments for compress.
	 * @since 1.1.2
	 */
	function everest_backup_compress_init( $args = array() ) {

		$_args = wp_parse_args(
			$args,
			array(
				'type'              => 'backup',
				'params'            => array(),
				'disable_send_json' => false,
			)
		);
		$params = ! empty( $_args['params'] ) ? $_args['params'] : everest_backup_get_ajax_response( EVEREST_BACKUP_EXPORT_ACTION );

		if ( empty( $params['next'] ) ) {
			Logs::init( $_args['type'] );
		}

		if ( true === $_args['disable_send_json'] ) {
			add_filter( 'everest_backup_disable_send_json', '__return_true' );
		}

		$_REQUEST['t']                         = time();
		$_REQUEST['action']                    = EVEREST_BACKUP_EXPORT_ACTION;
		$_REQUEST['everest_backup_ajax_nonce'] = everest_backup_create_nonce( 'everest_backup_ajax_nonce' );

		do_action( 'wp_ajax_' . EVEREST_BACKUP_EXPORT_ACTION, $params );

		$res = json_decode(@file_get_contents(EVEREST_BACKUP_PROC_STAT_PATH), true); // @phpcs:ignore

		if ( empty( $res['status'] ) ) {
			return;
		}

		if ( 'in-process' === $res['status'] ) {
			if ( 'database' === $res['next'] ) {
				everest_backup_export_wp_database();
				$res['next'] = 'plugins';
			}
			$_args['params'] = $res;
			return everest_backup_compress_init( $_args );
		}
	}
}

/**
 * Wrapper for disk_free_space.
 *
 * @param string $directory A directory of the filesystem or disk partition.
 * @return float|false
 * @since 1.0.9
 */
function everest_backup_disk_free_space( $directory ) {
	if ( everest_backup_is_php_function_enabled( 'disk_free_space' ) ) {
		return disk_free_space( $directory); // @phpcs:ignore
	}

	return false;
}

/**
 * Checks disk free space.
 *
 * @param string $directory A directory of the filesystem or disk partition.
 * @param string $size Size to check in directory.
 * @param string $log_warning Log warning.
 * @return boolean
 * @since 1.0.9
 */
function everest_backup_is_space_available( $directory, $size, $log_warning = true ) {
	$enabled = everest_backup_is_php_function_enabled( 'disk_free_space' );

	if ( $enabled ) {
		return everest_backup_disk_free_space( $directory ) > $size;
	} else {

		if ( $log_warning ) {
			Logs::warn( __( 'Disk free space function is disabled by hosting.', 'everest-backup' ) );
			Logs::warn( __( 'Using dummy file to check free space (it can take some time).', 'everest-backup' ) );
		}

		return Filesystem::init()->custom_check_free_space( $directory, $size );
	}
}

/**
 * Check if php function is enabled or disabled in 'disable_functions' list.
 *
 * @param string $function_name Function name.
 * @return bool
 * @since 1.1.4
 */
function everest_backup_is_php_function_enabled( $function_name ) {
	$disabled = explode( ',', ini_get( 'disable_functions' ) );
	return ! in_array( $function_name, array_map( 'trim', $disabled ), true );
}

/**
 * Returns true if any process related to Everest Backup is running.
 *
 * @return bool
 * @since 1.0.7
 */
function everest_backup_is_process_running() {
	return ! empty( Proc_Lock::get() );
}

/**
 * Formats bytes to human readable size format.
 *
 * @param int $bytes Number of bytes. Note max integer size for integers.
 * @return string|false
 * @since 1.0.7
 * @uses size_format();
 */
function everest_backup_format_size( $bytes ) {
	return size_format( $bytes, 2 );
}

/**
 * Returns the slug of WordPress core theme.
 *
 * @return string
 * @since 1.0.7
 */
function everest_backup_get_fallback_theme() {
	$core_theme = \WP_Theme::get_core_default_theme();

	if ( ! $core_theme ) {
		return;
	}

	return $core_theme->get_stylesheet();
}

/**
 * Checks if current page is being reloaded.
 *
 * @return boolean
 */
function everest_backup_is_reloading() {
	return ! empty( $_SERVER['HTTP_CACHE_CONTROL'] )
		&& ( ( 'no-cache' === $_SERVER['HTTP_CACHE_CONTROL'] ) || ( 'max-age=0' === $_SERVER['HTTP_CACHE_CONTROL'] ) );
}

/**
 * Checks if current page is Everest Backup page.
 *
 * @return boolean
 */
function everest_backup_is_ebwp_page() {
	$get = everest_backup_get_submitted_data( 'get' );

	if ( ! isset( $get['page'] ) ) {
		return;
	}

	if ( false === strstr( $get['page'], 'everest-backup' ) ) {
		return;
	}

	return true;
}

/**
 * Activate Everest Backup related plugin.
 * Wrapper function for `activate_plugin()`.
 *
 * @param string $plugin Path to the plugin file relative to the plugins directory.
 * @return null|\WP_Error
 * @since 1.0.5
 */
function everest_backup_activate_ebwp_addon( $plugin ) {
	return activate_plugin( $plugin, '', is_multisite() );
}

/**
 * Whether or not use fallback archiver library for compression.
 * It also loads fallback archiver class file.
 *
 * @return bool
 * @since 1.0.7
 * @see https://github.com/Ne-Lexa/php-zip
 */
function everest_backup_use_fallback_archiver() {
	$path  = EVEREST_BACKUP_PATH . 'vendor/autoload.php';
	$debug = everest_backup_get_settings( 'debug' );

	/**
	 * If overridden using debug mode.
	 */
	if ( ! empty( $debug['use_fallback_archiver'] ) ) {

		if ( ! class_exists( 'PhpZip\ZipFile' ) ) {
			require $path;
		}

		return true;
	}

	if ( ! class_exists( 'ZipArchive' ) ) {

		if ( ! class_exists( 'PhpZip\ZipFile' ) ) {
			require $path;
		}

		return true;
	}
}

/**
 * Returns loads and returns nelexa zip instance.
 *
 * @return \PhpZip\ZipFile
 */
function everest_backup_get_nelexa_zip() {
	require_once EVEREST_BACKUP_PATH . 'vendor/autoload.php';
	return new \PhpZip\ZipFile();
}

/**
 * Returns archiver object.
 *
 * @return array
 * @since 1.1.4
 */
function everest_backup_get_archiver() {
	if ( ! everest_backup_use_fallback_archiver() ) {
		return array(
			'type' => 'ziparchive',
			'lib'  => new \ZipArchive(),
		);
	}

	return array(
		'type' => 'fallback_archiver',
		'lib'  => new \PhpZip\ZipFile(),
	);
}

/**
 * Compare php version.
 *
 * @param string  $new_version New or current php version.
 * @param string  $old_version Old php version.
 * @param string  $operator The possible operators are: <, lt, <=, le, >, gt, >=, ge, ==, =, eq, !=, <>, ne respectively.
 * @param boolean $only_minor Whether to only check minor versions.
 * @return int|bool
 * @since 1.0.4
 */
function everest_backup_version_compare( $new_version, $old_version, $operator, $only_minor = false ) {
	$pos = 1; // Position to indicate what's a major version (x.[x].x.x = 1).

	if ( $only_minor ) {

		// Get parts as array.
		$new = explode( '.', $new_version );
		$old = explode( '.', $old_version );

		// Check if it's a major version update.
		$is_major_update = version_compare( $new[ $pos ], $old[ $pos ], $operator ) || version_compare( intval( $new_version ), intval( $old_version ), $operator );

		// Check if it's a minor update.
		$is_minor_update = ( ! $is_major_update && version_compare( strstr( $new_version, '.' ), strstr( $old_version, '.' ), $operator ) );

		return $is_minor_update;
	}

	return version_compare( $new_version, $old_version, $operator );
}

/**
 * Returns percent of file chunks.
 *
 * @param int $bytes Bytes downloaded.
 * @param int $total_size Total size of file in bytes.
 * @since Everest Backup 1.0.7
 * @return int
 */
function everest_backup_get_download_percent( $bytes, $total_size ) {

	if ( ! $total_size ) {
		return 0;
	}

	static $percent = 0;

	$percent += ( ( $bytes / $total_size ) * 100 );

	return round( $percent, 1 );
}

/**
 * Returns list of everest-backup installed addons according to the filter.
 *
 * @param string $filter Filter addon. Supports `all`, `active`, and `paused`.
 * @return array
 * @since 1.0.0
 */
function everest_backup_installed_addons( $filter = 'all' ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	static $ebwp_addons = array();

	$ebwp_active = array();
	$ebwp_paused = array();

	$prefix = 'everest-backup-';

	if ( ! $ebwp_addons ) {
		wp_cache_delete( 'plugins', 'plugins' );
		$plugins = array_keys( get_plugins() );

		if ( is_array( $plugins ) && ! empty( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				if ( false !== strpos( $plugin, $prefix ) ) {
					$ebwp_addons[] = $plugin;
				}
			}
		}
	}

	if ( 'all' === $filter ) {
		return $ebwp_addons;
	}

	if ( is_array( $ebwp_addons ) && ! empty( $ebwp_addons ) ) {
		foreach ( $ebwp_addons as $ebwp_addon ) {
			if ( is_plugin_active( $ebwp_addon ) ) {
				$ebwp_active[] = $ebwp_addon;
			} else {
				$ebwp_paused[] = $ebwp_addon;
			}
		}
	}

	return 'paused' === $filter ? $ebwp_paused : $ebwp_active;
}


/**
 * Flush Elementor cache
 *
 * @return void
 */
function everest_backup_elementor_cache_flush() {
	delete_post_meta_by_key( '_elementor_css' );
	delete_option( '_elementor_global_css' );
	delete_option( 'elementor-custom-breakpoints-files' );
}

/**
 * Returns an array of excluded folders names.
 *
 * @return array
 * @since 1.1.6
 */
function everest_backup_get_excluded_folders() {
	static $remote_data;

	if ( ! $remote_data ) {
		$url = add_query_arg( 't', time(), EVEREST_BACKUP_EXCLUDED_FOLDERS_JSON_URL ); // For busting cache.

		$res = wp_remote_get(
			$url,
			array(
				'sslverify' => false,
			)
		);

		$json = wp_remote_retrieve_body( $res );

		$remote_data = json_decode( $json, true );
		$remote_data = $remote_data && is_array( $remote_data ) ? $remote_data : array();
	}

	$defaults = array(
		'plugins',
		'themes',
		'upgrade',
		'uploads',
		'ebwp-temps',
		'ebwp-backups',
		'debug.log',
	);

	$folders = array_merge( $defaults, $remote_data );
	$folders = array_values( array_filter( array_unique( array_map( 'trim', $folders ) ) ) );

	return apply_filters( 'everest_backup_filter_excluded_folders', $folders );
}

/**
 * Returns an array of plugins other then Everest Backup and EB addons.
 *
 * @return array
 * @since 2.1.2
 */
function everest_backup_get_other_plugins() {
	$eb   = everest_backup_installed_addons();
	$eb[] = plugin_basename( EVEREST_BACKUP_FILE );

	return array_diff( get_option( 'active_plugins', array() ), $eb );
}

/**
 * Returns an array of addons data.
 *
 * @param string $category Addons category.
 * @return array
 * @since 1.0.1
 */
function everest_backup_fetch_addons( $category = '' ) {

	/**
	 * Cache function internally.
	 */
	static $json;

	if ( ! $json ) {

		if ( ! class_exists( 'Everest_Backup\Transient' ) ) {
			require_once EVEREST_BACKUP_PATH . 'inc/classes/class-transient.php';
		}

		$transient = new Transient( 'fetch_addons' );

		if ( everest_backup_is_debug_on() ) {
			$transient->delete();
		}

		$json = $transient->get();

		if ( ! $json ) {

			$url = add_query_arg( 't', time(), EVEREST_BACKUP_ADDONS_JSON_URL ); // @since 1.1.2 For busting cache.

			$res = wp_remote_get(
				$url,
				array(
					'sslverify' => false,
				)
			);

			$json = wp_remote_retrieve_body( $res );

			if ( ! is_array( json_decode( $json, true ) ) ) {
				return;
			}

			$transient->set( $json, HOUR_IN_SECONDS );
		}
	}

	if ( ! $json ) {
		return;
	}

	$decoded = json_decode( $json, true );

	if ( ! is_array( $decoded ) ) {
		return;
	}

	/**
	 * Hook to filter fetched addons data,
	 *
	 * @since 1.0.2
	 */
	$addons = apply_filters( 'everest_backup_filter_fetch_addons', $decoded );

	$data[ $category ] = ! empty( $addons[ $category ] ) ? $addons[ $category ] : '';

	return array(
		'categories' => array_keys( $addons ),
		'data'       => $category ? $data : $addons,
	);
}

/**
 * Returns the addon information according to its provided category and slug.
 *
 * @param string $category Addon category.
 * @param string $slug     Addon slug.
 * @return array
 * @since 1.0.1
 */
function everest_backup_addon_info( $category, $slug ) {
	$all_addons = everest_backup_fetch_addons( $category );

	$addons = ! empty( $all_addons['data'][ $category ] ) ? $all_addons['data'][ $category ] : '';

	if ( ! $addons ) {
		return;
	}

	$info = ! empty( $addons[ $slug ] ) ? $addons[ $slug ] : '';

	if ( ! is_array( $info ) ) {
		return;
	}

	$ebwp_addons = everest_backup_installed_addons();
	$plugin      = $slug . '/' . $slug . '.php';

	$installed = in_array( $plugin, $ebwp_addons, true );

	$info['plugin']    = $plugin;
	$info['installed'] = $installed;
	$info['active']    = $installed && is_plugin_active( $plugin );

	/**
	 * Hook to filter addon information.
	 *
	 * @param array $info Addon information.
	 * @since 1.0.2
	 */
	$info = apply_filters( 'everest_backup_filter_addon_info', $info, compact( 'category', 'slug' ) );

	return $info;
}

/**
 * Fetch content for upsell from upsell.json
 *
 * @return array
 */
function everest_backup_fetch_upsell() {

	if (!defined('EVEREST_BACKUP_UPSELL_JSON_URL')) {
		return;
	}

	/**
	 * Cache function internally.
	 */
	static $data = array();

	if ( $data) {
		return $data;
	}

	if (!class_exists('Everest_Backup\Transient')) {
		require_once EVEREST_BACKUP_PATH . 'inc/classes/class-transient.php';
	}

	$transient = new Transient('fetch_upsell');

	if (everest_backup_is_debug_on()) {
		$transient->delete();
	}

	$data = $transient->get();

	$url = add_query_arg('t', time(), EVEREST_BACKUP_UPSELL_JSON_URL);

	$json = wp_remote_retrieve_body(
		wp_remote_get(
			$url,
			array(
				'sslverify' => false,
			)
		)
	);

	if (!is_array(json_decode( $json, true))) {
		return;
	}

	$decode = json_decode( $json, true);

	if (!is_array( $decode)) {
		return;
	}

	$fields = array(
		'domain',
		'plugins',
		'themes',
		'date',
	);

	if (everest_backup_is_localhost()) {
		$fields[0] = 'localhost';
	}

	$upsells = array();

	if (is_array( $fields) && ! empty( $fields)) {
		foreach ( $fields as $field) {
			if (! isset( $decode[ $field] )) {
				continue;
			}

			$_data = $decode[ $field];

			switch ( $field) {
				case 'domain':
					$homeurl = str_replace(array('https://', 'http://'), '', home_url());

					if (! empty( $_data[ $homeurl] )) {
						$upsells[] = $_data[ $homeurl];
					}

					break;

				case 'plugins':
					if (!function_exists('is_plugin_active')) {
						require_once ABSPATH . 'wp-admin/includes/plugin.php';
					}

					if (is_array( $_data) && ! empty( $_data)) {
						foreach ( $_data as $plugin_slug => $plugin_upsell) {
							if (is_plugin_active( $plugin_slug)) {
								$upsells[] = $plugin_upsell['active'];
							} else {
								$upsells[] = $plugin_upsell['deactive'];
							}
						}
					}

					break;

				case 'themes':
					$active_theme = get_option('template', '');

					if (! empty( $_data[ $active_theme] )) {
						$upsells[] = $_data[ $active_theme];
					}

					break;

				case 'date':
					$today = strtotime(date('d-m-Y')); // @phpcs:ignore

					if (is_array( $_data) && ! empty( $_data)) {
						foreach ( $_data as $dates) {
							if (strtotime( $dates['from'] ) > $today) {
								continue;
							}

							if ( $today > strtotime( $dates['to'] )) {
								continue;
							}

							$upsells[] = $dates['contents'];
						}
					}

					break;

				case 'localhost':
					$upsells[] = $_data;

					break;
				default:
					break;
			}
		}
	}

	$data = array_values( array_filter( call_user_func_array( 'array_merge', $upsells ) ) );

	$transient->set( $data, DAY_IN_SECONDS );

	return $data;
}

/**
 * Fetch contents for sidebar from sidebar.json
 *
 * @param string $page Current everest backup admin page.
 * @return array
 */
function everest_backup_fetch_sidebar( $page ) {

	/**
	 * Cache function internally.
	 */
	static $json;

	if ( ! $json ) {
		if ( ! class_exists( 'Everest_Backup\Transient' ) ) {
			require_once EVEREST_BACKUP_PATH . 'inc/classes/class-transient.php';
		}

		$transient = new Transient( 'fetch_sidebar' );

		if ( everest_backup_is_debug_on() ) {
			$transient->delete();
		}

		$json = $transient->get();

		if ( ! $json ) {

			$url = add_query_arg( 't', time(), EVEREST_BACKUP_SIDEBAR_JSON_URL ); // @since 1.1.2 For busting cache.

			$res = wp_remote_get(
				$url,
				array(
					'sslverify' => false,
				)
			);

			$json = wp_remote_retrieve_body( $res );

			if ( ! is_array( json_decode( $json, true ) ) ) {
				return;
			}

			$transient->set( $json, HOUR_IN_SECONDS );
		}
	}

	if ( ! $json ) {
		return;
	}

	$decoded = json_decode( $json, true );

	if ( ! is_array( $decoded ) ) {
		return;
	}

	$data = array(
		'links'  => ! empty( $decoded['links'] ) ? $decoded['links'] : array(),
		'global' => ! empty( $decoded['global'] ) ? $decoded['global'] : array(),
		'paged'  => ! empty( $decoded['paged'][ $page ] ) ? $decoded['paged'][ $page ] : array(),
	);

	return $data;
}

/**
 * Logs memory used.
 */
function everest_backup_log_memory_used() {
	if ( everest_backup_is_debug_on() ) {
		/* translators: %s is the memory used value. */
		Logs::info( sprintf( __( 'Memory used: %s', 'everest-backup' ), everest_backup_format_size( memory_get_peak_usage( true ) ) ) );
	}
}

/**
 * Checks if debugging is defined.
 *
 * @return bool
 */
function everest_backup_is_debug_on() {
	$is_enabled = ( file_exists( wp_normalize_path( EVEREST_BACKUP_BACKUP_DIR_PATH . '/DEBUGMODEON' ) ) || ( defined( 'EVEREST_BACKUP_DEBUG' ) && EVEREST_BACKUP_DEBUG ) );

	return apply_filters( 'everest_backup_debug_mode', $is_enabled );
}

/**
 * If current connection is localhost.
 *
 * @return bool
 * @since 1.0.0
 */
function everest_backup_is_localhost() {
	$whitelist   = array( '127.0.0.1', '::1' );
	$remote_addr = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( in_array( $remote_addr, $whitelist, true ) ) {
		return true;
	}
}

/**
 * Add query args to API auth redirect url.
 *
 * @uses add_query_arg
 * @param array $args An associative array of key/value pairs: `array( 'key1' => 'value1', 'key2' => 'value2', )`.
 * @return string Returns new url.
 * @since 1.0.0
 * @since 1.0.8 `client_redirect` is now default query args parameter. No need to pass in $args.
 */
function everest_backup_add_redirect_url_query_arg( $args = array() ) {
	$redirect_url = EVEREST_BACKUP_AUTH_REDIRECT_URL;

	/**
	 * Parse "client_redirect".
	 *
	 * @since 1.0.8
	 */
	$args['client_redirect'] = network_admin_url( '/admin.php?page=everest-backup-settings&tab=cloud' );

	if ( ! empty( $args['cloud'] ) ) {
		$args['client_redirect'] = add_query_arg(
			array(
				'cloud' => $args['cloud'],
			),
			$args['client_redirect'],
		);
	}

	$args['client_redirect'] = rawurlencode( $args['client_redirect'] );

	return add_query_arg( $args, $redirect_url );
}

/**
 * Removes url argument passed as $key from the url.
 *
 * @param string|string[] $key — Query key or keys to remove.
 * @return string  New URL query string.
 * @since
 */
function everest_backup_remove_redirect_url_query_arg( $key ) {
	$redirect_url = EVEREST_BACKUP_AUTH_REDIRECT_URL;

	return remove_query_arg( $key, $redirect_url );
}

/**
 * Recursively sanitize the array values.
 *
 * @param array $data Array data to sanitize.
 * @return array $data Sanitized array data.
 * @since 1.0.0
 */
function everest_backup_sanitize_array( array $data ) {
	foreach ( $data as $key => &$value ) {
		if ( is_array( $value ) ) {
			$value = everest_backup_sanitize_array( $value );
		} else { // @phpcs:ignore
			if ( is_int( $value ) ) {
				$value = (int) $value;
			} elseif ( is_string( $value ) ) {
				$value = sanitize_text_field( wp_unslash( $value ) );
			}
		}
	}

	return $data;
}

/**
 * Returns settings data.
 *
 * @param string $key Array key for the settings data.
 * @return mixed If key is provided or not empty then it returns all the data according to key else it will return all settings data.
 * @since 1.0.0
 * @since 1.1.2 Added `everest_backup_filter_settings` filter hook.
 */
function everest_backup_get_settings( $key = '' ) {

	if ( 'debug' === $key ) {
		if ( ! everest_backup_is_debug_on() ) {
			// Bail if debug mode is off and values being asked for debugging purpose.
			return;
		}
	}

	/**
	 * Filter: everest_backup_filter_settings.
	 *
	 * @since 1.1.2
	 */
	$settings = apply_filters(
		'everest_backup_filter_settings',
		get_option( EVEREST_BACKUP_SETTINGS_KEY, array() )
	);

	if ( $key ) {
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	return $settings;
}

/**
 * Update settings.
 *
 * @param array $settings Settings data to save.
 * @return void
 * @since 1.0.0
 */
function everest_backup_update_settings( $settings ) {
	if ( ! $settings ) {
		return;
	}

	$sanitized_settings = everest_backup_sanitize_array( $settings );

	update_option( EVEREST_BACKUP_SETTINGS_KEY, $sanitized_settings );
}

/**
 * Returns maximum file upload size.
 *
 * @return int If returns 0 then it means "unlimited" or no limit.
 * @since 1.0.0
 */
function everest_backup_max_upload_size() {
	$wp_limit = wp_max_upload_size();

	return (int) apply_filters( 'everest_backup_filter_max_upload_size', $wp_limit );
}

/**
 * Returns cron cycles for schedule backup.
 *
 * @return array
 * @since 1.0.0
 */
function everest_backup_cron_cycles() {
	return apply_filters(
		'everest_backup_filter_cron_cycles',
		array(
			'everest_backup_hourly'  => array(
				'interval' => null, // Disabled.
				'display'  => __( 'Hourly ( PRO )', 'everest-backup' ),
			),
			'everest_backup_daily'   => array(
				'interval' => DAY_IN_SECONDS, // 24 hours.
				'display'  => __( 'Daily', 'everest-backup' ),
			),
			'everest_backup_weekly'  => array(
				'interval' => WEEK_IN_SECONDS, // 1 week.
				'display'  => __( 'Weekly', 'everest-backup' ),
			),
			'everest_backup_monthly' => array(
				'interval' => MONTH_IN_SECONDS, // 1 month.
				'display'  => __( 'Monthly', 'everest-backup' ),
			),
		)
	);
}

/**
 * Returns an array of locations to save the backup packages.
 *
 * @return array
 * @since 1.0.0
 */
function everest_backup_package_locations() {
	return (array) apply_filters(
		'everest_backup_filter_package_locations',
		array(
			'server' => array(
				'label'       => __( 'Local Web Server', 'everest-backup' ),
				'description' => __( 'Save the backup package locally on your host server.', 'everest-backup' ),
				'is_active'   => true,
			),
		)
	);
}

/**
 * Where should the current package be saved.
 * If current operation is Ajax then it returns `Additional Settings` value,
 * If current operation is Cron then it returns `Settings > Schedule Backup` value.
 *
 * @return string
 * @since 1.0.0
 * @since 2.0.0
 * @since 2.1.
 */
function everest_backup_is_saving_to() {
	if ( wp_doing_ajax() ) {
		$response = everest_backup_get_ajax_response( EVEREST_BACKUP_EXPORT_ACTION );
		if ( ! empty( $response['save_to'] ) ) {
			return $response['save_to'];
		}

		// @since 2.0.0
		$configpath = everest_backup_current_request_storage_path( EVEREST_BACKUP_CONFIG_FILENAME );

		if ( file_exists( $configpath ) ) {
			$decode = json_decode(file_get_contents( $configpath ), true); // @phpcs:ignore

			if ( ! empty( $decode['Params']['save_to'] ) ) {
				return $decode['Params']['save_to'];
			}
		}

		return 'server';
	}

	if ( wp_doing_cron() ) {
		$schedule_backup_data = everest_backup_get_settings( 'schedule_backup' );
		return ! empty( $schedule_backup_data['save_to'] ) ? $schedule_backup_data['save_to'] : 'server';
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		if ( ! empty( $_REQUEST['cloud'] ) ) { // @phpcs:ignore
			return sanitize_text_field( wp_unslash( $_REQUEST['cloud'] ) ); // @phpcs:ignore
		}
		$schedule_backup_data = everest_backup_get_settings( 'schedule_backup' );
		return ! empty( $schedule_backup_data['save_to'] ) ? $schedule_backup_data['save_to'] : 'server';
	}
	return 'server';
}

/**
 * Array search. Compatible with multi dimensional arrays.
 *
 * @param array      $array Array data to run the search.
 * @param string|int $field Field to check as key in the array.
 * @param mixed      $values Value respective to the field.
 * @return int|string $key Array key.
 * @since 1.0.0
 */
function everest_backup_array_search( $array, $field, $values ) { // @phpcs:ignore
	if ( is_array( $array ) && ! empty( $array ) ) {
		foreach ( $array as $key => $val ) {
			if ( ! isset( $val[ $field ] ) ) {
				continue;
			}

			if ( is_array( $values ) ) {
				if ( is_array( $values ) && ! empty( $values ) ) {
					foreach ( $values as $value ) {
						if ( $value === $val[ $field ] ) {
							return $key;
						}
					}
				}
			}

			if ( $values === $val[ $field ] ) {
				return $key;
			}
		}
	}
}

/**
 * List of items to exlude from backup. Eg; Plugins, Themes...
 *
 * @return array
 * @since 1.0.0
 * @since 1.0.9 Added `type` to array.
 * @since 1.1.2 Added `ignore_content` for excluding wp-content option.
 */
function everest_backup_get_backup_excludes()
{
	return apply_filters(
		'everest_backup_filter_backup_excludes',
		array(
			'ignore_database' => array(
				'type'        => 'database',
				'label'       => __('Database (Sql)', 'everest-backup'),
				'description' => __('Ignore database', 'everest-backup'),
			),
			'ignore_plugins'  => array(
				'type'        => 'plugins',
				'label'       => __('Plugins (Files)', 'everest-backup'),
				'description' => __('Ignore plugins', 'everest-backup'),
			),
			'ignore_themes'   => array(
				'type'        => 'themes',
				'label'       => __('Themes (Files)', 'everest-backup'),
				'description' => __('Ignore themes', 'everest-backup'),
			),
			'ignore_media'    => array(
				'type'        => 'media',
				'label'       => __('Media (Files)', 'everest-backup'),
				'description' => __('Ignore media', 'everest-backup'),
			),
			'ignore_content'  => array(
				'type'        => 'content',
				'label'       => __('Others (Files)', 'everest-backup'),
				'description' => __('Ignore other files and folders from wp-content folder', 'everest-backup'),
			),
		)
	);
}

/**
 * Generate tags array from params.
 *
 * @param array $params Parameters to manipulate backup modules set during start of backup.
 * @return array
 * @since 2.0.0 Converted from class method to function.
 */
function everest_backup_generate_tags_from_params( $params )
{

	$excludes = everest_backup_get_backup_excludes();

	$tags = array();

	if (is_array( $params) && ! empty( $params)) {
		foreach ( $params as $key => $value) {
			if (! isset( $excludes[ $key] )) {
				continue;
			}

			if (empty( $excludes[ $key]['type'] )) {
				continue;
			}

			if (!absint( $value)) {
				continue;
			}

			$tags[] = $excludes[ $key]['type'];
		}
	}

	return $tags;
}

/**
 * Process types used by logs.
 *
 * @return array
 * @since 1.0.0
 */
function everest_backup_get_process_types()
{
	return apply_filters(
		'everest_backup_filter_process_types',
		array(
			'debug'           => __('Debug', 'everest-backup'), // @since 1.1.1
			'abort'           => __('Abort', 'everest-backup'),
			'backup'          => __('Backup', 'everest-backup'),
			'rollback'        => __('Rollback', 'everest-backup'),
			'restore'         => __('Restore', 'everest-backup'),
			'clone'           => __('Clone', 'everest-backup'),
			'schedule_backup' => __('Schedule Backup', 'everest-backup'),
			'upload_to_cloud' => __('Upload to Cloud', 'everest-backup'),
		)
	);
}

/**
 * Returns true if doing the clone.
 *
 * @return bool
 * @since 1.0.4
 */
function everest_backup_doing_clone()
{
	return defined('EVEREST_BACKUP_DOING_CLONE') && EVEREST_BACKUP_DOING_CLONE;
}

/**
 * Returns true if doing the rollback.
 *
 * @return bool
 * @since 1.0.0
 */
function everest_backup_doing_rollback()
{
	return defined('EVEREST_BACKUP_DOING_ROLLBACK') && EVEREST_BACKUP_DOING_ROLLBACK;
}

/**
 * Returns array of info of provided backup file path.
 *
 * @param string $backup_file Full path to the backup file.
 * @return array
 * @since 1.0.0
 */
function everest_backup_get_backup_file_info( $backup_file )
{

	if (!is_file( $backup_file)) {
		return array();
	}

	return array(
		'filename' => basename( $backup_file),
		'path'     => $backup_file,
		'url'      => everest_backup_convert_file_path_to_url( $backup_file),
		'size'     => filesize( $backup_file),
		'time'     => filemtime( $backup_file),
	);
}

/**
 * Check if file extension is excluded in settings by the user.
 *
 * @param string $file Full path to the backup file.
 * @return bool
 * @since 1.0.0
 */
function everest_backup_is_extension_excluded( $file )
{
	if (!is_file( $file)) {
		return false;
	}

	$general    = everest_backup_get_settings('general');
	$extensions = ! empty( $general['exclude_files_by_extension'] ) ? $general['exclude_files_by_extension'] : '';

	if (!$extensions) {
		return false;
	}

	$excluded = explode(', ', $extensions);

	$extension = pathinfo( $file, PATHINFO_EXTENSION);

	return in_array( $extension, $excluded, true);
}

/**
 * Returns backup file full path according to the passed backup file name.
 *
 * @param string $backup_filename File name of backup package.
 * @param bool   $check If passed true then it checks if file exist or not.
 * @return string
 * @since 1.0.0
 */
function everest_backup_get_backup_full_path( $backup_filename, $check = true )
{

	if (!$backup_filename) {
		return;
	}

	$backup_dir = EVEREST_BACKUP_BACKUP_DIR_PATH;

	$backup_file_path = wp_normalize_path("{$backup_dir}/{$backup_filename}");

	if (!$check) {
		return $backup_file_path;
	}

	return is_file( $backup_file_path) ? $backup_file_path : '';
}

/**
 * Send json to frontend.
 *
 * @param any $data Data to send.
 * @return any
 */
function everest_backup_send_json( $data = null )
{
	if (!apply_filters('everest_backup_disable_send_json', false)) {
		wp_send_json( $data);
	}

	return $data;
}

/**
 * Sends json success response with logs.
 *
 * @param mixed $data Optional. Data to encode as JSON, then print and die. Default null.
 * @param int   $status_code Optional. The HTTP status code to output. Default null.
 * @param int   $options Optional. Options to be passed to json_encode(). Default 0.
 * @return void
 * @since 1.0.0
 */
function everest_backup_send_success( $data = null, $status_code = null, $options = 0 )
{
	/**
	 * Filter hook to disable die and send json from Everest Backup.
	 *
	 * @since 1.1.2
	 */
	$disable_send_json = apply_filters('everest_backup_disable_send_json', false);

	$res  = array();
	$logs = Logs::get();

	$res['logs']   = $logs;
	$res['result'] = $data;

	Logs::set_infostat('backup_status', 'success');

	Logs::save();
	Logs::reset_and_close();

	Logs::set_proc_stat(
		array(
			'status'   => 'done',
			'progress' => 100,
			'data'     => $res,
		)
	);

	/**
	 * As it is possible that "everest_backup_send_success" and "everest_backup_send_error"
	 * could both be trigger in same script run, which can cause mis-matched json error in client slide.
	 * So to avoid that, we are not sending any response from this function if the $logs is empty.
	 */
	if (!$logs) {
		if (!$disable_send_json) {
			die;
		}

		return;
	}

	Proc_Lock::delete();

	do_action('everest_backup_before_send_json');

	Filesystem::init()->delete(EVEREST_BACKUP_TEMP_DIR_PATH, true);

	if (get_transient('everest_backup_wp_cli_express')) {
		delete_transient('everest_backup_wp_cli_express');
	}

	if (!$disable_send_json) {
		wp_send_json_success( $res, $status_code, $options);
	}
}

/**
 * Sends json error response with logs.
 *
 * @param mixed $data Optional. Data to encode as JSON, then print and die. Default null.
 * @param int   $status_code Optional. The HTTP status code to output. Default null.
 * @param int   $options Optional. Options to be passed to json_encode(). Default 0.
 * @return void
 * @since 1.0.0
 */
function everest_backup_send_error( $data = null, $status_code = null, $options = 0 )
{

	/**
	 * Filter hook to disable die and send json from Everest Backup.
	 *
	 * @since 1.1.2
	 */
	$disable_send_json = apply_filters('everest_backup_disable_send_json', false);

	$res  = array();
	$logs = Logs::get();

	$res['logs']   = $logs;
	$res['result'] = $data;

	Logs::set_infostat('backup_status', 'failed');

	Logs::save();
	Logs::reset_and_close();

	Logs::set_proc_stat(
		array(
			'status' => 'error',
			'data'   => $res,
		)
	);

	Temp_Directory::init()->reset();

	if (get_transient('everest_backup_wp_cli_express')) {
		delete_transient('everest_backup_wp_cli_express');
	}

	Proc_Lock::delete();

	do_action('everest_backup_before_send_json');

	Filesystem::init()->delete(EVEREST_BACKUP_TEMP_DIR_PATH, true);

	/**
	 * As it is possible that "everest_backup_send_success" and "everest_backup_send_error"
	 * could both be trigger in same script run, which can cause mis-matched json error in client slide.
	 * So to avoid that, we are not sending any response from this function if the $logs is empty.
	 */
	if (!$logs) {
		if (!$disable_send_json) {
			die;
		}

		return;
	}

	if (!$disable_send_json) {
		wp_send_json_error( $res, $status_code, $options);
	}
}

/**
 * Returns post/get/request data.
 *
 * @param string $type Type of submission request to return.
 * @param bool   $ajax If true, it will return data merged with ajax response.
 * @return array
 * @since 1.0.0
 * @since 1.0.4
 */
function everest_backup_get_submitted_data( $type = 'request', $ajax = false )
{
	$data = array();

	switch ( $type) {
		case 'post':
			$data = $_POST; // @phpcs:ignore
			break;

		case 'get':
			$data = $_GET; // @phpcs:ignore
			break;

		default:
			$data = $_REQUEST; // @phpcs:ignore
			break;
	}

	if ( $ajax && wp_doing_ajax()) {
		$inputstream = file_get_contents('php://input');
		$data_decode = (array) json_decode( $inputstream, true);
		return array_merge( $data, $data_decode);
	}

	return $data;
}

/**
 * Verify ajax nonce and return ajax body response.
 *
 * @param string|array $action Ajax action. If array is provided then the response action will be checked with each array element.
 * @return array body response.
 * @since 1.0.0
 */
function everest_backup_get_ajax_response( $action ) {
	if ( ! wp_doing_ajax() ) {
		return array();
	}

	if ( false === strpos( current_action(), 'everest_backup' ) ) {
		return array();
	}

	if ( ! everest_backup_verify_nonce( 'everest_backup_ajax_nonce' ) ) {
		/* translators: action */
		$message = sprintf( __( 'Nonce verification failed. Action: "%s"', 'everest-backup' ), esc_html( $action ) );
		Logs::error( $message );
		everest_backup_send_error( $message );
	}

	if ( ! current_user_can( 'manage_options' ) && ! get_transient( 'everest_backup_doing_scheduled_backup' ) ) {
		$message = __( 'Permission denied.', 'everest-backup' );
		Logs::error( $message );
		everest_backup_send_error( $message );
	}

	/**
	 * If nonce verified, lets bring things into action. No puns intended.
	 */
	$response = everest_backup_get_submitted_data( 'request', true );

	$res_action = ! empty( $response['action'] ) ? sanitize_text_field( wp_unslash( $response['action'] ) ) : '';

	$is_action_valid = is_array( $action ) ? in_array( $res_action, $action, true ) : ( $res_action === $action );

	if ( ! $is_action_valid && ! get_transient( 'everest_backup_doing_scheduled_backup' ) ) {
		$message = __( 'Invalid action provided.', 'everest-backup' );
		Logs::error( $message );
		Logs::$is_sensitive = true;
		/* translators: expected action and received */
		Logs::error( sprintf( __( 'Expected action: %1$s Received: %2$s', 'everest-backup' ), esc_html( $action ), esc_html( $res_action ) ) );
		Logs::$is_sensitive = false;
		everest_backup_send_error( $message );
	}

	if ( ! isset( $response['cloud'] ) ) {
		$response['cloud'] = 'server';
	}

	return $response;
}

/**
 * Returns admin email set by user or WP admin email as default.
 *
 * @return string
 * @since 1.0.0
 */
function everest_backup_get_admin_email()
{
	$general = everest_backup_get_settings('general');

	return ! empty( $general['admin_email'] ) ? $general['admin_email'] : get_option('admin_email');
}


/**
 * PHP setup environment
 *
 * @return void
 */
function everest_backup_setup_environment()
{

	Backup_Directory::init()->create();

	if (session_id()) {
		session_write_close();
	}

	/**
	 * Increase memory limit.
	 */
	wp_raise_memory_limit();

	/**
	 * Check and create temporary directory.
	 */
	Temp_Directory::init()->create();

	@ignore_user_abort(false); // @phpcs:ignore

	/**
	 * No time limit for script execution.
	 */
	if (everest_backup_is_php_function_enabled('set_time_limit')) {
		@set_time_limit(0); // @phpcs:ignore
		@ini_set('max_execution_time', 0); // @phpcs:ignore
	}

	/**
	 * Set maximum time in seconds a script is allowed to parse input data
	 */
	@ini_set('max_input_time', '-1'); // @phpcs:ignore

	/**
	 * Clean (erase) the output buffer and turn off output buffering
	 */
	if (@ob_get_length()) { // @phpcs:ignore
		@ob_end_clean(); // @phpcs:ignore
	}

	/**
	 * Custom error handler.
	 */
	set_error_handler( // @phpcs:ignore
		function ( $errno, $message, $file, $line) {
			if (!$message) {
				return;
			}

			/* translators: %1$s is the error message, %2$s is the file path and %3$s is the file line number. */
			$error = sprintf(__('%1$s in %2$s on line %3$s'), esc_html( $message), esc_url_raw( $file), absint( $line));

			switch ( $errno) {

				case E_WARNING:
					Logs::warn( $error);
					break;

				case E_USER_WARNING:
					Logs::warn( $error);
					break;

				case E_NOTICE:
					Logs::warn( $error);
					break;

				case E_USER_NOTICE:
					Logs::warn( $error);
					break;

				case E_USER_ERROR:
					Logs::error( $error);
					break;

				default:
					Logs::error( $error);
					break;
			}

			return true;
		}
	);

	/**
	 * Catch errors and save them.
	 */
	register_shutdown_function(
		function () {
			$last_error = error_get_last();

			if ( ! is_array( $last_error ) ) {
				return;
			}

			if (
				in_array( $last_error['type'], array(2, 8, 32, 128, 512, 1024, 8192, 16384), true )
				&& ( false === strpos( $last_error['message'], 'Disk quota exceeded' ) )
			) {
				return;
			}

			/* translators: %1$s is the error message, %2$s is the file path and %3$s is the file line number. */
			$error = sprintf(__('%1$s in %2$s on line %3$s'), esc_html( $last_error['message'] ), esc_url_raw( $last_error['file'] ), absint( $last_error['line'] ));

			Logs::error( $error);

			everest_backup_send_error();
		}
	);
}

/**
 * Check if connection or process has been aborted by the user.
 *
 * @return bool
 * @since 1.0.0
 *
 * @see https://stackoverflow.com/a/16592945
 */
function everest_backup_has_aborted()
{

	if (wp_doing_cron()) {
		return;
	}

	print ' ';
	flush();
	if (ob_get_level() > 0) {
		ob_flush();
	}

	return connection_aborted() === 1;
}

/**
 * Wrapper function for `wp_nonce_field`
 *
 * @param string $action Scalar value to add context to the nonce.
 * @param bool   $referer Optional. Whether to set the referer field for validation. Default true.
 * @return string The token.
 * @since 1.0.0
 */
function everest_backup_nonce_field( $action, $referer = true )
{
	if ( $action) {
		return wp_nonce_field( $action, $action, $referer);
	}
}

/**
 * Wrapper function for `wp_create_nonce`
 *
 * @param string $action Scalar value to add context to the nonce.
 * @return string The token.
 * @since 1.0.0
 */
function everest_backup_create_nonce( $action )
{
	if ( $action) {
		return wp_create_nonce( $action);
	}
}

/**
 * Verify nonce.
 *
 * @param string $action Should give context to what is taking place and be the same when nonce was created.
 *
 * @return bool
 * @since 1.0.0
 */
function everest_backup_verify_nonce( $action )
{

	$nonce = ! empty( $_REQUEST[ $action] ) ? sanitize_text_field(wp_unslash( $_REQUEST[ $action] )) : '';

	if ( $nonce && $action) {
		return wp_verify_nonce( $nonce, $action);
	}
}

/**
 * Returns WordPress uploads directory path.
 *
 * @return string
 * @since 1.0.0
 */
function everest_backup_get_uploads_dir()
{
	$upload_dir = wp_upload_dir(null, false);
	if ( $upload_dir) {
		if (! empty( $upload_dir['basedir'] )) {
			return trailingslashit( $upload_dir['basedir'] );
		}
	}
}

/**
 * Returns WordPress uploads directory url.
 *
 * @return string
 * @since 1.0.0
 */
function everest_backup_get_uploads_url()
{
	$upload_dir = wp_upload_dir(null, false);
	if ( $upload_dir) {
		if (! empty( $upload_dir['baseurl'] )) {
			return trailingslashit( $upload_dir['baseurl'] );
		}
	}
}

/**
 * Converts file paths to url.
 *
 * @param string $file Full path to file.
 * @return string
 * @since 1.0.0
 */
function everest_backup_convert_file_path_to_url( $file )
{

	if (!$file) {
		return $file;
	}

	$abspath = wp_normalize_path(trailingslashit(ABSPATH));
	$homeurl = wp_normalize_path(trailingslashit(site_url()));

	return str_replace( $abspath, $homeurl, $file);
}

/**
 * Get activity log URL.
 *
 * @return string
 */
function everest_backup_get_activity_log_url()
{
	return everest_backup_convert_file_path_to_url(EVEREST_BACKUP_ACTIVITY_PATH) . '?t=' . time();
}

/**
 * Get issue reporter URL.
 *
 * @return string
 */
function everest_backup_get_issue_reporter_url()
{

	if (defined('EVEREST_BACKUP_ISSUE_REPORTER_URL')) {
		return EVEREST_BACKUP_ISSUE_REPORTER_URL;
	}

	$sidebar_json = everest_backup_fetch_sidebar(null);

	return ! empty( $sidebar_json['links']['issue_reporter'] ) ? $sidebar_json['links']['issue_reporter'] : '';
}

/**
 * Get issue reporter data.
 *
 * @return array
 */
function everest_backup_get_issue_reporter_data()
{

	if (!is_user_logged_in()) {
		return;
	}

	$user = wp_get_current_user();

	$display_name_explode = explode(' ', $user->display_name);

	$data = array(
		'user_email' => $user->user_email,
	);

	if (count( $display_name_explode) > 1) {
		$data['first_name'] = $display_name_explode[0];
		$data['last_name']  = $display_name_explode[everest_backup_array_key_last( $display_name_explode)];
	}

	if (!everest_backup_is_localhost()) {
		$data['site_url']      = site_url();
		$data['activity_url']  = everest_backup_get_activity_log_url();
		$data['session_token'] = wp_json_encode(everest_backup_get_current_user_ip_ua());
	}

	$data['ebwp_active_addons'] = wp_json_encode(everest_backup_installed_addons('active'));

	return $data;
}

/**
 * Get current user ip ua.
 *
 * @return array
 */
function everest_backup_get_current_user_ip_ua()
{
	if (!is_user_logged_in()) {
		return;
	}

	$session_token = get_user_meta(get_current_user_id(), 'session_tokens', true);

	if (!$session_token) {
		return;
	}

	$token = $session_token[everest_backup_array_key_last( $session_token)];

	return array(
		'ip' => ! empty( $token['ip'] ) ? $token['ip'] : '',
		'ua' => ! empty( $token['ua'] ) ? $token['ua'] : '',
	);
}

/**
 * Returns contents of WordPress .htaccess file.
 *
 * @return string
 * @since 1.0.0
 */
function everest_backup_get_htaccess()
{
	$htaccess = EVEREST_BACKUP_HTACCESS_PATH;

	if (is_file( $htaccess)) {
		return file_get_contents( $htaccess); // @phpcs:ignore
	}
}

/**
 * Converts strings to hex string.
 *
 * @param string $_string String to convert into hex.
 * @return string
 * @since 1.0.0
 */
function everest_backup_str2hex( $_string )
{
	if (is_string( $_string)) {
		$hexstr = unpack('H*', $_string);
		return array_shift( $hexstr);
	}
}

/**
 * Converts hex string to plain string.
 *
 * @param string $_string Hex string to convert into plain string.
 * @return string
 * @since 1.0.0
 */
function everest_backup_hex2str( $_string )
{
	if (is_string( $_string)) {
		return hex2bin("$_string");
	}
}

/**
 * Locate and render files from view folder.
 *
 * @param string $template Template file name without extension.
 * @param array  $args Arguments that will be passed to the template.
 * @return void
 * @since 1.0.0
 */
function everest_backup_render_view( $template, $args = array() )
{
	$file = wp_normalize_path(EVEREST_BACKUP_VIEWS_DIR . $template . '.php');

	if (!file_exists( $file)) {
		return;
	}

	$args = apply_filters('everest_backup_filter_view_renderer_args', $args, $template);

	everest_backup_print_notice();

	do_action('everest_backup_before_view_rendered', $template, $args);

	load_template( $file, false, $args);

	do_action('everest_backup_after_view_rendered', $template, $args);
}

/**
 * Set one time, dismissable notice.
 *
 * @param string $notice Notice message.
 * @param string $type Notice type.
 *
 * @see https://developer.wordpress.org/reference/hooks/admin_notices/#example for all notice types.
 *
 * @return void
 * @since 1.0.0
 */
function everest_backup_set_notice( $notice, $type )
{ // @phpcs:ignore
	if (!session_id()) {
		session_start(
			array(
				'read_and_close' => true,
			)
		);
	}

	$notices                 = isset( $_SESSION['ebwp_notice'] ) ? everest_backup_sanitize_array( $_SESSION['ebwp_notice'] ) : array(); // @phpcs:ignore
	$_SESSION['ebwp_notice'] = compact('notice', 'type');
}

/**
 * Prints HTML for the package location( Save To ) dropdown.
 *
 * @param array $args Arguments for the dropdown.
 *
 * @type `array $args['package_locations']` [Optional] Package locations array.
 * @type `string $args['name']` HTML name sttribute.
 * @type `string $args['id']` HTML id sttribute.
 * @type `string $args['class']` HTML class sttribute.
 * @type `string $args['selected']`Currently selected item.
 *
 * @return void
 * @since 1.0.0
 */
function everest_backup_package_location_dropdown( $args )
{

	$parsed_args = wp_parse_args(
		$args,
		array(
			'package_locations' => array(),
			'name'              => '',
			'id'                => '',
			'class'             => '',
			'selected'          => '',
		)
	);

	$package_locations = ! empty( $parsed_args['package_locations'] ) ? $parsed_args['package_locations'] : everest_backup_package_locations();
	$name              = $parsed_args['name'];
	$id                = $parsed_args['id'];
	$class             = $parsed_args['class'];
	$selected          = $parsed_args['selected'];

	ob_start();
	?>
	<select name="<?php echo esc_attr( $name); ?>" id="<?php echo esc_attr( $id); ?>" class="<?php echo esc_attr( $class); ?>">
		<?php
		if (is_array( $package_locations) && ! empty( $package_locations)) {
			foreach ( $package_locations as $key => $package_location) {
		?>
				<option <?php
						selected( $selected, $key);
						disabled((false === $package_location['is_active'] ));
						?> value="<?php echo esc_attr( $key); ?>" title="<?php echo esc_attr( $package_location['description'] ); ?>">
					<?php echo esc_html( $package_location['label'] ); ?> (&#8505;)
				</option>
		<?php
			}
		}
		?>
	</select>
	<?php
	$content = ob_get_clean();

	echo wp_kses(
		$content,
		array(
			'select' => array(
				'name'  => array(),
				'id'    => array(),
				'class' => array(),
			),
			'option' => array(
				'selected' => array(),
				'disabled' => array(),
				'value'    => array(),
				'title'    => array(),
			),
		)
	);
}

/**
 * Prints HTML for the backup files dropdown.
 *
 * @param array $args Arguments for the dropdown.
 *
 * @type `string $args['name']` HTML name sttribute.
 * @type `string $args['id']` HTML id sttribute.
 * @type `string $args['class']` HTML class sttribute.
 * @type `string $args['required']` Is dropdown required or not.
 * @type `string $args['selected']` Currently selected item.
 *
 * @return void
 * @since 1.0.0
 */
function everest_backup_backup_files_dropdown( $args ) {
	$parsed_args = wp_parse_args(
		$args,
		array(
			'name'     => '',
			'id'       => '',
			'class'    => '',
			'required' => false,
			'selected' => '',
			'order'    => 'asc',
		)
	);

	$name         = $parsed_args['name'];
	$id           = $parsed_args['id'];
	$class        = $parsed_args['class'];
	$required     = $parsed_args['required'];
	$selected     = $parsed_args['selected'];
	$order        = $parsed_args['order'];
	$backup_files = Backup_Directory::init()->get_backups_by_order( $order );

	$grouped_backups = array();

	if ( is_array( $backup_files ) && ! empty( $backup_files ) ) {
		foreach ( $backup_files as $backup_file ) {
			$file_info  = everest_backup_get_backup_file_info( $backup_file );
			$group_date = wp_date( 'F j, Y', $file_info['time'] );

			$grouped_backups[ $group_date ][] = $file_info;
		}
	}

	ob_start();
	?>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $class ); ?>" required="<?php echo $required ? esc_attr( 'required' ) : ''; ?>">
		<option value=""><?php esc_html_e( '--- Select ---', 'everest-backup' ); ?></option>
		<?php
		if ( is_array( $grouped_backups ) && ! empty( $grouped_backups ) ) {
			foreach ( $grouped_backups as $grouped_backup_date => $grouped_backup ) {
			?>
				<optgroup label="<?php echo esc_attr( $grouped_backup_date ); ?>">
					<?php
					if ( is_array( $grouped_backup ) && ! empty( $grouped_backup ) ) {
						foreach ( $grouped_backup as $backup ) {

							$filename  = $backup['filename'];
							$opt_label = $filename . ' [ ' . wp_date( 'h:i:s A', $backup['time'] ) . ' ]';
							?>
							<option title="<?php echo esc_attr( everest_backup_format_size( $backup['size'] ) ); ?>" <?php selected( $selected, $filename ); ?> value="<?php echo esc_attr( $filename ); ?>"><?php echo esc_html( $opt_label ); ?></option>
							<?php
						}
					}
					?>
				</optgroup>
			<?php
			}
		}
		?>
	</select>
	<?php
	$content = ob_get_clean();

	echo wp_kses(
		$content,
		array(
			'select'   => array(
				'name'     => array(),
				'id'       => array(),
				'class'    => array(),
				'required' => array(),
			),
			'optgroup' => array(
				'label' => array(),
			),
			'option'   => array(
				'selected' => array(),
				'disabled' => array(),
				'value'    => array(),
				'title'    => array(),
			),
		)
	);
}

/**
 * Breadcrumb function for Everest Backup admin pages.
 *
 * @return void
 * @since 1.0.0
 */
function everest_backup_breadcrumb() {
	$get = everest_backup_get_submitted_data( 'get' );

	$root = 'Everest Backup';
	$page = get_admin_page_title();
	$tab  = ! empty( $get['tab'] ) ? str_replace( array( '_', '-' ), ' ', ucwords( $get['tab'], '_' ) ) : '';

	?>
	<div class="everest-backup-breadcrumb" id="eb-breadcrumb">
		<svg xmlns="http://www.w3.org/2000/svg" width="17" viewBox="0 0 52.415 40.759">
			<path id="eb_icon_home" d="M25.511,12.828,8.735,26.645V41.557a1.456,1.456,0,0,0,1.456,1.456l10.2-.026a1.456,1.456,0,0,0,1.449-1.456V32.822a1.456,1.456,0,0,1,1.456-1.456h5.823a1.456,1.456,0,0,1,1.456,1.456v8.7a1.456,1.456,0,0,0,1.456,1.46l10.193.028a1.456,1.456,0,0,0,1.456-1.456V26.635L26.9,12.828A1.109,1.109,0,0,0,25.511,12.828Zm26.5,9.391L44.4,15.949V3.345a1.092,1.092,0,0,0-1.092-1.092h-5.1a1.092,1.092,0,0,0-1.092,1.092V9.952l-8.146-6.7a4.368,4.368,0,0,0-5.55,0L.4,22.219A1.092,1.092,0,0,0,.25,23.757l2.32,2.821a1.092,1.092,0,0,0,1.539.148L25.511,9.1a1.109,1.109,0,0,1,1.392,0l21.4,17.629a1.092,1.092,0,0,0,1.538-.146l2.32-2.821a1.092,1.092,0,0,0-.155-1.54Z" transform="translate(0.001 -2.254)" fill="#000000" />
		</svg>

		<strong><?php echo esc_html( $root ); ?></strong> <span class="breadcrumb-separator">&#187;</span> <?php echo $tab ? wp_kses_post( "<strong>$page</strong> <span class='breadcrumb-separator'>&#187;</span> <small>$tab</small>" ) : wp_kses_post( "<small>$page</small>" ); ?>
	</div>
	<?php
}

/**
 * Prints tooltip html.
 *
 * @param string $tip Tip or description to print.
 * @since 1.0.9
 */
function everest_backup_tooltip( $tip ) {
	?>
	<div class="eb-tooltip">
		<span class="dashicons dashicons-info"></span>
		<span class="eb-tooltiptext"><?php echo wp_kses_post( $tip ); ?></span>
	</div>
	<?php
}

/**
 * Prints tooltip html.
 *
 * @param string $tip Tip or description to print.
 * @since 1.0.9
 */
function everest_backup_premuim_locked_tooltip( $tip ) {
	?>
	<div class="eb-tooltip" style="color: #fcba03 !important">
		<span class="dashicons dashicons-lock"></span>
		<span class="eb-tooltiptext"><?php echo wp_kses_post( $tip ); ?></span>
	</div>
	<?php
}

/**
 * Prints HTML for toggle switch.
 *
 * @param array $args Arguments for the toggle switch.
 * @return void
 * @since 1.0.0
 */
function everest_backup_switch( $args = array() ) {

	static $called = 0;

	$parsed_args = wp_parse_args(
		$args,
		array(
			'id'              => sprintf( 'ebwp-switch-%d', $called ),
			'class'           => '',
			'name'            => '',
			'value_checked'   => 1,
			'value_unchecked' => 0,
			'checked'         => false,
			'label_checked'   => __( 'Enable', 'everest-backup' ),
			'label_unchecked' => __( 'Disable', 'everest-backup' ),
		)
	);

	++$called;

	$id    = $parsed_args['id'];
	$class = trim( sprintf( 'toggle %s', $parsed_args['class'] ) );

	?>
	<div class="toggle-switch" id="toggle-<?php echo esc_attr( $id ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $parsed_args['name'] ); ?>" value="<?php echo esc_attr( $parsed_args['value_unchecked'] ); ?>">
		<input <?php checked( $parsed_args['checked'] ); ?> name="<?php echo esc_attr( $parsed_args['name'] ); ?>" type="checkbox" class="<?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $parsed_args['value_checked'] ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>" data-checked="<?php echo esc_attr( $parsed_args['label_checked'] ); ?>" data-unchecked="<?php echo esc_attr( $parsed_args['label_unchecked'] ); ?>"></label>
	</div>
	<?php
}

/**
 * Print one time, dismissable notice.
 *
 * @return void
 * @since
 */
function everest_backup_print_notice() {
	if ( ! isset( $_SESSION['ebwp_notice'] )) {
		return;
	}

	$notice = everest_backup_sanitize_array( $_SESSION['ebwp_notice'] ); // @phpcs:ignore

	?>
	<div class="notice is-dismissible <?php echo esc_attr( $notice['type'] ); ?>">
		<p><?php echo wp_kses_post( $notice['notice'] ); ?></p>
	</div>
	<?php

	unset( $_SESSION['ebwp_notice'] );
}

/**
 * Replace the first occurrence of a string within a string.
 *
 * @param string $search The value being searched for, otherwise known as the needle.
 * @param string $replace The replacement value that replaces found search values.
 * @param string $subject The string or array being searched and replaced on, otherwise known as the haystack.
 * @return string
 * @since 1.0.0
 */
function everest_backup_str_replace_once( $search, $replace, $subject ) {
	$pos = strpos( $subject, $search );
	if ( false !== $pos ) {
		return substr_replace( $subject, $replace, $pos, strlen( $search ) );
	}
}

/**
 * Get everest backup loading gif image URL.
 */
function everest_backup_get_ebwp_loading_gif()
{
	return esc_url( EVEREST_BACKUP_URL . 'assets/images/ebwp-loading.gif' );
}

/**
 * Returns upload to cloud url.
 *
 * @return string
 */
function everest_backup_upload_to_cloud_url() {
	$admin_url           = network_admin_url( '/admin.php' );
	$upload_to_cloud_url = add_query_arg(
		array(
			'page'    => 'everest-backup-history',
			'action'  => 'upload-to-cloud',
			'_action' => 'upload-to-cloud',
		),
		$admin_url
	);
	return $upload_to_cloud_url;
}

if ( ! function_exists( 'everest_backup_cloud_get_option' ) ) {
	/**
	 * Get option.
	 *
	 * @param string $option Option.
	 * @return mixed Value of option. If not exists, false is return.
	 */
	function everest_backup_cloud_get_option( $option ) {
		global $everest_backup_pcloud_get_option;
		if ( ! array_key_exists( $option, (array) $everest_backup_pcloud_get_option ) ) {
			$everest_backup_pcloud_get_option[ $option ] = get_option( EVEREST_BACKUP_CLOUD_REST_API_PREFIX . $option );
		}
		return $everest_backup_pcloud_get_option[ $option ];
	}
}

if ( ! function_exists( 'everest_backup_cloud_update_option' ) ) {
	/**
	 * Set option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $val    Option value.
	 * @return mixed Value of option. If not exists, false is return.
	 */
	function everest_backup_cloud_update_option( $option, $val )
	{
		$updated = update_option( EVEREST_BACKUP_CLOUD_REST_API_PREFIX . $option, $val, true );
		if ( $updated ) {
			global $everest_backup_pcloud_get_option;
			$everest_backup_pcloud_get_option[ $option ] = $val;
		}
		return $updated;
	}
}

if ( ! function_exists( 'everest_backup_cloud_delete_option' ) ) {
	/**
	 * Delete option.
	 *
	 * @param string $option Option name.
	 * @return mixed True on success, false otherwise.
	 */
	function everest_backup_cloud_delete_option( $option )
	{
		$deleted = delete_option( EVEREST_BACKUP_CLOUD_REST_API_PREFIX . $option );
		if ( $deleted ) {
			global $everest_backup_cloud_get_option;
			unset( $everest_backup_cloud_get_option[ $option ] );
		}
		return $deleted;
	}
}

if ( ! function_exists( 'everest_backup_compare_version' ) ) {
	/**
	 * Delete option.
	 *
	 * @param string $version1 Option name.
	 * @param string $version2 Option name.
	 * @return mixed 0 if equal, 1 if $version1 is greater, -1 if $version2 is greater.
	 */
	function everest_backup_compare_version( $version1, $version2 ) {
		$parts1 = explode( '.', $version1 );
		$parts2 = explode( '.', $version2 );

		$max_length = max( count( $parts1 ), count( $parts2 ) );
		$parts1     = array_pad( $parts1, $max_length, 0 );
		$parts2     = array_pad( $parts2, $max_length, 0 );

		for ( $i = 0; $i < $max_length; $i++) {
			$part1 = intval( $parts1[ $i ] );
			$part2 = intval( $parts2[ $i ] );

			if ( $part1 < $part2 ) {
				return -1;
			} elseif ( $part1 > $part2 ) {
				return 1;
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'everest_backup_activate_our_plugins' ) ) {
	/**
	 * Activate our plugins if exists.
	 */
	function everest_backup_activate_our_plugins() {
		// Get all installed plugins.
		$all_plugins = get_plugins();

		// Get active plugins.
		$active_plugins = get_option( 'active_plugins' );

		// Filter out inactive plugins.
		$inactive_plugins = array_keys( array_diff_key( $all_plugins, array_flip( $active_plugins ) ) );

		$our_plugins = everest_backup_get_temp_values_during_backup( 'our_active_plugins' );

		if ( is_array( $our_plugins ) && is_array( $inactive_plugins ) ) {
			$our_inactive_plugins = array_intersect( $our_plugins, $inactive_plugins );
		}

		if ( ! empty( $our_inactive_plugins ) ) {
			activate_plugins( $our_inactive_plugins );
		}

		delete_option( 'everest_backup_active_plugins' );
	}
}

if ( ! function_exists( 'everest_backup_our_plugin_list' ) ) {
	/**
	 * Array list of all our plugins.
	 *
	 * @return array
	 */
	function everest_backup_our_plugin_list()
	{
		return array(
			'everest-backup/everest-backup.php',
			'everest-backup-pro/everest-backup-pro.php',
			'everest-backup-liteupload/everest-backup-liteupload.php',
			'everest-backup-unlimited/everest-backup-unlimited.php',
			'everest-backup-google-drive/everest-backup-google-drive.php',
			'everest-backup-pcloud/everest-backup-pcloud.php',
			'everest-backup-onedrive/everest-backup-onedrive.php',
			'everest-backup-aws-amazon-s3/everest-backup-aws-amazon-s3.php',
			'everest-backup-backblaze/everest-backup-backblaze.php',
			'everest-backup-dropbox/everest-backup-dropbox.php',
		);
	}
}

if ( ! function_exists( 'everest_backup_set_our_active_plugin_list' ) ) {
	/**
	 * Set active plugins.
	 */
	function everest_backup_set_our_active_plugin_list()
	{
		// Get active plugins.
		$active_plugins = get_option('active_plugins');

		$our_plugins = everest_backup_our_plugin_list();

		$our_active_plugins = array_intersect( $our_plugins, $active_plugins);

		everest_backup_set_temp_values_during_backup( array( 'our_active_plugins' => $our_active_plugins ) );
		return;
	}
}

if ( ! function_exists( 'everest_backup_set_temp_values_during_backup' ) ) {
	/**
	 * Set temp values during WordPress Backup.
	 */
	function everest_backup_set_temp_values_during_backup( $values = array() ) {
		$fs = everest_backup_create_temp_json();
		$existing = json_decode( $fs->get_file_content( EVEREST_BACKUP_TEMP_JSON_PATH ), true );
		return $fs->writefile( EVEREST_BACKUP_TEMP_JSON_PATH, wp_json_encode( array_merge( $existing, $values ) ) );
	}
}

if ( ! function_exists( 'everest_backup_get_temp_values_during_backup' ) ) {
	/**
	 * Set temp values during WordPress Backup.
	 */
	function everest_backup_get_temp_values_during_backup( $key = false ) {
		$fs = everest_backup_create_temp_json();
		$values = json_decode( $fs->get_file_content( EVEREST_BACKUP_TEMP_JSON_PATH ), true );
		if ( $key ) {
			if ( isset( $values[ $key ] ) ) {
				return $values[ $key ];
			} else {
				return array();
			}
		}
		return $values;
	}
}

function everest_backup_create_temp_json() {
	$fs = Filesystem::init();
	if ( ! $fs->is_file( EVEREST_BACKUP_TEMP_JSON_PATH ) ) {
		$fs->mkdir_p( dirname( EVEREST_BACKUP_TEMP_JSON_PATH ) );
		$fs->writefile( EVEREST_BACKUP_TEMP_JSON_PATH, wp_json_encode( array() ) );
	}
	return $fs;
}

if ( ! function_exists( 'everest_backup_unset_rest_properties' ) ) {
	/**
	 * Generate random auth code.
	 */
	function everest_backup_unset_rest_properties()
	{
		everest_backup_cloud_delete_option('current_folder_id');
		everest_backup_cloud_delete_option('file_name');
		everest_backup_cloud_delete_option('auth_code');
		everest_backup_cloud_delete_option('wrap_up');
		everest_backup_cloud_delete_option('current_file_seek');
		everest_backup_cloud_delete_option('upload_id');
		everest_backup_cloud_delete_option('iteration');
		everest_backup_cloud_delete_option('file_upload_retrying');
		everest_backup_cloud_delete_option('file_upload_started');
		everest_backup_cloud_delete_option('cloud_upload_error');
		everest_backup_cloud_delete_option('cloud_upload_error_msg');
	}
}

if ( ! function_exists( 'everest_backup_pro_active' ) ) {
	/**
	 * Check if pro version is active.
	 */
	function everest_backup_pro_active()
	{
		return defined('EVEREST_BACKUP_PRO_PATH');
	}
}

if ( ! function_exists( 'everest_backup_2fa_active' ) ) {
	/**
	 * Check if 2fa is active.
	 */
	function everest_backup_2fa_active()
	{
		if (everest_backup_pro_active()) {
			$settings = everest_backup_get_settings('general');
			if (! empty( $settings['enabled_2fa'] ) && ! empty( $settings['2fa-authentication-email'] )) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'everest_backup_2fa_check_otp' ) ) {
	/**
	 * Check OTP.
	 *
	 * @param string $otp OTP.
	 */
	function everest_backup_2fa_check_otp( $otp)
	{
		if (everest_backup_2fa_active()) {
			$settings = everest_backup_get_settings('general');

			$body     = array(
				'otp'   => $otp,
				'email' => $settings['2fa-authentication-email'],
			);
			$response = wp_remote_post(
				EVEREST_BACKUP_PRO_2FA_VALIDATE_URL,
				array(
					'body' => $body,
				)
			);

			return json_decode(wp_remote_retrieve_body( $response), true);
		}
		return false;
	}
}

if ( ! function_exists( 'everest_backup_2fa_check_recovery_code' ) ) {
	/**
	 * Check Recovery Code.
	 *
	 * @param string $recovery_code Recovery Code.
	 */
	function everest_backup_2fa_check_recovery_code( $recovery_code)
	{
		if (everest_backup_2fa_active()) {
			$settings = everest_backup_get_settings('general');

			$body     = array(
				'recovery_code' => $recovery_code,
				'email'         => $settings['2fa-authentication-email'],
			);
			$response = wp_remote_post(
				EVEREST_BACKUP_PRO_2FA_VERIFY_RECOVERY_CODE_URL,
				array(
					'body' => $body,
				)
			);

			return json_decode(wp_remote_retrieve_body( $response), true);
		}
		return false;
	}
}

if ( ! function_exists( 'everest_backup_export_wp_database' ) ) {
	/**
	 * Create databae backup.
	 *
	 * @param array $tables Tables to export(empty if export entire database).
	 */
	function everest_backup_export_wp_database( $tables = array() ) {
		$tables = (is_array( $tables) && ! empty( $tables)) ? $tables : array();

		// Ensure only authorized users can access this function.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get the raw MySQLi connection.
		$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

		if ( $mysqli->connect_error ) {
			return new WP_Error('Connection error: ' . $mysqli->connect_error);
		}

		// Array of all database field types which just take numbers.
		$numtypes = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double', 'decimal', 'real');

		// Get all of the tables if not provided.
		if (empty( $tables)) {
			$result = $mysqli->query('SHOW TABLES');
			while ( $row = $result->fetch_array(MYSQLI_NUM)) {
				$tables[] = $row[0];
			}
		}

		$path    = everest_backup_current_request_storage_path( EVEREST_BACKUP_CONFIG_FILENAME );
		$content = @file_get_contents( $path );
		$config  = $content ? json_decode( $content, true ) : array();

		if ( ! $config ) {
			$config = array();
		}

		$config['Database']['Tables'] = $tables;

		if ( ! Temp_Directory::init()->add_to_temp( $path, wp_json_encode( $config ), false ) ) {
			$error = __( 'Could not write tables to config file.', 'everest-backup' );
			Logs::error( $error );
			everest_backup_send_error();
			die;
		}

		$backup_path = everest_backup_current_request_storage_path( 'ebwp-database' );
		if ( ! is_dir( $backup_path ) ) {
			mkdir( $backup_path, 0777, true );
		}

		// Cycle through the tables.
		foreach ( $tables as $table) {
			// Script Variables.
			$filename    = $backup_path . DIRECTORY_SEPARATOR . $table . '.sql';
	
			// Open file for writing.
			$handle = fopen( $filename, 'w+' );
			if ( ! $handle ) {
				return new WP_Error( 'Error creating database backup. Please check the directory permissions.' );
			}
	
			// Start transaction.
			fwrite( $handle, "START TRANSACTION;\n\n");

			// Table structure.
			$result = $mysqli->query("SHOW CREATE TABLE $table");
			if ( ! $result ) {
				continue; // Skip this table if there's an error.
			}
			$row2 = $result->fetch_assoc();

			// Drop table if exists.
			fwrite( $handle, "DROP TABLE IF EXISTS $table;\n");
			fwrite( $handle, "\n\n" . $row2['Create Table'] . ";\n\n");
			unset( $row2);

			$query = sprintf( "SELECT * FROM %s", $table );
			$result = $mysqli->query( $query, MYSQLI_USE_RESULT);

			fwrite( $handle, 'INSERT INTO ' . $table . ' VALUES ');

			$i = 0;
			while ( $row = $result->fetch_assoc() ) {
				$row_values = [];
				foreach ( $row as $field => $value) {
					if (isset( $value)) {
						if (is_numeric( $value)) {
							$row_values[] = $value;
						} else {
							$row_values[] = "'" . $mysqli->real_escape_string( $value ) . "'";
						}
					} else {
						$row_values[] = 'NULL';
					}
				}
				fwrite( $handle, ('(' . implode(', ', $row_values) . "),\n"));
				++$i;
			}
			if ( $i > 0) {
				fseek( $handle, -2, SEEK_END); // Move the pointer back to overwrite the character
				fwrite( $handle, ";\n");
			} else {
				fseek( $handle, strlen('INSERT INTO ' . $table . ' VALUES ') * -1, SEEK_END);
			}

			$result->free_result();
			unset( $result);

			fwrite( $handle, "\n\n-- ------------------------------------------------ \n\n");

			// Commit transaction.
			fwrite( $handle, "COMMIT;\n" );

			fclose( $handle);
			$path = everest_backup_current_request_storage_path( 'ebwp-files.ebwplist' );
			Temp_Directory::init()->add_to_temp( $path, $filename . "\n", true );
		}

		// Script Variables.
		$nowtimename = time();
		$filename    = $backup_path . DIRECTORY_SEPARATOR . 'triggers-' . $nowtimename . '.sql';

		// Open file for writing.
		$handle = fopen( $filename, 'w');
		// Export triggers.
		$triggers_result = $mysqli->query("SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()");
		while ( $trigger = $triggers_result->fetch_assoc()) {
			$trigger_stmt = "CREATE TRIGGER {$trigger['TRIGGER_NAME']} {$trigger['ACTION_TIMING']} {$trigger['EVENT_MANIPULATION']} ON {$trigger['EVENT_OBJECT_TABLE']} FOR EACH ROW {$trigger['ACTION_STATEMENT']}";
			fwrite( $handle, "\n\n" . $trigger_stmt . ";\n\n");
		}

		// Commit transaction.
		fwrite( $handle, "COMMIT;\n");

		fclose( $handle );
		$mysqli->close();
		return true;
	}
}
