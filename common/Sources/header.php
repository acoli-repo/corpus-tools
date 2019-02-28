<?php
	// Script to allow viewing and editing teiHeader data
	// (c) Maarten Janssen, 2015

	if ( $act != "details" ) {
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();	
		$xml = $ttxml->xml;
		$fileid = $ttxml->fileid;

		$result = $xml->xpath("//title"); 
		$title = $result[0];

		$maintext .= "<h2>$fileid</h2><h1>$title </h1>";
	};
	
	$tplfile = $_POST['tpl'] or $tplfile = $_GET['tpl'];
	
	if ( !$tplfile && !$settings['teiheader'] ) $tplfile = "teiHeader-edit.tpl";

	if ( $tplfile ) {	
		if ( file_exists("Resources/teiHeader-$tplfile.tpl") ) $tplfile = "teiHeader-$tplfile.tpl";

		if ( !file_exists("Resources/$tplfile") && $act != "rawview" && !$settings['teiheader'] ) fatal ("No such header template: $tplfile");
		$text = file_get_contents("Resources/$tplfile");
		if ( $act != "rawview" ) $maintext .= "<h2>Template: $tplfile</h2>";	
	};
	
	if ( $act == "save" ) {
		check_login();

		$dom = dom_import_simplexml($xml)->ownerDocument;		
		
		# Check if dom exists
		
		print "\n<p>Saving TEIHEADER<hr>";
		foreach ( $_POST['values'] as $key => $value ) {
			$xquery = $_POST['queries'][$key];
			print "\n<p>$xquery => $value ";
			$verbose = 1;
			# If there is a new value to save, make sure the node exists (or create it)
			if ( $value ) { $dom = createnode($dom, $xquery); };
			
			$xpath = new DOMXpath($dom);
			$result = $xpath->query($xquery); 
			if ( $result->length == 1 )
			foreach ( $result as $node ) {
				if ( $node->nodeType == XML_ATTRIBUTE_NODE ) {
					$node->parentNode->setAttribute($node->nodeName, $value);
				} else {
					$tmp = $node->ownerDocument->saveXML($node);
					if ( preg_match("/^(<[^>]+>)(.*?)(<\/[^>]+>)$/si", $tmp, $matches ) ) { 
						$toinsert = $matches[1].$value.$matches[3]; 
					} else if ( preg_match("/^<(([a-z]+)[^>]*?)\/>$/si", $tmp, $matches ) ) { 
						$toinsert = '<'.$matches[1].'>'.$value.'</'.$matches[2].'>'; 
						print "\nAbout to insert: ".htmlentities($toinsert);
					} else { print "\n<p>Cannot insert node, does not have start and end tag: {".htmlentities($tmp).'}'; exit; };
					$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
					if ( !$sxe && $value ) {
						# This is not proper XML - try to repair
						print "\n<p>Repairing XML - $toninsert";
						$toinsert = preg_replace("/\&(?![a-z+];)/", "&amp;", $toinsert);
						$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);					
					};
					if ( !$sxe && $value ) {
						print "\n<p>Cannot insert node, invalid XML: {".htmlentities($toinsert).'}'; exit;
					};
					$newelement = dom_import_simplexml($sxe);
					$newelement = $dom->importNode($newelement, true);
					$node->parentNode->replaceChild($newelement, $node);
				};
			};
		};
		
		# print htmlentities($dom->saveXML()); exit;
		saveMyXML($dom->saveXML(), $fileid);

		print "\n<hr><p>The header has been modified - reloading";
		header("location:index.php?action=file&id=$fileid");
		print "<script language=Javascript>top.location='index.php?action=file&id=$fileid';</script>";
		exit;

	} else if ( $act == "rawview" ) {
	
		check_login();
		
		$teiheader = current($xml->xpath("//teiHeader"));
		$maintext .= showxml($teiheader);
		$maintext .= "<hr><p>".$ttxml->viewswitch();
	
	} else if ( $settings['teiheader'] && $act == "details" ) {

		$defaults = simplexml_load_file("$ttroot/common/Resources/teiheader.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$defaults ) fatal("Unable to load default teiheader");

		$maintext .= "<h1>Details about the metadata fields</h1>
			<table>
			<tr><th>Field Name<th>Local description<th>Default description<th>XPath query";
		foreach ( $settings['teiheader'] as $headerfield ) {
			$xquery = $headerfield['xpath'] or $xquery = $headerfield['key'];
			
			$desc = $headerfield['description'];
			$defdesc = current($defaults->xpath($xquery)); 
			
			$maintext .= "<tr><th>{$headerfield['display']}<td>$desc<td>$defdesc<td>$xquery";
		};
		$maintext .= "</table>";
	
	} else if ( $settings['teiheader'] && !$tplfile ) {
	
		# New edit method, to become default
		$defaults = simplexml_load_file("$ttroot/common/Resources/teiheader.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$defaults ) fatal("Unable to load default teiheader");
	
		if ( $act == "edit" ) check_login();
		if  ( !$corpusfolder ) $corpusfolder = "cqp";

		$maintext .= "<h2>Header Data</h2>";
		
		$text = "<table>";
			
		foreach ( $settings['teiheader'] as $headerfield ) {
			$xquery = $headerfield['xpath'] or $xquery = $headerfield['key'];
			
			$desc = $headerfield['description'];
			if ( !$desc ) $desc = current($defaults->xpath($xquery)); 
			
			if ( $headerfield['type'] == "sep" ) {
				$text .= "<tr><th colspan=2>{$headerfield['display']}";
				continue;
			};
									
			$result = $xml->xpath($xquery); 
			$tmp = $result[0];
			if ( !$result ) $to = "";
			else if ( $tmp->children() ) {
				$to = $tmp->asXML();
				$to = preg_replace ("/^<[^>]+>/", "", $to); # Get the innerXML
				$to = preg_replace ("/<\/[^>]+>$/", "", $to); # Get the innerXML
			} else $to = $tmp."";

			$xquery = str_replace("'", '&#039;', $xquery);
						
			$xval = str_replace('"', '&quot;', $to.""); # $to->asXML()
			$xval = str_replace("'", '&#039;', $xval);

			if ( $headerfield['type'] == "select" && $headerfield['cqp'] ) {
				$optionlist = ""; $xkey = $headerfield['cqp'];
				if ( !$xval ) $optionlist = "<option value=''>[{%select}]</option>"; 
				foreach ( explode ( "\0", file_get_contents("$corpusfolder/$xkey.avs") ) as $kva ) { 
					if ( $kva == "" || $kva == "_" ) continue; // Do not add rows without values 
					if ( $xval == $kva ) $sel = "selected"; else $sel = ""; 
					$optionlist .= "<option value='{$kva}' $sel>{$kva}</option>"; 
				};
				$editfld = "<select name=\"values[$xquery]\">$optionlist</select>";				
			} else {
				$rowcnt = min(8,ceil(strlen($xval)/80));
				$editfld = "<textarea name=\"values[$xquery]\" cols='80' rows='$rowcnt'>$xval</textarea>";
			};
						
			$existing = "<input type=hidden name='queries[$xquery]' value='$xquery'>";

			$text .= "<tr><td title='$desc'>{%{$headerfield['display']}}<td>$editfld $existing";

		};
		$text .= "</table>";

		$maintext .= "
			<form action='index.php?action=$action&act=save&cid=$ttxml->fileid' method=post>
			<input type=hidden name=tpl value='$tplfile'>
			$text
			<p><input type=submit value=Save>
			</form>";
			
	} else if ( $act == "edit" ) {
	
		check_login();
		
		preg_match_all ( "/\{#([^\}]+)\}/", $text, $matches );		

		foreach ( $matches[0] as $key => $match ) {

			$from = preg_quote($match, '/'); 

			$xquery = $matches[1][$key];
			
			$result = $xml->xpath($xquery); 
			$tmp = $result[0];
			if ( !$result ) $to = "";
			else if ( $tmp->children() ) {
				$to = $tmp->asXML();
				$to = preg_replace ("/^<[^>]+>/", "", $to); # Get the innerXML
				$to = preg_replace ("/<\/[^>]+>$/", "", $to); # Get the innerXML
			} else $to = $tmp."";

			$xquery = str_replace("'", '&#039;', $xquery);
						
			$xval = str_replace('"', '&quot;', $to.""); # $to->asXML()
			$xval = str_replace("'", '&#039;', $xval);

			$rowcnt = min(8,ceil(strlen($xval)/80));
			$to = "<textarea name=\"values[$key]\" cols='80' rows='$rowcnt'>$xval</textarea>";
			$to .= "<input type=hidden name='queries[$key]' value='$xquery'>";
			$text = preg_replace("/$from/", "$to", $text);
		};
		
		$maintext .= "
			<form action='index.php?action=$action&act=save&cid=$ttxml->fileid' method=post>
			<input type=hidden name=tpl value='$tplfile'>
			$text
			<p><input type=submit value=Save>
			</form>";

	} else {
				
		preg_match_all ( "/\{#([^\}]+)\}/", $text, $matches );		

		foreach ( $matches[0] as $key => $match ) {

			$from = preg_quote($match, '/'); 

			$xquery = $matches[1][$key];
			# We need to emulate SUBSTR here since PHP does not support it....
			if ( preg_match ( "/substring\((.*?), (\d+)\)/", $xquery, $subms ) ) { 
				$xquery = $subms[1]; $tmp  = $subms[2]-1;
				$result = $xml->xpath($xquery); 
				if ( !$to ) $to = "";
				else $to = $result[0];
				$to = substr($to, $tmp);				
			} else {			
				$result = $xml->xpath($xquery); 
				$to = $result[0];
				if ( !$to ) $to = "";
				# else if ( $to->children() ) $to = $to->asXML();
				else $to = $to."";
			};
			
			$xval = str_replace('"', '&quot;', $to);
			$xval = str_replace("'", '&#039;', $xval);
			
			$to = "<input name='[$xquery]' size='50' value=\"$xval\">";
			
			if ( $to == "" && !$showempties ) { 
				$text = preg_replace("/<tr><t[dh]>[^>]+(<[^>]+>)+$from(<[^>]+>)+/", "$to", $text); # For rows
			};
			
			$text = preg_replace("/$from/", "$to", $text);
		};
		
		$maintext .= $text;

	};


?>