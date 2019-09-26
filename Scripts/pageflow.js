document.onkeydown = function(evt) {
	evt = evt || window.event;
	if (evt.keyCode == 37) { // left = previous page
		switchpage(-1);
	} else if (evt.keyCode == 39) { // right = next page
		switchpage(1);
	} else if (evt.keyCode == 48) { // 0 = reset facsimile position
		resetfacs(1);
	} else if (evt.keyCode == 49) { // 1 = fit facsimile in window
		fitfacs();
	} else if (evt.keyCode == 70) { // f = fullscreen
		fullscreen();
	} else if (evt.keyCode == 27) { // escape = exit fullscreen
		unfullscreen();
	}
};

document.addEventListener('mousemove', mouseMove, false);
document.addEventListener('mouseup', mouseUp, false);

document.getElementById('grip').addEventListener('mousedown', function (e) {
	grip = e.target;
	startOffset = e.pageX;
});

document.addEventListener('webkitfullscreenchange', switchedfull, false);
document.addEventListener('mozfullscreenchange', switchedfull, false);
document.addEventListener('fullscreenchange', switchedfull, false);
document.addEventListener('MSFullscreenChange', switchedfull, false);
window.addEventListener('resize', resize, false);

// Init
var facsview = document.getElementById('facsview');
var textview = document.getElementById('mtxt');
var viewport = document.getElementById('viewport');
var doc = document.getElementById('fulltext').innerHTML;
var toc = document.getElementById('info');
var opts = document.getElementById('options');
var facs = new Image();
var facswidth;
var left = 50; // % of the leftmost column
var fullscreenmode = false;
var grip;
var startOffset;
var pagemode = true;
var fontsize;
var progscroll;

textview.addEventListener('scroll', textscroll, false);
textview.addEventListener('onscroll', textscroll, false);

// Populate the orgtoks (since we only display them by page)
if ( typeof(orgtoks) == "undefined" ) {
	var orgtoks = new Object();
};
var toks = document.getElementById('fulltext').getElementsByTagName("tok");
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
// Make all lb innerText into rend
var its = document.getElementById('fulltext').getElementsByTagName("lb");
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

var pagelist = document.getElementById('fulltext').getElementsByTagName('pb');
var pagesel = document.getElementById('pagesel') 
for ( i=0; i<pagelist.length; i++ ) {
	var option = document.createElement("option");
	option.text = pagelist[i].getAttribute('n');
	option.value = i+1;
	pagesel.add(option); 
};

var initleft = viewport.offsetLeft;
viewport.style.height = window.innerHeight + 'px';
viewport.style.width = (window.innerWidth-initleft) + 'px';
facsview.style.height = (viewport.offsetHeight-40) + 'px';
textview.style.height = (viewport.offsetHeight-40) + 'px';

var curpage = 1; 
var tid = getQueryVariable('tid');
if ( tid ) {
	var tmp = doc.indexOf(tid);
	if ( tmp != -1 ) showpage(1000000, tmp);
	else showpage(curpage);
} else {
	showpage(curpage);
};

redraw();

var last_position = {};
function mouseMove(evt) { 
	if (grip) {
        var newgrip = evt.pageX - viewport.offsetLeft;
        left = Math.floor((newgrip/viewport.offsetWidth) * 100);
        redraw();
	} else if ( evt.target.id == "facsview" && evt.buttons == 1) {
		//check to make sure there is data to compare against
		if (typeof(last_position.x) != 'undefined') {

			//get the change from last position to this position
			var deltaX = last_position.x - evt.clientX,
				deltaY = last_position.y - evt.clientY;

			bpx = bpx - deltaX;
			facsview.style.backgroundPositionX = bpx + "px"; 
			bpy = bpy - deltaY;
			facsview.style.backgroundPositionY = bpy + "px"; 

		};

		//set the new last position to the current for next time
		last_position = {
			x : evt.clientX,
			y : evt.clientY
		};	
    };

};
function mouseUp(evt) { 
	last_position = {};
	grip = undefined;
};

function showmenu() {
	// show the options menu
};

function setpage(num) {
	curpage = num*1; 
	showpage(curpage);
};

