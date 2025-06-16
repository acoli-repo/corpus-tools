<?php

	ini_set('display_errors', '1');

	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 

	$maintext .= "<h1>Manuscript Line Transcriber</h1>
	
		<div id=svg>$svg</svg>";
		
	if ( !$ttxml->xml ) {
	
		$maintext .= "<p>Please select a file first";

		$flist = glob("xmlfiles/*.xml");
		foreach ( $flist as $fn ) {
			$fn = preg_replace("/.*\//", "", $fn);
			$maintext .= "<p><a href='index.php?action=$action&cid=$fn'>$fn</a>";
		};	

	} else if ( $act == "linesave") {

		$lbid = $_POST['lbid'];	
		$pbid = $_POST['pbid'];	
		$lb = current($ttxml->xpath("//lb[@id=\"$lbid\"]"));
		$lb['text'] = $_POST['text'];
		print showxml($lb); 
		$ttxml->save();
		
		print "Transcription saved - reloading
			<script>top.location='index.php?action=$action&cid=$ttxml->fileid&pbid=$pbid&lbid=$lbid'</script>";
		exit;
		
	} else if ( $act == "unprov") {
	
		if ( $ttxml->xpath("//tok") ) $tokenized = 1;
		foreach ( $ttxml->xpath("//lb[@text]") as $lb ) {
			$lbid = $lb['id'];
			$text = $lb['text'];
			$editxml = $ttxml->page($lbid, array("elm"=>"lb"));	
			$ctest = preg_replace("/<\/?lb[^<>]*>/", "", $editxml);
			print "<p>$lbid: $text <div style='display: inline;' id=mtxt>$editxml</mtxt>";
			$prnt = current($lb->xpath(".."));
			if ( trim($ctest) == "" ) {
			   $dom= dom_import_simplexml($lb);
			   $next =  $dom->nextSibling;
				if ( $tokenized ) {
					foreach ( explode(" ", $text ) as $word ) {
					   preg_match("/^(\pP*)(.*?)(\pP*)$/", $word, $matches );
					   $prep = $matches[1];
					   $word = $matches[2]; 
					   $postp = $matches[3];
					   for ( $i=0; $i<mb_strlen($prep); $i++ ) {
					   	   $punct = mb_substr($prep,$i,1);
					   	   if ( $punct ) {
							   $tok = $dom->ownerDocument->createElement('tok', $punct);
							   $dom->parentNode->insertBefore($tok, $next);
							};
					   };
					   if ( $word ) {
						   $tok = $dom->ownerDocument->createElement('tok', $word);
						   $dom->parentNode->insertBefore($tok, $next);
					   };
					   for ( $i=0; $i<mb_strlen($postp); $i++ ) {
					   	   $punct = mb_substr($prep,$i,1);
					   	   if ( $punct ) {
							   $tok = $dom->ownerDocument->createElement('tok', $punct);
							   $dom->parentNode->insertBefore($tok, $next);
							};
					   };
					   $space = $dom->ownerDocument->createTextNode(" ");
					   $dom->parentNode->insertBefore($space, $next);
					};
				} else {
				   $newtext = $dom->ownerDocument->createTextNode($text);
 				   $dom->parentNode->insertBefore($newtext, $next);
				};
			};
		};
		$ttxml->save();
		
		print "Changes saved - reloading
			<script>top.location='index.php?action=$action&cid=$ttxml->fileid'</script>";
		exit;

	} else if ( $act == "pagsel") {

		$maintext .= "<p>Select page:";
		
		foreach ( $ttxml->xpath("//pb") as $pb ) {
			$maintext .= "<p><a href='index.php?action=$action&cid=$ttxml->fileid&pbid={$pb['id']}'>{$pb['id']}</a> ".htmlentities($pb->asXML());
		};	

	} else if ( $act == "linesel" ) {
	
		$maintext .= "<p>Select line:";
		
		foreach ( $ttxml->xpath("//lb") as $lb ) {
			$maintext .= "<p><a href='index.php?action=$action&cid=$ttxml->fileid&pbid={$_GET['pbid']}&lbid={$lb['id']}'>{$lb['id']}</a> ".htmlentities($lb->asXML());
		};	

	} else {

		$pbid = $_GET['pbid'];
		# $lbid = $_GET['lbid'];
		
		foreach ( $ttxml->xpath("//pb") as $i => $pbx ) { 
			$pbi = $pbx['id'];
			$sel = ""; 
			if ( $pbi == $pbid ) $sel = "selected";
			if ( !$pbid ) $pbid = $pbx['id'];	
			$pnr = $pbx['n'] or $pnr = "[".($i+1)."]";
			$pblist .= "<option value=$pbi $sel>Page $pnr</option>"; 
		}; 	
		
		$pagexml = $ttxml->page($pbid, array("elm"=>"pb"));
		preg_match_all("/<lb[^<>]+id=\"([^\"]+)\"/", $pagexml, $lbs);
		foreach ( $lbs[1] as $i => $lbi ) { 
			$sel = ""; 
			if ( $lbi == $_GET['lbid'] ) {
				$sel = "selected";
				$lbid = $lbi;
			};
			$lblist .= "<option value=$lbi $sel>Line ".($i+1)."</option>"; 
		}; if ( !$lbid ) $lbid = $lbs[1][0];		
		
		$pb = current($ttxml->xpath("//pb[@id=\"$pbid\"]"));
		$lb = current($ttxml->xpath("//lb[@id=\"$lbid\"]"));
		$pbn = $pb['n'] or $pbn = $pb['id'];
		$lbn = $lb['n'] or $lbn = $lb['id'];

		$editxml = $ttxml->page($lbid, array("elm"=>"lb"));	
		$ctest = preg_replace("/<\/?lb[^<>]*>/", "", $editxml);
		if ( trim($ctest) == ""  ) { 
			$linetext = $lb['text'];
			$editxml = "<form action='index.php?action=$action&act=linesave&cid=$ttxml->fileid' method=post>
				<input type=hidden name=cid value='$ttxml->fileid'>
				<input type=hidden name=pbid value='$pbid'>
				<input type=hidden name=lbid value='$lbid'>
				Provisional transcription:
				<br><textarea id=prov name=text style='width: 100%;'>$linetext</textarea>
				<input type=submit value='Save'>
				</form>
				<script>document.getElementById('prov').focus();</script>";
		};
		$maintext .= "<table width=100%>
			<tr><td><h2>Page $pbn - Line $lbn</h2>
			<div id=mtxt>$editxml</div>
			<td align=right>
				<form id=lineform action='index.php'>
				<input type=hidden name=action value='$action'>
				<input type=hidden name=cid value='$ttxml->fileid'>
				<select id=pagsel onChange='this.parentNode.lbid.value = null; this.parentNode.submit();' name=pbid>$pblist</select>
				<br/><select id=linesel onChange='this.parentNode.submit();' name=lbid>$lblist</select>
				</form>
				<a href='index.php?action=elmedit&cid=$ttxml->fileid&tid=$lbid'>edit</a>
			</tr>
			</table>
			<script>
			document.addEventListener('keyup', logKey);
			var linesel = document.getElementById('linesel');
			var lineform = document.getElementById('lineform');
			var pagsel = document.getElementById('pagsel');
			function logKey(e) {
			  if ( e.code == \"ArrowRight\" ) {
			  	if (linesel.selectedIndex < linesel.options.length-1 ) {
				 	linesel.selectedIndex++;
				 	lineform.submit();
				};
			  } else if ( e.code == \"ArrowLeft\" ) {
			  	if (linesel.selectedIndex) {
			  		linesel.selectedIndex--;
			  		lineform.submit();
			  	};	
			  };
			  if ( e.code == \"ArrowDown\" ) {
			  	if (pagsel.selectedIndex < pagsel.options.length-1 ) {
				 	pagsel.selectedIndex++;
				 	lineform.submit();
				};
			  } else if ( e.code == \"ArrowUp\" ) {
			  	if (pagsel.selectedIndex) {
			  		pagsel.selectedIndex--;
			  		lineform.submit();
			  	};	
			  };
			}
			</script>
			<hr>";

		$showbb = 1;

		$imgsize = getimagesize("Facsimile/{$pb['facs']}");
		if ( !$imgsize[0] ) {
			$dim = 1;
			$fact = 1;
		} else {
			$dim = $imgsize[1]/$imgsize[0];
			$fact = 1500/$imgsize[0];
		};
		if ( $_GET['debug'] ) $maintext .= "<p>Image: {$imgsize[1]}x{$imgsize[0]} - factor: $fact";
		# $maintext .= print_r($imgsize, 1);
		$wdt = 1500;
		$hgt = $dim*$wdt;

		$svg = "\n<svg width=\"100%\" viewBox=\"0 0 {$imgsize[0]} {$imgsize[1]}\" xmlns=\"http://www.w3.org/2000/svg\">";
		list ( $ulx, $uly, $lrx, $lry ) = explode(" ", $lb['bbox']);
		$h = intval($lry)-intval($uly); $w = intval($lrx)-intval($ulx);
		if ( $lb['facs'] || $lb['corresp'] ) {
			$facsid = $lb['corresp'] or $facsid = $lb['facs'];
			$facsid = substr($facsid,1);
			$zone = current($ttxml->xpath("//*[@id=\"$facsid\" or @xml:id=\"$facsid\"]"));
			$baseline = $zone['points'];
			if ( strpos(",", $baseline ) == false ) $baseline = preg_replace("/ /", ",", $baseline);
			if ( $_GET['debug'] ) $maintext .= "<p>Points: $baseline";
			$svg .= "\n<polygon id='pol-$lbid' points=\"$baseline\"  fill=\"rgba(240,120,10,0.5)\" />";
		};
		if ( !$baseline || $showbb ) $svg .= "\n<rect y=\"$uly\" x=\"$ulx\" width=\"$w\" height=\"$h\" style=\"fill:none;stroke-width:3;stroke:rgba(255,255,0,0.2)\"/>";
		$svg .= "\n<image width=\"100%\" height=\"100%\">";
		$svg .= "\n</svg>";
		
		$maintext .= "
			<div style='height: 300px; overflow-y: scroll;' id=container>
			<div style='background-image: url(Facsimile/{$pb['facs']}); background-size: 100%; width: 100%;' height='1000px' id=svg>$svg</div>
			</div>
			<script>
				document.getElementById('pol-$lbid').scrollIntoView({
            behavior: 'auto',
            block: 'center',
            inline: 'center'
        });
			</script>
			<hr><a href='index.php?action=file&cid=$ttxml->fileid'>back to text</a>
			";
	
	};
	

?>