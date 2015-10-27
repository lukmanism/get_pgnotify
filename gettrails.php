<?php
	// Connecting, selecting database
	$dbconn = pg_connect("host=localhost dbname=notifytest user=postgres password=123456")
	    or die('Could not connect: ' . pg_last_error());

	// Performing SQL query
	$query = 'SELECT DISTINCT name FROM  foo';
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());

	$get_vessel = pg_fetch_all($result);

	$vessels = [];
	foreach ($get_vessel as $key => $v) {
		array_push($vessels, ['name' => $v['name'], 'trails' => []]);
	}



	$query = 'SELECT id, name, lat, lng FROM
			(SELECT id, name, lat, lng, 
			ROW_NUMBER() OVER(PARTITION BY name ORDER BY id DESC) as x
			FROM foo
			)
			foo
			WHERE x <=5';
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	$trails = pg_fetch_all($result);

	foreach ($vessels as $key => $value) {
		foreach ($trails as $k => $v) {
			if($v['name'] == $value['name']){
				array_push($vessels[$key]['trails'], [$v['lat'], $v['lng']]);
			}
		}
	}
	echo json_encode($vessels);


	// Free resultset
	pg_free_result($result);

	// Closing connection
	pg_close($dbconn);
?>