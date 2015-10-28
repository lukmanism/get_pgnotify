<?php
	// Connecting, selecting database
	$dbconn = pg_connect("host=localhost dbname=notifytest user=postgres password=123456")
	    or die('Could not connect: ' . pg_last_error());

	// Performing SQL query
	$query = 'SELECT DISTINCT vessel_id, name FROM  foo';
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());

	$get_vessel = pg_fetch_all($result);

	$vessels = [];
	foreach ($get_vessel as $key => $v) {
		array_push($vessels, [
			'vessel_id' => $v['vessel_id'], 
			'name' => $v['name'], 
			'trails' => [],
			'attr' => []
			]
		);
	}



	$query = 'SELECT id, vessel_id, lat, lng, speed, cmg FROM
			(SELECT id, vessel_id, lat, lng, speed, cmg,
			ROW_NUMBER() OVER(PARTITION BY vessel_id ORDER BY id DESC) as x
			FROM foo
			)
			foo
			WHERE x <= 3';
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	$trails = pg_fetch_all($result);

	foreach ($vessels as $key => $value) {
		foreach ($trails as $k => $v) {
			if($v['vessel_id'] == $value['vessel_id']){
				array_push($vessels[$key]['trails'], [$v['lat'], $v['lng']]);
				array_push($vessels[$key]['attr'], [$v['speed'], $v['cmg']]);
			}
		}
	}
	echo json_encode($vessels);


	// Free resultset
	pg_free_result($result);

	// Closing connection
	pg_close($dbconn);
?>