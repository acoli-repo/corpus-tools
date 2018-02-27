<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	$title = $ttxml->title();
	$editxml = $ttxml->asXML();

	$maintext .= "
		<style>lb::before { content: '\A'; } lb { white-space:pre; }</style>
		<div id='viewport' style='border: 1px solid #666666; background-color: black; position: fixed; top: 0;'>
			<table width='100%' height='500px'>
		   <colgroup>
			<col  style='width: 50%'>
			<col  style='width: 50%'>
		  </colgroup>	
		  		
			<tr style='height: 30px;'>
			<td colspan=2>
				<div id='title' style='color: white; font-weight: bold; font-size: 24px;'>$title</div>
				<div id='toolbar' style='float: right; color: white; '>
					<select id='pagesel' onChange='setpage(this.value);'></select> &nbsp;
					<span onClick='fullscreen();'>fullscreen</span></div>
			</tr>
			<tr>
			<td><div id='facsview' style='background-color: black; height: 470px; text-align: center; overflow: hidden; vertical-align: middle;'></div>
			<td><div id='textview' style='background-color: white; height: 470px; overflow: scroll; padding-left: 20px; padding-right: 20px;'></div>
			</tr>
			</table>
		</div>
		<div id='fulltext' style='display: none;'>$editxml</div>
		<script language=Javascript src='$jsurl/pageflow.js'></script>
		";

?>