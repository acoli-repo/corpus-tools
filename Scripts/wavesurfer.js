document.onclick = clickEvent; 
document.onkeydown = keyEvent; 
document.onmouseover = mouseEvent; 
document.onmouseout = mouseOut; 
document.onmousedown = mouseDown; 
document.onmouseup = mouseUp; 
document.onmousemove = mouseMove; 

var wavesurfer = Object.create(WaveSurfer);
var waveform = document.getElementById('waveform');
var utteditor = document.getElementById('utteditor');
var mtxt = document.getElementById('mtxt');
var speed = 1; 
var zoom = 100;
var loaded = false;
var pointa = 0;
var pointe = 0;
var utttag = "U";
var currregion;
var editmode;
var downpoint;
var downtype;
var lastdown;
var modified = false;

var uttxp = "//" + utttag;

window.onbeforeunload = function warnUsers() {
	if (modified) {
		return "Your XML has been changed, unsaved changes will be lost.";
	}
}

function keyEvent(evt) { 
	// Handle key presses - with the ALT key in edit mode

	var kc = evt.keyCode
	var actfld = document.activeElement.tagName;
	if ( loaded && ( !editmode || evt.altKey ) ) {
		switch ( kc ) {
			case 32: // space
				playpause(evt); 
				evt.preventDefault();
				break;
			case 39: // rightarrow
				wavesurfer.skipForward(); 
				evt.preventDefault();
				break;
			case 37: //leftarrow
				wavesurfer.skipBackward(); 
				evt.preventDefault();
				break;
			case 65: // a - set a
				pointa = wavesurfer.getCurrentTime(); 
				pointe = 0;
				currregion.id = 'new';
				currregion.update({start: pointa, end: pointa, color: 'rgba(255, 255, 0, 0.3)'});
				evt.preventDefault();
				break;
			case 66: // b - back to a
				if ( pointa ) { wavesurfer.play(pointa); }; 
				evt.preventDefault();
				break; 
			case 67: // c - create utterance
				if ( pointa && pointe && editmode ) { 
					newutt(); 
					modified = true;
				}; 
				evt.preventDefault();
				break; 
			case 69: // e - set e and repeat
				if ( pointa ) { 
					pointe = wavesurfer.getCurrentTime(); 
					currregion.update({end: pointe});
					wavesurfer.pause();
				}; 
				evt.preventDefault();
				break; 
			case 70: // f - continue from e
				if ( pointe ) {
					pointa = pointe; 
					pointe = 0;
					currregion.id = 'new';
					currregion.update({start: pointa, end: pointa, color: 'rgba(255, 255, 0, 0.3)'});
					wavesurfer.play();
				};
				evt.preventDefault();
				break;
				
			case 78:  // n - speed to normal
				evt.preventDefault();
				setspeed(0);  break;
			case 80: // p - play currregion
				if ( pointa && pointe ) { 
					currregion.play();
				}; 
				evt.preventDefault();
				break; 
			case 81: 
				pointa = 0;  
				pointe = 0
				currregion.update({start: pointa});
				currregion.update({end: pointe});
				evt.preventDefault();
				break; // q - remove a
			case 83: 
				setspeed(0.8);  
				evt.preventDefault();
				break; // s - slow down
			case 90: 
				setzoom(1.2);  
				evt.preventDefault();
				break; // z - zoom in
		};
	}; 
}

function mouseMove(evt) { 
	uppoint = xtotime(evt);
	if ( evt.buttons && downtype != "DOWN" && ( evt.target.tagName == "HANDLE" || evt.target.tagName == "REGION" ) ) {
		// Mark we are dragging a region or region handle, and do not do move until mouseup
		downtype = "HANDLE";
	};
    if ( evt.buttons && downtype != "HANDLE" ) {
		if ( uppoint > downpoint ) {
			pointa = downpoint;
			pointe = uppoint;
		} else if ( uppoint < downpoint ) {
			pointe = downpoint;
			pointa = uppoint;
		};
		currregion.update({start: pointa, end: pointe, color: 'rgba(255, 255, 0, 0.3)'});
		currregion.id = "new";
    };
};

function mouseDown(evt) { 
    downpoint = xtotime(evt);
	downtype = "DOWN";
};

function mouseUp(evt) { 
	downtype = "UP";
};


