<?php
	// Script to list the <pb/> elements in an XML file - or <milestone/>
	// (c) Maarten Janssen, 2015
		
	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 
	
	$fileid = $_GET['cid'];
	
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
			if ( $lpnr ) {
				$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&pageid=$lpid&pbtype=pb\">$lpnr</a>";
			};
			$lpnr = $pnr; $lpid = $pid;
		};
		$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&pageid=$pid&pbtype=pb\">$pnr</a>";
		$maintext .= "</td>";
	};
	if ( !$settings['xmlfile']['index'] ) $settings['xmlfile']['index'] = array ( "chapter" => array ( "display" => "Chapter List" ));
	foreach ( $settings['xmlfile']['index'] as $key => $val ) {
		if ( $val['div'] ) {
			$divxp = "//{$val['div']}[@type=\"$key\"]";
			if ( count($ttxml->xml->xpath($divxp)) > 0 ) {
				$lpnr = "";
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath($divxp); $tmp = 0;
				foreach ($result as $cnt => $node) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					if ( $lpnr ) {
						$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&div=$lpid&divtype={$val['div']}\">$lpnr</a>";
					};
					$lpnr = $pnr; $lpid = $pid;
				};
				$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&div=$pid&divtype={$val['div']}\">$pnr</a>";
				$maintext .= "</td>";
			};
		} else {
			if ( count($ttxml->xml->xpath("//milestone[@type=\"$key\"]")) > 0 ) {
				$lpnr = "";
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath("//milestone[@type=\"$key\"]"); $tmp = 0;
				foreach ($result as $cnt => $node) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					if ( $lpnr ) {
						$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&pageid=$lpid&pbtype=$key\">$lpnr</a>";
					};
					$lpnr = $pnr; $lpid = $pid;
				};
				$maintext .= "<p><a href=\"index.php?action=file&id=$fileid&pageid=$pid&pbtype=$key\">$pnr</a>";
				$maintext .= "</td>";
			};
		};
	};
	$maintext .= "</tr></table>";
	
?>