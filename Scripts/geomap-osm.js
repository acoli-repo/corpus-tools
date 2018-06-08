  // Script to display jsondata onto Google maps
  // (c) Maarten Janssen 2016

  // jsondata format: [ { "lat": lat, "lng": lng, "location": placename, "cnt": nr of docs } ]
  var map;

  function initMap() {
	
	var doclist = JSON.parse(jsondata);
         
	// Calculate min and max cnt to display value-based markers
	var totcnt = 0; var maxcnt = 0; var mincnt = 999999;
	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		if ( doc.cnt < mincnt ) { mincnt = doc.cnt };
		if ( doc.cnt > maxcnt ) { maxcnt = doc.cnt };
		totcnt += doc.cnt		
	}; var colsteps = (16*16+1)/rescale(maxcnt);
		
	// Use start positions or set temporary position to fit-to-screen later 
	var startpos;
	if ( typeof defpos == "undefined" ) { 
		startpos = { lat: doclist[0].lat, lng: doclist[0].lng }; 
	} else { startpos = defpos; };
	var startzoom ; 
	if ( typeof defzoom == "undefined" ) { 
		startzoom = 4; 
	} else { startzoom = defzoom; };

	map = L.map('mapdiv').setView([startpos.lat, startpos.lng], startzoom);

	L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw', {
		maxZoom: 18,
		attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
			'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
		id: 'mapbox.streets'
	}).addTo(map);

	// Make an infowindow
	var infowindow = []; 
	var marker = [];

	// Place all the document markers
	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		// coordinates need to be string for CQP match
		var lat = doc.lat; if ( typeof lat == "string" ) { lat = parseFloat(lat); }; 
		var lng = doc.lng; if ( typeof lng == "string" ) { lng = parseFloat(lng); }; 
		var npos = { lat: lat, lng: lng };

		var contentString = doc.cid;

		if ( maxcnt > 1 ) {
			// Make dots on scale red - blue
			var blue = ( '0' + Math.floor(colsteps*rescale(doc.cnt)).toString(16) ).substr(-2);
			var green = '00';
			var red = ( '0' + Math.floor(colsteps*(rescale(maxcnt)-rescale(doc.cnt))).toString(16) ).substr(-2);
			var mcol = '#' + red + green + blue;
		} else {
			var mcol = '#990000';
		};
		
		// Define what to put in the infowindow
		var htmltxt = '';
		if ( typeof doc.desc != "undefined" ) {
			htmltxt = '<div><h2>' + doc.location + '</h2><p>' + doc.desc + '</p>';
		} else {
			htmltxt = '<div><h2>' + doc.location + '</h2><p><a href="index.php?action=geomap&act=view&place=' + doc.location + ' &lat=' + doc.lat + '&lng=' + doc.lng + '">' + doc.cnt + ' ' + doctxt + '</a></p>';
		};

		marker[i] = L.circleMarker([npos.lat, npos.lng], {color: mcol, weight: 1}).addTo(map)
			.bindPopup(htmltxt);
		marker[i].setRadius(5);

	};

  }
    
  function zoomto ( geo, zoom ) {
  	var pos = geo.split(' ');
	var geopos = { lat: pos[0]*1, lng: pos[1]*1 }; 
	map.setView(new L.LatLng(geopos.lat, geopos.lng), zoom);
  };
  
  function rescale (num) {
  	return Math.log(num)+1;
  };
