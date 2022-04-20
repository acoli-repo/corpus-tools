document.onkeyup = keyb; 

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
		sentxml = makeXML(document.getElementById('sentxml').value)
		if ( !document.getElementById('sentxml').value ) {
			sentxml = makeXML(document.getElementById('mtxt').innerHTML);
			var senttxt = new XMLSerializer().serializeToString(sentxml);
			document.getElementById('sentxml').value = senttxt;
		};
	};
};

const getTransformParameters = (element) => {
  const transform = element.style.transform;
  let scale = 1,
    x = 0,
    y = 0;
  if (transform.includes("scale"))
    scale = parseFloat(transform.slice(transform.indexOf("scale") + 6));
  if (transform.includes("translateX"))
    x = parseInt(transform.slice(transform.indexOf("translateX") + 11));
  if (transform.includes("translateY"))
    y = parseInt(transform.slice(transform.indexOf("translateY") + 11));
  return { scale, x, y };
};
const getTransformString = (scale, x, y) =>
  "scale(" + scale + ") " + "translateX(" + x + "%) translateY(" + y + "%)";
function zoomsvg (dScale=0.1) {
	var svgtree = document.getElementById('svgtree');
	const { scale, x, y } = getTransformParameters(svgtree);
	var newscale = scale + dScale;
	svgtree.style.transform = getTransformString(newscale, x, y);
	var svgdiv = document.getElementById('svgdiv');
	var baseheight = svgtree.height.baseVal.value;
	console.log(baseheight*newscale);
	svgdiv.style.height = baseheight*newscale + 'px';
};

function keyb(evt) {
	if ( evt.key == "+" ) { zoomsvg(0.1); };
	if ( evt.key == "-" ) { zoomsvg(-0.1); };
	if ( selected ) {
		var newsel = '';
		var thisid = selected.getAttribute('tokid');
		var prntid = selected.getAttribute('head');
		var prnt = document.getElementById('node-'+prntid);
		if ( evt.key == "Enter" ) {
			window.open('index.php?action=tokedit&cid='+cid+'&tid='+selected.getAttribute('tokid'), 'edit');
		};
		if ( evt.key == "ArrowUp" ) {
			newsel = prnt;
		};
		if ( evt.key == "ArrowDown" ) {
		  var chldrn = document.evaluate('//tok[@head="'+thisid+'"]', document, null, XPathResult.ANY_TYPE, null );
		  var firstchild = chldrn.iterateNext();
		  if ( firstchild ) {
	  	    var chld = document.getElementById('node-'+firstchild.getAttribute('id'));
	  	    if ( !chld ) {
		  	    firstchild = chldrn.iterateNext();
		  	    chld = document.getElementById('node-'+firstchild.getAttribute('id'))
	  	    };
	  	    if ( chld ) {
				newsel = chld;
			};
		  };
		};
		if ( evt.key == "ArrowRight" || evt.key == "ArrowLeft" ) {
		  var sblings = document.evaluate('//tok[@head="'+prntid+'"]', document, null, XPathResult.ANY_TYPE, null );
		  var sbling = sblings.iterateNext();
		  var lastsb = sbling;
		  var sblid = '';
		  if ( sbling ) { sblid = sbling.getAttribute('id'); };
		  var lastid = '';
		  while ( sbling && sblid && thisid != sblid ) {
		  	lastid = sblid;
		  	sbling = sblings.iterateNext();
		  	sblid = sbling.getAttribute('id');
		  };
		  if ( evt.key == "ArrowRight" ) {
		  	sbling = sblings.iterateNext();
			if ( sbling ) { sblid = sbling.getAttribute('id'); };
		  } else if ( evt.key == "ArrowLeft" ) {
		  	sblid = lastid;
		  };		  
		  var sbl = document.getElementById('node-'+sblid);
		  if ( sbl ) {
			newsel = sbl;
		  };
		};
		if ( newsel ) {
			var newid = newsel.getAttribute('tokid');
			var tok = document.getElementById(newid);
			selected.setAttribute('fill',  ''); 
			newsel.setAttribute('fill',  '#ff00ff'); 
			selected = newsel;
			showtokinfo(newsel, tok, newsel);
			unhighlight();
			highlight(newid, '#ffff00'); 
			document.getElementById('linktxt').innerText = "Select a new head for the selected node (" + newsel.getAttribute('tokid') + " = " + newsel.innerHTML +  ")";
		};
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
			hlbar.style['opacity'] = '1';
		
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

function toksel (event) {
	var tid = event.target.getAttribute('id');
	if ( !tid ) { return; };
	var tmp = document.querySelectorAll('[tokid="'+tid+'"]');
	if ( !tmp.length ) { return; };
	relink(tmp[0]);
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
		
		var selid = selected.getAttribute('tokid');
		if ( !sentxml ) { sentxml = makeXML(document.getElementById('mtxt').innerHTML); };
		var changenode = sentxml.getElementById(selid);
		if ( selected.getAttribute('tokid')+'' == clicked.getAttribute('tokid')+'' ) { 
			document.getElementById('linktxt').innerText = "Self-headedness not allowed";
			if ( typeof(cid) != 'undefined' ) {
				window.open('index.php?action=tokedit&cid='+cid+'&tid='+selected.getAttribute('tokid'), 'edit');
			};
			selected = null;
			return; 
		};
		if ( changenode ) {
			changenode.setAttribute('head', clicked.getAttribute('tokid'));
			senttxt = new XMLSerializer().serializeToString(sentxml);
			document.getElementById('sentxml').value = senttxt;
			scriptedexit = true;
			document.sentform.submit();
		} else {
			console.log('oops - ' + selected.getAttribute('tokid'));
			console.log(changenode);
		};		
	};
	
};

function makelink ( clicked ) {
	var option = clicked[clicked.selectedIndex];
	var link = option.getAttribute('link');
	// console.log(link);
	window.open(link, '_new');
};

function scaletext () {
	var elms = document.getElementById('svgdiv').getElementsByTagName('text');
	for (i = 0; i <elms.length; i++) {
		elm = elms[i]; 
		bbox = elm.getBBox();
		fontsize = elm.getAttribute('font-size').slice(0,-2);
		while ( bbox.width > 150 && fontsize > 0) { 
			elm.setAttribute('font-size', fontsize-- + 'pt'); 
			bbox = elm.getBBox();
		};
	};
};