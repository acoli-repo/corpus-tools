<?php
	
	# Basic support for IGT format, where morphemic data are stored in //tok/morph
	# (c) Maarten Janssen, 2016

	require ("$ttroot/common/Sources/ttxml.php");
	
	$colors = array ('#aa2200', 'green', '#0022aa', 'orange', 'purple', 'pink');
	
	$ttxml = new TTXML();
	$cid = $ttxml->xmlid;
	
	$morphelm = $_GET['melm'] or $morphelm = $_POST['melm'] or $morphelm = "m";
	$layername = getset("annotations/$morphelm/display", "Interlinear glossed text");
	$sentelm = $_GET['selm'] or $sentelm = getset("annotations/$morphelm/selm", "s");


	$wordlvl = getset("annotations/$morphelm/word/display", "Word");
	$morphann = current($ttxml->xpath("//spanGrp[@type=\"$morphelm\"]"));
	$annfile = "Annotations/{$morphelm}_$cid.xml"; 
	if ( !$morphann && file_exists($annfile) ) {
		$morphann = simplexml_load_file($annfile);
	};

	$maintext .= "<h2>$layername</h2>"; 
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 


	# Display the teiHeader data as a table
	$maintext .= $ttxml->tableheader(); 
	$editxml = $ttxml->asXML(); # We are not showing this, but we need it to check for attributes

	$maintext .= $ttxml->topswitch();

	if ( $ttxml->audio ) {
		// Determine where the playbutton is hosted
		if ( getset('defaults/playbutton') ) $playimg = getset('defaults/playbutton');
		else  if ( file_exists("$sharedfolder/Images/playbutton.gif") ) $playimg = "$sharedurl/Images/playbutton.gif";
		else  if ( file_exists("Images/playbutton.gif") ) $playimg = "Images/playbutton.gif";
		else $playimg = "$hprot://www.teitok.org/Images/playbutton.gif";
		$audiourl = "Audio/".$ttxml->audio[0]['url'];
		$maintext .= "<script language='Javascript' src=\"$jsurl/audiocontrol.js\"></script>";
		$maintext .= "<audio id=\"track\" src=\"$audiourl\" controls ontimeupdate=\"checkstop();\">
							<p><i><a href='$audiourl'>{%Audio}</a></i></p>
						</audio>
						"; 
	};

			#Build the view options	
			foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
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
			foreach ( getset('xmlfile/pattributes/tags', array()) as $key => $item ) {
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
			$jsonforms = array2json(getset('xmlfile/pattributes/forms'));
			$jsonforms .= "\n\t\tvar tagdef = ".array2json(getset('xmlfile/pattributes/tags', array())).";";
			$jsontrans = array2json(getset('transliteration'));

	$hlcol = $_POST['hlcol'] or $hlcol = $_GET['hlcol'] or $hlcol = getset('defaults/highlight/color', "#ffffaa"); 
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
	
	foreach ( $ttxml->xpath("//$sentelm") as $sent ) {
		$morphed = 0; 
		if ( $sent->xpath(".//$morphelm") ) { $morphed = 1; };
		if ( $morphann ) { $morphed = 1; };
		$maintext .= "<table id=$sid><tr><td style='border-right: 1px solid #bbaabb;' valign=top>";
		$maintext .= "<div class='floatbox' id='$sid' style='padding-right: 5px;'>{%$wordlvl}";
		$doatts = array(); $attarr = array(); 
		$con = 0;
		foreach ( array_merge(getset('xmlfile/pattributes/forms', array()), getset('xmlfile/pattributes/tags', array())) as $patttag ) {
			$pattname = $patttag['display'];
			$pattlvl = $patttag['lvl'] or $pattlvl = $patttag['key'];
			if ( $patttag['igt'] && ( $patttag['inherit'] || $patttag['compute'] || $sent->xpath(".//tok[@$pattlvl]") ) ) {
				$thiscolor = $patttag['color'] or $thiscolor = $colors[$con++];
				$maintext .= "<br><span style='color: $thiscolor' title='$pattlvl'>$pattname</span>";
				$doatts[$pattlvl] = $thiscolor;
				$attarr[$pattlvl] = $patttag;				
			} else {
				if ( $debug ) $maintext .= "<br>No $pattname";
			};
		};
		if ( $morphed ) {
			$maintext .= "<hr><table style='margin: 0;'>";
			foreach ( getset("annotations/$morphelm", array()) as $item ) {
				if (is_array($item)) $maintext .= "<tr><td style='color:{$item['color']};'>".$item['display'];
			};
			$maintext .= "</table>";
		};
		$maintext .= "</div><td style='padding-left: 5px;' valign=top>";		
		
		foreach ( $sent->xpath(".//tok") as $tok ) {
			$tokid = $tok['id'];
			$maintext .= "<div class=floatbox id='$sid' style='text-align: center;'>".$tok->asXML();
			foreach ( $doatts as $pattlvl => $thiscolor ) {
				$val = "";
				if ( getset("xmlfile/pattributes/forms/$pattlvl/compute") != "" ) {
					# This is a computable form
					$tokcp = new SimpleXMLElement($tok->asXML());
					$tokcp['setform'] = $pattlvl;
					$val = $tokcp->asXML();
				} else if ( getset("xmlfile/pattributes/forms/$pattlvl") != "" ) {
					# This is a form - inherit it
					$val = forminherit($tok, $pattlvl);
				} else {
					# This is a tag - do not inherit
					$val = $tok[$pattlvl] or $val = "&nbsp;";
				};
				$ptit = $attarr[$pattlvl]['display'];
				$maintext .= "<br><span style='color: $thiscolor' title='$ptit'>$val</span>";
			};
			if ( $morphed ) {
				$maintext .= "<hr><table style='margin: 0;'>";
				foreach ( getset("annotations/$morphelm", array()) as $item ) {
					$maintext .= "<tr>";
					$morphs = $tok->xpath(".//$morphelm");
					if ( !$morphs && $morphann ) { $morphs = $morphann->xpath(".//span[@corresp=\"#$tokid\"]"); };
					foreach ( $morphs as $morph ) {
						if (!is_array($item)) continue;
						$txt = $morph[$item['key']]; if ( $txt == '' ) { $txt = "&nbsp;"; };
						$maintext .= "<td align=center title='{$item['display']}'  style='color: {$item['color']};'>$txt</td>";
					};
				};
				$maintext .= "</table>";
			};
			$maintext .= "</div>";		
		};
		foreach ( getset("xmlfile/sattributes/$sentelm", array()) as $item ) {
			if ( is_array($item) && $item['igt'] ) {
				$stit = $item['short'] or $stit = $item['display'] or $stit = $item['key'];
				$sval = $sent[$item['key']]."";
		 		if ( $sval != '' ) $maintext .= "<tr><td style='border-right: 1px solid #bbaabb; color: {$item['color']}'>$stit</td><td style='padding-left: 5px; color: {$item['color']}'>$sval</td>";
		 	};
		};
		if ( $audiourl && $sent['start'] && $sent['end'] ) {
			$strt = $sent['start']; $stp = $sent['end']; 
			$audiobut = "<a onClick=\"playpart('$audiofile', $strt, $stp, this );\">{%play audio}</a>";
		 	$maintext .= "<tr><td style='border-right: 1px solid #bbaabb; color: {$item['color']}'>Audio</td><td style='padding-left: 5px; color: {$item['color']}'>$audiobut	</td>";
		};
		$maintext .= "</div><hr class=mainhr></table>";
	};
	$maintext .= "</div><hr>
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
	if ( $tocompute ) {
		$maintext .= "<script></script>";
	};
	$maintext .= $ttxml->viewswitch();
	# $maintext .= "<a href='index.php?action=file&cid=$cid&jmp=$sentid'>{%to text mode}</a> $options</p><br>";


?>