<?php
/**
 * Core export Wrapup class file.
 *
 * @package Everest_Backup
 */

namespace Everest_Backup\Core\Export;

use Everest_Backup\Core\Archiver_V2;
use Everest_Backup\Filesystem;
use Everest_Backup\Logs;
use Everest_Backup\Modules\Migration;
use Everest_Backup\Traits\Export;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrap up export.
 */
class Wrapup {

	use Export;

	/**
	 * Delete zip from server.
	 *
	 * @param string $zip Zip location.
	 */
	private static function delete_from_server( $zip ) {

		if ( ! file_exists( $zip ) ) {
			return;
		}

		$params = self::read_config( 'Params' );

		/**
		 * Filter hook to avoid delete from server if the cloud upload fails.
		 * Return true if you want to avoid the delete from server.
		 *
		 * @since 1.1.5
		 */
		if ( true === apply_filters( 'everest_backup_avoid_delete_from_server', false ) ) {
			return;
		}

		if ( empty( $params['save_to'] ) ) {
			return;
		}

		if ( 'server' === $params['save_to'] ) {
			return;
		}

		if ( empty( $params['delete_from_server'] ) ) {
			return;
		}

		Logs::info( __( 'Deleting the backup file from the server.', 'everest-backup' ) );

		/**
		 * Filesystem class object.
		 *
		 * @var Filesystem
		 */
		$filesystem = Filesystem::init();

		$filesystem->delete( $zip );
	}

	/**
	 * File stats.
	 *
	 * @param string $listpath List path.
	 * @return array
	 */
	private static function files_stats( $listpath ) {
		$total = 0;
		$size  = 0;

		$handle = fopen( $listpath, 'r' ); // phpcs:ignore

		if ( is_resource( $handle ) ) {
			while ( ! feof( $handle ) ) {
				$line = fgets( $handle );

				if ( ! $line ) {
					continue;
				}

				$line = trim( $line );

				if ( ! file_exists( $line ) ) {
					continue;
				}

				if ( 'ebwp' === pathinfo( $line, PATHINFO_EXTENSION ) ) {
					continue;
				}

				$size += filesize( $line );
				++$total;

				$line = '';
			}

			fclose( $handle ); // phpcs:ignore

			$handle = false;
		}

		return compact( 'total', 'size' );
	}

