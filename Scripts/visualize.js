var cntcols; var data; var options; var headrow;
var chart; var charttype; var cnttype;

function downloadData ( dltype='tsv' ) {
	if ( dltype == 'json' ) {
		window.open('data:text/csv;base64,' + btoa(JSON.stringify(json)), '_self');
	} else if ( dltype == 'csv' ) {
		data = google.visualization.arrayToDataTable(json, headrow);
		var csv = "SEP=,\n" + google.visualization.dataTableToCsv(data);
		window.open('data:text/csv;base64,' + btoa(csv), '_self');
	} else if ( dltype == 'png' ) {
		var uri = chart.getImageURI();
		window.open(uri, '_new');
	} else if ( dltype == 'svg' ) {
		var tmp = document.getElementsByTagName('svg');
		var svg = tmp[0];
		console.log(svg);
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

			//add xml declaration (no longer seems to work, nor does the CHARSET)
			// source = '<?xml version="1.0" standalone="no"?>\r\n' + source;

			//convert svg source to URI data scheme.
			var url = "data:image/svg+xml,"+encodeURIComponent(source);	
			window.open(url, '_new');
		} else {
			console.log('No SVG found, table?');
		};
	};
};

function setcnt(input = 1) {
	cnttype = input;

	data = google.visualization.arrayToDataTable(json, headrow);
	var newcol = json[0].length-1-cntcols+parseInt(cnttype);
	if ( newcol > json[0].length-1 ) { newcol = json[0].length - 1; };
	var minval = data.getColumnRange(newcol).min;
	var disabled = false; 
	
	// Pies are not allowed to have negative values 
	if ( minval < 0 ) {
		disableView('pie,piehole', 1);
		if ( charttype == 'pie' || charttype == 'piehole' ) { disabled = true; };
	} else {
		disableView('pie,piehole', 2);
	};
	
	if ( charttype != 'table' && !disabled ) {
		drawGraph(charttype);
	}; 
};

function disableView(views, hide=0) {

	var todo = views.split(',');
	var sel = document.getElementById('graphselect');
		
	if ( sel != null ) 	
	for ( var i=0; i<sel.length; i++) {
		var opt = sel[i];
		if ( todo.indexOf(opt['value']) != -1 ) {
			if ( hide == 1 ) {
				opt.disabled = true;
			} else if ( hide == 2 ) {
				opt.disabled = false;
			} else {			
				opt.style.display = 'none';
			};
		};
	};

};

