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

		var cache = { // set init values
			style: {
				'vector': [],
				'cluster': [],
			},
			source: {
				'vector': [],
				'cluster': [],
			},
			vessels: [],
			init_test: {
				joints: [],
				trails: [],
				markers: [],
				markers_stop: []				
			},
			selected: {
				vessel_id: 0,
				style: ''
			}
		};

		var ws = [
			{
				url: 'https://localhost/test/socket-io/get_pgnotify/gettrails.php',
				type: 'clients_vessels',
				style: 'vector', // vector, cluster
				zoom_limit: {
					joints: 9,
					trails: 7
				}
			},
			{
				url: 'https://localhost/test/socket-io/get_pgnotify/gettrails2.php',
				type: 'ais',
				style: 'vector', // vector, cluster
				zoom_limit: {
					joints: 10,
					trails: 7
				}
			}
		];

//  Map init

		// var socket = io.connect('http://localhost:3000');
		// socket.on('pg notify', function(msg){
		// 	connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails.php', ''); // init webservices
		// 	connect_api('https://localhost/test/socket-io/get_pgnotify/gettrails2.php', '_ais');
		// }).on('connect', function() {
		// 	$('#status').text("Connected");
		// }).on('disconnect', function() {
		// 	$('#status').text("Disconnected");
		// });

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

		var n = 0, ws_len = Object.keys(ws).length;
		for(n; n < ws_len; n++) {
			if(Object.keys(cache['source'][ws[n]['style']]).length === 0){
				if(ws[n]['style'] === 'cluster'){
					cache['source'][ws[n]['style']] = new ol.source.Vector({});
					layer_init(new ol.source.Cluster({
						distance: 20,
						source: cache['source'][ws[n]['style']]
					}), get_cluster_style);				
				} else {
					cache['source'][ws[n]['style']] = new ol.source.Vector({});
					layer_init(cache['source'][ws[n]['style']], get_style);					
				}
			}
			connect_api(ws[n]);
		}

//  Start PopPop

		var element = $('#popup');
		var popup = new ol.Overlay({
			element: element,
			offset : [8,-42],
			stopEvent: true
		});
		map.addOverlay(popup);

		map.on('click', function(evt){
			var feature = map.forEachFeatureAtPixel(evt.pixel, function(feature, layer){
				return feature;
			});
			var vessel = (typeof feature != 'undefined')? feature : false;

			if(feature){ // markers clicked
				if($.inArray('features', feature.getKeys()) >= 0){ // cluster of markers clicked
					if(vessel.getProperties().features.length === 1){ // show only vessel length === 1
						marker_popup(vessel.getProperties().features[0], popup, true);
					} else {
						marker_popup(false, popup, false); // reset cluster-marker poppop
					}
				} else if(feature.get('type') != 'trails'){ // markers clicked
					marker_popup(vessel, popup, true);
				}
			} else { // outside markers clicked
				marker_popup(false, popup, false); // reset cluster & vector-marker poppop
			}
		});

// 	Listeners

		map.on('moveend', function(){
			for(var vkey in cache['vessels']) {
				if (cache['vessels'].hasOwnProperty(vkey)) {
					get_features(vkey);
				}
			}
		});

