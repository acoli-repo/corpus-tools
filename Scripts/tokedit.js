var pattcolors = {'gloss':'#009900', 'nform':'#990099', 'pos':'#009999', 'mfs':'#009999', 'lemma':'#999900' };
var formified = false;
var labels = [ ];
var baseurl = location.href.replace(/(\/..)*\/index\.php.*/, '/');

// Default values for settings variables
var showee = false;
var showcol = false;
var showimg = false;
var showform = 'pform';
var showlist = "";
var showtag = [];
var transt = [];
var footnotes = [];
var basedirection = "";

if ( typeof(orgtoks) == "undefined" ) {
	var orgtoks = new Object();
};
if ( typeof(username) == "undefined" ) {
	var username = '';
}
if ( typeof(interpret) == "undefined" ) {
	var interpret = false;
};

function wsearch ( wrd ) {
	unhighlight();
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( tok.innerHTML == wrd ) {
			highlight (tok.getAttribute('id'), "#ffee44");
		};
	};
	return false; 
};

function setbut (id) { 
	var its = document.getElementsByTagName("button");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( it.getAttribute('id') == undefined ) { continue; };
		if ( it.getAttribute('id').substr(0,4) == "but-" ) {
			it.style.background = '#FFFFFF';
		};
	};
	if ( document.getElementById(id) ) {
		document.getElementById(id).style.background = '#eeeecc';
	};
};

function exporttxt () {
	var mtxt = document.getElementById("mtxt");
	var textToWrite = mtxt.innerText;
	if ( !textToWrite ) { textToWrite = getPlainText(mtxt); };
	var textFileAsBlob = new Blob([textToWrite], {encoding:"UTF-8",type:'text/plain;charset=UTF-8'});
	var fileNameToSaveAs = 'plaintext.txt';
	
	var downloadLink = document.createElement("a");
	downloadLink.download = fileNameToSaveAs;
	downloadLink.innerHTML = "Download File";
	if (window.webkitURL != null)
	{
		// Chrome allows the link to be clicked
		// without actually adding it to the DOM.
		downloadLink.href = window.webkitURL.createObjectURL(textFileAsBlob);
	}
	else
	{
		// Firefox requires the link to be added to the DOM
		// before it can be clicked.
		downloadLink.href = window.URL.createObjectURL(textFileAsBlob);
		downloadLink.onclick = destroyClickedElement;
		downloadLink.style.display = "none";
		document.body.appendChild(downloadLink);
	}

	downloadLink.click();
};

destroyClickedElement = function(event) {
    document.body.removeChild(event.target);
};

