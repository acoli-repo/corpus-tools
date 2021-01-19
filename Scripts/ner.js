var mtxt = document.getElementById('mtxt');
var nerdata = {};
var seq = []; var selstring = '';

var tokinfo = document.getElementById('tokinfo');
if ( !tokinfo ) {
	var tokinfo = document.createElement("div"); 
	tokinfo.setAttribute('id', 'tokinfo');
	document.body.appendChild(tokinfo);
};

if ( jmp ) { 
	var it = document.getElementById(jmp);
	it.style['backgroundColor'] = '#ffffbb'; 
	it.scrollIntoView(true); 
}; // TODO: this should depend on jmp

var nercolor;
for ( var i=0; i<Object.keys(nerlist).length; i++) {
	var tmp = Object.keys(nerlist)[i];
	var tagelm = nerlist[tmp]['elm'];
	if ( !tagelm ) { tagelm = tmp; };
	var its = mtxt.getElementsByTagName(tagelm);
	nercolor = nerlist[tmp]['color']; if ( !nercolor ) { nercolor = 'green'; };
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];	
		it.style.color = nercolor;
		// it.style['font-weight'] = 'bold';
		it.onclick = function(event) {
			doclick(this);
		};
		it.onmouseover = function(event) {
			showinfo(this);
		};
		it.onmouseout = function(event) {
			hideinfo(this);
		};
		if ( it.getAttribute(nerlist[tmp]['nerid']) == hlid ) { 
			it.style['backgroundColor'] = '#ffffbb'; 
			if ( !jmp ) { it.scrollIntoView(true); }; // TODO: this should depend on jmp
		}
	};
};

function doclick(elm) {
	var ttype = elm.nodeName.toLowerCase();
	var neratt = nerlist[ttype]['nerid'];
	var trgt = elm.getAttribute(neratt);
	var newurl = 'index.php?action=ner&nerid='+encodeURIComponent(trgt)+'&type='+ttype;
	if ( username ) {
		newurl = 'index.php?action=ner&act=edit&cid='+fileid+'&nerid='+elm.getAttribute('id');
	};
	console.log(newurl);
	window.open(newurl, '_self');
};

function hideinfo(showelement) {
if ( document.getElementById('tokinfo') ) {
	document.getElementById('tokinfo').style.display = 'none';
};
if ( typeof(hlbar) != "undefined" && typeof(facsdiv) != "undefined" ) {
	hlbar.style.display = 'none';
	var tmp = facsdiv.getElementsByClassName('hlbar'+hln);
};
};

function showinfo(showelement) {
if ( !tokinfo ) { return -1; };
var nertype = nerlist[showelement.nodeName.toLowerCase()];

nername = showelement.nodeName; 
if ( nertype ) nername =  nertype['display'];
infoHTML = '<table><tr><th>' + nername + '</th><td><b><i>'+ showelement.innerHTML +'</i></b></td></tr>';
if ( showelement.getAttribute('type') ) {
	var typetext = showelement.getAttribute('type') + '';
	if ( typeof(attnames) != 'undefined' && attnames[typetext] ) { typetext = 	attnames[typetext]; }
	else 
	if ( document.getElementById('tagset') ) { typetext = treattag(showelement, 'type', 'full'); }
	infoHTML += '<tr><th>' + 'Type' + '</th><td>'+ typetext +'</td></tr>';
};

tokinfo.style.display = 'block';
var foffset = offset(showelement);
tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
tokinfo.style.top = ( foffset.top + showelement.offsetHeight + 4 ) + 'px';

infoHTML += '</table>';

tokinfo.innerHTML = infoHTML;

var idfld = 'corresp';
if ( nertype ) idfld =  nertype['nerid'];
var nerid = showelement.getAttribute(idfld)
if ( nerid ) {
	if ( nerdata[nerid] ) {
	  tokinfo.innerHTML = nerdata[nerid];
	} else {
		// start Ajax to replace info by full data
		  var xhttp = new XMLHttpRequest();
		  xhttp.onreadystatechange = function() {
			if (this.readyState == 4 && this.status == 200) {
			 nerdata[nerid] = this.responseText;
			 tokinfo.innerHTML = this.responseText;
			}
		  };
		  xhttp.open('GET', 'index.php?action=ner&act=snippet&nerid='+encodeURIComponent(nerid), true);
		  xhttp.send();
	};
};


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
};  
function makespan(event) { 
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

	// Reset the selection
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		tok.style['background-color'] = null;
		tok.style.backgroundColor= null; 
	};
	seq = []; 

	var nodei = node1;

	seq.push(node1); 
	while ( nodei != noden && nodei ) {
		nodei = nodei.nextSibling;
		if ( nodei && ( nodei.nodeName == 'TOK' || nodei.nodeName == 'tok' )  ) { 
			seq.push(nodei);			
		};
	};
	window.getSelection().removeAllRanges();
	
	color = '#88ffff';  selstring = '';  idlist = ''; 
	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		if ( tok == null ) continue;
		tok.style['background-color'] = color;
		tok.style.backgroundColor= color; 
		selstring += tok.innerHTML + ' ';
		idlist += tok.getAttribute('id') + ';';
	};
	
	document.getElementById('toklist').value = idlist;
	document.getElementById('addner').style.display = 'block';
	document.getElementById('nerspan').innerHTML = selstring;
	
};

function jumpto (tmp) {
	var idlist = tmp.split(";");
	var it = document.getElementById(idlist[0]);

	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		tok.style['background-color'] = null;
		tok.style.backgroundColor= null; 
	};
	seq = []; selstring = '';

	it.style['backgroundColor'] = '#ffffbb'; 
	it.scrollIntoView(true); 
	seq.push(it);
};
function highlight (tmp) {
	var idlist = tmp.split(";");

	for ( var a = 0; a<seq.length; a++ ) {
		var tok = seq[a];
		tok.style['background-color'] = null;
		tok.style.backgroundColor= null; 
	};
	seq = []; selstring = '';

	for ( var a = 0; a<idlist.length; a++ ) {
		var it = document.getElementById(idlist[a]);
		it.style['backgroundColor'] = '#ffffbb'; 
		seq.push(it);
	};
};

function getlang ( node, type ) {
	if ( !node ) { return ""; };
	if ( typeof(lang) == 'undefined' ) { lang = ''; };
	var langtext;
	if ( lang && type != "full" ) { langtext = node.getAttribute('short-'+lang); };
	if ( !langtext && lang ) { langtext = node.getAttribute('display-'+lang); };
	if ( !langtext && lang ) { langtext = node.getAttribute('lang-'+lang); }; // backward compatibility
	if ( !langtext && type != "full" ) { langtext = node.getAttribute('short'); };
	if ( !langtext ) { langtext = node.getAttribute('display'); };
	return langtext;
};

function treattag ( elm, label, type ) {
	tag = elm.getAttribute(label);
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
			if ( type == "main" ) { 
				return maintext; 
			} else if ( type == "full" ) {
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
			} else {
				return maintext;
			};
		};
	};
	
	return tag;
}