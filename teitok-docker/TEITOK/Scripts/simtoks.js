function simtoks(tid) { 
	var tok = document.getElementById(tid);
	
	var div = document.getElementById('simtoks');
	div.innerHTML = '<p><ul><li><span class="fakelink" onClick=\"chall();\">select all</ul></p>';
	var its = document.getElementsByTagName("tok");
	for ( var a in its ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		var cpid = it.getAttribute('id');
		if ( tid != cpid && tokcompare(tok, it) ) {
			document.getElementById(cpid).style['background-color'] = '#dddddd';
			div.innerHTML += '<p><input type=checkbox ref=\"'+cpid+'\" name=simtoks['+cpid+'] value="treat" onChange=\"chtoggle(this);\">'+cpid+': '+it.innerHTML+'</p>';
		};
	};
};

function tokcompare ( tok1, tok2 ) {
	if ( tok1.textContent != tok2.textContent ) { return false; } // innerText only works in Safari
	return true;
};

function chall ( ) {
	var its = document.getElementsByTagName("input");
	for ( var a in its ) {
		var it = its[a];
		if ( typeof(it) != 'object' ) { continue; };
		if ( it.value == "treat" ) {
			it.checked = true;
			chtoggle(it);
		};
	};
};

function chtoggle ( nm ) {
	var id = nm.getAttribute('ref');
	var col = '#dddddd'
	if ( nm.checked ) {
		col = '#ffaaee';
	};
	highlight(id, col);
};

function unhighlight () {
	var toks = document.getElementsByTagName("tok");
	for ( var a in toks ) {
		var tok = toks[a];
		if ( tok.style ) { tok.style['background-color'] = "#ffffff"; };
	};
};

function highlight ( id, color ) {
	// console.log('setting ' + id + ' to ' + color );
	if ( document.getElementById(id) ) {
		document.getElementById(id).style.setProperty('background-color', color);
	};
};
