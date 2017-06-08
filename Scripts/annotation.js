var seq = []; var selstring = '';

var segs = document.getElementsByClassName("segment");
for ( var a = 0; a<segs.length; a++ ) {
	var seg = segs[a];
	var array = seg.getAttribute('toklist').split(' ');
	for ( var b=0; b < array.length; b++ ) {
		var tokid = array[b]; console.log(tokid);
		var elm = document.getElementById(tokid);
		if ( elm ) {
			// elm.style['text-decoration'] = 'underline'; 
			// elm.style['text-decoration-color'] = seg.getAttribute('markupcolor'); 
			elm.style.borderBottom = '2px solid #0000FF';
			elm.style['borderBottom'] = '2px solid #0000FF';
			elm.style.borderColor = seg.getAttribute('markupcolor'); 
			elm.style['borderColor'] = seg.getAttribute('markupcolor'); 
		};
	};
};


var toks = mtxt.getElementsByTagName("tok");
for ( var a = 0; a<toks.length; a++ ) {
	var tok = toks[a];
	if ( tok.getAttribute('form') && !keepxml ) { 
		var org = tok.innerText;
		var form = tok.getAttribute('form');
		if ( form == '--' ) { form = ''; };
		if ( org.substring(org.length-1) == ' ' ) { form += ' '; };
		tok.innerHTML = form; 
	};
	tok.onmouseover = function(){ markback(this.getAttribute('id')); };
	tok.onmouseout = function(){ unmarkback(this.getAttribute('id')); };
};

function show ( id ) {
	var elm = document.getElementById(id);
	elm.style.display = "block";
}
function hide ( id ) {
	var elm = document.getElementById(id);
	elm.style.display = "none";
}

function markout ( seg, withinfo ) { 

	var array = seg.getAttribute('toklist').split(' ');
	var tokid = array[0];
	
	newrow = '<h3>' + seg.innerText + '</h3><table>';
	for ( var ak in interp ) {
		var an = interp[ak]; var av = seg.getAttribute(ak);
		newrow += '<tr><th>'+an+'</th><td>'+av+'</td></tr>';				
	};
	newrow += '</table>';
	var txt = newrow;
	if ( withinfo )  { showinfo ( tokid, txt ); };

	for ( var i=0; i<array.length; i++ ) {
		highlight(array[i], seg.getAttribute('markupcolor'));
	};
};

function unmarkout ( ) { 
	unhighlight();
	tokinfo.style.display = 'none';
}

function unhighlight () {
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( tok.style ) { 
			tok.style['background-color'] = ""; 
			tok.style.backgroundColor= ""; 
		};
	};
	var toks = document.getElementsByTagName("mtok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( tok.style ) { 
			tok.style['background-color'] = ""; 
			tok.style.backgroundColor= ""; 
		};
	};
};

function highlight ( id, color, dtokcolor ) {
	if ( !id )  { return -1; };
	if ( !color )  { color = '#ffffaa'; };
	if ( !dtokcolor )  { dtokcolor = color; };
	if ( document.getElementById(id) ) {
		var element = document.getElementById(id);
		
		// Move up to TOK when we are trying to highlight a DTOK
		if ( element.parentNode.tagName == "TOK" || element.parentNode.tagName == "MTOK" ) { element = element.parentNode; color = dtokcolor; };
		if ( element.parentNode.parentNode.tagName == "TOK" || element.parentNode.parentNode.tagName == "MTOK" ) { element = element.parentNode.parentNode; color = dtokcolor; };
		
		element.style['background-color'] = color;
		element.style.backgroundColor= color; 
	};
};

function markall ( type, value ) {
	var segs = document.getElementsByClassName("segment");
	for ( var a = 0; a<segs.length; a++ ) {
		var seg = segs[a];
		if ( seg.getAttribute(type) == value ) {
			markout(seg, false);
		};
	};
}

function unmarkall ( type, value ) {
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		tok.style['background-color'] = null;
		tok.style.backgroundColor= null; 
	};
}

function markback ( tokid ) {

	var segs = document.getElementsByClassName("segment");
	var txt = '';
	
	for ( var a = 0; a<segs.length; a++ ) {
		var seg = segs[a];
		var test = tokid+' '; var val = seg.getAttribute('toklist')+' ';
		if ( val.indexOf(test) != -1 ) {
			seg.style['background-color'] = seg.getAttribute('markupcolor');
			seg.style.backgroundColor= seg.getAttribute('markupcolor'); 
			
			newrow = '<h3>' + seg.innerText + '</h3><table>';
			for ( var ak in interp ) {
				var an = interp[ak]; var av = seg.getAttribute(ak);
				newrow += '<tr><th>'+an+'</th><td>'+av+'</td></tr>';				
			};
			newrow += '</table>';
			txt += newrow;
		};	
	};

	showinfo ( tokid, txt );
	
}

function showinfo ( tokid, txt ) {
	if ( txt == '' ) { return -1; };
	
	var element = document.getElementById(tokid);
	var tokinfo = document.getElementById('tokinfo');
	tokinfo.innerHTML = txt;
	if ( tokinfo.innerHTML != '' )  { tokinfo.style.display = 'block'; };

	var foffset = offset(element);
	if ( typeof(poselm) == "object" ) {
		var foffset = offset(poselm);
	};
	tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
	tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';

}

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

function unmarkback () {
	var segs = document.getElementsByClassName("segment");
	for ( var a = 0; a<segs.length; a++ ) {
		var seg = segs[a];
		seg.style['background-color'] = null;
		seg.style.backgroundColor= null; 
	};
	tokinfo.style.display = 'none';
}

function killselection () {
	hide('editform');
	window.getSelection().removeAllRanges();
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		if ( !tok ) { continue; };
		tok.style['background-color'] = null;
		tok.style.backgroundColor= null; 
	};
	seq = []; selstring = '';
};

function makespan( event ) {

	var toks = document.getElementsByTagName('tok');

	if (window.getSelection) {
		sel = window.getSelection();
	} else if (document.selection && document.selection.type != 'Control') {
		sel = document.selection.createRange();
	}
	
	var node1 = sel.anchorNode; 
	if ( !node1 ) { 
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

	if ( !event.altKey ) { // Shift key does not give a slection
		for ( var a = 0; a<seq.length; a++ ) {
			var tok = seq[a];
			tok.style['background-color'] = null;
			tok.style.backgroundColor= null; 
		};
		seq = []; 
	};

	var nodei = node1;

	seq.push(node1); 
	while ( nodei != noden && nodei ) {
		nodei = nodei.nextSibling;
		if ( nodei && ( nodei.nodeName == 'TOK' || nodei.nodeName == 'tok' )  ) { 
			seq.push(nodei);			
		};
	};
	window.getSelection().removeAllRanges();

	color = '#ffff88';  selstring = '';  idlist = ''; 
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		tok.style['background-color'] = color;
		tok.style.backgroundColor= color; 
		selstring += tok.innerText + ' ';
		idlist += '#' + tok.getAttribute('id') + ' ';
	}; 
	
	var selnode = document.getElementById('selection');
	selnode.innerHTML = selstring;
	var selnode = document.getElementById('selectionf');
	selnode.value = selstring;
	var selnode = document.getElementById('idlist');
	selnode.value = idlist;
	show('editform');

};