<?php
	// Script to help with a position-based tagset
	// The tagset is defined in tagset.xml
	// (c) Maarten Janssen, 2015

	require ( "$ttroot/common/Sources/tttags.php" );

	$maintext .= "<h1>{%Tagset}</h1>";
	$ttfile = $_GET['tagset'] or $ttfile = $tagsetfile;
	$tttags = new TTTAGS($ttfile, false);
	$tagset = $tttags->tagset['positions'];
	if ( !$tagset ) { fatal("Tagset $ttfile not position-based or positions not defined"); };
		
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

	} else if ( $act == "check" ) {
		check_login();
		$tagfld = $tagset->tagset['fulltag'] or $tagfld = "pos";
		$maintext .= "<p>Below is a verification of the POS tags used in the CQP corpus (field $tagfld) against
			the definition of the tagset. 
			It will display both errors in the tags (values used in positions that are not defined), and values
			that should occur according to the tagset, but are not in fact used in the corpus. For the erroneous tags, 
			you can click on the tags to find the occurrences in the corpus. Corrections will only show after 
			regenerating the CQP corpus.</p>";

		// Check if the CQP corpus has only valid POS tags
		$tmp = file_get_contents("cqp/$tagfld.lexicon"); unset($optarr); $optarr = array();
		foreach ( explode ( "\0", $tmp ) as $kva ) { 
			$main = substr($kva,0,1);
			for ( $i = 0; $i<strlen($kva); $i++ ) {
				$let = substr($kva,$i,1);
				$tags[$main][$i][$let] .= ",$kva";
			};
		};
		
		$maintext .= "<hr><h2>Undefined positions</h2>";
		$tagcheck = $tags; 
		
		foreach ( $tags as $main => $val ) {
			$maintxt = $tagset[$main]['display'];
			foreach ( $val as $posi => $val2 ) {
				$postxt = $tagset[$main][$posi]['display'];
				foreach ( $val2 as $value => $tags ) {
					if ( $posi > 0 && !$tagset[$main][$posi][$value] ) {
						$maintext .= "<p>Undefined value <b>$value</b> for position $posi ($postxt) of $main ($maintxt)<br> - used in: ";
						foreach ( explode(",", $tags ) as $tag ) {
							if ( $tag ) { $maintext .= "<a target=edit href='index.php?action=cqp&cql=[pos=\"$tag\"]'>$tag</a> "; };
						};
					};
				};
			};
		};
		
		$tags = $tagcheck;
		$maintext .= "<hr><h2>Unused values</h2>";

		foreach ( $tagset as $main => $val ) {
			// $maintext .= "<p>$main: {$val['display']}";
			foreach ( $val as $posi => $val2 ) {
				$postxt = $val2['display'];
				foreach ( $val2 as $value => $val3 ) {
					$value .= ""; $main .= ""; $posi += 0;
					if ( $posi > 0 && is_array($val3) && !$tags[$main][$posi][$value] ) {
						$valtxt = $val3['display'] or $valtxt = "<i style='color: #aaaaaa'>does not apply</i>";
						$maintext .= "<p>Unused value $value ($valtxt) for position $posi ($postxt) of $main ({$val['display']})";
					} else if ( $posi > 0 && is_array($val3) ) {
						// $maintext .= "<p>Used value $value ({$val3['display']}) for position $posi of $main ({$val['display']}) - ".$tags[$main][$posi][$value];
					};
				};
			};
		};
		$maintext .= "<hr><p><a href='index.php?action=$action'>{%back}</a>";
		
	} else if ( $act == "checkfile" ) {
		check_login();
		
		$cid = $_GET['cid'] or $cid = $_GET['id'];
		// Check if a certain file has only valid POS tags	
		$tagfld = $tagset->tagset['fulltag'] or $tagfld = "pos";
		require ("$ttroot/common/Sources/ttxml.php");
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
		else if ( $tttags->xml->xpath("//description") ) {
			$descriptiontext = current($tttags->xml->xpath("//description"))->asXML();
			$maintext .= "<div style='margin-bottom: 20px;'>$descriptiontext</div>";		
		} else {	
			$maintext .= "<h2>{%Description}</h2>";
		};
		
		$maintext .= "<table id=\"tagset\">";
		foreach ( $tagset  as $key => $val ) {
			$valname = $val['lang-'.$lang] or $valname = $val['display-'.$lang] or $valname = "{%{$val['display']}}";
			if ( $val['short-'.$lang] ) $valname .= " ({$val['short-'.$lang]})"; 
			else if ( $val['short'] ) $valname .= " ({$val['short']})"; 
			$maintext .= "<tr><th style='padding-left: 10px; padding-right: 10px; font-weight: bold; text-align: center; '>$key<th colspan=2 style='text-align: center;'>$valname";
			foreach ( $val as $key => $attr ) {
				$pos = $attr['pos'];
				if ( $pos == "multi" ) {
					$maintext .= "<tr><td><th style='padding-left: 5px; padding-right: 5px;' colspan=2><table>";
					foreach ( $attr as $key2 => $val2 ) {
						if ( is_array($val2) ) {
							if ( $val2['lang-'.$lang] ) $key2val = $val2['lang-'.$lang]; 
								else if ( $val2['display-'.$lang] ) $key2val = "{%{$val2['display-'.$lang]}}"; 
								else $key2val = "{%{$val2['display']}}"; 
							if ( $val2['short-'.$lang] ) $key2val .= " ({$val2['short-'.$lang]})"; 
								else if ( $val2['short'] ) $key2val .= " ({$val2['short']})"; 
							$maintext .= "<tr><td style=' width: 25px; text-align: center; border-right: 1px solid #aaaaaa;'><b>$key2</b><td style=' padding-left: 5px;' >$key2val";
						};
					};
					$maintext .= "</table>";
				} else if ( is_array($attr) ) {
					$attrname = $attr['display-'.$lang] or $attrname = "{%{$attr['display']}}";
					$maintext .= "<tr><td>$pos<th style='padding-left: 5px; padding-right: 5px;'>$attrname<td style='border-bottom: 1px solid #aaaaaa;'><table>";
					foreach ( $attr as $key2 => $val2 ) {
						if ( is_array($val2) ) {
							if ( $val2['display-'.$lang] ) $key2val = $val2['display-'.$lang]; 
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
		
		if ( $username )	
			$maintext .= " &bull; <a href='index.php?action=$action&act=check'>{%Check tagset consistency}</a>";
		
	};

?>