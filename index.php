<!DOCTYPE html>
<html>
<head>
	<title>Get PG NOTIFY</title>
	<link rel="stylesheet" type="text/css" href="bower_components/bower-ol3/css/ol.css">
	<link rel="stylesheet" type="text/css" href="bower_components/bootstrap/dist/css/bootstrap.css">
	<link rel="stylesheet" type="text/css" href="bower_components/bootstrap/dist/css/bootstrap-theme.css">
	<link rel="stylesheet" type="text/css" href="css/style.css">	
	<style>
	  .map {
		height: 600px;
		width: 100%;
	  }
	</style>
</head>
<body>

	<!-- <div class="container"> -->
		<header></header>
		<!-- <div class="main"> -->
			<div id="map" class="map"><div id="popup"></div></div>
			<div class="">
				<h4>Status: <span id="status">Disconnected</span></h4>
				<ul id="messages"></ul>
			</div>
		<!-- </div> -->
		<footer></footer>
	<!-- </div> -->

	<script src="bower_components/jquery/dist/jquery.js"></script>
	<script src="bower_components/bootstrap/dist/js/bootstrap.js"></script>
	<script src="bower_components/bower-ol3/build/ol.js"></script>
	<script src="http://localhost:3000/socket.io/socket.io.js"></script>
	<script>

//  Map init

		var socket = io.connect('http://localhost:3000');
		socket.on('pg notify', function(msg){
			connect_api();
		}).on('connect', function() {
			$('#status').text("Connected");
		}).on('disconnect', function() {
			$('#status').text("Disconnected");
		});


		var map = new ol.Map({
			target: 'map',
			layers: [
				new ol.layer.Tile({source: new ol.source.OSM()})
			],
			view: new ol.View({
				center: [11919200.410238788, 651686.8716054037],
				zoom: 6,
				projection: 'EPSG:3857'
			})
		});

		connect_api(); // init webservices

//  Start Marker & Trail Layers Style

		var vectorSource = new ol.source.Vector({});
		var style_cache = {};

		function get_style(feature, resolution){
			var type = feature.get('type');
			var style;
			switch(type){
				case 'trails':
					style = {
						stroke: new ol.style.Stroke({
							color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
							width: 0.5,
							lineDash: [4,4]
						})						
					}
				break;
				case 'marker':
					style = {
						image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
							anchor: [19, 32],
							anchorXUnits: 'pixels',
							anchorYUnits: 'pixels',
							opacity: 0.5,
							src: 'images/marker.png',
							snapToPixel: true
						}))
					}				
				break;
				case 'marker-ais':
					style = {
						image: new ol.style.Circle({
							radius: 6,
							fill: new ol.style.Fill({
								color: 'rgba(255,255,255,0.1)'
							}),
							stroke: new ol.style.Stroke({
								color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
								width: 0.5,
								lineDash: [2,2]
							})
						})
					}
				break;
				case 'joint-ais':
					style = {
						image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
							anchor: [12, 6],
							anchorXUnits: 'pixels',
							anchorYUnits: 'pixels',
							opacity: feature.get('opacity'),
							src: 'images/arrow_white.png',
							rotation: feature.get('rotation'),
							snapToPixel: true
						}))
					}	
				break;
				case 'joint':
					style = {
						image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
							anchor: [12, 6],
							anchorXUnits: 'pixels',
							anchorYUnits: 'pixels',
							opacity: feature.get('opacity'),
							src: 'images/arrow.png',
							rotation: feature.get('rotation'),
							snapToPixel: true
						}))
					}	
				break;
				case 'marker-stop':
					style = {
						image: new ol.style.RegularShape({
							points: 3,
							radius: 8,
							fill: new ol.style.Fill({
								color: 'rgba(255,255,255,0.35)'
							}),
							stroke: new ol.style.Stroke({
								color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
								width: 0.5,
								lineDash: [2,2]
							})
						})
					}	
				break;
			}
			style_cache[type] = new ol.style.Style(style);
			return [style_cache[type]];
		}

		var vectorLayer = new ol.layer.Vector({
			source: vectorSource,
			style: get_style
		});

		map.addLayer(vectorLayer);

//  Start PopPop

		var element = $('#popup');
		var popup = new ol.Overlay({
			element: element,
			offset : [8,-20],
			stopEvent: true
		});
		map.addOverlay(popup);
		map.on('click', function(evt){
			var feature = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer){
				return feature;
			});

			if(feature){
				var geometry = feature.getGeometry();
				var coord = geometry.getCoordinates();
				popup.setPosition(coord);
				element.popover({
					trigger: 'manual',
					placement: 'right',
					html: true,
					title: 'TITLE',
					content: 'CONTENT'
				}).popover('show');
				$('#map .popover-title').html('<h4>'+feature.get('name')+'</h4>');
				$('#map .popover-content').html(
					'<div><b>MMSI: </b>' + feature.get('vessel_id') + '</div>'
					+ '<h5>' + feature.get('point') + '</h5>'
					+ '<h5>' + feature.get('timestamp') + '</h5>'
				);				
			} else {
				element.popover('destroy');
			}
		});

