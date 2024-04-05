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
	
	$filetype = array ( 
			"txt" => array ( 'mime' => 'text/txt', 'show' => 1 ),
			"htm" => array ( 'mime' => 'text/html', 'show' => 1 ),
			"html" => array ( 'mime' => 'text/html', 'show' => 1 ),
			"doc" => array ( 'mime' => 'application/msword',  
				'helpers' => array ( 'textutil' => '-stdout -convert html {}' ), 
			),
			"rtf" => array ( 'mime' => 'application/rtf',  
				'helpers' => array ( 'textutil' => '-stdout -convert html {}' ), 
			),
			"docx" => array ( 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
				'helpers' => array ( 'textutil' => '-stdout -convert txt {}' ), 
			),
		);
	
	# Determine the raw filename
	if ( $_GET['cid'] ) {
		require_once ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML($_GET['cid'], true);
		$ttheader .= "<h2>".$ttxml->title()."</h2>"; 
		$ttheader .= $ttxml->tableheader(); 
		$orgnode = current($ttxml->xpath("//note[@n=\"orgfile\"]"));
		$filename = $orgnode."";
		
		if ( !file_exists($filename) ) $filename = "Originals/$filename";
	} else {
		$filename = $_GET['id'];
		
		if ( !file_exists($filename) ) $filename = str_replace(".xml", ".txt", $filename );
	};


	if ( !preg_match("/^\//", $filename) )  { $filename = $basefolder.$filename; };
	$thisdir = preg_replace("/\/[^\/]+$/", "", $_SERVER['SCRIPT_FILENAME'] );

	if ( preg_match("/([^\/]+\.([^\/.]+))/", $filename, $matches)) { 
		$shortname = $matches[1]; $extention = $matches[2]; 
	};

	# Get the raw source file
	if ( file_exists($filename) && !$filetype[$extention]['show'] ) {
		
		if ( is_array($filetype[$extention]['helpers']) ) {
			foreach ( $filetype[$extention]['helpers'] as $app => $options ) {
				$cmd = "$app $options";
				$cmd = str_replace("{}", $filename, $cmd);
				$rawtxt = shell_exec($cmd);
			};
		};
		
		if ( !$rawtxt ) $rawtxt = "Raw document (type $extention) cannot be displayed";
		
	} else if ( file_exists($filename) ) {
	
		$tmp = file_get_contents($filename);

		$enc = $orgnode['encoding'] or $enc = "UTF-8";
		$tmp = htmlentities($tmp, ENT_QUOTES, $enc);
		$rawtxt = "<pre>$tmp</pre>";

	} else if ( file_exists($filename.".Z") ) {
		# compressed file
		$filename .= ".Z";
		$cmd = "/bin/zcat '$filename'";
		$rawtxt = shell_exec($cmd);
		$rawtxt = "<pre>".htmlentities($rawtxt)."</pre>";

	} else {
		$rawtxt = "No such file : $filename";
	};
	

	# Check if the raw source is HTML
	if ( !$showable[$extention] )  { $raw = "<p><a href='index.php?action=$action&id=$filename&raw=1' target=html>Download file</a>"; }
	else if ( strstr($rawtxt, "<html") || strstr($rawtxt, "<HTML") )  { $raw = "<p><a href='index.php?action=$action&id=$filename&html=1' target=html>View as HTML file</a>"; };
	
	if ( $_GET['html'] ) {
		# Show the raw source as HTML
		print $rawtxt; exit;
	} else if ( $_GET['raw'] ) {
		# Download the raw file
		if ( $filetype[$extention]['mime'] ) { header("Content-Type: ".$filetype[$extention]['mime']); }
		header("Content-Disposition: attachment; filename=\"$shortfile\"");
		print $rawtxt; exit;
	} else {
		# Show the raw source in-line
		if ( $settings['scripts']['showorg']['dl'] && file_exists($filename) ) { $filename = "<a href='$filename'>$filename</a>"; };
		$maintext .= "<h1>Raw source input file</h1>
			$ttheader
			<p>Filename: $filename</p>
			<p><i>This is a dump of the original file used for the creation of this XML file</i>
			$raw
			<hr>
			$rawtxt 
			";

	};		
		
	
?>