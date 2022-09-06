<?php
	// Script to insert and remove <tok> 
	// from an XML file
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
	$nextid = $tokid;	
		
	if ( $fileid && $tokid ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			print "No such XML File: $xmlfolder/$fileid"; 
			exit;
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		
		# In which direction to place the node - TODO: add those defaults?
		$nodedir = $_GET['dir']; # or $nodedir = "before";
		# Where to place the node
		$nodepos = $_GET['pos']; # or $nodepos = "left";
		# What node to place
		$nodetype = $_GET['node']; # or $nodetype = "tok";
		
		if ( $nodetype == "par" ) {
			$newnode = "<sb/>";
			$nodepos = "right";
		} else if ( $nodetype == "dtok" ) {
			$newnode = "<dtok/>";
			$nodepos = "inside";
		} else if ( $nodetype == "lb" ) {
			$newnode = "<lb/>";
			$nodepos = "left";
		} else if ( $nodetype == "s" ) {
			$newnode = "<s/>";
			$nodepos = "left";
		} else if ( $nodetype == "note" ) {
			$newnode = "<note id=\"torenew\"/>";
			$nodedir = "after";
			$nodepos = "left";
			$goto = urlencode("index.php?action=elmedit&cid=$fileid&tid=newid");
		} else {
			## By default, insert a token with no written form
			$newnode = "<tok><ee/></tok>";
		};
		
		$nodetype = substr($tokid,0,1);
	
		if ( $nodepos == "inside" ) {
			if ( !$newnode )  { $newnode = "<dtok/>"; };
			
			preg_match( "/(<tok ([^>]*)id=\"$tokid\"(.*?))<\/tok>/", $file, $matches );
			$fromtok = $matches[0]; $totok = $fromtok;
			$dtoknr = $_GET['cnt'] or $dtoknr = 1;
			for ( $i=1; $i<=$dtoknr; $i++ ) {
				$totok = str_replace('</tok>', $newnode.'</tok>', $totok);
			}; $fromtok = preg_quote($fromtok, '/');
			
			$file = preg_replace ( "/$fromtok/", "$totok", $file );
			$ntid = $tokid;
		
		} else if ( $nodedir == "before" ) {
		
			if ( $nodetype == "w" ) {
				if ( $nodepos == "left" ) {
					$file = preg_replace ( "/ (<tok ([^>]*)id=\"$tokid\")/", "$newnode $1", $file );
				} else if ( $nodepos == "right" ) {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\")/", "$newnode$1", $file );
				} else if ( $nodepos == "glued" ) {
					$file = preg_replace ( "/ (<tok ([^>]*)id=\"$tokid\")/", "$newnode$1", $file );
				} else {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\")/", "$newnode $1", $file );
				};
			} else {
				if ( $nodepos == "left" ) {
					$file = preg_replace ( "/ (<([^>]*) id=\"$tokid\")/", "$newnode $1", $file );
				} else if ( $nodepos == "right" ) {
					$file = preg_replace ( "/(<([^>]*) id=\"$tokid\")/", "$newnode$1", $file );
				} else if ( $nodepos == "glued" ) {
					$file = preg_replace ( "/ (<([^>]*) id=\"$tokid\")/", "$newnode$1", $file );
				} else {
					$file = preg_replace ( "/(<([^>]*) id=\"$tokid\")/", "$newnode $1", $file );
				};
			};
			## Move the edit to the previous token
			if ( preg_match("/<tok/", $newnode) ) {
				// just stay if we have token - it will get right after renumbering
				$ntid = $tokid;
			} else {
				// find the next token, which will turn correct after renumbering
				if ( preg_match ("/ id=\"$tokid\".*?<tok.*? id=\"(.*?)\"/", $file, $matches) ) {
					$ntid = $matches[1];
				};
			};
		
		} else if ( $nodedir == "after" ) {	

			if ( $nodetype == "w" ) {
				if ( $nodepos == "left" ) {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\".*?<\/tok>)/", "$1$newnode", $file );
				} else if ( $nodepos == "right" ) {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\".*?<\/tok>) /", "$1 $newnode", $file );
				} else if ( $nodepos == "glued" ) {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\".*?<\/tok>) /", "$1$newnode", $file );
				} else {
					$file = preg_replace ( "/(<tok ([^>]*)id=\"$tokid\".*?<\/tok>)/", "$1 $newnode", $file );
				};
				
				// just increase the number if we have token
				$ntid = $nodetype.'-'.(substr($tokid,2)+1);
			} else {
				if ( $nodepos == "left" ) {
					$file = preg_replace ( "/(<*([^>]*) id=\"$tokid\"[^>]*\/>)/", "$1$newnode", $file );
				} else if ( $nodepos == "right" ) {
					$file = preg_replace ( "/(<*([^>]*) id=\"$tokid\"[^>]*\/>) /", "$1 $newnode", $file );
				} else if ( $nodepos == "glued" ) {
					$file = preg_replace ( "/(<*([^>]*) id=\"$tokid\"[^>]*\/>) /", "$1$newnode", $file );
				} else {
					$file = preg_replace ( "/(<*([^>]*) id=\"$tokid\"[^>]*\/>)/", "$1 $newnode", $file );
				};
				if ( preg_match ("/ id=\"$tokid\".*?<tok.*? id=\"(.*?)\"/", $file, $matches) ) {
					$ntid = $matches[1];
				};
			};
		};
					
		if ( $goto ) $newurl = "&nexturl=$goto";
		$nexturl = "index.php?action=renumber&cid=$fileid&tid=$nextid&dir=$nodedir$newurl";
					
		saveMyXML($file, $fileid);
				
		$maintext .= "<hr><p>Your tok has been inserted - reloading to <a href='$nexturl'>the edit page</a>";
		$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";		
	
	} else {
		print "<p>Please go play outside"; exit;
	};
	
?>