function formify () {
	// This is basically the "init" function
	
	// If we did not specify the attributelist, build it from formdef
	if ( typeof(attributelist) == "undefined" || attributelist.length == 0 ) {
		if ( typeof(attributelist) == "undefined" ) attributelist = Array();
		if ( typeof(formdef) != "undefined" ) {
			for ( fld in formdef ) {
				attributelist.push(fld);
				attributenames[fld] = formdef[fld]['display'];
			};
		};
		if ( typeof(tagdef) != "undefined" ) {
			for ( fld in tagdef ) {
				attributelist.push(fld);
				attributenames[fld] = tagdef[fld]['display'];
			};
		};
		if ( attributelist.length == 0 )  { attributelist = Array("fform", "lemma", "pos", "mfs"); };
	};
	
	var mtxt = document.getElementById("mtxt");
	if ( formified ) {
		return ""; 
	};
	formified = true;

	var tmp = mtxt.getElementsByTagName("text");
	if ( tmp[0] && tmp[0].getAttribute('direction') ) {
		basedirection = tmp[0].getAttribute('direction');
	};
	
	// Make all lb innerText into rend
	var its = mtxt.getElementsByTagName("lb");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( it.innerText != '' && it.innerText != undefined ) { 
			it.setAttribute('rend', it.innerText);
		};
		
		// Create internal element for rendering, numbering, and breaks
		var lbrend = document.createElement("span"); // LB rendering (hyphen)
		it.appendChild(lbrend);
		var lbnum = document.createElement("span"); // LB number (empty)
		it.appendChild(lbnum);
		var lbhl = document.createElement("span"); // LB line
		it.appendChild(lbhl);
	};
	
	var toks = mtxt.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };
		// When explicitly not having a form - don't show
		if ( tok.innerHTML == '--' ) { 
			tok.innerHTML = '';
		};
		tokid = tok.getAttribute('id');
		if ( tokid && orgtoks[tokid] == undefined ) {
			orgtoks[tokid] = tok.innerHTML;
		};
	};

	var toks = mtxt.getElementsByTagName("mtok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };
		// When explicitly not having a form - don't show
		if ( tok.innerHTML == '--' ) { 
			tok.innerHTML = '';
		};
		tokid = tok.getAttribute('id');
		if ( tokid && orgtoks[tokid] == undefined ) {
			orgtoks[tokid] = tok.innerHTML;
		};
	};

	var its = mtxt.getElementsByTagName("gap");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( it.innerHTML == '' ) { it.innerHTML = '[...]'; }; // unless there is text inside the gap, make it [...]
		// Make this node clickable
		if ( it.getAttribute('id') && username != '' )
			it.onclick = function() { window.open('index.php?action=elmedit&cid='+tid+'&tid='+this.getAttribute('id'), '_top'); };
	};

	// Make <note> into roll-over numbers (optional, can be turned off)
	if ( typeof(floatnotes) != "undefined" && floatnotes ) {
		var its = mtxt.getElementsByTagName("note");
		for ( var a = 0; a<its.length; a++ ) {
			var it = its[a];  
			if ( typeof(it) != 'object' ) { continue; };
			var notenr = it.getAttribute('n');
			if (!notenr) { notenr = parseInt(a)+1; };
			var noteid = it.getAttribute('id');
			if ( !footnotes[noteid] ) { footnotes[noteid] = it.innerHTML; };
			it.innerHTML = '['+notenr+']';
			it.style.display = 'inline';
			// Make this node roll-over
			it.onmouseover = function() { shownote(this.getAttribute('id')); };
			it.onmouseout = function() { hidenote(); };
			if ( it.getAttribute('id') && username != '' )
				it.onclick = function() { window.open('index.php?action=noteedit&cid='+tid+'&tid='+this.getAttribute('id'), '_top'); };
		};
	};

	var its = mtxt.getElementsByTagName("app");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];  
		if ( typeof(it) != 'object' ) { continue; };
		var itnr = it.getAttribute('n');
		if (!itnr) { itnr = parseInt(a)+1; };
		var itid = it.getAttribute('id');
		if ( !footnotes[itid] ) { footnotes[itid] = it.innerHTML; };
		it.innerHTML = '['+itnr+']';
		it.style.display = 'inline';
		// Make this node roll-over
		it.onmouseover = function() { shownote(this.getAttribute('id')); };
		it.onmouseout = function() { hidenote(); };
		// if ( it.getAttribute('id') && username != '' )
		//	it.onclick = function() { window.open('index.php?action=noteedit&cid='+tid+'&tid='+this.getAttribute('id'), '_top'); };
	};
	
	var its = mtxt.getElementsByTagName("deco");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if (it.getAttribute('decoRef')) {
			var decotxt = '['+it.getAttribute('decoRef')+']';
		} else {
			var decotxt = '[deco]';
		}; 
		it.innerHTML = decotxt;
	};
		
	// Treat all pb elements
	var pbs = mtxt.getElementsByTagName("pb");
	// there should be no <c_pb> at this point
	for ( var a = 0; a<pbs.length; a++ ) {
		var pb = pbs[a];
		if ( typeof(pb) != 'object' ) { continue; };
		if ( pb.getAttribute('id') && username )
			pb.onclick = function() { window.open('index.php?action=elmedit&cid='+tid+'&tid='+this.getAttribute('id'), '_top'); };

		// Create internal element for rendering, numbering, and breaks
		var pbhl = document.createElement("span"); // LB line
		pb.appendChild(pbhl);
		var pbnum = document.createElement("span"); // LB number (empty)
		pbnum.setAttribute('title', 'page number');
		pb.appendChild(pbnum);

		if ( typeof(nofacs) != "undefined" && nofacs == 1 ) {
			pb.setAttribute('admin', "1");
		};

		// Make img for all pb facs
		// TODO: make this work with IIIF
		if (  pb.getAttribute('facs') 
				&& ( pb.getAttribute("admin") != "1" || username )
				&& pb.getAttribute("img") != "yes" 
				&& ( typeof(noimg) == 'undefined' || typeof(noimg) == null )
			) { // Set a marker to say we already made an img
			var pbimg = pb.getAttribute('facs');
			pb.setAttribute("img", "yes");

			var imgsrc;
			if ( pb.getAttribute('facsimg') ) {
				imgsrc = pb.getAttribute('facsimg');
			} else {
				imgsrc = pb.getAttribute('facs');
			};
			if ( imgsrc.substr(0,4) != "http" ) {
				imgsrc = baseurl + 'Facsimile/' + imgsrc;
			};

			var imgelm = document.createElement("div");

			var imghl = document.createElement("div");
			imghl.setAttribute('class', 'hlbar'); // The highlight bar
			imgelm.appendChild(imghl);
			imgelm.setAttribute('class', 'imgdiv'); // The highlight bar
			
			var rlimg = document.createElement("img");
			imgelm.appendChild(rlimg);

			var pbcopy = pb.getAttribute('copy');
			if ( !pbcopy && typeof(facscopy) != "undefined" ) pbcopy = facscopy; 
			if ( pbcopy ) {
				var imgdesc = document.createElement("div");
				imgelm.appendChild(imgdesc);
				imgdesc.innerHTML = '&copy; ' + pb.getAttribute('copy');
				imgdesc.width = '100%';
				imgdesc.style['text-align'] = 'center';
				imgdesc.style['font-size'] = 'small';
				imgdesc.style['color'] = 'grey';
			};
			
			imgelm.setAttribute('src', imgsrc);
			rlimg.src = imgsrc;

			var pbrend = pb.getAttribute('rend'); 
			var cropside = pb.getAttribute('crop'); // Deprecated
			if ( cropside == "left" && pbrend == "" ) { pbrend = "0,0,50,100" };
			if ( cropside == "right" && pbrend == "" ) { pbrend = "50,0,100,100" };

			if ( pbrend == "0,0,50,100" ) {
				rlimg.style['cssFloat'] = 'left';
				rlimg.style.width = '200%';
			} else if ( pbrend == "50,0,100,100"  ) {
				rlimg.style['cssFloat'] = 'right';
				rlimg.style.width = '200%';
			} else if ( pbrend ) {
				var tmp = pbrend.split(",");
				var cutout = tmp[2]-tmp[0];
				var pbzoom = 10000/cutout;
				rlimg.style.width = pbzoom + '%';
				// TODO: resize the div vertically and move left/up	
			} else {
				rlimg.style.width = '100%';
			};
			imgelm.style['overflow'] = 'hidden';


			if ( pb.getAttribute('url') ) { imgelm.setAttribute('url', pb.getAttribute('url')); };
			if ( pb.getAttribute("admin") === "1"  ) { 	
				imgelm.style.border = '3px solid #992000'; 
				imgelm.title = 'Not shown to visitors due to copyright restrictions'; 
			};
			imgelm.setAttribute('facs', '1'); // Mark explicitly as a facsimile image
			imgelm.style.width = '40%';
			imgelm.style['cssFloat'] = 'right';
			imgelm.style['clear'] = 'right';
			imgelm.style.marginTop = '10px';
			imgelm.style.marginLeft = '10px';
			if ( imgelm.getAttribute('url') != "" && imgelm.getAttribute('url') != null ) {
				imgelm.onclick = function() { window.open(this.getAttribute('url'), 'img'); };
			} else {
				imgelm.onclick = function() { window.open(this.getAttribute('src'), 'img'); };
			};
			if ( pb.parentNode.nodeName == "tok" || pb.parentNode.nodeName == "TOK" ) {
				// We need to place this next to the tok - so go on to grandparent iff tok
				var tmp = pb.parentNode.parentNode.insertBefore( imgelm, pb.parentNode.nextSibling );
			} else {
				var tmp = pb.parentNode.insertBefore( imgelm, pb.nextSibling );
			};
		};
	};
};

