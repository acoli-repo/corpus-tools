// Bounding Box Javascript
// Deals with @bbox attributes related to preceding <pb/> elements
// (c) Maarten Janssen, 2016

document.onclick = clickEvent; 
var imgdiv = document.getElementById('facsimg');
var hlbar = document.getElementById('hlbar');
var imgoffset = offset(imgdiv);
var imgwidth;
var imgscale;
var bb = []; // the bounding box values
var cb = []; // temporary bbox values 
var bbfld;
var state = 0;

function offset(elem) {
	if(!elem) elem = this;

	var x = elem.offsetLeft;
	var y = elem.offsetTop;

	while (elem = elem.offsetParent) {
		x += elem.offsetLeft;
		y += elem.offsetTop;
	}

	return { left: x, top: y };
};

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) {
		state = 0; // This should not happen
	} else {
		if ( element.getAttribute('id') == 'facsimg' ) { 
			xpos = evt.clientX;
			ypos = evt.clientY;
			var imgclickx = Math.floor((xpos-imgoffset.left)/imgscale);
			var imgclicky = Math.floor((ypos-imgoffset.top)/imgscale);
			if ( state == 0 ) {
				bb[0] = imgclickx;
				bb[1] = imgclicky;
				cb[0] = xpos;
				cb[1] = ypos;
				state = 1;
				hlbar.style.display = 'block';
				hlbar.style.left = cb[0] + 'px';
				hlbar.style.top = cb[1] + 'px';
				hlbar.style.width = '10px';
				hlbar.style.height = '10px';
			} else if ( state == 1 ) {
				bb[2] = imgclickx;
				bb[3] = imgclicky;
				cb[2] = xpos;
				cb[3] = ypos;
				hlbar.style.width = (cb[2]-cb[0]) + 'px';
				hlbar.style.height = (cb[3]-cb[1]) + 'px';
				if ( bbfld ) { bbfld.value = bb.join(' '); };
				state = 0;						
			};
		};		
	};
};

// Functions to place the HighLighting bar

function selecthl(elm) {
	bbfld = elm;
	state = 0; 
	if ( elm.value == '' ) {
		var elmid = elm.getAttribute('id');
		var elmnr = elmid.substring(3);
		var prbbv = document.getElementById('lb-'+(elmnr-1));
		var prbbv2 = document.getElementById('lb-'+(elmnr-2));
		if ( prbbv2 && prbbv2.value != '' && prbbv && prbbv.value != '' ) { 
			var prbb = prbbv.value.split(' ');
			var prbb2 = prbbv2.value.split(' ');
			bb[0] = prbb[0]; bb[1] = prbb[1]; // keep x coords
			bb[1] = 0 - prbb2[1] + 2*prbb[1]; // move previous stretch down
			bb[3] = 0 - prbb2[3] + 2*prbb[3]; 
			elm.value = bb.join(' ');
			placehl();
		} else if ( prbbv && prbbv.value != '' ) { 
			var prbb = prbbv.value.split(' ');
			bb = prbb; var tmp = prbb[3]; 
			bb[3] = 0 - prbb[1] + 2*prbb[3] - 2; 
			bb[1] = 1*tmp + 4;
			elm.value = bb.join(' ');
			placehl();
		} else {
			hlbar.style.display = 'none';
		};
	} else {
		bb = bbfld.value.split(' ');
		placehl();
	};
};

function updatehl(elm) {
	if ( elm.value != '' ) {
		bb = elm.value.split(' ');
		placehl();
	};
};

function placehl() {
	hlbar.style.display = 'block';
	cb[0] = (bb[0]*imgscale) + imgoffset.left;
	cb[1] = (bb[1]*imgscale) + imgoffset.top;
	hlbar.style.left = cb[0] + 'px';
	hlbar.style.top = cb[1] + 'px';
	cb[2] = (bb[2]*imgscale) + imgoffset.left;
	cb[3] = (bb[3]*imgscale) + imgoffset.top;
	hlbar.style.width = (cb[2]-cb[0]) + 'px';
	hlbar.style.height = (cb[3]-cb[1]) + 'px';
};