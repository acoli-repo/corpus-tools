  // Script to display jsondata onto Google maps
  // (c) Maarten Janssen 2016

  // jsondata format: [ { "lat": lat, "lng": lng, "location": placename, "cnt": nr of docs } ]
  var map; var sublist = ['a', 'b', 'c'];
  if ( typeof tilelayer == "undefined" ) {
		tilelayer = 'https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw';
		tiletit = 'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>';
  };
  if ( typeof tiletit == "undefined" ) { tiletit = ''; };
  if ( tilelayer.includes("google") ) {
	tiletit = 'Imagery © <a href="https://maps.google.com/">Google</a>';
	sublist = ['mt0','mt1','mt2','mt3'];
  };  
  if ( tiletit != '' ) { tiletit = ', '+ tiletit; };


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

	L.tileLayer(tilelayer, {
		maxZoom: 18,
		attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
			'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>' + tiletit,
		id: 'mapbox.streets',
		subdomains: sublist
	}).addTo(map);

	// Make an infowindow
	var infowindow = []; 
	var marker = [];
	
	var cqlset = cql.split('%7C%7C');

	if ( typeof cluster != "undefined" ) {
 		// var markers = L.markerClusterGroup();
 		// countCluster gives back a counting object
 		var markers = L.markerClusterGroup({
			// zoomToBoundsOnClick: false,
	 		iconCreateFunction: function(cluster) {
			 		var cdata = countCluster(cluster);
	 				return L.ExtraMarkers.icon({ number: cdata.cnt, icon: 'fa-number', markerColor: cdata.color, prefix: 'fa', shape: 'square' });
 			}
 		});
		markers.on('clustermouseover', function(c) {
			  var ll = c.layer.getLatLng();
			  var clusterdata = countCluster(c.layer);
			  putxt = '<h2>Cluster</h2>';
			  putxt += '<p>' + c.layer._childCount + ' locations</p><table>'
			  for ( var mset in clusterdata.mdcnt ) {
			  	if ( cqlset[mset].includes('%3Ctext') ) {
			  		// Document-level query
					putxt += '<tr><td><span style="color: '+ collist[mset] +'">&#9641;</span><td align=right>' + clusterdata.mcnt[mset] + ' ' + doctxt + '</tr>';
			  	} else {
			  		// Token-level query
					putxt += '<tr><td><span style="color: '+ collist[mset] +'">&#9641;</span><td align=right>' + clusterdata.mdcnt[mset] + ' ' + doctxt + '<td>|<td align=right>' + clusterdata.mcnt[mset] + ' results</tr>';
				};
			  };
			  putxt += '</table>';
			  var popup = L.popup()
				  .setLatLng(ll)
				  .setContent(putxt)
				  .openOn(map);
			  }).on('clustermouseout',function(c){
				   // map.closePopup();
			  }).on('clusterclick',function(c){
			  	   c.zoomToBounds;
				   map.closePopup();
			  });
	};

          
	// Place all the document markers
	for ( var i=0; i<doclist.length; i++ ) {
		doc = doclist[i];
		// coordinates need to be string for CQP match
		var lat = doc.lat; if ( typeof lat == "string" ) { lat = parseFloat(lat); }; 
		var lng = doc.lng; if ( typeof lng == "string" ) { lng = parseFloat(lng); }; 
		var npos = { lat: lat, lng: lng };

		var contentString = doc.cid;

		
		collist = [ 'blue', 'red', 'purple', 'violet', 'pink', 'orange-dark', 'orange', 'blue-dark', 'cyan', 'green-dark', 'green', 'green-light', 'black' ];
		
		// Define what to put in the infowindow
		var htmltxt = '';
		if ( typeof doc.desc != "undefined" ) {
			htmltxt = '<div><h2>' + doc.location + '</h2><p>' + doc.desc + '</p>';
		} else {
			var tmp = doc.cnt.split(",");
			marktot = 0; marktxt = '<table>';
			for (j = 0; j < tmp.length; j++) {
				var tmp2 = tmp[j].split(":");
				var mset = tmp2[0]; mdoc = tmp2[1]; mcnt = tmp2[2];
				marktot += mcnt * 1;
				markercol = collist[mset];
				if ( cqlset[mset].includes('%3Ctext') ) {
					marktxt += '<tr><td><span style="color: '+markercol+'">&#9641;</span><td align=right>' + mcnt + ' ' + doctxt + '</tr>';
				} else {
					marktxt += '<tr><td><span style="color: '+markercol+'">&#9641;</span><td align=right>' + mdoc + ' ' + doctxt + '<td>|<td align=right>' + mcnt + ' results</tr>';
				};
			};
			marktxt += '</table>';
			if ( tmp.length > 1 ) {
				markercol = 'yellow';
			};
			htmltxt = '<div><h2>' + doc.location + '</h2>'+ marktxt +'<p><a href="index.php?action=geomap&act=view&place=' + doc.location + ' &lat=' + doc.lat + '&lng=' + doc.lng + '&cql=' + cql + '">view ' + doctxt + '</a></p>';
		};

		if ( typeof cluster != "undefined" ) {
			var myMarker = L.ExtraMarkers.icon({
				icon: 'fa-number',
				number: marktot,
				markerColor: markercol
			  });			
			marker[i] = L.marker([npos.lat, npos.lng], {icon: myMarker}).bindPopup(htmltxt);
			marker[i].doccount = doc.cnt;
			marker[i].on('mouseover', function (e) {
				this.openPopup();
			});
			markers.addLayer(marker[i]);
		} else if ( typeof markertype != "undefined" && markertype == "pin" ) {
			var myMarker = L.ExtraMarkers.icon({
				icon: 'fa-number',
				number: marktot,
				markerColor: markercol
			  });			
  			marker[i] = L.marker([npos.lat, npos.lng], {icon: myMarker}).addTo(map).bindPopup(htmltxt);
		} else {
			if ( cqlset.length > 1) {
				var mcol = markercol;
			} else if ( maxcnt > 1  ) {
				// Make dots on scale red - blue
				var blue = ( '0' + Math.floor(colsteps*rescale(doc.cnt)).toString(16) ).substr(-2);
				var green = '00';
				var red = ( '0' + Math.floor(colsteps*(rescale(maxcnt)-rescale(doc.cnt))).toString(16) ).substr(-2);
				var mcol = '#' + red + green + blue;
			} else {
				var mcol = '#990000';
			};

			marker[i] = L.circleMarker([npos.lat, npos.lng], {color: mcol, weight: 1}).addTo(map).bindPopup(htmltxt);
			marker[i].setRadius(5);
		};

	};
	if ( typeof cluster != "undefined" ) {
		map.addLayer(markers);
	};
	
  }
    
  function countCluster(cluster) {
  	var cdata = { cnt: 0, doccnt: 0, color: 'yellow', mcnt: {}, mdcnt: {} };
	for ( var i=0; i<cluster._markers.length; i++ ) {
		var tmp = cluster._markers[i].doccount.split(",");
		for (j = 0; j < tmp.length; j++) {
			var tmp2 = tmp[j].split(":");
			var mset = tmp2[0]; mdoc = tmp2[1]; mcnt = tmp2[2];
			cdata.cnt += mcnt*1; 
			if ( !cdata.mcnt[mset] ) cdata.mcnt[mset] = 0;
			cdata.mcnt[mset] += mcnt*1; 
			cdata.doccnt += 1;
			if ( !cdata.mdcnt[mset] ) cdata.mdcnt[mset] = 0;
			cdata.mdcnt[mset] += 1; 
		}; 
	};
	for ( var i=0; i<cluster._childClusters.length; i++ ) {
		var chdata = countCluster(cluster._childClusters[i]);
		cdata.cnt += chdata.cnt;
		cdata.doccnt += chdata.doccnt;
		for ( var mset in chdata.mcnt ) {
			if ( !cdata.mcnt[mset] ) cdata.mcnt[mset] = 0;
			cdata.mcnt[mset] += chdata.mcnt[mset]; 
		};
		for ( var mset in chdata.mdcnt ) {
			if ( !cdata.mdcnt[mset] ) cdata.mdcnt[mset] = 0;
			cdata.mdcnt[mset] += chdata.mdcnt[mset]; 
		};
	};
	var cols = Object.keys(cdata.mcnt); 
	if ( cols.length == 1 ) { 
		cdata.color = collist[cols[0]]; 
	};
	return cdata;
  }; 
    
  function zoomto ( geo, zoom ) {
  	var pos = geo.split(' ');
	var geopos = { lat: pos[0]*1, lng: pos[1]*1 }; 
	map.setView(new L.LatLng(geopos.lat, geopos.lng), zoom);
  };
  
  function rescale (num) {
  	return Math.log(num)+1;
  };
