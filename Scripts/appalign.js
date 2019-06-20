document.onclick = clickEvent; 
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 

var appid; var hls;

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	// We might be hovering over a child of our TOK
	if ( element.parentNode && element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
	if ( element.parentNode && element.parentNode.parentNode && element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };

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
	if ( typeof(hls) == null ) { return -1; };
	for (i = 0; i < hls.length; ++i) {
		hls[i].style['background-color'] = "";
		hls[i].style.backgroundColor= ""; 
	};
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	if ( typeof(element) != "object" ) { return -1; };
	
	// Look up the DOM tree until we find an element with an @appid 
	appid = element.getAttribute('appid');
	while ( appid == null && element.getAttribute('id') != 'mtxt' ) { 
		element = element.parentNode; 
		appid = element.getAttribute('appid');
	};

	// Now highlight the appid
	var qs = '*[appid=\''+appid+'\']';
	hls = document.querySelectorAll(qs);
	var color = '#ffffaa';
	var newtop = element.scrollTop;
	for (i = 0; i < hls.length; ++i) {
		hls[i].style['background-color'] = color;
		hls[i].style.backgroundColor= color; 
		
		if ( hls[i] != element ) { 
			// Scroll aligned elements into view
			hls[i].scrollIntoView({behavior: "smooth", block: "center"}); 
		};
	};
	
};
	