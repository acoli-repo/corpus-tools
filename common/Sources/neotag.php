<?php
	// Script to run Neotag over an XML file
	// Requires a compiled version of Neotag
	// (c) Maarten Janssen, 2015

	check_login();		
	$debug = $_GET['debug'];
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );

	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 
	ob_end_flush();

	# if ( !$_GET['style'] == "php" ) { $style = "perl"; };

	$file = file_get_contents("$xmlfolder/$fileid"); 

	if ( strstr($file, 'pos=') ) {
		fatal("File has already been tagged");
	};

	$xml = simplexml_load_string($file);
	if ( !$xml ) { fatal ( "Failing to read/parse $fileid" ); };

	# Figure out which parameters to use for this XML file
	foreach ( $settings['neotag']['parameters'] as $key => $item ) {
		$result = $xml->xpath($item['restriction']); 
		if ( count($result) && strlen($key) > strlen($ntcond) ) {
			$ntcond = $item['restriction'];
			$ntfolder = $item['folder'];
		};
	};
	if ( !$ntfolder ) {
		fatal ("No appropriate Neotag parameters were found for this file" );
	};
	if ( $debug ) print "Using parameter folder $ntfolder for $ntcond";
	
	if ( $fileid && $style == "perl" ) {
		
		## Use the POS tagger to tag a text in this corpus
		print "<html> <link rel=\"stylesheet\" type=\"text/css\" href=\"http://alfclul.clul.ul.pt/teitok/common/cw.css\"><body><h1>Tagging a file</h1>";
		
		
		$cmd = "/usr/bin/perl {$thisdir}/../common/Scripts/xml-tag.pl --filename='xmlfiles/$fileid' --folder='$ntfolder' > /dev/null & ";
		print "<p><b>Tagging the file</b>";
		print "<p>".$cmd;
		print "<pre>";
		passthru($cmd);
		print "</pre>";
		
		print "<p>Tagging process might take time - tagged file will be avoilable <a href='index.php?action=edit&cid=$fileid'>here</a> (replacing the old file)";

		exit;
		
	} else 
	if ( $fileid ) {
		## Use the POS tagger to tag a text in this corpus - directly in PHP
		
		print "<html> <link rel=\"stylesheet\" type=\"text/css\" href=\"http://alfclul.clul.ul.pt/teitok/common/cw.css\"><body><h1>Tagging a file</h1>";

		$txtfile = "$thisdir/tmp/tagtemp_$xmlid";
		print "<p><b>Verticalizing file into $txtfile</b>";
		if ( file_exists("Resources/neotag.xslt" ) ) 
			$cmd = "/usr/bin/xsltproc --novalid $thisdir/Resources/neotag.xslt $thisdir/xmlfiles/$fileid > $txtfile";
		else 
			$cmd = "/usr/bin/xsltproc --novalid $thisdir/Resources/verticalize.xslt $thisdir/xmlfiles/$fileid | perl -e 'while (<>) { s/(\\S*)\\t(\\S*)/\\2\\t\\1/; print; }' > $txtfile";
		print "<p>$cmd";
		if (!$debug) shell_exec($cmd);

		$tagfile = "$thisdir/tmp/tagged_$xmlid";
		print "<p><b>Tagging file into $tagfile</b>";
		$cmd = "/bin/cat $txtfile | $thisdir/../bin/neotag --linenr --featuretags --forcelemma --transsmooth=0.1 --endretry=1 --folder='$ntfolder' > $tagfile";
		print "<p>$cmd";
		print "<p style='color: #aaaaaa'>[wait - this might take a while - this page will continue to load after tagging is completed]";
		if (!$debug) shell_exec($cmd);
		
		print "<p><b>Reading tags into XML</b></p>";
		$cmd = "perl $thisdir/../common/Scripts/tagload.pl --xmlfile='$xmlfolder/$fileid' --tagfile='$tagfile'";
		print "<p>$cmd";
		if (!$debug) $tagcnt = shell_exec($cmd);
		
		if ( $tagcnt ) {
			print "<p>Tagging process completed - tagged file available <a href='index.php?action=edit&cid=$fileid'>here</a>";
			# saveMyXML($xml->asXML(), $fileid); # This writes the original back :)
		} else {
			print "<p>Tagging process failed ($tag tags tagged)";
		};
		
		# print $xml->asXML();
		exit;
		
	};
?>
