<?php
	// Line view with bounding box images
	// splits text into non-XML elements based on <lb/>
	// and adds crops of facsimile images
	// provisional - likely to be integrated into file.php
	// (c) Maarten Janssen, 2016
	
	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$fileid = $ttxml->fileid;

	$maintext .= "<h2>{%Annotated Lines}</h2>";
	$maintext .= "<h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();

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
		if ( preg_match("/ $key=/", $editxml) ) {
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
	foreach ( $ttxml->xml->xpath("//lb") as $lb ) {
		$nr++;
		
		// Parse the actual line
		$lbxml = $lb->asXML(); $linexml = htmlentities($lbxml);
		$linenr = $lb['n'] or $linenr = "[$nr]";

		// Get the line text 
		$linetxt = ""; $lineimg = "";
		$linepos = strpos($ttxml->rawtext, $lbxml);
		$nextlb = strpos($ttxml->rawtext, "<lb", $linepos+1);
		$nextpb = strpos($ttxml->rawtext, "<pb", $linepos+1);
		$lineend = min($nextlb, $nextpb);
		if ( !$lineend ) $lineend = strpos($ttxml->rawtext, "</text", $linepos+1);
		$linetxt = substr($ttxml->rawtext, $linepos, $lineend-$linepos);

		// If there are bounding box data, proceed to crop
		if ( $lb['bbox'] ) {
			
			// Get the facsimile image
			$invlinepos = $linepos - strlen($ttxml->rawtext);
			$pbpos = strrpos($ttxml->rawtext, "<pb", $invlinepos);
			$pbepos = strpos($ttxml->rawtext, ">", $pbpos);
			$pb = substr($ttxml->rawtext, $pbpos, $pbepos-$pbpos);
			if ( preg_match ( "/facs=\"([^\"]+)\"/", $pb, $matches ) ) { $imgsrc = $matches[1]; }
			if ( strpos($imgsrc, "http" ) === false ) $imgsrc = "Facsimile/$imgsrc";
			
			if ( $imgsrc ) { 
				// Get the bounding box data for this line
				$bb = explode ( " ", $lb['bbox'] );
				$cropwidth = $bb[2]-$bb[0];
				$cropheight = $bb[3]-$bb[1]; 
			
				// Get the size of the original image and create crop measurements
				list($imgwidth, $imgheight, $imgtype, $imgattr) = getImageSize($imgsrc);
				$divwidth = 600;
				$divheight = $divwidth*($cropheight/$cropwidth);
				$imgscale = $divwidth/$cropwidth;
				$setwidth = $imgscale*$imgwidth;
				$setheight = $imgscale*$imgheight;
				$topoffset = $bb[1]*$imgscale;
				$leftoffset = $bb[0]*$imgscale;
			};
						
			// Add the data of the line
			$lineimg = "<div style='width: {$divwidth}px; height: {$divheight}px; overflow: hidden'>
				<img style='width: {$setwidth}px; height: {$setheight}px; margin-top: -{$topoffset}px; margin-left: -{$leftoffset}px;' src='$imgsrc'/>
				</div>";
		};
		$maintext .= "\n<tr><th title=\"{$lb['id']}\">$linenr<td>$lineimg$linetxt";
	};
	$maintext .= "</table>
					<script language=Javascript src='$jsurl/tokedit.js'></script>
					<script language=Javascript src='$jsurl/tokview.js'></script>
					<script language=Javascript>
						var username = '$username';
						var formdef = $jsonforms;
						var orgtoks = new Object();
						var attributelist = Array($attlisttxt);
						$attnamelist
						formify(); 
						var orgXML = document.getElementById('mtxt').innerHTML;
						setForm('pform');
					</script>

	<hr><p><a href='index.php?action=file&cid={$_GET['cid']}'>{%Back to text view}</a>";
	
?>