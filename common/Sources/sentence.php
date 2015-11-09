<?php
	// Script to display the <s> elements in an XML file
	// and all the features defined over each sentence
	// Should prob. be extended to do <u> as well
	// (c) Maarten Janssen, 2015
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	$oid = $fileid;
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );
		
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
	
	$file = file_get_contents("$xmlfolder/$fileid"); 
	$xml = simplexml_load_string($file);
	if ( !$xml ) { fatal ( "Failing to read/parse $fileid" ); };
			
	$result = $xml->xpath("//title"); 
	$title = $result[0];

	if ( $username ) $txtid = $fileid; else $txt = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>$title </h1>
				<style>.adminpart { background-color: #eeeedd; padding: 5px; }</style>
				
				<div id='mtxt'>";
	
	$result = $xml->xpath("//s"); 
	foreach ( $result as $sent ) {
		$stxt = $sent->asXML();
		$maintext .= "<div style='width: 100%; border-bottom: 1px solid #66aa66; margin-bottom: 6px; padding-bottom: 6px;'>$stxt";
		foreach ( $sentatts as $key => $val ) {
			$atv = preg_replace("/\/\//", "<lb>", $sent[$key]);		
			$maintext .= "<div class='s-$key' title='$val'>$atv</div>"; 
		}
		$maintext .= "</div>";
	};
	$maintext .= "</div>";	

?>