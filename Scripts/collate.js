// Witness collation script to build an apparatus view
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 
var hlid;

function mouseOut(evt) {
	hidetokinfo();

	var toklist = document.getElementsByTagName('tok');
	for ( var a=0; a<toklist.length; a++ ) {
		var tok = toklist[a]; 
		var color = '#ffffaa'
		tok.style['background-color'] = '';
		tok.style.backgroundColor= ''; 
	};
};

function hidetokinfo() {
	if ( document.getElementById('tokinfo') ) {
		document.getElementById('tokinfo').style.display = 'none';
	};
	if ( typeof(hlbar) != "undefined" && typeof(facsdiv) != "undefined" ) {
		hlbar.style.display = 'none';
		var tmp = facsdiv.getElementsByClassName('hlbar'+hln);
	};
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" && element.tagName != "GTOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" && element.tagName != "GTOK" ) { element = element.parentNode.parentNode; };

	showtokinfo(evt, element);
	
	var hlid = element.getAttribute('appid');
	if ( hlid != null ) {
		var toklist = document.getElementsByTagName('tok');
		for ( var a=0; a<toklist.length; a++ ) {
			var tok = toklist[a]; 
			var color = '#ffffaa'
			if ( tok.getAttribute('appid') == hlid ) {
				tok.style['background-color'] = color;
				tok.style.backgroundColor= color; 
			};
		};
	};
	
};

function showtokinfo(evt, element, poselm) {

	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
	if ( !element.getAttribute('list') ) { return -1; };

	var fld = "<table width='100%'>";
	var list = element.getAttribute('list').split(',');
	for ( var a=0; a<list.length-1; a++ ) {
		var tmp = list[a].split(':'); 
		fld += '<tr><th>'+tmp[0]+'</th><td>'+tmp[1]+'</td></tr>';
	};
	fld += '</table>';
	tokinfo.innerHTML = fld;

	var showelement = element;

	var foffset = offset(showelement);
	if ( typeof(poselm) == "object" ) {
		var foffset = offset(poselm);
	};

	tokinfo.style.display = 'block';
	tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
	tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';

	function offset(elem) {
		if(!elem) elem = this;

		var x = elem.offsetLeft;
		var y = elem.offsetTop;

		if ( typeof(x) == "undefined" ) {

			bbr = elem.getBoundingClientRect();
			x = bbr.left + window.pageXOffset;
			y = bbr.top + window.pageYOffset;

		} else {

			while (elem = elem.offsetParent) {
				x += elem.offsetLeft;
				y += elem.offsetTop;
			}
		
		};
		
		return { left: x, top: y };
	}    

};

// List all the witnesses by appid	
var wits = document.getElementsByClassName("wits");
var apps = [];
for ( var w = 0; w<wits.length; w++ ) {
	var witness=wits[w];
	var wid = witness.getAttribute('wit');
	var its = witness.getElementsByTagName("tok");
	for ( var a = 0; a<its.length; a++ ) {
		var tok = its[a]; 
		var appid = tok.getAttribute('appid');
		if ( appid == '' || appid == null ) continue;
		var form = tok.getAttribute('form');
		if ( !form ) form = tok.innerHTML;
		if ( !apps[appid] ) apps[appid] = [];
		if ( !apps[appid][form] ) apps[appid][form] = "";
		apps[appid][form] += wid + '; ';
	};
};
	
// Show the apparatus on the bf element
var its = document.getElementById('bf').getElementsByTagName("tok");
for ( var a = 0; a<its.length; a++ ) {
	var tok=its[a]; 
	var appid = tok.getAttribute('appid');
	var keys = Object.keys(apps[appid]);
	if ( keys.length > 1 ) {
		var appstring = '';
		for ( var b=0; b<keys.length; b++ ) {
			var key = keys[b];
			var val = apps[appid][key];
			appstring += key+':'+val+',';
		};
		tok.setAttribute('apps', 'true');
		tok.setAttribute('list', appstring);
	};
};