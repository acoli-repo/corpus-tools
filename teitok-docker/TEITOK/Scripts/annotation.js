var seq = []; var selstring = '';

var segs = document.getElementsByClassName("segment");
for ( var a = 0; a<segs.length; a++ ) {
	var seg = segs[a];
	var array = seg.getAttribute('toklist').split(' ');
	for ( var b=0; b < array.length; b++ ) {
		var tokid = array[b]; // console.log(tokid);
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
	 
	newrow = '<table width=100%><tr><th colspan=2><b>' + seg.getElementsByTagName("ann").item(0).innerHTML + '</b></th></tr>';
	for ( var ak in interp ) {
		if ( ak == "idx" ) continue;
		var an = interp[ak]; 
		var av = makeval(seg, ak);
		if ( av != "" ) { newrow += '<tr><th>'+an+'</th><td>'+av+'</td></tr>'; };				
	};
	newrow += '</table>';
	var txt = newrow;
	if ( withinfo )  { 
		// Scroll to view (first - so that the position gets correct)
		document.getElementById(tokid).scrollIntoView();
		
		showinfo ( tokid, txt ); 
	};

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
			// scrollParentToChild(document.getElementById('annotations'), seg); // Too jumpy
			seg.style['background-color'] = seg.getAttribute('markupcolor');
			seg.style.backgroundColor= seg.getAttribute('markupcolor'); 
			
			var segid = seg.getAttribute('annid');
			if ( typeof(reldefs) != 'undefined' ) { console.log(reldefs[segid]); };
			
			newrow = '<table width=100%><tr><th colspan=2><b>' + seg.getElementsByTagName("ann").item(0).innerHTML + '</b></th></tr>';
			for ( var ak in interp ) {
				if ( ak == "idx" ) continue;
				var an = interp[ak]; 
				var av = makeval(seg, ak);
				if ( av != "" ) { newrow += '<tr><th>'+an+'</th><td>'+av+'</td></tr>';	};			
			};
			newrow += '</table>';
			txt += newrow;
		};	
	};

	showinfo ( tokid, txt );
	
}

function makeval ( seg, ak ) {
	var av = seg.getAttribute(ak);
	if ( codetrans[av] ) av = codetrans[av] + ' (' + av + ')';
	return av;
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

	bbr = elem.getBoundingClientRect();
	if ( bbr.x ) {

		x = bbr.x + window.pageXOffset;
		y = bbr.y + window.pageYOffset;

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


	var tokdata = '';
	
	color = '#ffff88';  selstring = '';  idlist = ''; 
	tokdata += '<hr><h3>Token data</h3><table><tr>';
	if ( typeof(formdef) != "undefined" ) {
		for ( fld in formdef ) {
			tokdata += '<th>' + formdef[fld]['display'];
		};
	};
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		if ( tok == null ) continue;
		tok.style['background-color'] = color;
		tok.style.backgroundColor= color; 
		selstring += tok.innerText + ' ';
		idlist += '#' + tok.getAttribute('id') + ' ';
		tokdata += '<tr>'; 
		var thisform;
		if ( typeof(formdef) != "undefined" ) {
			for ( fld in formdef ) {
				if ( fld == 'pform' ) thisform = tok.innerHTML;
				else if ( tok.getAttribute(fld) != null ) thisform = tok.getAttribute(fld);
				else thisform = '<span style="color: #cccccc; font-style: italic;">' + forminherit(tok, fld); + '</span>'; 
				tokdata += '<td>' + thisform;
			};
		};
	}; 	
	tokdata += '</table>';

	var mtch = document.evaluate("//tr[@toklist = '"+idlist.trim().replace(/#/g, '')+"']", document, null, XPathResult.ANY_TYPE, null); 
	var mitm = mtch.iterateNext(); // console.log(mitm);
	if ( mitm ) { 
		tokdata += '<h3>Existing annotations</h3><table><tr>';
			for ( var ak in interp ) {
				if ( ak == "idx" ) continue;
				var an = interp[ak]; 
				var av = makeval(seg, ak);
				tokdata += '<th>'+ an;		
			};
		while ( mitm ) {
		  if ( typeof(mitm) != 'object' ) { continue; };
		  tokdata += '<tr>';
			for ( var ak in interp ) {
				if ( ak == "idx" ) continue;
				var an = interp[ak]; 
				var av = mitm.getAttribute(ak);
				tokdata += '<td>'+ av;		
			};
		  mitm = mtch.iterateNext();
		};
		tokdata += '</table>';
	};

	
	var selnode = document.getElementById('selection');
	selnode.innerHTML = selstring;
	var selnode = document.getElementById('selectionf');
	selnode.value = selstring;
	var selnode = document.getElementById('idlist');
	selnode.value = idlist;
	var tokinfos = document.getElementById('tokinfos');
	tokinfos.innerHTML = tokdata;
	show('editform');

};

function scrollParentToChild(parent, child) {

  // Where is the parent on page
  var parentRect = parent.getBoundingClientRect();
  // What can you see?
  var parentViewableArea = {
    height: parent.clientHeight,
    width: parent.clientWidth
  };

  // Where is the child
  var childRect = child.getBoundingClientRect();
  // Is the child viewable?
  var isViewable = (childRect.top >= parentRect.top) && (childRect.top <= parentRect.top + parentViewableArea.height);

  // if you can't see the child try to scroll parent
  if (!isViewable) {
    // scroll by offset relative to parent
    parent.scrollTop = (childRect.top + parent.scrollTop) - parentRect.top
  }


}