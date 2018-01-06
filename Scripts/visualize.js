
function downloadCSV() {
	var data = google.visualization.arrayToDataTable(json, headrow);
	var csv = google.visualization.dataTableToCsv(data);
	window.open('data:text/csv;base64,' + btoa(csv), '_self');
};

function drawChart(type='table') {

	var input; var fldnum = json[0].length -1;
	if ( type == 'table' ) {
		input = json;
	} else {
		// Merge cells unless we have a table
		input = [];
		for ( var i=0; i<json.length; i++ ) {
			var row = json[i];
			input.push([json[i].slice(0,fldnum).join('+'), json[i][fldnum]]);
		};
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
			legend: 'none',
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
	linkfld.innerHTML = '<p>Search for ' + fldlabs.join('+') + ' = ' + val.join('+');
	/* TODO: make this also take the base query into account */
	var tokrest = ''; var sep = '';
	for ( var i=0; i<fld.length; i++ ) {
		var j = fld[i];
		tokrest = tokrest + sep + fld[i]['id'] + '="'+val[i]+'"';
		sep = ' & ';
	}; 
	var newcql = '['+tokrest+']';
	var url = 'index.php?action=cqp&cql=' + encodeURIComponent(newcql);
	linkfld.onclick = function () { window.open(url, '_self'); };
};