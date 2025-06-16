<?php

	# PHP script for creating an XML template
	# (c) Maarten Janssen, 2017

	$headerxml = simplexml_load_file("$ttroot/common/Resources/teiHeader.xml");

	$teiheaderfields = array ( 
			"sec1" => "Publisher",
			"/TEI/teiHeader/fileDesc/publicationStmt/publisher" => "Publisher's name",
			"/TEI/teiHeader/fileDesc/publicationStmt/pubPlace" => "Publisher's location",
			"sec2" => "Project",
			"/TEI/teiHeader/fileDesc/titleStmt/sponsor" => "Project sponsor",
			"/TEI/teiHeader/fileDesc/titleStmt/funder" => "Project funder",
			"/TEI/teiHeader/fileDesc/titleStmt/respStmt/resp[@subcat=&quot;project&quot;]" => "Project name",
			"sec3" => "Varia",
			"/TEI/teiHeader/fileDesc/publicationStmt/availability/p" => "Availability",
		);
	
	if ( $act == "create" ) {

		$file = "<TEI>
<teiHeader/>
<text/>
</TEI>";
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$dom = dom_import_simplexml($xml)->ownerDocument; #->ownerDocument		

		# Fill in from XPath commands
		foreach ( $_POST['values'] as $key => $value ) {
			$xquery = $_POST['queries'][$key];
			# print "\n<p>$xquery => $value ";
		
			# If there is a new value to save, make sure the node exists (or create it)
			if ( $value ) { $dom = createnode($dom, $xquery); };
		
			$xpath = new DOMXpath($dom);
			$result = $xpath->query($xquery); 
			if ( $result->length == 1 )
			foreach ( $result as $node ) {
				if ( $node->nodeType == XML_ATTRIBUTE_NODE ) {
					$node->parentNode->setAttribute($node->nodeName, $value);
				} else {
					$tmp = $node->ownerDocument->saveXML($node);
					if ( preg_match("/^(<[^>]+>)(.*?)(<\/[^>]+>)$/si", $tmp, $matches ) ) { 
						$toinsert = $matches[1].$value.$matches[3]; 
					} else if ( preg_match("/^<(([a-z]+)[^>]*?)\/>$/si", $tmp, $matches ) ) { 
						$toinsert = '<'.$matches[1].'>'.$value.'</'.$matches[2].'>'; 
						# print "\nAbout to insert: ".htmlentities($toinsert);
					} else { print "\n<p>Cannot insert node, does not have start and end tag: {".htmlentities($tmp).'}'; exit; };
					$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
					if ( !$sxe && $value ) {
						# This is not proper XML - try to repair
						# print "\n<p>Repairing XML - $toninsert";
						$toinsert = preg_replace("/\&(?![a-z+];)/", "&amp;", $toinsert);
						$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);					
					};
					if ( !$sxe && $value ) {
						print "\n<p>Cannot insert node, invalid XML: {".htmlentities($toinsert).'}'; exit;
					};
					$newelement = dom_import_simplexml($sxe);
					$newelement = $dom->importNode($newelement, true);
					$node->parentNode->replaceChild($newelement, $node);
				};
			};
		};
		
		$xmlfilename = "Resources/xmltemplate-{$_POST['id']}.xml";
		file_put_contents($xmlfilename, $xml->asXML());
		
		# Now put this template in the settings.xml
		$settingsxml = simplexml_load_file("Resources/settings.xml");
		$settingsdom = dom_import_simplexml($settingsxml)->ownerDocument; #->ownerDocument		
		$nxp = "/ttsettings/xmltemplates/item[@key='xmltemplate-{$_POST['id']}.xml']";
		$settingsdom = createnode($settingsdom, $nxp);
		$tempnode = current($settingsxml->xpath($nxp));
		$tempnode['display'] = $_POST['name'];
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());

		$maintext .= "<h1>XML Template Saved</h1>
			<p>Your XML Template {$_POST['name']} was saved as $xmlfilename";

	} else {
		$maintext .= "<h1>XML Template Creator</h1>
			<p>In order to have a more informative TEI/XML file, it is useful to keep information about
				the project, the transcription norms, etc. in the teiHeader of each XML file of your
				project. Since these data are the same for each XML file, the easiest is to not have them
				editable, but rather pre-load all those data upon the creation of a new XML file. You
				can do that by simply selecting an existing XML file, which will automatically copy all the
				non-editable data from there; but you can also use a template, which is basically an empty XML 
				file that contains only the data that are the same for all (or a subsection of) the XML files
				in your project. To help create such a file, below is a list of frequently used teiHeader
				elements.
				
		<hr>
		<form action='index.php?action=$action&act=create' method=post>
		<table>
		<tr><th>Template id:<td><input name=id value='default'>
		<tr><th>Template name:<td><input name=name value='Default'>
		";
		foreach ( $teiheaderfields as $xpath => $title ) {
			if ( substr($xpath,0,3) == "sec" ) {
				$maintext .= "<tr><td colspan=2><h2>$title</h2>";
			} else {
				$key++;
				$maintext .= "<tr><th title='$xpath'>$title<td><textarea name=\"values[$key]\" cols='80' rows='$rowcnt'></textarea>";
				$maintext .= "<input type=hidden name='queries[$key]' value='$xpath'>";		
			};
		};
		$maintext .= "</table><hr><p><input type=submit value=Select>
		</form>";
				
		
	};
?>