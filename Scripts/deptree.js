var toks = document.getElementsByTagName("text");
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

