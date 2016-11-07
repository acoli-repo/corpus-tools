<?php
	
	# Basic support for IGT format, where morphemic data are stored in //tok/morph
	# (c) Maarten Janssen, 2016

	require ("../common/Sources/ttxml.php");
	
	$ttxml = new TTXML();
	$cid = $ttxml->fileid;
	
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 

	# Display the teiHeader data as a table
	$maintext .= $ttxml->tableheader(); 

	$maintext .= "<div id=mtxt>";
	$maintext .= "<style>.floatbox { float: left; margin-right: 10px; }</style>";
	
	foreach ( $ttxml->xml->xpath("//s") as $sent ) {
		$morphed = 0; if ( $sent->xpath(".//morph") ) { $morphed = 1; };
		$maintext .= "<table id=$sid><tr><td style='border-right: 1px solid #bbaabb;'>";
		$maintext .= "<div class='floatbox' id='$sid' style='padding-right: 5px;'>Word";
		if ( $morphed ) {
			$maintext .= "<table style='margin: 0;'>";
			foreach ( $settings['annotations']['morph'] as $item ) {
				if (is_array($item)) $maintext .= "<tr><td style='color:{$item['color']};'>".$item['display'];
			};
			$maintext .= "</table>";
		};
		$maintext .= "</div><td style='padding-left: 5px;'>";		
		
		foreach ( $sent->xpath(".//tok") as $tok ) {
			$maintext .= "<div class=floatbox id=$sid>".$tok->asXML();
			if ( $morphed ) {
				$maintext .= "<table style='margin: 0;'>";
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
	$maintext .= "</table></div><hr>";
			$maintext .= "<a href='index.php?action=edit&cid=$cid&jmp=$sentid'>{%to text mode}</a> $options</p><br>";


?>