//  Methods
		function layer_init(source, style){
			var layer = new ol.layer.Vector({
				source: source,
				style: style
			});
			map.addLayer(layer);		
		}

		function connect_api(ws){
			$.ajax({
				url: ws['url']
			}).done(function(data){
				// vectorSource.clear();
				var datas = $.parseJSON(data);
				var i = 0, len = datas.length;
				for(i; i < len; i++) {

					var temp = add_marker(datas[i], ws);
					if(typeof cache['vessels'][datas[i]['vessel_id']] === 'undefined'){
						cache['vessels'][datas[i]['vessel_id']] = [temp];
					} else {
						cache['vessels'][datas[i]['vessel_id']].push(temp); // captures duplication from two different webservice if availables
					}
					for(var key in cache['init_test']) {
						if (cache['init_test'].hasOwnProperty(key) && temp[key].length >= 1) {
							feature_init(
								temp[key], 	// features
								false, 		// highlight
								ws['style'] // style
							);
						}
					}
				}
			});
		}

		function feature_init(features, highlight, style){
			var id 		= features[0].get('vessel_id');
			var limit 	= features[0].get('zoom_limit');
			var type 	= features[0].get('type');

			var zoom = map.getView().getZoom();
			var i = 0, len = (features.length - 1);
			var source = (typeof style != 'undefined')? cache['source'][style]: cache['source']['vector'];

			if(zoom >= limit && cache['init_test'][type][id] === false){
				for(i; i <= len; i++) {


					if(!highlight && style != ''){
						source.addFeature(features[i]);
					} else if(highlight){
						features[i].set('opacity', 1);
					} else if(!highlight){
						var opacity = 1;
						switch(type){
							case 'markers':
								opacity = 0.8;
							break;
							case 'markers_ais':
							case 'markers_stop':
							case 'markers_stop_ais':
								opacity = 0.2;
							break;
							default:
								opacity = ((len-i)/(len*1.5/* buffer extra .5 for click-to-highlight */)).toFixed(2);
							break;
						}
						features[i].set('opacity', opacity);
					}


				}
				cache['init_test'][type][id] = true;
			} else if(zoom <= (limit-1) && cache['init_test'][type][id] === true){
				for(i; i <= len; i++) {
					source.removeFeature(features[i]);
				}
				cache['init_test'][type][id] = false;
			}
		}

		function get_features(vessel_id){
			var zoom = map.getView().getZoom();
			var vessel = cache['vessels'][vessel_id][0];
			var test = (typeof vessel['markers'][0] != 'undefined')? vessel['markers'][0]: vessel['markers_stop'][0];
			if(test.get('style') !== 'cluster' && zoom >= 8){ // Cluster need no reinit
				for(var key in cache['init_test']) {
					if (cache['init_test'].hasOwnProperty(key) && vessel[key].length >= 1) {
						var style = (zoom >= 8)? 'vector': vessel[key][0].get('style');
						feature_init(vessel[key], false, style);
					}
					// TODO: if(Object.keys(cache['vessels'][vessel_id]) >= 2), ais vs inmarsat
				}				
			}
		}

		function marker_popup(vessel, popup, active){
			var vessel_id = (!vessel)? 0 : vessel.get('vessel_id');
			if(active){ // On
				features_onclick(vessel_id, true, vessel.get('style')); // feature highlight

				var geometry 	= vessel.getGeometry();
				var coord 		= geometry.getCoordinates();
				popup.setPosition(coord);
				element.popover({
					trigger		: 'manual',
					placement	: 'right',
					html		: true,
					title		: 'TITLE',
					content		: 'CONTENT'
				}).popover('show');
				$('#map .popover-title').html(
					'<h4>'+vessel.get('name')
					+'<button type="button" id="close" class="close" onclick="$(&quot;#popup&quot;).popover(&quot;hide&quot;);">&times;</button></h4>'
				);
				$('#map .popover-content').html(
					'<div><b>MMSI: </b>' + vessel.get('vessel_id') + '</div>'
					+ '<h5>' + vessel.get('point') + '</h5>'
					+ '<h5>' + vessel.get('timestamp') + '</h5>'
				);
				cache['selected'] = {
					vessel_id: vessel.get('vessel_id'),
					style: vessel.get('style'),
				};
			} else { // Off
				element.popover('destroy');

				if(cache['selected']['vessel_id'] > 0) { // cluster/markers clicked
					if(cache['selected']['vessel_id'] === 'vector') // only reinit vector style
					features_onclick(cache['selected']['vessel_id'], false, cache['selected']['style']); // reset feature highlight

					cache['selected'] = {
						vessel_id: 0,
						style: '',
					};
				}
			}
		}

		function features_onclick(vessel_id, active, style){
			// TODO: debug missing one trail after reinit onclick
			// TODO: AIS marker onclick opacity doesn't reset
			// TODO: reset previous marker's opacity after another marker clicked
			var vessel = cache['vessels'][vessel_id][0];
			for(var key in vessel) {
				if (vessel.hasOwnProperty(key) && vessel[key].length >= 1) {
					cache['init_test'][key][vessel_id] = false;
					feature_init(vessel[key], active, style);
				}
			}			
		}

