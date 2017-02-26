<?php
	# Module to conveniently edit the settings.xml file
	if ( !$settingsxml ) { fatal("Failed to load settings.xml");};

	check_login();

	$setdef = simplexml_load_file("$ttroot/common/Resources/adminsettings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$setdef ) { fatal("Failed to load adminsettings.xml");};

	$section = $_GET['section'];

	if ( $act == "save" ) {

		$xpath = $_POST['xpath']; 
		if ( !$xpath ) { fatal("No node indicated"); };

		$tmp = $settingsxml->xpath($xpath); 
		$valnode = $tmp[0];
		
		if ( !$tmp ) {
			# Non-existing node (attribute) - create
			$unused = "&showunused=1";
			if ( preg_match("/^(.*)\/([^\/]+$)/", $xpath, $matches ) ) {
				$parxp = $matches[1]; $thisnode = $matches[2];
				$tmp = $settingsxml->xpath($parxp); $parnode = $tmp[0];
				if ( !$tmp ) fatal("No parent node $parxp found");
				if ( substr($thisnode,0,1) == "@" ) {
					$attname = substr($thisnode,1);
					$parnode[$attname] = "";
				};
			
				$tmp = $settingsxml->xpath($xpath); $valnode = $tmp[0];
			};
			
		};
			
		if ( !$tmp ) { 
			fatal("Node not found and cannot be created: $xpath"); 
		};

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
			<script language=Javascript>top.location='index.php?action=$action&section=$section$unused';</script>
			"; exit;

	} else if ( $act == "edit" ) {

		$xpath = $_GET['node']; 
		if ( !$xpath ) { fatal("No node indicated"); };
		$tmp = $settingsxml->xpath($xpath); $valnode = $tmp[0];
		
		$defnode = findnode($xpath); $deftxt = "";

		if ( !$defnode ) { fatal ( "Node not found: $xpath" ); };

		if ( $defnode->xpath("desc") ) {
			$descnode = current($defnode->xpath("desc")); 
			$deftxt .= "<div style='color: #339933'>$descnode</div>";
		};
		
		if ( $defnode['default'] ) { 
			$defval = $defnode['default'];
			$tmp = $defnode->xpath("val[@key=\"$defval\"]"); $defdis = $tmp[0]['display'];
			$deftxt .= "<p>Default value: $defval";
			if ( $defdis ) $deftxt .= " = ".$defdis; 
		};

		$tmp = $defnode->xpath("ancestor::item[parent::ttsettings]"); 
		$section = $tmp[0]['key'];
		
		
		$xptxt = "".$xpath;
		$valtxt = addslashes($valnode);

		if ( $valtxt ) { 
			$tmp = $defnode->xpath("val[@key=\"$valtxt\"]"); $defdis = $tmp[0]['display'];
			$valdef = $valtxt." = ".$tmp[0]['display'];
		} else {
			$valdef = "(none)";
		};
		if ( !$valtxt ) $valtxt = $defnode['default']."";

		$maintext .= "<h1>Edit settings</h1>
			<p>Settings node: $xpath
			<p style='color: #666666;'>{$defnode['display']}
			$deftxt 
			<p>Current value: <b>$valdef</b>
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
			<input type=submit value=Save> 
			&bull; <a href='index.php?action=$action&section=$section'>cancel</a>
			</form>
			";

	} else if ( $act == "addelm" ) {
		
		$xpath = $_GET['node'];

		if ( !$xpath ) { fatal ("No new element xpath selected"); };
		if ( preg_match( "/\/ttsettings\/([^\/]+)/", $xpath, $matches ) ) { $section = $matches[1]; }; 
		
		# Create the element
		if ( preg_match("/^(.*)\/([^\/]+$)/", $xpath, $matches ) ) {
			$parxp = $matches[1]; $thisnode = $matches[2];
			$tmp = $settingsxml->xpath($parxp); $parnode = $tmp[0];
			if ( !$tmp ) fatal("No parent node $parxp found");
			if ( substr($thisnode,0,1) != "@" ) {
				$parnode->addChild($thisnode);
			};
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
			<script language=Javascript>top.location='index.php?action=$action&section=$section&showunused=1';</script>
			"; exit;


	} else if ( $act == "createsection" ) {
		
		$newsection = $_GET['section'];

		if ( !$newsection ) { fatal ("No new section selected"); };
		
		# Check whether known
		if ( !$setdef->xpath("/ttsettings/item[@key=\"$newsection\"]") ) { fatal ("Invalid new section: $newsection"); };
		# Check whether new
		if ( $settingsxml->xpath("/ttsettings/$newsection") ) { fatal ("Section exists: $newsection"); };

		# Create the section
		$settingsxml->addChild($newsection);
		
		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "settings-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/settings.xml", "backups/$buname");
		};
	
		
		# Now save the actual file
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action&section=$section&showunused=1';</script>
			"; exit;


	} else if ( $act == "additem" ) {
		
		$xpath = $_GET['xpath'];

		if ( !$xpath ) { fatal ("No xpath given"); };
		
		if ( preg_match( "/\/ttsettings\/([^\/]+)/", $xpath, $matches ) ) { $section = $matches[1]; }; 
		
		# Check whether new
		$tmp = $settingsxml->xpath($xpath); $valnode = $tmp[0];
		if ( !$tmp ) { fatal ("No such node: $xpath"); };

		# Create the section
		$valnode->addChild("item");
		
		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "settings-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/settings.xml", "backups/$buname");
		};
	
		# Now save the actual file
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=adminsettings&act=edit&node={$xpath}/item[not(@key) or @key=\"\"]/@key';</script>
			"; exit;

	} else if ( $section ) {
		
		$tmp = $setdef->xpath("/ttsettings/item[@key=\"$section\"]"); 
		$secdef = $tmp[0]; 
		if ( !$secdef ) { fatal ("No such section: $section"); };

		$tmp = $settingsxml->xpath("/ttsettings/$section"); 
		$valdef = $tmp[0]; 
		
		$maintext .= "<h1>Settings: $section</h1>
			<p><b>{$secdef['display']}</b></p>";
		
		if ( $secdef->desc ) {
			$maintext .= "<p>".$secdef->desc->asXML()."</p><hr>";
		};	
		
		$maintext .= settingstable($valdef, $secdef, $_GET['showunused'] );		
	
		$maintext .= "<hr><p><a href='index.php?action=$action'>back to sections</a>";
		if ( !$_GET['showunused'] ) $maintext .= " &bull; <a href='index.php?action=$action&section=$section&showunused=1'>show/edit unused items and attributes</a>";
	
	} else {
	
		$maintext .= "<h1>Settings sections</h1>
		
			<p>TEITOK is highly customizable, to make it usable for a wide range of projects.
				The customization is mostly done in the settings file, which can be edited here.
				The settings file is divided into several sections, each addressing a different
				aspect of the system.
			<hr>
		
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
					<td><a href='index.php?action=$action&act=createsection&section={$child['key']}'>create</a></td>
					<th>{$child['key']}</th>
					<td>{$child['display']}</td>
					</tr>";
			};
		};		
		$maintext .= "</table>";

		foreach ( $settings as $key => $val ) {
			if ( !$done[$key] ) $maintext .= "<p style='color: #992000'>Unknown section: $key"; 
		};		
	
		if ($notused) $maintext .= "<h2>Unused sections</h2><table>$notused</table>";
		
		if ( $user['permissions'] == "admin" ) {
			$maintext .= "<hr><p><a href='index.php?action=adminedit&id=settings.xml'>edit raw XML</a>";
		};
		
	}; 
	
	function settingstable ( $valnode, $defnode, $showunused = false ) {
		global $user;

		if ( $valnode->asXML() == "" ) return "";
		if ( !$defnode ) return "<i style='color: #992000'>Unknown field</i>";
		
		
		$tabletext .= "<table>"; unset($done);
		foreach ( $valnode->attributes() as $key => $item ) {
			$key .= ""; $done[$key] = 1; $value = "";
			$tmp = $defnode->xpath("att[@key=\"$key\"]"); $itdef = $tmp[0];
			if ( $itdef ) {
				$tmp = $itdef->xpath("val[@key=\"$item\"]"); $value = $tmp[0]['display'];
			};
			$deftxt = $itdef['display'] or $deftxt = "<i style='color: #992000'>Unknown attribute</i>";
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
				$key = $item['key'].""; $value = "";
				$deftxt = $item['display'];
				if ( $item['default'] ) $itemtxt = "default: ".$item['default']; else $itemtxt = "(unused)";
				if ( !$done[$key] ) {
					if ( $user['permissions'] == "admin" ) {
						$xpath = makexpath($valnode)."/@$key";
						$itemtxt = "<a href='index.php?action=adminsettings&act=edit&node=$xpath'  style='color: #888888;'>$itemtxt</a>";
					};
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
				$done['list'] = 1;
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
				if ( $user['permissions'] == "admin" ) {
					$xpath = makexpath($valnode)."/$key";
					$add = "<br><a href='index.php?action=adminsettings&act=addelm&node=$xpath'>create item</a>";
				};
				if ( !$done[$key] ) {
					$tabletext .= "<tr><th style='background-color: #d2d2ff'>$key
						<td style='color: #888888;'>(unused)$add
						<td style='color: #888888; padding-left: 20px;'>$deftxt
						<td>$value
						";
				};
			};
			foreach ( $defnode->children() as $key => $item ) {
				if ( $item->getName() != "list" || $item['deprecated'] ) continue;
				$key = $item['key']."";
				$deftxt = $item['display'];
				if ( !$done["list"] ) {
					$xpath = makexpath($valnode);
					if ( $user['permissions'] == "admin" ) {
						$add = "<br><a href='index.php?action=adminsettings&act=additem&xpath=$xpath'>add item</a>";
					} else $add = "";
					$tabletext .= "<tr><td>
						<td style='color: #888888;'>(unused)$add
						<td style='color: #888888; padding-left: 20px;'>$deftxt
						<td>$value
						";
				} else {
					$xpath = makexpath($valnode);
					$tabletext .= "<tr><td>
						<td><a href='index.php?action=adminsettings&act=additem&xpath=$xpath'>add item</a> 
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
			if ( $nn == "item" ) { 
				if ( $tn['key'] == "" ) $nn = "{$nn}[not(@key) or @key=\"\"]"; 
				else $nn = "{$nn}[@key=\"".$tn['key']."\"]"; 
			};
			$xpath = "/$nn".$xpath;
			$tmp = $tn->xpath(".."); $tn = $tmp[0];
		};
		return "/ttsettings$xpath";
	};
	
?>