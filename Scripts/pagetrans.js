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
	if (document.webkitIsFullScreen || document.mozFullScreen || document.msFullscreenElement !== null) {
	} else {
		unfullscreen();
	};	
};

var zoomset = 0;
var fontsize = 16;
var full = false;

function changestatus(elm, status='', max='') {
	// Change the status of a line (verified, unverified, ...)
	var statcols = ['#dddddd', '#ffcc44', '#66ff66', '#ff0000'];
	var stattxt = ['unverified', 'partially verified', 'verified', 'locked'];
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
	var facsimg = document.getElementById('facs');
	facsimg.width = ( transtab.style['width'] * 0.5 ) + 'px';
	facsimg.height = facsimg.width*(facsimg.naturalHeight/facsimg.naturalWidth);
	document.getElementById('textfld').height = document.getElementById('facs').height + 'px';
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