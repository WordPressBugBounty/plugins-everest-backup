<?php
/**
 * Logs the process.
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
 * Class to log the process.
 *
 * @since 1.0.0
 */
class Logs {

	/**
	 * Warning: It must be set to false again after sensitive information has been logged:
	 * Whether or not we are going to log sensitive informations.
	 * If passed true then log won't be saved in activity log file.
	 *
	 * @var boolean
	 * @since 1.0.5
	 */
	public static $is_sensitive = false;

	/**
	 * Whether or not logging is closed.
	 *
	 * @var boolean
	 * @since 1.0.0
	 */
	protected static $is_closed = false;

	/**
	 * Temporary process logs.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected static $logs = array();

	/**
	 * Option key for the option table.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected static $logs_key = EVEREST_BACKUP_LOGS_KEY;

	/**
	 * Temporary information and details. Its values won't be displayed in logs directly.
	 *
	 * @var array
	 * @since 1.1.2
	 */
	protected static $infostat = array();

	protected static $last_log;

	private static function fs_infostat_path() {
		static $path;

		if ( ! $path ) {
			$path = everest_backup_current_request_storage_path( 'ebwp-infostat.json' );

			if ( ! file_exists( $path ) ) {
				@touch( $path );
			}
		}

		return $path;
	}

	private static function fs_infostat_get() {

		if ( ! self::fs_infostat_path() ) {
			return array();
		}

		$infostat = json_decode( @file_get_contents( self::fs_infostat_path() ), true );

		if ( ! $infostat ) {
			return array();
		}

		return $infostat;
	}

	private static function fs_infostat_set( $key, $info ) {

		if ( ! self::fs_infostat_path() ) {
			return false;
		}

		self::$infostat = self::get_infostat();

		self::$infostat[ $key ] = $info;

		return @file_put_contents( self::fs_infostat_path(), wp_json_encode( self::$infostat ) );
	}

	/**
	 * Sets infostat.
	 *
	 * @param string $key Key for infostat.
	 * @param mixed  $info Information value for infostat.
	 * @return void
	 * @since 1.1.2
	 */
	public static function set_infostat( string $key, $info ) {
		self::fs_infostat_set( $key, $info );
	}

	/**
	 * Returns infostat. It is better to use this method at the end of the current running process.
	 *
	 * @param string $key Key for infostat.
	 * @param mixed  $default Default value to return if $key provided and $key not exists or empty.
	 * @return mixed
	 * @since 1.1.2
	 */
	public static function get_infostat( $key = null, $default = null ) {

		if ( empty( self::$infostat ) ) {
			self::$infostat = self::fs_infostat_get();
		}

		if ( is_null( $key ) ) {
			return self::$infostat;
		}

		return ! empty( self::$infostat[ $key ] ) ? self::$infostat[ $key ] : $default;
	}

	/**
	 * Returns path to current process log file.
	 *
	 * @return string
	 */
	public static function get_path() {

		static $path;

		if ( ! $path ) {
			$path = everest_backup_current_request_storage_path( 'ebwp-logs.json' );

			if ( ! file_exists( $path ) ) {
				touch( $path );
			}
		}

		return $path;
	}

	/**
	 * Returns logs from current process log file.
	 *
	 * @return array
	 */
	private static function fs_get() {
		$logs = json_decode( @file_get_contents( self::get_path() ), true );

		if ( ! $logs ) {
			return array();
		}

		return $logs;
	}

	/**
	 * Sets logs to current process log file.
	 *
	 * @return bool
	 */
	private static function fs_set( $data ) {
		if ( empty( self::$logs ) ) {
			self::$logs = self::fs_get();
		}

		self::$logs[] = $data;

		return @file_put_contents( self::get_path(), wp_json_encode( self::$logs ) );
	}

	private static $last_timer = 0;

