<?php

	if ( $user['permissions'] != "admin" ) fatal("Only administrator level users can edit the metadata settings");
	
	if ( !$settings['teiheader'] && $act != "makesettings" ) {
		fatal("The teiHeader section of the settings is not yet defined. Check your settings first in Admin > Check configuration");
	};

	$defaults = simplexml_load_file("$ttroot/common/Resources/teiHeader.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

	$binvals = array ( "cqp",  "add", "noshow", "nosearch", "noedit", "i18n" );
	
	if ( $settings['teiheader']['recqp'] && ( $act == "save" || $act == "toggle") ) $recqpact = "&act=recqp"; 
	
	if ( $act == "toggle" ) {

		$headersettings = current($settingsxml->xpath("//teiheader"));
		$headersettings['recqp'] = $_POST['recqp'];
		file_put_contents("Resources/settings.xml", $settingsxml->asXML());

		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action$recqpact';</script>
			"; exit;
		
	} else if ( $act == "save" ) {   	
	
		$fldns = array();
		if ( $_POST['fldn'] ) {
			foreach ( $_POST['fldn'] as $key => $fldn ) {
				$fldd = $_POST['fldd'][$key];
				if ( $fldn ) array_push($fldns, array($fldn, $fldd));
			};
		};
		unset($_POST['fldn']); unset($_POST['fldd']); 
		
		$fid = $_POST['fid'];
		if ( $fid == "_new" ) {
			$tmp = current($settingsxml->xpath("//teiheader"));
			$settingsnode = $tmp->addChild("item");	
		} else if ( preg_match("/^\d+$/", $fid) ) {
			$fid++;
			$settingsnode = current($settingsxml->xpath("//teiheader/item[$fid]"));		
		} else {
			$settingsnode = current($settingsxml->xpath("//teiheader/item[@key='$fid']"));
		};
		

		foreach ( $binvals as $key ) 
			unset($settingsnode[$key]);
		
		unset($_POST['fid']); 
		foreach ( $_POST as $key => $val ) {
			if ( !$val ) continue;
			$settingsnode[$key] = $val;
		};
		if ( count($fldns) > 0 ) {
			$tmp = current($settingsnode->xpath("./options"));
			if ( $tmp ) unset($tmp[0]);
			$optionsnode = $settingsnode->addChild("options");
			foreach ( $fldns as $key => $val ) {
				$newval = $optionsnode->addChild("item");
				$newval['key'] = $val[0];
				$newval['display'] = $val[1];
			};
		};
		print htmlentities($settingsnode->asXML());
		
		# Save a backup copy
		$date = date("Ymd"); 
		$fldr = "Resources"; $id = "settings.xml";
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $id );
		$buname = preg_replace ( "/.*\//", "", $buname );
		if ( !file_exists("backups/$buname") ) {
			copy ( "$fldr/$id", "backups/$buname");
		};
	
		file_put_contents("$fldr/$id", $settingsxml->asXML());
		
		# Now save the actual file
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action$recqpact';</script>
			"; exit;
	
	} else if ( $act == "assign" ) {   	
	
		$i=0;
		foreach ( $settingsxml->xpath("//teiheader/item") as $headerfield ) {
			
			$i++; 
			if ( !$headerfield['key'] ) {
				$tmp = current($defaults->xpath($headerfield['xpath'])); 
				$newkey = $tmp['fldid'] or $newkey = "fld".$i;
				print "<p>".$headerfield['xpath'].": $newkey";
				$headerfield['key'] = $newkey;
			};
		};		

		# Save a backup copy
		$date = date("Ymd"); 
		$fldr = "Resources"; $id = "settings.xml";
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $id );
		$buname = preg_replace ( "/.*\//", "", $buname );
		if ( !file_exists("backups/$buname") ) {
			copy ( "$fldr/$id", "backups/$buname");
		};

		file_put_contents("$fldr/$id", $settingsxml->asXML());
		
		# Now save the actual file
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action$recqpact';</script>
			"; exit;
	
	} else if ( $act == "makesettings" ) {
	
		check_login();
		# Temporary function to create settings definitions from teiHeader-edit.tpl

		$headerfile = file_get_contents("Resources/teiHeader-edit.tpl");
		$maintext .= "<h1>Building Settings</h1>";

		$rows = "	<teiheader>\n";
		if ( $headerfile ) {
			preg_match_all ( "/<tr><th>(.*?)<\/th><td>(.*?)<\/td>/", $headerfile, $matches );
			for ( $i = 0; $i<count($matches[0]); $i++ ) {
				$name = preg_replace("/{%(.*?)}/", "\\1", $matches[1][$i]); 
				$xpath = preg_replace("/{#(.*?)}/", "\\1", $matches[2][$i]);
				
				# Correct the XPath
				if ( substr($xpath, 0, 11) == "//teiHeader" ) $xpath = str_replace("//teiHeader", "/TEI/teiHeader", $xpath);
				$xpath = str_replace('"', "'", $xpath);				
				$node = $defaults->xpath($xpath); # Check whether it exists
							
				$rows .= "		<item xpath=\"$xpath\" display=\"$name\"/>\n";
			};
		};
		$rows .= "	</teiheader>\n";
		

		if ( !$settings['teiheader'] ) {
			$tmp = file_get_contents("Resources/settings.xml");
			$tmp = str_replace("\n</ttsettings>", "$rows\n</ttsettings>", $tmp);

			$newxml = simplexml_load_string($tmp, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			file_put_contents("Resources/settings.xml", $newxml->asXML()); 

			$maintext .= "<p>New section saved</p><pre>".htmlentities($rows)."</pre>";
		} else 		$maintext .= "<p>Section exists, but content would be as follows</p><pre>".htmlentities($rows)."</pre>";
		
		
	} else if ( $_GET['id'] ) {

		$fid =  $_GET['id'];
		if ( $fid != "_new" ) {
			$fielddefs = $settings['teiheader'][$fid];
			if ( !$fielddefs ) fatal("No such field: $fid");
		};

		if ( $tmp = $defaults->xpath($fielddefs['xpath']) ) {
			$defdesc = current($tmp); 
			$defdesc = "<span style='color: #ff9999'>Non-standard field</span>"; 
				$nonstandard = "<p><i>\"Non-standard field\" in the table above does not mean the field does not follow the TEI standard, it merely means it does no appear on the <a href='index.php?action=metadata'>list of recommended fields</a> kept in TEITOK to improve compatibility between projects</i></p>
				<script language=Javascript>
					function valfill(elm) {
						var newval = elm.textContent;
						document.getElementById('xpfld').value = newval;
					};
				</script>";	
			
			# Try to find the corresponding field
			$tmp = preg_replace("/.*\//", "", $fielddefs['xpath']);
			if ( $tmp && substr($tmp,0,1) != "@" ) {
				$tmp = "//$tmp";
				$tmp2 = $defaults->xpath($tmp);
				if ( $tmp2 ) {
					$nonstandard .= "<p>Did you mean: (click to auto fill) <ul>";
					foreach ( $tmp2 as $key => $val ) {
						$nxp = makexpath($val);
						if ( $val['ida'] ) $nxp .= "[@{$val['ida']}='".$val[$val['ida']]."']";
						$nonstandard .= "<li> <a onClick='valfill(this);'>$nxp</a>: ".$val->asXML();
					};
					$nonstandard .= "</ul>";
				} else {
					$nonstandard .= "<p>TRYING: $tmp";
				};
			};	
		};
		
		$i = 0; $none = "none"; foreach ( $fielddefs['options'] as $key => $val ) {
			$knownvals .= "<tr><td><input name=\"fldn[$i]\" size=20 value=\"{$val['key']}\">
							<td><input name=\"fldd[$i]\" size=40 value=\"{$val['display']}\">";
			$i++;
		};
		
		
		foreach ( $binvals as $fld ) {
			if ($fielddefs[$fld]) $chkd[$fld] = "checked";
		};
		
		foreach ( $fielddefs as $key => $val ) $fielddefs[$key] = str_replace("'", "&rsquo;", $val);
		if ( $fielddefs['input'] == "select" ) {	
			$schecked = "checked"; $none = "block";
		};

		if ( preg_match('/\/\//', $fielddefs['xpath'] ) ) $nonstandard .= "<p class=wrong>Your XPath {$fielddefs['xpath']} definition are relative, which can lead to problems; it is best to no use // in your XPath, and start with /TEI/teiHeader";

		$maintext .= "<h1>Metadata field: $fid</h1>
			<form action='index.php?action=$action&act=save' method='post'>
			<input type=hidden name=fid value='$fid'>
			<table style='width: 100%;'>
			<tr><th>Field ID<td><input style='width: 100%' name='key' value='{$fielddefs['key']}'>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Unique identifier for this field (also used for CQP corpus)
			<tr><th>Display name<td><input style='width: 100%' name='display' value='{$fielddefs['display']}'>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Text used to display this value
			<tr><th>XPath location<td><input size=80 style='width: 100%' name='xpath' id='xpfld' value='{$fielddefs['xpath']}'>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Indication of where in the XML this value is placed
			<tr><th>Description<td><input size=80 style='width: 100%' name='description' value='{$fielddefs['description']}'>
				<br><i style='color: #999999'>$defdesc</i>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Description of the content of this field (indications in italic are the default interpretations for this TEI path)
			<tr><th rowspan=2>Views<td><input style='width: 100%' name='show' value='{$fielddefs['show']}'>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Which views to include this value in (default views: short, long)
			<tr><!--><td><input type=checkbox name='noshow' {$chkd['noshow']} value='1'> Not shown in lists 
					<br><input type=checkbox name='i18n' {$chkd['i18n']} value='1'> Internationalize this value 
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'> Additional view options
			<tr><th rowspan=1>CQP<td><input type='checkbox' {$chkd['cqp']} name='cqp' value='1'> Searchable (as text_$fid)
					<br><input type='checkbox' {$chkd['nosearch']} name='nosearch' value='1'> Do not show in query builder
				<td rowspan=1 style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>Settings for the searchable corpus (CQP)
			<tr><th>Input type<td><input type=checkbox name='input' $schecked id='selbox' value='select' onChange='togglefixed();'> select from list 
						<div id='fixopts' style='display: $none; margin-top: 15px;'><h3>Fixed field values</h3>
						<span onClick=\"addfield('fixedlist', 'fixed-%')\">add new value</span>
						<table id='fixedlist'>
							<tr><th>Field value<th>Display name
							$knownvals
						</table>
						<p><input type=checkbox name='add'  {$chkd['add']} value='1'> can add new values 
						</div>
				<td style='font-style: italic; color: #bbbbbb; padding-left: 10px;'>How values are edit in the edit window - select fields need either a list of fixed values, or a CQP name (in which case values are selected from the searchable corpus)
			</table>
			<hr>
			
			<script language=Javascript>
				function togglefixed() {
					var none = 'none';
					if ( document.getElementById('selbox').checked ) none = 'block';
					document.getElementById('fixopts').style.display = none;
				};
				function addfield(nodeid) {
					var tabl = document.getElementById(nodeid);
					if ( tabl.nodeName != 'TABLE' ) { console.log('Not a table: '+nodeid); };

					var lastidx = tabl.getElementsByTagName('TD').length/2;

					var row = tabl.insertRow();
					var cell1 = row.insertCell(0);
					var cell2 = row.insertCell(1);

					cell1.innerHTML = '<input name=\"fldn['+lastidx+']\" size=20>';
					cell2.innerHTML = '<input name=\"fldd['+lastidx+']\" size=40>';
				};
			</script>
			
			<input type=submit value='Save'> <a href='index.php?action=$action&act=details'>cancel</a>
			</form>
			$nonstandard
			";
		
		
	} else if ( $act == "recqp" ) {

		check_login();
		if ( !$settingsxml ) { fatal("Failed to load settings.xml");};

		if ( $settings['teiheader']['recqp'] || $_GET['force'] ) {
			# Rewrite //cqp/sattributes/text
			$textsettings = current($settingsxml->xpath("//cqp/sattributes/item[@key=\"text\"]"));
			foreach ( $textsettings->xpath("item") as $tmp ) {
				unset($tmp[0]);
			};
			foreach ( $settingsxml->xpath("//teiheader/item[@cqp]") as $tmp ) {
				$newitem = $textsettings->addChild("item");
				$newitem['key'] = $tmp['key']; 
				$newitem['xpath'] = $tmp['xpath']; 
				$newitem['display'] = $tmp['display']; 
				if ( $newitem['nosearch'] ) $tmp['nosearch'] = "1"; 
				if ( $newitem['noshow'] ) $tmp['noshow'] = "1"; 
			};
		} else {
			if ( $_GET['fld'] && $_GET['set'] != "add" ) {
				$fld = current($settingsxml->xpath("//cqp/sattributes/item[@key=\"text\"]/item[@key=\"{$_GET['fld']}\"]"));
			};

			if ( $_GET['set'] == "del" && $fld ) {
				unset($fld[0][0]);
			} else if ( $_GET['set'] == "change" && $fld ) {
				$fld['xpath'] = $_GET['xpath'];
			} else if ( $_GET['set'] == "add" ) {
				$prnt = current($settingsxml->xpath("//cqp/sattributes/item[@key=\"text\"]"));
				$fld = $prnt->addChild('item');
				$fld['key'] = $_GET['fld'];
				$fld['xpath'] = $_GET['xpath'];			
				$fld['display'] = $_GET['name'];			
				print htmlentities($prnt->asXML()); 
			};
		};
		
		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "settings-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/settings.xml", "backups/$buname");
		};
	
		# Now save the actual file
		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($settingsxml->asXML());
		file_put_contents("Resources/settings.xml", $dom->saveXML());
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$action';</script>
			"; exit;
			
	} else {

		if ( !$defaults ) fatal("Unable to load default teiheader");

		# Read CQP data
		$cqpdefs = $settings['cqp']['sattributes']['text']; 

		if ( $settings['teiheader']['recqp'] ) $schecked = "checked";
		$maintext .= "<h1>Metadata fields defined in this corpus</h1>
		
			<p><form id=toggle action='index.php?action=$action&act=toggle' method=post><input type=checkbox value='1' name='recqp' onChange=\"document.getElementById('toggle').submit()\" $schecked> Overwrite settings for text in <a href='index.php?action=adminsettings&section=cqp'>CQP section</a> based on settings defined here</form></p>
		
			<table>
			<tr><th>Field ID<th>Display Name<th>Description (<i>Default for this XPath</i>)<th>Options<th>Views";
			if ( $showxp ) $maintext .= "<th>XPath query";
			
		foreach ( $settings['teiheader'] as $headerfield ) {
			if ( !is_array($headerfield) ) continue;

			if ( $headerfield['type'] == "sep" ) {
				$maintext .= "<tr><th colspan=8>{$headerfield['display']}";
				continue;
			};
			
			$xquery = $headerfield['xpath'] or $xquery = $headerfield['key'];
			
			$desc = $headerfield['description'];
			$defdesc = current($defaults->xpath($xquery)); 
			if ( !$defdesc ) {
				$defdesc = "<span style='color: #ff9999'>Non-standard field</span>";
				$nonstandard = "<p><i>\"Non-standard field\" in the table above does not mean the field does not follow the TEI standard, it merely means it does no appear on the <a href='index.php?action=metadata'>list of recommended fields</a> kept in TEITOK to improve compatibility between projects</p>";	
		};
	
			if ( $desc ) {
				$desc .= "<br><i>$defdesc</i>";
			} else $desc = "<i>$defdesc</i>";	

			$hops = ""; $sep = ""; $cqp = "";
			if ( $headerfield['cqp'] ) { $hops .= $sep."search";$sep = ","; $cqp = $headerfield['key']; }
			if ( $headerfield['options'] ) { $hops .= $sep."fixedvalues"; $sep = ","; }
			if ( $headerfield['i18n'] ) { $hops .= $sep."i18n"; $sep = ","; }
			if ( $headerfield['input'] == "select" ) { 
				if ( $headerfield['cqp'] ||  $headerfield['options'] ) $hops .= $sep."select";
				else $hops .= $sep."select <span class=wrong><- requires CQP or FIXEDVALUES!</span> ";
				$sep = ","; 
				if ( $headerfield['add'] ) $hops .= $sep."add";
			}

			$cqpdef = $cqpdefs[$cqp.''];
			$cqpfld = str_replace("text_", "", $cqpdef['key']);
			unset($cqpdefs[$cqp.""]); 
			
			if ( $cqpdef['display'] ) {
				$cqp .= "<br><i>{$cqpdef['display']}</i>";
				if ( $cqpdef['xpath'] != $xquery ) {
					$cqpwarn .= "<p>$cqpfld: {$cqpdef['display']} = {$cqpdef['xpath']} != {$xquery} (<a href='index.php?action=$action&act=recqp&set=change&fld={$headerfield['cqp']}&xpath=".urlencode($xquery)."'>change</a>)";
				};
			} else if ( $cqp ) {
				$cqpwarn .= "<p>$cqp: Not defined in CQP section (<a href='index.php?action=$action&act=recqp&set=add&fld=$cqp&name={$headerfield['display']}&xpath=".urlencode($xquery)."'>add</a>)";
			};
			
			$key = $headerfield['key'];
			if ( !$key ) { $key = "<span class=wrong>NO ID</span>"; $warns = "<p class=wrong>To be editable, all fields require a uniquely identifying key - click <a href='index.php?action=$action&act=assign'>here</a> to assign keys automatically</p>"; };
			$fields[$i++] = $headerfield['display'];
			
			if ($headerfield['show']) foreach ( explode(",", $headerfield['show']) as $viewf ) $views[$viewf] .= "$key,";
			$maintext .= "\n\t<tr><td><a href='index.php?action=$action&id=$key'>$key</a><th view=\"{$headerfield['view']}\">{$headerfield['display']}<td>$desc<td>$hops<td>{$headerfield['show']}";
			if ( $showxp ) $maintext .= "<td>$xquery";
			
		};
		$maintext .= "</table>$warns";
		
		# Show the missing CQP fields
		foreach ( $cqpdefs as $rest ) {
			if ( !is_array($rest) ) continue;
			$resttxt .= "<p>{$rest['key']}: {$rest['display']} = {$rest['xpath']} (<a href='index.php?action=$action&act=recqp&set=del&fld={$rest['key']}'>remove</a>)";
		};
		
		
		$maintext .= "<hr><p><a href='index.php?action=$action&id=_new'>add new metadata field</a> &bull; <a href='index.php?action=metadata'>view recommended metadata fields</a>
						$nonstandard 
			";
		if ( !$settings['teiheader']['recqp'] ) $maintext .= "&bull; <a href='index.php?action=$action&act=recqp&force=1'>overwrite text-level settings in CQP</a>";			
		
		# Show the views
		$maintext .= "<h2>Views</h2>";
		if ( file_exists("Resources/teiHeader.tpl") ) $maintext .= "<p style='font-style: italic; color: #999999;'>There is a teiHeader.tpl file for this corpus, which will be used instead of the settings here";
		$maintext .="<table>";
		foreach ( $views as $key => $val ) {
			$val = preg_replace("/,$/", "", $val);
			$maintext .= "<tr><th>$key<td>$val";
		};
		$maintext .= "</table>";
		if ( !$views['short'] ) $maintext .= "<p class=warning>Nothing defined for short view - nothing will be shown above the text";
		if ( !$settings['teiheader']['recqp'] ) {
			if ( $resttxt ) $maintext .= "<h2>Non-used CQP fields</h2>$resttxt";
			if ( $cqpwarn ) $maintext .= "<h2>CQP field mismatches</h2>$cqpwarn";
		};
	};