function xtotime(evt) {
	var timeidx = 0;
	const bbox = wavesurfer.drawer.wrapper.getBoundingClientRect();
	var clientX = evt.clientX - bbox.left + wavesurfer.drawer.wrapper.scrollLeft;

	timeidx = ( clientX / wavesurfer.drawer.width ) * wavesurfer.getDuration(); // this works only for the first canvas
    
    return timeidx;
};

function clickEvent(evt) { 

	// Check if we cmd-click a token - in which we jump to edit if we are logged in
	element = evt.toElement;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };
	
	// With the cmd button pressed, we are trying to edit a token
	if ( evt.metaKey && username ) {
		if ( element.parentNode.tagName == "TOK" ) { element = element.parentNode; };
		if ( element.parentNode.parentNode.tagName == "TOK" ) { element = element.parentNode.parentNode; };

		if (element.tagName == "TOK" ) {
			window.open('index.php?action=tokedit&cid='+tid+'&tid='+element.getAttribute('id'), 'edit');
		};
		return;
	};
	
	// Check if we are clicking on an utterance
	
	// We might be hovering over a child of our utterance
	if ( element.parentNode && element.parentNode.tagName == utttag ) { element = element.parentNode; };
	if ( element.parentNode && element.parentNode.parentNode && element.parentNode.parentNode.tagName == utttag ) { element = element.parentNode.parentNode; };

	if ( element.tagName == utttag ) {
		// We are clicking on an utterance
		var uttid = element.getAttribute('id');
		var uttreg = regionarray[uttid];
		if ( uttreg.start && (!editmode || evt.altKey) ) {
			pointa = uttreg.start; pointe = uttreg.end;
			currregion.update({start: pointa, end: pointe, color: 'rgba(255, 0, 0, 0.15)'});
			if ( uttreg.id == lastreg ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
			currregion.id = uttreg.id; // set the ID so we know we do now want to create a new utterance
			currregion.play();
			evt.preventDefault();
		} else if ( editmode && evt.altKey && currregion.start && currregion.end ) {
			// For an utterance that does not yet have a region, set it to current region
			uttreg.start = currregion.start;
			uttreg.end = currregion.end;
			element.setAttribute("start", currregion.start);
			element.setAttribute("end", currregion.end);
			evt.preventDefault();
		};
	} else if ( ( element.tagName == "CANVAS" || element.tagName == "REGION" ) ) {
		// We are clicking in the wavesurfer
		if ( evt.shiftKey ) {
			pointa = wavesurfer.getCurrentTime(); 
			pointe = xtotime(evt);
			currregion.id = 'new';
			currregion.update({start: pointa, end: pointe, color: 'rgba(255, 255, 0, 0.3)'});
		};
	};
	
};

