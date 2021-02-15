<?php
	# Module to conveniently edit the settings.xml file

	# In case no settings file has been loaded, recuperate
	if ( !$settingsxml ) { 
		check_folder("Resources", "settings.xml");

		if ( file_exists("$sharedfolder/Resources/defaultsettings.xml") ) {
			$sharedloaded = 1;
			$settingsxml =  simplexml_load_file("$sharedfolder/Resources/defaultsettings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING); 			
			if ( !file_exists("Resources/settings.xml") ) copy("$sharedfolder/Resources/defaultsettings.xml", "Resources/settings.xml");
			if ( !file_exists("Resources/settings.xml") ) copy("$ttroot/common/Resources/settings.xml", "Resources/settings.xml");
		} else if ( file_exists("$sharedfolder/Resources/settings.xml") ) {
			$sharedloaded = 1;
			$settingsxml =  simplexml_load_file("$sharedfolder/Resources/settings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING); 			
			if ( !file_exists("Resources/settings.xml") ) copy("$sharedfolder/Resources/settings.xml", "Resources/settings.xml");
		} else 
			$settingsxml =  simplexml_load_file("$ttroot/common/Resources/settings.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING); 

		if ( $sharedloaded ) {
			$warning = "<p class=wrong>No settings file has been defined yet, below are the settings from the shared folder</p>";
		} else if ( file_exists("Resources/settings.xml") ) 
			$warning = "<p class=wrong>Your settings.xml file has been corrupted and is not currently loadable, below are the skeleton default settings</p>";
		else if ( is_writable("Resources") )
			$warning = "<p class=wrong>No settings file has been defined yet, below are the skeleton default settings</p>";
		else 
			fatal("No settings.xml file has been defined, and the Resources folder is not writable - please fix before continuing");
	};

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
			$dom = dom_import_simplexml($settingsxml)->ownerDocument; #->ownerDocument		
			
			createnode($dom, $xpath); 	
			$tmp = $settingsxml->xpath($xpath); 
			$valnode = $tmp[0];
			if ( !$tmp ) { print fatal("Cannot create: $xpath"); };
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
			$deftxt .= "<p>Default value: <b>$defval</b>";
			if ( $defdis ) $deftxt .= " = ".$defdis; 
		};
		
		if ( $sharedfolder ) {
			$sharedxml = simplexml_load_string(file_get_contents("$sharedfolder/Resources/settings.xml"));
			$sharednode = current($sharedxml->xpath($xpath));
			$sharedval = $sharednode."";
			$defval = $sharedval;
			if ( $sharedval ) {
				$tmp = $defnode->xpath("val[@key=\"$sharedval\"]"); $shareddis = $tmp[0]['display'];
				$deftxt .= "<p>Shared value: <b>$sharedval</b>";
				if ( $shareddis ) $deftxt .= " = ".$shareddis; 
			};
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
		if ( !$valtxt ) $valtxt = $defval;

		$maintext .= "<h1>Edit settings</h1>
			<p>Settings node: $xpath
			<p style='color: #666666;'>{$defnode['display']}
			$deftxt 
			<p>Current local value: <b>$valdef</b>
			<form action=\"index.php?action=$action&act=save\" method=post name=myform id=myform>
			<textarea style='display: none;' type=hidden name=xpath>$xptxt</textarea>
			";
		
		if ( $defnode['serverval'] && $valdef != "(none)" ) {
			$maintext .= "<div class=warning>This is a server-specific value that should ideally not be set in
				the project, but in the shared settings on the server.</div>";
		};
		
		if ( $defnode && $defnode->val && $defnode['listvals'] ) {
			foreach ( $defnode->val as $option ) {
				if ( $option["key"] == $valtxt ) $seltxt = "selected"; else $seltxt = "";
				if ( $option['deprecated'] ) $deptxt = " (deprecated)";  else $deptxt = ""; # $seltxt .= " disabled";
				if ( !$option['deprecated'] || $option["key"] == $valtxt ) $optionlist .= "<tr><th>{$option['display']}<td><a onclick='console.log(this); document.myform.newval.value=this.innerText;'>{$option['key']}</a><td>$deptxt</tr>";
			};
			$maintext .= "<p>New value: <input name=newval size=100 style='width: 80%;' value=\"$valtxt\">
				<p>Possible values (click to select):
				<table>$optionlist</table>";
		} else if ( $defnode && $defnode->val ) {
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
		if ( !$tmp ) { 
			if ( $_GET['force'] ) {
				$valnode = xpathnode($settingsxml, $xpath);
				file_put_contents("Resources/settings.xml", $settingsxml->asXML());
			} else fatal ("No such node: $xpath"); 
		};

		$tmp = explode('/', $xpath); $dxp = "/ttsettings";
		foreach ( $tmp as $prt ) {
			if ( $prt != "" && $prt != "ttsettings" ) $dxp .= "/item[@key=\"$prt\"]";
		};
		$opts = current($setdef->xpath($dxp));

		$maintext .= "<h1>Create new item</h1><p>Location: $xpath == $dxp/list</p>	
			<style>.obl { color: #992200; }</style>	
			<div>".current($opts->xpath("./desc"))."</div>
			<p><form method=post action='index.php?action=$action&act=makeitem'>
			<input name=xpath value='$xpath' type=hidden>
			<input name=goto value='{$_GET['goto']}' type=hidden>
			<table>
			";
		$optlist = current($opts->xpath("./list"));
		if ( !$optlist ) fatal("Not a list section: $xpath");
		foreach ( $optlist->children() as $opt ) {
			$mores = "";
			if ( $opt['placeholder'] ) $mores = " placeholder='{$opt['placeholder']}'";
			if ( strtolower($opt->getName()) == "list" ) {
				$maintext .= "<tr><th>{$opt['display']}<td><i>Option list - create afterward</i>";
			} else if ( !$opt['open'] && $vallist = $opt->xpath("./val") ) {
				$options = ""; $deftxt = "";
				if ( !$opt['obl'] ) {
					$options = "<option value=''>[select]</option>";
				};
				foreach ( $vallist as $val ) {
					if ( $opt['default'] == $val['key']."" ) {
						$deftxt = "<span style='color: grey'>default: {$val['display']}</span>";
						$options .= "<option value='{$val['key']}' selected>{$val['display']}</option>";
					} else {
						$options .= "<option value='{$val['key']}'>{$val['display']}</option>";
					};
				};
				$maintext .= "<tr><th>{$opt['display']}<td><select name='flds[{$opt['key']}]'>$options</select> $deftxt";
			} else if ( $opt['obl'] || $opt['key'] == "key"  || $opt['key'] == "display" ) {
				$maintext .= "<tr><th class='obl'>{$opt['display']}<td><input name='flds[{$opt['key']}]' size=40 required $mores>";
			} else {
				$maintext .= "<tr><th>{$opt['display']}<td><input name='flds[{$opt['key']}]' size=40 $mores>";
			};
		}; 
		$maintext .= "</table>
		<p><input type=submit value='Create item'>
		</form>
		<p class='obl'>Items in red are obligatory
		";

	} else if ( $act == "makeitem" ) {

		$tmp = $settingsxml->xpath($_POST['xpath']); $valnode = $tmp[0];
		if ( !$tmp ) fatal("No such section: {$_POST['xpath']}");

		# Create the section
		if ( !$_POST['flds']['key'] ) fatal("No ID given"); 
		foreach ( $valnode->xpath("./item") as $tmp ) { if ( $tmp['key'] == $_POST['flds']['key'] ) fatal("ID {$_POST['flds']['key']} already exists");  };
		$new = $valnode->addChild("item");
		foreach ( $_POST['flds'] as $key => $val ) {
			if ( $val ) $new[$key] = $val;
		};
			
		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "settings-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/settings.xml", "backups/$buname");
		};
	
		# Now save the actual file
		if ( $_POST['goto'] ) {
			$goto = $_POST['goto'];
			preg_match_all("/{#([^\}]+)}/", $goto, $tmp);
			foreach ( $tmp[1] as $key => $val ) {
				$goto = preg_replace( "/\{#$val\}/", $_POST['flds'][$val], $goto );
			};
		} else {
			$goto = "index.php?action=adminsettings&act=edit&node={$_POST['xpath']}/item[@key=\"{$_POST['flds']['key']}\"]/@key";
		};
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='$goto';</script>
			"; exit;

	} else if ( $section ) {
		
		if ( $_GET['subsection'] ) {
			$tmp = $setdef->xpath("/ttsettings/item[@key=\"$section\"]/item[@key=\"{$_GET['subsection']}\"]"); 
			$secdef = $tmp[0]; 
			if ( !$secdef ) { fatal ("No such subsection: $section/{$_GET['subsection']}"); };
			$sectiontxt = "$section/{$_GET['subsection']}";

			$tmp = $settingsxml->xpath("/ttsettings/$section/{$_GET['subsection']}"); 
			$valdef = $tmp[0]; 
		} else {
			$tmp = $setdef->xpath("/ttsettings/item[@key=\"$section\"]"); 
			$secdef = $tmp[0]; 
			if ( !$secdef ) { fatal ("No such section: $section"); };
			$sectiontxt = $section;

			$tmp = $settingsxml->xpath("/ttsettings/$section"); 
			$valdef = $tmp[0]; 
		};
		
		
		$maintext .= "<h1>Settings: $sectiontxt</h1>
			<p><b>{$secdef['display']}</b></p>";
		
		if ( $secdef->desc ) {
			$maintext .= "<p>".$secdef->desc->asXML()."</p><hr>";
		};	
		
		$maintext .= settingstable($valdef, $secdef, $_GET['showunused'] );		
	
		$maintext .= "<hr><p><a href='index.php?action=$action'>back to sections</a>";
		if ( !$_GET['showunused'] ) $maintext .= " &bull; <a href='index.php?action=$action&section=$section&subsection={$_GET['subsection']}&showunused=1'>show/edit unused/shared items and attributes</a>";
	
	} else {
	
		$maintext .= "<h1>Settings sections</h1>
		
			<p>TEITOK is highly customizable, to make it usable for a wide range of projects.
				The customization is mostly done in the settings file, which can be edited here.
				The settings file is divided into several sections, each addressing a different
				aspect of the system.
			<hr> $warning
		
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
		global $user; global $action; global $section; global $sharedfolder;

		if ( $sharedfolder && file_exists("$sharedfolder/Resources/settings.xml") ) {
			$sharedxml = simplexml_load_string(file_get_contents("$sharedfolder/Resources/settings.xml"));
		};

		if ( $valnode == null ) return "";
		if ( $valnode->asXML() == "" ) return "";
		if ( !$defnode ) return "<i style='color: #992000'>Unknown field</i>";
		
		$tabletext .= "<table>"; unset($done);
		foreach ( $valnode->attributes() as $key => $item ) {
			$key .= ""; $done[$key] = 1; $value = "";
			$tmp = $defnode->xpath("att[@key=\"$key\"]"); $itdef = $tmp[0];
			if ( $itdef ) {
				$tmp = $itdef->xpath("val[@key=\"$item\"]"); $value = $tmp[0]['display'];
				if ( $tmp[0]['deprecated'] ) $value .= " <span class=warning>(deprecated)</span>";
			};
			$deftxt = $itdef['display'] or $deftxt = "<i style='color: #992000'>Unknown attribute</i>";
			if ( $itdef['desc'] ) $deftxt .= "<p>".$itdef['desc']."</p>";
			if ( $user['permissions'] == "admin" ) {
				$xpath = makexpath($item);
				$item = "<a href='index.php?action=adminsettings&act=edit&node=$xpath'>$item</a>";
			};
			if ( $itdef['deprecated'] ) {
				$tabletext .= "<tr><th style='background-color: #ffcccc'>$key
					<td>$item
					<td style='color: #888888; padding-left: 20px;'>$deftxt <span class=warning>(deprecated)</span>
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
				$txtcol = "#888888"; $thcol = "#d2d2ff";
				if ( $sharedfolder )  {
					$xpath = makexpath($valnode)."/@$key";
					$sharednode = current($sharedxml->xpath($xpath));
				} 
				if ( $sharednode ) {
					$itemtxt = "shared: ".$sharednode;
					$txtcol = "black"; $thcol = "#d2ffd2";
				} else if ( $item['default'] ) $itemtxt = "default: ".$item['default']; else $itemtxt = "(unused)";
				if ( !$done[$key] ) {
					if ( $user['permissions'] == "admin" ) {
						$xpath = makexpath($valnode)."/@$key";
						$itemtxt = "<a href='index.php?action=adminsettings&act=edit&node=$xpath'  style='color: #888888;'>$itemtxt</a>";
					};
					$tabletext .= "<tr><th style='background-color: $thcol'>$key
						<td style='color: $txtcol;'>$itemtxt
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
				if ( $itdef['subsection'] ) $keytxt = "$key<br><a href='index.php?action=$action&section=$section&subsection=$key'>select</a>"; else $keytxt = $key;
				$tabletext .= "<tr><th>$keytxt<td colspan=3><i style='color: #888888;'>{$itdef['display']}</i>
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
					if ( $item['subsection'] ) $keytxt = "$key<br><a href='index.php?action=$action&section=$section&subsection=$key'>select</a>"; else $keytxt = $key;
					$tabletext .= "<tr><th style='background-color: #d2d2ff'>$keytxt
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

	
?>