function shownote ( id ) {
	document.getElementById('footnotediv').innerHTML = footnotes[id];
	document.getElementById('footnotediv').style.display = 'block';
};

function hidenote () {
	document.getElementById('footnotediv').style.display = 'none';
};

function toggleshow () { // Show or hide empty elements
	var but = document.getElementById('btn-see');
	if ( showee ) {
		showee = false;
		but.style.background = '#FFFFFF';
	} else {
		showee = true;
		but.style.background = '#eeeecc';
	};
	document.cookie = 'toggleshow='+showee;
	setview();
};

function toggletn (tag) { // Show or hide empty elements
	var but = document.getElementById('btn-tag-'+tag);
	if ( showtag[tag] ) {
		showtag[tag] = false;
		if  ( but != null ) but.style.background = '#FFFFFF';
	} else {
		showtag[tag] = true;
		if  ( but != null ) but.style.background = '#eeeecc';
	};
	// document.cookie = tag+'='+showee;
	setview();
};

function toggletag (tag) { // Show or hide empty elements
	var but = document.getElementById('tbt-'+tag);
	if ( !but ) return;
	var idx = labels.indexOf(tag);
	if ( idx > -1 ) {
		labels.splice(idx, 1);;
		but.style.background = '#FFFFFF';
	} else {
		labels.push(tag);
		but.style.background = '#eeeecc';
	};
	document.cookie = 'labels='+labels.join();
	setForm(showform);
};

function togglecol () { // Show or hide colours
	var but = document.getElementById('btn-col');
	if ( !but ) return;
	if ( showcol ) {
		showcol = false;
		but.style.background = '#FFFFFF';
	} else {
		showcol = true;
		but.style.background = '#eeeecc';
	};
	document.cookie = 'togglecol='+showcol;
	setForm(showform);
};

