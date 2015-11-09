<?php
	function load_xml_file () {
		# Find which XML file is meant, and set:
		# $fileid = full path name of the file
		# $xmlid = ID of the XML file
	
		global $fileid, $xmlid;
	
		$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
		$oid = $fileid;
		if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
		$xmlid = $fileid; 
		$xmlid = preg_replace ( "/\.xml/", "", $xmlid );
		$xmlid = preg_replace ( "/.*\//", "", $xmlid );
	
		if ( !$fileid ) { 
			fatal ( "No XML file selected." );  
		};

		if ( !file_exists("$xmlfolder/$fileid") && substr($fileid,-4) != ".xml" ) { 
			$fileid .= ".xml";
		};
	
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

	};
};