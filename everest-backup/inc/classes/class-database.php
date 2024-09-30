<?php
/**
 * Database import/export core class.
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
 * Database import/export base class.
 *
 * @since 1.0.0
 */
class Database {

	/**
	 * WordPress database instance.
	 *
	 * @var \wpdb
	 */
	protected $wpdb = null;

	/**
	 * Full path to the sql file.
	 *
	 * @var string
	 * @since 1.0.2
	 */
	protected $filename = null;

	/**
	 * Database prefix for import/export.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $old_db_prefix = null;

	/**
	 * Database prefix for import/export.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $new_db_prefix = null;

	/**
	 * File handle.
	 *
	 * @var resource|boolean
	 * @since 1.0.7
	 */
	protected $handle = false;

	/**
	 * List of table prefixes.
	 *
	 * @var array
	 */
	protected $table_prefix_filters = array();

	/**
	 * Init class.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 * @since 1.0.0
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Custom query function for importing.
	 *
	 * @param string $query SQL query to run.
	 * @return \mysqli_result|bool
	 */
	public function query( $query, $unbuffered = false ) {

		if ( ! $query ) {
			return;
		}

		$dbh = $this->wpdb->dbh;

		if ( ! $dbh ) {
			return;
		}

		if ( $this->wpdb->use_mysqli ) {
			$result_mode = $unbuffered ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT;
			return \mysqli_query( $dbh, $query, $result_mode ); // @phpcs:ignore
		}

		if ( function_exists( 'mysql_query' ) ) {
			return \mysql_query( $query, $dbh ); // @phpcs:ignore
		}
	}

	/**
	 * Returns the error code for the most recent query.
	 *
	 * @return int
	 */
	public function error_num() {
		$dbh = $this->wpdb->dbh;

		if ( ! $dbh ) {
			return;
		}

		if ( $this->wpdb->use_mysqli ) {
			return \mysqli_errno( $dbh ); // @phpcs:ignore
		}

		if ( function_exists( 'mysql_errno' ) ) {
			return \mysql_errno( $dbh ); // @phpcs:ignore
		}
	}

	/**
	 * Returns mysqli last error.
	 *
	 * @return string
	 * @since 1.1.4
	 */
	public function error_msg() {
		$dbh = $this->wpdb->dbh;

		if ( ! $dbh ) {
			return;
		}

		if ( $this->wpdb->use_mysqli ) {
			return \mysqli_error( $dbh ); // @phpcs:ignore
		}

		if ( function_exists( 'mysql_error' ) ) {
			return \mysql_error( $dbh ); // @phpcs:ignore
		}
	}

	/**
	 * Repair crashed or corrupt table.
	 *
	 * @param string $table_name Table name to repair.
	 * @return void
	 */
	public function repair_table( $table_name ) {
		$this->query( "REPAIR TABLE `{$table_name}`" );
	}

	/**
	 * Returns header for dump file.
	 *
	 * @param  string $prefix DB Prefix.
	 * @return string
	 */
	public function get_header( $prefix = false ) {
		$wpdb = $this->wpdb;
		if ( ! $prefix ) {
			$prefix = $wpdb->prefix;
		}
		$dbhost = $wpdb->dbhost;
		$dbname = $wpdb->dbname;

		// Some info about software, source and time.
		$header = sprintf(
			"-- Everest Backup SQL Dump\n" .
			"--\n" .
			"-- Prefix: %s\n" .
			"-- Host: %s\n" .
			"-- Database: %s\n" .
			"-- Class: %s\n" .
			"--\n",
			$prefix,
			$dbhost,
			$dbname,
			get_class( $this )
		);

		return $header;
	}

	/**
	 * Add table prefix filter
	 *
	 * @param  string $table_prefix   Table prefix
	 * @param  string $exclude_prefix Exclude prefix
	 * @return object
	 */
	public function add_table_prefix_filter( $table_prefix, $exclude_prefix = null ) {
		$this->table_prefix_filters[] = array( $table_prefix, $exclude_prefix );

		return $this;
	}

	/**
	 * Get table prefix filter
	 *
	 * @return array
	 */
	public function get_table_prefix_filters() {
		return $this->table_prefix_filters;
	}