	/**
	 * Set process status in PROCSTAT path.
	 *
	 * @param array     $procstat Process status.
	 * @param int|float $sleep If greater than 0, it will sleep for `$sleep` seconds. Default: 1 second.
	 * @return void
	 * @since 1.0.7
	 */
	public static function set_proc_stat( $procstat, $sleep = 0 ) {

		if ( ! $procstat ) {
			return;
		}

		if ( empty( $procstat['next'] ) ) {
			$timenow   = time() * 1000; // Milliseconds.
			$threshold = everest_backup_get_logger_speed();

			if ( isset( $procstat['detail'] ) ) {
				if ( $threshold > ( $timenow - self::$last_timer ) ) {
					return;
				}
			}

			self::$last_timer = $timenow;
		}

		$sep = ' ~ ';

		$message  = isset( $procstat['message'] ) ? $procstat['message'] : __( 'Waiting for response', 'everest-backup' );
		$detail   = isset( $procstat['detail'] ) ? $procstat['detail'] : $message; // Lets atleast send something to the client.
		$datetime = '[' . date( 'h:i:s' ) . ']' . $sep;
		$explode  = explode( $sep, $detail );

		$procstat['hash']   = md5( wp_json_encode( $procstat ) );
		$procstat['detail'] = $datetime . trim( $explode[ everest_backup_array_key_last( $explode ) ] );

		if ( ! empty( $procstat['log'] ) ) {
			if ( in_array( $procstat['log'], array( 'error', 'info', 'warn' ) ) ) {
				call_user_func( array( __CLASS__, $procstat['log'] ), $message );
			}
		}

		return Filesystem::init()->writefile( EVEREST_BACKUP_PROC_STAT_PATH, wp_json_encode( $procstat ) );
	}

	/**
	 * Get process status data.
	 *
	 * @return array
	 * @since 1.0.7
	 */
	public static function get_proc_stat() {
		$file = EVEREST_BACKUP_PROC_STAT_PATH;

		if ( ! file_exists( $file ) ) {
			return array();
		}

		$log = @file_get_contents( $file );

		return $log ? json_decode( $log, true ) : array();
	}

	/**
	 * Returns process type from logs array.
	 * If there are more than one logs in logs array, it will return data for first key.
	 *
	 * @param array $logs Logs array.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_process_type( $logs ) {

		$process_types = everest_backup_get_process_types();

		$key = everest_backup_array_search( $logs, 'init', array_keys( $process_types ) );

		if ( ! empty( $logs[ $key ]['init'] ) ) {
			return array(
				'key'  => $key,
				'type' => $logs[ $key ]['init'],
			);
		}

		$key = everest_backup_array_search( $logs, 'type', array_keys( $process_types ) );

		if ( ! empty( $logs[ $key ]['type'] ) ) {
			return array(
				'key'  => $key,
				'type' => $logs[ $key ]['type'],
			);
		}
	}

	/**
	 * Save collected logs in the option table. This method must be called before `Everest_Backup\Logs::clear` method.
	 *
	 * @database
	 * @return void
	 * @since 1.0.0
	 */
	public static function save() {

		$time = time();
		$logs = self::retrive();

		$temp_logs = self::get();

		if ( ! $temp_logs ) {
			return;
		}

		$process_types = self::get_process_type( $temp_logs );

		$key  = ! empty( $process_types['key'] ) ? $process_types['key'] : '';
		$type = ! empty( $process_types['type'] ) ? $process_types['type'] : '';

		$logs[ $time ] = array();

		$logs[ $time ]['time'] = $time;
		$logs[ $time ]['type'] = $type;

		if ( $type ) {
			unset( $temp_logs[ $key ] );
		}

		$logs[ $time ]['logs'] = array_values( $temp_logs );

		self::set_infostat( 'log_key', $time );

		$args = array(
			'infostat' => self::get_infostat(),
			'logs'     => $logs,
		);

		do_action( 'everest_backup_before_logs_save', $temp_logs, $args );

		update_option( self::$logs_key, $logs );

		do_action( 'everest_backup_after_logs_save', $temp_logs, $args );
	}

