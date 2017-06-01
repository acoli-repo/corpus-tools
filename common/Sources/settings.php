<?php

	// Script to read settings.xml and parse it
	// (c) Maarten Janssen, 2015

	if ( file_exists( "Resources/settings.xml" ) ) {
	
		$file = file_get_contents("Resources/settings.xml");
		$settingsxml = simplexml_load_string($file);
	
		if ( !$settingsxml ) {
			$file = file_get_contents("$ttroot/common/Resources/settings.xml");
			$settingsxml = simplexml_load_string($file);
		}; 
		
		$settings = xmlflatten($settingsxml);

	};	

	function xmlflatten ( $xml ) {
		global $maintext; 
		if ( !$xml ) return "";
	
		if ( $xml->attributes() ) 
		foreach ( $xml->attributes() as $atn => $atv ) {
			$flatxml[$atn] = $atv."";
		};

		if ( $xml.""  != "" ) { $flatxml['(text)'] = $xml.""; };

		foreach ( $xml->children() as $node ) {
			$chn = "".$node->getName();
			if ( $node['id'] ) $key = $node['id']."";
			else if ( $chn == "item" ) {
				if ( $node['key'] ) $key = $node['key']."";
				else { $icnt++; $key = $icnt; };
			} else $key = $chn;
			
			$flatxml[$key] = xmlflatten($node);
		};
	
		return $flatxml;
	};
	
?>