<?php

	# Provide the context for a word as a REST request
	# Input: XML ID (cid), and either a Token ID (tid) or or word position in the corpus (pos)

	$cid = $_GET['cid']; $tid = $_GET['tid']; $pos = $_GET['pos'];

	# Deal with settings
	$color = $_GET['color'] or $color = $settings['context']['color'] or $color = "#ffffaa";
	$format = $_GET['format'] or $format = $settings['context']['format'] or $format = "html";
	if ( isset($_GET['hls']) ) $hls = $_GET['hls']; else if ( isset($settings['context']['hls']) ) $hls = $settings['context']['hls']; else $hls = "1";
	if ( isset($_GET['header']) ) $withheader = $_GET['header']; else if ( isset($settings['context']['header']) ) $withheader = $settings['context']['header']; else $withheader = "1";
	$context = $_GET['context'] or $context = $settings['context']['context'] or $context = "s";

	$leftpos = $_GET['leftpos'];
	$rightpos = $_GET['rightpos'];
	
	if ( !$leftpos ) $leftpos = $pos;
	if ( !$rightpos ) $rightpos = $pos;

	$fileid = "xmlfiles/$cid.xml"; $outfolder = "cqp"; $leftpos = $pos; $expand = "--expand=$context";
	$xidxcmd = findapp("tt-cwb-xidx");

	$leftpost = $pos;
	
	# If we do not have a tid, look it up
	if ( !$tid ) {
		
	};	
	
	
	if ( $hls ) $hlstyle = "<style>tok[highlight] { background-color: #ffee77; }</style>";

	$cmd = "$xidxcmd --filename=$fileid --cqp='$outfolder' $expand $leftpos $rightpos";
	$resxml = shell_exec($cmd);

	$cssfile = file_get_contents("Resources/xmlstyles.css");

	if ( $debug ) $cmdt = $cmd;

	$resxml = preg_replace("/ (id=\"$tid\")/", " \\1 highlight=\"1\"", $resxml );

	if ( $withheader ) $header = "<p><a href='{$baseurl}index.php?action=file&cid=$cid&tid=$tid'>TEITOK Context</a></p>";

	if ( $format == "json" ) {
		print "{'results': '$resxml'}";
	} else {
		print "<style>$cssfile</style>
			$hlstyle
			$header
			<div id='mtxt'>$resxml</div>
			";
	};
	
	exit;

?>