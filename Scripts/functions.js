// Remove highlighting
function unhighlight () {
	var toks = document.getElementsByTagName("tok");
	for ( var a in toks ) {
		var tok = toks[a]; 
		if ( tok.style ) { 
			tok.style['background-color'] = "#ffffff"; 
			tok.style.backgroundColor= "#ffffff"; 
		};
	};
};

// Highlight an element by ID
function highlight ( id, color ) {
	if ( document.getElementById(id) ) {
		document.getElementById(id).style['background-color'] = color;
		document.getElementById(id).style.backgroundColor= color; 
	};
};

// Calculate the offset for an element
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
}    

// Hide the tokinfo popup
function hideinfo() {
	
	if ( document.getElementById('tokinfo') ) {
		tokid = document.getElementById('tokinfo').getAttribute('tokid');
		document.getElementById('tokinfo').style.display = 'none';
	};
	if ( typeof(hlbar) != "undefined" && typeof(facsdiv) != "undefined" ) {
		hlbar.style.display = 'none';
		var tmp = facsdiv.getElementsByClassName('hlbar'+hln);
	};
	if ( typeof(window.posttok) === 'function' ) { posttok('out', null, tokid); }; // if needed, run post scripts, pe to highlight the token elsewhere
};

// Show HTML on the tokinfo popup
function showinfo( evt, poselm, html ) {

	var shownrows = 0;
	var tibel = 3;
	var tokinfo = document.getElementById('tokinfo');
	if ( !tokinfo ) {
		var tokinfo = document.createElement("div"); 
		tokinfo.setAttribute('id', 'tokinfo');
		document.body.appendChild(tokinfo);
	};
	tokinfo.innerHTML = html;
	if ( html )  { tokinfo.style.display = 'block'; };
	var foffset = offset(poselm);
	tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
	tokinfo.style.top = ( foffset.top + poselm.offsetHeight + tibel ) + 'px';
 
}; 