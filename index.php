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

// Variables
		// set global features init values
		init_test = {
			joints: [],
			trails: [],
			markers: [],
			markers_stop: []
		}, vessels = [], selected_vessel_id = 0;
		var style_cache = {};

//  Map init

		var socket = io.connect('http://localhost:3000');
		socket.on('pg notify', function(msg){
			connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails.php', ''); // init webservices
			// connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails2.php', '_ais');
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
				zoom: 5,
				projection: 'EPSG:3857'
			})
		});

		var vectorSource = new ol.source.Vector({});
		var vectorLayer = new ol.layer.Vector({
			source: vectorSource,
			style: get_style
		});
		map.addLayer(vectorLayer);

		connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails.php'); // init webservices
		connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails2.php', 'ais'); // init webservices

//  Marker & Trail Layers Style

		function get_style(feature, resolution){
			var type = (typeof feature.get('data_type') === 'undefined')? feature.get('type'): feature.get('type') + '_ais';
			var style;
			switch(type){
				case 'trails':
				case 'trails_ais':
					style = {
						stroke: new ol.style.Stroke({
							color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
							width: 0.5,
							lineDash: [4,4]
						})						
					}
				break;
				case 'markers':
					style = {
						image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
							anchor: [19, 32],
							anchorXUnits: 'pixels',
							anchorYUnits: 'pixels',
							opacity: feature.get('opacity'),
							src: 'images/marker.png',
							snapToPixel: true,
						}))
					}				
				break;
				case 'markers_ais':
					style = {
						image: new ol.style.Circle({
							radius: 6,
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
				case 'joints_ais':
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
				case 'joints':
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
				case 'markers_stop':
				case 'markers_stop_ais':
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

			if(selected_vessel_id > 0) {
				features_onclick(selected_vessel_id, false); // reset feature highlight
			}
			var vessel_id = (feature)? feature.get('vessel_id'): selected_vessel_id;
			if(feature && (feature.get('type') != 'trails')){
				features_onclick(feature.get('vessel_id'), true); // feature highlight
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
				selected_vessel_id = feature.get('vessel_id');
			} else {
				selected_vessel_id = 0;
				element.popover('destroy');
				if(vessel_id > 0) {
					features_onclick(vessel_id, false); // reset feature highlight
				}
			}
		});

// 	Listeners

		map.on('moveend', function(){
			for(var vkey in vessels) {
				if (vessels.hasOwnProperty(vkey)) {
					get_features(vkey);
				}
			}
		});

		function features_onclick(vessel_id, active){
			var vessel = vessels[vessel_id][0];
			for(var key in vessel) {
				if (vessel.hasOwnProperty(key) && vessel[key].length >= 1) {
					init_test[key][vessel_id] = false;
					feature_init(vessel[key], 0, key, active);
				}
			}			
		}

		function get_features(vessel_id){
			for(var key in init_test) {
				var vessel = vessels[vessel_id][0][key];
				if (init_test.hasOwnProperty(key) && vessel.length >= 1) {
					feature_init(vessel, vessel[0].get('zoom_limit'), vessel[0].get('type'));
				}
				// TODO: if(Object.keys(vessels[vessel_id]) >= 2)
			}
		}

//  Methods
		
		function connect_api(url, data_type){
			$.ajax({
				url: url
			}).done(function(data){
				// vectorSource.clear();
				var datas = $.parseJSON(data);
				var i = 0, len = datas.length;
				for(i; i < len; i++) {
					var color = get_color();
					var distance = 5; // KM
					var temp = add_marker(datas[i], color, distance, data_type);
					if(typeof vessels[datas[i]['vessel_id']] === 'undefined'){
						vessels[datas[i]['vessel_id']] = [temp];
					} else {
						vessels[datas[i]['vessel_id']].push(temp); // captures duplication from two different webservice if availables
					}
					for(var key in init_test) {
						if (init_test.hasOwnProperty(key) && temp[key].length >= 1) {
							feature_init(temp[key], temp[key][0].get('zoom_limit'), temp[key][0].get('type'));
						}
					}
				}
			});
		}

		function add_marker(data, color, set_distance, data_type){
			var feature = {
				joints: [],
				trails: [],
				markers: [],
				markers_stop: [],
			};
			var vessel_id = parseFloat(data.vessel_id);

			if(data.trails.length >= 2){ // Vessels with more than one trail update will have trail lines.
				var k = 0, len = (data.trails.length - 1);
				for(k; k < len; k++) {
					var opacity = ((len-k)/(len*1.5/* buffer extra .5 for click-to-highlight */)).toFixed(2);
					var start = {
						lng: parseFloat(data.trails[k][1]),
						lat: parseFloat(data.trails[k][0])
					}, end = {
						lng: parseFloat(data.trails[k+1][1]),
						lat: parseFloat(data.trails[k+1][0])
					}

					// var get_distance = spherical_cosinus(start.lat, start.lng, end.lat, end.lng);
					var rotation = get_rotation(start.lat, start.lng, end.lat, end.lng);
					var timestamp = (typeof data.attr[k][2] === 'object')? data.attr[k][2]['date'] : data.attr[k][2];

					if(k === 0){
						feature['markers'].push(new ol.Feature({
							color		: color,
							data_type	: data_type,
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: 0.8,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'markers',
							vessel_id	: vessel_id,
							zoom_limit	: 0
						}));	
					} else {
						feature['joints'].push(new ol.Feature({
							color		: color,
							data_type	: data_type,
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: opacity,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'joints',
							vessel_id	: vessel_id,
							zoom_limit	: 8 
						}));
					}

					var lines = [
						ol.proj.fromLonLat([start.lng, start.lat]),
						ol.proj.fromLonLat([end.lng, end.lat])
					];
					feature['trails'].push(new ol.Feature({
						color		: color,
						data_type	: data_type,
						geometry	: new ol.geom.LineString(lines, 'XYZM'),
						name		: data.name,
						opacity		: opacity,
						type		: 'trails',
						vessel_id	: vessel_id,
						zoom_limit	: 7 
					}));				

					// if(get_distance >= set_distance) // show if distance >= set_distance(KM)
				}
				
			} else if(data.trails.length === 1){ // Vessels with only one trail update are consider stalled.
				var start = {
					lng: parseFloat(data.trails[0][1]),
					lat: parseFloat(data.trails[0][0])
				}
				var timestamp = (typeof data.attr[0][2] === 'object')? data.attr[0][2]['date'] : data.attr[0][2];

				feature['markers_stop'].push(new ol.Feature({
					color		: color,
					data_type	: data_type,
					geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
					name		: data.name,
					opacity		: 0.8,
					point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
					rotation	: 0,
					timestamp	: new Date(timestamp).toUTCString(),
					type		: 'markers_stop',
					vessel_id	: vessel_id,
					zoom_limit	: 0 
				}));
			}

			for(var key in init_test) {
				if (init_test.hasOwnProperty(key)) {
					init_test[key][vessel_id] = false;
				}
			}
			return feature;
		}

		function feature_init(features, limit, type, highlight){

			// if(type == 'markers')
			// console.log(type, features[0].get('opacity'))

			var id = features[0].get('vessel_id');
			var zoom = map.getView().getZoom();
			var i = 0, len = (features.length - 1);
			if(zoom >= limit && init_test[type][id] === false){




				for(i; i <= len; i++) {
					if(typeof highlight === 'undefined'){
						vectorSource.addFeature(features[i]);						
					} else if(highlight){
						features[i].set('opacity', 1);						
					} else if(!highlight){
						var opacity = (type != 'markers')?((len-i)/(len*1.5/* buffer extra .5 for click-to-highlight */)).toFixed(2): 0.8;
						features[i].set('opacity', opacity);	
					}
				}



				init_test[type][id] = true;
			} else if(zoom <= (limit-1) && init_test[type][id] === true){
				for(i; i <= len; i++) {
					vectorSource.removeFeature(features[i]);
				}
				init_test[type][id] = false;
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