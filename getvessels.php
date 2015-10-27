<?php
	// Connecting, selecting database
	$dbconn = pg_connect("host=localhost dbname=notifytest user=postgres password=123456")
	    or die('Could not connect: ' . pg_last_error());

	// Performing SQL query
	$query = 'SELECT * FROM foo ORDER BY ID';
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	echo json_encode(pg_fetch_all($result));

	// Free resultset
	pg_free_result($result);

	// Closing connection
	pg_close($dbconn);
?>