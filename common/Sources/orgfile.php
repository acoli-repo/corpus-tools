<?php

	// Script to display the original file an XML file was based on 
	// (c) Maarten Janssen, 2015

	check_login();
	
	if ( $settings['defaults']['originalsfolder'] ) { 
		$basefolder = $settings['defaults']['originalsfolder'];
	} else {
		$basefolder = "";
	};
	
	$filename = $basefolder.$_GET['id'];
	$thisdir = preg_replace("/\/[^\/]+$/", "", $_SERVER['SCRIPT_FILENAME'] );

	if ( file_exists($filename) ) {
		$rawtxt = file_get_contents($filename);
	} else if ( file_exists($filename.".Z") ) {
		$filename .= ".Z";
		$cmd = "/bin/zcat $filename";
		$rawtxt =  shell_exec($cmd);
	} else {
		$rawtxt = "No such file";
	};
	

	$maintext .= "<h1>Raw source input file</h1>
		<p>Filename: $filename</p>
		<p><i>This is a dump of the original file as kept in $basefolder</i><hr>
		<pre>".htmlentities($rawtxt)."</pre>
		";
		
	
?>