<?php
	// Script to allow viewing and editing teiHeader data
	// (c) Maarten Janssen, 2015

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();	
	$xml = $ttxml->xml;
	$fileid = $ttxml->fileid;

	$result = $xml->xpath("//title"); 
	$title = $result[0];

	$tplfile = $_POST['tpl'] or $tplfile = $_GET['tpl'] or $tplfile = "teiHeader-edit.tpl";

	if ( !file_exists("Resources/$tplfile") && $act != "rawview" ) fatal ("No such header template: $tplfile");
	$text = file_get_contents("Resources/$tplfile");
	$maintext .= "<h2>$fileid</h2><h1>$title </h1>";
	if ( $act != "rawview" ) $maintext .= "<h2>Template: $tplfile</h2>";

	if ( $act == "save" ) {
		check_login();

		$dom = dom_import_simplexml($xml)->ownerDocument;		
		
		# Check if dom exists
		
		print "\n<p>Saving TEIHEADER<hr>";
		foreach ( $_POST['values'] as $key => $value ) {
			$xquery = $_POST['queries'][$key];
			print "\n<p>$xquery => $value ";
			
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