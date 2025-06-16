function maketext() { 
	var its = document.getElementsByTagName('eLeaf');
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		var leaftext = it.getAttribute('Text');
		if ( !leaftext ) { leaftext = it.getAttribute('Notext'); };
		if ( !leaftext ) { leaftext = '&empty;'; };
		it.innerHTML = leaftext;
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
		if ( username && it.getAttribute('id') ) {
			it.onclick = function () {
				var tid = this.getAttribute('id');
				if ( !cid ) {
					forestnode = this;
					while ( forestnode.tagName != "FOREST" && forestnode.parentNode ) { 
						forestnode = forestnode.parentNode; 
					};
					cid = forestnode.getAttribute('File');
				};
				window.open('index.php?action=psdx&act=nodeedit&cid='+cid+'&treeid='+treeid+'&nid='+tid, 'edit');
			};
		};
	};
	var its = document.getElementsByTagName('eTree');
	for ( var a = 0; a<its.length; a++ ) {
		var it = its[a];
		var theFirstChild = it.firstChild;
		var newElement = document.createElement('nodeName');
		newElement.innerHTML = it.getAttribute('Label');
		if ( username && it.getAttribute('id') ) {
			newElement.onclick = function () {
				var tid = this.parentNode.getAttribute('id');
				if ( !cid ) {
					forestnode = this;
					while ( forestnode.tagName != "FOREST" && forestnode.parentNode ) { 
						forestnode = forestnode.parentNode; 
					};
					cid = forestnode.getAttribute('File');
				};
				window.open('index.php?action=psdx&act=nodeedit&cid='+cid+'&treeid='+treeid+'&nid='+tid, 'edit');
			};
		};
		it.insertBefore(newElement, theFirstChild);
	};
};
