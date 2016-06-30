<?php
	// Script to edit sentences <s> in an XML file
	// only partially finished - avoid using
	// (c) Maarten Janssen, 2015

	check_login();

	$sentatts = $settings['xmlfile']['sattributes'];

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$sentid = $_POST['sid'] or $sentid = $_GET['sid'];
	
	if ( $fileid ) { 
		$stype = $_GET['sentence'] or $stype = $_GET['elm'] or $stype = "s";
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			print "No such XML File: $xmlfolder/$fileid"; 
			exit;
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file);
		if ( !$xml ) { 
			print "Failed to load XML File: $xmlfolder/$fileid"; 
			exit;
		};

		$result = $xml->xpath("//*[@id='$sentid']"); 
		$sent = $result[0]; # print_r($token); exit;
		if ( !$sent ) fatal ( "Sentence not found: $sentid" );
		$stf = $sent->getName();
		
		$sentname = $sentatts[$stf]['display'] or $sentname = "Sentence";
		
		$maintext .= "<h1>Edit {$sentname} $sentid</h1>
			<div>Full text: <div id=mtxt style='inlinde-block;'>".$sent->asXML()."</div></div>
			
			<p>
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$sentid'>
			<input type=hidden name=stype value='$stype'>
			<table>";
			

		// Show all the defined attributes
		foreach ( $sentatts[$stf] as $key => $val ) {
			$atv = $sent[$key]; 
			if ( is_array($val) ) $maintext .= "<tr><th>$key<td>{$val['display']}<td><textarea style='width: 600px' name=atts[$key] id='f$key'>$atv</textarea>";
		};

		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 

		$maintext .= "</table>
			<p><input type=submit value=Save>
			</form>";
	
	} else {
		print "Oops"; exit;
	};
	
?>