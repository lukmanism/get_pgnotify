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
		height: 400px;
		width: 100%;
	  }
	</style>
</head>
<body>

	<div class="container">
		<header></header>
		<div class="main">
			<div id="map" class="map"><div id="popup"></div></div>
			<div class="">
				<h4>Status: <span id="status">Disconnected</span></h4>
				<button onclick="add_layer()">Add</button>
				<ul id="messages"></ul>
			</div>
		</div>
		<footer></footer>
	</div>

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

		connect_api();

		var map = new ol.Map({
			target: 'map',
			layers: [
				new ol.layer.Tile({source: new ol.source.OSM()}),
				// vectorLayer
			],
			view: new ol.View({
				center: [0, 0],
				zoom: 1
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
							color: feature.get('color'),
							width: 0.5,
							lineDash: [2,2]
						})
					});
				break;
				case 'Point':
					style_cache[type] = new ol.style.Style({
						image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
							anchor: [19, 32],
							anchorXUnits: 'pixels',
							anchorYUnits: 'pixels',
							opacity: 0.8,
							src: 'images/marker.png',
							// rotation: 0
							snapToPixel: true,
						}))
					});
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
			// positioning: 'top-center',
			offset : [-38,-74],
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
					placement: 'top',
					html: true,
					title: 'TITLE',
					content: 'CONTENT'
				}).popover('show');
				$('#map .popover-title').html('ID: '+feature.get('name'));
				$('#map .popover-content').html(
					'<div><b>Vessel ID: </b>' + feature.get('id') 
					+ '</div><div><b>Lat: </b>' + feature.get('lat').toFixed(8)
					+ '</div><div><b>Lng: </b>' + feature.get('lng').toFixed(8) + '</div>'
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
					add_marker(v);
					add_trails(v);
				});
			});
		}


		function format_location(data){
			var temp = data;
			if(typeof temp.length == 'undefined'){
				$.each(temp.trails, function(x,y){
					temp['trails'][x] = format_geometry(y[0], y[1])
				});
			} else {
				$.each(temp, function(k,v){
					$.each(v.trails, function(x,y){
						temp[k]['trails'][x] = format_geometry(y[0], y[1])
					});
				});
			}
			return temp;
		}


		function format_geometry(a,b){
			return ol.proj.transform([parseFloat(a), parseFloat(b)], 'EPSG:4326', 'EPSG:3857');
		}


		function add_marker(data){
			var marker = new ol.Feature({
				geometry: new ol.geom.Point(
					ol.proj.transform([parseFloat(data.trails[0][0]), parseFloat(data.trails[0][1])], 'EPSG:4326', 'EPSG:3857')
					),
				name: data.name,
				id: data.name,
				lat: parseFloat(data.trails[0][0]),
				lng: parseFloat(data.trails[0][1])
			});
			vectorSource.addFeature(marker);
		}


		function add_trails(data){
			var temp = format_location(data);
			var trails = new ol.Feature({
				geometry: new ol.geom.LineString(temp.trails, 'XYZM'),
				name: data.name,
				id: data.name,
				color: 'rgba('+get_color()+', 0.8)'
			});
			vectorSource.addFeature(trails);
		}



		function get_color() {
			return (Math.floor(Math.random() * 256)) + ',' + (Math.floor(Math.random() * 256)) + ',' + (Math.floor(Math.random() * 256));
		}		

		// not working
		// $(map.getViewport()).on('mousemove'), function(e){
		// 	var pixel = map.getEventPixel(e.originalEvent);
		// 	var hit = map.forEachFeatureAtPixel(pixel, function(feature, layer){
		// 		return true;
		// 	});
		// 	if(hit){
		// 		map.getTarget().style.cursor = 'pointer';
		// 	} else {
		// 		map.getTarget().style.cursor = '';
		// 	}
		// }
	</script>

</body>
</html>