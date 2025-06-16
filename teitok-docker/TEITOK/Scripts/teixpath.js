document.onclick = clickEvent; 
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 

var selelm = false;

if ( !mtxtelm ) { var mtxtelm = "TEXT"; };
var notoks = false;

var rendfeats = ['hand', 'rend'];

function clickEvent(evt) { 
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
};

function mouseOut(evt) {
	document.getElementById('pathinfo').innerHTML = '&nbsp;';
	if ( selelm ) {
		selelm.style['background-color'] = 'transparent';
		selelm.style.backgroundColor = 'transparent';
	}; selelm = false;
};

function mouseEvent(evt) { 
	element = evt.toElement; 
	if ( !element ) { element = evt.target; };
	
	if ( typeof element !== "object" ) { 
		console.log('Not a object: ' + element);
		return '';
	};
	
	showxpath(element);

	selelm = element;
	if ( document.getElementById('pathinfo').innerHTML != '&nbsp;' ) {
		selelm.style['background-color'] = '#ffffaa';
		selelm.style.backgroundColor = '#ffffaa';
	};
	
	
};

function showxpath ( node ) {
	var donode = node;

	if ( node.nodeType == 0 ) { 
		console.log('Not a node: ' + node);
		return ''; 
	};
	
	var xpath = nodeinfo(donode);
	
	while ( donode.parentNode && donode.parentNode.tagName != mtxtelm  && donode.parentNode.getAttribute('id') != 'mtxt' ) {
		donode = donode.parentNode;
		if ( donode.nodeType != 0 && nodeinfo(donode) != '' ) { xpath = nodeinfo(donode) + ' > ' + xpath; };
	};
	document.getElementById('pathinfo').innerHTML = xpath;
};

function nodeinfo (node) {
	
	if ( !node.tagName ) { return ''; };
	if ( node.tagName == "TOK" && notoks ) { return ''; };
	
	txt = node.tagName;
	
	for ( var a in rendfeats ) {	
		rf = rendfeats[a];
		if ( node.getAttribute(rf) ) { 
			txt += '['+rf+'="'+node.getAttribute(rf)+'"]';
		};
	};
	
	return txt;
};