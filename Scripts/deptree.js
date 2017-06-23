var toks = document.getElementsByTagName("text");
var selected = null;
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

if ( labelstxt != '' ) {
	document.getElementById('linktxt').innerText = "Edit mode: Select a node in the tree to attach it to a new head - or select a label to change it";

	var sentxml = makeXML(document.getElementById('mtxt').innerHTML);
	var senttxt = new XMLSerializer().serializeToString(sentxml);
	document.getElementById('sentxml').value = senttxt;
};

function relabel ( clicked ) {

		if ( selected != null ) {
			selected.setAttribute('fill',  ''); 
		};
		
		selected = clicked;
		clicked.setAttribute('fill',  '#ff00ff'); 

		document.getElementById('linktxt').innerHTML = labelstxt;

};

function setlabel ( clicked ) {

	console.log(clicked);
	console.log(selected);
	if ( selected != null && selected.getAttribute('type') == 'label' ) {
		var changenode = sentxml.getElementById(selected.getAttribute('baseid'));
		changenode.setAttribute('deps', clicked.getAttribute('key'));
		senttxt = new XMLSerializer().serializeToString(sentxml);
		document.getElementById('sentxml').value = senttxt;
		document.sentform.submit();
	};
	
};

function relink ( clicked ) {
	
	if ( selected != null ) {
		selected.setAttribute('fill',  ''); 
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
		document.sentform.submit();
		
	};
	
};