//  Marker & Trail Layers Style

		function add_marker(data, ws){
			var color = get_color(true);
			var distance = 5; // KM

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
							data_type	: ws['type'],
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: (ws['type'] !== 'ais')? 0.8: 0.5,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'markers',
							style		: ws['style'],
							vessel_id	: vessel_id,
							zoom_limit	: 0 // not applicable
						}));	
					} else {
						feature['joints'].push(new ol.Feature({
							color		: color,
							data_type	: ws['type'],
							geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
							name		: data.name,
							opacity		: opacity,
							point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
							rotation	: rotation,
							timestamp	: new Date(timestamp).toUTCString(),
							type		: 'joints',
							style		: ws['style'],
							vessel_id	: vessel_id,
							zoom_limit	: ws['zoom_limit']['joints']
						}));
					}

					var lines = [
						ol.proj.fromLonLat([start.lng, start.lat]),
						ol.proj.fromLonLat([end.lng, end.lat])
					];
					feature['trails'].push(new ol.Feature({
						color		: color,
						data_type	: ws['type'],
						geometry	: new ol.geom.LineString(lines, 'XYZM'),
						name		: data.name,
						opacity		: opacity,
						type		: 'trails',
						style		: ws['style'],
						vessel_id	: vessel_id,
						zoom_limit	: ws['zoom_limit']['trails'] 
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
					data_type	: ws['type'],
					geometry	: new ol.geom.Point(ol.proj.fromLonLat([start.lng, start.lat])),
					name		: data.name,
					opacity		: 0.5,
					point		: ol.coordinate.toStringHDMS([start.lng, start.lat]),
					rotation	: 0,
					timestamp	: new Date(timestamp).toUTCString(),
					type		: 'markers_stop',
					style		: ws['style'],
					vessel_id	: vessel_id,
					zoom_limit	: 0 // not applicable
				}));
			}

			for(var key in cache['init_test']) {
				if (cache['init_test'].hasOwnProperty(key)) {
					cache['init_test'][key][vessel_id] = false;
				}
			}
			return feature;
		}

		function get_style(feature, resolution){
			var style, type = (feature.get('data_type') !== 'ais')? feature.get('type'): feature.get('type') + '_ais';
			switch(type){
				case 'trails':
					style = {
						stroke: new ol.style.Stroke({
							color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
							width: 0.5,
							lineDash: [8,4]
						})						
					}
				break;
				case 'trails_ais':
					style = {
						stroke: new ol.style.Stroke({
							color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
							width: 0.5,
							lineDash: [4,4]
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
						image: new ol.style.RegularShape({
							points: 3,
							radius: 7,
							fill: new ol.style.Fill({
								color: 'rgba(92,184,92, '+ feature.get('opacity') + ')'
							}),
							rotation: feature.get('rotation')
						})
					}	
				break;
				case 'markers_stop':
				case 'markers_stop_ais':
					style = {
						image: new ol.style.Circle({
							radius: 5,
							fill: new ol.style.Fill({
								color: 'rgba(240,173,78, '+ feature.get('opacity') + ')'
							})
						})
					}
				break;
			}
			cache['style']['vector'][type] = new ol.style.Style(style);
			return [cache['style']['vector'][type]];
		}

		function get_cluster_style(feature, resolution){
			var size = feature.get('features').length;
			var style, color;
			if(!style) {
				style = [new ol.style.Style({
					image: new ol.style.Circle({
						radius: 10,
						fill: new ol.style.Fill({
							color: get_color(false, size)
						})
					}),
					text: new ol.style.Text({
						text: size.toString(),
						fill: new ol.style.Fill({
							color: 'rgba(255,255,255,0.8)'
						})
					})
				})];
				cache['style']['cluster'][size] = style;
			}
			return style;
		}

		function get_color(random, size) {
			var color;
			if(random){
				color = 
				Math.floor(Math.random() * 200) + ',' + 
				Math.floor(Math.random() * 200) + ',' + 
				Math.floor(Math.random() * 200);
			} else {
				if(size >= 40)
					color = 'rgba(217,83,79,0.8)'; // red
				if(size >= 20 && size <= 39)
					color = 'rgba(240,173,78,0.7)'; // yellow
				if(size >= 6 && size <= 19)
					color = 'rgba(91,192,222,0.5)'; // blue
				if(size <= 5)
					color = 'rgba(92,184,92,0.3)'; // green
			}
			return color;
		}

// Helpers

		function to_rad(x) {
			return 
			x * Math.PI / 180;
		}

		function spherical_cosinus(lat1, lon1, lat2, lon2) { // Get distance from two given points 
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