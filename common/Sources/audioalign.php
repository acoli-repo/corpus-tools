<?php
	// Script to help put audio alignment tags (start, stop)
	// in all elements of type <p> <s> <u> (choose)
	// use keyboard to indicate (start) end of each fragment
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'];
	$oid = $fileid;
	
	if ( !strstr( $fileid, '.xml') ) { $fileid .= ".xml"; };	
	if ( !$fileid ) fatal ("No XML file indicated"); 


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
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) fatal ("No valid XML in $xmlfolder/$fileid");

	if ( $act == "save" ) {
	
		$tagname = $_POST['tagname'];
		foreach ( $_POST['nodeid'] as $key => $val) {	
			$nodestart = $_POST['start'][$key];
			$nodeend = $_POST['end'][$key];
			
			print "//{$tagname}[@id='$val']";
			$result = $xml->xpath("//{$tagname}[@id='$val']"); 
			$node = $result[0]; 
			$node['start'] = $nodestart;
			$node['end'] = $nodeend;
			
		};
		# print $xml->asXML(); exit;
		saveMyXML($xml->asXML(), $fileid);

		$maintext .= "<hr><p>The time indexes have been saved - reloading";
		header("location:index.php?action=file&cid=$fileid");
		
		exit;
	} else {
		# Determine the alignment level
		if ( $_GET['tag'] ) $tagname = $_GET['tag'];
		else {
			$result = $xml->xpath("//*[@start]"); 
			if ( $result ) {
				# Take whichever level has already been annotated
				$audionode = $result[0];
				$tagname = $audionode->getName();
			} else {
				$result = $xml->xpath("//s"); 
				if ( $result ) $tagname = "s";
				else $tagname = "p";
			};
		};
	
		
		# Get the audio file
		$result = $xml->xpath("//media"); 
		if ( !$result ) fatal ("No audio file in XML");
		foreach ( $result as $medianode ) {
			list ( $mtype, $mform ) = explode ( '/', $medianode['mimeType'] );
			if ( $mtype == "audio" ) {
				if ( preg_match ( "/MSIE|Trident/i", $_SERVER['HTTP_USER_AGENT']) ) {	
					// IE does not do sound - so just put up a warning
					$audiobit .= "
							<p><i><a href='{$medianode['url']}'>{%Audio fragment for this text}</a></i> - {%Consider using Chrome or Firefox for better audio support}</p>
						"; 
				} else {
					$audiobit .= "<audio id=\"track\" src=\"{$medianode['url']}\" controls ontimeupdate=\"checkaudio();\">
							<source  src=\"{$medianode['url']}\">
							<p><i><a href='{$medianode['url']}'>{%Audio fragment for this text}</a></i></p>
						</audio>
						"; 
					$result = $medianode ->xpath("desc"); 
					$desc = $result[0].'';
					if ( $desc ) {
						$audiobit .= "<br><span style='font-size: small;'>$desc</span>";
					};
				};
			};
		};
		if ( $username ) $txtid = $fileid; else $txtid = $xmlid;
		$result = $xml->xpath("//title"); 
		$title = $result[0];
		if ( $title == "" ) $title = "<i>{%Without Title}</i>";

		$maintext .= "<script language=Javascript src=\"$jsurl/audioalign.js\"></script>";
		$maintext .= "<h2>$txtid</h2><h1>$title </h1>$audiobit
				<style>.adminpart { background-color: #eeeedd; padding: 5px; }</style>";


		# Check whether the tagname exists
		$result = $xml->xpath("//$tagname"); 
		if ( !$result ) fatal ( "No existing nodes for tagname $tagname" );

		$maintext .= "<p>Edit sound alignment at the level of: &lt;$tagname&gt;";
	
		$maintext .= "
			<form action='index.php?action=$action&act=save' method=post id=audioform name=audioform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tagname value='$tagname'>
			<table>
			<tr><th>ID<th>Text<th>Start<th>End";
		$audioidx = 0;
		foreach ( $result as $audionode ) {
			$nodeid = $audionode['id'];
			if ( $tagname == "lb" ) {
				$nodetxt = "(line)";
			} else {
				$nodetxt = $audionode->asXML();
			};
			$nodestart = $audionode['start'];
			$nodeend = $audionode['end'];
			$maintext .= "<tr id='row$audioidx' onClick=\"gotoRow($audioidx)\">
				<td>$nodeid<td>$nodetxt<td><input name='start[$audioidx]' value='$nodestart'>
				<td><input name='end[$audioidx]' value='$nodeend'>
				<input type=hidden name='nodeid[$audioidx]' value='$nodeid'>";
			$audioidx++;
		};
		$maintext .= "</table>
			<input type=submit value=Save>
			<input type=button value=Cancel onClick=\"window.open('index.php?action=edit&cid=$fileid', '_top');\">
			</form>
			";

	};
	
?>