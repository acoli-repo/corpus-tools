<?php
	// Line view with bounding box images
	// splits text into elements based on <lb/>
	// shows image crops, or allows editing
	// (c) Maarten Janssen, 2016
	
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;

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

		$pbxpath = "//pb";
		$pageid = $_GET['pageid'] or $pageid = $_GET['pid'];
		if ( $pageid ) { $pbxpath .= "[@id='{$pageid}']"; };
		$curr = current($ttxml->xml->xpath($pbxpath)); $pid = $curr['id'];

		$imgsrc = $curr['facs']; 
		if ( strpos($imgsrc, "http" ) === false ) $imgsrc = "Facsimile/$imgsrc";

		list($imgwidth, $imgheight, $imgtype, $imgattr) = getImageSize($imgsrc);
		$divwidth = 500;
		$divheight = $divwidth*($imgheight/$imgwidth);
		
		if ( $divheight > 300 ) {
			$divheight = 200;
			$divwidth = $divheight*($imgwidth/$imgheight);
		};
		$imgscale = $divwidth/$imgwidth;
	
		$next = current($curr->xpath("following::pb"));
		if ( $next['n'] ) { 
			$nxp = " and following::pb[@id='{$next['id']}']"; 
			$nnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&pageid={$next['id']}'>> {$next['n']}</a> ";
		} else	if ( $next['id'] ) { 
			$nxp = " and following::pb[@id='{$next['id']}']"; 
			$nnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&pageid={$next['id']}'>> [{$next['id']}]</a> ";
		};
		
		if ( !$onlylb ) $lbxpath = "//lb[preceding::pb[@id='{$curr['id']}']$nxp] | //l[preceding::pb[@id='{$curr['id']}']$nxp]"; 
		else $lbxpath = "//lb[preceding::pb[@id='{$curr['id']}']$nxp]"; 
	
		$prev = current($curr->xpath("preceding::pb"));
		if ( $prev['n'] ) { 
			$bnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&pageid={$prev['id']}'>{$prev['n']} <</a> ";
		} else	if ( $prev['id'] ) { 
			$bnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&pageid={$prev['id']}'>[{$prev['id']}] <</a> ";
		};

		$folionr = $curr['n'] or $folionr = $curr['id'];

		# Build the page navigation
		$maintext .= "<table style='width: 100%'><tr> 
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>{%Folio} $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
						";

		if ( $act == "edit" ) {
		
			check_login();
		$divwidth = 500;
		$divheight = $divwidth*($imgheight/$imgwidth);
		
			// A float makes that the items are not visible at the same time, while fixed would not work well for 2-up 
			// and needs a different logic (scroll offset)
			$maintext .= "
				<div id=\"facsimg\" style='position: fixed; right: 10px; top: 50px; width: {$divwidth}px; height: {$divheight}px; background-image: url($imgsrc); background-size: 100% 100%;'>
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
	
			if (  $debug ) {
				$maintext .= "<p>Page selection: $pbxpath";
				$maintext .= "<p>Line selection: $lbxpath";
			};
	
			foreach ( $ttxml->xml->xpath($lbxpath) as $lb ) {
				$nr++;
		
				// Parse the actual line
				$lbxml = $lb->asXML(); $linexml = htmlentities($lbxml);
				$linenr = $lb['n'] or $linenr = "[$nr]";

				if ( $lb->getName() == "l" ) {
					$linetxt = $lb->asXML();
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

			<hr><p>
				<a href='index.php?action=file&cid={$ttxml->fileid}'>{%Text view}</a>
				&bull; <a href='index.php?action=lineview&cid={$ttxml->fileid}'>{%Line view}</a>
				";
				
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

			$maintext .= "
				<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				<table id=mtxt>";

			foreach ( $ttxml->xml->xpath($lbxpath) as $lb ) {
				$bb = explode ( " ", $lb['bbox'] );
				$cropwidth = $bb[2]-$bb[0];
				if ( $cropwidth ) { $maxwidth = max($cropwidth, $maxwidth); };
			}; 
			if ( $maxwidth ) { $imgscale = (700/$maxwidth); };
							
			foreach ( $ttxml->xml->xpath($lbxpath) as $lb ) {
				$nr++;
		
				// Parse the actual line
				$lbxml = $lb->asXML(); $linexml = htmlentities($lbxml);
				$linenr = $lb['n'] or $linenr = "[$nr]"; $lineimg = "";
				
				// Get the line text 
				if ( $lb->getName() == "l" ) {
					$linetxt = $lb->asXML();
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
				
				// If there are bounding box data, proceed to crop
				if ( $lb['bbox'] ) {
			
					if ( $imgsrc ) { 
						// Get the bounding box data for this line
						$bb = explode ( " ", $lb['bbox'] );
						$cropwidth = $bb[2]-$bb[0];
						$cropheight = $bb[3]-$bb[1]; 
			
						list($imgwidth, $imgheight, $imgtype, $imgattr) = getImageSize($imgsrc);
						if ( $imgscale ) {							
							$divwidth = $cropwidth*$imgscale;
							$divheight = $divwidth*($cropheight/$cropwidth);
							$setwidth = $imgscale*$imgwidth;
							$setheight = $imgscale*$imgheight;
							$topoffset = $bb[1]*$imgscale;
							$leftoffset = $bb[0]*$imgscale;
						} else {
							// Get the size of the original image and create crop measurements
							$divwidth = 600;
							$divheight = $divwidth*($cropheight/$cropwidth);

							if ( $divheight > 300  ) {
								$divheight = 100;
								$divwidth = $divheight*($cropwidth/$cropheight);
							};
						
							$imgscale = $divwidth/$cropwidth;
							$setwidth = $imgscale*$imgwidth;
							$setheight = $imgscale*$imgheight;
							$topoffset = $bb[1]*$imgscale;
							$leftoffset = $bb[0]*$imgscale;
						};
					};
						
					// Add the data of the line
					$lineimg = "<div style='width: {$divwidth}px; height: {$divheight}px; overflow: hidden; margin: 3px;'>
						<img style='width: {$setwidth}px; height: {$setheight}px; margin-top: -{$topoffset}px; margin-left: -{$leftoffset}px;' src='$imgsrc'/>
						</div>";
				};
				$maintext .= "\n<tr><th title=\"{$lb['id']}\">$linenr<td>$lineimg<div style='padding: 3px; background-color: #eeeeee;'>$linetxt</div>";
			};
			$maintext .= "</table>
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
							</script>

			<hr><p><a href='index.php?action=file&cid={$_GET['cid']}&pageid={$curr['id']}'>{%Text view}</a>";

			if ( $username ) 
				$maintext .= " &bull;
					<a href='index.php?action=$action&act=edit&cid={$ttxml->fileid}&pid={$curr['id']}'>{%Edit lines}</a>
					";
		
		};
	};	
?>