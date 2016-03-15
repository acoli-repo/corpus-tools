<?php
	// Script to allow visualizing and editing
	// a dictionary that is based on the corpus
	// (c) Maarten Janssen, 2015
		
	if ( $_GET['did'] ) {
		$_SESSION['did'] = $_GET['did'];
	};
	$did = $_SESSION['did'];
	if ( $settings['xdxf'] ) {
		if ( $did ) { 
			$dict = $settings['xdxf'][$did];
		} else if ( count($settings['xdxf']) == 1 ) {	
			$did = $dict['key'];
			$dict = array_shift($settings['xdxf']);
		};
		$dictfile = $dict['filename'];
	} else {
		 $dictfile = "dict.xml";
	};
	$filename = "Resources/$dictfile";
	$id = $_GET['id'];

	$arxpath = $dict['entry'] or $arxpath = "//lexicon/ar";
	$hwxpath = $dict['headword'] or $hwxpath = "k";

	if ( $filename && !file_exists($filename) ) { fatal("Dictionary file not found: $filename"); }; 

	if ( !$dictfile ) {
		
		if ( $settings['xdxf'] ) {
			$maintext .= "<h1>XDXF Dictionay Tool</h1>
				<p>Choose a dictionary from the list below";
			foreach ( $settings['xdxf'] as $key => $dict ) {
				$maintext .= "<p><a href='index.php?action=$action&did=$key'>{$dict['title']}</a>";
			};
		} else {
			fatal("no dictionary selected");
		};	
		
	} else if ( $username && $act == "save" && $id ) {

		$newrec = $_POST['rawxml']; 
		$file = file_get_contents($filename); 
		
		# Check if this new person is valid XML
		$test = simplexml_load_string($newrec, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$test ) fatal ( "XML Error in AR record. Please go back and make sure to input valid XML" );
		
		if ( $id == "new" ) {
			# Add the new record after the first entry
			$newrec = preg_replace ( "/<!--.*?-->/", "", $newrec );
			$newxml = preg_replace ( "/<\/lexicon>/smi", $newrec."\n\n</lexicon>", $file, 1 );
			$newk = $test->k[0]."";
			if ( !$newk ) { fatal ("No headword given"); };
		} else {
			# Overwrite the existing record
			$newxml = preg_replace ( "/<ar id=\"$id\".*?<\/ar>/smi", $newrec, $file );
		};
		
		$test = simplexml_load_string($newxml, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$test ) fatal ( "XML Error in XDXF - something went wrong here (record XML was valid though)" );
		
		# print $newxml; exit;
		# Save new XML to file
		file_put_contents($filename, $newxml);
		
		if ( $id == "new" ) {
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&act=renumber&toword=$newk'</script>"; 
		} else {
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&id=$id'</script>"; 
		};
		exit;
		
	} else if ( $act == "edit" ) {
	
		$xml = simplexml_load_file($filename, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( $id && $id != "new" ) {
			$result = $xml->xpath($arxpath."[@id=\"$id\"]"); 
			$maintext .= "<h1>Edit dictionary item $id</h1>";
		} else {
			$result = $xml->xpath("//ar[@id=\"new\"]"); 
			$maintext .= "<h1>New dictionary item</h1>";
		};
		
		foreach ( $result as $entry ) {
			$editxml = $entry->asXML();
			$editxml = htmlentities($editxml, ENT_QUOTES, "UTF-8");
			$id = $entry['id'];
		};
										
		$maintext .= "
			<div id=\"editor\" style='width: 100%; height: 300px;'>".$editxml."</div>

			<form action=\"index.php?action=$action&act=save&id=$id\" id=frm name=frm method=post>
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"runsubmit();\"> 
			<input type=button value=Cancel onClick=\"window.open('index.php?action=$action', '_self');\"> 
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
		
	} else if ( $act == "generate" ) {
	
		fatal ("Generation not implemented yet");
	
	} else if ( $act == "renumber" ) {
	
		# Each <ar> needs a unique ID
		check_login();
		$id = 1;
		$xml = simplexml_load_file($filename, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$result = $xml->xpath($arxpath); 
		foreach ( $result as $entry ) {
			$entry['id'] = $id;
			$id++;
		};
		file_put_contents($filename, $xml->asXML()); 
		header("location:index.php?action=$action");
		exit;
		
	} else {
		
		$cssfile = $dict['css'] or $cssfile = "dict.css";
		if ( file_exists("Resources/$cssfile") ) {
			$maintext .= "\n<style type=\"text/css\"> @import url(\"Resources/$cssfile\"); </style>\n";
		} else {
			$css = file_get_contents("../common/Resources/dict.css");
			$maintext .= "\n<style>\n$css\n</style>\n";
		};
		
		$file = file_get_contents($filename);

		$max = $_GET['max'] or $max = 100;
		$query = $_GET['query'];
	
		if ( preg_match("/lang_from=\"(.*?)\"/", $file, $matches) ) $langfrom = "{%lang-{$matches[1]}}";
		if ( preg_match("/lang_to=\"(.*?)\"/", $file, $matches) ) $langto = " - {%lang-{$matches[1]}}";
		
		
		$dicttitle = $dict['title'];
		$maintext .= "\n<h1>$dicttitle</h1>";
		if ( $_GET['match'] == 1 ) $msel = "selected";
		if ( $_GET['match'] == 2 ) $msel2 = "selected";
		if ( $_GET['match'] == 3 ) $msel3 = "selected";
		
		$maintext .= "<form action='index.php'>{%Word} 
			<select name=match>
				<option value=\"\">{%contains}</option>
				<option value=\"1\" $msel>{%matches}</option>
			</select>
			<input name=query value=\"$query\">
			<input type=hidden name=action value=\"$action\">
			<input type=Submit value=\"{%Search}\">
			</form>
			<hr>
			<div id=\"dict\">";

		if ( !$query && !$id && !$debug ) {

			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Dict XML Error - unable to read $filename"; exit; };

			if ( file_exists("Resources/dictInfo-$did.tpl") ) { 
				$header = file_get_contents("Resources/dictInfo-$did.tpl");
			} else {
				$header = "<table>
					<tr><th>{%Author}</th><td>{#//author}</td>
					<tr><th>{%Description}</th><td>{#//description}</td>	</tr>
					</table>
					";
			};
			$maintext .= xpathrun($header, $xml);
			
		
		} else {

			$xml = new DOMDocument();
			$xml->load($filename);
			if ( !$xml ) { print "Dict XML Error"; exit; };
			$xpath = new DOMXPath($xml);
			$noregex = 1;
			
			$kn = $_GET['kn'] or $kn = $hwxpath;
			
			if ( $id ) $restr = "@id=\"$id\"";
			else if ( $query && $_GET['match'] == 1 ) $restr = $kn."[.='$query']"; // Match
			else if ( $query && $noregex ) $restr = $kn."[contains(.,'$query')]"; // Contains
			else if ( $query ) { // Regexp - does not work
				$xpath->registerNamespace("php", "http://php.net/xpath");
				$xpath->registerPHPFunctions();
				$restr = $kn."[php:functionString(\"preg_match\",\"/$query/\",.)]";
			};
			
			$xquery = $arxpath;			
			if ( $restr ) $xquery .= "[$restr]";
		
			if ( $debug ) $maintext .= "<p>XPath query: $xquery";
			$result = $xpath->query($xquery); 
			if ( $result ) {
				$count = $result->length;
				if ( $count > $max ) $showing = " - {%showing first} $max";
				$maintext .= "<p><i>$count {%results}</i> $showing</p>\n";
				$sortarray = array();
				foreach ( $result as $entry ) {
					$entryxml = $entry->ownerDocument->saveXML($entry);
					$arid = $entry->getAttribute("id");
					$tmp = $xpath->query($hwxpath, $entry); $ark = $tmp->item(0)->textContent;
					if ( $username && $arid ) $entryxml = "<div onClick=\"window.open('index.php?action=$action&act=edit&id=$arid', '_self')\">".$entryxml."</div>";
					array_push ( $sortarray, "<div k=\"$ark\">".$entryxml."</div>" );
					if ( $cnt++ > $max ) break;
				};
				natsort($sortarray);
				$maintext .= join ( "\n", $sortarray );
			} else $maintext .= "<i>{%No results found}</i>";
		
		};
			
	};

?>