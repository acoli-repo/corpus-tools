<?php
	// Script to display bounding boxes 
	// Indicates where in the @facs <tok> <l> etc. is located
	// (c) Maarten Janssen, 2015


	if ( $act == "save" ) {
	
		saveMyXML($_POST['rawxmlta'], $_POST['fileid']);
		print "<p>The file has been modified - reloading
			<script language=Javascript>top.location='index.php?action=$action&cid={$_POST['fileid']}&pageid={$_GET['pageid']}';</script>";		
	
	} else {
	
		require ("$ttroot/common/Sources/ttxml.php");
	
		$ttxml = new TTXML($cid, 0, 'keepns');
	
		$maintext .= "
			<h1>Region Editor</h1>
			<h2>".$ttxml->title()."</h2>"; 

		# Display the teiHeader data as a table
		$maintext .= $ttxml->tableheader(); 
		$fileid = $ttxml->fileid;
	
		$fullwidth = 600;

			$editxml = $ttxml->rawtext;

		$highl = $_GET['tid'] or $highl = $_GET['jmp'];
		$tmp = explode(" ", $highl);
		$tid = $tmp[0];	

		if ( 1==1 ) { # Grab the page
			$pbelm = "pb";
			$titelm = "Page";
			$pbtype = "pb";
			$pbsel = "&pbtype={$_GET['pbtype']}";

			if ( $_GET['pageid'] ) {
				$pb = "<$pbelm id=\"{$_GET['pageid']}\"";
				$pidx = strpos($editxml, $pb);
			} else if ( $tid ) {
				$tokidx = strpos($editxml, "id=\"$tid\"");
				$pb = "<$pbelm";
				$pidx = rstrpos($editxml, $pb, $tokidx);
			} else {
				$pb = "<$pbelm";
				$pidx = strpos($editxml, $pb);
			};
		
			if ( !$pidx || $pidx == -1 ) { 
				# When @n is not the first attribute, we cannot use strpos - try regexp instead (slower)
				if ( $_GET['pageid'] ) {
					preg_match("/<$pbelm [^>]*id=\"{$_GET['pageid']}\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
				} else {
					preg_match("/<$pbelm [^>]*n=\"{$_GET['page']}\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
				};
				$pidx = $matches[0][1];
			};
			if ( !$pidx || $pidx == -1 ) { fatal ("No such $pbelm in XML: {$_GET['page']} {$_GET['pageid']}"); };

		
			# Find the next page/chapter (for navigation, and to cut off editXML)
			$nidx = strpos($editxml, "<$pbelm", $pidx+1); 
			if ( !$nidx || $nidx == -1 ) { 
				$nidx = strpos($editxml, "</text", $pidx+1); $nnav = "";
			} else {
				$nidy = strpos($editxml, ">", $nidx); 
				$tmp = substr($editxml, $nidx, $nidy-$nidx ); 
			 
				if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $npid = $matches[1]; };
				if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $npag = $matches[1]; };
			
				if ( $npid ) $nnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$npid&pbtype={$_GET['pbtype']}'>> $npag</a>";
				else $nnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$npag'>> $npag</a>";
			};
		
			# Find the previous page/chapter (for navigation)
			$bidx = rstrpos($editxml, "<$pbelm ", $pidx-1); 
			if ( !$bidx || $bidx == -1 ) { 
				$bidx = strpos($editxml, "<text", 0); $bnav = "<a href='index.php?action=pages&cid=$fileid$pbsel'>{%index}</a>";
			} else {
				$tmp = substr($editxml, $bidx, 150 ); 
				if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $bpid = $matches[1]; };
				if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $bpag = $matches[1]; } else { $bpag = ""; };
				if ( $bpid  )  $bnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$bpid$pbsel'>$bpag <</a> ";
				else $bnav = "<a href='index.php?action=$action&cid=$fileid&page=$bpag'>$bpag <</a>";
				if ( !$firstpage ) { $bnav = "<a href='index.php?action=pages&cid=$fileid$pbsel'>{%index}</a> &nbsp; $bnav"; };
			};

			// when pbelm != pb, grab the <pb/> from just before the milestone
			if ( $pb && $pbelm != "pb") {
				if ( strpos($editxml, "<tok", $pidx) < strpos($editxml, "<pb", $pidx) ) {
					$bpb1 = rstrpos($editxml, "<pb ", $pidx-1); 
					$bpb2 = strpos($editxml, ">", $bpb1);
					$len = ($bpb2-$bpb1)+1;
					$facspb = substr($editxml, $bpb1, $len); 
				};
			};		
		
			$span = $nidx-$pidx;
			$editxml = $facspb.substr($editxml, $pidx, $span); 

			$editxml = preg_replace("/<lb([^>]+)\/>/", "<lb\\1></lb>", $editxml);
		
			if ( $_GET['page'] ) $folionr = $_GET['page']; // deal with pageid
			else if ( $_GET['pageid'] ) {
				if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"[^>]*id=\"{$_GET['pageid']}\"/", $editxml, $matches ) 
					|| preg_match("/<$pbelm [^>]*id=\"{$_GET['pageid']}\"[^>]*n=\"([^\"]+)\"/", $editxml, $matches ) ) 
						$folionr = $matches[1];
			} else if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"/", $tmp, $matches ) ) {
				$folionr = $matches[1]; 
			};

			if ( preg_match("/<$pbelm [^>]*facs=\"(.*?)\"/", $editxml, $matches ) ) {
				$img = $matches[1];
				if ( !preg_match("/^(http|\/)/", $img) ) $img = "Facsimile/$img";
			};
		
			if ( $pbelm == "pb" ) $foliotxt = "{%Folio}";
		
			# Build the page navigation
			$pagenav = "<table style='width: 100%'><tr> <!-- /<$pbelm [^>]*id=\"{$_GET['pageid']}\"[^>]*n=\"(.*?)\"/ -->
							<td style='width: 33%' align=left>$bnav
							<td style='width: 33%' align=center>$foliotxt $folionr
							<td style='width: 33%' align=right>$nnav
							</table>
							<hr> 
							";
		};

			#Build the view options	
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
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
				&& !$settings['xmlfile']['pattributes']['forms'][$showform]['subtract']
				) { $showform = $bestform;};
	
		$rawxml = $ttxml->rawtext;
		if ( $tid ) $hltok = "tokhl('$highl', true);";

			$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";

		$maintext .= "
		<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 300;'></div>
		$pagenav
		<div style='width: 100%; height: 50px;'>
			<input type=button onClick=\"showregions('p,tei:head,h1,h2,h3,h4',255,0,0)\" value='Paragraphs' style='background-color: rgba(255,0,0,0.4);'/>
			<input type=button onClick=\"showregions('lb,l',0,0,255)\" value='Lines'  style='background-color: rgba(0,0,255,0.4);'/>
			<input type=button onClick=\"showregions('tok,gtok',0,255,0)\" value='Words'  style='background-color: rgba(0,255,0,0.4);'/>
			<input type=button style='float: right;' onClick=\"scale(1.2)\" value='Zoom in'/>
			<input type=button style='float: right;' onClick=\"scale(0.8)\" value='Zoom out'/>
			<input type=button style='float: right;' onClick=\"savexml()\" value='Save'/>
		<p id='autoplace' style='display: none;'>Unplaced element are shown on the bottom of the screen - <a onclick=\"autoplace();\">auto distribute</a></p>	
		</div>
		<hr>
		<img id=facs src='$img' style='display: none;'/>
		<div id=mtxt style='display: none;'>$editxml</div>
		<form action='index.php?action=$action&act=save&pageid={$_GET['pageid']}' method=post id=xmlsave name=xmlsave>
		<input type=hidden name=fileid value='$ttxml->filename'>
		<textarea style='display: none;' name='rawxmlta' id='rawxmlta'></textarea>
		</form>
		<div id=imgdiv style=\"position: relative; float: left; border: 1px solid #660000; background-image: url('$img'); background-size: cover; width: 100%;\">
		</div>
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
			var orgXML = document.getElementById('mtxt').innerHTML;

			var rawxml = `$rawxml`;
			parser = new DOMParser();
			xmlDoc = parser.parseFromString(rawxml,\"text/xml\");
		
			imgdiv.style.height = imgdiv.offsetWidth*(imgfacs.naturalHeight/imgfacs.naturalWidth) + 'px';

			$settingsdefs
			var attributelist = Array($attlisttxt);
		</script>
		<script language=Javascript src='http://code.interactjs.io/v1.3.0/interact.min.js'></script>
		<script language=Javascript src='Scripts/regionedit.js'></script>
		<br style='clear: both; margin-top: 10px; margin-top: 10px;'/>

		";
	
	};
	
?>