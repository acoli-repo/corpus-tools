<?php
	// Line view with bounding box images
	// splits text into elements based on <lb/>
	// shows image crops, or allows editing
	// (c) Maarten Janssen, 2016
	
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;

	$highl = $_GET['tid'] or $highl = $_GET['jmp'];
	$tmp = explode(" ", $highl);
	$tid = $tmp[0];	

	if ( $settings['xmlfile']['basedirection'] ) $morestyle .= "direction: {$settings['xmlfile']['basedirection']}";

	if ( $act == "save" ) {
		foreach ( $_POST['bbox'] as $key => $bbox ) {
			$elm = current($ttxml->xml->xpath("//lb[@id='$key'] | //l[@id='$key']"));
			if ( $elm ) {
				if ( $bbox ) { 
					$elm['bbox'] = $bbox; 
					print "<p>$key: $bbox => ".htmlentities($elm->asXML());			
				};
			} else {
				print "<p>$key: lb not found (failing to save)";			
			};
		};
		$ttxml->save(); 
		header("location:index.php?action=$action&cid={$ttxml->fileid}&pageid={$_POST['pid']}");
		exit;

	} else {
	
		if ( $settings['xmlfile']['l'] == "nolb" || $_GET['elm'] == "lb" ) $onlylb = 1;
	
		$maintext .= "<h2>{%Facsimile Lines}</h2>";
		$maintext .= "<h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();

		$editxml = $ttxml->page();

		# Build the page navigation
		$maintext .= $ttxml->pagenav;

		if ( $act == "edit" ) {
		
			check_login();
		$divwidth = 500;
		$divheight = 200;
		
			// A float makes that the items are not visible at the same time, while fixed would not work well for 2-up 
			// and needs a different logic (scroll offset)
			$maintext .= "
				<div id=\"facsimg\" style='position: fixed; right: 10px; top: 50px; width: {$divwidth}px; height: {$divheight}px; background-image: url($ttxml->facsimg); background-size: 100% 100%;'>
				<div id='hlbar' class='hlbar' style='display: none;'></div>
				</div>
				<script language=Javascript src=\"$jsurl/bbox.js\"></script>
				<script language=Javascript>
					var imgscale = $imgscale;
					var imgwidth = $imgwidth;
				</script>
				<form action='index.php?action=$action&act=save' method=post>
				<input type=hidden name=id value=\"{$ttxml->fileid}\">
				<input type=hidden name=pid value=\"{$pid}\">
				<table id=mtxt>";
	
			if ( $debug ) {
				$maintext .= "<p>Page selection: $pbxpath";
				$maintext .= "<p>Line selection: $lbxpath";
			};
	
			foreach ( $ttxml->xml->xpath($lbxpath) as $lb ) {
				$nr++;
		
				// Parse the actual line
				$lbxml = $lb->asXML(); $linexml = htmlentities($lbxml);
				$linenr = $lb['n'] or $linenr = "[$nr]";

				if ( $lb->getName() == "l" ) {
					$linetxt = makexml($lb);
					$linetxt = preg_replace("/<lb .*/smi", "", $linetxt);
				} else {
					$linetxt = ""; $lineimg = "";
					$linepos = strpos($ttxml->rawtext, $lbxml);
					$nextlb = strpos($ttxml->rawtext, "<lb", $linepos+1);
					$nextpb = strpos($ttxml->rawtext, "<pb", $linepos+1);
					$lineend = min($nextlb, $nextpb) or $lineend = $nextlb or $lineend = $nextpb;
					if ( !$lineend ) $lineend = strpos($ttxml->rawtext, "</text", $linepos+1);
					$linetxt = substr($ttxml->rawtext, $linepos, $lineend-$linepos);
					if ( $onlylb ) {
						$linetxt = preg_replace("/<l [^>]+>/smi", "", $linetxt);
						$linetxt = preg_replace("/<lg[^>]*>/smi", "", $linetxt);
						$linetxt = preg_replace("/<p [^>]+>/smi", "", $linetxt);
					} else $linetxt = preg_replace("/<l .*/smi", "", $linetxt);
				};
				
				$lbi++;
				$bbox = "<br><input name=\"bbox[{$lb['id']}]\" size=20 value=\"{$lb['bbox']}\" id=\"lb-$lbi\" onChange=\"updatehl(this);\" onFocus=\"selecthl(this);\">";
				$maintext .= "\n<tr><th title=\"{$lb['id']}\">$linenr<td>$linetxt$bbox";

			};
			$maintext .= "</table>
				<p><input type=submit value='Save'>
				</form>

			<hr><p><a href='index.php?action=lineview&cid={$ttxml->fileid}'>{%Line view}</a>
				";
			$maintext .= $ttxml->viewswitch(false);
				
		} else {
		
			$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
			$jsontrans = array2json($settings['transliteration']);
			#Build the view options	
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
				$formcol = $item['color'];
				# Only show forms that are not admin-only
				if ( $username || !$item['admin'] ) {	
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
				};
			};
			foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
				$val = $item['display'];
				if ( preg_match("/ $key=/", $editxml) || 1==1 ) { // TODO: should this see if the tag occurs? 
					if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
					if ( !$item['admin'] || $username ) {
						if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
						$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
						$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
						$pcolor = $item['color'];
						$tagstxt .= " <button id='tbt-$key' style='background-color: #$bc; color: $pcolor;$bgcol' onClick=\"toggletag('$key')\">{%$val}</button>";
					};
				} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
					unset($labarray[$akey]);
				};
			};

			$maintext .= "<img style='display: none;' id='facsimg'/>";
			$maintext .= "
				<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				<table id=mtxt style='width: 100%;'>";

			# Display all the <lb> and/or <l> in this page
			if ( $onlylb ) $lb = "<lb "; else $lb = "<lb? ";
			preg_match_all("/$lb/", $editxml, $matches, PREG_OFFSET_CAPTURE);
			$linecnt = 1;
			foreach ( $matches[0] as $i => $tmp ) {
				$cpos = $tmp[1]; $npos = $matches[0][$i+1][1];
				if ( !$npos ) { $npos = strlen($editxml); };
				$linetxt = substr($editxml, $cpos, $npos-$cpos);

				if ( preg_match("/^[^>]+id=\"([^\"]+)\"/", $linetxt, $matches2 ) ) { $lineid = $matches2[1]; } else $lineid = "";
				if ( preg_match("/^[^>]+n=\"([^\"]+)\"/", $linetxt, $matches2 ) ) { $linenr = $matches2[1]; } else $linenr = "[".$linecnt++."]";
				
				if ( preg_match("/^[^>]+bbox=\"([^\"]+)\"/", $linetxt, $matches2 ) ) {
					$bbox = $matches2[1];
					$bb = explode ( " ", $bbox );
					$divheight = $bb[3] - $bb[1];
					// Add the data of the line
					$lineimg = "\n
					<div bbox='$bbox' class='linediv' id='reg_$lineid' tid='$lineid' style='width: 100%; height: {$divheight}px; background-image: url(\"$ttxml->facsimg\"); background-size: cover;'></div>
					";

				} else $lineimg = "";
				if ( $username ) $linenr .= "<br><a href='index.php?action=elmedit&tid=$lineid&cid=$ttxml->fileid' class=adminpart>edit</a>";
				$maintext .= "\n<tr><th title=\"$lineid\">$linenr<td>$lineimg<div style='padding: 3px; margin-top: 5px; background-color: #eeeeee; $morestyle'>$linetxt</div>";
			};  

			if ( $highl ) $hltok = "highlight('$highl', true);";
			
			$maintext .= "</table>
							<script language=Javascript>
								var facsimg = document.getElementById('facsimg');
								var linedivs = document.getElementsByClassName('linediv');
								function resizelb ( ) {
									for ( var i=0; i<linedivs.length; i++ ) {
										var linediv = linedivs[i];
										var bbox = linediv.getAttribute('bbox').split(' ');
										// Never scale more than 50% up
										var imgscale  = Math.min(1.2, linediv.offsetWidth/(bbox[2]-bbox[0]));

										var biw = facsimg.naturalWidth*imgscale;
										var bih = biw*(facsimg.naturalHeight/facsimg.naturalWidth);
										var bix = bbox[0]*imgscale;
										var biy = bbox[1]*imgscale;

										linediv.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
										linediv.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
										linediv.style['background-size'] = biw+'px '+bih+'px';
										linediv.style['background-position'] = '-'+bix+'px -'+biy+'px';

									};
								};
								facsimg.src='$ttxml->facsimg';
								facsimg.onload=resizelb;
							</script>
							<script language=Javascript src='$jsurl/tokedit.js'></script>
							<script language=Javascript src='$jsurl/tokview.js'></script>
							<script language=Javascript>
								var username = '$username';
								var formdef = $jsonforms;
								var tid = '{$ttxml->fileid}';
								var orgtoks = new Object();
								var attributelist = Array($attlisttxt);
								$attnamelist
								formify(); 
								var orgXML = document.getElementById('mtxt').innerHTML;
								setForm('pform');
								var jmps = '$highl'; var jmpid;
								if ( jmps ) { 
									var jmpar = jmps.split(' ');
									for (var i = 0; i < jmpar.length; i++) {
										var jmpid = jmpar[i];
										highlight(jmpid, '$hlcol');
									};
									element = document.getElementById(jmpar[0])
									alignWithTop = true;
									if ( typeof(element) != null ) { element.scrollIntoView(alignWithTop); };
								};
							</script>

			<hr><p>";
			$maintext .= $ttxml->viewswitch();

			if ( $username ) 
				$maintext .= " &bull;
					<a href='index.php?action=lineedit&cid={$ttxml->fileid}&pageid={$curr['id']}' class=adminpart>Transcribe lines</a>
					"; // Used to be $action&act=edit, but regionedit is nicer
		
		};
	};	
?>