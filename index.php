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
				<button onclick="add_layer()">Add</button>
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

		connect_api(); // init webservices

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


//  Start Marker & Trail Layers

		var vectorSource = new ol.source.Vector({});
		var style_cache = {};

		function get_style(feature, resolution){
			var type = feature.getGeometry().getType();
			switch(type){
				case 'LineString':
					style_cache[type] = new ol.style.Style({
						stroke: new ol.style.Stroke({
							color: 'rgba('+ feature.get('color') +', '+ feature.get('opacity') + ')',
							width: 0.5,
							lineDash: [2,2]
						})
					});
				break;
				case 'Point':
					var src = (feature.get('type') === 'Marker')? 'images/marker.png' : '';
					if(feature.get('type') === 'Marker'){
						style_cache[type] = new ol.style.Style({
							image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
								anchor: [19, 32],
								anchorXUnits: 'pixels',
								anchorYUnits: 'pixels',
								opacity: 0.8,
								src: 'images/marker.png',
								snapToPixel: true
							}))
						});						
					} else { // trails arrow
						style_cache[type] = new ol.style.Style({})
						var geometry = [feature.get('lat'),feature.get('lng')];
						style_cache[type] = new ol.style.Style({
							image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
								anchor: [12, 6],
								anchorXUnits: 'pixels',
								anchorYUnits: 'pixels',
								opacity: feature.get('opacity'),
								src: 'images/arrow.png',
								rotation: feature.get('rotation'),
								snapToPixel: true
							}))
						});
					}
				break;
			}
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
					'<h5>' + feature.get('point') 
					+ '</h5><div>MMSI: ' + feature.get('vessel_id') + '</div>'
					+ '</div><div>Timestamp: ' + feature.get('timestamp') + '</div>'
				);				
			} else {
				element.popover('destroy');
			}
		});


//  Methods

		function connect_api(){
			$.ajax({
				url: 'http://localhost:8080/test/socket-io/get_pgnotify/gettrails.php'
			}).done(function(data){
				vectorSource.clear();
				var vessels = $.parseJSON(data);
				$.each(vessels, function(k,v){
					var color = get_color();
					add_trails(v, color);
					add_point(v, color);
				});
			});
		}

		function add_point(data, color){
			var rotation = 0;
			$.each(data.trails, function(k,v){
				var len = data.trails.length;
				var opacity = (((len-1)-k)/(data.trails.length-1)).toFixed(1);
				var lat = parseFloat(data.trails[k][0]), lng = parseFloat(data.trails[k][1]);

				if(k < (data.trails.length-1)){
					var start = {
						lng: lng,
						lat: lat		
					}, end = {
						lng: parseFloat(data.trails[k+1][1]),
						lat: parseFloat(data.trails[k+1][0])	
					}
					var dx =  start.lat - end.lat;
					var dy =  start.lng - end.lng;
					rotation = Math.atan2(dy, dx);
				}

				var type = (k === 0)? 'Marker': 'Joint';
				var marker = new ol.Feature({
					geometry: new ol.geom.Point(ol.proj.fromLonLat([lng,lat])),
					name: data.name,
					vessel_id: data.vessel_id,
					point: ol.coordinate.toStringHDMS([lng,lat]),
					rotation: rotation,
					type: type,
					color: color,
					timestamp: data.attr[k][2],
					opacity: opacity
				});
				vectorSource.addFeature(marker);
			});
		}

		function add_trails(data, color){
			$.each(data.trails, function(x,y){
				if(x <= (data.trails.length - 2)){
					var len = data.trails.length;
					var opacity = (((len-1)-x)/(data.trails.length-1)).toFixed(1);
					var temp = [
						ol.proj.fromLonLat([
							parseFloat(data.trails[x][1]),
							parseFloat(data.trails[x][0])
						]),
						ol.proj.fromLonLat([
							parseFloat(data.trails[x+1][1]),
							parseFloat(data.trails[x+1][0])
						])
					];
					var trails = new ol.Feature({
						geometry: new ol.geom.LineString(temp, 'XYZM'),
						name: data.name,
						vessel_id: data.vessel_id,
						color: color,
						opacity: opacity
					});
					vectorSource.addFeature(trails);
				}
			});
		}

		function get_color() {
			return (
				Math.floor(Math.random() * 256) + ',' + 
				Math.floor(Math.random() * 256) + ',' + 
				Math.floor(Math.random() * 256)
			);
		}
		
	</script>

</body>
</html>