// 	Listeners

		map.on('moveend', function(){
			joints_init();
			trails_init();
		});
		
		var joint_test = false;
		function joints_init(){
			var zoom = map.getView().getZoom();
			var limit = 9;
			var i = 0, len = (joints.length - 1);
			if(zoom >= limit && joint_test === false){
				for(i; i < len; i++) {
					vectorSource.addFeature(joints[i]);
				}
				joint_test = true;
			} else if(zoom <= (limit-1) && joint_test === true){
				for(i; i < len; i++) {
					vectorSource.removeFeature(joints[i]);
				}
				joint_test = false;		
			}
		}

		var trail_test = false;
		function trails_init(){
			var zoom = map.getView().getZoom();
			var limit = 8;
			var i = 0, len = (trails.length - 1);
			if(zoom >= limit && trail_test === false){
				for(i; i < len; i++) {
					vectorSource.addFeature(trails[i]);
				}
				trail_test = true;
			} else if(zoom <= (limit-1) && trail_test === true){
				for(i; i < len; i++) {
					vectorSource.removeFeature(trails[i]);
				}
				trail_test = false;		
			}
		}


//  Methods

		function connect_api(){
			$.ajax({
				url: 'https://localhost/test/socket-io/get_pgnotify/gettrails2.php'
			}).done(function(data){
				vectorSource.clear();
				var vessels = $.parseJSON(data);
				var i = 0, len = (vessels.length - 1);
				for(i; i < len; i++) {
					var color = get_color();
					var distance = 5; // KM
					add_marker(vessels[i], color, distance, '-ais');
				}

			});
		}

		var joints = [];
		var trails = [];
		function add_marker(data, color, set_distance, data_type){
			if(data.trails.length >= 2){ // Vessels with more than one trail update will have trail lines.
				var k = 0, len = (data.trails.length - 1);
				for(k; k < len; k++) {
					var opacity = ((len-k)/len).toFixed(1);
					var start = {
						lng: parseFloat(data.trails[k][1]),
						lat: parseFloat(data.trails[k][0])
					}, end = {
						lng: parseFloat(data.trails[k+1][1]),
						lat: parseFloat(data.trails[k+1][0])
					}

					var rotation = get_rotation(start.lat, start.lng, end.lat, end.lng);

					var timestamp = (typeof data.attr[k][2] === 'object')? data.attr[k][2]['date'] : data.attr[k][2];

					if(k === 0){
						var marker = new ol.Feature({
							color		: color,
							data_type	: data_type,
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: 0.25,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'marker' + data_type,
							vessel_id	: data.vessel_id
						});	
					} else {
						joints.push(new ol.Feature({
							color		: color,
							data_type	: data_type,
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: opacity,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'joint' + data_type,
							vessel_id	: data.vessel_id
						}));
					}

					var lines = [
						ol.proj.fromLonLat([start.lng, start.lat]),
						ol.proj.fromLonLat([end.lng, end.lat])
					];
					trails.push(new ol.Feature({
						color		: color,
						geometry	: new ol.geom.LineString(lines, 'XYZM'),
						name		: data.name,
						opacity		: opacity,
						type		: 'trails',
						vessel_id	: data.vessel_id
					}));				

					if(typeof marker != 'undefined'){
						vectorSource.addFeature(marker);
					}
				}
				
			} else if(data.trails.length === 1){ // Vessels with only one trail update are consider stalled.
				var start = {
					lng: parseFloat(data.trails[0][1]),
					lat: parseFloat(data.trails[0][0])
				}
				var timestamp = (typeof data.attr[0][2] === 'object')? data.attr[0][2]['date'] : data.attr[0][2];

				var marker_stop = new ol.Feature({
					color		: color,
					data_type	: data_type,
					geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
					name		: data.name,
					opacity		: 0.9,
					point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
					rotation	: 0,
					timestamp	: new Date(timestamp).toUTCString(),
					type		: 'marker-stop',
					vessel_id	: data.vessel_id
				});
				vectorSource.addFeature(marker_stop);
			}
		}	


		function get_color() {
			return (
				Math.floor(Math.random() * 200) + ',' + 
				Math.floor(Math.random() * 200) + ',' + 
				Math.floor(Math.random() * 200)
			);
		}

		function to_rad(x) {
			return x * Math.PI / 180;
		}

		function spherical_cosinus(lat1, lon1, lat2, lon2) {
			var R = 6371; // km
			var dLon = to_rad(lon2 - lon1),
			lat1 = to_rad(lat1),
			lat2 = to_rad(lat2),
			d = Math.acos(Math.sin(lat1)*Math.sin(lat2) + Math.cos(lat1)*Math.cos(lat2) * Math.cos(dLon)) * R;
			return d;
		}

		function get_rotation(lat1, lon1, lat2, lon2){
			var dx =  lat1 - lat2;
			var dy =  lon1 - lon2;
			return Math.atan2(dy, dx);
		}	
		
	</script>

</body>
</html>