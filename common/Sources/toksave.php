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

		if ( $act == "delete" ) {
			$maintext .= "<h1>Delete Token</h1>
				<h2>Token value ($tokid): ".$token."</h2>";
			
			# Get the parent token so that we can go back
			$tmp = $token->xpath("parent::*"); $parent = $tmp[0]; $parentid = $parent['id'];
			
			unset($token[0]);
			
			if ( $nodetype == "d" ) { $nextaction = "tokedit"; }; 
			
			print "Deleted $tokid<hr>"; #.$xml->asXML();
			$tokid = $parentid;
			# exit;
		} else {
			$maintext .= "<h1>Save Token</h1>
				<h2>Token value ($tokid): ".$token."</h2>";

			// If we have XML in the pform and no form (or auto form), generate it
			if ( ( strpos($_POST['word'], "<", 1) && !strpos($_POST['word'], "<tok>", 1) ) && ( !$_POST['atts']['form'] || $settings['xmlfiles']['pattributes']['forms']['form']['noedit'] ) ) {
				$_POST['atts']['form'] = killxml($_POST['word']);
			};

			if ( $_POST['atts'] )
			foreach ( $_POST['atts'] as $key => $val ) {
				
				# Allow newatt to override atts 
				if ( $_POST['newatt'][$key] ) $val = $_POST['newatt'][$key];

				if ( $val != "" || $token[$key] != "" ) 
				$token[$key] = $val;
				
				if ( $_POST['simtoks'] )
				foreach ( $_POST['simtoks'] as $stk => $stv ) {
					if ( $stv == "treat" ) {
						$result = $xml->xpath("//tok[@id='$stk']"); 
						$stoken = $result[0]; # print_r($token); exit;
						if ( $val != "" || $stoken[$key] != "" ) {
							$stoken[$key] = $val;
						};
					};
				};
			};

			// Save mtok data
			if ( $_POST['matts'] ) {
			foreach ( $_POST['matts'] as $key => $val ) {
				list ( $mtid, $mkey ) = explode ( ":", $key );
				$mtok = $mtoks[$mtid];
				if ( !$mtok ) {
					$result = $xml->xpath("//mtok[@id='$mtid']"); 
					$mtok = $result[0]; 
					$mtoks[$mtid] = $mtok;
				};
				
				if ( $val != "" || $mtok[$mkey] != "" ) {
					$mtok[$mkey] = $val;
				};
			};
			};
		};		

		# Removing trailing and leading whitespaces (which should not be there anyway)
		$_POST['word'] = preg_replace("/^\s+/", "", $_POST['word']);
		$_POST['word'] = preg_replace("/\s+$/", "", $_POST['word']);

		# Add the dtoks to the xml word
		if ( is_array($_POST['dtok']) ) 
		foreach ( $_POST['dtok'] as $did => $dtok ) {
			$dtoken = simplexml_load_string($dtok);
			foreach ( $_POST['datts'] as $key => $val ) {
				list ( $did, $dkey ) = explode ( ':', $key );
				if ( $did == $dtoken['id'] ) {
					$dtoken[$dkey] = $val;
				};
			};
			$dtoktxt = str_replace('<?xml version="1.0"?>', "", $dtoken->asXML());
			$dtoktxt = preg_replace("/^\s+/", "", $dtoktxt);
			$dtoktxt = preg_replace("/\s+$/", "", $dtoktxt);
			$dtoktxt = preg_replace("/ [a-zA-Z0-9]+=\"\"/", "", $dtoktxt);
			$_POST['word'] .= $dtoktxt;
		}; # print htmlentities($_POST['word']); exit;

		# When the XML word has been changed, we need to make that change in the TXT version of the XML
		$xmlword = $token->asXML(); $xmlword = preg_replace("/<\/?tok[^>]*>/", "", $xmlword); 
		if ( $_POST['word'] && $_POST['word'] != $xmlword ) {
			$file = $xml->asXML();
			$newwrd = $_POST['word'];
			$file = preg_replace("/(<tok [^>]*?id=\"$tokid\"[^>]*?>).*?(<\/tok>)/smi", '${1}'.$newwrd.'$2', $file);
			$xml = simplexml_load_string($file);
		}; 
		
		if ( !$xml ) { 
			print "OOPS: no longer XML";
			print $file;
			exit;
		};

		saveMyXML($xml->asXML(), $fileid);

		$toktype = $token->getName();
		if ( $toktype != "tok" ) $slnk = "&elm=$toktype";
		else if ( $settings['xmlfile']['paged']  ) {
			$tokpos = strpos($file, "id=\"$tokid\"");
			$pbef = rstrpos($file, "<pb", $tokpos) or $pbef = strpos($file, "<pb");
			$tmp = substr($file, $pbef, 20); if ( preg_match("/<pb n=\"(.*?)\"/", $tmp, $matches) ) {
				$thispage = $matches[1];
				$slnk = "&page=$thispage";
			};
		};
		
		if ( !$nextaction ) { // Somehow we need to decide what the best action after saving is...
			if ( $settings['defaults']['popup'] ) $nextaction = "tokview";
			else $nextaction = "file";
		};
		$maintext .= "<hr><p>Your text has been modified - reloading";
		header("location:index.php?action=$nextaction&id=$fileid&tid=$tokid$slnk");
		exit;
	
	} else {
		print "Oops"; exit;
	};
	
	function killxml ( $word ) {
		// Create form from pform (remove del and xml)
		
		$clean = $word;
		$clean = preg_replace("/<del [^>]+>.*?<\/del>/", "", $clean);
		$clean = preg_replace("/<del>.*?<\/del>/", "", $clean);
		$clean = preg_replace("/-<lb/", "<lb", $clean);
		$clean = preg_replace("/<expan>.*?<\/expan>/", "", $clean); # This is wrong in PS
		$clean = preg_replace("/<ex>.*?<\/ex>/", "", $clean);
		$clean = preg_replace("/<[^>]+>/", "", $clean);

		return $clean;
	};
	
?>