function switchpage(dif) {
	curpage = curpage + dif; 
	if ( curpage < 1 ) curpage = 1;
	if ( curpage > pagelist.length-1 ) curpage = pagelist.length-1;
	pagesel.selectedIndex = curpage - 1; 
	showpage(curpage);
};

function showpage(num, before=-1) {
	var i=0; var lp = -1; var pi = 0; var tmp = 0;
	while ( tmp != -1 && i < num ) {
		tmp = doc.indexOf('<pb', lp+1);
		// If we got here via a token, look up the page
		if ( before != -1 && tmp > before ) {
			tmp2 = doc.indexOf('id="', lp)+4;
			tmp3 = doc.indexOf('"', tmp2);
			pageid = doc.substring(tmp2, tmp3);
			for ( i=0; i<pagelist.length; i++ ) {
				if ( pagelist[i].getAttribute('id') == pageid ) curpage = i + 1;
				pagesel.selectedIndex = curpage - 1;
			};
			break;
		};
		pi = tmp; 
		page = pagelist[i];
		i++; lp = pi;
	};
	np = doc.indexOf('<pb', lp+1);
	pageXML = doc.substring(pi, np);

	textview.innerHTML = pageXML;

	if ( tid && document.getElementById(tid) ) {	
		document.getElementById(tid).style.backgroundColor = '#ffff88';
	};
	
	var img = page.getAttribute('facs');
	if ( img && ( !page.getAttribute('admin') || username ) ) {
		var imgurl = img;
		if ( imgurl.indexOf("http") == -1 ) imgurl = 'Facsimile/' + imgurl;
    	facs.src = imgurl;
    	facsview.style['background-image'] = 'url('+imgurl+')';
    	facswidth = facsview.offsetWidth;
		facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
		facsview.style['background-repeat'] = 'no-repeat';
		facsview.style.backgroundPositionX = "0px"; bpx = 0;
		facsview.style.backgroundPositionY = "0px"; bpy = 0;
		
		facsview.addEventListener("mousewheel", scalefacs, false);
		facsview.addEventListener("DOMMouseScroll", scalefacs, false);
	} else {
    	facsview.style['background-image'] = 'none';
	};
	if ( page.getAttribute('copy') ) {
		facsview.innerHTML = "&copy; " + page.getAttribute('copy');
	};


	pageinit();
		
	facswidth = facsview.offsetWidth;
	redraw();
	
	// Finally, skip this page if it is empty
	// TODO: unless we select it by hand
	if ( page.getAttribute('empty') ) {
		switchpage(1);
	};
	
};

function pageinit() {

	formify();
	setForm(showform);
	fontzoom(0);	
};

function tocshow() {
	if ( toc.style.display == 'block' ) {
		toc.style.display = 'none';
	} else {
		toc.style.display = 'block';
	};
	redraw();
};

function optshow() {
	var optrow = opts.parentNode.parentNode;
	if ( optrow.style.display == 'none' ) {
		optrow.style.display = 'table-row';
	} else {
		optrow.style.display = 'none';
	};
	redraw();
};

function fontzoom (factor) {
	var toks = mtxt.getElementsByTagName("tok");
	if ( typeof(toks[0]) != 'object' ) return;
	if ( !fontsize ) fontsize = window.getComputedStyle(toks[0], null).getPropertyValue('font-size').replace('px', '') * 1;
	fontsize = Math.floor(fontsize) + factor;
	for ( var a = 0; a<toks.length; a++ ) {
		var tok = toks[a];
		if ( typeof(tok) != 'object' ) { continue; };

		tok.style.fontSize = fontsize + 'px';
	};
	redraw();
};

function zoom(factor) {
	facswidth = facswidth + (30 * factor);

	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	redraw();
};

