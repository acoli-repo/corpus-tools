<?php

	// Script to display the original file an XML file was based on 
	// (c) Maarten Janssen, 2015-2016

	check_login();
	
	# See if a base folder is set
	if ( $settings['defaults']['originalsfolder'] ) { 
		$basefolder = $settings['defaults']['originalsfolder'];
	} else {
		$basefolder = "";
	};
	
	
	# Determine the raw filename
	if ( $_GET['cid'] ) {
		require_once ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($txtid, true);
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 
		$filename = current($ttxml->xml->xpath("//note[@n=\"orgfile\"]"))."";
	} else {
		$filename = $_GET['id'];
	};

	if ( !preg_match("/^\//", $filename) )  { $filename = $basefolder.$filename; };
	$thisdir = preg_replace("/\/[^\/]+$/", "", $_SERVER['SCRIPT_FILENAME'] );


	# Get the raw source file
	if ( file_exists($filename) ) {
		$rawtxt = file_get_contents($filename);
	} else if ( file_exists($filename.".Z") ) {
		$filename .= ".Z";
		$cmd = "/bin/zcat $filename";
		$rawtxt =  shell_exec($cmd);
	} else {
		$rawtxt = "No such file";
	};
	

	# Check if the raw source is HTML
	if ( strstr($rawtxt, "<html") || strstr($rawtxt, "<HTML") )  { $raw = "<p><a href='index.php?action=$action&id=$filename&html=1' target=html>View as HTML file</a>"; };
	
	if ( $_GET['html'] ) {
		# Show the raw source as HTML
		print $rawtxt; exit;
	} else {
		# Show the raw source in-line
		$maintext .= "<h1>Raw source input file</h1>
			<p>Filename: $filename</p>
			<p><i>This is a dump of the original file used for the creation of this XML file</i>
			$raw
			<hr>
			<pre>".htmlentities($rawtxt)."</pre>
			";

	};		
		
	
?>