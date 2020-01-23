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

	$sharedfolder = $settings['defaults']['shared']['folder'];
	if ( !$sharedfolder ) $sharedfolder = getenv("TT_SHARED"); 
	
	# See if there are any local or shared startup scripts
	if ( file_exists("Sources/startup.php") ) require("Sources/startup.php");
	if ( file_exists("$sharedfolder/Sources/startup.php") ) require("$sharedfolder/Sources/startup.php");

	# Read any shared settings
	function readinshared($sharr, &$starr) {
		global $settings;
		if ( !is_array($sharr) ) return;
		if ( $sharr['noshare'] || $starr['noshare'] ) return; # Shared settings can be marked as not-to-read
		foreach ( $sharr as $key => $val ) {
			if ( !$starr[$key] ) continue;
			if ( is_array($val) ) {
				readinshared($val, $starr[$key]);
			} else if ( !array_key_exists($key, $starr) ) {
				$starr[$key] = $val;
			};
		};
	};
	if ( $sharedfolder && file_exists("$sharedfolder/Resources/settings.xml") ) {
		$sharedsettings = xmlflatten(simplexml_load_string(file_get_contents("$sharedfolder/Resources/settings.xml")));
		readinshared($sharedsettings, $settings);
	};

	
	# Define where to get the JS libraries from - and in which version (if not defined in the settings)

	# TinyMCE WYSIWYG editor
	$tinymceurl = $settings['defaults']['src']['tinymce'] or $tinymceurl = "https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.7.4/tinymce.min.js";
	if ( $tinymceurl == "local" ) $tinymceurl = "$jsurl/tinymce/tinymce.min.js";

	# ACE code editor (XML)
	$aceurl = $settings['defaults']['src']['ace'] or $aceurl = "https://cdnjs.cloudflare.com/ajax/libs/ace/1.2.9/ace.js";
	if ( $aceurl == "local" ) $aceurl = "$jsurl/ace/ace.js";


	function xmlflatten ( $xml, $int = 0 ) {
		global $maintext; 
		if ( !$xml ) return "";
	
		if ( $xml->attributes() ) 
		foreach ( $xml->attributes() as $atn => $atv ) {
			$flatxml[$atn] = $atv."";
		};

		if ( $int && $xml.""  != "" ) { $flatxml['(text)'] = $xml.""; };

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