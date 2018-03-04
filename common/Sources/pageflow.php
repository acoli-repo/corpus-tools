<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	$title = $ttxml->title();
	$editxml = $ttxml->xml->asXML();

	#Build the view options	
	foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
		$formcol = $item['color'];
		# Only show forms that are not admin-only
		if ( $username || !$item['admin'] ) {
			if ( !$bestform ) $bestform = $key; 
			if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
			$ikey = $item['inherit'];
			if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
				$viewopts .= " <option id='but-$key' value='$key'>{%".$item['display']."}</option>";
				$fbc++;
			};
			if ( $key != "pform" ) { 
				if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
				$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
			};
		} else if ( $showform == $key ) $showform = $bestform;
	};
	# Check whether we HAVE the form to show - or switch back
	if ( !strstr($editxml, " $showform=") 
		&& !$settings['xmlfile']['pattributes']['forms'][$showform]['subtract']
		) { $showform = $bestform;};
	
	
	# Only show text options if there is more than one form to show
	if ( $fbc > 1 ) {
		$viewoptions .= "<p>{%Text view}: <select onChange='setForm(this.value)'>$viewopts</select>"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>
		$viewoptions .= " &nbsp; <button id='btn-col' style='background-color: #ffffff;' title='{%color-code form origin}' onClick=\"togglecol();\">{%Colors}</button> ";
	};
	
	# Some of these checks work after the first token, so first find the first token
	$tokpos = strpos($editxml, "<tok"); 
	
	if ( !$nobreakoptions && strpos($editxml, "<lb", $tokpos) ) {
		$showoptions .= "
			- {%Lines}:
				<select onChange='dolines(this.value);'>
				<option value='format'>{%New line}</option>
				<option value='bar'>{%Vertical bar}</option>
				<option value='hide'>{%Don't show}</option>
				</select>";
	};

	
	if ( !$username ) $noadmin = "(?![^>]*admin=\"1\")";
	

	$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
	$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	if ( strstr("interpret", $settings['xmlfile']['defaultview']) != -1 ) $settingsdefs .= "var interpret = true;";
	$header = $ttxml->tableheader("pageflow,long", false);
	$viewsels = $ttxml->viewswitch("select");
	
	$maintext .= "
		<link href=\"https://fonts.googleapis.com/icon?family=Material+Icons\" rel=\"stylesheet\">
		<script>
			var username = '$username';
			$settingsdefs
			var nofacs = true;
			var tid = '$ttxml->fileid'; 
		</script>
		<div id='pageflow' style='z-index: 150;'>
		<div id='info' style='display: none; position: fixed; z-index: 200; opacity: 0.9; overflow: scroll;'>
			<span title='{%close}' style='float: right;' onClick='toc.style.display=\"none\";'><i class=\"material-icons\">close</i></span>
			<div style='padding: 20px;'>
			<h2>$ttxml->title</h2>
			$header
			</div>
		</div>
		<div id='viewport' style='z-index: 160; position: fixed; top: 0; width: 100%;'>
		<table width='100%' height='500px' style='table-layout: fixed;' id=viewtable>
		   <colgroup>
			<col id='col1' style='width: 50%'>
			<col style='width: 1px; background-color: white;'>
			<col id='col2' style='width: 50%'>
		  </colgroup>	
			<tr style='height: 30px; overflow: hidden;'>
			<td colspan=3>
				<div id='title' style=''>
					$title
					<span id='toolbar' style='float: right; color: white; vertical-align: top;'>
						<span title='{%zoom out}' onClick='zoom(-1);'><i class=\"material-icons\">zoom_out</i></span>
						<span title='{%zoom in}' onClick='zoom(1);' style='margin-right: 5px'><i class=\"material-icons\">zoom_in</i></span>
						<span title='{%previous page}' onClick='switchpage(-1);'><i class=\"material-icons\">navigate_before</i></span>
						<select id='pagesel' onChange='setpage(this.value);' style='margin-top: 3px; margin-right: 0px; vertical-align: top;'></select> &nbsp;
						<span title='{%next page}' onClick='switchpage(1);' style='margin-left: -13px;'><i class=\"material-icons\">navigate_next</i></span>
						<span title='{%details}' onClick='tocshow();'><i class=\"material-icons\">info</i></span>
						<span id=fullscreen title='{%fullscreen}' onClick='togglefull();'><i class=\"material-icons\">fullscreen</i></span>
						<span  title='{%options}' onClick='optshow();'><i class=\"material-icons\">menu</i></span>
					</span>
				</div>
			</tr>
			<tr style='height: 30px; overflow: hidden;'>
			<td colspan=3>
				<div id='options' style='z-index: 200; overflow: scroll;'>
					$viewoptions
					$showoptions
					- {%Switch to view}: <select onChange=\"window.open(this.value, '_self');\">$viewsels</select>
					-   <span title='{%smaller font}' style='margin-left: 4px;' onClick='fontzoom(-1);'>A-</span>
						<span title='{%larger font}' style='margin-left: 4px;' onClick='fontzoom(1);' style='margin-right: 5px'>A+</span>

				</div>
			</td>
			</tr>
			<tr>
			<td><div id='facsview' style='background-color: black; height: 470px; overflow: hidden;'></div>
			<td id='grip' style='cursor: col-resize;'>&nbsp;</td>
			<td><div id='mtxt' style='background-color: white; height: 470px; overflow: scroll; padding: 20px;'></div>
			</tr>
			</table>
		</div>
		</div>
		<div id='fulltext' style='display: none;'>".$ttxml->asXML(true)."</div>
		<script>
			var orgXML = document.getElementById('fulltext').innerHTML;
		</script>
		<script language=Javascript src='$jsurl/tokedit.js'></script>
		<script language=Javascript src='$jsurl/tokview.js'></script>
		<script language=Javascript src='$jsurl/pageflow.js'></script>
		";

?>