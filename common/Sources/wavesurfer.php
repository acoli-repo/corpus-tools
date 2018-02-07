<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	$maintext .= "
		<h2>$fileid</h2>
		<h1>{%Waveform view}</h1>";
	// $maintext .= $ttxml->tableheader();
	
	$soundfile = current($ttxml->xml->xpath("//media[@mimeType=\"audio/wav\"]/@url"));
	$mp3 = str_replace(".wav", ".mp3", $soundfile);
	if ( file_exists($mp3) ) $soundfile = $mp3;

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
		 {%Speed}: <span onclick=\"setspeed(0.8);\">-</span> <span id='speedtxt' val=1>100%</span> <span onclick=\"setspeed(1.2);\">+</span>

		<td><span id='timeindex' style=''></span>

		<td style='text-align: center;'>

		  <span onclick=\"wavesurfer.skipBack();\"><i class=\"material-icons\">fast_rewind</i></span>
		  <span onclick=\"playpause(this);\"><i class=\"material-icons\">play_arrow</i></span>
		  <span onclick=\"wavesurfer.skipForward();\"><i class=\"material-icons\">fast_forward</i></span>
		<td  style='text-align: right;'>
		 {%Zoom}: <span onclick=\"setzoom(0.8);\">-</span> <span id='zoomtxt' val=1>200 pps</span> <span onclick=\"setzoom(1.2);\">+</span>
		</table>
	</div>

	";

	//$editxml = current($ttxml->xml->xpath("//text"))->asXML();
	$editxml = $ttxml->asXML();
	$maintext .= "<hr><div id=mtxt style='margin-top: 20px; height: 0; overflow: scroll;'>$editxml</div>
	
		<hr><a href='index.php?action=file&cid=$ttxml->fileid'>{%text view}</a>";

	$jmp = $_GET['jump'] or $jmp = $_GET['tid'];

	$maintext .= "<script language=Javascript>
		var soundfile = '$soundfile'; 
		var	jmp = '$jmp';
		</script>";

	$maintext .= "<script src=\"$jsurl/wavesurfer.js\"></script>";

?>

