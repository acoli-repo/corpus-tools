<?php
	# Script to view details of a partical token
	# Interacts with XDXF and TAGSET  
	# (c) Maarten Janssen, 2016

	$maintext .= "<h1>{%Token Details}</h1>";
	
	# Read the XML file
	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 

	# Get the token
	$tokid = $_GET['tid'] or $tokid = $_GET['id'];
	$result = $ttxml->xml->xpath("//tok[@id='$tokid']"); 
	$token = $result[0]; # print_r($token); exit;


	if ( $debug ) $maintext .= "<p>Token: ".htmlentities($token->asXML());

	# Display the token 
	$maintext .= "<h2>{%Token ID}: {$token['id']}</h2>
		<p><div id=mtxt style='display: inline;'>$token</div></p>";

	# Display the best context
	// See if there is a <s> or <l> around or token
	$tmp = $token->xpath("ancestor::s | ancestor::l | ancestor::p");
	if ( $tmp ) {	
		$sent = $tmp[0];
		$editxml = $sent->asXML();
		$maintext .= "<hr><div id=mtxt>".$editxml."</div>";
		foreach ( $settings['xmlfile']['sattributes'] as $key => $val ) {
			if ( $val['color'] ) $style = " style=\"color: {$val['color']}\" ";
			if ( $sent[$key] ) $maintext .= "<p title=\"{$val['display']}\" $style>$sent[$key]</p>";
		};
		$maintext .= "<hr>";
	} else {
	};
	
	# Display the token details
	$maintext .= "<table>";
	foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $val ) {
		if ( $val['display'] && $token[$key] && ( !$val['admin'] || $username ) ) $maintext .= "<tr><th>{%{$val['display']}}<td>{$token[$key]}</td></tr>";
	};
	foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $val ) {
		if ( $val['display'] && $token[$key] && ( !$val['admin'] || $username ) ) $maintext .= "<tr><th>{%{$val['display']}}<td>{$token[$key]}</td></tr>";
	};
	$maintext .= "</table>";
	
	# Read the tagset
	require ( "../common/Sources/tttags.php" );
	$tttags = new TTTAGS("", false);
	$tagset = $tttags->tagset['positions'];
	# Display the tagset analysis
	$tagfld = $tttags->tagset['fulltag'] or $tagfld = "pos";
	$tag = $token[$tagfld];
	if ( $tagset && $tag ) {
		$maintext .= $tttags->table($tag);
		if ( $warnings ) $maintext .= "<div style='margin-top: 20px; font-weight: bold; color: #992000' class=adminpart>$warnings</div>";
	};
	
	# Display the dictionary item
	$tmp = $settingsxml->xpath("//xdxf/item[cqp]"); $dict = $tmp[0];
	if ( $dict ) {
	
		# Read the dictionary
		$filename = "Resources/".$dict['filename'] or $filename = "Resources/dict.xml";
		$dictxml = simplexml_load_file($filename);
		
		$lemmafld = $dict['cqp']['lemma'] or $lemmafld = "lemma";
		$lemma = $token['lemma'];
		
		$arxpath = $dict['entry'] or $arxpath = "//lexicon/ar";
		$hwxpath = $dict['headword'] or $hwxpath = "k";

		// We should do a pos check

		$xquery = $arxpath."[{$hwxpath}[.='$lemma']]";
		$result = $dictxml->xpath($xquery);
		if ( $result ) {
			$cssfile = $dict['css'] or $cssfile = "dict.css";
			if ( file_exists("Resources/$cssfile") ) {
				$maintext .= "\n<style type=\"text/css\"> @import url(\"Resources/$cssfile\"); </style>\n";
			} else {
				$css = file_get_contents("../common/Resources/dict.css");
				$maintext .= "\n<style>\n$css\n</style>\n";
			};

			$maintext .= "<hr><h2>{$dict['title']}</h2>
				<div id=dict>";
			foreach ( $result as $entry ) {
				$entryxml = $entry->asXML();
				$maintext .= "<div k=\"$ark\">".$entryxml."</div>";		
			};
			$maintext .= "</div>";
		};
		
	};

?>