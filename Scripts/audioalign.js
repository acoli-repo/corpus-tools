document.onkeypress = keyEvent; 
var audioidx = -1;
var startend = 0;
var offset = 0.5;
var hlrow = -1;
var nextidx = -1;
var endtime = 0;

function keyEvent(evt) { 
    var charCode = (typeof evt.which == "number") ? evt.which : evt.keyCode
   
   	if ( (charCode == 115 || charCode == 32) && audioidx == -1 ) {
   		// begin
		audioidx++;
		var audio = document.getElementById('track');
		audio.play();
   	} else if ( charCode == 115 || charCode == 32 ) {
		var audio = document.getElementById('track');
		var timeidx = audio.currentTime.toFixed(5);
		console.log(timeidx);
		if ( startend && audioidx > 0 ) {
			document.audioform['end['+audioidx+']'].value = timeidx;
			nextRow();
		};
		document.audioform['start['+audioidx+']'].value = timeidx - offset;
   	} else if ( charCode == 101 ) {
		var audio = document.getElementById('track');
		var timeidx = audio.currentTime;
		document.audioform['end['+audioidx+']'].value = timeidx;
		nextRow();
   	} else {
   		console.log(charCode);
   	};
};

function rowHighlight(gohl) {
	// Unhighlight the highlighted row
	if ( hlrow > -1 ) {
		document.getElementById('row'+hlrow).style['background-color'] = '';	
	};
	document.getElementById('row'+gohl).style['background-color'] = '#ffeeaa';
	hlrow = gohl;
};

function gotoRow(gohl) {
	audioidx = gohl;
	rowHighlight(gohl);
	nextidx = document.audioform['end['+audioidx+']'].value;
	var timeidx = document.audioform['start['+audioidx+']'].value;
	var audio = document.getElementById('track');
	if ( timeidx ) {
		audio.currentTime = timeidx;
		audio.play();
	} else if ( audioidx > 0 ) {
		var previdx = document.audioform['end['+(audioidx-1)+']'].value;
		if ( previdx ) {
			audio.currentTime = previdx;
			audio.play();
		};
	};
};

function nextRow() {
	audioidx++;
	rowHighlight(audioidx);
	nextidx = document.audioform['end['+audioidx+']'].value;
	console.log(nextidx);
};

function checkaudio ( ) {	
	var audio = document.getElementById('track');
	if ( audioidx == -1 ) { nextRow(); };
	if ( audio.src == '' ) { return -1; };
	if ( audio.currentTime > endtime && endtime > 0  ) { audio.pause(); endtime = 0; }; 
	if ( audio.currentTime > nextidx && nextidx > 0  ) { 
		nextRow();
	}; 
};
