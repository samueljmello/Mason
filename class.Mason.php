<?php

/**
* 	Simple SQL builder class with easy method names. Designed to make interaction with
* 	different versions of SQL easy. Where version 1.0 was intended for MySQLi only, version 2.0.1
* 	is designed to operate across many database types including MySQLi, MySQL, MSSQL, and SQLSRV.
* 	In the future, MSSQL PDO, ODBC, and PostGre DB's will be supported.
*
* 	@package 	Mason
* 	@author 	Samuel Mello
* 	@version 	2.0.2
* 	@license 	http://opensource.org/licenses/gpl-license.php GNU Public License
*	@todo 		Build out the prepare function
*/
final class Mason {


	/**
	* 	@access 	public
	* 	@var 		string 		$host 			The host name to connect to	
	* 	@var 		string 		$database 		The database name to perform queries on
	* 	@var 		string 		$username 		The username to connect with
	* 	@var 		string 		$password 		The password to connect with
	* 	@var 		object 		$connection 	The connection object to store
	* 	@var 		array 		$queries 		All queries performed during object life	
	* 	@var 		array 		$errors 		Any errors occurred during object life
	* 	@var 		int 		$status 		The current status (0 for failure, 1 for success)
	* 	@var 		string 		$method 		The method of database interaction (mysqli, mysql, mssql, & sqlsrv, pdo_sqlsrv)
	*/
	public 	$host = '';
	public 	$database = '';
	private $username = '';
	private $password = '';
	public 	$connection = null;
	public 	$queries = array();
	public 	$errors = array();
	public 	$status = 0;
	public 	$method = 'mssql';
	public 	$driver = '';


	/**
	* 	Constructs a new Mason SQL object and sets it's properties, then connects
	*
	* 	@access 	public
	* 	@param 		array 		$args 		Array of arguments to set
	* 	@return 	bool 					The success or failure of the connection method
	* 	@since 		2.0.1
	*/
	public function __construct($args) {
		$defaults = array(
			'host' 		=> ''
		,	'database' 	=> ''
		,	'username' 	=> ''
		,	'password' 	=> ''
		,	'method' 	=> ''
		,	'driver' 	=> ''
		);
		$details = array_merge($defaults,$args);
		foreach ( $details as $propName=>$propVal ) {
			if ( property_exists($this, $propName) ) {
				$this->$propName = $propVal;
			}
		}
		return $this->connect();
	}


	/**
	* 	Destructs the object and forces connections closed
	*
	* 	@access 	public
	* 	@since 		2.0.1
	*/
	public function __destruct() {
		if ( $this->connection ) {
			switch ( $this->method ) {
				case 'mysqli': $this->connection->close(); break;
				case 'mysql': mysql_close($this->connection); break;
				case 'mssql': mssql_close($this->connection); break;
				case 'sqlsrv': sqlsrv_close($this->connection); break;
			}
		}
		return;
	}


