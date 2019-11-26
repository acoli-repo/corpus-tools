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
	$more = file_get_contents("Resources/context.css");
	if ( $more ) {
		$cssfile .= "\n$more";
	} else {
		$cssfile .= "\n#tokinfo th { border: 1px solid #cccccc; background-color: #f2f2f2; }\n#tokinfo { display: none; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 300; }";
	};

	if ( $debug ) $cmdt = $cmd;

	$resxml = preg_replace("/ (id=\"$tid\")/", " \\1 highlight=\"1\"", $resxml );

	$headtext = $settings['context']['link'] or $headtext = "View TEITOK document";
	if ( $withheader ) {
		if ( $withlang ) $headtext = lgMsg("{%headtext}"); 
		$header = "<p style='margin-top: 30px;'><a href='{$baseurl}index.php?action=file&cid=$cid&tid=$tid'>$headtext</a></p>";
	};

	if ( $format == "json" ) {
		print "{'results': '$resxml'}";
	} else {
		$resxml = preg_replace("/<tok /", "<tok onmouseover=\"window.showtokinfo(this)\" onmouseout=\"window.hidetokinfo();\"", $resxml );

		$tagdef = array2json($settings['xmlfile']['pattributes']['tags']); 

		print "<style>$cssfile</style>
			$hlstyle
			<div id='tokinfo'></div>
			<img src='http://www.teitok.org/Images/ex-multiedit.png' style='display: none;' onload=\"window.showtokinfo = function(elm) { var tokinfo = document.getElementById('tokinfo'); console.log(elm); tokinfo.style.display='block';tokinfo.style.top= (elm.offsetTop + elm.offsetHeight + 3) + 'px';  tokinfo.style.left=elm.offsetLeft + 'px';   var innery = '<table style=\'width: 100%;\'><tr><th colspan=2>'+elm.innerHTML; var tagdef = $tagdef; for (var key in tagdef) { var tagval = elm.getAttribute(key); if ( tagval ) { innery += '<tr><th>' + tagdef[key].display + '<td>' + tagval;  }; }; innery += '</table>'; tokinfo.innerHTML = innery; tokinfo.style.visibility = '' }; window.hidetokinfo = function() { var tokinfo = document.getElementById('tokinfo'); tokinfo.style.visibility='hidden'; };\"/>
			<div id='mtxt'>$resxml</div>
			$header
			";
			
	};
	
	exit;

?>