var toks = document.getElementsByTagName("text");
var selected = null;
var sentxml;
var svgdiv = document.querySelector('svg').parentNode;

for ( var a = 0; a<toks.length; a++ ) {
	var it = toks[a];
	it.onmouseover = function () {
		var tid = this.getAttribute('tokid');
		if ( tid ) {
			highlight(tid, '#ffff00'); 
			showtokinfo(this, document.getElementById(tid), this);
		};
	};
	it.onmouseout = function() {
		unhighlight(); hidetokinfo();
	};
};

if ( typeof(labelstxt) != "undefined" ) {
	if ( labelstxt != '' ) {
	document.getElementById('linktxt').innerText = "Edit mode: Select a node in the tree to attach it to a new head - or select a label to change it";

	sentxml = makeXML(document.getElementById('mtxt').innerHTML);
	var senttxt = new XMLSerializer().serializeToString(sentxml);
	document.getElementById('sentxml').value = senttxt;
	};
};

function highlightlocal(elm, hln) {
	if ( elm.tagName != "TOK" ) return -1;
	var tokid = elm.getAttribute('id');
	
	placehlbar(tokid, hln);
	var its = elm.getElementsByTagName("dtok");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];	
		placehlbar(it.getAttribute('id'), a+hln);
	}

}

function placehlbar (tokid, hln) {

	// Go through all the <text> elements with this tokid
	var its = svgdiv.getElementsByTagName("text");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];	
		if ( it.getAttribute('tokid') == tokid ) { 
			var domRect = it.getBoundingClientRect();

			// Determine the hlbar and scale of the image div
			hlbar = svgdiv.getElementsByClassName('hlbar').item(hln);
			if ( !hlbar ) {
				hlbar = document.createElement("div");
				hlbar.setAttribute('class', 'hlbar'); // The highlight bar
				svgdiv.insertBefore(hlbar, svgdiv.firstChild);
			}; 

			hlbar.style.display = 'block';
			hlbar.style['background-color'] = '#ffff00';
			hlbar.style['z-index'] = '1';
			hlbar.style['position'] = 'absolute';
			hlbar.style['opacity'] = '0.5';
		
			hlbar.style.left = domRect.x + window.scrollX + 'px';
			hlbar.style.top = domRect.y + window.scrollY + 'px';
			hlbar.style.width = domRect.width + 'px';
			hlbar.style.height = domRect.height + 'px';
			hlbar.style.display = 'block';

		};
	};
};

function relabel ( clicked ) {

		if ( selected != null ) {
			if ( selected.getAttribute('type') == 'label' ) {
				selected.setAttribute('fill',  '#ff8866'); 
			} else {
				selected.setAttribute('fill',  ''); 
			};
		};
		
		selected = clicked;
		clicked.setAttribute('fill',  '#ff00ff'); 

		document.getElementById('linktxt').innerHTML = labelstxt;

};

function setlabel ( clicked ) {

	if ( selected != null && selected.getAttribute('type') == 'label' ) {
		var changenode = sentxml.getElementById(selected.getAttribute('baseid'));
		changenode.setAttribute('deprel', clicked.getAttribute('key'));
		senttxt = new XMLSerializer().serializeToString(sentxml);
		document.getElementById('sentxml').value = senttxt;
		scriptedexit = true;
		document.sentform.submit();
	};
	
};

function relink ( clicked ) {
	
		if ( selected != null ) {
			if ( selected.getAttribute('type') == 'label' ) {
				selected.setAttribute('fill',  '#ff8866'); 
			} else {
				selected.setAttribute('fill',  ''); 
			};
		};
	
	if ( selected == null || selected.getAttribute('type') != 'tok' ) {
		
		clicked.setAttribute('fill',  '#ff00ff'); 
		document.getElementById('linktxt').innerText = "Select a new head for the selected node (" + clicked.getAttribute('tokid') + " = " + clicked.innerHTML +  ")";
		selected = clicked;
				
	} else {
		
		var changenode = sentxml.getElementById(selected.getAttribute('tokid'));
		changenode.setAttribute('head', clicked.getAttribute('tokid'));
		senttxt = new XMLSerializer().serializeToString(sentxml);
		document.getElementById('sentxml').value = senttxt;
		scriptedexit = true;
		document.sentform.submit();
		
	};
	
};

function makelink ( clicked ) {
	var option = clicked[clicked.selectedIndex];
	var link = option.getAttribute('link');
	// console.log(link);
	window.open(link, '_new');
}
