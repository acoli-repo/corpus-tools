  // Script to display jsondata onto Google maps
  // (c) Maarten Janssen 2016

  // jsondata format: [ { "lat": lat, "lng": lng, "location": placename, "cnt": nr of docs } ]

  function initMap() {
	
	var doclist = JSON.parse(jsondata);

	// Calculate min and max cnt, avg lat and lng
	var minlat = 9999; var minlng = 9999; var totlat = 0;
	var maxlat = -9999; var maxlng = -9999; var totlng = 0;
	var totcnt = 0; var maxcnt = 0; var mincnt = 999999;
	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		if ( doc.lat < minlat ) { minlat = doc.lat };
		if ( doc.lat > maxlat ) { maxlat = doc.lat };
		totlat += doc.lat;
		if ( doc.lng < minlng ) { minlng = doc.lng };
		if ( doc.lng > maxlng ) { maxlng = doc.lng };
		totlng += doc.lng;
		if ( doc.cnt < mincnt ) { mincnt = doc.cnt };
		if ( doc.cnt > maxcnt ) { maxcnt = doc.cnt };
		totcnt += doc.cnt		
	}; var colsteps = (16*16+1)/rescale(maxcnt);
	
	console.log('cnt  - min: ' + mincnt + ' - max: ' + maxcnt + ' - log: ' + rescale(maxcnt));
	
	var startpos;
	if ( typeof defpos == "undefined" ) { 
		// startpos = { lat: doclist[0].lat, lng: doclist[0].lng }; 
		startpos = { lat: totlat/doclist.length, lng: totlng/doclist.length }; 
	} else { startpos = defpos; };

	var startzoom ;
	if ( typeof defzoom == "undefined" ) { 
		startzoom = 4; 
	} else { startzoom = defzoom; };

	var map = new google.maps.Map(document.getElementById('map'), {
	  zoom: startzoom,
	  center: startpos
	});

	var infowindow = []; 
	var marker = [];
	infowindow = new google.maps.InfoWindow({ content: 'My marker' });

	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		// coordinates need to be string for CQP match
		var lat = doc.lat; if ( typeof lat == "string" ) { lat = parseFloat(lat); }; 
		var lng = doc.lng; if ( typeof lng == "string" ) { lng = parseFloat(lng); }; 
		var npos = { lat: lat, lng: lng };

		var contentString = doc.cid;

		// Make dots on scale red - blue
		var blue = ( '0' + Math.floor(colsteps*rescale(doc.cnt)).toString(16) ).substr(-2);
		var green = '00';
		var red = ( '0' + Math.floor(colsteps*(rescale(maxcnt)-rescale(doc.cnt))).toString(16) ).substr(-2);
		var mcol = '#' + red + green + blue;

		var htmltxt = '';
		if ( typeof doc.desc != "undefined" ) {
			htmltxt = '<div><h2>' + doc.location + '</h2><p>' + doc.desc + '</p>';
		} else {
			htmltxt = '<div><h2>' + doc.location + '</h2><p><a href="index.php?action=geomap&act=view&place=' + doc.location + ' &lat=' + doc.lat + '&lng=' + doc.lng + '">' + doc.cnt + ' ' + doctxt + '</a></p>';
		};

 		marker[i] = new google.maps.Marker({
 	      map: map,
 		  position: npos,
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

	};

  }
  
  function rescale (num) {
  	return Math.log(num)+1;
  };
