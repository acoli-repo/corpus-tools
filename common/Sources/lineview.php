<?php
	// Line view with bounding box images
	// splits text into non-XML elements based on <lb/>
	// and adds crops of facsimile images
	// (c) Maarten Janssen, 2016
	
	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$fileid = $ttxml->fileid;

	$maintext .= "<h2>{%Annotated Lines}</h2>";
	$maintext .= "<h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();

	$maintext .= "<table id=mtxt>";
	foreach ( $ttxml->xml->xpath("//lb") as $lb ) {
	
		// Parse the actual line
		$lbxml = $lb->asXML(); $linexml = htmlentities($lbxml);
		$linenr = $lb['n'] or $linenr = "[{$lib['id']}]";

		// Get the line text 
		$linetxt = ""; $lineimg = "";
		$linepos = strpos($ttxml->rawtext, $lbxml);
		$lineend = strpos($ttxml->rawtext, "<lb", $linepos+1);
		if ( !$lineend ) $lineend = strpos($ttxml->rawtext, "<pb", $linepos+1);
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
		$maintext .= "\n<tr><th>$linenr<td>$lineimg$linetxt";
	};
	$maintext .= "</table>";
	
?>