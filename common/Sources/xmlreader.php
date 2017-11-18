<?php

	if ( !$xmlfile ) {
		if ( !$xmlid ) $xmlid = $_GET['xmlid'] or $xmlid = $_SESSION['xmlid'];
		
		# Use the one defined xmlreader definition if there is exactly one
		if ( !$xmlid && $settings['xmlreader'] && count($settings['xmlreader']) == 1 ) {
			$tmp = current($settings['xmlreader']);
			$xmlid = $tmp['key'];
		};
		
		$xrset = $settings['xmlreader'][$xmlid];
	
		if ( !$xrset ) 
			if ( $xmlid ) fatal("No settings have been defined for $xmlid");
			else  fatal("No XML file selected");
		$_SESSION['xmlid'] = $xmlid;

		$xmlfile = $xrset["xmlfile"] or $xmlfile = $xmlid;
		$title = $xrset["title"];
		$itemtitle = $xrset["itemtitle"];
		$defaultsort = $xrset["defaultsort"];
		$recname = "entry";
		$defaultsort = "name";
	
		$description = getlangfile("{$xmlfile}_text");
		if ( !$description && $username ) $description = "<p class=adminpart>There is no description for this XML file yet, click <a href='index.php?action=pageedit&id=new&name={$xmlfile}_text.html'>here</a> to add one.</p>";
		$entry = file_get_contents("Resources/$xmlfile-entry.xml");
	};
	
	if ( $xmlfile && file_exists("Resources/$xmlfile.xml") ) {
		# Read XML file only when defined
		$xml = simplexml_load_file("Resources/$xmlfile.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$xml ) fatal ( "Failed to load XML file" );

		$entryxml = simplexml_load_string($entry, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$recname = $entryxml->getName().""; 

		$maintext .= "<h1>{%$title}</h1>";
		$id = $_GET['id'];
	
		if ( !$recname ) $recname = "entry";
		if ( !$defaultsort ) $defaultsort = "title";

	} else if ( $xmlfile ) {
		fatal("The XML file $xmlfile does not exist");
	};
	
	if ( $action == "xmlreader" ) $linkaction = "xmlreader&xmlid=$xmlid"; else $linkaction = $action; 

	if ( !$xmlfile ) {
	
		if ( $settings['xmlreader'] ) {
			# Select from the xmlreader settings
		} else {
			fatal("This function can only be called as a helper function");
		};

	} else if ( $act == "save" && $id ) {

		check_login();
		if ( $id != "new" ) {
			$result = $xml->xpath("//{$recname}[@id='$id']"); 
			$record = current($result);
			if ( !$record ) fatal ( "No such record: $id" );
		} else {
			$record = $xml->addChild($recname);
			$newid = $_POST['newid'];
			if ( $newid == "" ) {
				$newnum = 1; 
				while ( $xml->xpath("//{$recname}[@id='rec-$newnum']") ) { $newnum++; };
				$newid = "rec-$newnum";
			};
			$record['id'] = $newid;
		};
			
		foreach ( $_POST['newvals'] as $key => $val ) {
			$fldval = current($record->xpath($key));
			$fldrec = current($entryxml->xpath($key));
			print "<p>$key: $fldval (".gettype($fldval).") => $val";
			if ( $val != "" && gettype($fldval) != "object" ) { # When child does not exist
				$fldval = $record->addChild($key);
			};
			if ( $fldrec['type'] == "xml" ) {
				$somexml = 1;
				$val = str_replace("<", "x(x", $val); # TODO: This should become an addChild	
				$val = str_replace(">", "x)x", $val); # TODO: This should become an addChild	
					$val = preg_replace("/^<[^>]+>|<[^>]+>$", "", $val); # TODO: This should become innerXML or replace	
				$fldval[0] = $val;			
			} else {
				$fldval[0] = $val;
			};
		};
		
		if ($somexml) {
			$textxml = $xml->asXML();
			$textxml = str_replace("x(x", "<", $textxml);	
			$textxml = str_replace("x)x", ">", $textxml); 
			$xml = simplexml_load_string($textxml, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			# Check whether we still have valid XML
			if ( !$xml ) fatal ("Invalid XML");
		};
		
		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "$xmlfile-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/$xmlfile.xml", "backups/$buname");
		};
	
		# Now save the actual file
		file_put_contents("Resources/$xmlfile.xml", $xml->asXML());
		
		# Reload to view
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$linkaction&id={$record['id']}';</script>
			";
		exit;

	} else if ( $act == "rawsave" && $id ) {

		$newentry = simplexml_load_string($_POST['rawxml'], NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		
		$tmp = dom_import_simplexml($xml);
		$newxml = new DOMDocument('1.0');
		$tmp = $newxml->importNode($tmp, true);
		$tmp = $newxml->appendChild($tmp);
		$tmp = dom_import_simplexml($newentry);
		$xpath = new DOMXPath($newxml);
		$newelement = $newxml->importNode($tmp, true);
		$element = $xpath->query("{$recname}[@id='$id']")->item(0);
		$element->parentNode->replaceChild($newelement, $element); 

		# Save a backup copy
		$date = date("Ymd"); 
		$buname = "$xmlfile-$date.xml";
		if ( !file_exists("backups/$buname") ) {
			copy ( "Resources/$xmlfile.xml", "backups/$buname");
		};
	
		# Now save the actual file
		file_put_contents("Resources/$xmlfile.xml", $newxml->saveXML());
		
		# Reload to view
		print "<p>File saved. Reloading.
			<script language=Javascript>top.location='index.php?action=$linkaction&id={$record['id']}';</script>
			";
		exit;

			
	} else if ( $act == "edit" && $id ) {
	
		check_login();
		if ( !is_writable("Resources/$xmlfile.xml") ) {
			fatal ("Due to file permissions, the file $xmlfile.xml cannot be edited, please contact the server administrator");
		};

		if ( !$entryxml ) fatal ("Failed to read entry specifications"); 
	
		if ( $id != "new" ) {
			$result = $xml->xpath("//{$recname}[@id='$id']"); 
			$record = current($result);
			if ( !$record ) fatal ( "No such record: $id" );

			$tmp = explode ( ",", $itemtitle );
			while ( !$tit && $tick++ < 100 ) $tit = current($record->xpath(array_shift($tmp)));
		};
						
		$maintext .= "<h2>$tit</h2>
		
		<form action='index.php?action=$action&act=save&id=$id' id=frm name=frm method=post>
		<table>";
		if ( $id == "new" ) $maintext .= "<tr><th>Record ID<td><input name=newid value='' size=10>";
 
		foreach ( $entryxml->children() as $fldrec ) {
			$key = $fldrec->getName();
			$val = $fldrec."" or $val = $key;
			if ( $record ) $fldval = current($record->xpath($key));
			if ( $fldrec['type'] == "xml" )  {
				$xmlnum++;
				$xmlupdate .= "document.getElementById(\"frm$key\").value = editor.getSession().getValue(); ";
				$maintext .= "\n<tr><th>{%$val}<td><div id=\"editor\" style='width: 100%; height: 80px;'>".htmlentities($fldval[1]->asXML())."</div><textarea id='frm$key' name=newvals[$key] style='display:none'>$fldval</textarea>";
			} else if ( $fldrec['type'] == "text" )  $maintext .= "<tr><th>{%$val}<td><textarea  name=newvals[$key] style='width: 100%; height: 50px;'>$fldval</textarea>";
			else $maintext .= "<tr><th>{%$val}<td><input name=newvals[$key] value='$fldval' size=80>";
		}; 
		$maintext .= "</table>
		<p><input type=submit value=Save  onClick=\"runsubmit();\">
		</form>
		<hr>";
		
		if ( $id != "new" ) $maintext .= "
			<p>
			<a href='index.php?action=$action&id=$id'>{%back to view}</a>
			&bull; 
			<a href='index.php?action=$action&act=raw&id=$id'>{%edit raw XML}</a>
			";
		else  $maintext .= "
			<p>
			<a href='index.php?action=$action'>{%back to list}</a>";

		if ( $xmlnum ) $maintext .= "
			<script src=\"$jsurl/ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var editor = ace.edit(\"editor\");
				editor.setTheme(\"ace/theme/chrome\");
				editor.getSession().setMode(\"ace/mode/xml\");
				editor.renderer.setShowGutter(false);
			
				function runsubmit ( ) {
					$xmlupdate
					document.frm.submit();
				};
			</script>";
	
	} else if ( $act == "raw" && $id ) {
	
		if ( $id ) {
			$result = $xml->xpath("//{$recname}[@id='$id']"); 
			$person = $result[0];
			if (!$person) fatal ("No such entry: $id");
			$editxml = htmlentities($person->asXML());
		} else {
			$result = $xml->xpath("//{$recname}[@id='XXX']"); 
			$person = $result[0];
			$editxml = $person->asXML();
			$editxml = preg_replace ("/<!--.*?-->/", "", $editxml); // Remove comments
			$editxml = htmlentities($editxml);
			$id = "new";
		};
		
		$maintext .= "
			<div id=\"editor\" style='width: 100%; height: 300px;'>".$editxml."</div>
	
			<form action=\"index.php?action=$action&act=rawsave&id=$id\" id=frm name=frm method=post>
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"runsubmit();\"> 
			&bull; <a href='index.php?action=$action&id=$id'>{%cancel}</a>
			</form>
		
			<script src=\"$jsurl/ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var editor = ace.edit(\"editor\");
				editor.setTheme(\"ace/theme/chrome\");
				editor.getSession().setMode(\"ace/mode/xml\");
			
				function runsubmit ( ) {
					document.frm.rawxml.value = editor.getSession().getValue();
					document.frm.submit();
				};
			</script>
		";
	
	
	} else if ( $id ) {
		# Record details
	
		$result = $xml->xpath("//{$recname}[@id='{$_GET['id']}']"); 
		$record = current($result);
		if ( !$record ) fatal ( "No such record: $id" );
		
		if ( current($record->xpath("status")) == "private" && !$username ) fatal("Private resource"); 
		
		$tmp = explode ( ",", $itemtitle );
		while ( !$tit && $tmp ) {  $tit = current($record->xpath(array_shift($tmp))); };
		$maintext .= "<h2>$tit</h2>
		
		<table>";
		foreach ( $record->children() as $fldrec ) {
			$key = $fldrec->getName();
			$val = current($entryxml->xpath($key))."" or $val = $key;
			$fldval = current($record->xpath($key));
			if ( strstr($fldval, "http" ) ) $fldval = "<a href='$fldval'>$fldval</a>";
			$maintext .= "<tr><th>{%$val}<td>$fldval";
		}; 
		$maintext .= "</table>
		<hr><p><a href='index.php?action=$action'>{%back to the list}</a>";
		
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=$action&act=edit&id={$_GET['id']}'>edit</a>";
	
	} else if ( $_GET['f'] ) {
	
		$f = $_GET['f'];
		$maintext .= "<h1>{%Entries by} {$txts[$f]}</h1>
			
			<style>
				.private { color: #999999; };
			</style>";
		
		
		$result = $xml->xpath("//$recname"); 
		foreach ( $result as $record ) { 

			$status = current($record->xpath("status"));
			
			if ( $status == "public" || $username ) {

				$name = current($record->xpath("name"))."";
				$cns = current($record->xpath($f))."";
				
				foreach (  explode(", ", $cns ) as $cn ) {
					$cnt[$cn]++;
					$ps[$cn] .= "<a>$name</a> ";
				};

			};
			
		};
		$maintext .= "<table>";
		foreach ( $cnt as $key => $val ) {
			$maintext .= "<tr><td><a href='index.php?action=$action&q=$f:$key'>$key</a><td style='text-align: right; padding-left: 10px;'>$val";#.$ps[$key];
		};
		$maintext .= "</table>
			<hr><p><a href='index.php?action=$action'>back to list</a>";
		

	} else if ( $act == "search" ) {

		foreach ( $entryxml->children() as $fldrec ) {
			$key = $fldrec->getName();
			$val = $fldrec."" or $val = $key;
			$fldsel .= "<option value='$key'>{%$val}</option>";
		}; 

		$maintext .= "
			<h2>{%Search}</h2>
			
			<form action='index.php?action=$linkaction' method=post>
			<p>{%Search}: <select name=f>$fldsel</select> <input name=q size=50 value=''>
			<input type=submit value='{%Search}'>
			</form>
			";

	} else {

		if ( file_exists("Pages/{$xmlfile}_text.txt") ) {
			$description = getlangfile("{$xmlfile}_text");
			$maintext .= $description;
			$maintext .= "<hr>";
		} else if ( $description ) {
			$maintext .= "<p>$description</p>";
			$maintext .= "<hr>";
		};

			
		$maintext .= "<style>
				.private { color: #999999; }
				.rollovertable tr:nth-child(even) { background-color: #fafafa; }
				.rollovertable tr:hover { background-color: #ffffeb; }
				.rollovertable td { padding: 5px; }
				a.black { color: black; }
				a.black:hover { text-decoration: underline; }
			</style>";
		
		if ( $_GET['q'] ) { 
			$whichtxt = $sep = ""; 
			foreach ( explode (";", $_GET['q'] ) as $qp ) {		
				if ( !$qp ) continue;	
				list ( $fld, $val ) = explode (":", $qp );
				$which .= $sep."contains($fld/.,\"$val\")";
				$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = $fld; 
				$whichtxt .= "$sep<i>$fldtxt</i> = <b>$val</b>";
				$sep = " and ";
			};
			$which = "[$which]";
			$whichtxt = "<p>$whichtxt (<a href='index.php?action=$action'>reset</a>)</p>";
		} else if ( $_POST['q'] ) {
			$val = $_POST['q'];
			$fld = $_POST['f'];
			$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = $fld; 
			$which = "[contains($fld/.,\"$val\")]";
			$whichtxt = "<p><i>$fldtxt</i> = <b>$val</b> (<a href='index.php?action=$action'>reset</a>)</p>";
		} else if ( $_POST['query'] ) {
			foreach ( $_POST['query'] as $fld => $val ) {	
				$which .= $sep."contains($fld/.,\"$val\")";
				$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = $fld; 
				$whichtxt .= "$sep<i>$fldtxt</i> = <b>$val</b>";
				$sep = " and ";
			};	
			$which = "[$which]";
			$whichtxt = "<p>$whichtxt (<a href='index.php?action=$action'>reset</a>)</p>";
		};
		
		$maxnum = $_GET['max'] or $maxnum = $xrset['max'] or $maxnum = 250;
		
		$result = $xml->xpath("//$recname$which"); 
		$arraylines = array();
		$sort = $_GET['sort'] or $sort = $defaultsort;
		$totnum = count($result);
		$result = array_slice($result,0,$maxnum);
		foreach ( $result as $record ) { 
							
			$sortkey = current($record->xpath($sort));
			$id = current($record->xpath("@id"));
			$tableline = "<tr id='$sortkey'><td>";
			if ( !$xrset["noview"] || $username ) $tableline .= "<a href='index.php?action=$action&id=$id' style='font-size: smaller;'>{%view}</a>";

			foreach ( $entryxml->children() as $fldrec ) {
				if ( !$fldrec['list'] ) continue;
				$key = $fldrec->getName();
				$val = current($record->xpath($key));
				if ( $fldrec["link"] ) {
					$linkurl = current($record->xpath($fldrec["link"].""));
					if ( $fldrec["target"] ) $target = $fldrec["target"]; else $target = "details";
					if ( $linkurl != "" ) $val = "<a target=$target href='$linkurl'>$val</a>";
				} else if ( $fldrec["select"] ) {
					$vals = $sep = "";
					$prevq = $_GET['q']; 
					foreach ( explode ( ", ", $val ) as $tmp ) { 
						$vals .= $sep."<a class=black href='index.php?action=$action&q=$key:$tmp;$prevq'>$tmp</a>"; $sep = ", ";
					};
					$val = $vals;
				};
				$tableline .= "<td>$val</td>";
			};

			
			array_push($arraylines, $tableline);
			
		};
		sort($arraylines)."</table>";
		$maintext .= "$whichtxt<table class=rollovertable><tr><td>";
		foreach ( $entryxml->children() as $fldrec ) {
			if ( !$fldrec['list'] ) continue;
			$key = $fldrec->getName();
			$val = $fldrec."";
			$maintext .= "<th><a href='index.php?action=$action&sort=$key' style='color: black'>{%$val}</a>";
		}; $num = count($arraylines);
		if ( $totnum > $num ) $showing = " - {%showing} 1-$maxnum";
		$maintext .= join("\n", $arraylines)."</table><hr><p>$totnum {%results} $showing
				- <i style='color: #aaaaaa'>{%click on a value to reduce selection}</i> 
				- <i style='color: #aaaaaa'>{%click on a column to sort}</i>
				- <a style='color: #aaaaaa' href='index.php?action=$linkaction&act=search'>{%search}</a>
				";
	
		if ( $username ) $maintext .= " - <a href='index.php?action=$linkaction&act=edit&id=new' class=adminpart>add new $recname</a>";
	};
	

?>