	/**
	 * Get MySQL lower case table names
	 *
	 * @return int
	 */
	protected function get_lower_case_table_names() {
		$result = $this->query( "SHOW VARIABLES LIKE 'lower_case_table_names'" );

		if ( ! $result ) {
			return;
		}

		$row = $result->fetch_assoc();

		$result->free_result();

		if ( isset( $row['Value'] ) ) {
			return $row['Value'];
		}
	}

	/**
	 * Returns MySql query string for get_tables.
	 *
	 * @return string
	 */
	public function get_tables_query() {
		$where_query = array();

		// Get lower case table names
		$lower_case_table_names = $this->get_lower_case_table_names();

		// Loop over table prefixes
		if ( $this->get_table_prefix_filters() ) {
			foreach ( $this->get_table_prefix_filters() as $prefix_filter ) {
				if ( isset( $prefix_filter[0], $prefix_filter[1] ) ) {
					if ( $lower_case_table_names ) {
						$where_query[] = sprintf( "(`Tables_in_%s` REGEXP '^%s' AND `Tables_in_%s` NOT REGEXP '^%s')", $this->wpdb->dbname, $prefix_filter[0], $this->wpdb->dbname, $prefix_filter[1] );
					} else {
						$where_query[] = sprintf( "(CAST(`Tables_in_%s` AS BINARY) REGEXP BINARY '^%s' AND CAST(`Tables_in_%s` AS BINARY) NOT REGEXP BINARY '^%s')", $this->wpdb->dbname, $prefix_filter[0], $this->wpdb->dbname, $prefix_filter[1] );
					}
				} else {
					if ( $lower_case_table_names ) {
						$where_query[] = sprintf( "`Tables_in_%s` REGEXP '^%s'", $this->wpdb->dbname, $prefix_filter[0] );
					} else {
						$where_query[] = sprintf( "CAST(`Tables_in_%s` AS BINARY) REGEXP BINARY '^%s'", $this->wpdb->dbname, $prefix_filter[0] );
					}
				}
			}
		} else {
			$where_query[] = 1;
		}

		return sprintf( "SHOW FULL TABLES FROM `%s` WHERE `Table_type` = 'BASE TABLE' AND (%s)", $this->wpdb->dbname, implode( ' OR ', $where_query ) );

	}

	/**
	 * Get database table names.
	 *
	 * @param null|string $custom_prefix If provided custom prefix then the tables array will have the `$custom_prefix` instead of actual `$wpdb` prefix.
	 * @return array
	 * @since 1.0.0
	 */
	public function get_tables( $custom_prefix = null ) {
		$wpdb = $this->wpdb;

		$prefix = $wpdb->prefix;
		$tables = $wpdb->get_results( $this->get_tables_query(), ARRAY_A ); // @phpcs:ignore

		$table_names = is_array( $tables ) ? array_map(
			function( $table ) use ( $custom_prefix, $prefix ) {
				$table_name = array_values( $table );
				if ( isset( $table_name[0] ) ) {
					if ( $custom_prefix ) {
						return everest_backup_str_replace_once( $prefix, $custom_prefix, $table_name[0] );
					}
					return $table_name[0];
				}
			},
			$tables
		) : array();

		$wpdb->flush();

		return $table_names;
	}

	/**
	 * Replace list of users login related meta key or values that needs to be changed according to the current table name.
	 *
	 * @param string $input SQL input string.
	 * @return string
	 * @since 1.0.0
	 */
	public function replace_users_login_related_metas( $input ) {

		if ( ! $input ) {
			return $input;
		}

		$lists = array();
		$metas = array(
			'user_roles',
			'capabilities',
			'user_level',
			'dashboard_quick_press_last_post_id',
		);

		if ( is_array( $metas ) && ! empty( $metas ) ) {
			foreach ( $metas as $meta ) {
				$lists['old'][] = $this->old_db_prefix . $meta;
				$lists['new'][] = $this->new_db_prefix . $meta;
			}
		}

		$replaced = str_replace( $lists['old'], $lists['new'], $input );

		return $replaced && is_string( $replaced ) ? $replaced : $input;

	}

