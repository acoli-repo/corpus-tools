  // Script to display jsondata onto Google maps
  // (c) Maarten Janssen 2016

  // jsondata format: [ { "lat": lat, "lng": lng, "location": placename, "cnt": nr of docs } ]

  function initMap() {
	
	var doclist = JSON.parse(jsondata);

    var latlngbounds = new google.maps.LatLngBounds();
         
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

	// Create the map
	var map = new google.maps.Map(document.getElementById('map'), {
	  zoom: startzoom,
	  center: startpos
	});

	// Make an infowindow
	var infowindow = []; 
	var marker = [];
	infowindow = new google.maps.InfoWindow({ content: 'My marker' });

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

 		marker[i] = new google.maps.Marker({
 		
 	      map: map,
 		  position: npos,
 		  
 		  // Make the marker a circle
		  icon: {
			path: google.maps.SymbolPath.CIRCLE,
			fillOpacity: 0.3,
			fillColor: mcol,
			strokeOpacity: 1.0,
			strokeColor: mcol,
			strokeWeight: 1.0, 
			scale: 5 //pixels
		  },

   		  html: htmltxt,
 		  title: doc.title
 		});
		
		mrk = marker[i];
		mrk.addListener('click', function() {
			infowindow.setContent(this.html);
			infowindow.open(map, this);
			// infowindow.setPosition(this.center);
		});
		latlngbounds.extend(mrk.position);

		
	};

	// Fit map around all markers if no start position or zoom is given
	if ( typeof defpos == "undefined" ) { 
		map.setCenter(latlngbounds.getCenter());
	};
	if ( typeof defzoom == "undefined" ) { 
		map.fitBounds(latlngbounds);
	};

  }
  
  function rescale (num) {
  	return Math.log(num)+1;
  };
