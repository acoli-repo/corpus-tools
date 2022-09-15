<?php
	// Script to renumber an XML file
	// Has two modes: the original mode to renumber in PHP
	// and the newer mode using Perl (default, faster, more reliable)
	// (c) Maarten Janssen, 2015

	check_login();
	header('Content-type: text/html; charset=utf-8');
	// mb_internal_encoding("UTF-8");
	
	$cardid = $_POST['id'] or $cardid = $_GET['id'] or $cardid = $_GET['cid'];
	$fileid = $cardid;
	
	$perlapp = findapp("perl");
	
	if ( $cardid )  {

		if ( $_GET['xx'] ) $xxx = " --xx=".preg_replace("/[^a-zA-Z0-9]/", "", $_GET['xx']);

		# Build the UNIX command that does the actual renumbering
		if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot"; };
		$cmd = "$perlapp $scrt/common/Scripts/xmlrenumber.pl $xxx --filename='xmlfiles/$fileid' ";
		# print $cmd; exit;
		$res = shell_exec($cmd);
		preg_match("/NEWID: (.*)/", $res, $matches); $newid = $matches[1];
		for ( $i=0; $i<1000; $i++ ) { $n = $n+(($i+$n)/$i); }; # Force a bit of waiting...
		
		if ( $_GET['nexturl'] ) {
			$nexturl = str_replace('newid', $newid, $_GET['nexturl']);
		} else if ( $_GET['tid'] ) {
			$newtid = $_GET['tid'];
			$posdir = $_GET['dir'];
			if ( $posdir ) {
				# Find the token to the left or right
				if ( !$forcerenum || $posdir == "after" ) { # renumbering + before will get the same tokid
					require ("$ttroot/common/Sources/ttxml.php");
					$ttxml = new TTXML();
					$oldnode = current($ttxml->xml->xpath("//tok[@id='{$_GET['tid']}']"));
					if ( $oldnode ) {
						if ( $posdir == "after" ) {
							$newnode = current($oldnode->xpath("following::tok"));
						} else {
							$newnode = current($oldnode->xpath("preceding::tok[1]"));
						};
					} else {
						fatal("No such node: {$_GET['tid']}"); 
					};
					if ( $newnode ) { 
						$newtid = $newnode['id'];
					} else {
						fatal("Oops - creation of the new node failed");
					};
				};
			};
			$nexturl = "index.php?action=tokedit&cid=$cardid&tid=$newtid";
		} else {
			$nexturl = "index.php?action=file&id=$cardid";
		};
		$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
		$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
		
	} else {
	
		fatal("No XML file selected"); 
	
	};

?>