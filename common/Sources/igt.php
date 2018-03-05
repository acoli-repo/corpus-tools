<?php
	
	# Basic support for IGT format, where morphemic data are stored in //tok/morph
	# (c) Maarten Janssen, 2016

	require ("$ttroot/common/Sources/ttxml.php");
	
	$ttxml = new TTXML();
	$cid = $ttxml->fileid;
	
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 

	# Display the teiHeader data as a table
	$maintext .= $ttxml->tableheader(); 

			#Build the view options	
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
				$formcol = $item['color'];
				# Only show forms that are not admin-only
				if ( $username || !$item['admin'] ) {	
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
				};
			};
			foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
				$val = $item['display'];
				if ( preg_match("/ $key=/", $editxml) ) {
					if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
					if ( !$item['admin'] || $username ) {
						if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
						$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
						$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
						$pcolor = $item['color'];
						$tagstxt .= " <button id='tbt-$key' style='background-color: #$bc; color: $pcolor;$bgcol' onClick=\"toggletag('$key')\">{%$val}</button>";
					};
				} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
					unset($labarray[$akey]);
				};
			};
			$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
			$jsontrans = array2json($settings['transliteration']);

	$hlcol = $_POST['hlcol'] or $hlcol = $_GET['hlcol'] or $hlcol = $settings['defaults']['highlight']['color'] or $hlcol = "#ffffaa"; 
	$highlights = $_GET['tid'] or $highlights = $_GET['jmp'] or $highlights = $_POST['jmp'];	

	$maintext .= "
		<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
		<div id=mtxt>
			<script language=Javascript>
				var username = '$username';
				var formdef = $jsonforms;
				var orgtoks = new Object();
				var tid = '$cid'; 
				var jmps = '$highlights'; var jmpid;
			</script>
			<script language=Javascript src='$jsurl/tokedit.js'></script>
			<script language=Javascript src='$jsurl/tokview.js'></script>
		";
	$maintext .= "<style>.floatbox { float: left; margin-right: 10px; }</style>";
	
	foreach ( $ttxml->xml->xpath("//s") as $sent ) {
		$morphed = 0; if ( $sent->xpath(".//morph") ) { $morphed = 1; };
		$maintext .= "<table id=$sid><tr><td style='border-right: 1px solid #bbaabb;' valign=top>";
		$maintext .= "<div class='floatbox' id='$sid' style='padding-right: 5px;'>Word";
		if ( $morphed ) {
			$maintext .= "<hr><table style='margin: 0;'>";
			foreach ( $settings['annotations']['morph'] as $item ) {
				if (is_array($item)) $maintext .= "<tr><td style='color:{$item['color']};'>".$item['display'];
			};
			$maintext .= "</table>";
		};
		$maintext .= "</div><td style='padding-left: 5px;' valign=top>";		
		
		foreach ( $sent->xpath(".//tok") as $tok ) {
			$maintext .= "<div class=floatbox id='$sid' style='text-align: center;'>".$tok->asXML();
			if ( $morphed ) {
				$maintext .= "<hr><table style='margin: 0;'>";
				foreach ( $settings['annotations']['morph'] as $item ) {
					if ( !is_array($item) ) continue;
					$maintext .= "<tr>";
					foreach ( $tok->xpath(".//morph") as $morph ) {
						$txt = $morph[$item['key']]; if ( $txt == '' ) { $txt = "&nbsp;"; };
						$maintext .= "<td align=center title='{$item['display']}'  style='color: {$item['color']};'>$txt</td>";
					};
				};
				$maintext .= "</table>";
			};
			$maintext .= "</div>";		
		};
		foreach ( $settings['xmlfile']['sattributes']['s'] as $item ) {
			if ( !is_array($item) ) continue;
	 		$maintext .= "<tr><td style='border-right: 1px solid #bbaabb; color: {$item['color']}'>{$item['short']}</td><td style='padding-left: 5px; color: {$item['color']}'> ".$sent[$item['key']]."</td>";
		};
		$maintext .= "</div><hr>";
	};
	$maintext .= "</table></div><hr>
				<script language=Javascript>			
				if ( jmps ) { 
					var jmpar = jmps.split(' ');
					for (var i = 0; i < jmpar.length; i++) {
						var jmpid = jmpar[i];
						highlight(jmpid, '$hlcol');
					};
					element = document.getElementById(jmpar[0])
					alignWithTop = true;
					if ( element != null && typeof(element) != null ) { 
						element.scrollIntoView(alignWithTop); 
					};
				};
				var attributelist = Array($attlisttxt);
				$attnamelist
				formify(); 
				var orgXML = document.getElementById('mtxt').innerHTML;
				setForm('$showform');
			</script>
			";
	$maintext .= $ttxml->viewswitch();
	# $maintext .= "<a href='index.php?action=file&cid=$cid&jmp=$sentid'>{%to text mode}</a> $options</p><br>";


?>