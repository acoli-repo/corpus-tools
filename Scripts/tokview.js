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
    	// console.log(evt); 
    	if ( username ) {
    		window.open('index.php?action=tokedit&cid='+tid+'&tid='+element.getAttribute('id'), 'edit');
    	};
    };
};

function mouseOut(evt) {
	hidetokinfo();
};

function hidetokinfo() {
	document.getElementById('tokinfo').style.display = 'none';
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };
	
	showtokinfo(evt, element);
};
	
function showtokinfo(evt, element, poselm) {
	var shownrows = 0;
	// if ( element.attributes.length == 1 && !element.hasChildNodes() ) { console.log('nothing to show'); return -1; };
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) { return -1; };
    if ( element.tagName == "TOK" || element.tagName == "DTOK" ) {
    	var atts = element.attributes;
    	var tokid = element.getAttribute('id');
    	if ( typeof(orgtoks) != "undefined" ) { 
    		textvalue = orgtoks[tokid];
    	} else { textvalue = element.textContent; };
    	if ( textvalue == "null" ) { textvalue = ""; };
    	tablerows = '<tr><th colspan=2><b>' + textvalue + '</b></th></tr>';
    	for ( i=0; i<attributelist.length; i++ ) {
    		var att = attributelist[i];
			var attname = attributenames[att];
			if ( !attname ) { attname = att; };
    		if (element.getAttribute(att)) {
    			shownrows = 1;
	    		tablerows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + 
	    			element.getAttribute(att) + '</td></tr>';
	    	};
    	}; 
    	tokinfo.innerHTML = '<table width=\'100%\'>' + tablerows + '</table>';
    	
    	// now look for dtoks
    	var children = element.childNodes;
    	var done = [];
    	for ( i=0; i<children.length; i++ ) {
    		var child = children[i];
    		// console.log(child);
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
						tablerows += '<tr><th style=\'font-size: small;\'>' + attname + '</th><td>' + child.getAttribute(att2) + '</td></tr>';
					};
				}; 
				tokinfo.innerHTML += '<hr><table width=\'100%\'>' + tablerows + '</table>';
    		}; // else { console.log('Unknown child ' +i + ' of ' + element.childNodes.length + ' : ' + child.tagName + ' - ' + element ); };
		};
		    	   	
		if ( shownrows )  { tokinfo.style.display = 'block'; };
		var foffset = offset(element);
		if ( typeof(poselm) == "object" ) {
			var foffset = offset(poselm);
		};
		tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth ) + 'px'; 
		tokinfo.style.top = ( foffset.top + element.offsetHeight + 4 ) + 'px';

    }
    else {
    	// console.log(element.tagName);
    };
 
	function offset(elem) {
		if(!elem) elem = this;

		var x = elem.offsetLeft;
		var y = elem.offsetTop;

		while (elem = elem.offsetParent) {
			x += elem.offsetLeft;
			y += elem.offsetTop;
		}

		return { left: x, top: y };
	}    
} 

