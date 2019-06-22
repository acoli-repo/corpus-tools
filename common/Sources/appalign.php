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

	
	
	# if ( ( $settings['appid']['rows'] && $_GET['rows'] != "0" ) || $_GET['rows'] == "1" ) ) $rows = 1;
	$rows = 1; 
	
	if ( $rows ) {
		
		$ptype = $_GET['pbtype'] or $ptype = $settings['appid']['baseview'] or $ptype = "pb";
		# Display the parts aligned by row
		$nums = array_keys($settings['appid']['numbering']);
 		$i=0; while ( $i < count($nums) && $nums[$i] != $ptype ) { print "$i: {$nums[$i]}"; $i++; }; 
 		$i++; $ctype = $nums[$i]; 
 
 		# Build the list of rows
 		foreach ( $idlist as $cid ) {		
 			$appidlist[$cid] = array();			
			foreach ( $versions[$cid]->xml->xpath(".//$ctype") as $row ) {
				$appid = $row['appid']."";
				array_push($appidlist[$cid], $appid);
				$rowlist[$cid][$appid] .= $row->asXML();
			}; 
		};
		
		$maintext .= "<table border=1  id=mtxt style='width: 100%; cellpadding: 5px; table-layout: fixed;'>
			<tr><td>";
 		foreach ( $idlist as $cid ) {
			$maintext .= "<td valign=top><h2><a href='index.php?action=file&cid=$cid'>".$versions[$cid]->title()."</a></h2>";
		};
		$mcid = $idlist[0];
		$donerows = 0;
		while ( !$donerows ) {
			$appid = array_shift($appidlist[$mcid]);
			while ( $appid == $appidlist[$idlist[1]][1] ) { # TODO: This should become more general
				# We have an inserted element in the 1st column - show that one
				$inserted = array_shift($appidlist[$idlist[1]]); 
				$maintext .= "<tr><td valign=top><a href='index.php?action=$action&appid=$inserted&pbtype=$ctype&id=$ids'>$inserted</a></td><td>";
				foreach ( $idlist as $cid ) {
					if ( $cid == $mcid ) continue;
					$maintext .= "<td valign=top>".$rowlist[$cid][$inserted];
				};
			};
			$maintext .= "<tr><td valign=top><a href='index.php?action=$action&appid=$appid&pbtype=$ctype&id=$ids'>$appid</a></td>";
			foreach ( $idlist as $cid ) {
				$maintext .= "<td valign=top>".$rowlist[$cid][$appid];
				if ( $appid == $appidlist[$cid][0] ) array_shift($appidlist[$cid]);
			};
			if ( count($appidlist[$mcid]) == 0 ) $donerows = 1;
		};
		$maintext .= "<table>";
		
	} else {
	
		$i = 0;
		$maintext .= "<table id=mtxt style='width: 100%; cellpadding: 5px; table-layout: fixed; '><tr>";
		foreach ( $idlist as $cid ) {
			$maintext .= "<td valign=top><h2><a href='index.php?action=file&cid=$cid'>".$versions[$cid]->title()."</a></h2>";
		};
		$maintext .= "<tr>";

		foreach ( $idlist as $cid ) {
			$maintext .= "<td valign=top id=td$i><div style=\"overflow-x: hidden; overflow-y: scroll; max-height: 600px; height: 600px;\">".$versions[$cid]->asXML()."</div></td>";
			$i++;
		};
		
		$maintext .= "<table>";
	};

?>