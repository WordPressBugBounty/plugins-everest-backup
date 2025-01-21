<?php
/**
 * Core export Wrapup class file.
 *
 * @package Everest_Backup
 *
 * @since 2.3.0
 */

namespace Everest_Backup\Core;

use Everest_Backup\Core\Archiver_V2;
use Everest_Backup\Temp_Directory;
use Everest_Backup\Logs;

class List_Backup {

    /**
     * Constructor for the List_Backup class.
     *
     * Initializes the class by registering AJAX actions.
     *
     * @since 2.3.0
     */
    public function __construct() {
        $this->register_ajax();
    }

    /**
     * Register AJAX actions.
     *
     *
     * @since 2.3.0
     */
    private function register_ajax() {
        add_action( 'wp_ajax_everest_backup_list_backup_content', array( $this, 'list_backup' ) );
        add_action( 'wp_ajax_nopriv_everest_backup_list_backup_content', array( $this, 'list_backup' ) );

        add_action( 'wp_ajax_everest_backup_generate_backup_list_file', array( $this, 'generate_backup' ) );
        add_action( 'wp_ajax_nopriv_everest_backup_generate_backup_list_file', array( $this, 'generate_backup' ) );
    }

    /**
     * Lists the content of the backup file.
     *
     * This function should be called via AJAX and it will list the content of the backup file.
     * It will detect if the backup is encrypted or not.
     */
    public function list_backup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $request = everest_backup_get_submitted_data();
        $file    = $request['file'];
        $resume  = $request['resume'] ?? false;
        $c_seek  = $request['c_seek'] ?? false;
        $path    = everest_backup_get_backup_full_path( $file );
        if ( file_exists( $path ) ) {
            echo wp_json_encode( $this->scan_file( $path, $resume, $c_seek ) ); exit;
        }
        echo wp_json_encode( false );
        exit;
    }

    /**
     * Scans the given file path for list of files and their positions.
     * Also detect if the backup is encrypted.
     *
     * @param string $path Path to the backup file.
     *
     * @return array{files:array,encrypted:bool}
     */
    public function scan_file( $path, $resume, $c_seek ) {
        $archiver = new Archiver_V2( $path );
        $archiver->open( 'rb' );

        $files = $archiver->scan_file( $resume, $c_seek );

        $encrypted   = false;
        $backup_meta = $archiver->get_metadata( 'FileInfo' );
        if ( ! empty( $backup_meta ) && isset( $backup_meta['encrypt'] ) && $backup_meta['encrypt'] ) {
            $encrypted = true;
        }

        return array(
            'files' => $files,
            'encrypted' => $encrypted,
        );
    
    }

    /**
     * Generate a list of files from backup file.
     *
     * This function is called via AJAX and it will generate a list of files from the given backup file.
     * It will detect if the backup is encrypted or not.
     *
     * This function will create a temporary directory and write the file chunks in it.
     *
     * @return array{continued:bool,c_seek:int}|bool Array if incomplete, true if complete and false on failure
     *
     * @since 2.3.0
     */
    public function generate_backup() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $request = everest_backup_get_submitted_data();
        if ( ! isset( $request['file'] ) || ! isset( $request['start'] ) ) {
            echo wp_json_encode( false );
            exit;
        }
        $backup = $request['backup'];
        $file   = $request['file'];
        $start  = $request['start'];
        $end    = $request['end'];
        $resume = $request['resume'] ?? false;
        $c_seek = $request['c_seek'] ?? false;

        $path   = everest_backup_get_backup_full_path( $backup );

        if ( file_exists( $path ) ) {
            $archiver = new Archiver_V2( $path );
            $archiver->open( 'rb' );

            $encrypted   = false;
            $backup_meta = $archiver->get_metadata( 'config' );
            if ( ! empty( $backup_meta['FileInfo'] ) && isset( $backup_meta['FileInfo']['encrypt'] ) && $backup_meta['FileInfo']['encrypt'] ) {
                $encrypted = true;
            }

            $list_temp = EVEREST_BACKUP_BACKUP_LIST_TEMP_DIR_PATH;
            if ( $resume ) {
                $mode = 'ab';
                $seek = $c_seek;
            } else {
                if ( is_dir( $list_temp ) ) {
                    $this->delete_directory( $list_temp );
                }
                wp_mkdir_p( $list_temp );
                $mode = 'wb';
                $seek = $start;
            }
            $filename = $list_temp . DIRECTORY_SEPARATOR . $file;
            $handle = fopen( $filename, $mode );

            if ( $handle ) {
                $archiver = new Archiver_V2( $path );
                $archiver->open( 'rb' );
                fseek( $archiver->get_ziphandle(), $seek );
                $start_time = time();
                while ( ! feof( $archiver->get_ziphandle() ) ) {
                    if ( ftell( $archiver->get_ziphandle() ) >= $end ) {
                        break;
                    }
                    if ( $encrypted ) {
                        $encrypted_file_chunk = $archiver->get_next_encrypted_chunk();
                        if ( $encrypted_file_chunk ) {
                            $decrypted = $archiver->decrypt( $encrypted_file_chunk, '' );
                            fwrite( $handle, $decrypted ); //phpcs:ignore
                        }
                    } else {
                        $line = fgets( $archiver->get_ziphandle() );
                        if ( 0 === strpos( $line, 'EBWPFILE_END:' ) ) {
                            break;
                        }
                        fwrite( $handle, $line ); //phpcs:ignore
                    }
                    if ( ( time() - $start_time ) > EVEREST_BACKUP_PHP_EXECUTION_PARKHINE ) {
                        $c_seek = ftell( $archiver->get_ziphandle() );
                        $archiver->close();
                        echo wp_json_encode( array(
                            'continued' => true,
                            'c_seek' => $c_seek,
                        ) ); die();
                    }
                }
                fclose( $handle );
            } else {
                echo wp_json_encode( false );
                exit;
            }
            $archiver->close();
            echo wp_json_encode( true );die;
        }
        echo wp_json_encode( false );die;
    }

    /**
     * Recursively delete a directory and all its contents.
     *
     * @param string $dir Path to the directory to delete.
     *
     * @return bool True on success, false on failure.
     */
    public function delete_directory($dir) {
        if ( ! file_exists( $dir ) ) {
            return true;
        }
    
        if ( ! is_dir( $dir ) ) {
            return unlink( $dir );
        }
    
        foreach ( scandir( $dir ) as $item ) {
            if ($item == '.' || $item == '..') {
                continue;
            }
    
            if ( ! $this->delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
                return false;
            }
    
        }
    
        return rmdir($dir);
    }
}

new List_Backup();
