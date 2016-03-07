  // Script to display jsondata onto Google maps
  // (c) Maarten Janssen 2016

  // jsondata format: [ { "lat": lat, "lng": lng, "location": placename, "cnt": nr of docs } ]

  function initMap() {
	
	var doclist = JSON.parse(jsondata);

	// Calculate min and max cnt, avg lat and lng
	var minlat = 9999; var minlng = 9999; var totlat = 0;
	var maxlat = -9999; var maxlng = -9999; var totlng = 0;
	var totcnt = 0; var maxcnt = 9999999; var mincnt = 0;
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
	};
	
	var startpos = defpos;
	if ( typeof startpos == "undefined" ) { 
		// startpos = { lat: doclist[0].lat, lng: doclist[0].lng }; 
		startpos = { lat: totlat/doclist.length, lng: totlng/doclist.length }; 
	};

	var startzoom = defzoom;
	if ( typeof startzoom == "undefined" ) { 
		startzoom = 4; 
	};

	var map = new google.maps.Map(document.getElementById('map'), {
	  zoom: startzoom,
	  center: startpos
	});

	var infowindow = []; 
	var marker = [];
	infowindow = new google.maps.InfoWindow({ content: 'My marker' });

	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		var npos = {lat: doc.lat, lng: doc.lng };

		var contentString = doc.cid;

 		marker[i] = new google.maps.Marker({
 	      strokeColor: '#FF0000',
 	      map: map,
 		  position: npos,
  icon: {
    path: google.maps.SymbolPath.CIRCLE,
    fillOpacity: 0.3,
    fillColor: '#ff0000',
    strokeOpacity: 1.0,
    strokeColor: '#ff0000',
    strokeWeight: 1.0, 
    scale: 5 //pixels
  },
   		  html: '<div><h2>' + doc.location + '</h2><p><a href="index.php?action=geomap&act=view&place=' + doc.location + ' &lat=' + doc.lat + '&lng=' + doc.lng + '">' + doc.cnt + ' ' + doctxt + '</a></div>',
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
