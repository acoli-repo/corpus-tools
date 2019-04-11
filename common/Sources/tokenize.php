<?php
	// Script to tokenize an XML file (actually done in Perl)
	// (c) Maarten Janssen, 2015

	check_login();
	header('Content-type: text/html; charset=utf-8');
	// mb_internal_encoding("UTF-8");

	// Character-level tags can be defined within each project, but this is the default list
	if ( !$settings['defaults']['chartags'] ) $chartags = Array ( "add", "del", "supplied", "expan", "abbr", "hi", "lb", "pb", "cb", "ex" ); 
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'];
	
	if ( $fileid ) { 

		if ( preg_match("/\/([a-z0-9]+)$/i", $mtxtelement, $matches ) ) {
			$mtxtelm = $matches[1];
		} else {
			$mtxtelm = "text";
		};

		if ( $settings['xmlfile']['linebreaks'] ) { $lbcmd = " --linebreaks "; };
		if ( $_GET['s'] ) { $lbcmd .= " --s={$_GET['s']} "; }; # Sentence split

		if ( $settings['defaults']['tokenizer']['sentences'] ) $lbcmd .= " --sent=1";

		# Build the UNIX command
		if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot"; };
		$cmd = "/usr/bin/perl $scrt/common/Scripts/xmltokenize.pl --mtxtelm=$mtxtelm --filename='xmlfiles/$fileid' $lbcmd ";
		# print $cmd; exit;
		$res = shell_exec($cmd);
		for ( $i=0; $i<1000; $i++ ) { $n = $n+(($i+$n)/$i); }; # Force a bit of waiting...
		
		if ( strpos($res, "Invalid XML") !== false ) { 
			$maintext .= "<p>Tokenization failed - potentially due to the use of namespaces in the XML file, which are not supported by the Perl tokenization module";
		} else if (strpos($res, "XML got messed up") !== false ) { 
			$error = shell_exec("xmllint /tmp/wrong.xml");
			$maintext .= "<p>Tokenization failed - probably due to a complex cluster of not token-based XML annotations";
			if ( $error ) $maintext .= " - below is an output of the error analysis, which might suggest where the problem is located.
				<pre>$error</pre>";
		} else {		
			if ( $_GET['tid'] )
				$nexturl = "index.php?action=tokedit&cid=$fileid&tid={$_GET['tid']}";
			else 
				$nexturl = "index.php?action=file&id=$fileid";
			$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
			$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
		};
		
	} else {
	
		fatal("Oops - no filename has been provided");
	
	};

?>