function toggleimg () { // Show or hide images
	var but = document.getElementById('btn-img');
	if ( !but ) return;
	if ( showimg ) {
		showimg = false;
		if (but && typeof(but.style) == "object") {
			but.style['background-color'] = '#FFFFFF';
		};
	} else {
		showimg = true;
		if (but && typeof(but.style) == "object") {
			but.style['background-color'] = '#eeeecc';
		};
	};
	document.cookie = 'toggleimg='+showimg;

	// Show/hide all IMG elements inside MTXT
	var its = mtxt.getElementsByClassName("imgdiv");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( it.start ) { continue; }; // this is not a facs image but a sound control button
		if ( showimg ) {
			it.style.display = 'block';
		} else {
			it.style.display = 'none';
		};
	};
};

function toggleint () { // Interpret breaks or not
	var but = document.getElementById('btn-int');
	if ( !but ) return;
	if ( interpret ) {
		interpret = false;
		if ( typeof(but) == "object" ) { 
			but.style['background-color'] = '#FFFFFF';
		};
	} else {
		interpret = true;
		if ( typeof(but) == "object" && but != null ) { 
			but.style['background-color'] = '#eeeecc';
		};
	};
	document.cookie = 'toggleint='+interpret;
	setview();
};

function setview () {
	var mtxt = document.getElementById('mtxt');
	var pbs = mtxt.getElementsByTagName("pb");
	// there should be no <c_pb> at this point
	var pnr = 0;
	for ( var a = 0; a<pbs.length; a++ ) {
		var pb = pbs[a];
		
		var pbhl = pb.childNodes[0]; 
		if ( typeof(pb) != 'object' ) { continue; };
		if ( typeof(pbhl) == 'undefined' ) { continue; };
		if ( interpret  && typeof(pagemode) == "undefined" ) {	
			pbhl.innerHTML = '<hr style="background-color: #cccccc; clear: both;">';
		} else {
			pbhl.innerHTML = '';
		};
		var pbnum = pb.childNodes[1]; 
		if ( showee || showtag['pb'] ) {	
			if ( pb.getAttribute('show') ) { pid = '' + pb.getAttribute('show'); } else 
			if ( pb.getAttribute('n') ) { pid = '' + pb.getAttribute('n'); } 
			else { pid = '<span style="opacity: 0.5;">'+a+'</span>'; };
			pbnum.innerHTML = '<span style="color: #4444ff; font-size: 12px;">['+pid+']</span>';
		} else{
			pbnum.innerHTML = '';
		};
	};
	var its = mtxt.getElementsByTagName("cb");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		it.innerHTML = '';
		if ( interpret ) {	
			it.innerHTML += '<hr style="background-color: #aaffaa;">';
		};
		if ( showee || showtag['cb'] ) {
			it.innerHTML += '<span style="color: #4444ff; font-size: 12px;">[cb]</span>';
			if ( it.getAttribute('id') && username != '' ) {
				it.firstChild.onclick = function() { window.open('index.php?action=elmedit&cid='+tid+'&tid='+this.parentNode.getAttribute('id'), '_top'); };
			};
		};
	};
	
	// Show the linebreaks
	var its = mtxt.getElementsByTagName("lb"); var lcnt = 0;
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];

		if ( typeof(it) != 'object' ) { continue; }; // For just in case

		// Handle the rendering element (innerHTML of the <lb/> or the @rend)
		var lbrend = it.childNodes[0]; if ( typeof(lbrend) == "undefined" ) { var lbrend = document.createElement("span"); it.appendChild(lbrend); };
		if ( showform == 'pform' && it.getAttribute('rend') != "none" && it.getAttribute('rend') != null ) {
			lbrend.innerHTML = it.getAttribute('rend');
		} else {
			lbrend.innerHTML = '';
		};

		if ( it.getAttribute('n') && it.getAttribute('n') != "false" ) { 
			lid = it.getAttribute('n'); 
		} else if ( typeof(autonumber) != 'undefined' ) {
			lcnt = lcnt + 1;
			lid = '['+lcnt+']';
		} else {
			lid = '';
		};
		
		// Handle the linebreak child
		var lbhl = it.childNodes[1]; if ( typeof(lbhl) == "undefined" ) { var lbhl = document.createElement("span");  it.appendChild(lbhl); };
		if ( interpret ) {
			lbhl.innerHTML = '<br>';
		} else if ( ( showee || showtag['lb'] ) && lid != '' ) {
			lbhl.innerHTML = '<span style="color: #4444ff; font-size: 14px;">['+lid+']</span>';
		} else if ( showee || showtag['lb'] ) {
			lbhl.innerHTML = '<span style="color: #4444ff; font-size: 14px;">|</span>';
		} else {
			lbhl.innerHTML = ''; lbrend.innerHTML = '';
		};
		
		 // Handle the number child
		var lbnum = it.childNodes[2];if ( typeof(lbnum) == "undefined" ) { var lbnum = document.createElement("span");  it.appendChild(lbnum); };
		if ( showee || showtag['lb'] ) {
			if ( interpret ) {
				if ( lid == '' ) { 
					lid = '-'; 
				};
				lbnum.innerHTML = '<div style="display: inline-block; color: #4444ff; font-size: 12px; width: 30px;">'+lid+'</div> ';
			} else {
				lbnum.innerHTML = '';
			};
			
			// Make the line element clickable
			if ( it.getAttribute('id') && username != '' ) {
				lbnum.onclick = function() { window.open('index.php?action=elmedit&cid='+tid+'&tid='+this.parentNode.getAttribute('id'), '_top'); };
				lbhl.onclick = function() { window.open('index.php?action=elmedit&cid='+tid+'&tid='+this.parentNode.getAttribute('id'), '_top'); };
			};
		} else {
			lbnum.innerHTML = '';
		};
	};

};

