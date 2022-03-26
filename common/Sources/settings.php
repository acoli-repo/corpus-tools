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
	if ( $sharedfolder && !is_dir($sharedfolder) ) $sharedfolder = ""; # In case there are wrong local settnigs
	if ( !$sharedfolder ) $sharedfolder = getenv("TT_SHARED"); 
	if ( !$sharedfolder ) $sharedfolder = $_SERVER["TT_SHARED"]; 
	if ( $sharedfolder && !is_dir($sharedfolder) ) $sharedfolder = ""; # In case there are wrong local settnigs
	$checkshared = preg_replace("/.*\/([^\/]+)\/?/", "\\1", $sharedfolder );
	if ( $checkshared == $foldername ) $isshared = 1;
	
	# See if there are any local or shared startup scripts
	if ( file_exists("Sources/startup.php") ) require("Sources/startup.php");
	if ( file_exists("$sharedfolder/Sources/startup.php") ) require("$sharedfolder/Sources/startup.php");

	# Read any shared settings
	function readinshared($sharr, &$starr) {
		global $settings;
		if ( !is_array($sharr) ) return;
		if ( $starr['nolocal'] && $isshared ) unset($starr); # Remove shared-only items in shared project
		if ( $sharr['noshare'] || $starr['noshare'] ) return; # Shared settings can be marked as not-to-read
		foreach ( $sharr as $key => $val ) {
			if ( is_array($val) ) {
				if ( is_array($starr) && !array_key_exists($key, $starr) && !$sharr[$key]['noshare'] ) {
					$starr[$key] = array( );
				};
				if ( is_array($starr[$key]) ) {
					readinshared($val, $starr[$key]);
				};
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
	$tinymceurl = $settings['defaults']['src']['tinymce'] or $tinymceurl = "https://cdnjs.cloudflare.com/ajax/libs/tinymce/4.9.6/tinymce.min.js";
	if ( $tinymceurl == "local" ) $tinymceurl = "$jsurl/tinymce/tinymce.min.js";

	# ACE code editor (XML)
	$aceurl = $settings['defaults']['src']['ace'] or $aceurl = "https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.14/ace.js";
	if ( $aceurl == "local" ) $aceurl = "$jsurl/ace/ace.js";

	$bindir = $settings['defaults']['base']['bin'] or $bindir = "/usr/local/bin";
	
?>