function drawGraph(type='table') {

	// disable some options
	if ( json[0][0]['type'] != "number" ) {
		disableView('trendline');
	} else {
		// non-number fields are just cast to strings dynamically
	};

	charttype = type;
	var input; var fldnum = json[0].length - cntcols;
	input = json;

	var cntcol = fldnum + parseInt(cnttype) -1;
	if ( cntcol > json[0].length-1 ) { cntcol = json[0].length - 1; }; // Safety measure in case cntcols is wrong

	if ( type == 'geomap' ) {
		// Split geolocation 
		input = []; 
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
	} else if ( type != "table" && type != "totals"  ) { 
		// Merge cells unless we have a table
		input = []; 
		fldnum = fldnum-1; // Kill the percentage column
		for ( var i=0; i<json.length; i++ ) {
			var row = json[i];
			if ( row[0]['id'] ) {
				var fldlabs = row.slice(0,fldnum).map(function(item) { return item['label']; });
				input.push([fldlabs.join(' + '), json[i][cntcol]]);
			} else {
				if ( fldnum > 1 || charttype == 'pie' || charttype == 'piehole' ) {
					input.push([json[i].slice(0,fldnum).join('+'), json[i][cntcol]]);
				} else {
					input.push([json[i][0], json[i][cntcol]]);
				};
			}; 
		};
		fldnum = 1; // We now have only 1 column left
	};

	if ( input.length == 0 || ( !headrow && input.length == 1 ) ) {
		viewport.innerHTML = '<i>No data to show</i>';
		return -1;
	};
	if ( !headrow ) { headrow = false; };

	data = google.visualization.arrayToDataTable(input, headrow);
	data.sort({column: fldnum, desc: true}); 
	
	if ( cntcols == 3 && charttype == "table" ) {
		// Format WPM with two digits after the comma
		var formatter1 = new google.visualization.NumberFormat({pattern:'###,##0.00'});
		formatter1.format(data, fldnum+2);
	};

	switch ( type  ) {
	case 'pie' :
		options = {
			legend: { position: 'bottom',  },
			sliceVisibilityThreshold: .03,
			chartArea : { top: 0, left: 5 },
			pieSliceText: 'label',
		};

		viewport.style.height = '600px';
		chart = new google.visualization.PieChart(viewport);
		break;

	case 'piehole' :
		options = {
			legend: { position: 'right' },
			pieHole: 0.5,
			sliceVisibilityThreshold: .01,
			chartArea : { top: 15, left: 5 },
			pieSliceText: 'label',
		};

		viewport.style.height = '600px';
		chart = new google.visualization.PieChart(viewport);
		break;

	case 'table' :
		options = {
			legend: 'none',
		};

		viewport.style.height = 'auto';
		chart = new google.visualization.Table(viewport);
		chart.draw(data, options);
		break;

	case 'histogram' :
		options = {
			legend: 'none',
		};

		viewport.style.height = '600px';
		chart = new google.visualization.Histogram(viewport);
		break;

	case 'lines' :
		options = {
			legend: 'none',
			curveType: 'function',
		};
		data.sort({column: 0, desc: false}); 

		viewport.style.height = '600px';
		chart = new google.charts.Line(viewport);
		break;

	case 'scatter' :
		options = {
			legend: 'none',
			hAxis: { title: json[0][0]['label'] },
			vAxis: { title: json[0][cntcol]['label'] },
    	};
		data.sort({column: 0, desc: false}); 

		viewport.style.height = '600px';
		chart = new google.charts.Scatter(viewport);
		break;

	case 'trendline' :
		options = {
			legend: 'none',
			chartArea : { top: 20, left: 'auto' },
			hAxis: { title: json[0][0]['label'] },
			vAxis: { title: json[0][cntcol]['label'] },
    	    crosshair: { trigger: 'both' }, // Display crosshairs on focus and selection.
		    trendlines: { 0: { color: 'green' } },    // Draw a trendline for data series 0.
		};

		// TODO: the hAxis title shows too low, solution?

		// For a trendline, we need to have a number column with unique numbers # TODO: Do we?
		// data = google.visualization.data.group(data, [{'column': 0, 'type': 'number'}], [{'column': cntcol, 'aggregation': google.visualization.data.sum, 'type': 'number'}] );
		data.sort({column: 0, desc: false}); 

		viewport.style.height = '600px';
		chart = new google.visualization.ScatterChart(viewport);
		break;

	case 'geochart' :
		options = {
	        displayMode: 'markers',
	        enableRegionInteractivity: true,
			showZoomOut: true,
		}; 

		viewport.style.height = '600px';
		chart = new google.visualization.GeoChart(viewport);
		break;

	case 'geomap' :
		options = {
          showTooltip: true,
          showInfoWindow: true
		}; 

		viewport.style.height = '600px';
		chart = new google.visualization.Map(viewport);
		break;

	case 'bars' :
		var height = data.getNumberOfRows() * 30 + 60;
		options = {
			legend: null,
			bars: 'horizontal',
			chartArea : { top: 0, left: "auto" },
			height: height
		};

		viewport.style.height = 'auto';
		chart = new google.charts.Bar(viewport);
		break;

	case 'totals' :
		headrow = false;

		var data = new google.visualization.DataTable();

		// Declare columns
		data.addColumn('string', 'Measure');
		data.addColumn('number', 'Value');

		// Make an array for jStat
		var myVect = []; 
		for ( var i=1; i<json.length; i++ ) {
			var val = json[i][cntcol];
			myVect.push(val);
		};
		jObj = jStat( myVect );
		
		data.addRow(['Rows', myVect.length]);
		data.addRow(['Total', jObj.sum()]);
		data.addRow(['Minimum', jObj.min()]);
		data.addRow(['Maximum', jObj.max()]);
		data.addRow(['Mean', jObj.mean()]);
		data.addRow(['Median', jObj.median()]);
		var mode = jObj.mode(); if ( typeof(mode) == "number") data.addRow(['Mode', mode]);
		data.addRow(['Range', jObj.range()]);
		data.addRow(['Standard deviation', jObj.stdev()]);
		data.addRow(['Mean deviation', jObj.meandev()]);
		data.addRow(['Median deviation', jObj.meddev()]);
		data.addRow(['Mean squared error', jObj.meansqerr()]);
		
		options = {
			legend: 'none',
		};

		var formatter1 = new google.visualization.NumberFormat({pattern:'###,##0.00'});
		formatter1.format(data, 1);

		viewport.style.height = 'auto';
		chart = new google.visualization.Table(viewport);
		break;

	};

	chart.draw(data, options);
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
	
	if ( typeof(cql) != undefined && charttype != "totals" ) {
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