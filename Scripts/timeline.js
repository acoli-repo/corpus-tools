// Interactive Timeline Script

var rightnow = new Date(Date.now());
var day = rightnow.getDate();
var month = rightnow.getMonth() + 1;
var year = rightnow.getFullYear();
var date = year+'-'+('0000'+month).slice(-2)+'-'+day;

var container = document.getElementById('visualization');
var info = document.getElementById('info');
var periods = [];

var options = {
	showCurrentTime: true,
	max: rightnow,
//     showMinorLabels: false,
//     showMajorLabels: false,
//     timeAxis: { scale: 'hour', step: 0.1 },
//     format: {
//       majorLabels: function(date, scale, step) {
// 		var seconds = Math.round(new Date(date).getTime() / 1000);
// 		return seconds;
//       }
//     }
};
var data = new vis.DataSet(options);

  // Create a Timeline
  var timeline = new vis.Timeline(container, data, options);
  
  timeline.on('select', function (properties) {
  	info.innerHTML = '';
  	properties.items.forEach(function(value, index, array) {
		  var xhttp = new XMLHttpRequest();
		  xhttp.onreadystatechange = function(value) {
			if (this.readyState == 4 && this.status == 200) {
			 info.innerHTML = this.responseText;
			}
		  };
		  xhttp.open("GET", "index.php?action=ajax&data=page&id=timeline_"+value+".html", true);
		  xhttp.send();
  	});
  });
  timeline.on('rangechange', function (properties) {
	 if (!properties.byUser) return
	var newwindow = timeline.getWindow();
	var date1 = newwindow.start; var date2 = newwindow.end; 
	var newrange = date2.getTime()-date1.getTime();
	var newzoom = Math.max(0, Math.floor(Math.log10(newrange/1000)));
	if ( newzoom != zoomlevel ) {
		zoomlevel = newzoom; 
		resetdata();
	};
  });

var zoomlevel = 0;
function setgroup(num) {
	var years = 10**num;
	zoomlevel = num;
	rightnow = new Date(Date.now());
	var begin = new Date(Date.now()-years*1000);
	timeline.setWindow(begin, rightnow);
	timeline.setOptions({ timeAxis: {step: 10*(1/years) }, });
	resetdata();
};

function resetdata() {
  	info.innerHTML = '';
	data.clear();
	datelist.forEach(function(value, index, array) {
		if ( !( value.minzoom && value.maxzoom && zoomlevel ) || ( value.minzoom <= zoomlevel && value.maxzoom >= zoomlevel ) ) {
			value.group = zoomlevel;
			data.add(value);
		};
	}); 
};

function setperiod(id) {
	var pardata = periods[id];
	console.log(pardata);
	setgroup(pardata.zoom);
	document.getElementById('pername').innerHTML = pardata.display;

	pardata.items.forEach(function(value, index, array) {
		value.group = id;
		data.add(value);
	}); 

	timeline.setWindow(pardata.start, pardata.end);
};

resetdata();
