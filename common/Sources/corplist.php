<?php

	# Centralized corpus list
	
	if ( file_exists("Resources/corplist.xml") ) $corplist = simplexml_load_file("Resources/corplist.xml");
	else if ( file_exists("$sharedfolder/Resources/corplist.xml") ) $corplist = simplexml_load_file("$sharedfolder/Resources/corplist.xml");
	
	if ( !$corplist ) fatal("No corplist");

	$maintext .= "<h1>{%Corpora}</h1>";
	
	$rest = $settings['corplist']['xprest'];
	
	$maintext .= "<table>";
	foreach ( $corplist->xpath("//corpus$rest") as $corp ) {
		$corpname = $corp['display'] or $corpname = $corp['ident'];
		$corpurl = $corp['url'] or $corpurl = str_replace("tt_", "", $corp['ident']);
		$tmp = current($corp->xpath("./desc")); if ( $tmp ) $desc = $tmp->asXML();
		
		$maintext .= "<tr><th><a href='$corpurl'>$corpname</a><td>$desc";
	};
	$maintext .= "</table>";

?>