	/**
	 * Run.
	 *
	 * @throws \Exception File not found exception.
	 */
	private static function run() {

		$subtask = ! empty( self::$params['subtask'] ) ? json_decode( self::$params['subtask'], true ) : array();

		if ( ! $subtask ) {
			Logs::set_proc_stat(
				array(
					'log'      => 'info',
					'status'   => 'in-process',
					'progress' => 80,
					'message'  => __( 'Wrapping things up', 'everest-backup' ),
				)
			);
		}

		$listpath       = everest_backup_current_request_storage_path( self::$LISTFILENAME ); // phpcs:ignore
		$removelistpath = everest_backup_current_request_storage_path( self::$REMOVELISTFILENAME ); // phpcs:ignore

		if ( ! file_exists( $listpath ) ) {
			Logs::error( esc_html__( 'Files list not found, aborting backup.', 'everest-backup' ) );
			everest_backup_send_error();
		}

		$zip       = self::get_archive_path();
		$timestart = time();

		if ( ! $subtask ) {
			Logs::set_proc_stat(
				array(
					'log'      => 'info',
					'status'   => 'in-process',
					'progress' => 80,
					'message'  => __( 'Checking available space', 'everest-backup' ),
				)
			);
		}

		$stats = ! empty( $subtask['stats'] ) ? $subtask['stats'] : self::files_stats( $listpath );

		if ( ! everest_backup_is_space_available( EVEREST_BACKUP_BACKUP_DIR_PATH, $stats['size'] ) ) {
			Logs::error( esc_html__( 'Required space not available, aborting backup.', 'everest-backup' ) );
			everest_backup_send_error();
		}

		if ( ! $subtask ) {
			Logs::set_proc_stat(
				array(
					'log'      => 'info',
					'status'   => 'in-process',
					'progress' => 80,
					'message'  => __( 'Space available, archiving files', 'everest-backup' ),
				)
			);
		}

		$archiver = new Archiver_V2( $zip );

		if ( $archiver->open( $subtask ? 'ab' : 'wb' ) ) {

			if ( ! $subtask ) {
				$archiver->set_metadata(
					array(
						'stats'      => $stats,
						'filename'   => self::get_archive_name(),
						'request_id' => everest_backup_current_request_id(),
						'tags'       => everest_backup_generate_tags_from_params( self::read_config( 'Params' ) ),
						'config'     => self::read_config(),
					)
				);
			}

			$encode   = false;
			$FileInfo = self::read_config( 'FileInfo' );
			if ( isset( $FileInfo['encrypt'] ) && $FileInfo['encrypt'] ) {
				$encode = true;
			}

			$handle = fopen( $listpath, 'r' ); // phpcs:ignore

			/**
			 * Files add section.
			 */
			if ( is_resource( $handle ) && ! isset( $subtask['remove_'] ) ) {

				$count = ! empty( $subtask['count'] ) ? absint( $subtask['count'] ) : 1;

				if ( ! empty( $subtask['ftell'] ) ) {
					fseek( $handle, absint( $subtask['ftell'] ) );
				}
				while ( ! feof( $handle ) ) {
					if ( empty( $subtask['c_f'] ) && empty( $subtask['c_ftell'] ) ) {
						$line = fgets( $handle );

						if ( ! $line ) {
							continue;
						}

						$filepath = trim( $line );

						if ( ! file_exists( $filepath ) ) {
							continue;
						}

						if ( 'ebwp' === pathinfo( $line, PATHINFO_EXTENSION ) ) {
							continue;
						}
					} else {
						$filepath = $subtask['c_f'];
					}

					$file_write_return = $archiver->add_file( $filepath, $subtask, $encode );
					if ( $file_write_return ) {
						$subtask['c_f']     = '';
						$subtask['c_ftell'] = '';

						$progress = ( $count / $stats['total'] ) * 100;

						Logs::set_proc_stat(
							array(
								'status'   => 'in-process',
								'progress' => round( $progress * 0.2 + 80, 2 ), // Starts from 80 ends at 100.
								'message'  => sprintf(
									/* translators: number of archived files */
									__( 'Archiving files: %d%% completed', 'everest-backup' ),
									esc_html( $progress )
								),
								/* translators: archived count and total */
								'detail'   => sprintf( __( 'Archived: %1$s out of %2$s', 'everest-backup' ), esc_html( $count ), esc_html( $stats['total'] ) ),
							)
						);

						++$count;
					}

					if ( is_array( $file_write_return ) ) {
						return self::set_next(
							'wrapup',
							wp_json_encode(
								array(
									'count'   => $count,
									'ftell'   => ftell( $handle ),
									'stats'   => $stats,
									'c_ftell' => $file_write_return['current_file_ftell'],
									'c_f'     => $file_write_return['file_name'],
								)
							)
						);
					}

					if ( ( time() - $timestart ) > ( EVEREST_BACKUP_PHP_EXECUTION_PARKHINE / 2 ) ) {
						return self::set_next(
							'wrapup',
							wp_json_encode(
								array(
									'count' => $count,
									'ftell' => ftell( $handle ),
									'stats' => $stats,
								)
							)
						);
					}

					$line = '';
				}

				fclose( $handle ); // phpcs:ignore

				$handle = false;
			}

			$handle = false;
			if ( file_exists( $removelistpath ) ) {
				$handle = fopen( $removelistpath, 'r' ); // phpcs:ignore
			}

			/**
			 * File removed section.
			 */
			if ( is_resource( $handle ) ) {
				$count = ! empty( $subtask['count'] ) ? absint( $subtask['count'] ) : 1;

				if ( ! empty( $subtask['ftell'] ) ) {
					fseek( $handle, absint( $subtask['ftell'] ) );
				}
				while ( ! feof( $handle ) ) {
					if ( empty( $subtask['c_f'] ) && empty( $subtask['c_ftell'] ) ) {
						$line = fgets( $handle );

						if ( ! $line ) {
							continue;
						}

						$filepath = trim( $line );

						if ( 'ebwp' === pathinfo( $line, PATHINFO_EXTENSION ) ) {
							continue;
						}
					}

					$file_write_return = $archiver->add_remove_file( $filepath, $subtask, $encode );

					if ( is_array( $file_write_return ) ) {
						return self::set_next(
							'wrapup',
							wp_json_encode(
								array(
									'count'   => $count,
									'ftell'   => ftell( $handle ),
									'stats'   => $stats,
									'remove_' => 1,
								)
							)
						);
					}

					if ( ( time() - $timestart ) > 10 ) {
						return self::set_next(
							'wrapup',
							wp_json_encode(
								array(
									'count'   => $count,
									'ftell'   => ftell( $handle ),
									'stats'   => $stats,
									'remove_' => 1,
								)
							)
						);
					}

					$line = '';
				}

				fclose( $handle ); // phpcs:ignore

				$handle = false;
			}

			fwrite( $archiver->get_ziphandle(), "\nEBWPFILE_FILE_END:" . ftell( $archiver->get_ziphandle() ) );
			$archiver->close();
		}

		if ( get_transient( 'everest_backup_doing_scheduled_backup' ) ) {
			rename( EVEREST_BACKUP_CURRENT_BACKUP_FILE_INFO_PATH, EVEREST_BACKUP_LAST_BACKUP_FILE_INFO_PATH );
		}

		$migration = new Migration(
			array(
				'file'       => basename( $zip ),
				'auto_nonce' => true,
			)
		);

		$time_elapsed = everest_backup_is_debug_on() ? time() - everest_backup_current_request_timestamp() . ' seconds' : human_time_diff( everest_backup_current_request_timestamp() );

		/* translators: time elapsed */
		Logs::info( sprintf( __( 'Time elapsed: %s', 'everest-backup' ), $time_elapsed ) );

		/* translators: file size */
		Logs::info( sprintf( __( 'File size: %s', 'everest-backup' ), esc_html( everest_backup_format_size( filesize( $zip ) ) ) ) );

		$params = self::read_config( 'Params' );

		if ( isset( $params['save_to'] ) && 'server' !== $params['save_to'] ) {
			if ( ! empty( $params['delete_from_server'] ) ) {
				everest_backup_cloud_update_option( 'delete_from_server', true );
			} else {
				everest_backup_cloud_delete_option( 'delete_from_server' );
			}
		}

		do_action( 'everest_backup_after_zip_done', $zip, $migration->get_url() );

		if (
			(
				isset( $params['save_to'] )
				&& 'pcloud' === $params['save_to']
				&& defined( 'EVEREST_BACKUP_PCLOUD_VERSION' )
				&& ( everest_backup_compare_version( EVEREST_BACKUP_PCLOUD_VERSION, '1.0.8' ) > 0 )
			)
		) {
			everest_backup_cloud_update_option( 'manual_backup_continued', true ); // for not showing uploaded to cloud message after backup.
		} else {
			Logs::done( __( 'Backup completed', 'everest-backup' ) );

			self::delete_from_server( $zip );

			$procstat = Logs::get_proc_stat();

			everest_backup_send_success(
				array_merge(
					array(
						'zipurl'        => everest_backup_convert_file_path_to_url( $zip ),
						'migration_url' => $migration->get_url(),
					),
					$procstat
				)
			);
		}
	}
}
