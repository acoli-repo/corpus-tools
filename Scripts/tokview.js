document.onclick = clickEvent; 
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 
if (!attributenames) {
	var attributenames = Array();
};
if ( !attributelist ) {
	var attributelist = Array();
};

if ( !document.getElementById('tokinfo') ) {
	var tokinfo = document.createElement("div"); 
	tokinfo.setAttribute('id', 'tokinfo');
	document.body.appendChild(tokinfo);
};
if ( typeof(tibel) == 'undefined' ) { var tibel = 4; };

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode && element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };

    if (element.tagName == "TOK" ) {
		if ( typeof(tid) == "undefined" ) { // For KWIC rows
			var mtch = document.evaluate("ancestor::tr[@tid]", element, null, XPathResult.ANY_TYPE, null); 
			var mitm = mtch.iterateNext();
			if ( mitm ) { jumpid = mitm.getAttribute('tid'); };
		} else {
			jumpid = tid;
		};
    	if ( username && typeof(jumpid) != 'undefined' && jumpid ) {
    		window.open('index.php?action=tokedit&cid='+jumpid+'&tid='+element.getAttribute('id'), 'edit');
    	} else if ( typeof(wordinfo) != null && typeof(wordinfo) != 'undefined' && jumpid ) {
    		window.open('index.php?action=wordinfo&cid='+jumpid+'&tid='+element.getAttribute('id'), '_self');
    	};
    } else if ( element.tagName == "text" ) {
		if ( typeof treeclick === "function" ) {
			treeclick(element);
		};
	};
};

function mouseOut(evt) {
	hidetokinfo();
	if ( typeof(dllines) == "object" ) {
		remlines();
	};
};

function hideinfo(elm) {
	hidetokinfo(elm);
};
function hidetokinfo() {
	
	if ( document.getElementById('tokinfo') ) {
		tokid = document.getElementById('tokinfo').getAttribute('tokid');
		document.getElementById('tokinfo').style.display = 'none';
	};
	if ( typeof(hlbar) != "undefined" && typeof(facsdiv) != "undefined" ) {
		hlbar.style.display = 'none';
		var tmp = facsdiv.getElementsByClassName('hlbar'+hln);
	};
	if ( typeof(window.posttok) === 'function' ) { posttok('out', null, tokid); }; // if needed, run post scripts, pe to highlight the token elsewhere
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" && element.tagName != "GTOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" && element.tagName != "GTOK" ) { element = element.parentNode.parentNode; };

	showtokinfo(evt, element);
	highlightbb(element);

	if ( typeof(dllines) == "object" ) {
		drawtok(element);
	};
		
};

function showtokinfo(evt, element, poselm) {
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
	var shownrows = 0;
	var showelement; var html;
	if ( element.tagName == "GTOK" ) { showelement = element; element = element.parentNode; } else { showelement = element; };
    if ( element.tagName == "TOK" || element.tagName == "DTOK" || element.tagName == "MTOK" ) {
    	var done = [];
    	var atts = element.attributes;
    	var tokid = element.getAttribute('id');
    	done[tokid] = 1
    	tokinfo.setAttribute('tokid', tokid);
    	if ( element.tagName == "DTOK" ) { 
    		textvalue = element.getAttribute('form');
    	} else if ( typeof(orgtoks) != "undefined" ) { 
    		textvalue = orgtoks[tokid];
    		if ( textvalue == "" ) { 
    			textvalue = orgtoks[tokid].getAttribute('form'); 
    		};
    	} else { 
    		textvalue = element.textContent; 
    		if ( textvalue.trim() == "" ) { 
    			textvalue = element.getAttribute('form'); 
    		};
    	};
    	if ( textvalue == "null" || typeof(textvalue) == "undefined" ) { 
    		textvalue = element.innerText; 
    	};
     	tablerows = '<tr><th colspan=2><b>' + textvalue + '</b></th></tr>';
     	shownrows = 1;
		tablerows += infotable(element);
    	html = '<table width=\'100%\'>' + tablerows + '</table>';
    	
    	// now look for dtoks
    	var children = element.childNodes;
    	for ( i=0; i<children.length; i++ ) {
    		var child = children[i];
    		if ( child.tagName == "DTOK" && !done[child.getAttribute('id')] ) {
    			shownrows = 1;
    			done[child.getAttribute('id')] = 1;
				if ( child.getAttribute('form') != '' && child.getAttribute('form') != null ) { tablerows = '<tr><th colspan=2><b>' + child.getAttribute('form') + '</b></th></tr>'; }
				else { tablerows = ''; };
				tablerows += infotable(child);
				if ( tablerows ) {
			     	shownrows = 1;
					html += '<hr><table width=\'100%\'>' + tablerows + '</table>';
				};
    		}; 
		};
		
    	// now look for parent nodes of type MTOK, NAME
    	var parent = element; lastparent = null;
    	if ( !satts['mtok'] ) satts['mtok'] = 1; // Always do MTOK
    	while ( parent && parent.getAttribute('id') ) {
    		console.log(parent);
			if ( satts[parent.tagName.toLowerCase()] && !done[parent.getAttribute('id')] ) { 
				shownrows = 1;
				done[parent.getAttribute('id')] = 1;
				var form = parent.getAttribute('form');
				if ( !form ) { form = parent.innerText; };
				stablerows = sinfotable(parent);
    		console.log(stablerows);
				if ( stablerows ) {
					html += '<hr><table width=\'100%\'>' + stablerows + '</table>';
			     	shownrows = 1;
				};
			};
			lastparent = parent;
			parent = parent.parentNode;
			console.log('next');
			console.log(parent);
		};

		if ( shownrows )  { 
			if ( !poselm ) { poselm = element; };
			showinfo(poselm, html); 
		};
	};
	
	if ( typeof(window.posttok) === 'function' ) { posttok('in', evt, tokid); }; // if needed, run post scripts, pe to highlight the token elsewhere

};

