<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	$maintext .= "
		<h2>$fileid</h2>
		<h1>{%Waveform view}</h1>";
	// $maintext .= $ttxml->tableheader();
	
	$soundfile = current($ttxml->xml->xpath("//media[contains(@mimeType, \"audio\")]/@url"));
	if ( !$soundfile ) $soundfile = current($ttxml->xml->xpath("//media/@url")); // maybe there is no mimeType
	if ( !$soundfile ) fatal ("XML file has no media element providing a URL to the sound file");
	$mp3 = str_replace(".wav", ".mp3", $soundfile);
	if ( file_exists($mp3) ) $soundfile = $mp3;

	if ( $act == "save" ) {
	
		$newtext = $_POST['newval'];
		$newtext = preg_replace("/ style=\"background-color: rgb\(255, 255, 204\);\"/", "", $newtext);		
		
		$newxml = preg_replace("/(<text .*?<\/text>|<text>.*?<\/text>|<text\/>)/smi", $newtext, $ttxml->xml->asXML());
		$outputxml = simplexml_load_string($newxml);
		if ( !$outputxml ) fatal("Ended up with invalid XML - refusing to save"); 

		saveMyXML ( $newxml, $ttxml->fileid );
		print "Saved to $ttxml->fileid";
			print "Your file has been saved
					<script language=Javascript>top.location='index.php?action=$action&cid=$ttxml->filename';</script>"; 
    	exit;
	
	} else {
		$maintext .= "
		<!-- Sound file: $soundfile -->
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

		//$editxml = current($ttxml->xml->xpath("//text"))->asXML();
		$editxml = $ttxml->asXML();

		$utttag = $settings['xmlfile']['defaults']['speechturn'] or $utttag = strtoupper($_GET["utt"]) or $utttag = "U"; // Make it possible to use <p> instead of <u>
		
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
			$editbuts = "<hr><p><input type=button onClick='savetrans();' value='Save'> &bull; <input type=button onClick='window.reload();' value='Cancel'>";
			$editbuts .= "
				<form style='display: none;' action='index.php?action=$action&act=save&cid=$ttxml->fileid' method=post id=newtab>
				<textarea style='display:none' name=newval id=newval></textarea>
				</form>";

		};
	
		$maintext .= "<hr>
		
			<div id='fullmtxt' style='visibility: hidden;'>
			$editmsg
			<div $editable id=mtxt style='margin-top: 20px; height: 0; overflow: scroll;'>$editxml</div>
			$editbuts
			</div>
	
			<div id='utteditor' style='visibility: hidden; position: absolute; top: 270px; width: 650px; padding: 20px; left: 200px; background-color: #ffffee; border: 1px solid #999999;'>
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
			</div>
	
			<hr><a href='index.php?action=file&cid=$ttxml->fileid'>{%text view}</a>";
	
		if ( $username && !$editmsg ) $maintext .= " &bull; <a href='index.php?action=$action&act=edit&cid=$ttxml->fileid'>edit transcripion</a>";

		
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
			var soundfile = '$soundfile'; 
			var username = '$username'; 
			var tid = '$ttxml->fileid'; 
			var	jmp = '$jmp';
			var alttag = '$utttag';
			var setedit = $setedit;
			</script>";

		$maintext .= "<script src=\"$jsurl/wavesurfer.js\"></script>";
	};
	
?>

