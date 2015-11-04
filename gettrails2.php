<?php
	error_reporting(0);
	// Connecting, selecting database
	$serverName = "ALIENWARE-PC\SQLEXPRESS";
	$connectionInfo = array("Database" => "MVMSuite", "UID" => "SOAP", "PWD" => "123456");
	$conn = sqlsrv_connect($serverName, $connectionInfo);
	if( $conn === false ) {
		die( print_r( sqlsrv_errors(), true));
	}

	$vessels = [];
	$guide = [];
	$trails = [];

	$sql = "SELECT ID, Name, VDateTime, VLat, VLon, VSpd, VCMG, Origin FROM
			(SELECT ID, Name, VDateTime, VLat, VLon, VSpd, VCMG, Origin,
			ROW_NUMBER() OVER(PARTITION BY ID ORDER BY VDateTime DESC) as x
			FROM Trails
			)
			Trails
			WHERE x <= 6
			AND VDateTime > '2015-9-27' AND VDateTime < '2015-10-27'
			AND Origin LIKE '%AIS_%'";
	$stmt = sqlsrv_query($conn, $sql);

	while($obj = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
		array_push($guide, $obj['ID']);
		if(!isset($trails[$obj['ID']])){
			$trails[$obj['ID']] = [
				'trails' => [[$obj['VLat'], $obj['VLon']]],
				'attr' => [[$obj['VSpd'], $obj['VCMG'], $obj['VDateTime']]],
				'name' => $obj['Name'],
			];
		} else {
			array_push($trails[$obj['ID']]['trails'], [$obj['VLat'], $obj['VLon']]);
			array_push($trails[$obj['ID']]['attr'], [$obj['VSpd'], $obj['VCMG'], $obj['VDateTime']]);
		}
	}

	$len = count($guide);
	$guide = array_unique($guide);
	$i = 0;
	for ($i; $i < $len; $i++) { 
		if(isset($guide[$i])){
			array_push($vessels, [
				'vessel_id' => $guide[$i], 
				'name' => $trails[$guide[$i]]['name'], 
				'trails' => $trails[$guide[$i]]['trails'],
				'attr' => $trails[$guide[$i]]['attr']
			]);
		}
	}
	echo json_encode($vessels);
	sqlsrv_close($conn);
?>