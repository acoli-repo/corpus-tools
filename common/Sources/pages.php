<?php
	// Script to list the <pb/> elements in an XML file - or <milestone/>
	// (c) Maarten Janssen, 2015
		
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 
	
	$fileid = $_GET['cid'] or $fileid = $_GET['id'];
	
	$pbsel = $_GET['pbtype'] or $pbsel = $_GET['type'];
	if ( !$pbsel ) { 
		$pbelm = "pb";
		$titelm = "Page";
		$pbtype = "pb";
	} else if ( $pbsel == "chapter" ) { 
		$pbelm = "milestone[@type=\"chapter\"]";
		$titelm = "Chapter";
		$pbtype = "milestone";
	} else {
		$pbelm = "milestone[@type=\"$pbsel\"]";
		$titelm = ucfirst($pbsel);
		$pbtype = "milestone";
	};
	
	$maintext .= "<table style='width: 100%'><tr>";
	if ( count($ttxml->xml->xpath("//pb")) > 1 ) {
		$lpnr = "";
		$maintext .= "<td valign=top>
			<h2>{%Page List}</h2>";
		# Build the list of pages
		$result = $ttxml->xml->xpath("//pb"); $tmp = 0;
		foreach ($result as $cnt => $node) {
			$pid = $node['id'] or $pid = "cnt[$cnt]";
			$pnr = $node['n'] or $pnr = "cnt[$cnt]";
			if ( $settings['defaults']['thumbnails'] ) {
				$tni = $node['facs']; 
				$tnn = "$ttxml->xmlid/$ttxml->xmlid"."_$pnr.jpg";
				if ( $tni && file_exists("Thumbnails/$tni") ) $tni = "Thumbnails/$tni";
				else if ( file_exists("Thumbnails/$tnn") ) $tni = "Thumbnails/$tnn";
				else $tni = "Facsimile/$tni";
				$maintext .= "<a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\"><div class=thumbnail><img src='$tni' title=\"$ttxml->xmlid:$pnr\"/><br>$pnr</a></div>";
			} else {
				$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\">$pnr</a>";
			};
		};
		$maintext .= "</td>";
	};
	if ( !$settings['xmlfile']['index'] ) $settings['xmlfile']['index'] = array ( "chapter" => array ( "display" => "Chapter List" ));
	foreach ( $settings['xmlfile']['index'] as $key => $val ) {
		if ( $val['div'] || $val['xpath'] ) {
			if ( $val['xpath'] ) $divxp = $val['xpath'];
			else $divxp = "//{$val['div']}[@type=\"$key\"]";
			if ( count($ttxml->xml->xpath($divxp)) > 0 ) {
				$lpnr = "";
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath($divxp); $tmp = 0;
				foreach ( $result as $cnt => $node ) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&div=$pid&divtype={$key}\">$pnr</a>";
				};
				$maintext .= "</td>";
			};
		} else {
			if ( count($ttxml->xml->xpath("//milestone[@type=\"$key\"]")) > 0 ) {
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath("//milestone[@type=\"$key\"]"); $tmp = 0;
				foreach ($result as $cnt => $node) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=$key\">$pnr</a>";
				};
				$maintext .= "</td>";
			};
		};
	};
	$maintext .= "</tr></table>";
	
?>