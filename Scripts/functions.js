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

function highlight ( id, color ) {
	if ( document.getElementById(id) ) {
		document.getElementById(id).style['background-color'] = color;
		document.getElementById(id).style.backgroundColor= color; 
	};
};
