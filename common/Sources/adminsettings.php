<?php
	# Module to conveniently edit the settings.xml file
	if ( !$settingsxml ) { fatal("Failed to load settings.xml");};

	check_login();

	$setdef = simplexml_load_file("../common/Resources/adminsettings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$setdef ) { fatal("Failed to load adminsettings.xml");};

	$section = $_GET['section'];

	if ( $act == "save" ) {

		$xpath = $_POST['xpath']; 
		if ( !$xpath ) { fatal("No node indicated"); };
		$tmp = $settingsxml->xpath($xpath); $valnode = $tmp[0];
		if ( !$valnode ) { fatal("Node not found: $xpath"); };

		if ( preg_match ("/ttsettings\/([^\/]+)/", $xpath, $matches) ) $section = $matches[1];

		if ( is_attribute($valnode) ) {
			$valnode[0] = $_POST['newval'];
		} else {
			$valnode[0] = $_POST['newval'];
		};

		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "settings-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/settings.xml", "backups/$buname");
		};
	
		
		# Now save the actual file
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action&section=$section';</script>
			";

	} else if ( $act == "edit" ) {

		$xpath = $_GET['node']; 
		if ( !$xpath ) { fatal("No node indicated"); };
		$tmp = $settingsxml->xpath($xpath); $valnode = $tmp[0];
		if ( !$valnode ) { fatal("Node not found: $xpath"); };
		
		$defnode = findnode($xpath);
		
		$xptxt = "".$xpath;
		$valtxt = addslashes($valnode);
		
		$maintext .= "<h1>Edit settings</h1>
			<p>Settings node: $xpath
			<p>{$defnode['display']}
			<p>Current value: <b>$valnode</b>
			<form action=\"index.php?action=$action&act=save\" method=post>
			<textarea style='display: none;' type=hidden name=xpath>$xptxt</textarea>
			";
			
		if ( $defnode && $defnode->val ) {
			foreach ( $defnode->val as $option ) {
				if ( $option["key"] == $valtxt ) $seltxt = "selected"; else $seltxt = "";
				if ( $option['deprecated'] ) $deptxt = " (deprecated)";  else $deptxt = ""; # $seltxt .= " disabled";
				if ( !$option['deprecated'] || $option["key"] == $valtxt ) $optionlist .= "<option value=\"{$option['key']}\" $seltxt>{$option['display']}$deptxt</option>";
			};
			$maintext .= "<p>New value: <select name=newval><option value=''>[select]</option>$optionlist</select>";
		} else {
			$maintext .= "<p>New value: <input name=newval size=60 value=\"$valtxt\">";
		};
		$maintext .= "
			<input type=submit value=Save></form>";

	} else if ( $section ) {
		
		$tmp = $setdef->xpath("/ttsettings/item[@key=\"$section\"]"); 
		$secdef = $tmp[0]; 

		$tmp = $settingsxml->xpath("/ttsettings/$section"); 
		$valdef = $tmp[0]; 
		
		$maintext .= "<h1>Settings: $section</h1>
			<p>{$secdef['display']}</p>";
	
		
		$maintext .= settingstable($valdef, $secdef, $_GET['showunused'] );		
	
		$maintext .= "<hr><p><a href='index.php?action=$action'>back to sections</a>";
		if ( !$_GET['showunused'] ) $maintext .= " &bull; <a href='index.php?action=$action&section=$section&showunused=1'>show unused attributes</a>";
	
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
	
		if ($notused) $maintext .= "<h2>Unused sections</h2><table>$notused</table>";
		
		if ( $user['permissions'] == "admin" ) {
			$maintext .= "<hr><p><a href='index.php?action=adminedit&id=settings.xml'>edit raw XML</a>";
		};
		
	}; 
	
	function settingstable ( $valnode, $defnode, $showunused = false ) {
		global $user;
		if ( !$valnode ) return "";
		if ( !$defnode ) return "<i>Unknown field</i>";
		
		$tabletext .= "<table>"; unset($done);
		foreach ( $valnode->attributes() as $key => $item ) {
			$key .= ""; $done[$key] = 1; 
			$tmp = $defnode->xpath("att[@key=\"$key\"]"); $itdef = $tmp[0];
			if ( $itdef ) {
				$tmp = $itdef->xpath("val[@key=\"$item\"]"); $value = $tmp[0]['value'];
			};
			$deftxt = $itdef['display'] or $deftxt = "<i>Unknown attribute</i>";
			if ( $user['permissions'] == "admin" ) {
				$xpath = makexpath($item);
				$item = "<a href='index.php?action=adminsettings&act=edit&node=$xpath'>$item</a>";
			};
			if ( $itdef['deprecated'] ) {
				$tabletext .= "<tr><th style='background-color: #ffcccc'>$key
					<td>$item
					<td style='color: #888888; padding-left: 20px;'>$deftxt (deprecated)
					<td>$value
					";
			} else {
				$tabletext .= "<tr><th>$key
					<td>$item
					<td style='color: #888888; padding-left: 20px;'>$deftxt
					<td>$value
					";
			};
		};
		if ( $showunused ) {
			foreach ( $defnode->children() as $key => $item ) {
				if ( $item->getName() != "att"  || $item['deprecated'] ) continue;
				$key = $item['key']."";
				$deftxt = $item['display'];
				if ( $item['default'] ) $itemtxt = "default: ".$item['default']; else $itemtxt = "(unused)";
				if ( !$done[$key] ) {
					$tabletext .= "<tr><th style='background-color: #d2d2ff'>$key
						<td style='color: #888888;'>$itemtxt
						<td style='color: #888888; padding-left: 20px;'>$deftxt
						<td>$value
						";
				};
			};
		};
		foreach ( $valnode->children() as $key => $item ) {
			$key .= ""; $done[$key] = 1; 
			$nodetype = $item->getName();
			if ( $nodetype == "item" ) {
				$tmp = $defnode->xpath("list"); $itdef = $tmp[0];
				$tabletext .= "<tr><td><td colspan=3>".settingstable($item, $itdef, $showunused);
			} else {
				$tmp = $defnode->xpath("item[@key=\"$key\"]"); $itdef = $tmp[0];
				$tabletext .= "<tr><th>$key<td colspan=3><i style='color: #888888;'>{$itdef['display']}</i>
					<br>".settingstable($item, $itdef, $showunused);
			};
		};
		if ( $showunused ) {
			foreach ( $defnode->children() as $key => $item ) {
				if ( $item->getName() != "item" || $item['deprecated'] ) continue;
				$key = $item['key']."";
				$deftxt = $item['display'];
				if ( !$done[$key] ) {
					$tabletext .= "<tr><th style='background-color: #d2d2ff'>$key
						<td style='color: #888888;'>(unused)
						<td style='color: #888888; padding-left: 20px;'>$deftxt
						<td>$value
						";
				};
			};
		};
		$tabletext .= "</table>";
				
		return $tabletext;
	};

	function is_attribute($node) {
		$tmp = $node->asXML();
		return !( $tmp[0] == "<");
	};

	function findnode ( $xpath ) {
		global $setdef;
		$defx = "/ttsettings"; $xpath = str_replace("/ttsettings/", "", $xpath);
		foreach ( explode ( "/", $xpath ) as $xpp ) {
			if ( preg_match("/item\[@key=\"(.*?)\"\]/", $xpp, $matches ) ) {
				$xppt = "list";
			} else if ( preg_match("/@(.*)/", $xpp, $matches ) ) {
				$xppt = "att[@key=\"{$matches[1]}\"]";
			} else {
				$xppt = "item[@key=\"$xpp\"]";
			};
			
			$defx .= "/".$xppt;
		};
		
		$tmp = $setdef->xpath($defx); $defnode = $tmp[0];
		
		return $defnode;
	};
		
	function makexpath ( $node ) {
		$tn = $node;
		if ( is_attribute($node) ) {
			$xpath = "/@".$node->getName();
			$tmp = $node->xpath(".."); $tn = $tmp[0];
		}; $c=0;
		while ( $tn->getName() != "ttsettings" && $c < 10 ) {
			$c++;
			$nn = $tn->getName();
			if ( $nn == "item" ) { $nn = "{$nn}[@key=\"".$tn['key']."\"]"; };
			$xpath = "/$nn".$xpath;
			$tmp = $tn->xpath(".."); $tn = $tmp[0];
		};
		return "/ttsettings$xpath";
	};
	
?>