function showinfo(element, html) {
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
	
	tokinfo.style.display = 'block';
	tokinfo.innerHTML = html;
	
	if ( typeof(element) == "object" ) {
		var foffset = offset(element);
		tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
		tokinfo.style.top = ( foffset.top + element.offsetHeight + tibel ) + 'px';
	} else {
		console.log(element);
	};
 
} 

function sinfotable (elmnode) {
	// Calculate all the row we need to show for this region node
	if ( elmnode.tagName == 'MTOK' ) {
		var form = elmnode.getAttribute('form');
		if ( !form ) { form = elmnode.innerText; };
		let inforows = infotable(elmnode); // mtok is a "token"
		inforows = '<tr><th colspan=2><b>' + form + '</b></th></tr>' + inforows;
		return inforows;
	};
	var inforows = '';
	nodeatts = satts[elmnode.tagName.toLowerCase()];
	if ( !nodeatts ) return '';
	if ( nodeatts['info'] ) {
		var form = elmnode.getAttribute('form');
		if ( !form ) { form = elmnode.innerText; };
		inforows = '<tr><th colspan=2><b>' + form + '</b></th></tr>';
		inforows += '<tr><th style=\'font-size: small;\'>' + nodeatts['info'] + '</th><td>' + nodeatts['display'] + '</td></tr>';
		for ( let atti in nodeatts ) {
			if ( nodeatts[atti]['admin'] && !username ) { continue; };
			if ( nodeatts[atti]['noshow'] ) { continue; };
			let attkey = nodeatts[atti]['key'];
			if ( !attkey ) { continue; };
			rowval = elmnode.getAttribute(attkey);
			if ( rowval ) {
				let attname = nodeatts[atti]['display'];
				inforows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + rowval + '</td></tr>';
			};
		};
	};
	return inforows;
};

