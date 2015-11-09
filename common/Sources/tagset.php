<?php

	$maintext .= "<h1>{%Tagset}</h1>";
	$tagset = $settings['tagset']['positions'];
	if ( !$tagset ) { fatal("Tagset not position-based or positions not defined"); };
		
	if ( $act == "analyze" ) {
		$tag = $_GET['tag'];
		$maintext .= "
			<form action='index.php'>
			<input type=hidden name=action value=\"$action\">
			<input type=hidden name=act value=\"analyze\">
			<p>Tag: <input name=tag size=15 value=\"$tag\">
			<input type=submit value=Analyze>
			</form>";
			
			if ( $tag ) {
				$pos1 = substr($tag,0,1);
				$tagoptions = $tagset[$pos1];
				$maintext .= "<h2>{%Tag}: $tag</h2>
				<table cellpadding='5px'>";
				$maintext .= "<tr><td>$pos1<th>{%Main type}<td>{$tagoptions['display']}</h2>";
				for ( $i=1; $i<strlen($tag); $i++ ) {
					$posx = substr($tag,$i,1);
					if ($tagoptions[$i]['display']) $key1val = "{%{$tagoptions[$i]['display']}}"; 
						else $key1val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
					if ( !$tagoptions[$i][$posx] && $username ) $warnings .= "<p>Invalid value for $pos1 position $i: $posx";
					if ($tagoptions[$i][$posx]['display']) $key2val = "{%{$tagoptions[$i][$posx]['display']}}"; 
						else $key2val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
					$maintext .= "<tr><td>$posx<th>{%{$tagoptions[$i]['display']}}<td>$key2val</h2>";
				};
			};
			$maintext .= "</table><hr><p><a href='index.php?action=$action'>{%To tagset description}</a>";
			if ( $warnings ) $maintext .= "<div style='margin-top: 20px; font-weight: bold; color: #992000' class=adminpart>$warnings</div>";

	} else if ( $act == "checkfile" ) {
		check_login();
		
		$cid = $_GET['cid'];
		// Check if a certain file has only valid POS tags	
		$tagfld = $settings['tagset']['fulltag'];
		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($cid, false);
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 
		$maintext .= $ttxml->viewheader(); 

			$maintext .= "<h2>Tag Validity Check</h2> <table>";
		foreach ( $ttxml->xml->xpath("//tok[@".$tagfld."]") as $tok ) {
			$mfs = $tok[$tagfld]."";
			$mainpos = $mfs[0]; $status = ""; $interpret = $settings['tagset']['positions'][$mainpos]['display'].";";
			for ( $i = 1; $i<strlen($mfs); $i++ ) {
				$let = $mfs[$i];
				if ( !$settings['tagset']['positions'][$mainpos] ) $status .= "Invalid main POS $mainpos; ";
				if ( !$settings['tagset']['positions'][$mainpos][$i][$let] ) {
					$status .= "Invalid $let in position $i for $mainpos; ";
					$interpret .= "?;";
				} else { $interpret .= $settings['tagset']['positions'][$mainpos][$i][$let]['display'].";"; };
			}; if ( !$status ) { $status = "<span style='color: #009900'>(ok)</span>"; };
			$interpret = preg_replace( "/;+$/", "", $interpret );
			$interpret = preg_replace( "/;;+/", ";", $interpret );
			$maintext .= "<tr>
				<td><a href='index.php?action=tokedit&cid=$cid&tid={$tok['id']}' target=edit>{$tok['id']}<td>$tok
				<td><a href='index.php?action=$action&act=analyze&tag=$mfs'>$mfs</a><td>$interpret
				<td style='color: #992000;'>$status";
		};
			
	} else {

		// Get the description text when available
		$descriptionpage = getlangfile("tagsettext");
		if ( $descriptionpage ) $maintext .= $descriptionpage;
		else {	
			$maintext .= "<h2>{%Description}";
			# $maintext .= "</h2><p>{%Example}:";
		};
		
		$maintext .= "<table>";
		foreach ( $tagset  as $key => $val ) {
			$maintext .= "<tr><th style='padding-left: 10px; padding-right: 10px; font-weight: bold; text-align: center; '>$key<th colspan=2 style='text-align: center;'>{%{$val['display']}}";
			foreach ( $val as $pos => $attr ) {
				if ( is_array($attr) ) {
					$maintext .= "<tr><td>$pos<th style='padding-left: 5px; padding-right: 5px;'>{%{$attr['display']}}<td style='border-bottom: 1px solid #aaaaaa;'><table>";
					foreach ( $attr as $key2 => $val2 ) {
						if ( is_array($val2) ) {
							if ( $val2['display'] ) $key2val = "{%{$val2['display']}}"; else $key2val = "<span style='color: #aaaaaa'><i>{%does not apply}</i></span>";
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