<?php
	// Script to display the raw XML of a file
	// Partially depricated, based on the idea that by removing <tok>
	// you get the original TEI back
	// (c) Maarten Janssen, 2015
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	
	if ( !$fileid ) { 
		print "No XML file selected."; 
		exit;
	};

	if ( !file_exists("$xmlfolder/$fileid") ) { 
		print "No such XML File: $xmlfolder/$fileid"; 
		exit;
	}; # $template = "iframe";
	
	$file = file_get_contents("$xmlfolder/$fileid"); 
	
	// Remove all <tok> elements
	if ( !$_GET['withtok'] ) {
		$file = preg_replace ( "/<\/?d?tok[^>]*>/", "", $file );
		$file = preg_replace ( "/<ee\/>/", "", $file );
		$file = preg_replace ( "/ id=\".*?\"/", "", $file );
	};
	
	$xml = simplexml_load_string($file);
	if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };

			
	$result = $xml->xpath("//title"); 
	$title = $result[0];

	$maintext .= "<h2>$fileid</h2><h1>$title </h1>";

	# Show optional additional headers
	if ( $shortheader ) $maintext .= "<table>";
	foreach ( $headershow as $hq => $hn ) {
		$result = $xml->xpath($hq); 
		$hv = $result[0];
		if ( $hv ) {
			$htxt = $hv->asXML();
			if ( $shortheader ) 
				$maintext .= "<tr><th style='padding: 5px;'>$hn</th><td>$htxt</td></tr>";
			else 
				$maintext .= "<h3>$hn</h3><p>$htxt</p>";
		};
	}; 
	if ( $shortheader ) $maintext .= "</table>";
	if ( $headershow ) $maintext .= "<hr>";

	$result = $xml->xpath("//tok"); 
	$tokcheck = $result[0]; 
			
	if ( $_GET['full'] ) {
		$editxml = $file;
	} else {
		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 
		$editxml = $txtxml->asXML();
	};
	
	$showxml = htmlentities($editxml, ENT_QUOTES, 'UTF-8');
	# $showxml = preg_replace ( "/[\n\r]/", "</ln><ln>", "$showxml</ln>" );
	$showxml = preg_replace ( "/[\n\r]/", "<br/>", "$showxml" );
	$showxml = preg_replace ( "/\t/", "<div style='display: inline-block; width: 12px;'><hr style='background-color: #eeeeee;'></div>", $showxml );

	$showxml = preg_replace ( "/ ([a-z]+=&quot;(.*?)&quot;)/", " <span style='color: #bb8855'>$1</span>", $showxml );
	$showxml = preg_replace ( "/(&lt;.*?&gt;)/", "<span style='color: #3355bb'>$1</span>", $showxml );
	$showxml = preg_replace ( "/(&lt;\/?tok.*?&gt;)/", "<span style='color: #bb3355'>$1</span>", $showxml );
	$showxml = preg_replace ( "/(&lt;!--.*?--&gt;)/", "<span style='color: #33aa33'>$1</span>", $showxml );
	$maintext .= $showxml;

	$maintext .= "<hr><a href='index.php?action=edit&id=$fileid'>to view mode</a> &bull; ";
	if ( $_GET['withtok'] ) 
		$maintext .= "<a href='index.php?action=$action&id=$fileid&withtok=0&full={$_GET['full']}'>hide token elements</a>";
	else 
		$maintext .= "<a href='index.php?action=$action&id=$fileid&withtok=1&full={$_GET['full']}'>show token elements</a>";
	$maintext .= " &bull; ";
	if ( $_GET['full'] ) 
		$maintext .= "<a href='index.php?action=$action&id=$fileid&withtok={$_GET['withtok']}&full=0'>hide teiHeader</a>";
	else 
		$maintext .= "<a href='index.php?action=$action&id=$fileid&withtok={$_GET['withtok']}&full=1'>show teiHeader</a>";

?>