	/**
	 * Appends content to the activity.txt file in backup directory.
	 *
	 * @param string|array $data Data to save in activity.txt file.
	 * @param bool         $init Whether or not if it is first init.
	 * @param bool         $debug_mode @since 1.1.6 Set to true if using debug mode for minor log.
	 * @return bool
	 */
	public static function save_to_activity_log( $data, $init = false, $debug_mode = false ) {

		$is_debug_on = everest_backup_is_debug_on();

		if ( $debug_mode && ! $is_debug_on ) {
			return;
		}

		$activity_log_file = EVEREST_BACKUP_ACTIVITY_PATH;

		$content = '';

		if ( $init ) {

			global $wp_version, $wp_db_version;

			if ( file_exists( $activity_log_file ) ) {

				/**
				 * Create new file during every new instance of the process.
				 */
				unlink( $activity_log_file );
			}

			$ebwp_addons = everest_backup_installed_addons( 'active' );
			if ( is_array( $ebwp_addons ) && ! empty( $ebwp_addons ) ) {
				foreach ( $ebwp_addons as $ebwp_addon ) {
					$plugin_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $ebwp_addon;
					$data        = get_plugin_data( $plugin_file, false, false );

					$addons[] = "{$data['Name']} [{$data['Version']}]";
				}
				$data = '';
			}

			$content .= '============================================';
			$content .= PHP_EOL;
			$content .= 'Server Engine: ' . ( ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'N/A' );
			$content .= PHP_EOL;
			$content .= 'PHP Version: ' . PHP_VERSION;
			$content .= PHP_EOL;
			$content .= 'DB Version: ' . $wp_db_version;
			$content .= PHP_EOL;
			$content .= 'WP Version: ' . $wp_version;
			$content .= PHP_EOL;
			$content .= 'EB Version: ' . EVEREST_BACKUP_VERSION;
			$content .= PHP_EOL;
			$content .= 'EB Archiver: Everest Backup Archiver';
			$content .= PHP_EOL;
			$content .= 'Multisite: ' . is_multisite();
			$content .= PHP_EOL;
			$content .= 'Localhost: ' . everest_backup_is_localhost();
			$content .= PHP_EOL;
			$content .= 'EB Debug: ' . $is_debug_on;
			$content .= PHP_EOL;
			$content .= 'WP Debug: ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG );
			$content .= PHP_EOL;
			$content .= 'WP Debug Log: ' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG );
			$content .= PHP_EOL;
			$content .= 'Timestamp: ' . time();
			$content .= PHP_EOL;
			$content .= 'Timezone: ' . wp_date( 'e' );
			$content .= PHP_EOL;
			$content .= 'Active Addons: ' . wp_json_encode( $addons );
			$content .= PHP_EOL;
			$content .= '============================================';
			$content .= PHP_EOL;
		}

		$content .= '[' . wp_date( 'd-M-Y h:i:s' ) . '] ';
		$content .= $debug_mode ? ' ::DEBUG:: ' : '';
		$content .= isset( $data ) && is_array( $data ) ? wp_json_encode( $data ) : $data;
		$content .= PHP_EOL;

		if ( self::$is_sensitive ) {
			return;
		}

