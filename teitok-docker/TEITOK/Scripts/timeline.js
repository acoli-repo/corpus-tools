// Interactive Timeline Script

var rightnow = new Date(Date.now());
var day = rightnow.getDate();
var month = rightnow.getMonth() + 1;
var year = rightnow.getFullYear();
var date = year+'-'+('0000'+month).slice(-2)+'-'+day;

var container = document.getElementById('visualization');
var info = document.getElementById('info');
var periods = [];
var itlist = [];

var options = {
	showCurrentTime: true,
	max: rightnow,
};
var data = new vis.DataSet(options);

  // Create a Timeline
  var timeline = new vis.Timeline(container, data, options);
  
  timeline.on('select', function (properties) {
  	info.innerHTML = '';
  	properties.items.forEach(function(value, index, array) {
  		var tlit = itlist[value];
  		if ( tlit.className == 'document' ) {
			info.innerHTML = '<h2>'+value+'</h2><ul>';
			tlit.list.forEach(function(value, index, array) {
				info.innerHTML += '<li>'+value+'</li>';
			});
  		} else { // if ( tlit.className == 'event' ) {
		  var xhttp = new XMLHttpRequest();
		  xhttp.onreadystatechange = function(value) {
			if (this.readyState == 4 && this.status == 200) {
			 info.innerHTML = this.responseText;
			}
		  };
		  xhttp.open("GET", "index.php?action=ajax&data=page&id=timeline_"+value+"", true);
		  xhttp.send();
		  if ( typeof(cqpfld) != 'undefined' ) {
			  xhttp = new XMLHttpRequest();
			  xhttp.onreadystatechange = function(value) {
				if (this.readyState == 4 && this.status == 200) {
				 info.innerHTML += this.responseText;
				}
			  };
			  xhttp.open("GET", "index.php?action=ajax&data=doctable&cqp="+cqpfld+"%20%3D%20\""+value+"\"", true);
			  xhttp.send();
		  };
		};
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
  	info.innerHTML = '';
};

function resetdata() {
	data.clear();
	itlist = [];
	datelist.forEach(function(value, index, array) {
		if ( !( value.minzoom && value.maxzoom && zoomlevel ) || ( value.minzoom <= zoomlevel && value.maxzoom >= zoomlevel ) ) {
			value.group = zoomlevel;
			data.add(value);
			itlist[value.id] = value;
		};
	}); 
};

resetdata();
