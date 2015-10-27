<!DOCTYPE html>
<html>
<head>
	<title>Get PG NOTIFY</title>
	<link rel="stylesheet" type="text/css" href="bower_components/bower-ol3/css/ol.css">
	<link rel="stylesheet" type="text/css" href="bower_components/bootstrap/dist/css/bootstrap.css">
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
		var socket = io.connect('http://localhost:3000');
		socket.on('pg notify', function(msg){
			// $('#messages').append($('<li>').text(msg.payload));
			// add_marker(msg);
			clear_marker();
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

		var styles = {
			'LineString': [new ol.style.Style({
				stroke: new ol.style.Stroke({
					color: getRandomColor(),
					width: 0.5,
					lineDash: [4,4]
				})
			})],
			'Point': [new ol.style.Style({
				image: new ol.style.Icon(/** @type {olx.style.IconOptions} */ ({
					anchor: [19, 32],
					anchorXUnits: 'pixels',
					anchorYUnits: 'pixels',
					opacity: 0.75,
					src: 'images/marker.png'
				}))
			})],
		};

		var styleFunction = function(feature, resolution) {
		  return styles[feature.getGeometry().getType()];
		};

		var vectorLayer = new ol.layer.Vector({
			source: vectorSource,
			style: styleFunction
		});


		function add_layer(){
			map.addLayer(vectorLayer);
		}

		map.addLayer(vectorLayer);

//  Start PopPop
		var element = $('#popup');
		var popup = new ol.Overlay({
			element: element,
			positioning: 'top-center',
			stopEvent: false
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
				$('#map .popover-title').html(feature.get('name'));
				$('#map .popover-content').html(
					'<div>Vessel ID: ' + feature.get('id') 
					+ '</div><div>Lat: ' + feature.get('lat').toFixed(8)
					+ '</div><div>Lng: ' + feature.get('lng').toFixed(8) + '</div>'
				);				
			} else {
				element.popover('destroy');
			}
		});


		function connect_api(){
			$.ajax({
				url: 'http://localhost:8080/test/socket-io/get_pgnotify/gettrails.php'
			}).done(function(data){
				var vessels = $.parseJSON(data);
				$.each(vessels, function(k,v){
					// add_marker(v);
					add_trails(v);
				});
			});
		}


		function format_location(data){
			var temp = data;
			console.log(temp.length)
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
			add_marker(data);
			var temp = format_location(data);
			var trails = new ol.Feature({
				geometry: new ol.geom.LineString(temp.trails, 'XYZM')
			});
			vectorSource.addFeature(trails);
		}


		function clear_marker(){
			vectorSource.clear();
		}


		function getRandomColor() {
			var letters = '0123456789ABCDEF'.split('');
			var color = '#';
			for (var i = 0; i < 6; i++ ) {
				color += letters[Math.floor(Math.random() * 16)];
			}
			return color;
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