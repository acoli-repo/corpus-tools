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
	
	if ( $cardid && $_GET['php'] ) { 
		# In PHP this gets too slow for larger XML files - replaced by Perl (below)
		
		if ( !file_exists("$xmlfolder/$cardid") ) { print "No such letter"; exit; };
		
		$file = file_get_contents("$xmlfolder/$cardid"); 
		$newxml = simplexml_load_string($file);
		
		if ( !$newxml ) {
			print "Something went wrong with the XML - please contact the operator. Created XML:
				$rawishtext
				
				<hr>
				
				$file";
			exit;
		};
		
		# Number the tokens
		$wcnt = 1;
		$result = $newxml->xpath("//$mtxtelement//tok"); 
		foreach ( $result as $node ) {
			$node['id'] = "w-$wcnt"; $dcnt = 1;
			$result2 = $node->xpath("dtok"); 
			foreach ( $result2 as $dnode ) {
				$dnode['id'] = "d-$wcnt-$dcnt"; $dcnt++;
			};
			$wcnt++; 
		};

		# Number the paragraphs
		$wcnt = 1;
		$result = $newxml->xpath("//$mtxtelement//p"); 
		foreach ( $result as $node ) {
			$node['id'] = "p-$wcnt"; $wcnt++;
		};

		# Number the sentences
		$wcnt = 1;
		$result = $newxml->xpath("//$mtxtelement//s | //$mtxtelement//l"); 
		foreach ( $result as $node ) {
			$node['id'] = "s-$wcnt"; $wcnt++;
		};

		# Number the empty elements
		$wcnt = 1;
		$result = $newxml->xpath("//$mtxtelement//lb | //$mtxtelement//pb  | //$mtxtelement//cb | //$mtxtelement//gap | //$mtxtelement//deco"); 
		foreach ( $result as $node ) {
			$node['id'] = "e-$wcnt"; $wcnt++;
		};

		# print "MYXML: ".$newxml->asXML(); exit;
		saveMyXML($newxml->asXML(), $cardid);

		if ( $_GET['tid'] )
			$nexturl = "index.php?action=tokedit&cid=$cardid&tid={$_GET['tid']}";
		else 
			$nexturl = "index.php?action=edit&id=$cardid";
		$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
		$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
		
	} else if ( $cardid )  {

		# Build the UNIX command
		if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot"; };
		$cmd = "/usr/bin/perl $scrt/common/Scripts/xmlrenumber.pl --filename='xmlfiles/$fileid' ";
		# print $cmd; exit;
		$res = shell_exec($cmd);
		for ( $i=0; $i<1000; $i++ ) { $n = $n+(($i+$n)/$i); }; # Force a bit of waiting...
		
		if ( $_GET['tid'] )
			$nexturl = "index.php?action=tokedit&cid=$cardid&tid={$_GET['tid']}";
		else 
			$nexturl = "index.php?action=edit&id=$cardid";
		$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
		$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
		
	} else {
	
		print "oops"; exit;
	
	};

?>