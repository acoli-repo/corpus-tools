document.onclick = clickEvent; 
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 
if (!attributelist) {
	var attributelist = Array("fform", "lemma", "pos", "mfs");
};
if (!attributenames) {
	var attributenames = Array();
};

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };

    if (element.tagName == "TOK" ) {
    	if ( username ) {
    		if ( typeof(tid) == "undefined" ) { // For KWIC rows
				var mtch = document.evaluate("ancestor::tr[@tid]", element, null, XPathResult.ANY_TYPE, null); 
				var mitm = mtch.iterateNext();
				jumpid = mitm.getAttribute('tid');
    		} else {
    			jumpid = tid;
    		};
    		window.open('index.php?action=tokedit&cid='+jumpid+'&tid='+element.getAttribute('id'), 'edit');
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
	if ( typeof(hlbar) != "undefined" ) {
		hlbar.style.display = 'none';
	};
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };
	
	showtokinfo(evt, element);
	highlightbb(element);
	
};
	
function showtokinfo(evt, element, poselm) {
	var shownrows = 0;
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
    if ( element.tagName == "TOK" || element.tagName == "DTOK" || element.tagName == "MTOK" ) {
    	var atts = element.attributes;
    	var tokid = element.getAttribute('id');
    	if ( typeof(orgtoks) != "undefined" ) { 
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
    		textvalue = ""; 
    	};
    	tablerows = '<tr><th colspan=2><b>' + textvalue + '</b></th></tr>';
    	for ( ia=0; ia<attributelist.length; ia++ ) {
    		var att = attributelist[ia];
			var attname = attributenames[att];
			if ( !attname ) { attname = att; };
    		if ( element.getAttribute(att) && ( !formdef[att] || ( !formdef[att]['admin'] || username ) ) ) {
    			shownrows = 1;
    			var rowval = element.getAttribute(att);
    			if ( typeof(tagdef) != "undefined" && tagdef && tagdef[att] && tagdef[att]['type'] == 'pos' ) { rowval = treatpos(element, att, 'full'); }; 
	    		tablerows += '<tr><th style=\'font-size: small;\' span="row">' + attname + '</th><td name="'+att+'">' + 
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
						tablerows += '<tr><th style=\'font-size: small;\' span="row">' + attname + '</th><td>' + rowval + '</td></tr>';
					};
				}; 
				tokinfo.innerHTML += '<hr><table width=\'100%\'>' + tablerows + '</table>';
    		}; 
		};
		    	   	
		if ( shownrows )  { tokinfo.style.display = 'block'; };
		var foffset = offset(element);
		if ( typeof(poselm) == "object" ) {
			var foffset = offset(poselm);
		};
		tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
		tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';

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
} 

function highlightbb (elm) {

	// Find the bbox we need
	if ( elm.getAttribute('bbox') == null ) {
		var mtch = document.evaluate("ancestor::*[@bbox]", elm, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null); 
		if ( mtch.snapshotLength == 0 ) {
			mtch = document.evaluate("ancestor-or-self::tok/preceding::lb", elm, null, XPathResult.ANY_TYPE, null);
		};
		var tmpe;
		if ( mtch != null ) { tmpe = mtch.iterateNext(); };
		while ( tmpe != null )  { elm = tmpe; tmpe = mtch.iterateNext(); };				
	};
	if ( elm.getAttribute('bbox') == null ) { return -1; };

	// Find the image div we need
	var mtch = document.evaluate("preceding::div[div[@class='hlbar']]", elm, null, XPathResult.ANY_TYPE, null); 
	var facsdiv;
	var tmpe = mtch.iterateNext(); 
	while ( tmpe != null )  { facsdiv = tmpe; tmpe = mtch.iterateNext(); };		
	if ( typeof(facsdiv) == "undefined" ) { return -1; };
	
	// Determine the hlbar and scale of the image div
	hlbar = facsdiv.getElementsByClassName('hlbar').item(0);
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