	/**
	* 	Checks to make sure this method exists for Mason to use
	*
	* 	@access 	private
	* 	@return 	bool 		$return
	* 	@since 		2.0.1
	*/
	private function drivers_exist() {
		$return = FALSE;
		switch ($this->method) {
			case 'mysqli':
			case 'mysql': 
			case 'mssql': 
			case 'sqlsrv':
				if ( function_exists($this->method . '_connect') ) {
					$return = TRUE;
				}
				break;
			case 'pdo_odbc':
			case 'pdo_mysql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				if ( class_exists('PDO') && in_array(str_replace('pdo_', '', $this->method),PDO::getAvailableDrivers()) ) {
					$return = TRUE;
				}
				break;
		}
		return $return;
	}


	/**
	* 	Connects to the server and selects the DB using the stored properties set in __construct
	*
	* 	@access 	private
	* 	@return 	bool 		$return 		The success or failure of the connection
	* 	@since 		2.0.1
	*/
	private function connect() {
		$return = FALSE;
		$connected = FALSE;
		if ( $this->drivers_exist() ) {
			try {
				switch ($this->method) {
					case 'mysqli':
						$connected = new mysqli($this->host, $this->username, $this->password);
						break;
					case 'mysql': 
						@$connected = mysql_connect($this->host, $this->username, $this->password); 
						break;
					case 'mssql': 
						@$connected = mssql_connect($this->host, $this->username, $this->password);
						break;
					case 'sqlsrv':
						$connection_info = array(
							"Database" 	=> $this->database
						,	"UID" 		=> $this->username
						,	"PWD" 		=> $this->password
						);
						@$connected = sqlsrv_connect($this->host, $connection_info);
						break;
					case 'pdo_mysql':
						@$connected = new PDO('mysql:host=' . $this->host .';dbname=' . $this->database, $this->username, $this->password);
						break;
					case 'pdo_mssql':
						@$connected = new PDO('mssql:host=' . $this->host .';dbname=' . $this->database, $this->username, $this->password);
						break;
					case 'pdo_sqlsrv':
						@$connected = new PDO('sqlsrv:server=' . $this->host .';database=' . $this->database, $this->username, $this->password);
						break;
						$this->errors[] = 'Unsuported method being called: ' . $this->method;
						break;
				}
			}
			catch(Exception $e) {
				$this->errors[] = $e->getMessage();
			}
		}
		else {
			$this->errors[] = 'Server does not support this method or driver is not installed.';
		}
		if ( $connected ) {
			$this->connection = $connected;
			switch ($this->method) {
				case 'mysqli': 
					@$selected_db = $this->connection->select_db($this->database); 
					break;
				case 'mysql': 
					@$selected_db = mysql_select_db($this->database, $this->connection);
					break;
				case 'mssql': 
					@$selected_db = mssql_select_db($this->database, $this->connection); 
					break;
				case 'sqlsrv':
				case 'pdo_mysql':
				case 'pdo_mssql':
				case 'pdo_sqlsrv':
					$selected_db = TRUE; 
					break;
			}
			if ( $selected_db ) {
				$this->status = 1;
				$return = TRUE;
			}
			else { 
				$this->errors[] = 'Could not select database: ' . $this->database;
			}
		}
		else {
			$this->errors[] = 'Could not connect to server.';
		}
		return $return;
	}


	/**
	* 	Prepares and executes a query against the server, then returns the results
	*
	* 	@access 	public
	* 	@param 		string 		$sql 		The SQL statement to execute
	* 	@return 	mixed 		$return 	TRUE / FALSE on success failure, insert_id on INSERT, and rows on SELECT
	* 	@since 		2.0.1
	*/
	public function query($sql) {
		$return = FALSE;
		if ( is_string($sql) && strlen($sql) > 0 ) {
			$result = $this->execute($sql);
			if ( $result ) {
				if ( $this->is_select($sql) && !$this->is_pdo_type() ) {
					$count = $this->count($result);
					if ( $count > 0 ) {
						$return = $this->rows($result);
					}
					else {
						if ( $count === FALSE ) {
							$this->errors[] = 'Error counting rows.';
						}
						else {
							$this->errors[] = 'No results were returned.';
						}
					}
				}
				elseif ( $this->is_insert($sql) ) {
					$return = $this->insert_id($result);
				}
				elseif ( $this->is_pdo_type() ) {
					$rows = $this->rows($result);
					if ( count($rows) > 0 ) {
						$return = $rows;
					}
					else {
						$this->errors[] = 'No results were returned.';
					}
				}
				else {
					$return = TRUE;
				}
			}
			else {
				$this->errors[] = 'An error ocurred.';
			}
		}
		else {
			$this->errors[] = 'No query provided.';
		}
		return $return;
	}


	/**
	* 	Performs the query, used by method 'query'
	*
	* 	@access 	private
	* 	@param 		string 		$sql 		The SQL statement to execute
	* 	@return 	mixed 		$return 	FALSE on failure, resource on success.
	* 	@since 		2.0.1
	*/
	private function execute($statement) {
		$result = FALSE;
		$this->queries[] = $statement;
		switch ( $this->method ) {
			case 'mysqli': 
				@$result = $this->connection->query($statement); 
				break;
			case 'mysql': 
				@$result = mysql_query($statement,$this->connection); 
				break;
			case 'mssql':
				@$result = mssql_query($statement,$this->connection);
				break;
			case 'sqlsrv': 
				@$result = sqlsrv_query($this->connection,$statement,null,array('Scrollable'=>SQLSRV_CURSOR_STATIC)); 
				break;
			case 'pdo_mysql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				@$result = $this->connection->query($statement);
				break;
		}
		if ( !$result ) {
			switch ( $this->method ) {
				case 'mysqli':
					$this->errors[] = $this->connection->error;
					break;
				case 'mysql':
					$this->errors[] = mysql_error();
					break;
				case 'mssql':
					$this->errors[] = mssql_get_last_message();
					break;
				case 'sqlsrv':
					$this->errors[] = sqlsrv_errors()[0]['message'];
					break;
				case 'pdo_mysql':
				case 'pdo_mssql':
				case 'pdo_sqlsrv':
					$this->errors[] = $this->connection->errorInfo();
					break;
			}
		}
		return $result;
	}


	/**
	* 	Prepares the SQL statement
	*
	* 	@access 	public
	* 	@param 		string 		$statement 		The SQL prepare statement
	* 	@param 		array 		$vals 			The array of values for this prepared statement
	* 	@since 		2.0.1
	* 	@todo 		This method under construction
	*/
	public function prepared($statement,$vals) {
		$return = FALSE;
		$query = array(
			'sql' => $statement
		,	'values' => $vals
		);
		$this->queries[] = $query;
		switch ( $this->method ) {
			case 'mysqli': 
				$this->errors[] = 'Under construction.';
				break;
			case 'mysql': 
			case 'mssql':
			case 'sqlsrv': 
				$this->errors[] = 'Does not support prepare statements.';
				break;
			case 'pdo_mysql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				try {
					$prepared = $this->connection->prepare($statement);
					$exec = $prepared->execute($vals);
					if ( $exec ) {
						if ( ( $this->is_select($statement) || $this->is_mssql_type() ) && !$this->is_update($statement) ) {
							$return = $prepared->fetchAll(PDO::FETCH_ASSOC);
						}
						elseif ( $this->is_insert($statement) ) {
							$return = $this->connection->lastInsertId();
						}
						else {
							$return = TRUE;
						}
					}
					else {
						$error = $prepared->errorInfo();
						$error['sql'] = $query;
						$this->errors[] = $error;
					}
				}
				catch( PDOException $e ) {
					$this->errors[] = $e;
				}
				break;
		}
		return $return;
	}

	private function prepare($statement) {

	}


	/**
	* 	Applys database-specific escapes on the variable
	*
	* 	@access 	private
	* 	@param 		mixed 		$var 		The variable to escape
	* 	@return 	mixed 		$var 		The variable after escape applied
	* 	@since 		2.0.1
	*/
	public function escape($var) {
		$prepared = FALSE;
		switch ( $this->method ) {
			case 'mysqli':
				$var = "'" . $this->connection->real_escape_string($var) . "'";
				break;
			case 'mysql':
				$var = "'" . mysql_real_escape_string($var) . "'";
				break;
			case 'pdo_mysql':
				$var = "'" . str_replace("'","\\'",$var) . "'"; 
				break;
			case 'sqlsrv':
			case 'mssql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				$var = "'" .str_replace("'","''",$var) . "'";
				break;
		}
		return $var;
	}


	/**
	* 	Counts the results from a returned resource
	*
	* 	@access 	private
	* 	@param 		resource 	$resource 		The resource returned from the execute method
	* 	@return 	int 		$count 			The number of rows returned
	* 	@since 		2.0.1
	*/
	private function count($resource) {
		$count = 0;
		switch ( $this->method ) {
			case 'mysqli': 
				$count = $resource->num_rows;
				break;
			case 'mysql': 
				$count = mysql_num_rows($resource);
				break;
			case 'mssql':
				$count = mssql_num_rows($resource);
				break;
			case 'sqlsrv':
				$count = sqlsrv_num_rows($resource);
				break;
		}
		return $count;
	}


	/**
	* 	Returns the result set rows from a returned resource
	*
	* 	@access 	private
	* 	@param 		resource 	$resource 		The resource returned from the execute method
	* 	@return 	mixed 		$rows 			FALSE on failure, array of data on success
	* 	@since 		2.0.1
	*/
	private function rows($resource) {
		$rows = FALSE;
		switch ( $this->method ) {
			case 'mysqli':
				while ( $row = $resource->fetch_assoc() ) { 
					if ( !$rows ) {
						$rows = array();
					}
					$rows[] = $row;
				}
				break;
			case 'mysql':
				while ( $row = mysql_fetch_array($resource) ) {
					if ( !$rows ) {
						$rows = array();
					}
					$rows[] = $row;
				}
				break;
			case 'mssql':
				while ( $row = mssql_fetch_array($resource) ) {
					if ( !$rows ) {
						$rows = array();
					}
					$rows[] = $row;
				}
				break;
			case 'sqlsrv':
				while ( $row = sqlsrv_fetch_array($resource) ) {
					if ( !$rows ) {
						$rows = array();
					}
					$rows[] = $row;
				}
				break;
			case 'pdo_mysql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				$rows = $resource->fetchAll(PDO::FETCH_ASSOC);
				break;
		}
		return $rows;
	}


	/**
	* 	Gets the insert id of the last INSERT query executed
	*
	* 	NOTES: Since MSSQL and SQLSRV have no insert_id function, you must append your insert queries
	* 	with OUTPUT INSERT.* (where * is your ID column name) before VALUES. This will send back an array
	* 	of data with the column as the result.
	*
	*	Example: INSERT INTO table (column_1, column_2) OUTPUT INSERTED.column_ID VALUES ('value_1','value_2')
	*
	* 	@access 	private
	* 	@return 	mixed 		$id 		FALSE on failure, ID for inserted row on success
	* 	@since 		2.0.1
	*/
	private function insert_id($result) {
		$id = FALSE;
		switch ( $this->method ) {
			case 'mysqli':
				$id = $this->connection->insert_id;
				break;
			case 'mysql':
				$id = mysqli_insert_id($this->connection);
				break;
			case 'mssql':
			case 'sqlsrv':
				$id = $this->rows($result);
				break;
			case 'pdo_mysql':
			case 'pdo_mssql':
			case 'pdo_sqlsrv':
				$id = $this->connection->lastInsertId();
				break;
		}
		return $id;
	}


	/**
	* 	Checks to see if this is mssql srvr type
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_mssql_type() {
		$mssql_methods = array(
			'mssql'
		,	'sqlsrv'
		,	'pdo_mssql'
		,	'pdo_sqlsrv'
		);
		if ( in_array($this->method, $mssql_methods) ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}


	/**
	* 	Checks to see if this is pdo type
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_pdo_type() {
		$mssql_methods = array(
			'pdo_mssql'
		,	'pdo_mysql'
		,	'pdo_sqlsrv'
		);
		if ( in_array($this->method, $mssql_methods) ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	* 	Returns an array of tables in this database
	*
	* 	@access 	public
	* 	@return 	array 		$return 		The array of tables retrieved
	* 	@since 		2.0.1
	*/
	public function tables() {
		$return = array();
		$result = $this->query('SELECT Distinct TABLE_NAME FROM information_schema.TABLES');
		if ( $result && is_array($result) && count($result) > 0 ) {
			foreach ( $result as $row ) {
				$return[] = $row['TABLE_NAME'];
			}
		}
		return $return;
	}


	/**
	* 	Tests SQL statement to see if it's a select and returns TRUE/FALSE
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_select($statement) {
		if ( stripos(trim($statement), 'SELECT') === 0 ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	* 	Tests SQL statement to see if it's an insert and returns TRUE/FALSE
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_insert($statement) {
		if ( stripos(trim($statement), 'INSERT') === 0 ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	* 	Tests SQL statement to see if it's an update and returns TRUE/FALSE
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_update($statement) {
		if ( stripos(trim($statement), 'UPDATE') === 0 ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	* 	Tests SQL statement to see if it's a delete and returns TRUE/FALSE
	*
	* 	@access 	public
	* 	@return 	bool
	* 	@since 		2.0.1
	*/
	public function is_delete($statement) {
		if ( stripos(trim($statement), 'DELETE') === 0 ) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}
}