	/**
	 * Recursively fix serialized strings with multiple regex checks.
	 *
	 * @param string $serialized Serialized unescaped data string.
	 * @param int    $key Auto increased recursion key for the regex array value.
	 * @return string
	 * @since 1.1.4
	 */
	protected function recursively_fix_serialized_string( $serialized, $key = 0 ) {
		if ( ! $serialized ) {
			return;
		}

		/**
		 * Old RegEx ( Before 1.1.4 ): '/s:([0-9]+):\"(.*?)\";/'
		 */

		$regexes = array(

			// Beaver Builder contents type compatible.
			'/(?<=^|\{|;)s:(\d+):\"(.*?)\";(?=[asbdiO]\:\d|N;|\}|$)/s',

			// Elementor contents type compatible.
			'/s\:(\d+)\:\"(.*?)\";/s',

			// General all purpose final check.
			'#s:(\d+):"(.*?)";(?=\\}*(?:[aOsidbN][:;]|\\z))#s'
		);

		$regex = $regexes[ $key ];

		$fixed_string = preg_replace_callback(
			$regex,
			function ( $matches ) {
				return 's:' . strlen( $matches[2] ) . ':"' . $matches[2] . '";';
			},
			$serialized
		);

		if ( $key === everest_backup_array_key_last( $regexes ) ) {
			return $fixed_string;
		}

		$key = $key + 1;

		return $this->recursively_fix_serialized_string( $fixed_string, $key );

	}

	/**
	 * Fix string length in serialized value.
	 *
	 * @param string $query Serialized string value to fix.
	 * @return mixed
	 * @since 1.0.2
	 * @since 1.0.6 Re-wrote for compatibility. '/s:([0-9]+):\"(.*?)\";/'
	 * @since 1.1.4 Added recursion alias to fix serialized data recursively.
	 */
	protected function fix_str_length( $query ) {

		if ( ! $query ) {
			return $query;
		}

		$delimiter = "', '";

		$query_parts = explode( $delimiter, $query );

		if ( ! $query_parts ) {
			return $query;
		}

		$queries = array();

		if ( is_array( $query_parts ) && ! empty( $query_parts ) ) {
			foreach ( $query_parts as $query_part ) {

				if ( ! is_serialized( $query_part ) ) {
					$queries[] = $query_part;
				} else {

					$serialized = $this->escape_mysql( $this->recursively_fix_serialized_string( $this->unescape_mysql( $query_part ) ) );

					$explode = array_filter( explode( "\');", $serialized ) );

					if ( count( $explode ) > 1  ) {
						$last_key = everest_backup_array_key_last( $explode );

						$queries[] = ( false !== strpos( $explode[ $last_key ], "\');" ) ) ? rtrim( $serialized, "\');" ) . "');" : $serialized;
					} else {
						$queries[] = ( false !== strpos( $serialized, "\');" ) ) ? rtrim( $serialized, "\');" ) . "');" : $serialized;
					}

					$serialized = '';
					$explode    = array();
				}
			}
		}

		$query_parts = array();

		return implode( $delimiter, $queries );

	}

	/**
	 * Escape MySQL special characters
	 *
	 * @param  string $data Data to escape.
	 * @return string
	 * @since 1.0.7
	 */
	public function escape_mysql( $data ) {

		$dbh = $this->wpdb->dbh;

		if ( ! $dbh ) {
			return;
		}

		if ( $this->wpdb->use_mysqli ) {
			return \mysqli_real_escape_string( $dbh, $data ); // @phpcs:ignore
		}

		if ( function_exists( 'mysql_real_escape_string' ) ) {
			return \mysql_real_escape_string( $data, $dbh ); // @phpcs:ignore
		}
	}

	/**
	 * Escape MySQL special characters
	 *
	 * @param  string $data Data to escape.
	 * @return string
	 * @since 1.0.0
	 */
	public function escape_mysql_legacy( $data ) {
		return strtr(
			$data,
			array_combine(
				array( "\x00", "\n", "\r", '\\', "'", '"', "\x1a" ),
				array( '\\0', '\\n', '\\r', '\\\\', "\\'", '\\"', '\\Z' )
			)
		);
	}

	/**
	 * Unescape MySQL special characters
	 *
	 * @param  string $data Data to unescape.
	 * @return string
	 */
	public function unescape_mysql( $data ) {
		return strtr(
			$data,
			array_combine(
				array( '\\0', '\\n', '\\r', '\\\\', "\\'", '\\"', '\\Z' ),
				array( "\x00", "\n", "\r", '\\', "'", '"', "\x1a" )
			)
		);
	}

