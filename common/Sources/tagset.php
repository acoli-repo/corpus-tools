<?php
	// Script to help with a position-based tagset
	// The tagset is defined in tagset.xml
	// (c) Maarten Janssen, 2015

	require ( "../common/Sources/tttags.php" );

	$maintext .= "<h1>{%Tagset}</h1>";
	$tttags = new TTTAGS("", false);
	$tagset = $tttags->tagset['positions'];
	if ( !$tagset ) { fatal("Tagset not position-based or positions not defined"); };
		
	if ( $act == "analyze" ) {
		$tag = $_GET['tag'];
		$maintext .= "
			<form action='index.php'>
			<input type=hidden name=action value=\"$action\">
			<input type=hidden name=act value=\"analyze\">
			<p>{%Tag}: <input name=tag size=15 value=\"$tag\">
			<input type=submit value=\"{%Analyse}\">
			</form>";
			
			$maintext .= $tttags->table($tag);

			$maintext .= "<hr><p><a href='index.php?action=$action'>{%To tagset description}</a>";
			if ( $warnings ) $maintext .= "<div style='margin-top: 20px; font-weight: bold; color: #992000' class=adminpart>$warnings</div>";

	} else if ( $act == "checkfile" ) {
		check_login();
		
		$cid = $_GET['cid'];
		// Check if a certain file has only valid POS tags	
		$tagfld = $settings['tagset']['fulltag'] or $tagfld = "pos";
		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($cid, false);
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 
		$maintext .= $ttxml->viewheader(); 

		$maintext .= "<h2>Tag Validity Check</h2> <table>";
		foreach ( $ttxml->xml->xpath("//tok[@".$tagfld."] | //dtok[@".$tagfld."]") as $tok ) {
			$mfs = $tok[$tagfld]."";
			$mainpos = $mfs[0]; $status = ""; $interpret = $tagset[$mainpos]['display'].";";
			for ( $i = 1; $i<strlen($mfs); $i++ ) {
				$let = $mfs[$i];
				if ( !$tagset[$mainpos] ) $status .= "Invalid main POS $mainpos; ";
				if ( !$tagset[$mainpos][$i][$let] ) {
					$status .= "Invalid $let in position $i for $mainpos; ";
					$interpret .= "?;";
				} else { $interpret .= $tagset[$mainpos][$i][$let]['display'].";"; };
			}; if ( !$status ) { $status = "<span style='color: #009900'>(ok)</span>"; };
			$interpret = preg_replace( "/;+$/", "", $interpret );
			$interpret = preg_replace( "/;;+/", ";", $interpret );
			$form = $tok['fform'] or $form = $tok['form'] or $form = $tok."";
			$maintext .= "<tr>
				<td><a href='index.php?action=tokedit&cid=$cid&tid={$tok['id']}' target=edit>{$tok['id']}<td>$form
				<td><a href='index.php?action=$action&act=analyze&tag=$mfs'>$mfs</a><td>$interpret
				<td style='color: #992000;'>$status";
		};
			
	} else {

		// Get the description text when available
		$descriptionpage = getlangfile("tagsettext");
		if ( $descriptionpage ) $maintext .= $descriptionpage;
		else {	
			$maintext .= "<h2>{%Description}</h2>";
		};
		
		$maintext .= "<table>";
		foreach ( $tagset  as $key => $val ) {
			$valname = $val['lang-'.$lang] or $valname = "{%{$val['display']}}";
			$maintext .= "<tr><th style='padding-left: 10px; padding-right: 10px; font-weight: bold; text-align: center; '>$key<th colspan=2 style='text-align: center;'>$valname";
			foreach ( $val as $pos => $attr ) {
				if ( is_array($attr) ) {
					$attrname = $attr['lang-'.$lang] or $attrname = "{%{$attr['display']}}";
					$maintext .= "<tr><td>$pos<th style='padding-left: 5px; padding-right: 5px;'>$attrname<td style='border-bottom: 1px solid #aaaaaa;'><table>";
					foreach ( $attr as $key2 => $val2 ) {
						if ( is_array($val2) ) {
							if ( $val2['lang-'.$lang] ) $key2val = $val2['lang-'.$lang]; 
								else if ( $val2['display'] ) $key2val = "{%{$val2['display']}}"; 
								else $key2val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
							$maintext .= "<tr><td style=' width: 25px; text-align: center; border-right: 1px solid #aaaaaa;'>$key2<td style=' padding-left: 5px;' >$key2val";
						};
					};
					$maintext .= "</table>";
				};
			};
		};
		$maintext .= "</table><hr><p><a href='index.php?action=$action&act=analyze'>{%Analyze a specific POS tag}</a>";
	};

?>