function infotable (elmnode) {
	// Calculate all the row we need to show for this "token"
	var inforows = '';
	if ( !attributelist.length ) {
		if ( formdef ) {
			var fsa = Object.keys(formdef);
			attributelist = attributelist.concat(fsa);
		};
		if ( typeof(tagdef) == "object" ) {
			var fsa = Object.keys(tagdef);
			attributelist = attributelist.concat(fsa);
		};
	};
	for ( ia=0; ia<attributelist.length; ia++ ) {
		var att = attributelist[ia];
		var attname = attributenames[att];
		if ( !attname ) { attname = att; };
		var attdef = false;
		if ( formdef[att] ) attdef = formdef[att];
		else if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] ) attdef = tagdef[att];
		var rowval = elmnode.getAttribute(att);
		if ( attdef['compute'] || attdef['transcribe'] ) rowval = forminherit(elmnode, att);
		if ( rowval && attdef && !attdef['noshow'] && ( !attdef['admin'] || attdef['admin'] == "0" || username ) ) {
			shownrows = 1;
			var atttype = '';
			if ( typeof(formdef) != "undefined" && formdef && formdef[att] && formdef[att]['type'] ) atttype = formdef[att]['type'];
			if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['type'] ) atttype = tagdef[att]['type'];
			// Calculate if we need to display this better
			if ( atttype == 'pos' && typeof(treatpos) == 'function' ) { rowval = treatpos(elmnode, att, 'full'); }
				else if ( atttype == 'udfeats' && typeof(treatfeats) == 'function' ) { rowval = treatfeats(elmnode, att, 'full'); }
				else if ( atttype == 'ref' && typeof(treatref) == 'function' ) { rowval = treatref(elmnode, att, 'full'); }
				else if ( typeof(formdef) != "undefined" && formdef && formdef[att] && formdef[att]['options'] && formdef[att]['options'][rowval] ) { rowval = formdef[att]['options'][rowval]['display'] + ' (' + rowval + ')'; }
				else if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['options'] && tagdef[att]['options'][rowval] ) { rowval = tagdef[att]['options'][rowval]['display'] + ' (' + rowval + ')'; 
			}; 
			if ( rowval ) {
				inforows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + rowval + '</td></tr>';
			};
		};
	}; 
	return inforows;
};

function highlightbb (elm, hln=0) {

	// Unhighlight if we still have a hlbar 
	var its = document.getElementsByClassName('hlbar');
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];	
		it.style.display = 'none';
	};

	// Goto the local module
	if ( typeof(localhl) != "undefined" ) { highlightlocal(elm, hln); return -1; };

	// Find the bbox we need
	if ( elm.getAttribute('bbox') == null ) {
		var mtch = document.evaluate("./gtok[@bbox]", elm, null, XPathResult.ANY_TYPE, null);
		if ( elm.tagName == "TOK" && mtch.invalidIteratorState != false ) { // In case we have GTOK elements
			var gtoks = [];
			var tmpe = mtch.iterateNext(); 
			while ( tmpe != null )  { gtoks.push(tmpe); tmpe = mtch.iterateNext(); };		
			for (var i = 0; i < gtoks.length; i++) {
				// TODO: This does not work, since there is only one hlbar
				highlightbb(gtoks[i], i);
			};
		} else {
			var mtch = document.evaluate("ancestor::*[@bbox]", elm, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null); 
			if ( mtch.snapshotLength == 0 ) {
				mtch = document.evaluate("ancestor-or-self::tok/preceding::lb", elm, null, XPathResult.ANY_TYPE, null);
			};
			var tmpe;
			if ( mtch != null && typeof mtch[Symbol.iterator] === 'function' ) { tmpe = mtch.iterateNext(); };
			while ( tmpe != null )  { elm = tmpe; tmpe = mtch.iterateNext(); };		
		};		
	}; 
	if ( elm.getAttribute('bbox') == null ) { return -1; };

	// Find the image div we need
	var mtch = document.evaluate("preceding::div[div[@class='hlbar']]", elm, null, XPathResult.ANY_TYPE, null); 
	var facsdiv;
	var tmpe = mtch.iterateNext(); 
	while ( tmpe != null )  { facsdiv = tmpe; tmpe = mtch.iterateNext(); };		
	if ( typeof(facsdiv) == "undefined" ) { return -1; };
	
	// Determine the hlbar and scale of the image div
	hlbar = facsdiv.getElementsByClassName('hlbar').item(hln);
	if ( !hlbar ) {
		hlbar = document.createElement("div");
		hlbar.setAttribute('class', 'hlbar'); // The highlight bar
		facsdiv.appendChild(hlbar);
	}; 
	var facsimg = facsdiv.getElementsByTagName('img').item(0);
	var orgImg = new Image(); orgImg.src = facsimg.src; 
	
	orgImg.setAttribute("bbox", elm.getAttribute('bbox'));
	orgImg.setAttribute("fsize", facsimg.width);

	// show the hlbar once the image is loaded
	orgImg.onload = function(){
		var imgscale = this.getAttribute("fsize")/this.width;
		var bb = this.getAttribute('bbox').split(' '); 
		hlbar.style.display = 'block';
		hlbar.style['background-color'] = '#ffff00';
		hlbar.style['z-index'] = '100';
		hlbar.style['position'] = 'absolute';
		hlbar.style['opacity'] = '0.5';
	
		facsleft = facsimg.offsetLeft; obj = facsimg;
		// The hlbar is embedded - should not always use offset 
		while ( obj.offsetParent ) { obj = obj.offsetParent; facsleft += obj.offsetLeft; };
		facstop = facsimg.offsetTop; obj = facsimg; while ( obj.offsetParent ) { obj = obj.offsetParent; facstop += obj.offsetTop; };
	
		hlleft = ( bb[0] * imgscale ) + facsleft;
		hltop = ( bb[1] * imgscale ) + facstop;
		hlwidth = (bb[2] - bb[0])  * imgscale;
		hlheight = (bb[3] - bb[1])  * imgscale;

		hlbar.style.left = hlleft + 'px';
		hlbar.style.top = hltop + 'px';
		hlbar.style.width = hlwidth + 'px';
		hlbar.style.height = hlheight + 'px';
	}

	
};