function showempties () {
	var mtch = document.evaluate("//*[.='']", document, null, XPathResult.ANY_TYPE, null); 
	var mitm = mtch.iterateNext();
	while ( mitm ) {
	  if ( typeof(mitm) != 'object' ) { continue; };
	  var nn = mitm.nodeName;
	  mitm.innerHTML = '<span style="color: #4444ff; font-size: 12px;">['+nn+']</span>';
	  mitm = mtch.iterateNext();
	}

};

function setbd (bd) {
	basedirection = bd;
};

function setForm ( type ) {
	if ( type != "" ) { setbut('but-'+type); };
	document.cookie = 'showform='+type;
	showform = type;
	
	// determine the writing direction
	// if ( typeof(formdir) != 'undefined' ) {

		if ( typeof(formdir) != 'undefined' && formdir[type] ) {
			document.getElementById('mtxt').style['direction'] = formdir[type];
			document.getElementById('mtxt').direction = formdir[type];
		} else if ( basedirection ) {
			document.getElementById('mtxt').style['direction'] = basedirection;
		} else if ( typeof(formdir) != 'undefined' && formdir['pform'] ) {
			document.getElementById('mtxt').style['direction'] = formdir['pform'];
		} else {
			document.getElementById('mtxt').style['direction'] = 'ltr';
		};
		
	// };

	// Do the <c> to allow for normalizing spaces
	var its = document.getElementsByTagName("c");
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];	
		if ( !it.hasAttribute('pform') ) { it.setAttribute('pform', it.innerHTML) }; // explicitly store
		var thisform = forminherit(it,type); 
		if ( type == "pform" ) thisform = it.getAttribute('pform');
		it.innerHTML = thisform; 
	};
		
	// Do the <mtok> when so asked to allow for normalizing MWE
	var its = document.getElementsByTagName("mtok");
	if ( typeof(mtokform) != 'undefined' ) {
		for ( var a = 0; a<its.length; a++ ) {
			var it = its[a];	
			if ( !it.hasAttribute('pform') ) { it.setAttribute('pform', it.innerText) }; // explicitly store form
			var tokid = it.getAttribute('id');
			var tokxml = orgtoks[tokid];
			it.innerHTML = tokxml; 
			if ( type != "pform" ) {
				var thisform = forminherit(it,type); 
				it.innerHTML = "";
				var org = document.createElement("span");
				org.innerHTML = tokxml; 
				it.appendChild(org);
				org.style.display = 'none';
				it.innerHTML += thisform; 
			};
		};
	};
			
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];		
		if ( typeof(tok) != 'object' ) { continue; };
		if ( showlist == "" ) { tok.className = ''; };
		var tokid = tok.getAttribute('id');
		// Lookup the XML version of this node
		var tokxml = orgtoks[tokid];
		if ( tokxml == undefined ) { 
			// We cannot find the orgtok - leave tok in place
			tokxml = '';
			console.log('Error: no orgtok found for '+tokid);
		} else {
			tok.innerHTML = tokxml;
		};
		// if ( showcol ) { tok.style['color'] = '#000000'; };
		tok.style['color'] = '';
		opre = "";  opost = "";
		var patt = new RegExp("<[pcl]b[^>]*>.*?</[pcl]b>", "g");
		while ( ( match = patt.exec(tokxml) ) != null ) {
    		opre += match;
		}
		patt = new RegExp("<dtok[^>]*></dtok>", "g");
		while ( ( match = patt.exec(tokxml) ) != null ) {
    		opre += match;
		}
		if ( type != "" && type != "pform" ) { // pform is the innerHTML

			var thisform = forminherit(tok,type);
			if ( thisform == '--' ) { thisform = "<ee/>"; };
			if ( thisform.search(/<[pcl]b/) > -1 ) {
				tok.innerHTML = thisform; // In calculated forms, the breaks might still be inside a non-pform
			} else if ( thisform != '' ) {
				// If we cannot find the form (inheritance error?) just do not touch the token
				tok.innerHTML = opre +  thisform + opost;
			};						
		};
		// If there are any labels to show, do so
		if ( labels.length && tok.innerHTML != '' ) {
			tok.className = 'floatblock';
			for ( var ab = 0; ab<labels.length; ab++ ) {
				label = labels[ab];
				var ltxt = tok.getAttribute(label); 
    			if ( typeof(tagdef) != "undefined" && tagdef && tagdef[label]['type'] == 'pos' ) { ltxt = treatpos(tok, label, 'main'); }; 
				// Add dtoks to the view
				var children = tok.childNodes;
				var done = []; var sep = ""; var dtxt = "";
				for ( i=0; i<children.length; i++ ) {
					var child = children[i];
					if ( child.tagName == "DTOK" && !done[child.getAttribute('id')] ) {
						if ( child.getAttribute(label) != null ) { 
							var labtxt;
			    			if ( tagdef && tagdef[label]['type'] == 'pos' ) { labtxt = treatpos(child, label, 'main'); } else { labtxt = child.getAttribute(label); };
							dtxt += sep + labtxt; sep = "+"; 
							done[child.getAttribute('id')] = 1;
						};
					};
				};
				if ( ltxt != null && ltxt != "" && dtxt != "" && dtxt != null ) { ltxt += ":" + dtxt; };
				if ( ( ltxt == null || ltxt == "" ) && dtxt != "" && dtxt != null ) { ltxt = dtxt; };
				if ( pattcolors[label] ) { 
					lcol = pattcolors[label]; 
				} else { lcol = "#999999"; };
				if ( but = document.getElementById('tbt-'+label) ) {
					ltit = " title=\""+but.textContent+"\"";
				} else { ltit = ""; };
				if ( ltxt != "" && ltxt != null ) { tok.innerHTML += "<div style='color: "+lcol+"'"+ltit+">" + ltxt + '</div>'; };
			};
		} else {
			tok.className = '';
		};
	};
	// The inserted breaks do not have visuals - so rerun 
	if ( interpret || showee ) {
		setview();
	};
};

