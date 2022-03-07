<?php

	# Provide the context for a word as a REST request
	# Input: XML ID (cid), and either a Token ID (tid) or or word position in the corpus (pos)

	$cid = $_GET['cid']; $tid = $_GET['tid']; $pos = $_GET['pos'];

	# Deal with settings
	$format = $_GET['format'] or $format = $settings['context']['format'] or $format = "html";
	if ( isset($_GET['hls']) ) $hls = $_GET['hls']; else if ( isset($settings['context']['hls']) ) $hls = $settings['context']['hls']; else $hls = "1";
	if ( isset($_GET['header']) ) $withheader = $_GET['header']; else if ( isset($settings['context']['header']) ) $withheader = $settings['context']['header']; else $withheader = 1;
	if ( isset($_GET['wordh']) ) $withword = $_GET['wordh']; else if ( isset($settings['context']['wordheader']) ) $withword = $settings['context']['wordheader']; else $withword = 0; # Whether to display the word itself
	$context = $_GET['context'] or $context = $settings['context']['context'] or $context = "s";
	
	if ( $settings['context']['method'] == "xml"  ) {
	
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$xp = "//text//{$context}[.//tok[@id=\"$tid\"] | .//dtok[@id=\"$tid\"]]";
		$node = current($ttxml->xml->xpath($xp));
		$resxml = makexml($node);
	
	} else if ( $settings['context']['method'] == "xpath"  ) {

		$app = findapp("tt-xpath");
		if ( !$app ) fatal ("This function relies on tt-xpath, which is not installed on the server");
		
		$filenames = rglob("xmlfiles/*/$cid*");  // There should be only one file
		$xp = "//text//{$context}[.//tok[@id=\"$tid\"] | .//dtok[@id=\"$tid\"]]";

		$cmd = "$bindir/tt-xpath --folder='' --filename='{$filenames[0]}' --xpquery='$xp'"; 
		$resxml = shell_exec($cmd);
	
	} else {
		if ( $context + 0 == 0 && !$settings['cqp']['sattributes'][$context] ) {
			if ( $username ) fatal("Context set to $context, which is not a CQP level in this corpus. Please correct in settings.xml//context");
			$context = 5;
		};      

		$leftpos = $_GET['leftpos'];
		$rightpos = $_GET['rightpos'];
	
		if ( $leftpos == "" ) $leftpos = $pos;
		if ( $rightpos == "" ) $rightpos = $pos;

		if ( $settings['context']['nopos'] ) { $pos = $leftpos = $rightpos = ""; }; # Ignore POS if indexes might differ

		$fileid = "xmlfiles/$cid.xml"; 
		$outfolder = "cqp";
		if ( $settings['cqp']['subcorpora'] && preg_match("/(^[^\/]+)\//", $cid, $matches) ) {
			$subfolder = $matches[1];
			$outfolder .= "/$subfolder";
			$subcorpus = "-$subfolder";
		};
		$xidxcmd = findapp("tt-cwb-xidx");
	
		# If we do not have a left/right position 
		if ( $leftpos == "" ) {
			if ( $tid ) {
				# lookup the position in CQP
				include ("$ttroot/common/Sources/cwcqp.php");
				$cqpcorpus = strtoupper($settings['cqp']['corpus'].$subcorpus); # a CQP corpus name ALWAYS is in all-caps
				$cqp = new CQP();
				$cqp->exec($cqpcorpus); // Select the corpus
				$cqp->exec("set PrettyPrint off");
				$cqp->exec("Matches = [id=\"$tid\"] :: match.text_id=\"$fileid\"");
				$tmp = $cqp->exec("tabulate Matches match, match text_id");
				list ( $pos, $fileid ) = explode( "\t", $tmp ); 
				$leftpos = $pos; $rightpos = $pos;
			} else {
				print "No position or token ID indicated"; exit;
			};
		};
	
		if ( preg_match("/^\d+$/", $context) ) {
			$leftpos -= $context;
			$rightpos += $context;
		} else {
			$expand = "--expand=$context";
		};

		# If we do not have a tid, look it up (so that we can highlight the word)
		if ( !$tid ) {
			include ("$ttroot/common/Sources/cwcqp.php");
			$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
			$cqp = new CQP();
			$cqp->exec($cqpcorpus); // Select the corpus
			$cqp->exec("set PrettyPrint off");
			$lupos = $pos or $lupos = $leftpos;
			$cqp->exec("Matches = [] :: match=$lupos");
			$tid = chop($cqp->exec("tabulate Matches match id"));
		};	
	
		if ( $hls ) $hlstyle = "<style>tok[highlight] { background-color: #ffee77; }</style>";

		$cmd = "$xidxcmd --filename='$fileid' --cqp='$outfolder' $expand $leftpos $rightpos";
		$resxml = shell_exec($cmd);
	};
	
	$cssfile = "";
	if ( $sharedfolder ) $cssfile .= file_get_contents("$sharedfolder/Resources/xmlstyles.css");
	$cssfile .= file_get_contents("Resources/xmlstyles.css");
	$more = file_get_contents("Resources/context.css");
	if ( !$more && $sharedfolder ) $more = file_get_contents("$sharedfolder/Resources/context.css");
	if ( $more ) {
		$cssfile .= "\n$more";
	} else {
		$cssfile .= "\n#tokinfo th { border: 1px solid #cccccc; background-color: #f2f2f2; }\n#tokinfo { display: none; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 300; }";
	};

	if ( $debug ) $cmdt = $cmd;

	$resxml = preg_replace("/.*<\/teiHeader>/", "", $resxml);

