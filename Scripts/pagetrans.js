document.onkeydown = function(evt) {
	evt = evt || window.event;
	if (evt.keyCode == 27) {
		unfullscreen();
	}
};

if (document.addEventListener) {
	document.addEventListener('webkitfullscreenchange', fstoggle, false);
	document.addEventListener('mozfullscreenchange', fstoggle, false);
	document.addEventListener('fullscreenchange', fstoggle, false);
	document.addEventListener('MSFullscreenChange', fstoggle, false);
};

function fstoggle() {
	if ( ( document.webkitIsFullScreen && typeof(document.webkitIsFullScreen) != "undefined" ) || document.mozFullScreen || ( document.msFullscreenElement !== null && typeof(document.msFullscreenElement) != "undefined" ) ) {
	} else {
		unfullscreen();
	};	
};

var zoomset = 0;
var fontsize = 16;
var full = false;

var image = new Image(); var imgsrc;
function cropbgimg ( divelm, bboxtxt, bgsrc = '', keep = 'width', maxscale = 1.2) {
	if ( typeof(divelm) != "object" ) {
		divelm = document.getElementById(divelm + '');
	};
	
	var orgwidth = divelm.offsetWidth;

	if ( bgsrc == "" ) { 
		bgsrc = divelm.getAttribute('bgsrc'); 
	};
	
	if ( bgsrc != '' ) { 
		var bgimgtxt = 'url(\'' + bgsrc + '\')';
		divelm.style.backgroundImage = bgimgtxt; 
	} else {
		bgsrc = divelm.style
                      .backgroundImage
                       .replace(/url\((['"])?(.*?)\1\)/gi, '$2')
                        .split(',')[0];
	};

	if ( imgsrc != bgsrc ) {
	    image = new Image();
	    imgsrc = bgsrc;
   		image.src = bgsrc;
	};
	
	if ( bboxtxt == "" ) { 
		bboxtxt = divelm.getAttribute('bbox'); 
	};
	
	var bbox = bboxtxt.split(' ');
	// Never scale more than 50% up
	if ( keep == 'width' ){  	
		var imgscale  = Math.min(maxscale, divelm.offsetWidth/(bbox[2]-bbox[0]));
	} else {
		var imgscale  = Math.min(maxscale, divelm.offsetHeight/(bbox[3]-bbox[1]));
	};
	
	var biw = image.width*imgscale;
	var bih = biw*(image.height/image.width);
	var bix = bbox[0]*imgscale;
	var biy = bbox[1]*imgscale;

	divelm.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
	divelm.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
	divelm.style['background-size'] = biw+'px '+bih+'px';
	divelm.style['background-position'] = '-'+bix+'px -'+biy+'px';
	divelm.setAttribute('orgbpos', '-'+bix+'px -'+biy+'px');  			
};

function changestatus(elm, status='', max='') {
	// Change the status of a line (verified, unverified, ...)
	var statcols = ['#dddddd', '#ffcc44', '#66ff66', '#00ff00', '#ff0000'];
	var stattxt = ['unverified', 'partially verified', 'verified', 'locked', 'XML error'];
	var lineid = elm.getAttribute('tid');
	var statfld = document.getElementById('linestat-'+lineid);
	var curstat;
	if ( statfld ) { curstat = statfld.value; };
	var newstat;
	if ( status != '' && max ) {
		newstat = Math.max(curstat, status);
	} else if ( status != '' ) {
		newstat = status;
	} else if ( curstat < 2 ) {
		newstat = 2;
	} else {
		newstat = 1;
	};
	elm.style.backgroundColor = statcols[newstat];
	elm.title = stattxt[newstat];
	
	// Now change the value of the input field
	if ( statfld ) { statfld.value = newstat; };
};

var linestats = document.getElementsByClassName('linestat');
for ( var i=0; i<linestats.length; i++ ) {
	linestat = linestats[i];
	statval = document.getElementById('linestat-'+linestat.getAttribute('tid')).value;
	var rawxml = document.getElementById('line-'+linestat.getAttribute('tid')).value;
	rawxml = '<line>' + rawxml + '</line>';
	
	var oParser = new DOMParser();
	var oDOM = oParser.parseFromString(rawxml, 'text/xml');

	if ( oDOM.documentElement.nodeName == 'parsererror' ) {
		statval = 4;
	};
	
	if ( statval != '' ) {
		changestatus(linestat, statval);
	};
};

function togglezoom() { 
	if ( zoomset ) {
		zoomset = 0; 
		document.getElementById('zoomset').style['background-color'] = '#f2f2f2';
	} else {
		zoomset = 1; 
		document.getElementById('zoomset').style['background-color'] = '#aaffaa';
	};
};
var transtab = document.getElementById('transtab');

if ( document.getElementById('textfld') ) {
	if ( document.getElementById('facs').height ) document.getElementById('textfld').style['height'] = document.getElementById('facs').height + 'px';
	document.getElementById('textfld').style['background'] = "#f9f4ea";
	document.getElementById('textfld').focus();
};

var convset = 0;
function toggleconv() { 
	if ( convset ) {
		convset = 0; 
		document.getElementById('conv').style['background-color'] = '#f2f2f2';
	} else {
		convset = 1; 
		document.getElementById('conv').style['background-color'] = '#aaffaa';
		chareq(document.getElementById('textfld'));
	};
};
String.prototype.replaceAll = function(search, replacement) {
    var target = this;
    return target.split(search).join(replacement);
};
function chareq (fld) {
	// Set status of line/token/page to partial
	var type = fld.getAttribute('id').substring(0,3);
	var linestat; var lineid;
	if ( type == "tok" ) {
		lineid = fld.parentNode.getAttribute('tid');
	} else if ( type == "lin" ) {
		lineid = fld.getAttribute('id').substring(5,fld.getAttribute('id').length);		
	};
	if ( lineid ) { linestat = document.getElementById('statbox-'+lineid); };
	if ( linestat ) { changestatus(linestat, 1, 1); };
	
	// Change hard to type characters if so desired
	if ( !convset ) { return -1; };
	for(i in ces) {
		fld.value = fld.value.replaceAll(i, ces[i]);
	}
};

function fontchange (dif) {
	fontsize += dif;
	var tas = document.getElementsByTagName('textarea');
	for ( var i=0; i<tas.length; i++ ) {
		var ta = tas[i]; 
		ta.style['font-size'] = fontsize + 'px';
	};
};
function togglefull () {
	if ( full ) {
		unfullscreen();
		full = 0;
	} else {
		fullscreen();
		full = 1;
	};
};

function fullscreen() {
	var facsimg = document.getElementById('facs');
	// Set DIV to full browser screen
	if (document.documentElement.requestFullScreen) {  
	  document.documentElement.requestFullScreen();  
	} else if (document.documentElement.mozRequestFullScreen) {  
	  document.documentElement.mozRequestFullScreen();  
	} else if (document.documentElement.webkitRequestFullScreen) {  
	  document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);  
	};  
	document.getElementById('buttons').style['position'] = 'fixed';
	transtab.style['position'] = 'fixed';
	transtab.style['left'] = 0;
	transtab.style['top'] = 0;
	transtab.style['z-index'] = '100';
	transtab.style['width'] = '100%';
	transtab.style['height'] = '100%';
	
	if ( facsimg.height > screen.height ) {
		facsimg.height = screen.height;
		facsimg.width = facsimg.height*(facsimg.naturalWidth/facsimg.naturalHeight) + 'px';
	 	transtab.style['width'] = '100%';
	};
	document.getElementById('textfld').height = facsimg.height + 'px';
}

function redraw() {
	var facsimg = document.getElementById('facs');
	facsimg.width = ( transtab.style['width'] * 0.5 ) + 'px';
	facsimg.height = facsimg.width*(facsimg.naturalHeight/facsimg.naturalWidth);
	document.getElementById('textfld').height = document.getElementById('facs').height + 'px';
	console.log('redrawn'); console.log(facsimg);
};

function unfullscreen() {
	document.getElementById('buttons').style['position'] = 'relative';
	if (document.cancelFullScreen) {  
	  document.cancelFullScreen();  
	} else if (document.mozCancelFullScreen) {  
	  document.mozCancelFullScreen();  
	} else if (document.webkitCancelFullScreen) {  
	  document.webkitCancelFullScreen();  
	};
	transtab.style['position'] = 'relative';
	transtab.style['left'] = '';
	transtab.style['top'] = '';
	transtab.style['width'] = transtab.parentNode.width;
	transtab.style['height'] = '100%';
	redraw();
}

function zoomIn(event) {
	if ( !zoomset ) return -1;
	var element = document.getElementById("overlay");
	element.style.display = "block";
	var img = document.getElementById("facs");
	var zoom = document.getElementById("facs").height/document.getElementById("facs").naturalHeight;
	var midX = event.offsetX - (element.offsetWidth/2)*zoom;
	var midY = event.offsetY - (element.offsetHeight/2)*zoom; 
	element.style.backgroundPosition=(-midX/zoom)+"px "+(-midY/zoom)+"px";
}

function zoomOut() {
	if ( !zoomset ) return -1;
	var element = document.getElementById("overlay");
	element.style.display = "none";
}

function checkxml(fld) {
	var rawxml = '<line>' + fld.value + '</line>';
	var type = fld.getAttribute('id').substring(0,3);
	console.log(rawxml);
	var linestat; var lineid;
	if ( type == "tok" ) {
		lineid = fld.parentNode.getAttribute('tid');
	} else if ( type == "lin" ) {
		lineid = fld.getAttribute('id').substring(5,fld.getAttribute('id').length);		
	};
	if ( lineid ) { linestat = document.getElementById('statbox-'+lineid); };

	var oParser = new DOMParser();
	var oDOM = oParser.parseFromString(rawxml, 'text/xml');

	if ( oDOM.documentElement.nodeName == 'parsererror' ) {
		linestat.style.backgroundColor = '#ff0000'; 	
		changestatus(linestat, 4);
	} else {
		linestat.style.backgroundColor = '#ffffff'; 	
		changestatus(linestat, statval);
	};						
}