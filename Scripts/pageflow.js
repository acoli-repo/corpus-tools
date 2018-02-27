document.onkeydown = function(evt) {
	evt = evt || window.event;
	if (evt.keyCode == 37) {
		switchpage(-1);
	} else if (evt.keyCode == 39) {
		switchpage(1);
	} else if (evt.keyCode == 48) {
		resetfacs(1);
	} else if (evt.keyCode == 27) {
		unfullscreen();
	}
};

document.addEventListener('mousemove', mouseMove, false);
document.addEventListener('mouseup', mouseUp, false);

var last_position = {};
function mouseMove(evt) { 
	if ( evt.target.id == "facsview" && evt.buttons == 1) {
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
};

function showmenu() {
	// show the options menu
};

document.addEventListener('webkitfullscreenchange', togglefull, false);
document.addEventListener('mozfullscreenchange', togglefull, false);
document.addEventListener('fullscreenchange', togglefull, false);
document.addEventListener('MSFullscreenChange', togglefull, false);

// Init
var facsview = document.getElementById('facsview');
var textview = document.getElementById('mtxt');
var viewport = document.getElementById('viewport');
var doc = document.getElementById('fulltext').innerHTML;
var facs = new Image();
var facswidth;

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
facsview.style.height = (viewport.offsetHeight-30) + 'px';
textview.style.height = (viewport.offsetHeight-30) + 'px';

var curpage = 1; 
var tid = getQueryVariable('tid');
if ( tid ) {
	var tmp = doc.indexOf(tid);
	if ( tmp != -1 ) showpage(1000000, tmp);
	else showpage(curpage);
} else {
	showpage(curpage);
};

function setpage(num) {
	curpage = num*1; 
	console.log(num);
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
	// console.log(page.getAttribute('n') + ' = ' + curpage);

	if ( tid ) {	
		document.getElementById(tid).style.backgroundColor = '#ffff88';
	};
	
	// var img = page.getAttribute('facs');
	var img = textview.innerHTML.match(/facs="([^"]+)"/);
	if ( img ) {
		var imgurl = 'Facsimile/' + img[1];
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
		facsview.innerHTML = "";
	};
	
	resetfacs();
	
	// Finally, skip this page if it is empty
	// TODO: unless we select it by hand
	if ( page.getAttribute('empty') ) {
		switchpage(1);
	};
	
};

function resetfacs() {
	facswidth = facsview.offsetWidth;
	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	facsview.style.backgroundPositionX = "0px"; bpx = 0;
	facsview.style.backgroundPositionY = "0px"; bpy = 0;
};

function scalefacs(e) {
	var delta = Math.max(-1, Math.min(1, (e.wheelDelta || -e.detail)));
	
	var maxwidth = Math.max(facs.naturalWidth*1.5, facsview.offsetWidth);
	facswidth = Math.max(50, Math.min(maxwidth, facswidth + (30 * delta)));

	facsview.style['background-size'] = facswidth + 'px ' + (facswidth*(facs.naturalHeight/facs.naturalWidth)) + 'px';
	
	return false;
};

function togglefull (man) {
    if ( document.webkitIsFullScreen || document.mozFullScreenEnabled ) {
		fullscreen(man);
	} else {
		unfullscreen(man);
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
	
	document.getElementById('fullscreen').innerHTML = '<i class=\"material-icons\">fullscreen_exit</i>';
	
	viewport.style.height = screen.height + 'px';
	viewport.style.width = screen.width + 'px';
	viewport.style.top = '0';
	viewport.style.left = '0';
	
	facsview.style.height = (viewport.offsetHeight-30) + 'px';
	textview.style.height = (viewport.offsetHeight-30) + 'px';

	resetfacs();
}

function unfullscreen(man) {

	if ( man == 1 ) {
		// Set DIV to inline
		if (document.cancelFullScreen) {  
		  document.cancelFullScreen();  
		} else if (document.mozCancelFullScreen) {  
		  document.mozCancelFullScreen();  
		} else if (document.webkitCancelFullScreen) {  
		  document.webkitCancelFullScreen();  
		};
	};

	document.getElementById('fullscreen').innerHTML = '<i class=\"material-icons\">fullscreen</i>';
	
	viewport.style.height = window.innerHeight + 'px';
	viewport.style.width = (window.innerWidth-initleft) + 'px';
	viewport.style.left = initleft + 'px';
	
	facsview.style.height = (viewport.offsetHeight-30) + 'px';
	textview.style.height = (viewport.offsetHeight-30) + 'px';
	
	resetfacs();
}

function getQueryVariable(variable)
{
       var query = window.location.search.substring(1);
       var vars = query.split("&");
       for (var i=0;i<vars.length;i++) {
               var pair = vars[i].split("=");
               if(pair[0] == variable){return pair[1];}
       }
       return(false);
}