function scalefacs(e) {
	var delta = Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
	
	if ( e.shiftKey || e.buttons ) {
		var maxwidth = Math.max(facs.naturalWidth*1.5, facsview.offsetWidth);
		facswidth = Math.max(50, Math.min(maxwidth, facswidth + (30 * delta)));

		facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	} else if ( e.axis == 1 ) {	
		var tmp = facsview.style.backgroundSize.match(/(.+)px (.+)px/);
		var maxx = tmp[1]*1 - facsview.offsetWidth;
		bpx = Math.max(-maxx, Math.min(0, bpx + (30 * delta)));
		facsview.style.backgroundPositionX = bpx + "px"; 
	} else {
		var tmp = facsview.style.backgroundSize.match(/(.+)px (.+)px/);
		var maxy = tmp[2]*1 - facsview.offsetHeight + 0;
		bpy = Math.max(-maxy, Math.min(0, bpy + (30 * delta)));
		facsview.style.backgroundPositionY = bpy + "px"; 
		
		// grab the first tok inside the view 
		if ( mtxt.innerHTML.indexOf('bbox') != -1 ) 
		if ( delta < 0 ) {
			// scroll down
			var lbs = mtxt.getElementsByTagName("lb"); var i=0; var seltok;
			while ( !seltok && i<lbs.length ) {
				var tmp = lbs[i].getAttribute('bbox').split(" ");
				if ( tmp[1] > 0-bpy ) {
					seltok = lbs[i];
					scrollToElementD(seltok);
				};
				i++;
			};
		} else {
			// scroll up
			var lbs = mtxt.getElementsByTagName("lb"); var i=0; var seltok;
			while ( !seltok && i<lbs.length ) {
				var tmp = lbs[i].getAttribute('bbox').split(" ");
				if ( tmp[1] > 0-bpy ) {
					seltok = lbs[i];
					scrollToElementD(seltok);
				};
				i++;
			};
		};
	};
		
	return false;
};

function switchedfull (e) {
	// Called after changing fullscreen mode (manually or automatically)
	if ( ( document.webkitIsFullScreen && typeof(document.webkitIsFullScreen) != "undefined" ) || document.mozFullScreen || ( document.msFullscreenElement !== null && typeof(document.msFullscreenElement) != "undefined" ) ) {
		unfullscreen();
		resetfacs(1);
	} else {
		fullscreen();
	};
};

function togglefull () {
	// Called to change fullscreen mode (manually)
    if ( fullscreenmode ) {
		unfullscreen();
		fullscreenmode = false;
	} else {
		fullscreen();
		fullscreenmode = true;
	};
};

function fullscreen() {

	// Set DIV to full browser screen
	if (document.documentElement.requestFullScreen) {  
	  document.documentElement.requestFullScreen();  
	} else if (document.documentElement.mozRequestFullScreen) {  
	  document.documentElement.mozRequestFullScreen();  
	} else if (document.documentElement.webkitRequestFullScreen) {  
	  document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);  
	};  
	
	fullscreenmode = true;
	document.getElementById('fullscreen').innerHTML = '<i class=\"material-icons\">fullscreen_exit</i>';

	document.getElementById('pageflow').style['position'] = 'absolute';
	
	// Make the viewport use the entire screen
	viewport.style.height = screen.height + 'px';
	viewport.style.width = screen.width + 'px';
	viewport.style.top = '0';
	viewport.style.left = '0';

	facswidth = facsview.offsetWidth;
	redraw();
}

function resetfacs() {
	facswidth = facsview.offsetWidth;
	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	redraw();
}

function fitfacs() {

	var natwidth = viewport.offsetHeight*(facs.naturalWidth/facs.naturalHeight);
	left = Math.floor((natwidth/viewport.offsetWidth) * 100);

	facswidth = facsview.offsetWidth;
	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	redraw();
}