		return Filesystem::init()->writefile( $activity_log_file, $content, true );
	}

	/**
	 * Retrive logs from the option table.
	 *
	 * @database
	 * @return array
	 * @since 1.0.0
	 */
	public static function retrive() {
		return (array) get_option( self::$logs_key, array() );
	}

	/**
	 * Delete/unset logs from the option table.
	 *
	 * @database
	 * @param int[] $keys Array of logs keys.
	 * @return void
	 * @since 1.0.0
	 */
	public static function delete( $keys ) {
		if ( ! $keys ) {
			return;
		}

		$logs = self::retrive();

		if ( is_array( $keys ) && ! empty( $keys ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $logs[ $key ] ) ) {
					unset( $logs[ $key ] );
				}
			}
		}

		update_option( self::$logs_key, $logs );
	}

	/**
	 * Delete all logs from the database ( option table ).
	 *
	 * @database
	 * @return bool
	 * @since 1.0.1
	 */
	public static function delete_all_logs() {
		return delete_option( self::$logs_key );
	}

	/**
	 * Set logs log.
	 *
	 * @param array $data Data to save for logs..
	 *
	 * @return void
	 * @since 1.0.0
	 * @since 2.0.0
	 */
	public static function set( $data ) {

		$log = isset( $data['message'] ) ? $data['message'] : '';

		if ( self::$last_log === $log ) {
			return;
		}

		self::$last_log = $log;

		self::save_to_activity_log( $data );

		/**
		 * Stop setting logs if logging is closed.
		 * This is to prevent "SyntaxError: JSON.parse: unexpected end of data ajax response" type of errors in client side.
		 */
		if ( self::$is_closed ) {
			return;
		}

		if ( isset( $data['type'] ) && 'error' === $data['type'] ) {
			if ( false !== strpos( urldecode( $log ), ABSPATH ) ) {
				$message = explode( ' in ', $data['message'] );
				$data['message'] = is_array( $message ) ? $message[0] : false;
				if ( ! $data['message'] ) {
					return;
				}
			}
		}

		self::fs_set( $data );

	}

	/**
	 * Get logs log.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get() {

		$sorted = array();
		$logs   = everest_backup_sanitize_array( self::$logs );
		$key    = everest_backup_array_search( $logs, 'type', 'done' );

		/**
		 * Make sure, done is always at the end of the array.
		 */
		if ( is_int( $key ) ) {
			$done = $logs[ $key ];

			unset( $logs[ $key ] );

			$sorted = array_values( $logs );

			$sorted[] = $done;
		}

		return $sorted ? $sorted : $logs;
	}

	/**
	 * Set process log type. To add other process types, @see `everest_backup_get_process_types()`
	 *
	 * @param string $type Current process type for log.
	 * @return void
	 * @since 1.0.0
	 */
	public static function init( $type ) {
		static $called = 0;

		/**
		 * Do not call this method more than one time in a single process run.
		 */
		if ( $called ) {
			return;
		}

		self::save_to_activity_log( '', true );

		Proc_Lock::set( $type );

		self::set(
			array(
				'init' => $type,
			)
		);

		++$called;
	}

	/**
	 * Set info log.
	 *
	 * @param string $message Info message.
	 * @return void
	 * @since 1.0.0
	 */
	public static function info( $message ) {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			if ( get_transient( 'everest_backup_wp_cli_express' ) ) {
				\WP_CLI::log( $message );
			}
		}
		self::set(
			array(
				'type'    => 'info',
				'message' => $message,
			)
		);
	}

	/**
	 * Set warning log.
	 *
	 * @param string $message Warning message.
	 * @return void
	 * @since 1.0.0
	 */
	public static function warn( $message ) {

		static $count = 1;

		self::set_infostat( 'total_warnings', $count );

		self::set(
			array(
				'type'    => 'warning',
				'message' => $message,
			)
		);

		++$count;
	}

	/**
	 * Set error log.
	 *
	 * @param string $message Error message.
	 * @return void
	 * @since 1.0.0
	 */
	public static function error( $message ) {

		static $count = 1;

		self::set_infostat( 'total_errors', $count );

		self::set(
			array(
				'type'    => 'error',
				'message' => $message,
			)
		);

		++$count;

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			if ( get_transient( 'everest_backup_wp_cli_express' ) ) {
				\WP_CLI::error( $message );
			}
		}
	}

	/**
	 * Set done log.
	 *
	 * @param string $message done message.
	 * @return void
	 * @since 1.0.0
	 */
	public static function done( $message ) {
		self::set(
			array(
				'type'    => 'done',
				'message' => $message,
			)
		);

		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			if ( get_transient( 'everest_backup_wp_cli_express' ) ) {
				\WP_CLI::success( $message );
			}
		}
	}

	/**
	 * Reset log. This method should be called at the very end of the process.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public static function reset() {
		self::$logs = array();
	}

	/**
	 * Close the logging and reset vars.
	 *
	 * @return void
	 */
	public static function reset_and_close() {
		self::reset();
		self::$is_closed = true;
	}
}
