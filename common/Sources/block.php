<?php
	// Script to display the <s> elements in an XML file
	// and all the features defined over each sentence
	// Should prob. be extended to do <u> as well
	// (c) Maarten Janssen, 2015
	
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;

	# Show sentence view
	$stype = $_GET['elm'] or $stype = "s";
	if ( $stype == "1" ) $stype = "s";
	
	$blockdef = $settings['xmlfile']['sattributes'][$stype];
	
	$stype = str_replace("|", "| //", $stype);
	$result = $xml->xpath("//$stype"); 
	if ( $result > 100 ) { 
		$result = array_slice($result, 0, 100);
	};
	$sentnr = 1; $ewd = 25;
	foreach ( $result as $sent ) {
		$stxt = $sent->asXML(); 
		$sentid = $sent['n'] or $sentid = "[".$sentnr++."]";
		$treelink = ""; $nrblock = "";
		if ( $sent->xpath(".//tok[@head and @head != \"\"]") ) { 
			$treelink .= "<a href='index.php?action=deptree&cid=$fileid&sid={$sent['id']}' title='dependency tree'>tree</a>"; 
			$ewd = 50;
		};
		if ( $psdx  && $stype == "s" ) { // Allow a direct link to a PSDX tree 
			$nrblock = "
				<div style='display: inline-block; float: left; margin: 0px; padding: 0px; width: 80px;'>
				<table style='width: 100%; table-layout:fixed; margin: 0px;'><tr><td style='width: 25px;font-size: 10pt; '>";
			if ( $psdx->xpath("//forest[@sentid=\"$sentid\"]") ) {
				$editxml .= "<a href='index.php?action=psdx&cid=$xmlid&sentence=$sentid'>tree</a>";
			};
			$pl = "100px";
			if ( $username ) {
				$editxml .= " 
					<td style='width: 25px;font-size: 10pt; '><a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>edit</a>";
				$pl = "100px";
			};
			$nrblock .= " 
				<td style='width: 30px;font-size: 10pt;  text-align: right;'>$sentid </table></div>";
		}  else {
			$nrblock = "
				<div style='display: inline-block; float: left; margin: 0px; padding: 0px; padding-top: 0px; width: {$ewd}px; font-size: 10pt;'>
					<a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>$sentid</a>
					$treelink
				</div>";
			$pl = "50px";
		};
		$editxml .= "
			<div style='width: 90%; border-bottom: 1px solid #66aa66; margin-bottom: 6px; padding-bottom: 6px;'>
			$nrblock
			<div style='padding-left: $pl;'>
			$stxt";
		foreach ( $settings['xmlfile']['sattributes'][$stype] as $item ) {
			$key = $item['key'];
			$atv = preg_replace("/\/\//", "<lb/>", $sent[$key]);	
			if ( $item && $item['color']) { $scol = "style='color: {$item['color']}'"; } else { $scol = "class='s-$key'"; };
			if ( $atv && ( !$item['admin'] || $username ) ) {
				if ( $item['admin'] ) $scol .= " class='adminpart'";
				$editxml .= "<div $scol title='{$item['display']}'>$atv</div>"; 
			}
		};
		$editxml .= "</div></div>";
	};
	
	
	if ( $username ) $txtid = $fileid; else $txt = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>{%{$blockdef['display']} view}</h1>";
	$maintext .= $ttxml->tableheader();
				
	$maintext .= "<div id='mtxt'>$editxml</div>";

	$maintext .= "<hr><p>".$ttxml->viewswitch();
	

?>