function redraw() {

	// Redraw the table 
	document.getElementById('col1').style.width = left + '%';
	document.getElementById('col2').style.width = ( 100 - left ) + '%';
	document.getElementById('viewtable').style.height = viewport.offsetHeight + 'px';

	// Resize the divs inside the table to the table size
	// The box model makes this more difficult than it should be
	facsview.style.height = (facsview.parentNode.offsetHeight-20) + 'px';
	textview.style.height = (textview.parentNode.offsetHeight-60) + 'px';
	facsview.style.width = ( facsview.parentNode.offsetWidth -10) + 'px';
	textview.style.width = ( textview.parentNode.offsetWidth -45) + 'px';

	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	facsview.style.backgroundPositionX = "0px"; bpx = 0;
	facsview.style.backgroundPositionY = "0px"; bpy = 0;
	facsview.style['line-height'] = (facsview.style.height.replace("px","")*1 * 2)-30 + 'px'; 

	// TODO: make this use percentages (or bbox)
	var pbrend = page.getAttribute('rend'); var bbrend;
	var cropside = page.getAttribute('crop'); // Deprecated
	if ( cropside == "left" ) { pbrend = "0,0,50,100" };
	if ( cropside == "right" ) { pbrend = "50,0,100,100" };
	if ( pbrend != null && pbrend != "" ) {
		bbrend = pbrend.split(",");
		var tmp = bbrend[2]-bbrend[0];
		pbzoom = 1/(tmp/100);
		facswidth = facsview.offsetWidth * pbzoom;
		facsheight = (facswidth*(facs.naturalHeight/facs.naturalWidth));
		facsview.style['background-size'] = facswidth + 'px ' + facsheight + 'px';
		bpx = 0 - (bbrend[0]/100)*facswidth;
		facsview.style.backgroundPositionX = bpx + "px"; 
		bpy = 0 - (bbrend[1]/100)*facsheight;
		facsview.style.backgroundPositionY = bpy + "px"; 
		console.log('Show: ' + bbrend + ' > ' + pbzoom + ' - ' + bpx + ' x ' + bpy);
	};
	
	var rect = facsview.getClientRects();
	toc.style.top = rect[0]['top'] + 'px';
	toc.style.left = rect[0]['left'] + 'px';
	toc.style.width = rect[0]['width'] + 'px';
	toc.style.height = rect[0]['height'] + 'px';
	
};

function unfullscreen() {

	// Set DIV to inline
	if (document.cancelFullScreen) {  
	  document.cancelFullScreen();  
	} else if (document.mozCancelFullScreen) {  
	  document.mozCancelFullScreen();  
	} else if (document.webkitCancelFullScreen) {  
	  document.webkitCancelFullScreen();  
	};

	fullscreenmode = false;
	document.getElementById('fullscreen').innerHTML = '<i class=\"material-icons\">fullscreen</i>';

	document.getElementById('pageflow').style['position'] = 'inline';

	// Put the viewport where it used to be
	viewport.style.height = window.innerHeight + 'px';
	viewport.style.width = (window.innerWidth-initleft) + 'px';
	viewport.style.left = initleft + 'px';
		
	facswidth = facsview.offsetWidth;
	redraw();
}

function resize() {
	if ( !fullscreenmode ) {
		// Put the viewport where it used to be
		viewport.style.height = window.innerHeight + 'px';
		viewport.style.width = (window.innerWidth-initleft) + 'px';		
	};
	redraw();
}

function getQueryVariable(variable) {
       var query = window.location.search.substring(1);
       var vars = query.split("&");
       for (var i=0;i<vars.length;i++) {
               var pair = vars[i].split("=");
               if(pair[0] == variable){return pair[1];}
       }
       return(false);
}

function dolines(type) {
	showtag['lb'] = false;
	if ( type == 'bar' ) {
		showtag['lb'] = true;
	};
	interpret = false;
	if ( type == 'format' ) {
		interpret = true;
	};
	setview();
}

function textscroll(e) {
	var lbs = mtxt.getElementsByTagName("lb"); var i=0; var seltok;
	var facsheight = facswidth*(facs.naturalHeight/facs.naturalWidth);
	
	if ( progfacs ) {
		progfacs = false;
		return;
	}; progfacs = false;
	
	while ( !seltok && i<lbs.length ) {
		var tmp = lbs[i].getAttribute('bbox').split(" ");
		if ( lbs[i].offsetTop > mtxt.scrollTop ) {
			seltok = lbs[i];
			bpy = Math.max(-facsheight, Math.min(0, 40 - tmp[1] ));
			facsview.style.backgroundPositionY = bpy + "px"; 
		};
		i++;
	};
}


function scrollToElementD(elm){
	var topPos = elm.offsetTop;
	var newst = topPos - mtxt.offsetTop - (mtxt.offsetHeight/2) + (elm.offsetHeight/2);
	progfacs = true;
	mtxt.scrollTop = newst;
}

