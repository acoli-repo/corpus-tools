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
		$recname = "project";
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
		$recname = $entryxml->getName(); 

		$maintext .= "<h1>{%$title}</h1>";
		$id = $_GET['id'];
	
		if ( !$recname ) $recname = "entry";
		if ( !$defaultsort ) $defaultsort = "title";

	} else if ( $xmlfile ) {
		fatal("The XML file $xmlfile does not exist");
	};

	if ( !$xmlfile ) {
	
		if ( $settings['xmlreader'] ) {
			# Select from the xmlreader settings
		} else {
			fatal("This function can only be called as a helper function");
		};

	} else if ( $act == "edit" && $id ) {
	
		check_login();
		if ( !$entryxml ) fatal ("Failed to read entry specifications"); 
	
		$result = $xml->xpath("//{$recname}[@id='$id']"); 
		$record = current($result);
		if ( !$record ) fatal ( "No such record: $id" );
		
		if ( current($record->xpath("status")) == "private" && !$username ) fatal("Private resource"); 
		
		$tmp = explode ( ",", $itemtitle );
		while ( !$tit && $tick++ < 100 ) $tit = current($record->xpath(array_shift($tmp)));
		$maintext .= "<h2>$tit</h2>
		
		<form action='index.php?action=$action&act=save&id=$id' method=post>
		<table>";
		foreach ( $entryxml->children() as $fldrec ) {
			$key = $fldrec->getName();
			$val = $fldrec."" or $val = $key;
			$fldval = current($record->xpath($key));
			$maintext .= "<tr><th>{%$val}<td><input name=$key value='$fldval' size=80>";
		}; 
		$maintext .= "</table>
		<p><input type=submit value=Save>
		</form>
		<hr><p>
			<a href='index.php?action=$action&id=$id'>{%back to view}</a>
			 &bull; 
			<a href='index.php?action=$action&act=raw&id=$id'>{%edit raw XML}</a>
			";
	
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
	
			<form action=\"index.php?action=$action&act=save&id=$id\" id=frm name=frm method=post>
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"runsubmit();\"> 
			&bull; <a href='index.php?action=$action&id=$id'>{%cancel}</a>
			</form>
		
			<script src=\"http://alfclul.clul.ul.pt/teitok/ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
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
		};
		
		$result = $xml->xpath("//$recname$which"); 
		$arraylines = array();
		$sort = $_GET['sort'] or $sort = $defaultsort;
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
		$maintext .= join("\n", $arraylines)."</table><hr><p>$num {%results} - <i style='color: #aaaaaa'>{%click on a value to reduce selection}</i> - <i style='color: #aaaaaa'>{%click on a column to sort}</i>";
	
	};
	

?>