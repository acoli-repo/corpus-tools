document.onmouseover = mouseEvent; 
document.onclick = clickEvent; 

var seq = []; var selstring = '';
var prv = document.getElementById('prv');
var xp = document.getElementById('xpath');
var tokinfo = document.getElementById('tokinfo');
var attshow;
var hlnode;
var addmode;

function toggle(elm) {
	var sp = document.getElementById('span'+elm);
	var cls = document.getElementById('class'+elm);
	if ( sp.getAttribute('on') ) {
		sp.removeAttribute('on');
		cls.setAttribute('media', 'max-width: 1px');
	} else {
		sp.setAttribute('on', '1');
		cls.removeAttribute('media');
	}; 
};

function toggle2(elm, onoff) {
	var cls = document.getElementById('hl'+elm);
	if ( onoff == 0 ) {
		cls.setAttribute('media', 'max-width: 1px');
	} else {
		cls.removeAttribute('media');
	}; 
};

function togglestyles( onoff ) {
	if ( onoff || prv.getAttribute('id') == 'prv' ) {
		prv.setAttribute('id', 'mtxt');
	} else {
		prv.setAttribute('id', 'prv');
	};
};

function mouseEvent(evt) { 
	if ( addmode ) { return -1; }
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };

	showxpath(element);
};

function clickEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	
	console.log(pseudo(element,evt));
		
	if ( seq[0] ) { return; };
	
	var tag = element.nodeName;
	var elid = element.getAttribute('id');
	if ( tag != 'TOK' && tag != 'TEXT' && prv.contains(element) ) { 
		var attrs = element.attributes;
		nn = element.nodeName.toLowerCase().replace('tei_', '');

		var infotxt = '<table style=\"width: 100%;\"><tr><th colspan=2>Annotation Info</th></tr><tr><th>Element</th><td>' + nn + '</td></tr>';
		if ( attrs ) { 
			for(var i = 0; i <attrs.length; i++) {
				if ( attrs[i].name.substr(0,4) != 'pnv#' && attrs[i].name != 'style' ) infotxt += '<tr><th>' + attrs[i].name + '</th><td>' + attrs[i].value + '</td></tr>';
			};
		};
		infotxt += '</table>'; 
		if ( element.getAttribute('id') ) {
			document.getElementById('remid').value = element.getAttribute('id');
			document.getElementById('remfld').style.display = 'block';
		} else {
			document.getElementById('remnr').value = element.getAttribute('pnv#nr');
			document.getElementById('remfld').style.display = 'block';
		}
		document.getElementById('infotxt').innerHTML = infotxt;
		document.getElementById('addner').style.display = 'none';
		document.getElementById('elminfo').style.display = 'block';
	};
};
			
function showxpath(element) {
	nn = element.nodeName.toLowerCase().replace('tei_', '');
	var xpath = ''; var xpsep = '';
	var xinfo = '<table>';
	if ( hlnode ) { hlnode.style.backgroundColor = 'transparent'; };
	if ( !prv.contains(element) ) {
		xp.innerHTML = '';
		tokinfo.style.display = 'none';
		return;
	};
	var focusnode = element;
	if ( element) {
		hlnode = element;
		hlnode.style.backgroundColor = '#ffffaa';
	};
	var atts;
	while ( focusnode ) {
		if ( !focusnode ) { break; };
		nn = focusnode.nodeName.toLowerCase().replace('tei_', '');
		var ntxt = nn;
		var attrs = focusnode.attributes;
		atts = '';
		if (  attrs && nn != 'text' ) { 
			for(var i = 0; i <attrs.length; i++) {
				if ( attrs[i].name.substr(0,4) == 'pnv#' || attrs[i].name == 'style' ) { continue; } // do not show our own attributes
				var attval = attrs[i].value;
				if ( attval.length > 25 ) { attval = attval.substr(0,23) + '...'; };
				if ( attval ) { 
					atts += '<tr><th>' + attrs[i].name + '</th><td>' + attval + '</td></tr>'; 
				};
			}
			if ( attshow && atts ) { ntxt += '<span style=\"color: #aaaaaa; font-size: smaller;\">[' + atts + ']</span>'; };
		};
		if ( focusnode.getAttribute('id') == 'prv' || focusnode.getAttribute('id') == 'mtxt' ) { break; };
		xpath = ntxt + xpsep + xpath; xpsep = ' > ';
		xinfo = xinfo + '<tr><th>' + nn + '</th><td><table>' + atts + '</table></td></tr>';
		focusnode = focusnode.parentNode;
	}; 
	xinfo = xinfo + '</table>';

	xp.innerHTML = xpath;
	
	tokinfo.innerHTML = xinfo;
	tokinfo.style.display = 'block';
	var foffset = offset(element);
	tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
	tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';
};

function makespan(event) { 
	var toks = document.getElementsByTagName('tok');
	selstring = '';
	
	if (window.getSelection) {
		sel = window.getSelection();
	} else if (document.selection && document.selection.type != 'Control') {
		sel = document.selection.createRange();
	}

	var node1 = sel.anchorNode; 
	if ( !node1 || sel.anchorOffset == 0) { 
		for ( var a = 0; a<seq.length; a++ ) {
			var tok = seq[a];
			tok.style['background-color'] = null;
			tok.style.backgroundColor= null; 
		};
		seq = []; selstring = '';
		return -1;
	};
	var noden = sel.focusNode;
	var order = 0;
	if ( node1.compareDocumentPosition(noden) == 2 ) {
		// switch if selection is inverse
		var tmp = node1;
		node1 = noden;
		noden = tmp;
	};

	while ( node1 && node1.nodeName != 'TOK' && node1.nodeName != 'tok'  ) { node1 = node1.parentNode; };
	while ( noden && noden.nodeName != 'TOK' && noden.nodeName != 'tok'  ) { noden = noden.parentNode; };

	// Reset the selection
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		if ( tok ) {
			tok.style['background-color'] = null;
			tok.style.backgroundColor= null; 
		};
	};
	seq = []; 

	var nodei = node1;

	seq.push(node1); 
	while ( nodei != noden && nodei ) {
		nodei = nodei.nextSibling;
		if ( nodei && ( nodei.nodeName == 'TOK' || nodei.nodeName == 'tok' )  ) { 
			seq.push(nodei);			
		};
	};
	window.getSelection().removeAllRanges();

	color = '#88ffff';  selstring = '';  idlist = ''; 
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		if ( tok == null ) continue;
		tok.style['background-color'] = color;
		tok.style.backgroundColor= color; 
		selstring += tok.innerHTML + ' ';
		idlist += tok.getAttribute('id') + ';';
	};

	addmode = 1;
	document.getElementById('toklist').value = idlist;
	document.getElementById('addner').style.display = 'block';
	document.getElementById('elminfo').style.display = 'none';
	tokinfo.style.display = 'none';
	document.getElementById('nerspan').innerHTML = selstring;

};

function canceladdann() {
	document.getElementById('addner').style.display='none';
	addmode = 0;
	// hide the selection
	var idlist = document.getElementById('toklist').value.split(';');
	for ( var i=0; i<idlist.length; i++ ) {
		id = idlist[i];
		if ( id ) {
			var tok = document.getElementById(id);
			tok.style.backgroundColor= null; 
		};
	}; seq = [];
};

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

function pseudo(elem, evt) {
	 // Not working
	if ( evt.clientX < offset(prv).left + element.offsetLeft ) { return true; };
	if ( evt.clientX > offset(prv).left + element.offsetLeft + element.offsetWidth ) { return true; };
	return false;
};