Mason
=====

MySQL builder class for constructing queries and retrieving results using simple PHP arrays


Usage
=====

<b>Public variables:</b>

<em>
$status  = Array of last recorded status message
$results = Array of all results retrieved in session
$queries = Array of all queries used in session
$tables  = Array of tables in database
</em>

<b>Public functions:</b>

<em>reconnect( $args )</em>
Used to initiate connection. Happens on __construct() but can be used later to reconnect if needed.

<em>get_status() </em>
Gets the last recorded status message.

<em>run_query( $sql, $type ) </em>
Master function to run queries. Runs when using insert, update, etc; but can be used to execute custom SQL strings.

<em>get_results( $all )</em>
Gets stored results from session. $all is optional and can be used to get all recorded result sets.

<em>get_queries( $all )</em>
Gets stored queries from session. $all can be used to get all, or just the last one will be returned.

<em>select( $table, $where, $columns )</em>
Used to build a select statement. $where and $columns are optional and can be either string or array. See examples:

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

$columns = "ID, column_1, column_2";

/* OR */

$columns = array(
	'ID'
,	'column_1'
,	'column_2'
);
```

<em>update( $table, $ids, $vals )</em>
Used to build an update statement. All parameters required. See examples:

'''php

'''