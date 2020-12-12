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

tokinfo.style.display = 'block';
var foffset = offset(showelement);
if ( typeof(poselm) == "object" ) {
	var foffset = offset(poselm);
};
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