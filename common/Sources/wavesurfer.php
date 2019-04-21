<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	if ( $act == "edit" ) $editmode = " - {%Edit mode}";
	$maintext .= "
		<h2>{%Waveform view}$editmode</h2>
		<h1>".$ttxml->title()."</h1>";
	// $maintext .= $ttxml->tableheader();
	
	$utttag = $settings['xmlfile']['defaults']['speechturn'] or $utttag = strtoupper($_GET["utt"]) or $utttag = "U"; // Make it possible to use <p> instead of <u>
 	$tmp = "//".strtolower($utttag)."[not(@id)]"; // print $tmp; exit;
	if ( $username && $ttxml->xml->xpath($tmp) ) {
		$maintext .= "<p class=wrong>The waveform function will not work properly since the XML file has not been (properly) numbered -
						all utterances need a unique identifier to work properly. You can renumber by clicking <a href='index.php?action=rawedit&cid=$ttxml->fileid'>here</a> 
						and then click save.</p>";
	};
	if ( $username && !$ttxml->xml->xpath("/TEI/teiHeader") ) {
		$maintext .= "<p class=wrong>The XML does not have a proper set-up with a /TEI/teiHeader. You should resolve this before editing this file.</p>";
	};
	
	$audiourl = $ttxml->audiourl; 
	if ( $audiourl == "" ) fatal ("XML file has no media element providing a URL to the sound file");

	$audiolink = preg_replace("/.*Audio\//", "", $audiourl); // Kill folder from Audio file name
	if ( $username && !preg_match("^http:", $audiourl) && !file_exists("Audio/$audiolink") ) {
		$maintext .= "<p>The audio file for this file ({$audiolink}) does not exist on the server - please upload it first.		
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

	if ( $act == "save" ) {
	
		$newtext = $_POST['newval'];
		$newtext = preg_replace("/ style=\"background-color: rgb\(255, 255, 204\);\"/", "", $newtext);		
		
		$newxml = preg_replace("/(<text .*?<\/text>|<text>.*?<\/text>|<text[^>]*\/>)/smi", $newtext, $ttxml->xml->asXML());
		$outputxml = simplexml_load_string($newxml);
		if ( !$outputxml ) fatal("Ended up with invalid XML - refusing to save"); 

		saveMyXML ( $newxml, $ttxml->fileid );
		$nexturl = urlencode("index.php?action=$action&cid=$ttxml->filename");
		print "Saved to $ttxml->fileid"; 
 			print "Your file has been saved
 					<script language=Javascript>top.location='index.php?action=renumber&cid=$ttxml->filename&nexturl=$nexturl';</script>"; 
    	exit;
	
	} else if ( !$nofile ) {
		$maintext .= "
		<!-- Sound file: $audiourl -->
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/1.4.0/wavesurfer.min.js\"></script>
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/1.4.0/plugin/wavesurfer.regions.min.js\"></script>
		<script src=\"//cdnjs.cloudflare.com/ajax/libs/wavesurfer.js/1.4.0/plugin/wavesurfer.minimap.min.js\"></script>
		<link href=\"https://fonts.googleapis.com/icon?family=Material+Icons\" rel=\"stylesheet\">

		<div id='loading'>Loading wave form: 0%</div>
	
		<div id=\"waveblock\" style='visibility: hidden;'>
			<div id=\"waveform\"></div>
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

		$editxml = $ttxml->asXML();
		
		$setedit = "false";
		if ( count($ttxml->xml->xpath("//".strtolower($utttag))) == 0 && $username ) {
			$editmsg = "<p>You can create a transcription for the sound file above by creating utterances. 
				Key codes: a = set start time; c = create timeslot (from a) (<a target=help href='http://www.teitok.org/index.php?action=help&id=wavesurfer#edit'>more</a>).<hr>";
		} else if ( $act == "edit"  && $username ) {
			$editmsg = "<p>Below is an editable version of the transcription; Hold ctrl to control the sound. You can resize utterances boxes (<a target=help href='http://www.teitok.org/index.php?action=help&id=wavesurfer#edit'>more</a>)
				<br><span style='color: red'>Editing here may modify the content of your transcription, Avoid using after tokenization.</span>";
		};
		if ( $editmsg ) {
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
	
		$maintext .= "<hr>
		
			<div id='fullmtxt' style='visibility: hidden;'>
			<!-- $editmsg -->
			<div $editable id=mtxt style='margin-top: 20px; height: 0; overflow: scroll;'>$editxml</div>
			$editbuts
			</div>
	
			<div id='utteditor' style='visibility: hidden; position: absolute; top: 120px; width: 650px; padding: 20px; left: 20px; background-color: #ffffee; border: 1px solid #999999; z-index: 500;'>
			<h2>{%Edit utterance}</h2>
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

		if ( $editmsg ) $maintext .= "
			<div id='sourceeditor' style='visibility: hidden; position: absolute; top: 120px; width: 100%; left: 20px; z-index: 600;'>
			</div>
			<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var aceeditor = ace.edit(\"sourceeditor\");
				aceeditor.setTheme(\"ace/theme/chrome\");
				aceeditor.getSession().setMode(\"ace/mode/xml\");
			</script>";
			
		$maintext .= "<hr><a href='index.php?action=file&cid=$ttxml->fileid'>{%Text view}</a>";
	
		if ( $username && !$editmsg ) $maintext .= " &bull; <a href='index.php?action=$action&act=edit&cid=$ttxml->fileid'>Edit transcripion</a>";
		if ( $username ) $maintext .= " &bull;  <a onClick='toelan(this);'>Export as ELAN</a>";
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=audiomanage&cid=$fileid'>Audio management</a>";
		$maintext .= " &bull;  <a href='http://www.teitok.org/index.php?action=help&id=wavesurfer' target=help>{%Help}</a>";
		
		if ( !$editmsg ) {
			$jmp = $_GET['jump'] or $jmp = $_GET['tid'];
		} else {
			// In edit mode, make utterances without a @start opaque
			$maintext .= "<style>
				u { opacity: 0.3;}
				u[start] { opacity: 1;}
			</style>";
		};
	
		$maintext .= "<script language=Javascript>
			var soundfile = '$audiourl'; 
			var username = '$username'; 
			var tid = '$ttxml->fileid'; 
			var	jmp = '$jmp';
			var alttag = '$utttag';
			var setedit = $setedit;
			</script>";

		$maintext .= "<script src=\"$jsurl/wavesurfer.js\"></script>";
	};
	
?>