function getlang ( node, type ) {
	if ( !node ) { return ""; };
	var langtext;
	if ( lang && type != "full" ) { langtext = node.getAttribute('short-'+lang); };
	if ( !langtext && lang ) { langtext = node.getAttribute('display-'+lang); };
	if ( !langtext && lang ) { langtext = node.getAttribute('lang-'+lang); }; // backward compatibility
	if ( !langtext && type != "full" ) { langtext = node.getAttribute('short'); };
	if ( !langtext ) { langtext = node.getAttribute('display'); };
	return langtext;
};

function treatpos ( tok, label, type ) {
	tag = tok.getAttribute(label);
	if ( !tag ) { return ''; };
	var tagset = document.getElementById('tagset');
	if ( tagset ) {
		// Show the main pos name of a position-based tagset
		var mainpos = tag.substring(0,1); 
		var xpath = "//item[@key='"+mainpos+"' and @maintag]"
		var tmp = document.evaluate(xpath, tagset, null, XPathResult.ANY_TYPE, null); 
		var tagdef = tmp.iterateNext();
		if ( tagdef ) {
			var maintext;
			prtlen = 1+parseInt(tagdef.getAttribute('maintag'));
			if ( prtlen == 1 ) {
				maintext = getlang(tagdef, type);
			} else {
				var tmp;
				do { // Get the longest defined match
					var posprt = tag.substr(0,prtlen);
					var xpath = ".//multi/item[@key='"+posprt+"']"
					var tmp = document.evaluate(xpath, tagdef, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null); 
					prtlen--;
				} while ( !tmp.snapshotLength && prtlen > 0 );
				var mtagdef;
				if ( tmp.snapshotLength ) { 
					mtagdef = tmp.snapshotItem(0);
				} else { 
					mtagdef = tagdef; // Default to main tag definition
				};
				maintext = getlang(mtagdef, type);
			};
			if ( type == "main" ) { return maintext; };
			if ( type == "full" ) {
				var mfs; var sep; 
				sep = ''; mfs= '';
				var mychildren = tagdef.childNodes;
				for ( ilc=0; ilc<mychildren.length; ilc++ ) {
					var posdef = mychildren[ilc];
					if ( posdef.tagName == "ITEM" ) {
						var posnr = parseInt(posdef.getAttribute('pos'));
						if ( posnr <= parseInt(tagdef.getAttribute('maintag')) ) { continue; };
						var posprt = tag.substring(posnr,1+posnr);
						if ( posprt != "" && posprt != "0" ) {
							var xpath = "item[@key='"+posprt+"']";
							var tmp = document.evaluate(xpath, posdef, null, XPathResult.ANY_TYPE, null); 
							var valdef = tmp.iterateNext();
							if ( valdef ) {
								var postxt;
								postxt = valdef.getAttribute('display-'+lang);
								if ( postxt == "" || postxt === null ) postxt = valdef.getAttribute('display');
								if ( postxt != "" ) {
									mfs += sep + postxt; 
									sep = '; ';
								};
							};
						};
					};
				};
				var fulltext = maintext + ' (' + tag+ ')' + '<br>' + mfs;
				return fulltext;				
			};
		};
	};
	
	return tag;
}

