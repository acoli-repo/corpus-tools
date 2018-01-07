
function downloadData ( dltype='csv' ) {
	if ( dltype == 'json' ) {
		window.open('data:text/csv;base64,' + btoa(JSON.stringify(json)), '_self');
	} else if ( dltype == 'csv' ) {
		data = google.visualization.arrayToDataTable(json, headrow);
		var csv = google.visualization.dataTableToCsv(data);
		window.open('data:text/csv;base64,' + btoa(csv), '_self');
	} else if ( dltype == 'png' ) {
		var uri = chart.getImageURI();
		window.open(uri, '_new');
	} else if ( dltype == 'svg' ) {
		var tmp = document.getElementsByTagName('svg');
		var svg = tmp[0];
		if ( svg != null ) {
			var serializer = new XMLSerializer();
			var source = serializer.serializeToString(svg);
			//add name spaces.
			if(!source.match(/^<svg[^>]+xmlns="http\:\/\/www\.w3\.org\/2000\/svg"/)){
				source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
			}
			if(!source.match(/^<svg[^>]+"http\:\/\/www\.w3\.org\/1999\/xlink"/)){
				source = source.replace(/^<svg/, '<svg xmlns:xlink="http://www.w3.org/1999/xlink"');
			}

			//add xml declaration
			source = '<?xml version="1.0" standalone="no"?>\r\n' + source;

			//convert svg source to URI data scheme.
			var url = "data:image/svg+xml;charset=utf-8,"+encodeURIComponent(source);		
			window.open(url, '_new');
		} else {
			console.log('No SVG found, table?');
			console.log(document.getElementsByTagName('svg'));
		};
	};
};

function setcnt(input = 'count') {
	cnttype = input;
	if ( charttype != 'table' ) {
		drawGraph(charttype);
	}; 
};

function drawGraph(type='table') {
	charttype = type;
	var input; var fldnum = json[0].length - cntcols;
	var cntcol = fldnum;
	if ( type == 'table' ) {
		input = json;
	} else if ( type == 'geomap' ) {
		// Split geolocation 
		input = []; 
		if ( cnttype == 'wpm' && cntcols > 1 ) {
			cntcol = fldnum+2;
		} else {
			cntcol = fldnum;
		};
		for ( var i=0; i<json.length; i++ ) {
			var row = json[i];
			if ( row[0]['id'] ) {
				input.push(['Lat', 'Lang', json[i][cntcol]['label']]);
			} else {
				latlang = json[i][0].split(' ');
				if ( latlang.length == 2 ) { input.push([Number(latlang[0]), Number(latlang[1]), json[i][cntcol].toString()]); };
			}; 
		};
		fldnum = 2; // We now have 2 columns
		headrow = false;
	} else { 
		// Merge cells unless we have a table
		input = []; 
		if ( cnttype == 'wpm' && cntcols > 1 ) {
			cntcol = fldnum+2;
		} else {
			cntcol = fldnum;
		};
		for ( var i=0; i<json.length; i++ ) {
			var row = json[i];
			if ( row[0]['id'] ) {
				var fldlabs = row.slice(0,fldnum).map(function(item) { return item['label']; });
				input.push([fldlabs.join(' + '), json[i][cntcol]]);
			} else {
				input.push([json[i].slice(0,fldnum).join('+'), json[i][cntcol]]);
			}; 
		};
		fldnum = 1; // We now have only 1 column left
	};
	
	data = google.visualization.arrayToDataTable(input, headrow);
	data.sort({column: fldnum, desc: true}); 

	if ( type == 'pie' ) {
		options = {
			legend: { position: 'bottom',  },
			sliceVisibilityThreshold: .03,
			chartArea : { top: 0, left: 5 }
		};

		viewport.style.height = '600px';
		chart = new google.visualization.PieChart(viewport);
		chart.draw(data, options);
	} else if ( type == 'piehole' ) {
		options = {
			legend: { position: 'right' },
			pieHole: 0.5,
			sliceVisibilityThreshold: .01,
			chartArea : { top: 15, left: 5 }
		};

		viewport.style.height = '600px';
		chart = new google.visualization.PieChart(viewport);
		chart.draw(data, options);

	} else if ( type == 'table' ) {
		options = {
			legend: 'none',
		};

		viewport.style.height = 'auto';
		chart = new google.visualization.Table(viewport);
		chart.draw(data, options);

	} else if ( type == 'histogram' ) {
		options = {
			legend: 'none',
		};

		viewport.style.height = '600px';
		chart = new google.visualization.Histogram(viewport);
		chart.draw(data, options);

	} else if ( type == 'lines' ) {
		options = {
			legend: 'none',
			curveType: 'function',
		};
		data.sort({column: 0, desc: false}); 

		viewport.style.height = '600px';
		chart = new google.charts.Line(viewport);
		chart.draw(data, options);

	} else if ( type == 'scatter' ) {
		options = {
			legend: 'none',
			curveType: 'function',
		};
		data.sort({column: 0, desc: false}); 

		viewport.style.height = '600px';
		chart = new google.charts.Scatter(viewport);
		chart.draw(data, options);

	} else if ( type == 'geochart' ) {
	
		options = {
	        displayMode: 'markers',
	        enableRegionInteractivity: true,
			showZoomOut: true,
		}; 
		console.log(options);

		viewport.style.height = '600px';
		chart = new google.visualization.GeoChart(viewport);
		chart.draw(data, options);

	} else if ( type == 'geomap' ) {
	
		options = {
          showTooltip: true,
          showInfoWindow: true
		}; 

		viewport.style.height = '600px';
		chart = new google.visualization.Map(viewport);
		chart.draw(data, options);

	} else if ( type == 'bars' ) {

		var height = data.getNumberOfRows() * 30 + 60;
		options = {
			legend: null,
			bars: 'horizontal',
			chartArea : { top: 0, left: "auto" },
			height: height
		};

		viewport.style.height = 'auto';
		var view = new google.visualization.DataView(data);
		view.setColumns([0, 1]);
		chart = new google.charts.Bar(viewport);
		chart.draw(view, options);

	};
	google.visualization.events.addListener(chart, 'select', selectHandler);
	google.visualization.events.addListener(chart, 'regionClick', regionClick);

	// Turn off image download buttons if there is no SVG (tables only)
	var imgbuts = document.getElementsByClassName('imgbut');
	for ( var i=0; i<imgbuts.length; i++ ) {
		if ( charttype == 'table' ) {
			imgbuts[i].disabled = true;
		} else {
			imgbuts[i].disabled = false;
		};
	};

}