// 	$resxml = preg_replace("/ (id=\"$tid\")/", " \\1 highlight=\"1\"", $resxml );
	$resxml = str_replace(" id=\"$tid\"", " hl=\"1\" id=\"$tid\"", $resxml);
	if ( preg_match("/d-(.*)-\d+/", $tid, $matches) ) {
		$tdid = "w-".$matches[1];
		$resxml = str_replace(" id=\"$tdid\"", " hl=\"2\" id=\"$tdid\"", $resxml);
	};

	$headtext = $settings['context']['link'] or $headtext = "View TEITOK document";
	if ( $withheader ) {
		if ( $withlang ) $headtext = lgMsg("{%headtext}"); 
		$cidx = $cid; if ( substr($cidx, -4) != ".xml" ) $cidx .= ".xml";
		$header = "<p class='linktxt'><a href='$baseurl/index.php?action=file&cid=$cidx&tid=$tid'>$headtext</a></p>";
	};
	
	# Protect empty elements
	$resxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $resxml );

	if ( $format == "json" ) {
		print "{'results': '$resxml'}";
	} else {
		$resxml = preg_replace("/<tok /", "<tok onmouseover=\"window.showtokinfo(this)\" onmouseout=\"window.hidetokinfo();\" ", $resxml );

		$alltags = array_merge($settings['xmlfile']['pattributes']['forms'], $settings['xmlfile']['pattributes']['tags']);
		$tagdef = array2json($alltags); 

		if ( $withword ) $wordheader = "var innery += '<tr><th colspan=2>'+elm.innerHTML;";

		print "
			<div id='teitokbox' style='width: 100%;'>
			<style>$cssfile</style>
			$hlstyle
			<div id='tokinfo'></div>
			<img src='http://www.teitok.org/Images/ex-multiedit.png' style='display: none;' onload=\"window.showtokinfo = function(elm) { var tokinfo = document.getElementById('tokinfo'); tokinfo.style.display='block';  var innery = '<table style=\'width: 100%;\'>'; $wordheader var tagdef = $tagdef; for (var key in tagdef) { var tagval = elm.getAttribute(key); if ( tagval ) { innery += '<tr><th>' + tagdef[key].display + '<td>' + tagval; tokinfo.style.top= (elm.offsetTop + elm.offsetHeight + 3) + 'px'; var ttw = document.getElementById('teitokbox').clientWidth;  var leftpos = Math.min(elm.offsetLeft, ttw - tokinfo.offsetWidth); tokinfo.style.left = leftpos + 'px'; }; }; innery += '</table>'; tokinfo.innerHTML = innery; tokinfo.style.visibility = '' }; window.hidetokinfo = function() { var tokinfo = document.getElementById('tokinfo'); tokinfo.style.visibility='hidden'; };\"/>
			<div id='mtxt'>$resxml</div>
			$header
			</div>
			";
			
	};
	
	exit;

?>