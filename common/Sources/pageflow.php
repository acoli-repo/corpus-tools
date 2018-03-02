<?php

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	
	$title = $ttxml->title();
	$editxml = $ttxml->asXML();

	# Build the buttons
	#Build the view options	
	foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
		$formcol = $item['color'];
		# Only show forms that are not admin-only
		if ( $username || !$item['admin'] ) {
			if ( !$bestform ) $bestform = $key; 
			if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
			$ikey = $item['inherit'];
			if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
				$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
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
	if ( $fbc > 1 ) $viewoptions .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

	$sep = "<p>";
	if ( $fbc > 1 ) {
		$showoptions .= "<button id='btn-col' style='background-color: #ffffff;' title='{%color-code form origin}' onClick=\"togglecol();\">{%Colors}</button> ";
		$sep = " - ";
	};
	
	# Some of these checks work after the first token, so first find the first token
	$tokpos = strpos($editxml, "<tok"); 
	
	if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) ||  strpos($editxml, "<lb", $tokpos)  ) ) {
		$showoptions .= "<button id='btn-int' style='background-color: #ffffff;' title='{%format breaks}' onClick=\"toggleint();\">{%Formatting}</button>";
	};
	if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) || ( $username && strpos($editxml, "<pb") )  ) ) {
		// Should the <pb> button be hidden if there is only one page? (not for admin - pb editing)
		$showoptions .= "<button id='btn-tag-pb' style='background-color: #ffffff;' title='{%show pagebreaks}' onClick=\"toggletn('pb');\">&lt;pb&gt;</button>";
	};
	if ( !$nobreakoptions && ( strpos($editxml, "<lb", $tokpos) ) ) {
		$showoptions .= "<button id='btn-tag-lb' style='background-color: #ffffff;' title='{%show linebreaks}' onClick=\"toggletn('lb');\">&lt;lb&gt;</button>";
	};
	
	if ( !$username ) $noadmin = "(?![^>]*admin=\"1\")";
	
	if ( $viewoptions != "" ) $viewoptions = "<p>{%Text display}: $viewoptions</p>";
	if ( $showoptions != "" ) $showoptions = "<p>{%Display options}: $showoptions</p>";

	$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
	$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	$header = $ttxml->tableheader("pageflow,long", false);
	$views = $ttxml->viewswitch();
	$maintext .= "
		<link href=\"https://fonts.googleapis.com/icon?family=Material+Icons\" rel=\"stylesheet\">
		<script>
			var username = '$username';
			$settingsdefs
			var nofacs = true;
			var tid = '$ttxml->fileid'; 
		</script>
		<style>
			#pageflow .material-icons:hover { background-color: #990000; }
			#pageflow #info { background-color: white; color: white; }
			#pageflow #options { background-color: black; color: white; }
			#pageflow #options a { color: #ffdddd; }
			#pageflow { box-sizing: border-box; }
			#facsview { color: #dddddd; text-align: right;  }
		</style>
		<div id='pageflow' style='z-index: 150;'>
		<div id='info' style='display: none; position: fixed; z-index: 200; opacity: 0.9; overflow: scroll;'>
			<span title='{%close}' style='float: right;' onClick='toc.style.display=\"none\";'><i class=\"material-icons\">close</i></span>
			<div style='padding: 20px;'>
			<h2>$ttxml->title</h2>
			$header
			</div>
		</div>
		<div id='options' style='display: none; position: fixed; z-index: 200; opacity: 0.9; overflow: scroll;'>
			<span title='{%close}' style='float: right; color: white;' onClick='opts.style.display=\"none\";'><i class=\"material-icons\">close</i></span>
			<div style='padding: 20px;'>
			<h1 style='color: white;'>{%Viewing options}</h1>
			$viewoptions
			$showoptions
			<p style='color: white;'>{%Switch to view}:<br>$views</p>
			</div>
		</div>
		<div id='viewport' style='z-index: 160; border: 1px solid #666666; background-color: black; position: fixed; top: 0; width: 100%;'>
		<table width='100%' height='500px' style='table-layout: fixed;' id=viewtable>
		   <colgroup>
			<col id='col1' style='width: 50%'>
			<col style='width: 1px; background-color: white;'>
			<col id='col2' style='width: 50%'>
		  </colgroup>	
			<tr style='height: 30px; overflow: hidden;'>
			<td colspan=3>
				<div id='title' style='color: white; font-weight: bold; font-size: 24px;'>
					$title
					<span id='toolbar' style='float: right; color: white; vertical-align: top;'>
						<span title='{%previous page}' onClick='switchpage(-1);'><i class=\"material-icons\">navigate_before</i></span>
						<select id='pagesel' onChange='setpage(this.value);' style='margin-top: 3px; margin-right: 0px; vertical-align: top;'></select> &nbsp;
						<span title='{%next page}' onClick='switchpage(1);' style='margin-left: -13px;'><i class=\"material-icons\">navigate_next</i></span>
						<span title='{%details}' onClick='tocshow();'><i class=\"material-icons\">info</i></span>
						<span id=fullscreen title='{%fullscreen}' onClick='togglefull();'><i class=\"material-icons\">fullscreen</i></span>
						<span  title='{%options}' onClick='optshow();'><i class=\"material-icons\">menu</i></span>
					</span>
				</div>
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
		<hr>
		<script>
			var orgXML = document.getElementById('fulltext').innerHTML;
		</script>
		<script language=Javascript src='$jsurl/tokedit.js'></script>
		<script language=Javascript src='$jsurl/tokview.js'></script>
		<script language=Javascript src='$jsurl/pageflow.js'></script>
		";
	
	
	$maintext .= $ttxml->viewswitch();

?>