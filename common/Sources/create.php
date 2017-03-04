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

	$xmltitle = "Create XML from Template";
	
	if ( count($settings['xmltemplates']) == 1 ) {
		$tmp = array_keys($settings['xmltemplates']);
		$xmltemplate = $tmp[0];
		if ( !file_exists($xmltemplate) ) $xmltemplate = "Resources/$xmltemplate";
	} else if ( !$settings['xmltemplates'] ) {
		$xmltemplate = "Resources/xmltemplate.xml";
		$xmltitle = "Create Empty XML File";

		# Use the empty XML template from the common folder if there is no local template
		if ( !file_exists("$xmltemplate") ) {
			$xmltemplate = "$ttroot/common/Resources/xmltemplate.xml";
			$xmltitle = "Create new empty XML file";
		};
	} else {
		$tmp = $_POST['tplid'] or $tmp = $_GET['tplid'];
		if ( $tmp ) { $xmltemplate = "Resources/$tmp"; };
	};
	

	if ( $_POST['fname'] ) {
	
		$file = file_get_contents("$xmltemplate"); 
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };
		
		
		
		$cardid = $_POST['fname'];
		$cardid = preg_replace("/[+ '\"]+/", "_", $cardid); # Remove problematic characters from the name

		if ( substr($cardid, -4) != ".xml" ) {
			fatal("Filename ($cardid) should end on .xml");
		} else if ( file_exists("$xmlfolder/$cardid") ) {
			fatal("File $cardid already exists");
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

		$file = file_get_contents("$xmltemplate"); 
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };
	
		if ( $xmltemplates[$xmltemplate] ) { $templatename = "<p>Template used: <b>{$xmltemplates[$xmltemplate]}</b>"; };
		
		$maintext .= "<h1>$xmltitle</h1>
		
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
			list ( $vtype, $skey ) = explode ( ": ", $node['desc'] );
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