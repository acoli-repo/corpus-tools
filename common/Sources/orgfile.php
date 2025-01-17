<?php

	// Script to display the original file an XML file was based on 
	// (c) Maarten Janssen, 2015-2016

	check_login();
	
	# See if a base folder is set
	$basefolder = getset('defaults/originalsfolder');
	
	$filetype = array ( 
			"txt" => array ( 'mime' => 'text/txt', 'show' => 1 ),
			"htm" => array ( 'mime' => 'text/html', 'show' => 1 ),
			"html" => array ( 'mime' => 'text/html', 'show' => 1 ),
			"json" => array ( 'mime' => 'application/json', 'show' => 1, 'pre' => 1 ),
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
	
	$viewers = getset("viewers", array());
	
	# Determine the raw filename
	if ( $_GET['cid'] || $_GET['id'] ) {
		require_once ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
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
	if ( $viewers[$extention] ) {
		
		$vact = $viewers[$extention]['action'];
		$xmlname = $ttxml->xmlid or $xmlname = preg_replace("/.*([^\/]+?)\?[^.]*/", "\\1", $filename);
		$rawtxt = "<p>Custom visualization tool: <a href='index.php?action=$vact&id=$xmlname'>$vact</a></p>";	

	} else if ( file_exists($filename) && !$filetype[$extention]['show'] ) {
		
		if ( is_array($filetype[$extention]['helpers']) ) {
			foreach ( $filetype[$extention]['helpers'] as $app => $options ) {
				$cmd = "$app $options";
				$cmd = str_replace("{}", $filename, $cmd);
				$rawtxt = shell_exec($cmd);
			};
		} else if ( $_GET['raw'] ) {
			$rawtxt = file_get_contents($filename);
		};
		
		if ( $filetype[$extention]['pre'] ) $rawtxt = "<pre>$rawtxt</pre>"; 
		
		if ( !$rawtxt ) $rawtxt = "Raw document (type $extention) cannot be displayed";
		
	} else if ( file_exists($filename) ) {
	
		$tmp = file_get_contents($filename);

		$enc = $orgnode['encoding'] or $enc = "UTF-8";
		if ( $extention == "json" ) {
			$rawtxt = "
				<script src=\"https://code.jquery.com/jquery-3.3.1.min.js\"></script>
			  <script src=\"https://abodelot.github.io/jquery.json-viewer/json-viewer/jquery.json-viewer.js\"></script>
			  <link href=\"https://abodelot.github.io/jquery.json-viewer/json-viewer/jquery.json-viewer.css\" type=\"text/css\" rel=\"stylesheet\">
			<div style='display: none;' id=\"json\">$tmp</div>
			<pre id=\"json-view\"></pre>
			<script>
				var jsonData = JSON.parse(document.getElementById('json').innerText);				
				$('#json-view').jsonViewer(jsonData);
			</script>";
		} else {
			$tmp = htmlentities($tmp, ENT_QUOTES, $enc);
			$rawtxt = "<pre>$tmp</pre>";
		};
		
	} else if ( file_exists($filename.".Z") ) {

	   # compressed file
	   $filename .= ".Z";
	   $cmd = "/bin/zcat '$filename'";
	   $rawtxt = shell_exec($cmd);
	   $rawtxt = "<pre>".htmlentities($rawtxt)."</pre>";

	} else {
		$nofile = 1;
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
	} else if ( $nofile ) {
		$maintext .= "<h1>Raw source input file</h1>
			$ttheader
			<p>Filename: $filename</p>
			<p><i>File does not exist</i></p>
			";
	} else {
		# Show the raw source in-line
		if ( getset('scripts/showorg/dl') != '' && file_exists($filename) ) { $filename = "<a href='$filename'>$filename</a>"; };
		$maintext .= "<h1>Raw source input file</h1>
			$ttheader
			<p>Filename: $filename</p>
			<p><i>This is a dump of the original file used for the creation of this XML file</i>
			$raw
			<hr>
			$rawtxt 
			";

	};		
		
	if ( $ttxml ) $maintext .= "<hr>".$ttxml->viewswitch();
	
?>