// Audiocontrol depends on there being an element called "track"
// It should in principle even work with video, but unchecked as of yet

var endtime = 0; // Disable stop at end time
var showaudio = false;
var playimage = false;
if ( typeof(playimg1) == undefined || typeof(playimg1) == 'undefined' ) {
	var playimg1 = 'Images/playbutton.gif';
};
var playimg2 = playimg1.replace('button.gif', 'button2.gif');
var audiolist = new Array();

// We should check whether we can play this sound

function playpart(file, start, stop, img ) {	
	var audio = document.getElementById('track');
	audio.currentTime = 0; 
	endtime = stop;
	if ( audio.src != file && file != '' ) { 
		audio.src = file; 
		playimage = img;
		if ( playimage ) { playimage.src = playimg2; };
		audio.onloadedmetadata = function() {
			this.currentTime = start; 
			this.play();
		};
	} else {	
		audio.currentTime = start; 
		audio.play();
	};
};
function checkstop ( ) {	
	var audio = document.getElementById('track');
	if ( audio.src == '' ) { return -1; };
	if ( audio.currentTime > endtime && endtime > 0  ) { 
		audio.pause(); endtime = 0; 
		if ( playimage ) { playimage.src = playimg1; playimage = false; };
	} else if ( playimage ) {
		if ( playimage.src == playimg1 ) {
			playimage.src = playimg2;
		} else {
			playimage.src = playimg1;
		};
	};
};
function continueplay() {	
	var audio = document.getElementById('track');
	audio.play();
};


function makeaudio() {
	
	var iterator = document.evaluate('//*[@start]', document, null, XPathResult.ANY_TYPE, null );
	try {
	  var thisNode = iterator.iterateNext();
  
	  while (thisNode) {
		if ( thisNode.nodeName != "TOK"  || typeof(audiotok) != 'undefined' ) {
			audiolist.push(thisNode);
		};
		thisNode = iterator.iterateNext();
	  }	
	}
	catch (e) {
	  console.log( 'Error: Document tree modified during iteration ' + e );
	}

	for ( var i=0; i<audiolist.length; i++ ) {
		thisNode = audiolist[i];
		
		var start = thisNode.getAttribute('start');
		var end = thisNode.getAttribute('end');

		var audioelm = document.createElement("img");
		audioelm.src = playimg1;
		audioelm.start = start;
		audioelm.width = 14;
		audioelm.height = 14;
		audioelm.style.marginRight = 5 + 'px';
		audioelm.style.display = 'none';
		audioelm.end = end;
		audioelm.onclick = function() { playpart('', this.start, this.end ); };

		if ( thisNode.firstChild ) {
			var tmp = thisNode.insertBefore( audioelm, thisNode.firstChild );
		} else {
			var tmp = thisNode.parentNode.insertBefore( audioelm, thisNode );
		};

	};

};

function toggleaudio () { // Show or hide images
	var but = document.getElementById('btn-audio');
	if ( showaudio ) {
		showaudio = false;
		if (but && typeof(but.style) == "object") {
			but.style['background-color'] = '#FFFFFF';
		};
	} else {
		showaudio = true;
		if (but && typeof(but.style) == "object") {
			but.style['background-color'] = '#eeeecc';
		};
	};
	document.cookie = 'toggleaudio='+showaudio;

	// Show/hide all IMG elements inside MTXT
	var its = mtxt.getElementsByTagName("img");
	for ( var a in its ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( !it.start ) { continue; }; // this is not a sound control image
		if ( showaudio ) {
			it.style.display = 'inline-block';
		} else {
			it.style.display = 'none';
		};
	};
};