function tagshow () {
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };
		var raw = tok.innerHTML;
		var shown = raw.replace(/</g, '&lt;');
		tok.innerHTML = shown;
	};
};

function setPOS () {
	var toks = document.getElementsByTagName("tok");
	if ( postag == '' || postag == null ) { postag = 'pos'; };
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };
		var form = tok.innerHTML;
		tok.className = 'floatblock';
		if ( tok.getAttribute('lemma') ) lemma = tok.getAttribute('lemma'); else lemma = '';
		if ( tok.getAttribute(postag) ) pos = tok.getAttribute(postag); else pos = '';
		tok.innerHTML = '<div style="text-align: center;"><span style="font-weight: bold; font-size: 13pt">' + form + '</span><span style="color: #aaaaaa;"><br>' + lemma + '<br>' + pos + '</span></div>';
	};
};

function setALL () {
	document.getElementById('mtxt').innerHTML = orgXML; formify();
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };
		var raw = tok.innerHTML;
		form = raw.replace(/<\/[lp]b>/g, '');
		form = form.replace(/<([lp])b.*?>/g, '<span style="color: #8888ff; font-weight: normal;">[$1b]</span>');
		form = form.replace(/<ee\/?>/g, '<span style="color: #8888ff; font-weight: normal;">[ee]</span>');
		if ( typeof(tok) != 'object' ) { continue; };
		tok.className = 'floatblock';
		var formlist = '<tr><td><b>'+form+'</b></td></tr>';
		lform = form;
		for ( var t in formdef ) {
			if ( tok.getAttribute(t) ) {
				tform = tok.getAttribute(t);
				formlist += '<tr><td style="color: '+formdef[t]['color']+'">'+tform+'</td></tr>';
			} else { tform = lform; };
			lform = tform;
		};
		tok.innerHTML = '<div style="text-align: center; vertical-align: middle;"><table>' + formlist + '</tabl></div>';
	};
};

function psearch ( pos, match ) {
	unhighlight();
	var toks = document.getElementsByTagName("tok");
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( tok.getAttribute('pos') ) {
			if ( tok.getAttribute('pos') == pos ) {
				highlight (tok.getAttribute('id'), "#ffee88");
			} else if ( tok.getAttribute('pos').substr(0,pos.length) == pos  && tok.getAttribute('pos').substr(pos.length,1) == '-' ) { //  ) {
				highlight (tok.getAttribute('id'), "#88ffee");
			};
		};
	};
	return false; 
};

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

function jumpto (id) {
	highlight(id, '#ffffaa');
	document.getElementById(id).scrollIntoView(true);
};

function makeXML (text) {
if (window.DOMParser)
  {
  parser=new DOMParser();
  xmlDoc=parser.parseFromString(text,"text/xml");
  }
else // code for IE
  {
  xmlDoc=new ActiveXObject("Microsoft.XMLDOM");
  xmlDoc.async=false;
  xmlDoc.loadXML(text); 
  } 
  return xmlDoc;
};

