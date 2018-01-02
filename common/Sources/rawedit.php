<?php
	// Script to edit an XML file by raw XML
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	
	if ( $_POST ) {
		print_r($_POST); exit;
	};
	
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
	if ( $_GET['remtok'] ) {
		$file = preg_replace ( "/<\/?d?tok[^>]*>/", "", $file );
		$file = preg_replace ( "/<ee\/>/", "", $file );
		$file = preg_replace ( "/ id=\".*?\"/", "", $file );
	};
	
	# We need to turn of the xmlns here
	# TODO: This should also turn off the internal things 
	$file = preg_replace ( "/ xmlns=/", " xmlnsoff=", $file );	
	
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };

			
	$result = $xml->xpath("//title"); 
	$title = $result[0];

	$maintext .= "<h2>$fileid</h2><h1>$title </h1>";

	# Show optional additional headers
	if ( $shortheader ) $maintext .= "<table>";
	if ( is_array($headershow) ) 
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
		$type = "&type=full";
		$switch = "<a href='index.php?action=rawedit&cid=$fileid'>switch to only text element</a>";
	} else {
		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 
		$editxml = $txtxml->asXML();
		$switch = "<a href='index.php?action=rawedit&cid=$fileid&full=1'>switch to full XML including header</a>";
	}; $switch .= " &bull; <a href='index.php?action=edit&cid=$fileid&full=1'>back to view mode</a>";

	if ( $_GET['view'] != "wysiwyg" ) $editxml = htmlentities($editxml, ENT_COMPAT, 'UTF-8');
	
	$maintext .= "
		<div id=\"editor\" style='width: 100%; height: 400px;'>".$editxml."</div>
	
		<form action=\"index.php?action=rawsave&cid=$fileid$type\" id=frm name=frm method=post>
		<textarea style='display:none' name=rawxml></textarea>
		<p><input type=button value=Save onClick=\"return runsubmit();\"> $switch
		</form>
		
		<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
		<script>
			var editor = ace.edit(\"editor\");
			editor.setTheme(\"ace/theme/chrome\");
			editor.getSession().setMode(\"ace/mode/xml\");
			
			function runsubmit ( ) {
				var rawxml = editor.getSession().getValue();
				var oParser = new DOMParser();
				var oDOM = oParser.parseFromString(rawxml, 'text/xml');
				if ( oDOM.documentElement.nodeName == 'parsererror' ) {
					alert('Invalid XML - please revise before saving.'); 
					return -1; 							
				} else {
					document.frm.rawxml.value = rawxml;
					document.frm.submit();
				};						
			};
		</script>
	";
?>