// Convert a UD features label to text
function treatfeats ( tok, label, type ) {
	var tag = tok.getAttribute(label);
	var tagexpl = '';
	if ( !tag || tag == '_') { return ''; };
	var tagset = document.getElementById('tagset');
	if ( tagset ) {
		var sep = '';
		tag.split('|').forEach(function(element) {
		  var arr = element.split('=');
		  var feat = arr[0]; var val = arr[1];
  		  var xpath = "//values/item[@key='"+feat+"']/item[@key='"+val+"']";
		  var tmp = document.evaluate(xpath, tagset, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null); 
		  var valdef = '';
		  if ( tmp ) {
			valdef = tmp.snapshotItem(0);
		  };
		  if ( valdef && valdef.getAttribute('display') ) { val = valdef.getAttribute('display'); };
		  tagexpl += sep + feat + '=' + val;		  
		  sep = ", ";
		});
	} else {
		tagexpl = tag.replaceAll('|', ', ');
	};
	return tagexpl;
};

// Convert a (head) reference to text
function treatref ( tok, label, type ) {
	var tag = tok.getAttribute(label);
	var tagexpl = tag;
	if ( !tag ) { return ''; };
	var mtxt = document.getElementById('mtxt');
	var xpath = "//tok[@id='"+tag+"']";
    var tmp = document.evaluate(xpath, mtxt, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null); 
	if ( tmp ) {
		var refnode = tmp.snapshotItem(0);
		if ( refnode ) {
			// the refnode should always exist, but draw even if wrong
			tagexpl = refnode.innerHTML + ' ('+tag+')';
		};
	};
	return tagexpl;
};


// Show dependency lines
var dldepth = { 'top': 0, 'bottom': 0};
var dlcols = { 'top': 'rgba(226,0,122,0.6)', 'bottom': 'rgba(0,122,226,0.6)'};

function drawline(id1, id2, pos = 'top') {
	el1 = document.getElementById(id1);
	el2 = document.getElementById(id2);
	var dpt = 5;
	if ( typeof(dloff) != 'undefined' ) {
		dpt = 5 * (dldepth[pos] + 1);
	};
	var line = new LeaderLine(
	  el1,
	  el2,
			{
				  size: 1.5,
				  endPlug: 'Arrow3',
				  endPlugSize: 1.8,
				  color: dlcols[pos],
				  startSocket: pos,
				  startSocketGravity: dpt,
				  endSocket: pos,
				  endSocketGravity: dpt,
				  path: 'grid',
				  dash: false
			} );
	return line;
};
function drawhead (tok, pos = 'top') {
	if ( typeof(tok) != 'object' ) return false;
	id1 = tok.getAttribute('id');
	id2 = tok.getAttribute('head');
	if ( id1 && id2 ) { 
		line = drawline(id1, id2, pos); 
		if ( line ) { 
			dllines.push(line); 
			dldepth[pos]++;
		};
	};
};
function drawtok ( element ) {
	sent = element;
	while( sent.parentNode && sent.tagName != 'S'  ) {
		sent = sent.parentNode;
	};
	if ( sent.tagName == 'S' ) {
		drawhead(element);
		deps = sent.getElementsByTagName('TOK');
		for ( dep in deps ) {
			if ( deps[dep].tagName == 'TOK' ) {
				tok = deps[dep];
				if ( tok.getAttribute('head') == element.getAttribute('id') ) {
					line = drawhead(tok, 'bottom'); 
				};
			};
		};
	};
};
function remlines() {
	while ( line = dllines.pop() ) {
		line.remove();
	};
	dldepth['top'] = 0;
	dldepth['bottom'] = 0;
};

