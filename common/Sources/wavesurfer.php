<?php

	if ( !$_GET['cid'] && !$_GET['id'] ) fatal('No XML file selected');

	require("$ttroot/common/Sources/ttxml.php");

	# Determine if we need to cut out part of the text based on audio
	if ( getset('defaults/media/type') == "inline" && getset('xmlfile/speech/paged') !== "inherit" ) {
		$settings['xmlfile']['paged'] = array ( 
			"element" => "media",
			"display" => "audio file",
			"closed" => 1,
			"custom" => 1,
			"i18n" => 1,
		);
		if ( $_GET['pageid'] && strpos($_SERVER['HTTP_REFERER'], 'action=wavesurfer') == false )
			unset($_GET['pageid']);
	} else {
		unset($settings['xmlfiles']['paged']);
	};

	$ttxml = new TTXML("", true); # This should give an error if the XML cannot be read
	$fileid = $ttxml->fileid;
	
	$editxml = $ttxml->asXML();
	
	if ( $act == "edit" ) $editmode = " - <span class=adminpart>Edit mode</span>";
	$maintext .= "
		<h2>{%Waveform view}$editmode</h2>
		<h1>".$ttxml->title()."</h1>";
	// $maintext .= $ttxml->tableheader();
	
	$utttag = strtoupper($_GET["utt"]) or $utttag = getset('xmlfile/defaults/speechturn', "U"); // Make it possible to use <p> instead of <u>
	$alttag = strtoupper($_GET["alt"]) or $alttag = getset('xmlfile/speech/highlight');
	if ( !$alttag ) if ( $ttxml->xpath("//tok[@start]") ) $alttag = "TOK"; else $alttag = $utttag;
	$showsubtitles = $_GET["subtitles"] or $showsubtitles = getset('xmlfile/speech/showsubtitles', 0);
    $showspeaker =  $_GET["speaker"] or  $showspeaker = getset('xmlfile/speech/showspeaker', 0);

 	$tmp = "//".strtolower($utttag)."[not(@id)]"; // print $tmp; exit;
	if ( $username && $ttxml->xpath($tmp) ) {
		$maintext .= "<p class=wrong>The waveform function will not work properly since the XML file has not been (properly) numbered -
						all utterances need a unique identifier to work properly. You can renumber by clicking <a href='index.php?action=rawedit&cid=$ttxml->fileid'>here</a> 
						and then click save.</p>";
	};
	if ( $username && !$ttxml->xpath("/TEI/teiHeader") ) {
		$maintext .= "<p class=wrong>The XML does not have a proper set-up with a /TEI/teiHeader. You should resolve this before editing this file.</p>";
	};
	
	$audiourl = $ttxml->audiourl; $fldr = "Audio";
	$videourl = $ttxml->videourl; 
	if ( getset('defaults/media/type') == "inline" ) {
		if ( preg_match("/<media [^<>]+>/", $editxml, $matches) ) {
			$mediaxml = $matches[0];
			if ( preg_match("/url=\"([^\"]+)\"/", $mediaxml, $matches) ) { $audiourl = $matches[1]; };
			$mediabaseurl =  getset('defaults/media/baseurl') or $mediabaseurl =  getset('defaults/base/media', "Audio");
			if ( $audiourl != "" ) {
				if ( !strstr($audiourl, 'http') ) {
					if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
					else $audiourl =  "$mediabaseurl/$audiourl";
					# else if ( !strstr($audiourl, 'Audio') ) $audiourl = $baseurl."Audio/$audiourl"; 
				};
			};		
		};
 	} else if ( !$audiourl && $videourl ) { 
		$audiourl = $videourl; $fldr = "Video"; 
		$videobit .= "<video id=\"track\" class=\"wavesurfer\" src=\"$videourl\" controls ontimeupdate=\"checkstop();\">
				<p><i><a href='$videourl'>{%Video fragment for this text}</a></i></p>
			</video>";
	};
	# TODO : when can there be a missing audio URL?
	if ( $audiourl == "" && 1 != 2 ) fatal ("XML file $fileid has no media element providing a URL to the sound file");

	if ( getset('xmlfile/paged/element') == "media" ) {
		$pagenav = $ttxml->pagenav;
	};

	$audiolink = preg_replace("/.*(Audio|Video)\//", "", $audiourl); // Kill folder from Audio file name
	if ( $username && !preg_match("/^https?:/", $audiourl) && !file_exists("$fldr/$audiolink") ) {
		$maintext .= "<p>The $fldr file for this file ({$audiolink}) does not exist on the server - please upload it first.		
					</form>
					<p><form action='index.php?action=upload&act=save' method=post enctype=\"multipart/form-data\">
					<p>Add new file:
						<input type=file name=upfile accept=\"$accept\">
						<input name=filename type=hidden value=\"{$audiolink}\">
						<input name=type type=hidden value=\"audio\">
						<input name=goon type=hidden value=\"index.php?action=$action&cid={$_GET['cid']}\">
						<input type=submit value=Save name=submit>
					</form> ";	
		$nofile = 1;			
	};
	
	if ( getset('defaults/media/spectogram') != "" ) {
		# TODO: this does not work yet - the spectrogram shows, but is not the same width
		$spectjs = "var spect = 1;";
		$spectelm = "<div id=\"wave-spectrogram\"></div>";
		$specturl = "<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/4.4.0/plugin/wavesurfer.spectrogram.min.js\"></script>";
	};

	if ( $act == "save" ) {
	
		$newtext = $_POST['newval'];
		$newtext = preg_replace("/ style=\"background-color: rgb\(255, 255, 204\);\"/", "", $newtext);		
		
		$newxml = preg_replace("/(<text .*?<\/text>|<text>.*?<\/text>|<text[^>]*\/>)/smi", $newtext, $ttxml->xml->asXML());
		$outputxml = simplexml_load_string($newxml);
		if ( !$outputxml ) fatal("Ended up with invalid XML - refusing to save"); 
		actionlog("saved changes to  $ttxml->fileid");

		saveMyXML ( $newxml, $ttxml->fileid );
		$nexturl = urlencode("index.php?action=$action&cid=$ttxml->filename");
		print "Saved to $ttxml->fileid"; 
 			print "Your file has been saved
 					<script language=Javascript>top.location='index.php?action=renumber&cid=$ttxml->fileid&nexturl=$nexturl';</script>"; 
    	exit;
	
	} else if ( !$nofile ) {
	
		$maintext .= "
		<!-- Sound file: $audiourl -->
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/4.4.0/wavesurfer.min.js\"></script>
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/4.4.0/plugin/wavesurfer.regions.min.js\"></script>
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/4.4.0/plugin/wavesurfer.minimap.min.js\"></script>
		$specturl
		<link href=\"//fonts.googleapis.com/icon?family=Material+Icons\" rel=\"stylesheet\">

		<div id='loading'>Loading wave form: 0%</div>
	
		<div id=\"waveblock\" style='visibility: hidden;'>
			$videobit
			<div id=\"waveform\"></div>
			$spectelm
			<table width='100%'>
			<tr>
			<td>
			 {%Speed}: <span onclick=\"setspeed(0.8);\">&#8854;</span> <span id='speedtxt' val=1>100%</span> <span onclick=\"setspeed(1.2);\">&#8853;</span>

			<td><span id='timeindex' style=''></span>

			<td style='text-align: center;'>

			  <span onclick=\"wavesurfer.skipBack();\"><i class=\"material-icons\">fast_rewind</i></span>
			  <span onclick=\"playpause(this);\" id='ppbut'><i class=\"material-icons\">play_arrow</i></span>
			  <span onclick=\"wavesurfer.skipForward();\"><i class=\"material-icons\">fast_forward</i></span>
			<td  style='text-align: right;'>
			 {%Zoom}: <span onclick=\"setzoom(0.8);\">&#8854;</span> <span id='zoomtxt' val=1>200 pps</span> <span onclick=\"setzoom(1.2);\">&#8853;</span>
			</table>
		</div>

		";

		if ( $tmp = getset("defaults/topswitch") ) {
			if ( $tmp == "1" ) $tmp = "Switch visualization";
			$maintext .= "<p>{%$tmp}: ".$ttxml->viewswitch(); 
		};
		
		$setedit = "false";
		if ( count($ttxml->xpath("//".strtolower($utttag))) == 0 && $username ) {
			$editmsg = "<p>You can create a transcription for the sound file above by creating utterances. 
				Key codes: a = set start time; c = create timeslot (from a) (<a target=help href='http://www.teitok.org/index.php?action=help&id=wavesurfer#edit'>more</a>).<hr>";
		} else if ( $act == "edit"  && $username ) {
			$editmsg = "<p>Below is an editable version of the transcription; Hold ctrl to control the sound. You can resize utterances boxes (<a target=help href='http://www.teitok.org/index.php?action=help&id=wavesurfer#edit'>more</a>)
				<br><span style='color: red'>Editing here may modify the content of your transcription, Avoid using after tokenization.</span>";
		};
		if ( $editmsg ) {
			actionlog("started to edit $ttxml->fileid");
			$editable = "contenteditable"; $setedit = "true"; $editmsg .= "<hr>";
			$editbuts = "
				<div style='height: 12px; width: 100%; font-size: 10px; color: #999999; vertical-align: bottom; margin-top: 5px;' id=pospath></div>
				<hr><p><input type=button onClick='savetrans();' value='Save'> 
				<input type=button onClick='top.location=\"index.php?action=$action&cid=$ttxml->fileid\";' value='Cancel'>
				<span style='display: inline; float: right;'>
					<button onClick=\"showsource()\" id=\"sourcebutton\">Raw XML</button>
					<button onClick=\"sortutt()\">Sort utterances</button>
				</span>
				";
				
			$editbuts .= "
				<form style='display: none;' action='index.php?action=$action&act=save&cid=$ttxml->fileid' method=post id=newtab>
				<textarea style='display:none' name=newval id=newval></textarea>
				</form>";

		};
	
		if ( getset('defaults/media/skipempty') != '' ) {
			$jmpbuts .= "<p id='tostart' style='display: none;'><a onclick='jumpinit();'><i class=\"material-icons\" style='font-size: 18px; vertical-align:middle;'>play_arrow</i> {%play from start of transcription}</a></p>";
			$mintime = getset('defaults/media/skipempty') * 1;
			$morescript .= "<script language=Javascript>
				wavesurfer.on('ready', function () {
					var first = getElementByXpath(\"//*[@start]/@start\");
					if ( first.value > $mintime ) { 
						document.getElementById('tostart').style.display = 'block';
					};				
				});				
				function jumpinit( an='start' ) {
					var first = getElementByXpath(\"//*[@start]/@start\");
					wavesurfer.seekAndCenter(first.value/wavesurfer.getDuration());
					if ( an == 'start' ) {
						wavesurfer.play();
					} else {
						wavesurfer.pause();		
					};
				};
				function getElementByXpath(path) {
				  return document.evaluate(path, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
				}
			</script>";
		};
	
		$maintext .= "<hr>
			
			$pagenav
			
			<div id='fullmtxt' style='visibility: hidden;'>
			<!-- $editmsg -->
			$jmpbuts
			<div $editable id=mtxt style='margin-top: 20px; height: 0; overflow: scroll;'>$editxml</div>
			$editbuts
			</div>
	
			<div id='utteditor' style='visibility: hidden; position: absolute; top: 120px; width: 650px; padding: 20px; left: 20px; background-color: #ffffee; border: 1px solid #999999; z-index: 500;'>
			<h2>Edit utterance</h2>
			<form action='' method=post id=uttform name=uttform onsubmit=\"return changeutt(this);\">
			<p>Utterance: <input size=6 name='uttid' readonly style='border: none; background-color: rgba(0, 0, 0, 0);'> 
			- Region: <input size=6 name='start' editable=false> - <input size=6 name='end' editable=false>
			- Speaker: <input size=10 name=who>
			<p>Transcription: 
			<br><textarea name=transcription style='width: 100%; height: 100px;'></textarea>
			<input type=submit value=Save> 
			<input type=button value=Cancel onClick=\"utteditor.style.visibility='hidden';\">
			<a target=help href='http://www.teitok.org/index.php?action=help&id=wavesurfer#codes'>recommended codes</a>
			</form>
			</div>";
			
		$peaksfile = preg_replace("/\..*?$/", ".arr", preg_replace("/^.*\//", "Audio/", $audiourl));
		if ( file_exists($peaksfile) ) {
			$peaks = file_get_contents($peaksfile);
		} else { $peaks = "null"; };
		$maintext .= "<script language=Javascript>var peaks = $peaks</script>";

		if ( $editmsg ) $maintext .= "
			<div id='sourceeditor' style='visibility: hidden; position: absolute; top: 120px; width: 100%; left: 20px; z-index: 600;'>
			</div>
			<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var aceeditor = ace.edit(\"sourceeditor\");
				aceeditor.setTheme(\"ace/theme/chrome\");
				aceeditor.getSession().setMode(\"ace/mode/xml\");
			</script>";
			
		# $maintext .= "<hr><a href='index.php?action=text&cid=$ttxml->fileid'>{%Text view}</a>";
		$maintext .= "<hr>".$ttxml->viewswitch();
	
		if ( $username && $act != "edit" ) $maintext .= " &bull; <a class='adminpart' href='index.php?action=$action&act=edit&cid=$ttxml->fileid'>Edit transcripion</a>";
		# if ( $username ) $maintext .= " &bull;  <a class='adminpart' onClick='toelan(this);'>Export as ELAN</a>";
		if ( $username ) $maintext .= " &bull; <a class='adminpart' href='index.php?action=audiomanage&cid=$fileid'>Audio management</a>";
		$maintext .= " &bull;  <a href='http://www.teitok.org/index.php?action=help&id=wavesurfer' target=help>{%!help}</a>";
		
		if ( !$editmsg ) {
			$jmp = $_GET['jmp'] or $jmp = $_GET['jump'] or $jmp = $_GET['tid'];
		} else {
			// In edit mode, make utterances without a @start opaque
			$maintext .= "<style>
				u { opacity: 0.3;}
				u[start] { opacity: 1;}
			</style>";
		};
	
		$utttag = strtoupper($utttag);
		$maintext .= "<script language=Javascript>
			var soundfile = '$audiourl'; 
			var username = '$username'; 
			var tid = '$ttxml->fileid'; 
			var	jmp = '$jmp';
			var	fldr = '$fldr';
			var alttag = '$alttag';
			var utttag = '$utttag';
			var setedit = $setedit;
			var speaker = $showspeaker;
			var subtitles = $showsubtitles;
			$spectjs
			</script>";

		$maintext .= "<script src=\"$jsurl/wavesurfer.js\"></script>
			$morescript";
	};
	
?>