var lastreg;
function mouseEvent(evt) { 
	// Check whether the mouse rolls over an utterance
	var element = evt.toElement; var reg;
	if ( !element ) { element = evt.target; };
	if ( !element ) { console.log('No element found - try Chrome or Firefox'); console.log(evt); return -1; };

	// We might be hovering over a child of our utterance
	if ( element.parentNode && element.parentNode.tagName == utttag ) { element = element.parentNode; };
	if ( element.parentNode && element.parentNode.parentNode && element.parentNode.parentNode.tagName == utttag ) { element = element.parentNode.parentNode; };

	if ( element.tagName == utttag && !wavesurfer.isPlaying() ) {
		if ( regionarray[lastreg] ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
		lastreg = element.getAttribute('id');
		reg = regionarray[lastreg];
		if ( reg.id != currregion.id ) { reg.update({color: 'hsla(120, 100%, 50%, 0.1)'}); };
	}
	
}

function mouseOut(evt) { 
	// Hide the last roll-over region (if there is one), unless the sound is playing (otherwise the two out function interfere)
	if ( regionarray[lastreg]  && !wavesurfer.isPlaying()  ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
}

function regionOut(evt) { 
	// Hide the last roll-over region, when the waveform moves out of it (if there is one)
	if (lastutt) lastutt.style.backgroundColor = "";
	if ( regionarray[lastreg] ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
}


function newutt () {
	// Check that we are allowed to edit, and in edit mode
	if ( !editmode || !username ) return;

	var a = currregion.start;
	var b = currregion.end;
	a = Math.floor(a*1000)/1000;
	b = Math.floor(b*1000)/1000;
	wavesurfer.pause();

	// Show the utterance editor and instantiate the fields
	utteditor.style.visibility = 'visible';
	document.uttform.start.value = a;
	document.uttform.end.value = b;
	document.uttform.uttid.value = currregion.id;
	
	if ( currregion.id != "new" ) {
		var utt = uttarray[currregion.id];
		document.uttform.transcription.value = utt.innerHTML; 
		document.uttform.who.value = utt.getAttribute('who');
	} else {
		document.uttform.transcription.value = ''; // reset the transcription (but leave @who since it is often correct)
	};
	
	// focus the transcription
	document.uttform.transcription.focus();
};

function changeutt (frm) {
	// Process the new or updated utterance from the edit window
	var v = document.uttform;
	var utt;
	
	var uttid = v.uttid.value;
	if ( uttid == "new" ) {
		utt = document.createElement(utttag);
		// add the utt to the end of the list of utterances
		var mtch = document.evaluate(uttxp, waveform, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
		var llu = mtch.snapshotItem(mtch.snapshotLength-1);
		if ( llu ) {
			llu.parentNode.insertBefore(utt, llu.nextSibling);
			uttid = llu.getAttribute('id') + "-1";
		} else {
			var newline = document.createTextNode("\n\t");
			mtxt.firstChild.appendChild(newline);
			mtxt.firstChild.appendChild(utt);
			uttid = "utt-1";
		};
		utt.setAttribute('id', uttid);
		uttarray[uttid] = utt;
		var newregion = wavesurfer.addRegion({
			start: v.start.value, // time in seconds
			end: v.end.value, // time in seconds
			drag: false,
			resize: false,
			color: 'hsla(0, 0%, 0%, 0)'
		});
		regionarray[uttid] = newregion
	} else {
		utt = uttarray[uttid];
	};
	utt.setAttribute('start', v.start.value);
	utt.setAttribute('end', v.end.value);
	utt.setAttribute('who', v.who.value);
	
	
	// Now add the XML inside
	utt.innerHTML = v.transcription.value;	
	
	utteditor.style.visibility = 'hidden';
	pointa = 0; pointe = 0;
	
	modified = true;
	
	return false;
};

wavesurfer.init({
	container: document.querySelector('#waveform'),
	// backend: 'WebAudio',
	pixelRatio: 1,
	// normalize: false,
	// barWidth: 2,
	// barHeight: 200,
	// responsive: false,	
	scrollParent: true,
	waveColor: '#992200',
	autoCenter: true,
	audioRate: 1,
	fillParent: false,
	renderer: 'MultiCanvas',
	// splitChannels: true,
});

var minimap; var zoomregion;
var uttarray = Array();
var regionarray = Array();
var durtxt;

wavesurfer.load(soundfile);
wavesurfer.on('ready', function () {
	document.getElementById('loading').style.display = 'none';
	document.getElementById('waveblock').style.visibility = 'visible';

	// Load some optional arguments from the PHP
	if ( typeof(alttag) == "string" ) utttag = alttag;
	if ( typeof(setedit) == "boolean" ) editmode = setedit;
	
	if ( editmode ) {
		mtxt.addEventListener('click', mtxtSelect);
		mtxt.addEventListener('keyup', mtxtSelect);
	};

	setzoom(1);
	loaded = true;

	minimap = wavesurfer.initMinimap({
		height: 30,
		waveColor: '#ddd',
		progressColor: '#999',
		cursorColor: '#68A93D',
		barHeight: 1.4
	});

	// Load the utterances
	var mtch = document.evaluate(uttxp, waveform, null, XPathResult.UNORDERED_NODE_SNAPSHOT_TYPE, null);
	for ( var i=0 ; i < mtch.snapshotLength; i++ ) {
		utt = mtch.snapshotItem(i);
		var uttid = utt.getAttribute("id");
		if ( uttid == "" ) uttid = "utt" + i;
		var start = utt.getAttribute("start")*1;
		var stop = utt.getAttribute("end")*1;
		uttarray[uttid] = utt; 
		
		var newregion = wavesurfer.addRegion({
			start: start, // time in seconds
			end: stop, // time in seconds
			drag: false,
			resize: false,
			color: 'hsla(0, 0%, 0%, 0)'
		});
		newregion.id = utt.getAttribute('id');
		regionarray[uttid] = newregion;
	};
	
	// Now, resize the mtxt to fill the whole space below the wavesurfer element
	var setheight = window.innerHeight - mtxt.offsetTop - 5;
	mtxt.style.height = setheight + 'px';
	document.getElementById('fullmtxt').style.visibility = 'visible';
	durtxt = ftime(wavesurfer.getDuration());
	
	utteditor.style.top = mtxt.parentNode.offsetTop + 'px';
	utteditor.style.left = mtxt.parentNode.getBoundingClientRect().left + 'px';
	
	var sourceeditor = document.getElementById('sourceeditor');
	if ( sourceeditor ) {
		sourceeditor.style.top = mtxt.parentNode.offsetTop + 'px';
		sourceeditor.style.left = mtxt.parentNode.getBoundingClientRect().left + 'px';
		sourceeditor.style.height = mtxt.style.height;
		sourceeditor.style.width = mtxt.offsetWidth + 'px';
		aceeditor.resize();
	};
		
	if ( jmp ) {
		// Jump to a token
		var mtch = document.evaluate("//*[@id=\""+jmp+"\"]/ancestor::u", mtxt, null, XPathResult.ANY_TYPE, null);
		var utt = mtch.iterateNext(); 
		if ( utt ) {
			scrollToElementD(utt);
			if ( !editmode ) utt.style.backgroundColor = "#ffffcc";
			wavesurfer.seekAndCenter(utt.getAttribute('start') / wavesurfer.getDuration())
		};
	};
	
	// Add the HL region - last, so it is always on top
	currregion = wavesurfer.addRegion({
		start: 0, // time in seconds
		end: 0, // time in seconds
		color: 'rgba(255, 255, 0, 0.3)'
	});
	showtime();
	
});
wavesurfer.on('region-out', regionOut);
wavesurfer.on('region-click', regionClick);
wavesurfer.on('region-in', aligntranscription);
wavesurfer.on('loading', showload);
wavesurfer.on('audioprocess', showtime);
wavesurfer.on('region-update-end', changeregion);

var lastutt;
function aligntranscription (region, e) {
	var idx = region.id;
	
	var selutt = uttarray[idx];
	if ( !selutt ) return;

	// Highlight the region we just entered
	if ( idx != currregion.id ) {
		if ( regionarray[lastreg] ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
		lastreg = region.id;
		if ( region.id != currregion.id ) region.update({color: 'hsla(120, 100%, 50%, 0.1)'});
	};
		
	// Highlight the utterance (and unhighlight the previous one)
	if (lastutt) lastutt.style.backgroundColor = "";
	selutt.style.backgroundColor = "#ffffcc";
	lastutt = selutt;
	
	// Scroll to the utterance
	scrollToElementD(selutt);
	

};



function regionClick (uttreg, e) {
	if ( e.altKey ) {
	
		// Highlight the region itself
		pointa = uttreg.start; pointe = uttreg.end;
		currregion.update({start: pointa, end: pointe, color: 'rgba(255, 0, 0, 0.15)'});
		if ( uttreg.id == lastreg ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
		currregion.id = uttreg.id; // set the ID so we know we do now want to create a new utterance

		var selutt = uttarray[uttreg.id];
		if ( !selutt ) return;

		// Highlight the utterance (and unhighlight the previous one)
		if (lastutt) lastutt.style.backgroundColor = "";
		selutt.style.backgroundColor = "#ffffcc";
		lastutt = selutt;

		// Scroll to the utterance
		scrollToElementD(selutt);

	};
};

function changeregion (region) {
	if ( editmode && region.id != 'new' && region.drag ) {
		var utt = uttarray[region.id];
		if ( !utt ) return;
		var a = Math.floor(region.start*1000)/1000;
		var b = Math.floor(region.end*1000)/1000;
		utt.setAttribute('start', a);
		utt.setAttribute('end', b);
		regionarray[region.id].update({start: region.start, end: region.end});
	};
};

function ftime (ms) {
	var x = Math.floor(ms);
	
	var string = "000" + Math.floor((ms-x)*1000); 
	string = "." + string.substr(-3);

	var secs = x % 60; 
	string = secs + string;
	if ( x < 60 ) return string;
	x = Math.floor(x/60);
	if ( secs < 10 ) string = "0" + string;

	var mins = x % 60;  
	string = mins + ":" + string;
	if ( x < 60 ) return string;
	x = Math.floor(x/60);
	if ( mins < 10 ) string = "0" + string;

	var hours = x;  
	string = hours + ":" + string;
	
	return string;
};

function showtime(e) {	
	var now =  wavesurfer.getCurrentTime();
	document.getElementById('timeindex').innerHTML = ftime(now) + " / " + durtxt;
	if ( pointa && !pointe ) {
		currregion.update({end: now});
	};
};

function showload(e){
	document.getElementById('loading').innerHTML = "Loading wave file: " + e + "%"
	if ( e == 100 ) {
		document.getElementById('loading').innerHTML += "<p>Drawing wave form, please wait"
	}; 
};

function scrollToElementD(elm){
	var topPos = elm.offsetTop;
	mtxt.scrollTop = topPos - mtxt.offsetTop - (mtxt.offsetHeight/2) + (elm.offsetHeight/2);
}

function setspeed (factor) {
	speed = speed * factor;
	if ( speed == 0 ) speed = 1;
	var speedtxt = Math.floor(speed*100) + "%";
	wavesurfer.setPlaybackRate(speed);
	document.getElementById('speedtxt').innerHTML = speedtxt;
}

function setzoom (factor) {
	zoom = zoom * factor;
	var zoomtxt = Math.floor(zoom) + " pps";
	wavesurfer.zoom(zoom);
	document.getElementById('zoomtxt').innerHTML = zoomtxt;
}

function playpause(evt) {
	var ppbut = document.getElementById('ppbut');
	if ( wavesurfer.isPlaying() ) {
		ppbut.innerHTML = '<i class=\"material-icons\">play_arrow</i>';
	} else {
		ppbut.innerHTML = '<i class=\"material-icons\">pause</i>';
	};
	wavesurfer.playPause();
};

function sortutt() {

	// Copy the raw XML if we are looking at the raw XML
	if ( shown == "visible" ) {
		mtxt.innerHTML = aceeditor.getSession().getValue();
	};
	
	var oldsort = new Array();
	for ( uttid in uttarray ) {
		oldsort[uttid] = uttarray[uttid].getAttribute("start");
	}

	mtxt.innerHTML = "<text/>";
	
	var keys = Object.keys(oldsort);
 	keys.sort(function(a, b) {
		return oldsort[a] - oldsort[b]  
	}).forEach(function(k) {
	   var newline = document.createTextNode("\n\t");
	   mtxt.firstChild.appendChild(newline);
	   mtxt.firstChild.appendChild(uttarray[k]);
	});
	var newline = document.createTextNode("\n");
	mtxt.firstChild.appendChild(newline);
	
	// Copy back to the raw XML if we are looking at the raw XML	
	if ( shown == "visible" ) {
		aceeditor.getSession().setValue(mtxt.innerHTML);
	};

	modified = true;
};

function mtxtSelect(evt) {
	var node;
	if  ( evt.type == "click" ) node = evt.target;
	else node = window.getSelection().focusNode.parentNode;

	var pospath = ""; var sep = ""; var uttid;
	while ( node.tagName != "DIV" ) {
		time = "";
		if ( node.tagName == utttag && node.getAttribute("start") ) { 
			time = " ["+node.getAttribute("start")+"-"+node.getAttribute("end")+"]";
			uttid = node.getAttribute("id");
		};
		pospath = node.tagName + time + sep + pospath;
		sep = " > ";		
		node = node.parentNode;
	};
	document.getElementById('pospath').innerHTML = pospath;
	
	// also set HL to the time slot if there is one
	uttreg = regionarray[uttid];
	if ( uttreg ) { 
		pointa = uttreg.start; pointe = uttreg.end;
		currregion.update({start: pointa, end: pointe, color: 'rgba(255, 0, 0, 0.15)'});
		if ( uttreg.id == lastreg ) regionarray[lastreg].update({color: 'hsla(0, 0%, 0%, 0)'});
		currregion.id = uttreg.id; // set the ID so we know we do now want to create a new utterance
		if ( !wavesurfer.isPlaying() ) wavesurfer.seekAndCenter(pointa / wavesurfer.getDuration());
	};
	
};

var shown = "hidden";
function showsource() {
	if ( shown == "hidden" ) {
		aceeditor.getSession().setValue(mtxt.innerHTML);
		shown = "visible";
		document.getElementById('sourcebutton').innerHTML = "Preview";
		if ( currregion.id && currregion.id != "new" ) {
			aceeditor.find("id=\""+currregion.id+"\"");
			aceeditor.selection.selectLine();
			aceeditor.scrollToLine();
		};
	} else {
		mtxt.innerHTML = aceeditor.getSession().getValue();
		shown = "hidden";
		document.getElementById('sourcebutton').innerHTML = "Raw XML";
	};
		
	document.getElementById('sourceeditor').style.visibility = shown;
	
};

function savetrans() {

	// Copy the raw XML if we are looking at the raw XML
	if ( shown == "visible" ) {
		mtxt.innerHTML = aceeditor.getSession().getValue();
	};

	var newtrans = mtxt.innerHTML;
	modified = false;
	document.getElementById('newval').value = newtrans;
	document.getElementById('newtab').submit();	
};

var slotlist = new Array();
function toelan(elm) {
	var xmlString = "<ANNOTATION_DOCUMENT>\
	<HEADER>\
		<MEDIA_DESCRIPTOR MEDIA_URL=\""+  document.baseURI + soundfile +"\"/>\
	</HEADER>\
	<TIME_ORDER/>\
	<TIER TIER_ID=\"MAIN\"/>\
</ANNOTATION_DOCUMENT>";
	var parser = new DOMParser();
	var xmlDoc = parser.parseFromString(xmlString, "text/xml"); //important to use "text/xml"

	var timeorder = xmlDoc.getElementsByTagName("TIME_ORDER")[0]; var i=0;
	for ( var uttid in uttarray  ) {
		var utt = uttarray[uttid]; 
		var start = utt.getAttribute("start");
		if ( !slotlist[start] ) {
			var node = xmlDoc.createElement("TIME_SLOT");
			node.setAttribute("TIME_SLOT_ID", "T"+(i+1));
			node.setAttribute("TIME_VALUE", start);
			var newline = document.createTextNode("\n\t");
			timeorder.firstChild.appendChild(newline);
			timeorder.appendChild(node);
			slotlist[start] =  "T"+(i+1);
			i++;
		};
		var end = utt.getAttribute("end");
		if ( !slotlist[end] ) {
			var node = xmlDoc.createElement("TIME_SLOT");
			node.setAttribute("TIME_SLOT_ID", "T"+(i+1));
			node.setAttribute("TIME_VALUE", start);
			var newline = document.createTextNode("\n\t");
			timeorder.firstChild.appendChild(newline);
			timeorder.appendChild(node);
			slotlist[end] =  "T"+(i+1);
			i++;
		};
	};

	var tier = xmlDoc.getElementsByTagName("TIER")[0]; i = 1;
	for ( var uttid in uttarray  ) {
		var utt = uttarray[uttid]; 
		var start = slotlist[utt.getAttribute("start")];
		var end = slotlist[utt.getAttribute("end")];

		var node = xmlDoc.createElement("ANNOTATION");
		var node2 = xmlDoc.createElement("ALIGNABLE_ANNOTATION");
		node2.setAttribute("TIME_SLOT_REF1", start);
		node2.setAttribute("TIME_SLOT_REF2", end);
		var node3 = xmlDoc.createElement("ANNOTATION_VALUE");
		node3.innerHTML = utt.innerHTML;
		node2.appendChild(node3);
		node.appendChild(node2);
		var newline = document.createTextNode("\n\t");
		tier.firstChild.appendChild(newline);
		tier.appendChild(node);
	};
	
	var serializer = new XMLSerializer();
	var xmltext = serializer.serializeToString(xmlDoc);
	
 	var blob = new Blob([xmltext], {type: 'application/xml'});
 	var url = URL.createObjectURL(blob);
	elm.href = url;
	elm.download = tid.replace(".xml", ".eaf");
	// elm.click();
	// window.URL.revokeObjectURL(url);
};