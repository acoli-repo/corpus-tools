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
			jumpid = mitm.getAttribute('tid');
		} else {
			jumpid = tid;
		};
    	if ( username && jumpid ) {
    		window.open('index.php?action=tokedit&cid='+jumpid+'&tid='+element.getAttribute('id'), 'edit');
    	} else if ( typeof(wordinfo) != null && typeof(wordinfo) != 'undefined' && jumpid ) {
    		window.open('index.php?action=wordinfo&cid='+jumpid+'&tid='+element.getAttribute('id'), '_self');
    	};
    };
};

function mouseOut(evt) {
	hidetokinfo();
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
	highlightbb(element);
	
};
	
function showtokinfo(evt, element, poselm) {
	var shownrows = 0;
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
	var showelement;
	if ( element.tagName == "GTOK" ) { showelement = element; element = element.parentNode; } else { showelement = element; };
    if ( element.tagName == "TOK" || element.tagName == "DTOK" || element.tagName == "MTOK" ) {
    	var atts = element.attributes;
    	var tokid = element.getAttribute('id');
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
    	for ( ia=0; ia<attributelist.length; ia++ ) {
    		var att = attributelist[ia];
			var attname = attributenames[att];
			if ( !attname ) { attname = att; };
    		if ( element.getAttribute(att) && ( !formdef[att] || ( !formdef[att]['noshow'] && ( !formdef[att]['admin'] || username ) ) ) ) {
    			shownrows = 1;
    			var rowval = element.getAttribute(att);
    			if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['type'] == 'pos' ) { rowval = treatpos(element, att, 'full'); }
	    			else if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['type'] == 'udfeats' ) { rowval = treatfeats(element, att, 'full'); }
					else if ( typeof(formdef) != "undefined" && formdef && formdef[att] && formdef[att]['options'] ) { rowval = formdef[att]['options'][rowval]['display'] + ' (' + rowval + ')'; }
					else if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['options'] && tagdef[att]['options'][rowval] ) { rowval = tagdef[att]['options'][rowval]['display'] + ' (' + rowval + ')';; }; 
	    		tablerows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + 
	    			rowval + '</td></tr>';
	    	};
    	}; 
    	tokinfo.innerHTML = '<table width=\'100%\'>' + tablerows + '</table>';
    	
    	// now look for dtoks
    	var children = element.childNodes;
    	var done = [];
    	for ( i=0; i<children.length; i++ ) {
    		var child = children[i];
    		if ( child.tagName == "DTOK" && !done[child.getAttribute('id')] ) {
    			shownrows = 1;
    			done[child.getAttribute('id')] = 1;
				if ( child.getAttribute('form') != '' && child.getAttribute('form') != null ) { tablerows = '<tr><th colspan=2>' + child.getAttribute('form') + '</th></tr>'; }
				else { tablerows = ''; };
				for ( j=0; j<attributelist.length; j++ ) {
					var att2 = attributelist[j];
					var attname = attributenames[att2];
					if ( !attname ) { attname = att2; };
					if (child.getAttribute(att2)) {
						var rowval = child.getAttribute(att2);
		    			if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att2] && tagdef[att2]['type'] == 'pos' ) { rowval = treatpos(child, att2, 'full'); }; 
						tablerows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + rowval + '</td></tr>';
					};
				}; 
				tokinfo.innerHTML += '<hr><table width=\'100%\'>' + tablerows + '</table>';
    		}; 
		};

    	// now look for mtoks
    	var parent = element.parentNode;
		if ( parent.tagName == "MTOK" && !done[parent.getAttribute('id')] ) { // TODO: this only works for the direct parent
			shownrows = 1;
			done[parent.getAttribute('id')] = 1;
			var form = parent.getAttribute('form');
			if ( !form ) { form = parent.innerText; };
			tablerows = '<tr><th colspan=2>' + form + '</th></tr>';
			for ( j=0; j<attributelist.length; j++ ) {
				var att2 = attributelist[j];
				var attname = attributenames[att2];
				if ( !attname ) { attname = att2; };
				if ( parent.getAttribute(att2) ) {
					var rowval = parent.getAttribute(att2);
					if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att2] && tagdef[att2]['type'] == 'pos' ) { rowval = treatpos(parent, att2, 'full'); };
					tablerows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + rowval + '</td></tr>';
				};
			}; 
			tokinfo.innerHTML += '<hr><table width=\'100%\'>' + tablerows + '</table>';
		};

		    	   	
		if ( shownrows )  { tokinfo.style.display = 'block'; };
		var foffset = offset(showelement);
		if ( typeof(poselm) == "object" ) {
			var foffset = offset(poselm);
		};
		tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
		tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';

    };
 
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
			if ( mtch != null ) { tmpe = mtch.iterateNext(); };
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
	
	var imgscale = facsimg.width/orgImg.width;

	var bb = elm.getAttribute('bbox').split(' '); 
	hlbar.style.display = 'block';
	hlbar.style['background-color'] = '#ffff00';
	hlbar.style['z-index'] = '100';
	hlbar.style['position'] = 'absolute';
	hlbar.style['opacity'] = '0.5';
	
	facsleft = facsimg.offsetLeft; obj = facsimg; while ( obj.offsetParent ) { obj = obj.offsetParent; facsleft += obj.offsetLeft; };
	facstop = facsimg.offsetTop; obj = facsimg; while ( obj.offsetParent ) { obj = obj.offsetParent; facstop += obj.offsetTop; };
	
	hlleft = ( bb[0] * imgscale ) + facsleft;
	hltop = ( bb[1] * imgscale ) + facstop;
	hlwidth = (bb[2] - bb[0])  * imgscale;
	hlheight = (bb[3] - bb[1])  * imgscale;

	
	hlbar.style.left = hlleft + 'px';
	hlbar.style.top = hltop + 'px';
	hlbar.style.width = hlwidth + 'px';
	hlbar.style.height = hlheight + 'px';
	
};

// Show docinfo
var docdata = {};
function showdocinfo(showelement) {
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
	var cid = showelement.getAttribute('cid');

	var foffset = offset(showelement);
	tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset )  + showelement.offsetWidth + 10 + 'px'; 
	tokinfo.style.top = ( foffset.top + 4 ) + 'px';

	tokinfo.style.display = 'block';
	tokinfo.innerHTML = '<p><i style="color: #aaaaaa;">loading document info</i></p>';

	if ( cid ) {
		if ( docdata[cid] ) {
		  tokinfo.innerHTML = docdata[cid];
		} else {
			// start Ajax to replace info by full data
			  var xhttp = new XMLHttpRequest();
			  xhttp.onreadystatechange = function() {
				if (this.readyState == 4 && this.status == 200) {
					docdata[cid] = this.responseText;
					tokinfo.innerHTML = this.responseText;
				}
			  };
			  xhttp.open('GET', 'index.php?action=ajax&data=docinfo&cid='+cid, true);
			  xhttp.send();
		};
	};

};


// Convert a UD features label to text
function treatfeats ( tok, label, type ) {
	tag = tok.getAttribute(label);
	if ( !tag ) { return ''; };
	return tag.replaceAll('|', ', ');
};


