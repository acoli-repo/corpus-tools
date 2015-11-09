<?php
	// Script to create a new XML file
	// Allows using the depricated way to indicate with <tt/> elements in a template 
	// which fields to ask for - but recommendable to create an (almost) empty XML 
	// file and edit with header.php
	// (c) Maarten Janssen, 2015

	check_login();
	
	# Check whether we are allowed to write to xmlfiles
	if ( !is_writable("xmlfiles") ) {
		fatal ("The folder xmlfiles cannot be written by the system. Please contact the server administrator.");
	};
	
	if ( !$settings['xmltemplates'] ) $xmltemplate = "xmltemplate.xml";
	if ( count($settings['xmltemplates']) == 1 ) {
		$tmp = array_keys($settings['xmltemplates']);
		$xmltemplate = $tmp[0];
	};
	if ( $_POST['tplid'] ) $xmltemplate =  $_POST['tplid'];
	if ( $_GET['tplid'] ) $xmltemplate =  $_GET['tplid'];

	if ( $xmltemplate ) {
		if ( !file_exists("Resources/$xmltemplate") ) {
			print "<p>Template $xmltemplate not found"; exit;
		};
		$file = file_get_contents("Resources/$xmltemplate"); 
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };
	} else { 
		// print_r($_POST);
		// print "No template defined - exiting"; exit;
	};

	if ( $_POST['fname'] ) {
		
		$cardid = $_POST['fname'];
		if ( substr($cardid, -4) != ".xml" ) {
			print "Filename should end on .xml instead of ".substr($cardid, -4); exit;
		} else if ( file_exists("$xmlfolder/$cardid") ) {
			print "File already exists"; exit;
		};
		
		if ( preg_match("/([^\/.]+)\.xml/", $cardid, $matches) ) { $fileid = $matches[1]; };
	
		$result = $xml->xpath("//ttelm"); 
		foreach ( $result as $node ) {
			$nid = $node['id'].'';
			if ( $_POST['ttelm'][$nid] ) {
				$node[] = $_POST['ttelm'][$nid];
			};
		};
		
		$newfile = $xml->asXML();
		$newfile = preg_replace("/\s*<\/?ttelm[^>]*\/?>\s*/", "", $newfile);
		
		# Make sure the ID is in the <text> element
		if ( preg_match("/<text[^>]+id=/", $newfile) ) {
			$newfile = preg_replace("/<text/", "<text id=\"$fileid\"", $newfile);
		};
		
		saveMyXML($newfile, $cardid);
		print "<p>New XML file has been created. Reloading to edit mode.
			<script language=Javascript>top.location='index.php?action=edit&cid=$cardid&display=shand'</script>"; exit;

	} else if ( $xmltemplate ) {
	
		if ( $xmltemplates[$xmltemplate] ) { $templatename = "<p>Template used: <b>{$xmltemplates[$xmltemplate]}</b>"; };
		
		$maintext .= "<h1>Create XML from Template</h1>
		
			$templatename 
		
		<form action='index.php?action=$action' method=post>
		<input name=tplid type=hidden value='$xmltemplate'>
		<p>XML id: <input name=fname size=80>
		<table>";
		
		$result = $xml->xpath("//ttelm"); 
		$boxes['text'] = "<input #ID size=80 value=''>";
		$boxes['num'] = "<input #ID size=80 value=''>";
		$boxes['cdata'] = "<textarea #ID cols=60 rows=10></textarea>";
		foreach ( $result as $node ) {
			list ( $vtype, $skey ) = split ( ": ", $node['desc'] );
			# if ( $skey == "" ) { $vtype = "XML"; $skey = $node['desc'].''; };
			if ( !$vt[$vtype] ) $vt[$vtype] = array();
			$vt[$vtype][$skey] = $node;
		}; 

		foreach ( $vt as $key => $val ) {
			$maintext .= "\n<tr><th rowspan=".count($val)." valign=top>$key<td>"; $rsep = "";
			foreach ( $val as $key => $node ) {
				$boxtype = $node['type'].'';
				$box = $boxes[$boxtype];
				$box = str_replace('#ID', "name='ttelm[".$node['id']."]'", $box);
				$maintext .= "$rsep$key<td>$box";	
				$rsep = "<tr><td>";	
			};
		};
		$maintext .= "</table>
			<input type=submit value=Create>
			</form>";

	} else {
	
		$maintext .= "<h1>Create XML from Template</h1>
			<p>Choose a template to use:";
			
		while ( list ( $key, $item ) = each ( $settings['xmltemplates'] ) ) {
			$maintext .= "<p><a href='index.php?action=$action&tplid=$key'>{$item['display']}</a>";
		};
	
	};

?>