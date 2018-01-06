
function downloadCSV() {
	var data = google.visualization.arrayToDataTable(json, headrow);
	var csv = google.visualization.dataTableToCsv(data);
	window.open('data:text/csv;base64,' + btoa(csv), '_self');
};

function downloadSVG() {
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

function drawChart(type='table') {

	var input; var fldnum = json[0].length -1;
	var svgbut = document.getElementById('svgbut');
	if ( type == 'table' ) {
		input = json;
		svgbut.disabled = true;
	} else {
		// Merge cells unless we have a table
		input = [];
		for ( var i=0; i<json.length; i++ ) {
			var row = json[i];
			if ( row[0]['id'] ) {
				var fldlabs = row.slice(0,fldnum).map(function(item) { return item['label']; });
				input.push([fldlabs.join(' + '), json[i][fldnum]]);
			} else {
				input.push([json[i].slice(0,fldnum).join('+'), json[i][fldnum]]);
			};
		};
		svgbut.disabled = false;
	};
	
	var data = google.visualization.arrayToDataTable(input, headrow);

	if ( type == 'pie' ) {
		charttype = type;
		var options = {
			legend: { position: 'bottom',  },
			sliceVisibilityThreshold: .03,
			chartArea : { top: 0, left: 5 }
		};

		viewport.style.height = '80%';
		chart = new google.visualization.PieChart(viewport);
		chart.draw(data, options);
	} else if ( type == 'piehole' ) {
		var options = {
			legend: { position: 'right' },
			pieHole: 0.5,
			sliceVisibilityThreshold: .01,
			chartArea : { top: 15, left: 5 }
		};

		viewport.style.height = '80%';
		chart = new google.visualization.PieChart(viewport);
		chart.draw(data, options);

	} else if ( type == 'table' ) {
		var options = {
			legend: 'none',
		};

		viewport.style.height = 'auto';
		chart = new google.visualization.Table(viewport);
		chart.draw(data, options);

	} else if ( type == 'bars' ) {

		var height = data.getNumberOfRows() * 30 + 60;
		var options = {
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
}

function selectHandler(e) {
	var fldnum = json[0].length -1;
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