function regionClick(e) {
		options['region'] = e['region'];
		options['resolution'] = 'provinces';
		options['enableRegionInteractivity'] = false;
		chart.draw(data, options);
};

function selectHandler(e) {
	var fldnum = json[0].length - cntcols;
	var sel = chart.getSelection(); var val; 
	if ( !sel[0] ) { return -1; };
	var val = json[sel[0]['row']+1].slice(0,fldnum);
	var fld = json[0].slice(0,fldnum);
	if ( val[0].indexOf('+') != -1 ) {
		val = val[0].split('+');
	};
	var linkfld = document.getElementById('linkfield');
	var fldlabs = fld.map(function(item) { return item['label']; });
	
	if ( typeof(cql) != undefined ) {
		linkfld.innerHTML = '<p>Search for ' + fldlabs.join('+') + ' = ' + val.join('+');
		var tokrest = ''; var sep1 = ''; var sep2 = ''; var matchrest = '';
		var tmp = cql.match(/:: (.*)/i);
		if ( tmp !=  null ) {
			matchrest = tmp[1];
		}
		var tmp = cql.match(/:: (.*)/i);
		if ( tmp !=  null ) {
			matchrest = tmp[1]; sep1 = " & ";
			if ( matchrest.indexOf('within') != -1 ) { matchrest = matchrest.substr(0,matchrest.indexOf('within'))}
		}
		var tmp = cql.match(/\[([^\]]+)\]/i);
		if ( tmp !=  null ) {
			tokrest = tmp[1]; sep2 = " & ";
		}
		for ( var i=0; i<fld.length; i++ ) {
			var j = fld[i];
			var fldid = fld[i]['id'];
			if ( fldid.indexOf('_') != -1 ) {
				matchrest = matchrest + sep1 + 'match.' + fldid + '="'+val[i]+'"';
				sep1 = ' & ';
			} else {
				tokrest = tokrest + sep2 + fldid + '="'+val[i]+'"';
				sep2 = ' & ';
			};
		}; 
		var newcql = '['+tokrest+'] ';
		if ( matchrest != '' ) { newcql += ' :: ' + matchrest; };
		var url = 'index.php?action=cqp&cql=' + encodeURIComponent(newcql);
		linkfld.onclick = function () { window.open(url, '_self'); };
	};
};