function forminherit ( tok, fld ) {
	tok.style['opacity'] = 1; 
	if ( typeof(tok) != 'object' ) { console.log('no token'); return ''; };
	if ( typeof(fld) != 'string' ) { console.log('no form'); return ''; };
	if ( typeof(formdef[fld]) != 'object' ) { console.log('no settings for ' + fld); return ''; };
	if ( tok.getAttribute(fld) ) { 
		// this value is defined for this token
		if ( showcol && !tok.style['color'] ) { 
			if ( typeof(formdef[fld]['color']) != "undefined" ) { col = formdef[fld]['color']; } else { col = '#222222'; };
			tok.style['color'] = col;
		};
		return tok.getAttribute(fld);
	};
	// If this is a substraction - calculate both and substract
	var substract = formdef[fld]['subtract'];
	var inheritfrom = formdef[fld]['inherit'];
	if ( substract != undefined && inheritfrom != undefined ) {	
		var baseform = forminherit(tok, inheritfrom);
		var takeoff = forminherit(tok, substract);
		var difffrm = diffcalc(baseform, takeoff);
		// the subtracted form should be colored as this form	
		if ( showcol && difffrm != baseform ) { 
			if ( typeof(formdef[fld]['color']) != "undefined" ) { col = formdef[fld]['color']; } else { col = '#222222'; };
			tok.style['color'] = col;
		};
		return difffrm;
	};
	// When transliterating, find the form and transliterate
	var transfrom = formdef[fld]['transliterate'];
	if ( transfrom != undefined ) {
		var fromform = forminherit(tok, transfrom);
		var transform = transliterate(fromform)
		if ( showcol && transform != fromform  ) { 
			if ( typeof(formdef[fld]['color']) != "undefined" ) { col = formdef[fld]['color']; } else { col = '#222222'; };
			tok.style['color'] = col;
			// tok.style['opacity'] = 0.7;
		};
		return transform;
	};
	// If there is a form to inherit from, display that
	if ( inheritfrom != undefined ) {
		return forminherit(tok, inheritfrom);
	};
	return tok.innerHTML;
};

function transliterate ( form ) {
	console.log(form);
	if ( typeof(transl) != 'object' ) { console.log('no transliteration'); return form; };
	if ( transt.length == 0 ) { // Make an array out of the object on the first call
		for ( var a in transl ) {
			var from = transl[a]['from'];
			var to = transl[a]['to'];
			if ( typeof(from) != "undefined" && typeof(to) != "undefined" ) { 
				transt.push([from, to]); 
			};
		}; transt = transt.sort(function(a, b) {return b[0].length - a[0].length})
	};
	for ( var a = 0; a<transt.length; a++ ) {
		var from = transt[a][0];
		var to = transt[a][1];
		form = form.split(from).join(to);
		// form = form.replace(new RegExp(from, 'g'), to);
	};
	return form;
};

function diffcalc ( fform, form ) {
	if ( typeof(lex) == undefined || typeof(lex) == 'undefined' ) {
		var lex = '<ex>'; var rex = '</ex>'; 
	};
	
	var oform = form;

	// Clean up tags where needed 
	form = form.replace(/<[^>]+>/g, '');

	if ( form == '--'  || form == '<ee></ee>' ) {
		return ''; // explicit empty form
	} else if ( form == fform || form == '' || fform == ''  ) {
		return oform; // return untouched string when form and fform are the same
	} else {
		// calculate the difference between two form and bracket them
		// Step 1 - match all the letter on the end
		var beginning = ""; var end = ""; var middle = "";
		// Skip over abbreviation marks in form
		if ( form.substr(-1,1) == "̃" && form.substr(-1,1) != fform.substr(-1,1) ) { 
			form = form.substr(0,form.length-1);
		};
		while ( form.substr(-1,1) == fform.substr(-1,1) ) {
			end = form.substr(form.length-1,1)+end;
			form = form.substr(0,form.length-1);
			fform = fform.substr(0,fform.length-1);
			
			// Skip over abbreviation marks in form
			if ( form.substr(-1,1) == "̃" && form.substr(-1,1) != fform.substr(-1,1) ) { 
				form = form.substr(0,form.length-1);
			};
			
		};

		// Step 2 - match all the letter at the beginning
		while ( form.substr(0,1) == fform.substr(0,1) ) {
			beginning = beginning+form.substr(0,1);
			form = form.substr(1);
			fform = fform.substr(1);

			// Skip over abbreviation marks in form
			if ( form.substr(0,1) == "̃" && form.substr(0,1) != fform.substr(0,1) ) { 
				form = form.substr(1);
			};
		};
	
		// Now, if there still is more left in the form - dump the rest in expanded form
		if ( form == '' ) {
			middle = lex+fform+rex;
		} else {
			mend = ''; mstart = '';
			while ( form.substr(form.length-1,1) != fform.substr(fform.length-1,1) && fform.length > 0 ) {
				mend = fform.substr(fform.length-1,1)+mend;
				fform = fform.substr(0,fform.length-1);
			};
			while ( form.substr(0,1) != fform.substr(0,1) && fform.length > 0 ) {
				mstart += fform.substr(0,1);
				fform = fform.substr(1);
			};
			if ( form != fform ) {
				// Something went wrong - just return the form since we don't know what else to do
				// mleft = '['+form+'/'+fform+']'; 
				return oform;
			} else {
				mleft = form;
			};
			middle = lex+mstart+rex+mleft+lex+mend+rex;
		};
	
		diplo = beginning+middle+end;

		// TODO: see if we can return XML tags inside the diplomatic form (fast enough)
		
		return diplo;
	};
};

