<?php
	// Script to save the raw XML of a modified XML file
	// called from rawedit.php
	// (c) Maarten Janssen, 2015

	check_login();

	$cardid = $_POST['cid'] or $cardid = $_GET['cid'];
	
	if ( $cardid ) { 
	
		if ( !file_exists("$xmlfolder/$cardid") ) { 
			print "No such letter: $xmlfolder/$cardid"; 
			exit;
		};
		# print_r($_POST); exit;
		$file = file_get_contents("$xmlfolder/$cardid"); 

		if ( !strstr($file, "<?xml") ) { 
			# Turn off the namespaces
			$file = preg_replace ( "/ xmlns=/", " xmlnsoff=", $file );	
		};

		$maintext .= "<h1>Save Raw XML</h1>";

		if ( $_POST['rawxml'] ) {
		
			if ( $_GET['type'] == "full" ) {
			
				$newfile = $_POST['rawxml'];
				
			} else {

				$savexml =  $_POST['rawxml'];
				# Protect & in the xml - if they are not already HTML codes
				$savexml = preg_replace ( "/&(?![a-z]+;)/", "&amp;", $savexml );
				
				$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
				if ( !$xml ) { fatal("<p>Error opening XML file"); };
				$mtxtelement = $xml->xpath($mtxtelement); 
				if ( !$mtxtelement ) { fatal("<p>Error. There is no element $mtxtelement in this XML file"); };
				
				if ( preg_match("/^<([^> ]+)/", $mtxtelement[0]->asXML(), $matches ) ) $tag = $matches[1]; else $tag = "text";
				if ( strstr($mtxtelement[0]->asXML(), "<![CDATA[") && !strstr($savexml, "<![CDATA[") ) {
					$savexml = "<$tag><![CDATA[".$savexml."]]></$tag>";
				};

				$mtxtelement[0][0] = "#!XMLHERE!#";
				$newfile = preg_replace( "/<[^>]+>#!XMLHERE!#<\/[^>]+>/", $savexml, $xml->asXML() );
				
			};
			
			# print $newfile; exit;
			
			saveMyXML($newfile, $cardid);

			$maintext .= "<hr><p>Your text has been modified - reloading after renumbering";
			header("location:index.php?action=renumber&id=$cardid&tid=$tokid");
		
		};


	
	} else {
		print "Oops"; exit;
	};
	
?>