document.onclick = clickEvent; 
// document.onmouseover = mouseEvent; 
// document.onmouseout = mouseOut; 

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	
	// We might be hovering over a child of our utterance
	if ( element.parentNode && element.parentNode.tagName == "U" ) { element = element.parentNode; };
	if ( element.parentNode && element.parentNode.parentNode && element.parentNode.parentNode.tagName == "U" ) { element = element.parentNode.parentNode; };

	if (element.tagName == "U" ) {
		var start = element.getAttribute("start")*1;
		var stop = element.getAttribute("end")*1;
		wavesurfer.seekTo(start / wavesurfer.getDuration())
		wavesurfer.play(start, stop);
	};
};

var wavesurfer = Object.create(WaveSurfer);
var waveform = document.getElementById('waveform');
var mtxt = document.getElementById('mtxt');
var speed = 1; 
var zoom = 100;

wavesurfer.init({
	container: document.querySelector('#waveform'),
	// backend: 'WebAudio',
	pixelRatio: 1,
	// normalize: false,
	// barWidth: 2,
	// barHeight: 200,
	// responsive: false,	
	scrollParent: true,
	waveColor: '#992200',
	autoCenter: true,
	audioRate: 1,
	fillParent: false,
	renderer: 'MultiCanvas',
	// splitChannels: true,
});

var minimap;
var uttarray = Array();
var regionarray = Array();
var durtxt;

wavesurfer.load(soundfile);
wavesurfer.on('ready', function () {
	document.getElementById('loading').style.display = 'none';
	document.getElementById('waveblock').style.visibility = 'visible';

	setzoom(1);

	minimap = wavesurfer.initMinimap({
		height: 30,
		waveColor: '#ddd',
		progressColor: '#999',
		cursorColor: '#68A93D',
		barHeight: 1.4
	});

	// Load the utterances
	var uttlist = Array(); 
	var mtch = document.evaluate("//u", waveform, null, XPathResult.ANY_TYPE, null);
	var utt = mtch.iterateNext(); 
	while ( utt != null )  { 
		var start = utt.getAttribute("start")*1;
		var stop = utt.getAttribute("end")*1;
		uttlist.push(utt);
		uttarray[start] = utt;
		utt = mtch.iterateNext(); 
	};		
	for ( i=0; i<uttlist.length; i++ ) {
		var utt = uttlist[i];
		var start = 1*utt.getAttribute('start');
		var stop = 1*utt.getAttribute('end');
		
		regionarray[start] = wavesurfer.addRegion({
			start: start, // time in seconds
			end: stop, // time in seconds
			color: 'hsla(0, 0%, 0%, 0)'
		});
	};
	
	// Now, resize the mtxt to fill the whole space below the wavesurfer element
	var setheight = window.innerHeight - mtxt.offsetTop - 5;
	mtxt.style.height = setheight + 'px';
	durtxt = ftime(wavesurfer.getDuration());
	
	if ( jmp ) {
		// Jump to a token
		console.log(jmp);
		var mtch = document.evaluate("//*[@id=\""+jmp+"\"]/ancestor::u", mtxt, null, XPathResult.ANY_TYPE, null);
		var utt = mtch.iterateNext(); 
		scrollToElementD(utt);
		utt.style.backgroundColor = "#ffffcc";
		wavesurfer.seekTo(utt.getAttribute('start') / wavesurfer.getDuration())

	};
	
	showtime();
	
});
wavesurfer.on('region-in', aligntranscription);
wavesurfer.on('loading', showload);
wavesurfer.on('audioprocess', showtime);

var lastutt;
function aligntranscription (region, e) {
	var idx = region.start;
	
	if ( uttarray[idx] ) {
		if (lastutt) lastutt.style.backgroundColor = "";
		uttarray[idx].style.backgroundColor = "#ffffcc";
		scrollToElementD(uttarray[idx]);
		lastutt = uttarray[idx];
	};
};



function ftime (ms) {
	var x = Math.floor(ms);
	
	var string = "000" + Math.floor((ms-x)*1000); 
	string = "." + string.substr(-3);

	var secs = x % 60; 
	string = secs + string;
	if ( x < 60 ) return string;
	x = Math.floor(x/60);
	if ( secs < 10 ) string = "0" + string;

	var mins = x % 60;  
	string = mins + ":" + string;
	if ( x < 60 ) return string;
	x = Math.floor(x/60);
	if ( mins < 10 ) string = "0" + string;

	var hours = x;  
	string = hours + ":" + string;
	
	return string;
};

function showtime(e) {	
	document.getElementById('timeindex').innerHTML = ftime(wavesurfer.getCurrentTime()) + " / " + durtxt;
};

function showload(e){
	document.getElementById('loading').innerHTML = "Loading wave file: " + e + "%"
	if ( e == 100 ) {
		document.getElementById('loading').innerHTML += "<p>Drawing wave form, please wait"
	}; 
};

function scrollToElementD(elm){
	var topPos = elm.offsetTop;
	mtxt.scrollTop = topPos - mtxt.offsetTop - (mtxt.offsetHeight/2) + 10;
}

function setspeed (factor) {
	speed = speed * factor;
	var speedtxt = Math.floor(speed*100) + "%";
	wavesurfer.setPlaybackRate(speed);
	document.getElementById('speedtxt').innerHTML = speedtxt;
}

function setzoom (factor) {
	zoom = zoom * factor;
	var zoomtxt = Math.floor(zoom) + " pps";
	wavesurfer.zoom(zoom);
	document.getElementById('zoomtxt').innerHTML = zoomtxt;
}

function playpause(e) {
	if ( wavesurfer.isPlaying() ) {
		e.innerHTML = '<i class=\"material-icons\">play_arrow</i>';
	} else {
		e.innerHTML = '<i class=\"material-icons\">pause</i>';
	};
	wavesurfer.playPause();
};