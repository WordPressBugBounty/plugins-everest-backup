<?php
/**
 * Class for handling database export.
 *
 * @package everest-backup
 *
 * @phpcs:disable
 */

namespace Everest_Backup\Modules;

use Everest_Backup\Database;
use Everest_Backup\Logs;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling database export.
 *
 * @since 1.0.0
 */
class Export_Database extends Database {

	/**
	 * Init class.
	 *
	 * @param string $filename Full path to the sql file.
	 * @since 1.0.0
	 */
	public function __construct( $filename ) {
		global $wpdb;

		$this->filename      = $filename;
		$this->old_db_prefix = $wpdb->prefix;

		parent::__construct( $wpdb );
	}

	/**
	 * Returns primary key for provided table.
	 *
	 * @param string $table_name Table name.
	 * @return array
	 * @since 1.0.7
	 */
	public function get_primary_keys( $table_name ) {
		$primary_keys = array();

		// Get primary keys.
		$result = $this->query( "SHOW KEYS FROM `{$table_name}` WHERE `Key_name` = 'PRIMARY'" );
		while ( $row = $result->fetch_assoc() ) {
			if ( isset( $row['Column_name'] ) ) {
				$primary_keys[] = $row['Column_name'];
			}
		}

		// Close result cursor.
		$result->free_result();

		return $primary_keys;
	}

	/**
	 * Get MySQL column names
	 *
	 * @param  string $table_name Table name
	 * @return array
	 */
	public function get_column_names( $table_name ) {
		$column_names = array();

		$result = $this->query( "SHOW COLUMNS FROM `{$table_name}`" );
		while ( $row = $result->fetch_assoc() ) {
			if ( isset( $row['Field'] ) ) {
				$column_names[ strtolower( $row['Field'] ) ] = $row['Field'];
			}
		}

		$result->free_result();

		return $column_names;
	}

	/**
	 * Returns table where clause.
	 *
	 * @param string $table_name
	 * @return string
	 */
	protected function get_table_where( $table_name ) {
		$prefix = $this->wpdb->prefix;

		$where = array();

		switch ( $table_name ) {
			case "{$prefix}options":
				$where[] = 'option_name NOT LIKE "%_transient_%"';
				$where[] = 'AND option_name NOT IN ("' . EVEREST_BACKUP_LOGS_KEY . '", "' . EVEREST_BACKUP_SETTINGS_KEY . '")';
				break;

			default:
				$where[] = 1;
				break;
		}

		return implode( ' ', $where );

	}

	/**
	 * Returns sql query string for retriving table data.
	 *
	 * @param string $table_name Table name.
	 * @return string
	 */
	protected function get_table_data_query( $table_name ) {

		$primary_keys = $this->get_primary_keys( $table_name );
		$table_where  = $this->get_table_where( $table_name );

		if ( $primary_keys ) {

			// Set table keys.
			$table_keys = array();
			foreach ( $primary_keys as $key ) {
				$table_keys[] = sprintf( '`%s`', $key );
			}

			$table_keys = implode( ', ', $table_keys );

			return sprintf( 'SELECT * FROM `%s` AS t1 JOIN (SELECT %s FROM `%s` WHERE %s ORDER BY %s) AS t2 USING (%s);', $table_name, $table_keys, $table_name, $table_where, $table_keys, $table_keys );

		}

		$query   = array();
		$query[] = "SELECT * FROM $table_name";
		$query[] = sprintf( 'WHERE', $table_where );

		return implode( ' ', $query ) . ';';
	}

	/**
	 * Write INSERT query with table data values.
	 *
	 * @param string $table_name Table name.
	 * @param array $column_types Table column types.
	 * @return int|false;
	 */
	protected function write_table_data( $table_name, $column_types, $query_count_cb = null ) {

		static $query_count = 1;

		$created = 1;

		$query = $this->get_table_data_query( $table_name );

		$query_table_data = $this->query( $query, true );

		if ( 1194 === $this->error_num() ) {

			/**
			 * Probably this table is crashed or needs reparing.
			 */
			$this->repair_table( $table_name );

			$query_table_data = $this->query( $query, true );
		}

		if ( ! is_bool( $query_table_data ) ) {

			$created = $this->write( "START TRANSACTION;\n" );

			while ( $table_data = $query_table_data->fetch_assoc() ) {

				if ( ! $table_data ) {
					continue;
				}

				if ( is_callable( $query_count_cb ) ) {
					call_user_func( $query_count_cb, $query_count, $table_name );
				}

				if ( is_array( $table_data ) && ! empty( $table_data ) ) {

					$insert_query = "INSERT INTO `$table_name` VALUES(";
					$last         = key( array_slice( $table_data, -1, 1, true ) );

					foreach ( $table_data as $column => $value ) {
						$insert_query .= $this->prepare_table_values( $value, $column_types[ strtolower( $column ) ] );

						if ( $last !== $column ) {
							$insert_query .= ', ';
						}
					}

					$insert_query .= ");\n";

					$created = $this->write( $insert_query );

					$insert_query = '';
				}

				$query_count++;
			}

			$created = $this->write( "COMMIT;\n" );

			$query_table_data->free_result();
		}

		return $created;
	}

	public function export_table( $table_name, $query_count_cb = null, $prefix = false ) {

		$created = $this->write( $this->get_header( $prefix ) );

		Logs::save_to_activity_log( "Exporting Table: {$table_name}", false, true );

		$query_create_table = $this->query( "SHOW CREATE TABLE $table_name;" );

		if ( $query_create_table instanceof \mysqli_result ) {

			$created = $this->write( "\nDROP TABLE IF EXISTS `{$table_name}`;\n" );

			$create_table = $query_create_table->fetch_row();

			$query_create_table->free_result();

			$column_types = $this->get_column_names( $table_name );

			$created = $this->write( "$create_table[1];\n\n" );

			$created = $this->write_table_data( $table_name, $column_types, $query_count_cb );

		}

		$this->close();

		return $created;
	}

	/**
	 * Export database.
	 *
	 * @return bool
	 */
	public function export() {

		$created = $this->write( $this->get_header() );

		$query_tables = $this->query( $this->get_tables_query() );

		$total_tables = ! empty( $query_tables->num_rows ) ? (int) $query_tables->num_rows : 0;

		Logs::save_to_activity_log( "Total Tables: {$total_tables}", false, true );

		$count = 1;

		while ( $table = $query_tables->fetch_row() ) {
			$table_name = $table[0];

			Logs::save_to_activity_log( "Exporting Table: {$table_name}", false, true );

			$created = $this->write( "\nDROP TABLE IF EXISTS `{$table_name}`;\n" );

			$query_create_table = $this->query( "SHOW CREATE TABLE $table_name;" );

			$create_table = $query_create_table->fetch_row();

			$query_create_table->free_result();

			if ( empty( $create_table[1] ) ) {
				continue;
			}

			$column_types = $this->get_column_names( $table_name );

			$created = $this->write( "$create_table[1];\n\n" );

			$progress = ( ( $count / $total_tables ) * 100 );

			$created = $this->write_table_data( $table_name, $column_types, $progress );

			$count++;

		}

		$this->close();

		$query_tables->free_result();

		return $created;

	}
}
