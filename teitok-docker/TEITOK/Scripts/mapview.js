	// Initialize the map
    var map; var overlay; var opacity = 0.8; var worldmap = 0;
	if ( world && anchor ) {
		// Map on the world map

		map = L.map('map');
		var positron = L.tileLayer(tilelayer, {
			attribution: attribution,
			maxNativeZoom: 18,
			maxZoom:24
		}).addTo(map);

		var topleft = L.latLng(anchor['topleft'][0], anchor['topleft'][1]),
		topright = L.latLng(anchor['topright'][0], anchor['topright'][1]),
		bottomleft = L.latLng(anchor['bottomleft'][0], anchor['bottomleft'][1]);

		if ( username ) {
			var markertl = L.marker(topleft, {draggable: true} ).addTo(map),
				markertr = L.marker(topright, {draggable: true} ).addTo(map),
				markerbl = L.marker(bottomleft, {draggable: true} ).addTo(map);
	
			markertl.on('drag dragend', repositionImage);
			markertr.on('drag dragend', repositionImage);
			markerbl.on('drag dragend', repositionImage);
		};
		
		var	bounds = new L.LatLngBounds(topleft, topright).extend(bottomleft);
		map.fitBounds(bounds);

		overlay = L.imageOverlay.rotated(imageUrl, topleft, topright, bottomleft, {
			opacity: opacity,
			interactive: true,
			attribution: attribution
		});

		map.addLayer(overlay);
		worldmap = 1;

	} else {
		// Simple map
		
		map = L.map('map', {
			minZoom: -5,
			maxZoom: 5,
			center: [fw/2, fh/2],
			crs: L.CRS.Simple
		});

		// Set the image as a map
		const imageBounds = [[0, 0], [fh, fw]]; // Adjust based on image size
		overlay = L.imageOverlay(imageUrl, imageBounds).addTo(map);
		map.setMaxBounds(imageBounds);

		map.fitBounds(imageBounds);
	};

	var zoomLevel = map.getZoom(); // Get current zoom level
	map.setZoom(zoomLevel + 1); 
	map.setMinZoom(zoomLevel); 
	
	var placenodes = document.getElementById('mtxt').getElementsByTagName(placetags);

	// Add transparent, clickable areas
	Array.from(placenodes).forEach((place, index) => {
		   var tboxr = place.getAttribute('tbox');
		   var bboxr = place.getAttribute('bbox');
		   var rect = null;
		   if ( tboxr ) {
				tbox = tboxr.split(' ');
				var bounds = getRotatedRectangle(tbox);
			   if ( worldmap ) { bounds = [imageToMap(bounds[0]), imageToMap(bounds[1]), imageToMap(bounds[2]), imageToMap(bounds[3])]; };
				rect = L.polygon(bounds, { color: boxcolor, weight: 1 }).addTo(map);
		   } else if ( bboxr ) {
				bbox = bboxr.split(' ');
				var x1 = parseFloat(bbox[0]);
				var x2 = parseFloat(bbox[2]);
				var y1 = fh - parseFloat(bbox[1]);
				var y2 = fh - parseFloat(bbox[3]);
				
			   var bounds = [[y2, x2], [y1,x1]];
			   
			   if ( worldmap ) { bounds = [imageToMap(bounds[0]), imageToMap(bounds[1])]; };
			   
			   rect = L.rectangle(bounds, { color: boxcolor, weight: 1 }).addTo(map);
		   };
		   if ( rect ) {
			   var tooltip = place.innerHTML;
			   rect.bindTooltip(tooltip, { permanent: false, direction: "top" });
				rect.on('click', function(event) {
					onClickRectangle(place);
				});
				if ( place.getAttribute(corresp) ) {
					rect.on('tooltipopen', function(e) {
						var nerid = place.getAttribute(corresp).split('#').pop();
						var nerhtml = 'index.php?action='+ttaction+'&act='+ttact+'&nerid='+nerid+'&raw='+place.innerText;
						fetch(nerhtml) // Replace with a real API
							.then(response => {
								if (!response.ok) {
									throw new Error("Network response was not ok");
								}
								return response.text();
							})
							.then(html => {
								e.tooltip.setContent(html); // Update tooltip with AJAX HTML
							})
							.catch(error => {
								console.error("Error fetching data:", error);
							});
					});
				};
			};
	});

	function onClickRectangle(place) { 
		var tmp = place.getAttribute(corresp);
		if ( tmp ) {
			var nerid = tmp.split('#').pop();
			window.open('index.php?action='+neraction+'&nerid='+nerid, '_self'); 
		};
	}

	function getRotatedRectangle(tbox) {

		var x = parseFloat(tbox[0]);
		var y = fh - parseFloat(tbox[1]);
		var l = parseFloat(tbox[3]);
		var h = parseFloat(tbox[4]);
		var a = parseFloat(tbox[2]) ;

	   // Half width and half height
		var halfWidth = l / 2;
		var halfHeight = h / 2;

		// Rectangle corners (before rotation) based on the center (x, y)
		var corners = [
			[y, x],  // Top-left
			[y, x + l],  // Top-right
			[y + h, x + l],  // Bottom-right
			[y + h, x]   // Bottom-left
		];

		// Function to rotate a point around the center (x, y)
		function rotatePoint(xp, yp, angle) {
			var dx = xp - x;
			var dy = yp - y;
			var rotatedX = x + (dx * Math.cos(angle) - dy * Math.sin(angle));
			var rotatedY = y + (dx * Math.sin(angle) + dy * Math.cos(angle));
			return [rotatedY, rotatedX];
		}

		// Rotate all the corners around the center
		var rotatedCorners = corners.map(([cy, cx]) => rotatePoint(cx, cy, a));

		return rotatedCorners;
	}

	// Fullscreen Toggle Button
	const fullscreenBtn = document.getElementById("fullscreen-btn");
	const mapContainer = document.getElementById("map-container");

	fullscreenBtn.addEventListener("click", () => {
		mapContainer.classList.toggle("fullscreen");
		setTimeout(() => map.invalidateSize(), 300); // Fix rendering issue when resizing
	});

	document.getElementById('opacity').innerText = Math.round(opacity * 100) + '%';
	function changeOpacity(change) {
		opacity = Math.min(1, Math.max(0, opacity + change ));
		document.getElementById('opacity').innerText = Math.round(opacity * 100) + '%';
		overlay.setOpacity(opacity);
	}

	function repositionImage() {
		latlngtl = markertl.getLatLng();
		latlngtr = markertr.getLatLng();
		latlngbl = markerbl.getLatLng();
		overlay.reposition(latlngtl, latlngtr, latlngbl);
		document.getElementById('newcoords').value = latlngtl + " : " + latlngtr + " : " + latlngbl;
		document.getElementById('saveanchor').style.display = 'inline';
	};

	function imageToMap(coords) {
		imgWidth = fw, imgHeight = fh, topleft = anchor['topleft'], topright = anchor['topright'], bottomleft = anchor['bottomleft']
		y = fh - coords[0]; x = coords[1];

		// Normalize x, y to a 0-1 range
		var normX = x / imgWidth;
		var normY = y / imgHeight;
	
		// Compute map coordinates using affine transformation
		var lat = topleft[0] + normY * (bottomleft[0] - topleft[0]) + normX * (topright[0] - topleft[0]);
		var lon = topleft[1] + normY * (bottomleft[1] - topleft[1]) + normX * (topright[1] - topleft[1]);
	
		return [lat, lon];
	}
