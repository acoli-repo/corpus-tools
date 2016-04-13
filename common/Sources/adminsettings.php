<?php
	# Module to conveniently edit the settings.xml file
	if ( !$settingsxml ) { fatal("Failed to load settings.xml");};

	$setdef = simplexml_load_file("../common/Resources/adminsettings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$setdef ) { fatal("Failed to load adminsettings.xml");};

	$section = $_GET['section'];

	if ( $act == "save" ) {
	} else if ( $section ) {
		
		$tmp = $setdef->xpath("/ttsettings/item[@key=\"$section\"]"); 
		$secdef = $tmp[0]; 

		$tmp = $settingsxml->xpath("/ttsettings/$section"); 
		$valdef = $tmp[0]; 
		
		$maintext .= "<h1>Settings: $section</h1>
			<p>{$secdef['display']}</p>";
	
		$maintext .= settingstable($valdef, $secdef);		
	
		$maintext .= "<hr><p><a href='index.php?action=$action'>back to sections</a>";
	
	} else {
	
		$maintext .= "<h1>Settings sections</h1>
		
			<table>";
		
		foreach ( $setdef->children() as $child ) {
			$tmp = $settingsxml->xpath("/ttsettings/{$child['key']}"); 
			if ($tmp) {
				$done[$child['key'].""] = 1;
				$maintext .= "
					<tr>
					<td><a href='index.php?action=$action&section={$child['key']}'>select</a></td>
					<th>{$child['key']}</th>
					<td>{$child['display']}</td>
					</tr>";
			} else {
				$notused .= "
					<tr>
					<td><a href='index.php?action=$action&section={$child['key']}'>create</a></td>
					<th>{$child['key']}</th>
					<td>{$child['display']}</td>
					</tr>";
			};
		};		
		$maintext .= "</table>";

		foreach ( $settings as $key => $val ) {
			if ( !$done[$key] ) $maintext .= "<p>Unknown section: $key"; 
		};		
	
		$maintext .= "<h2>Unused sections</h2><table>$notused</table>";
		
		
	}; 
	
	function settingstable ( $valnode, $defnode ) {
		if ( !$valnode ) return "";
		if ( !$defnode ) return "<i>Unknown field</i>";
		
		$tabletext .= "<table>";
		foreach ( $valnode->attributes() as $key => $item ) {
				$key .= "";
				$tmp = $defnode->xpath("att[@key=\"$key\"]"); $itdef = $tmp[0];
				if ( $itdef ) {
					$tmp = $itdef->xpath("val[@key=\"$item\"]"); $value = $tmp[0]['value'];
				};
				$deftxt = $itdef['display'] or $deftxt = "<i>Unknown attribute</i>";
				$tabletext .= "<tr><th>$key
					<td>$item
					<td style='color: #888888; padding-left: 20px;'>$deftxt
					<td>$value
					";
		};
		foreach ( $valnode->children() as $key => $item ) {
			$nodetype = $item->getName();
			if ( $nodetype == "item" ) {
				$tmp = $defnode->xpath("list"); $itdef = $tmp[0];
				$tabletext .= "<tr><td><td colspan=3>".settingstable($item, $itdef);
			} else {
				$tmp = $defnode->xpath("item[@key=\"$key\"]"); $itdef = $tmp[0];
				$tabletext .= "<tr><th>$key<td colspan=3><i style='color: #888888;'>{$itdef['display']}</i>
					<br>".settingstable($item, $itdef);
			};
		};
		$tabletext .= "</table>";
		
		return $tabletext;
	};

	function is_attribute($node) {
		return !($node->asXML()[0] == "<");
	};
	
?>