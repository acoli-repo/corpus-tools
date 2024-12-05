<?php
	// Script to display bounding boxes 
	// Indicates where in the @facs <tok> <l> etc. is located
	// (c) Maarten Janssen, 2015
	
	require ("$ttroot/common/Sources/ttxml.php");
	
	$ttxml = new TTXML();
	
	$maintext .= "
		<h2>{%Facsimile view}</h2>
		<h1>".$ttxml->title()."</h1>";

	# Display the teiHeader data as a table
	$maintext .= $ttxml->tableheader(); 
	$fileid = $ttxml->fileid;
	
	$fullwidth = 600;

		$editxml = $ttxml->rawtext;

	$highl = $_GET['tid'] or $highl = $_GET['jmp'];
	$tmp = explode(" ", $highl);
	$tid = $tmp[0];	

	$editxml = $ttxml->page($_GET['pageid'], $tid);

		#Build the view options	
		foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
			$formcol = $item['color'];
			# Only show forms that are not admin-only
			if ( $username || !$item['admin'] ) {
				if ( !$bestform ) $bestform = $key; 
				if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
				$ikey = $item['inherit'];
				if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
					$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
					$fbc++;
				};
				if ( $key != "pform" ) { 
					if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
				};
			} else if ( $showform == $key ) $showform = $bestform;
		};
		# Check whether we HAVE the form to show - or switch back
		if ( !strstr($editxml, " $showform=") 
			&& !getset("xmlfile/pattributes/forms/$showform/subtract")
			) { $showform = $bestform;};
	
	if ( $tid ) $hltok = "tokhl('$highl', true);";

		$settingsdefs .= "\n\t\tvar formdef = ".array2json(getset('xmlfile/pattributes/forms', array())).";";

	$maintext .= "
	<script language=Javascript>var imgloaded = 0;</script>
	<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 300;'></div>
	$ttxml->pagenav
	<img id=facs src='$ttxml->facsimg' style='display: none;' onload='imgloaded=1;'/>
	<div id=imgdiv style=\"position: relative; float: left; border: 1px solid #660000; background-image: url('$ttxml->facsimg'); background-size: cover; width: 100%;\">
	<div id=mtxt $editxml</div>
	</div>
	<div style='display: block; position: inline; text-align: right; z-index: 600;'>
		<!-- <input type=button onClick='togglefs()' value='Fullscreen'/> 
		<input type=button onClick='scale(1.2)' value='{%Larger}'/>
		<br><input type=button onClick='scale(0.8)' value='{%Smaller}'/>
		-->
	</div>
	<script language=Javascript src='$jsurl/tokedit.js'></script>
	<script language=Javascript src='$jsurl/tokview.js'></script>
	<style>
	#mtxt tok:hover { background-color: rgba(220,220,0,0.4); text-shadow: none; }
	#mtxt tok { color: rgba(0,0,0,0); cursor: pointer; }
	#mtxt { color: rgba(0,0,0,0); }
	</style>
	<script language=Javascript>
		var facshown = 1; var bboxshown = 1;
		var imgdiv = document.getElementById('imgdiv');
		var imgfacs = document.getElementById('facs');
		var facsimg = imgdiv.style.backgroundImage; 
		var username = '$username';
		var orgtoks = new Object();
		formify(); 
		var orgXML = document.getElementById('mtxt').innerHTML;
		imgdiv.style.height = imgdiv.offsetWidth*(imgfacs.naturalHeight/imgfacs.naturalWidth) + 'px';
		$settingsdefs
		var attributelist = Array($attlisttxt);
		function sleep(ms) {
		  return new Promise(resolve => setTimeout(resolve, ms));
		}		
		async function tokhl ( tid, jump=false ) { 	
			var list = tid.split(' ');
			// Wait until the image is loaded
			while ( !imgloaded ) { await sleep(10); };
			for (i = 0; i < list.length; i++) {
				selid = list[i]; 
				var seltok = document.getElementById(selid);
				seltok.style.backgroundColor = 'rgba(255,220,4,0.3)';
			};
			if ( jump ) {
				document.getElementById(list[0]).scrollIntoView(true);
			};
		};
		var tokinfo = document.getElementById('tokinfo');
		var tid = '$fileid';
		var imgscale = imgdiv.offsetWidth/imgfacs.naturalWidth;
		var i; 

		var toks = document.getElementsByTagName('tok');
		for (i = 0; i < toks.length; i++) {
			tok = toks[i]; 
			if ( !tok ) { continue; };
			placeelm(tok);
		}; 
		var gtoks = document.getElementsByTagName('gtok');
		for (i = 0; i < gtoks.length; i++) {
			gtok = gtoks[i]; 
			// Copy all the attributs from the <tok>
			if ( !gtok ) { continue; };
			placeelm(gtok);
		};
		$hltok
		async function placeelm ( tok ) {
			var tmp = tok.getAttribute('bbox');
			while ( !imgloaded ) { await sleep(10); };
			if ( !tmp ) { 
				// Hide the token if it has no bbox
				tok.style.display = 'none';
				return -1; 
			};
			var bbox = tmp.split(' ');
			tok.style.position = 'absolute';
			tok.style.overflow = 'hidden';
			tok.style.zIndex = 100;
			tok.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
			tok.style.width = (bbox[2]-bbox[0])*imgscale  + 'px';
			tok.style.left = bbox[0]*imgscale  + 'px';
			tok.style.top = (bbox[1]-4)*imgscale  + 'px';
			tok.style.backgroundColor = 'rgba(0,0,0,0)';
			tok.style.color = 'rgba(0,0,0,0)';
			if ( username && !tok.getAttribute('form') ) { tok.setAttribute('form', tok.innerText); }; // Always show the form
		};
		
		function togglefs () {
			var imgdiv = document.getElementById('imgdiv');
			// Set DIV to full browser screen
			if (document.documentElement.requestFullScreen) {  
			  document.documentElement.requestFullScreen();  
			} else if (document.documentElement.mozRequestFullScreen) {  
			  document.documentElement.mozRequestFullScreen();  
			} else if (document.documentElement.webkitRequestFullScreen) {  
			  document.documentElement.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);  
			};  
			imgdiv.style['position'] = 'fixed';
			imgdiv.style['left'] = '50%';
			imgdiv.style['top'] = 0;
			imgdiv.style['z-index'] = '100';
			if ( imgfacs.naturalHeight > imgfacs.naturalWidth ) {
				imgdiv.height = screen.height;
				imgdiv.width = imgdiv.height*(imgfacs.naturalWidth/imgfacs.naturalHeight) + 'px';
			} else {
				imgdiv.width = screen.width;
				imgdiv.height = imgdiv.width*(imgfacs.naturalHeight/imgfacs.naturalWidth) + 'px';
			};
			imgdiv.style['background-size'] = 'cover';
		};
		var scl = 1;
		function scale(pls) {
			scl = scl * pls;
			imgdiv.style.transform = 'scale('+scl+','+scl+')';
			imgdiv.style['transform-origin'] = 'left top';
		};
		document.body.onkeydown = function(e) { 
			if ( e.key == 'ArrowLeft' ) {
				var np = document.getElementById('prevpag');
				console.log(np);
				if ( np ) np.click();
			} else if ( e.key == 'ArrowRight' ) {
				var np = document.getElementById('nextpag');
				console.log(np);
				if ( np ) np.click();
			} ;
		}; 
	</script>
	<br style='clear: both; margin-top: 10px; margin-top: 10px;'/>
	<hr>
	";
	$maintext .= $ttxml->viewswitch();
	
?>