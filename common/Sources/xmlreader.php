<?php

	# A tool to easily deploy a (non-nested) XML file as a table
	# Provide sort, search, and select - customizable
	# You can also add and edit items as admin
	# (c) Maarten Janssen, 2017

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
	
	if ( !file_exists("Resources/$xmlfile.xml") ) {
		file_put_contents("Resources/$xmlfile.xml", "<database></database>");
	};
	
	if ( $entry == "" && $username ) {
	
		# Not defined yet
		if ( $xmlfile && file_exists("Resources/$xmlfile.xml") ) {
			# Read XML file only when defined
			$xml = simplexml_load_file("Resources/$xmlfile.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			$tmp = current($xml->children());
			if ( $tmp ) {
				$rn = $tmp[0]->getName();
				$tryentry = "<$rn>";
				foreach ( $tmp[0]->children() as $child ) {
					$nn = $child->getName();
					$tryentry .= "\n\t<$nn>$nn</$nn>";
				};
				$tryentry .= "\n</$rn>";
			} else $tryentry = "<record></record>";
		} else $tryentry = "<record></record>";
		print "Saving temptative entry defition: "; htmlentities($tryentry);
		file_put_contents("Resources/$xmlfile-entry.xml", $tryentry);
		print "<p>Definition file does not exist - reloading to generate
			<script language=Javascript>top.location='index.php?action=adminedit&id=$xmlfile-entry.xml';</script>
		";
		exit;
	};
	
	if ( $xmlfile && file_exists("Resources/$xmlfile.xml") ) {
		# Read XML file only when defined
		$xml = simplexml_load_file("Resources/$xmlfile.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( gettype($xml) != "object" ) fatal ( "Failed to load XML file" );

		$entryxml = simplexml_load_string($entry, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( $entryxml ) $recname = $entryxml->getName().""; 

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
			$id = $newid;
		};
				
		foreach ( $_POST['newvals'] as $cn => $val ) {
			$key = $_POST['newflds'][$cn];
			$fldval = current($record->xpath($key));
			$fldrec = current($entryxml->xpath($key));
			if ( !$fldrec ) $fldrec = current($entryxml->xpath("*[@xpath=\"$key\"]"));
			print "<p>$key: $fldval (".gettype($fldval).") => $val";
			if ( $val != "" && gettype($fldval) != "object" ) { # When child does not exist
				# $fldval = $record->addChild($key);
				$key = "//{$recname}[@id='$id']/$key";
				$fldval = xpathnode($record, $key); 
			}
			if ( $fldrec['type'] == "rte" ) {
				$fldtype = $fldval->getName();
				$trval = html_entity_decode($val);
				$trval = str_replace("&lt;", "<", $trval);
				$trval = str_replace("&gt;", ">", $trval);
				$trval = str_replace("<b/>", "", $trval);
				$trval = str_replace("<i/>", "", $trval);
				$val = "<$fldtype>$trval</$fldtype>";
				replaceSimpleNode ( $fldval, $val);
			} else if ( $fldrec['type'] == "xml" ) {
				replaceSimpleNode ( $fldval, $val );
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
		<table style='width: 100%'>";
		if ( $id == "new" ) $maintext .= "<tr><th>Record ID<td><input name=newid value='' size=10>";
 
 		$cn = 0;
		foreach ( $entryxml->children() as $fldrec ) {
			$cn++;
			if ( $fldrec['xpath'] ) {
				$key = $fldrec['xpath']."";
			} else {
				$key = $fldrec->getName();
			};
			$val = $fldrec."" or $val = $key;
			if ( $record ) $fldval = current($record->xpath($key));				

			if ( $fldrec['type'] == "xml" )  {
				$xmlnum++;
				if ( $fldval ) $initcontent = $fldval[1]->asXML();
				$initcontent = htmlentities($initcontent);
				$xmlupdate .= "document.getElementById(\"frm$key\").value = editor.getSession().getValue(); ";
				$maintext .= "\n<tr><th>{%$val}<td><div id=\"editor\" style='width: 100%; height: 80px;'>$initcontent</div><textarea id='frm$key' name=newvals[$cn] style='display:none'>$fldval</textarea>";
			} else if ( $fldrec['type'] == "text" )  $maintext .= "<tr><th>{%$val}<td><textarea  name=newvals[$cn] style='width: 100%; height: 150px;'>$fldval</textarea>";
			else if ( $fldrec['type'] == "rte" ) {
				if ( $fldval ) $initcontent = $fldval[1]->asXML();
				$maintext .= "<tr><th>{%$val}<td><textarea class='rte' name=newvals[$cn] style='width: 100%; height: 50px;'>".$initcontent."</textarea>";
			} else $maintext .= "<tr><th>{%$val}<td><input name=newvals[$cn] value='$fldval' size=80>";
			$maintext .= "<input type=hidden name=newflds[$cn] value=\"$key\">";

		}; 
		$maintext .= "</table>
		<p><input type=submit value=Save  onClick=\"runsubmit();\">
		</form>
		<script type=\"text/javascript\" src=\"$tinymceurl\"></script>
		<script type=\"text/javascript\">
			tinyMCE.init({
				selector : \"textarea.rte\",
			  menu: {
				edit: {title: \"Edit\", items: \"undo redo | cut copy paste pastetext | searchreplace | selectall\"},
				insert: {title: \"Insert\", items: \"charmap pagebreak\"},
				format: {title: \"Format\", items: \"bold italic | formats | removeformat | code\"},
			  },
  				convert_urls: false,
				plugins: [
					 \"lists charmap searchreplace\",
					 \"paste pagebreak code\"
			   ],
			    extended_valid_elements: \"supplied,add,unclear,ex,hi[rend],b,i,b/strong,i/em\",
			    custom_elements: \"~supplied,~add,~unclear,~ex,~hi[rend]\",
			    valid_children : \"+p[supplied|add|unclear|ex|hi]\",
			    paste_word_valid_elements: \"b,i,b/strong,i/em,h1,h2,p\",
				content_css: \"Resources/xmlstyles.css\", 
				toolbar: \"undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent \", 

			    width: \"100%\",
			    height: 200,
			});
		</script>
		<hr>";
		
		if ( $id != "new" ) $maintext .= "
			<p>
			<a href='index.php?action=$action&id=$id'>{%back to view}</a>
			&bull; 
			<a href='index.php?action=$action&act=raw&id=$id'>edit raw XML</a>
			";
		else  $maintext .= "
			<p>
			<a href='index.php?action=$action'>{%back to list}</a>";

		if ( $xmlnum ) $maintext .= "
			<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
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
		
			<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
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
		foreach ( $entryxml->children() as $fldrec ) {
			if ( $fldrec['type'] == "separator" ) {
				$maintext .= "<tr><th colspan=2 span='column'>{%$fldrec}</th></tr>";
				continue;
			};
			if ( $fldrec['xpath'] ) {
				$key = $fldrec['xpath']."";
			} else {
				$key = $fldrec->getName();
			};
			$val = $fldrec."";
			$fldval = current($record->xpath($key))."";
			if ( $fldval == "" ) continue;
			if ( $fldrec["link"] ) {
				 $linkurl = $fldrec["link"]."";
				if ( preg_match_all("{#([^\}]+)}", $linkurl, $matches ) ) {
					foreach ( $matches[1] as $xp ) {
						$linkurl = str_replace("{#$xp}",  current($record->xpath($xp)), $linkurl);
					};
				};
				if ( $fldrec["target"] ) $target = $fldrec["target"]; $trgt = "";
				if ( $target && $target != "none" ) $trgt = " target=\"$target\"";
				if ( $linkurl != "" ) $fldval = "<a$trgt href='$linkurl'>$fldval</a>";
			} else if ( strstr($fldval, "http" ) ) $fldval = "<a href='$fldval'>$fldval</a>";
			if ( $fldrec['type'] == "xml" || $fldrec['type'] == "rte" ) {
				if ( !$fldrec['notitle'] ) $maintext .= "<tr><th span='row'>{%$val}</th><td colspan=2>".$fldval->asXML();
				else $maintext .= "<tr><td colspan=2>".$fldval->asXML();
			} else $maintext .= "<tr><th span='row'>{%$val}<td>$fldval";
		}; 
		$maintext .= "</table>";

		# If the ID is a field in CQP, render the corresponding XML files
		if ( $entryxml['cqp'] ) {
			$recid = $record['id']; $cqpfld = $entryxml['cqp'];
			# $cqlquery = "SELECT id, title FROM text WHERE {$entryxml['cqp']}='$recid'";
			
			$cql = "<text> [] :: match.text_$cqpfld=\"$recid\"";

			include ("$ttroot/common/Sources/cwcqp.php");
			$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";
			$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
			$cqpfolder = $settings['cqp']['searchfolder'];
			$cqp = new CQP();
			$cqp->exec($cqpcorpus); // Select the corpus
			$cqp->exec("set PrettyPrint off");
			$cqpquery = "Matches = $cql";
			$cqp->exec($cqpquery);
			$size = $cqp->exec("size Matches");
			
			# TODO: get morecols to show the desired title
			
			if ( $size > 0 ) {
			
				$cqptitle = $entryxml["cqptitle"] or $cqptitle = "Corresponding files";
			
				$cqpquery = "tabulate Matches match text_id $morecols";
				$results = $cqp->exec($cqpquery);
				$results = $cqp->exec($cqpquery); // TODO: Why do we need this a second time?
		
				$maintext .= "<h2>{%$cqptitle}</h2>"; unset($sortarray); $lcnt = 0;
				foreach ( explode("\n", $results ) as $line ) {
					
					list ( $cid, $texttit ) = explode ( "\t", $line );
					
					if ( !$texttit ) {
						if ( preg_match("/([^\/.]+)\.xml/", $cid, $matches) ) { $xmlid = $matches[1]; };
						$texttit = $xmlid;			
					};
					if ( $cid ) $sortarray[$cid] = $texttit;
				};	
				natsort($sortarray);
				foreach ( $sortarray as $cid => $texttit ) { 
					$lcnt++;
					$maintext .= "<p>$lcnt. <a href='index.php?action=file&cid=$cid'>$texttit</a>";
				};
			
			};
			
		};
		
		$maintext .="<hr><p><a href='index.php?action=$action'>{%back to list}</a>";
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=$action&act=edit&id={$_GET['id']}'>edit</a>";
	
	} else if ( $_GET['f'] ) {
		
		$f = $_GET['f'];
		$tittxt = current($entryxml->xpath("./$f")).""; 
		if ( !$tittxt ) $tittxt = $f;
		$maintext .= "<h2>{%Entries by} $tittxt</h2>
			
			<style>
				.private { color: #999999; };
			</style>";
		
		
		$result = $xml->xpath("//$recname"); 
		foreach ( $result as $record ) { 

			$name = current($record->xpath("name"))."";
			$cns = current($record->xpath($f))."";
			
			foreach (  explode(", ", $cns ) as $cn ) {
				$cnt[$cn]++;
				$ps[$cn] .= "<a>$name</a> ";
			};
			
		};
		$maintext .= "<table>";
		
		arsort($cnt);
		foreach ( $cnt as $key => $val ) {
			$maintext .= "<tr><td><a href='index.php?action=$action&q=$f:$key'>$key</a><td style='text-align: right; padding-left: 10px;'>$val";#.$ps[$key];
		};
		$maintext .= "</table>
			<hr><p><a href='index.php?action=$action'>back to list</a>";
		

	} else if ( $act == "search" ) {

		foreach ( $entryxml->children() as $fldrec ) {
			if ( $fldrec['type'] == "separator" ) continue;
			if ( $fldrec['xpath'] ) {
				$key = $fldrec['xpath']."";
				$key = str_replace("'", "&#039;", $key);
			} else {
				$key = $fldrec->getName();
			};
			$val = $fldrec."" or $val = $key;
			$fldsel .= "<option value='$key'>{%$val}</option>";
		}; 

		$maintext .= "
			<h2>{%Search}</h2>
			
			<form action='index.php?action=$linkaction' method=post>
			<p>{%Search}: <select name=f>$fldsel</select> <input name=q size=50 value=''>
			<input type=submit value='{%Search}'>
			</form>
			
			<hr>{%Get distribution by}: <select name=f onChange='dodist(this);'><option value=''>[{%select}]</option>$fldsel</select>
			<script language=Javascript>function dodist (elm) { window.open('index.php?action=$action&act=freq&f='+elm.value, '_self'); };</script>
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
				$fldxp = "item[@xpath=\"$fld\"]";
				$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = current($entryxml->xpath($fldxp))."" or $fldtxt = $fld; 
				$whichtxt .= "$sep<i>$fldtxt</i> = <b>$val</b>";
				$sep = " and ";
			};
			$which = "[$which]";
			$whichtxt = "<p>$whichtxt (<a href='index.php?action=$action'>reset</a>)</p>";
		} else if ( $_POST['q'] ) {
			$val = $_POST['q'];
			$fld = $_POST['f'];
			$fldxp = "item[@xpath=\"$fld\"]";
			$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = current($entryxml->xpath($fldxp))."" or $fldtxt = $fld; 
			$which = "[contains($fld/.,\"$val\")]";
			$whichtxt = "<p><i>$fldtxt</i> = <b>$val</b> (<a href='index.php?action=$action'>reset</a>)</p>";
		} else if ( $_POST['query'] ) {
			foreach ( $_POST['query'] as $fld => $val ) {	
				$which .= $sep."contains($fld/.,\"$val\")";
				$fldxp = "item[@xpath=\"$fld\"]";
				$fldtxt = current($entryxml->xpath($fld))."" or $fldtxt = current($entryxml->xpath($fldxp))."" or $fldtxt = $fld; 
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
			$tableline = "\n<tr id='$sortkey'><td>";
			if ( !$xrset["noview"] || $username ) $tableline .= "<a href='index.php?action=$action&id=$id' style='font-size: smaller;'>{%view}</a>";

			foreach ( $entryxml->children() as $fldrec ) {
				if ( !$fldrec['list'] ) continue;
				if ( $fldrec['xpath'] ) {
					$key = $fldrec['xpath']."";
				} else {
					$key = $fldrec->getName();
				};
				$keyt = urlencode($key);
				$val = current($record->xpath($key));
				if ( $fldrec["link"] ) {
					if ( substr($fldrec["link"],0,1) == "%" ) {
						$linkurl = substr($fldrec["link"],1);
						if ( preg_match_all("{#([^\}]+)}", $linkurl, $matches ) ) {
							foreach ( $matches[1] as $xp ) {
								$linkurl = str_replace("{#$xp}",  current($record->xpath($xp)), $linkurl);
							};
						};
					} else 
						$linkurl = current($record->xpath($fldrec["link"].""));
					if ( $fldrec["target"] ) $target = $fldrec["target"]; else $target = "details";
					$trgt = "";
					if ( $target && $target != "none" ) $trgt = " target=\"$target\"";
					if ( $linkurl != "" ) $val = "<a$trgt href='$linkurl'>$val</a>";
				} else if ( $fldrec["select"] ) {
					$vals = $sep = "";
					$prevq = $_GET['q']; 
					foreach ( explode ( ", ", $val ) as $tmp ) { 
						$vals .= $sep."<a class='black' href='index.php?action=$action&q=$keyt:$tmp;$prevq'>$tmp</a>"; $sep = ", ";
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
			if ( $fldrec['xpath'] ) {
				$key = $fldrec['xpath']."";
			} else {
				$key = $fldrec->getName();
			};
			$key = urlencode($key);
			$val = $fldrec."";
			$maintext .= "<th><a href='index.php?action=$action&sort=$key' class='black'>{%$val}</a>";
		}; $num = count($arraylines);
		if ( $totnum > $num ) $showing = " - {%showing} 1-$maxnum";
		$maintext .= join("\n", $arraylines)."</table><hr><p>$totnum {%results} $showing
				- <i style='color: #aaaaaa'>{%click on a value to reduce selection}</i> 
				- <i style='color: #aaaaaa'>{%click on a column to sort}</i>
				- <a style='color: #aaaaaa' href='index.php?action=$linkaction&act=search'>{%Search}</a>
				";
	
		if ( $username ) $maintext .= " - <a href='index.php?action=$linkaction&act=edit&id=new' class=adminpart>add new $recname</a>";
	};
	
	function replaceSimpleNode( $orgnode, $dostring ) {	
		$domToChange = dom_import_simplexml($orgnode);
		$domReplace = dom_import_simplexml(simplexml_load_string($dostring));
	
		$nodeImport  = $domToChange->ownerDocument->importNode($domReplace, TRUE);
	
		$domToChange->parentNode->replaceChild($nodeImport, $domToChange);
	};

?>