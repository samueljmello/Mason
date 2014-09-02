Package Info
=====
Mason is a MySQL builder class for constructing queries and retrieving results using simple PHP arrays or strings when needed. Great for extending as a building block for another class.

Version
=====
Current version is 1.3


Usage
=====

<b>Public variables:</b>

<em>
$status  = Array of last recorded status message<br/>
$results = Array of all results retrieved in session<br/>
$queries = Array of all queries used in session<br/>
$tables  = Array of tables in database<br/>
</em>

<b>Public functions:</b>


<em>reconnect( $args )</em><br/>
Used to initiate connection. Happens on __construct() but can be used later to reconnect if needed.


<em>get_status() </em><br/>
Gets the stored array of statuses during session


<em>run_query( $sql, $type ) </em><br/>
Master function to run queries. Runs when using insert, update, etc; but can be used to execute custom SQL strings.


<em>get_results( $all )</em><br/>
Gets stored results from session. $all is optional and can be used to get all recorded result sets.


<em>get_queries( $all )</em><br/>
Gets stored queries from session. $all can be used to get all, or just the last one will be returned.

<em>parse_val( $value, $type)</em><br/>
Parses values according to the provided type (defaults to text) and wraps + sql escapes them. Options include:

```
html = no filters applied
text = uses strip_tags
number = preg_replace all except numbers and .
phone = preg_replace all except numbers and + (for international)
date = can be fed date strings or timestamp. formats using date("Y-m-d H:i:s")
```


<em>select( $table, $where = FALSE, $columns = '*', $args = array() )</em><br/>
Used to build a select statement. $where, $columns and $args are optional. See examples:

```php
$where = "ID = '1' AND column_1 = '2'"

/* OR */ 

$where = array(
	'correlation' => 'AND'
,	'columns' 	=> array(
		'ID' => array(
				'operator' 	=> '='
			,	'value' 	=> '1' 
			, 	'type' 		=> 'text' 	// used to determine parse methods in parse_val()
		)
	,	'column_1' => array(
				'operator' 	=> '='
			,	'value' 	=> '2' 
			, 	'type' 		=> 'text'
		)
	)
);

---

$columns = "ID, column_1, column_2";

/* OR */

$columns = array(
	'ID'
,	'column_1'
,	'column_2'
);

---

$args = array(
	'order_by' => 'column_1'
,	'order_dir' => 'ASC'
,	'limit' => '1' // OR 10, 10 to offset and paginate
);
```


<em>update( $table, $ids, $vals )</em><br/>
Used to build and excecute an update statement. All parameters required. See examples:

```php
$ids = array(
	'column' => 'ID' 	// unique ID column
	'values' => array(  // Array of ID's to delete
		'1'
	,	'2'
	)
)

// array of column name => values to update
$vals = array(
	'column_1' => 'value_1'
,	'column_2' => 'value_2'
)

OR

// array of column name => properties to update
$vals = array(
	'column_1' => array(
		'value' => 'value_1'
	,	'type' 	=> 'text'
	)
,	'column_1' => array(
		'value' => 'value_2'
	,	'type' 	=> 'html'
	)
);
```


<em>insert( $table, $vals )</em><br/>
Used to build and excecute an insert statement. All parameters required. If you fail to include a column that is required, an error will be returned. See examples:

```php
// array of column name => values to insert
$vals = array(
	'column_1' => 'value_1'
,	'column_2' => 'value_2'
,	'column_3' => 'value_3'
);

OR

// array of column name => properties to insert
$vals = array(
	'column_1' => array(
		'value' => 'value_1'
	,	'type' 	=> 'text'
	)
,	'column_1' => array(
		'value' => 'value_2'
	,	'type' 	=> 'html'
	)
);
```


<em>delete( $table, $ids )</em><br/>
Used to build and excecute a delete statement. All parameters required. See examples:

```php
$ids = array(
	'column' => 'ID' 	// unique ID column
	'values' => array(  // Array of ID's to delete
		'1'
	,	'2'
	)
);
```


<em>create( $tables )</em><br/>
Used to create tables from an array of tables and corresponding key data

```php
$tables = array(
	'table_1' => array(
		'primary_key' => 'ID'
	,	'index' => array(
			'column_3'
		,	'column_4'
		)
	,	'columns' => array(
			'column_1' => 'INT NOT NULL AUTO_INCREMENT'
		,	'column_2' => 'TEXT'
		,	'column_3' => 'INT NOT NULL'
		,	'column_4' => 'INT NOT NULL'
		)
	)
,	'table_2' => array(
		'primary_key' => 'ID'
	,	'columns' => array(
			'ID' => 'INT NOT NULL AUTO_INCREMENT'
		,	'ip_address' => 'TEXT NULL'
		,	'created_on' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
		)
	)
);
```


Examples
=====

```php
require_once('class.Mason.php');

$args = array(
	'host' 	=> 'localhost' 		// your mysql server address
,	'user' 	=> 'myusername' 	// the username to connect with
,	'pass' 	=> 'mypassword' 	// the password to connect with
,	'db' 	=> 'mydatabase' 	// the database to query
,	'debug' => FALSE 			// turn debug on or off
);

$mason = new Mason( $args );

$where = array(
	'correlation' => 'OR'
,	'columns' => array(
		'column_1' => array(
			'operator' => '='
		,	'value' => 'value_1'
		,	'type' => 'text'
		)
	)
);

$columns = array(
	'column_1'
,	'column_2'
);

$result = $gs->select( 'mytable', $where, $columns );
if ( $result && $result['count'] > 0 ) {
	foreach ( $result['rows'] as $row ) {
		// handle data here
	}
}
else {
	// no results found
}
```