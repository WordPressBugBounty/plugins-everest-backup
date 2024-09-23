<?php
/**
 * Class for handling process lock.
 *
 * @package everest-backup
 * @since 1.0.7
 */

namespace Everest_Backup;

use Everest_Backup\Filesystem;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling process lock.
 *
 * @since 1.0.7
 */
class Proc_Lock {

	/**
	 * Full path to lockfile.
	 *
	 * @var string
	 */
	protected static $lockfile = EVEREST_BACKUP_LOCKFILE_PATH;

	/**
	 * Set process lock.
	 *
	 * @param string   $type Process type. @see `everest_backup_get_process_types`.
	 * @param int|null $time Time for lockfile.
	 * @param int|null $request_id Request ID for lockfile.
	 *
	 * @return void
	 */
	public static function set( $type, $time = null, $request_id = null ) {

		$json = wp_json_encode(
			array(
				'type'         => $type,
				'uid'          => get_current_user_id(),
				'time'         => is_null( $time ) ? time() : absint( $time ),
				'request_id'   => is_null( $request_id ) ? everest_backup_current_request_id() : $request_id,
				'logger_speed' => everest_backup_get_logger_speed(),
			)
		);

		Filesystem::init()->writefile(
			self::$lockfile,
			everest_backup_str2hex( $json )
		);
	}

	/**
	 * Get data from lockfile.
	 *
	 * @return array
	 */
	public static function get() {
		if ( ! file_exists( self::$lockfile ) ) {
			return array();
		}

		static $cached = array();

		if ( $cached ) {
			$cached['is_stale'] = self::is_stale( $cached );
			return $cached;
		}

		$encrypted = Filesystem::init()->get_file_content( self::$lockfile );
		$content   = everest_backup_hex2str( $encrypted );

		if ( $content ) {
			$cached = (array) json_decode( $content, true );

			$cached['is_stale'] = self::is_stale( $cached );
		}

		return $cached;
	}

	/**
	 * Check whether or not PROC LOCK is stale or invalid.
	 *
	 * @param array $proc_lock `Everest_Backup\Proc_Lock::get` data.
	 * @return bool
	 * @since 1.1.1
	 */
	public static function is_stale( $proc_lock ) {

		if ( empty( $proc_lock['time'] ) ) {
			return false;
		}

		$time_diff = time() - absint( $proc_lock['time'] );

		/**
		 * If time difference is greater than our threshold then LOCKFILE is probably stale.
		 */
		return $time_diff > EVEREST_BACKUP_LOCKFILE_STALE_THRESHOLD;
	}

	/**
	 * Delete lockfile.
	 *
	 * @return void
	 */
	public static function delete() {
		if ( file_exists( self::$lockfile ) ) {
			unlink( self::$lockfile ); // @phpcs:ignore
		}
	}
}
