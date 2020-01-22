<?php

	# Centralized corpus list
	$xmlfile = $settings['corplist']['xml'] or $xmlfile = "Resources/corplist.xml";
	$corplist = simplexml_load_file($xmlfile);	
	if ( !$corplist ) fatal("Failed to load the corpus list");

	$maintext .= "<h1>{%Corpora}</h1>";
	
	$rest = $settings['corplist']['xprest'];
	
	$maintext .= "<ul>";
	foreach ( $corplist->xpath("//corpus$rest") as $corp ) {
		$corpname = $corp['display'] or $corpname = $corp['ident'];
		$corpurl = $corp['url'] or $corpurl = str_replace("tt_", "", $corp['ident'])."/";
		$tmp = current($corp->xpath("./desc")); if ( $tmp ) $desc = $tmp->asXML();
		
		$maintext .= "	
		<li class='item-box'> <a href='$corpurl'>($corpname)</a></li>";

	};
	$maintext .= "</ul>";

?>