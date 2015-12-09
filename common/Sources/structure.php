<?php
	// Script to allow editing and viewing the structure of an XML file
	// (c) Maarten Janssen, 2015
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	$oid = $fileid;
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$xmlid = $fileid; 
	$xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	$xmlid = preg_replace ( "/.*\//", "", $xmlid );
	
	if ( !$fileid ) { 
		fatal ( "No XML file selected." );  
	};

	if ( !file_exists("$xmlfolder/$fileid") ) { 
	
		$fileid = preg_replace("/^.*\//", "", $fileid);
		$test = array_merge(glob("$xmlfolder/**/$fileid")); 
		if ( !$test ) 
			$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
		$temp = array_pop($test); 
		$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
		if ( $fileid == "" ) {
			fatal("No such XML File: {$oid}"); 
		};
	};

	# Determine the file date
	$tmp = filemtime("$xmlfolder/$fileid");
	$fdate = strftime("%d %h %Y", $tmp);

	$file = file_get_contents("$xmlfolder/$fileid"); 
	
	$xml = simplexml_load_string($file);

	$result = $xml->xpath($mtxtelement); 
	if ( $result ) {
		$txtxml = $result[0]; 
		$editxml = $txtxml->asXML();
	} else {
		# print $xml->asXML(); exit;
		# $result = $xml->xpath("//name"); 
		# print "$mtxtelement failed - trying something else : "; print_r($result); exit;
		fatal ("Display element not found: $mtxtelement");
	};

	# empty tags are working horribly in browsers - change
	$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

	$result = $xml->xpath("//title"); 
	$title = $result[0];
	if ( $title == "" ) $title = "<i>{%Without Title}</i>";

	if ( $username ) $txtid = $fileid; else $txtid = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>$title </h1>";

	$maintext .= "
			<p>Hover your mouse over the text to see the current the TEI element(s) it belongs to.
				The info will be show in the 'Node info' below, and correspond to the part of the text highlighted in yellow.
			<hr>
			<div style='padding: 4px; position: fixed; width: 100%; left: 0px; bottom: 0px; background-color: #ffffff; margin: 0px; border: 1px solid #aaaaaa;'>Node info: <span id=pathinfo style='background-color: #ffffaa; padding: 4px;'>&nbsp;</span></div>
			<hr>
			<div id=mtxt>".$editxml."</div>
			<script language=Javascript src='$jsurl/teixpath.js'></script>
			";

?>