	/**
	 * Write data to sql file.
	 *
	 * @param string $data Data to write in sql file.
	 * @return bool False on failure.
	 * @since 1.0.2
	 * @since 1.0.7 Now it opens files only once rather than opening the file everytime for writing.
	 */
	public function write( $data ) {

		if ( ! $this->filename ) {
			return;
		}

		if ( false === $this->handle ) {
			$this->handle = fopen( $this->filename, 'w' ); // @phpcs:ignore
		}

		$write = fwrite( $this->handle, $data ); // @phpcs:ignore

		return is_int( $write );

	}

	/**
	 * Close the file handle.
	 *
	 * @return bool
	 * @since 1.0.7
	 */
	public function close() {
		if ( false === $this->handle ) {
			return true;
		}

		return fclose( $this->handle ); // @phpcs:ignore
	}

	/**
	 * Check if current string is just a sql comment `--` or valid string.
	 *
	 * @param string $input SQL input string.
	 * @return boolean
	 * @since 1.0.0
	 */
	protected function is_valid( $input ) {
		return ! ( ( '--' === substr( $input, 0, 2 ) ) || ( '' === $input ) );
	}

	/**
	 * Check if current input string is the end of sql query.
	 *
	 * @param string $input SQL input string.
	 * @return boolean
	 * @since 1.0.0
	 */
	protected function is_query_end( $input ) {
		return ( ';' === substr( trim( $input ), -1, 1 ) );
	}

	/**
	 * Prepare table values
	 *
	 * @param  string  $input       Table value.
	 * @param  integer $column_type Column type.
	 * @return string
	 */
	protected function prepare_table_values( $input, $column_type ) {
		if ( is_null( $input ) ) {
			return 'NULL';
		} elseif ( stripos( $column_type, 'tinyint' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'smallint' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'mediumint' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'int' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'bigint' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'float' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'double' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'decimal' ) === 0 ) {
			return $input;
		} elseif ( stripos( $column_type, 'bit' ) === 0 ) {
			return $input;
		}

		return "'" . $this->escape_mysql( $input ) . "'";
	}

	/**
	 * Rename old table name into new.
	 *
	 * @param string $input SQL statement.
	 * @param array  $old_tables Old tables array with old original prefix.
	 * @return string
	 * @since 1.0.0
	 */
	protected function rename_table_names( $input, $old_tables ) {
		$old_prefix = $this->old_db_prefix;
		$new_prefix = $this->new_db_prefix;

		if ( $old_prefix === $new_prefix ) {
			return $input;
		}

		$new_tables = str_replace( $old_prefix, $new_prefix, implode( ',', $old_tables ) );

		return str_replace( $old_tables, explode( ',', $new_tables ), $input );

	}

	/**
	 * Replace table constraints
	 *
	 * @param  string $input SQL statement.
	 * @return string
	 */
	protected function replace_table_constraints( $input ) {
		$pattern = array(
			'/\s+CONSTRAINT(.+)REFERENCES(.+),/i',
			'/,\s+CONSTRAINT(.+)REFERENCES(.+)/i',
		);

		return preg_replace( $pattern, '', $input );
	}

	/**
	 * Replace table options
	 *
	 * @param  string $input SQL statement.
	 * @return string
	 */
	protected function replace_table_options( $input ) {
		$search  = array(
			'TYPE=InnoDB',
			'TYPE=MyISAM',
			'ENGINE=Aria',
			'TRANSACTIONAL=0',
			'TRANSACTIONAL=1',
			'PAGE_CHECKSUM=0',
			'PAGE_CHECKSUM=1',
			'TABLE_CHECKSUM=0',
			'TABLE_CHECKSUM=1',
			'ROW_FORMAT=PAGE',
			'ROW_FORMAT=FIXED',
			'ROW_FORMAT=DYNAMIC',
		);
		$replace = array(
			'ENGINE=InnoDB',
			'ENGINE=MyISAM',
			'ENGINE=MyISAM',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
			'',
		);

		return str_ireplace( $search, $replace, $input );
	}

}

