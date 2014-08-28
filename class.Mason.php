<?php

/**
* Simple MySQL builder class
*
* @package 	Mason
* @author 	Samuel Mello of Clark Nidkel Powell (http://clarknikdelpowell.com
* @version 	1.2
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License, version 2 (or later),
* as published by the Free Software Foundation.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class Mason {

	/**
	* protected variables (only accessible within this or child classes)
	*
	* @var 	MySQL 	$connection 		Stored connection to the database
	* @var 	array 	$connection_vars 	Array of connection parameters to use in connect_sql()
	* @var  array 	$messages 			Array of error codes and response messages
	* @var 	array 	$status 		 
	*/

	protected $connection = FALSE;
	protected $connection_vars = array(
		'host' 	=> '' 		/* database host address */
	,	'user' 	=> '' 		/* username to authenticate with */
	,	'pass' 	=> '' 		/* password to authenticate with */
	,	'db' 	=> '' 		/* database to select */
	,	'ext' 	=> FALSE	/* when extending, set to TRUE if you want the load_options function to return it's results */
	,	'debug' => FALSE 	/* turns on or off debugging */
	);
	protected $messages = array(
		0 	=> 'Class has not been instantiated yet'
	,	200 => 'The task has completed successfully'
	, 	300 => 'Performing the task requested has failed'
	,	500 => 'An uknown error has occurred'
	,	501 => 'Connection to server has failed'
	,	502 => 'Cannot perform query as connection has not been established'
	,	503 => 'Query failed'
	,	504 => 'No table name was provided or this is not a valid table. Check the stored $tables variable for valid table names.'
	,	505 => 'You must provide a valid array of column_name=>id values to update'
	,	506 => 'An associative array of column=>value pairs must be provided to update or insert'
	, 	507 => 'A string or array of statements are the only parameters accepted'
	,	508 => 'A string or array of column names are the only parameters accepted'
	);

	/**
	* Public variables (Available outside of the class)
	*
	* @var 	array 	$status 	The stored status array from last recorded operation
	* @var 	array 	$results 	The stored result set from the last recorded operation	
	* @var 	array 	$queries 	A list of all recent queries	 
	*/
	public $status 	= array();
	public $results = array();
	public $queries = array();
	public $tables 	= array();

	/**
	* Initial constructor: creates connection
	*
	* @since 	1.0
	* @param 	array 	$args 	Optional arguments to use for the DB connection
	* @return 	boolean 	 	Whether the connection was successful
	*/
	public function __construct( $args = array() ) {
		$this->debug( $args );
		$return = FALSE;
		$conn = $this->reconnect( $args );
		if ( $conn ) { 
			$this->load_tables();
			$options = $this->load_options();
			if ( $args['ext'] === TRUE ) { $return = $options; }
		}
		else { $return = $conn; }
		return $conn;
	}

	/**
	* Destructor: destroys DB connection
	*
	* @since 	1.0
	* @return 	null
	*/
	public function __destruct() {
		if ( $conn = $this->connection ) {
			$conn->close();
		}
		return;
	}

	/**
	* Turns debugging on or off using the args setting passed from __construct
	*
	* @since 	1.0
	* @return 	null
	*/
	protected function debug( $args ) {
		if ( isset($args['debug']) && $args['debug'] === TRUE ) {
			error_reporting(E_ALL | E_STRICT);
			ini_set('display_errors', 1);
		}
		return;
	}

	/**
	* Connection intermediary to merge optional arguments and perform connection
	* - Can be used to retry connection if needed (hence reconnect name)
	*
	* @since 	1.0
	* @param 	array 	$args 	Optional arguments to use for the DB connection
	* @return 	boolean		 	Whether the connection was successful or not
	*/
	public function reconnect( $args ) {
		$settings = $this->connection_vars;
		if ( count($args) > 0 ) { 
			$settings = array_merge( $settings, $args ); 
			$this->connection_vars = $settings;
		}
		$conn = $this->connect_sql($settings);
		return $conn;
	}

	/**
	* Optional function to run at instantiation. Useful when extending this class and you need something to run during __construct()
	*/
	public function load_options() {
		return;
	}

	/**
	* Sets the status code and message using the prebuilt messages and included message
	*
	* @since 	1.0
	* @param 	int 	$id 		The error code ID (200, 500, etc [see $messages for all])
	* @param 	string 	$message 	An optional message to use
	* @param 	boolean $append 	Whether to append or replace the system message with optional one
	* @return 	array				Status array with code and message
	*/
	protected function set_status( $id, $message = '', $append = FALSE ) {
		$retmsg = $this->messages[$id];
		if ( strlen($retmsg) == 0 ) { $retmsg = $this->messages[500]; }
		if ( strlen($message) > 0 ) {
			if ( $append === TRUE ) { $retmsg .= ': ' . $message; }
			else { $retmsg = $message; }
		}
		$backtraces = debug_backtrace();
		$functions = '';
		foreach ( $backtraces as $backtrace ) { 
			if ( $backtrace['function'] !== 'set_status' ) {
				$functions = trim($backtrace['class'] . '->' . $backtrace['function'] . ', ' . $functions); 
			}
		}
		$functions = substr($functions,0,strlen($functions)-1);

		$status = array(
			'code' => $id
		,	'message' => $retmsg 
		,	'operation' => $functions
		);
		array_unshift($this->status,  $status);
		return $this->status;
	}

	/**
	* Connect to MySQL server
	*
	* @since 	1.0
	* @param 	array 	$settings 	The merged array passed from either __construct() or reconnect()
	* @return 	boolean 			Whether the connection was succesful or not (passed back to parent)
	*/
	protected function connect_sql( $settings ) {
		@$conn = new mysqli( 
			$settings['host']
		,	$settings['user']
		,	$settings['pass']
		,	$settings['db']
		);
		if ( $conn && !$conn->connect_errno ) {
			$this->connection = $conn;
			$this->set_status(200);
			return TRUE;
		}
		else {
			$this->set_status( 501, $conn->connect_error, TRUE );
			return FALSE;
		}
	}

	/**
	* Executes a query against the database with the stored connection
	* - Can be used externally for manual queries
	*
	* @since 	1.0
	* @param 	string 	$sql 	The SQL statement to execute
	* @return 	mixed 			False when failed, result set on success
	*/
	public function run_query( $sql, $type = 'select' ) {
		array_unshift($this->queries, $sql);
		$conn = $this->connection;
		if ( $conn ) {

			if ( $type !== 'select' ) { $execute = $conn->query( $sql ); }
			else { $execute = $conn->query( $sql, MYSQLI_USE_RESULT ); }

			if ( $execute && !isset($execute->error)) {
				$is_object = FALSE;
				if ( $id = $conn->insert_id ) { $result = $id; }
				else { 
					$result = $execute; 
					$is_object = TRUE;
				}
				$rows = array();
				if ( $is_object === TRUE && $type == 'select' ) {
					while ( $row = $result->fetch_assoc() ) { $rows[] = $row; }
					if ( count($rows) > 0 ) { $num = $result->num_rows; }
					$result->free();
				}
				if ( count($rows) > 0 ) { 
					$result = array();
					$result['count'] = $num;
					$result['rows'] = $rows;
				}
				elseif ( $is_object === TRUE && $type !== 'select' ) { $result = 1; }
				array_unshift($this->results, $result);
				$this->set_status(200);
				return $result;
			}
			else { 
				$this->set_status( 503, $conn->error, TRUE ); 
				return FALSE;
			}
		}
		else { 
			$this->set_status(502);
			return FALSE;
		}
	}

	/**
	* Loads tables from DB for validation
	*
	* @since 	1.0
	* @return 	boolean 	FALSE on fail, TRUE on success
	*/
	protected function load_tables() {
		$conn = $this->connection;
		if ( $conn ) {
			$execute = $conn->query("SELECT * FROM information_schema.tables", MYSQLI_USE_RESULT);
			if ( $execute && !isset($execute->error)) {
				$db = $this->connection_vars['db'];
				$tables = array();
				while ( $row = $execute->fetch_assoc() ) { 
					if ( $row['TABLE_SCHEMA'] === $db )
					$tables[] = $row['TABLE_NAME']; 
				}
				$execute->free();
				$this->tables = $tables;
				$this->set_status(200);
				return TRUE;
			}
			else {
				$this->set_status( 503, $conn->error, TRUE ); 
				return FALSE;
			}
		}
		else { 
			$this->set_status(502);
			return FALSE;
		}
	}

	/**
	* Validates table names passed with tables loaded from the database
	*
	* @since 	1.0
	* @param 	string 		$table 	The table name to check
	* @return 	boolean 			TRUE on success, FALSE no failure
	*/
	protected function is_valid_table( $table ) {
		$tables = $this->tables;
		if ( is_string($table) || strlen($table) > 0 && count($tables) > 0 && in_array($table,$tables) ) { return TRUE; }
		else { return FALSE; };
	}

	/**
	* Gets the requested var from child functions
	*
	* @since 	1.0
	* @param 	string 		$what 	The variable to get
	* @param 	boolean 	$all 	If is array, whether or not to get all entries, or just the most recent
	* @return 	mixed 				FALSE on failure, the requested variable on success
	*/
	protected function get_var( $what, $all = FALSE ) {
		if ( isset($this->$what) ) {
			if ( $all === FALSE && is_array($this->$what) ) { 
				$arr = $this->$what;
				if ( count($arr) > 0 ) { return $arr[0]; }
				else { return array(); } 
			}
			else { return $this->$what; }
			$this->set_status(200);
		}
		else { 
			return FALSE; 
			$this->set_status(300);
		}
	}

	/**
	* Gets the stored result set generated from run_query()
	*
	* @since 	1.0
	* @param 	boolean 	$all 	Whether or not to get all results, or just the recent one
	* @return 	mixed 				The results of get_var()
	*/
	public function get_results( $all = FALSE ) {
		return $this->get_var( 'results', $all );
	}

	/**
	* Gets the stored queries recorded when using run_query()
	*
	* @since 	1.0
	* @param 	boolean 	$all 	Whether or not to get all queries, or just the recent one
	* @return 	mixed 				The results of get_var()
	*/
	public function get_queries( $all = FALSE ) {
		return $this->get_var( 'queries', $all );
	}

	/**
	* Gets the current status array for reading
	*
	* @since 	1.0
	* @return 	array 	The status array retreived
	*/
	public function get_status( $all = FALSE ) {
		return $this->get_var( 'status', $all );
	}


	/**
	* Takes values passed into it and performs validation / stripping based on type
	*
	* @since 	1.0
	* @param 	mixed 	$value 	The value to parse
	* @param 	string 	$type 	The type of parse to perform (default: text)
	* @return 	mixed 			The parsed value
	*/
	protected function parse_val( $value, $type = 'text' ) {
		$replaced = $value;
		$replaced = $this->connection->real_escape_string( $replaced );
		switch ($type) {
			default:
			case 'html':
				break;
			case 'text':
				$replaced = strip_tags($replaced);
				break;
			case 'number':
				break;
			case 'phone':
				break;
			case 'timestamp':
				break;
		}
		$replaced = "'" . $replaced . "'";
		return $replaced;
	}
	
	/**
	* Builds an SQL select statement and returns the results
	*
	* @since 	1.0
	* @param 	string 	$table 		The table name to select from
	* @param 	array 	$where 		An associative array of data or a string
	* @param 	mixed 	$columns 	An array of column names, a string of comma separated column names, or a single column name (or *) to return
	* @return 	mixed 				FALSE on failure, result set on success
	*/
	public function select( $table, $where = FALSE, $columns = '*' ) {
		if ( !$this->is_valid_table($table) ) {
			$this->set_status(504);
			return FALSE;
		}
		if ( ( is_string($where) && strlen($where) == 0 ) || ( is_array($where) && (!isset($where['columns']) || count($where['columns']) == 0 ) ) ) {
			$this->set_status(507);
			return FALSE;
		}
		if ( ( is_string($columns) && strlen($columns) == 0 ) || ( is_array($columns) && count($columns) == 0 ) ) {
			$this->set_status(508);
			return FALSE;
		}
		if ( is_array($where) && !isset($where['correlation']) ) { $where['correlation'] = 'AND'; }
		$sql = "SELECT ";
		if ( is_string($columns) ) {
			$sql .= $columns;
		}
		elseif ( is_array($columns) ) {
			$begin = TRUE;
			foreach ( $columns as $column ) {
				if ( $begin === FALSE ) { $sql .= ', '; }
				$sql .= $column;
				$begin = FALSE;
			}
		}
		$sql .= " FROM " . $table . " ";
		if ( is_string($where) ) {
			$sql .= "WHERE " . $where;
		}
		elseif ( is_array($where) ) {
			$sql .= "WHERE ";
			$begin = TRUE;
			foreach ( $where['columns'] as $column=>$attrs ) {
				if ( !isset($attrs['operator']) ) { $attrs['operator'] = '='; }
				if ( $begin === FALSE ) { $sql .= ' ' . $where['correlation']; }
				$sql .= $column . " " . $attrs['operator'] . " " . $this->parse_val( $attrs['value'], $attrs['type'] ) . " ";
				$begin = FALSE;
			}
		}
		$result = $this->run_query($sql);
		return $result;
	}

	/**
	* Builds an SQL update statement modify rows based on ID
	*
	* @since 	1.0
	* @param 	string 	$table 	The table name to update
	* @param 	int 	$ids 	An array of column_name=>ID to delete
	* @param 	array 	$vals 	An associative array of column_name => value pairs to update
	* @return 	mixed 			FALSE on failure to create SQL, result set on success
	*/
	public function update( $table, $ids, $vals ) {
		if ( !$this->is_valid_table($table) ) {
			$this->set_status(504);
			return FALSE;
		}
		if ( !is_array($ids) || !isset($ids['values']) || !isset($ids['column']) || count($ids) == 0 ) {
			$this->set_status(505);
			return FALSE;
		}
		if ( count($vals) == 0 ) {
			$this->set_status(506);
			return FALSE;
		}
		$sql = "UPDATE " . $table . " SET ";
		$begin = TRUE;
		foreach ( $vals as $key=>$val ) {
			if ( $begin === FALSE ) { $sql .= ', '; }
			$sql .= $key . " = " . $this->parse_val( $val );
			$begin = FALSE;
		}
		$sql .= " WHERE ";
		$begin = TRUE;
		foreach ( $ids['values'] as $val ) {
			if ( $begin === FALSE ) { $sql .= ' OR '; }
			$sql .= $ids['column'] . " = " . $this->parse_val( $val );
			$begin = FALSE;
		}
		$result = $this->run_query($sql);
		return $result;
	}

	/**
	* Builds an SQL insert statement to create new rows
	*
	* @since 	1.0
	* @param 	string 	$table 	The table name to add the row into
	* @param 	array 	$vals 	An associative array of column_name => value pairs to insert
	* @return 	mixed 			FALSE on failure to create SQL, result set on success
	*/
	public function insert( $table, $vals ) {
		if ( !$this->is_valid_table($table) ) {
			$this->set_status(504);
			return FALSE;
		}
		if ( count($vals) == 0 ) {
			$this->set_status(506);
			return FALSE;
		}
		$sql = "INSERT INTO " . $table . " (";
		$begin = TRUE;
		foreach ( $vals as $key=>$val ) {
			if ( $begin === FALSE ) { $sql .= ', '; }
			$sql .= $key;
			$begin = FALSE;
		}
		$sql .= ") VALUES (";
		$begin = TRUE;
		foreach ( $vals as $key=>$val ) {
			if ( $begin === FALSE ) { $sql .= ', '; }
			$sql .= $this->parse_val( $val ) . " ";
			$begin = FALSE;
		}
		$sql .= ")";
		$result = $this->run_query($sql);
		return $result;
	}

	/**
	* Builds an SQL insert statement to delete rows
	*
	* @since 	1.0
	* @param 	string 	$table 	The table name to add the row into
	* @param 	int 	$ids 	An array of column_name=>ID to delete
	* @return 	mixed 			FALSE on failure to create SQL, result set on success
	*/
	public function delete( $table, $ids ) {
		if ( !$this->is_valid_table($table) ) {
			$this->set_status(504);
			return FALSE;
		}
		if ( !is_array($ids) || !isset($ids['values']) || !isset($ids['column']) || count($ids) == 0 ) {
			$this->set_status(505);
			return FALSE;
		}
		$sql = "DELETE FROM " . $table . " WHERE ";
		$begin = TRUE;
		foreach ( $ids['values'] as $val ) {
			if ( $begin === FALSE ) { $sql .= ' OR '; }
			$sql .= $ids['column'] . " = " . $this->parse_val( $val );
			$begin = FALSE;
		}
		$result = $this->run_query($sql);
		return $result;
	}

	/**
	* Builds an SQL create statement to create tables
	*
	* @since 	1.1
	* @param 	array 	$tables 	An array of tables to create
	* @return 	mixed 			FALSE on failure to create SQL, result set on success
	*/
	public function create( $tables ) {
		$existing_tables = $this->tables;
		$return = FALSE;
		if ( count($tables) > 0 ) {
			$i = 0;
			foreach ( $tables as $table => $attributes ) {
				if ( !in_array($this->table_prefix . $table, $existing_tables) ) {
					$sql = "CREATE TABLE `" . $this->table_prefix . $table . "` (";
					$begin = TRUE;
					foreach ( $attributes['columns'] as $column => $properties ) {
						$comma = '';
						if ( $begin === FALSE ) { $comma = ', '; }
						$sql .= $comma . "`" . $column . "` " . $properties;
						$begin = FALSE;
					}
					if ( isset($attributes['primary_key']) && strlen($attributes['primary_key']) > 0 ) {
						$sql .= ", PRIMARY KEY (`" . $attributes['primary_key'] . "`)";
					}
					if ( isset($attributes['indexes']) && count($attributes['indexes']) > 0 ) {
						$sql .= ", INDEX `alt_index` (";
						$begin = TRUE;
						foreach ( $attributes['indexes'] as $index => $order ) {
							$comma = '';
							if ( $begin === FALSE ) { $comma = ', '; }
							$sql .= $comma  . "`" . $index . "` " . $order;
							$begin = FALSE;
						}
						$sql .= ")";
					}
					$sql .= ");";
					$return = $this->run_query($sql,'create');
					$i++;
				}
			}
			if ($i==0) { $return = TRUE; }
		}
		return $return;
	}
}