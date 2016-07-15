<?php
	// Script to save changes made in tokedit.php
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
		
	if ( $fileid ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			print "No such XML File: $xmlfolder/$fileid"; 
			exit;
		};
		# print_r($_POST); exit;
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file);
		if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };

		$nodetype = substr($tokid,0,1);
		if ( $nodetype == "w" ) 
			$result = $xml->xpath("//tok[@id='$tokid']"); 
		else if ( $nodetype == "d" ) 
			$result = $xml->xpath("//dtok[@id='$tokid']"); 
		else
			$result = $xml->xpath("//*[@id='$tokid']"); 
		$token = $result[0]; # print_r($token); exit;
		if ( !$token ) { print "Token not found: $tokid<hr>"; print $file; exit; };

		$maintext .= "<h1>Create Multi-Token</h1>";
		$result = $token->xpath("preceding::tok[{$_GET['num']}]"); 
		$prevtok = current($result);

		if ( $prevtok ) {
			$previd = $prevtok['id'];
			if ( preg_match("/<tok[^>]+id=\"$previd\".*<tok[^>]+id=\"$tokid\".*?<\/tok>/smi", $file, $matches ) ) { $innerXML = $matches[0]; };
			if ( $innerXML ) {
				# Check whether the innerXML is valid XML
				$mtok = "<mtok>".$innerXML."</mtok>";
				$tmp = simplexml_load_string($mtok);
				if ( $tmp ){
					$mtoktxt = dom_import_simplexml($tmp)->textContent;
					$file = preg_replace("/<tok[^>]+id=\"$previd\".*<tok[^>]+id=\"$tokid\".*?<\/tok>/smi", "<mtok id=\"newmtok\" form=\"$mtoktxt\">".$innerXML."</mtok>", $file);
				} else {
					fatal ("<p>Unable to create mtok - inner content not valid XML; please create mtok manually in the raw XML.<hr>".htmlentities($mtok));
				}; 
			} else {
				fatal ("Failed to find innerXML");
			};
		} else {
			fatal("no token found {$_GET['num']} left of $tokid");
		};

		$xml = simplexml_load_string($file);
		if ( !$xml ) { fatal ( "No longer valid XML $fileid<hr>".htmlentities($file) ); };

		saveMyXML($xml->asXML(), $fileid);

		$maintext .= "<hr><p>Your text has been modified - reloading";
		header("location:index.php?action=tokedit&id=$fileid&tid=$tokid");
		exit;
	
	} else {
		print "Oops"; exit;
	};
	
?>