<?php

	require_once("$ttroot/common/Sources/ttxml.php");

	$ids = $_GET['id'] or $ids = $_GET['cid'];
	$idlist = explode(",", $ids); 
	
	foreach ( $idlist as $cid ) {
		$versions[$cid] = new TTXML($cid); 
	};

	$maintext .= "<h1>Aligned Texts</h1>";

	$maintext .= "<script language=Javascript src=\"$jsurl/tokedit.js\"></script>";

	$maintext .= "<script language=Javascript src=\"$jsurl/appalign.js\"></script>";


			#Build the view options
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
				$formcol = $item['color'];
				# Only show forms that are not admin-only
				if ( $username || !$item['admin'] ) {
					if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
					$ikey = $item['inherit'];
					$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
					$fbc++;
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

			$showform = $_POST['showform'] or $showform = $_GET['showform'] or $showform = 'form';
			if ( $showform == "word" ) $showform = $wordfld;
			$showoptions .= "<button id='btn-int' style='background-color: #ffffff;' title='{%format breaks}' onClick=\"toggleint();\">{%Formatting}</button>";

			# Only show text options if there is more than one form to show
			if ( $fbc > 1 ) {
				$viewoptions .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

				$showoptions .= " - <button id='btn-col' style='background-color: #ffffff;' title='{%color-code form origin}' onClick=\"togglecol();\">{%Colors}</button> ";
				$sep = " - ";
			};
			
				$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
				$jsontrans = array2json($settings['transliteration']);

				if ( $tagstxt ) $showoptions .= "<p>{%Tags}: $tagstxt ";

			$maintext .= "
					$viewoptions $showoptions <hr>";
	
	$maintext .= "<table id=mtxt style='width: 100%; cellpadding: 5px; table-layout: fixed; '><tr>";
	foreach ( $idlist as $cid ) {
		$maintext .= "<td valign=top><h2><a href='index.php?action=file&cid=$cid'>".$versions[$cid]->title()."</a></h2>";
	};
	$maintext .= "<tr>";
	
	
	# if ( ( $settings['appid']['rows'] && $_GET['rows'] != "0" ) || $_GET['rows'] == "1" ) ) $rows = 1;
	
	if ( $rows ) {
		# Display the parts aligned by row
		foreach ( $versions[$cid]->children as $row ) {
			
		};
	} else {
		$i = 0;
		foreach ( $idlist as $cid ) {
			$maintext .= "<td valign=top id=td$i><div style=\"overflow-x: hidden; overflow-y: scroll; max-height: 600px; height: 600px;\">".$versions[$cid]->asXML()."</div></td>";
			$i++;
		};
	};

?>