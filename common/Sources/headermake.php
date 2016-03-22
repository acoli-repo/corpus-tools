<?php

	// An administration tool for creating teiHeader files
	
	if ( $user['permissions'] != "admin" ) {
		fatal("Super users only");
	};
	
	# Determine which teiHeader.tpl we are using
	
	$deffiles = array ( 
		"short" => "Resources/teiHeader.tpl",
		"long" => "Resources/teiHeader-long.tpl",
		"edit" => "Resources/teiHeader-edit.tpl",
		);
		
	
	if ( $act == "save" ) {
		
		foreach ( $_POST['xpath'] as $i => $xpath ) {
			if ( $_POST['name'][$i] ) {
				$rows .= "<tr><th>{%".$_POST['name'][$i]."}</th><td>{#$xpath}</td></tr>\n";
			};
		};
		
		$teiheader = "<table>\n$rows</table>";
		file_put_contents($_POST['tplfile'], $teiheader);
		
		$tplfile = str_replace("Resources/", "", $_POST['tplfile']);
		
		print "teiHeader has been saved";
		if ( $_POST['examplexml'] ) 
			print "<script language=Javascript>top.location='index.php?action=header&act=edit&cid={$_POST['examplexml']}&tpl=$tplfile'</script>";
		else 
			print "<script language=Javascript>top.location='index.php?action=$action'</script>"; 
		
	} else if ( $_POST['tpl'] ) {

		$maintext .= "<h1>teiHeader Template Creator</h1>";

		$headerxml = simplexml_load_file("../common/Resources/teiHeader.xml");

		$filename = $deffiles[$_POST['tpl']];
		
		if ( file_exists($filename) ) 
			$headerfile = file_get_contents($filename);
		$headerrows = array();

		$newrows = 10;
		
		if ( $_POST['xml'] == "xmlfiles" && $_POST['localfile'] ) {
			require ("../common/Sources/ttxml.php");
			$ttxml = new TTXML($_POST['localfile'], false);
			$examplexml = $ttxml->xml;
			$filesave = "<input type=hidden name='examplexml' value='".$ttxml->fileid."'>";
			$maintext .= "<p>Example XML file: <a href='index.php?action=file&cid=".$ttxml->fileid."' target=xml>".$ttxml->fileid."</a>";			
			$newrows = 6;
		} else if ( $_POST['xml'] == "upload" && file_exists($_FILES['upload']['tmp_name']) ) {
			$examplexml = simplexml_load_file($_FILES['upload']['tmp_name']);
			$maintext .= "<p>Example XML file: {$_FILES['upload']['name']} (uploaded file)";
			$newrows = 6;
		};

		if ( $examplexml && !$headerfile ) {
			// Use all the filled header elements 
			$fieldslist = makelist ($examplexml->teiHeader);
			foreach ( $fieldslist as $xpf ) {
				$name = ""; 
				array_push( $headerrows, array("name" => $name, "xpath" => $xpf ) );
			};
			$exampletxt .= "<p><i>Pre-filled are all xpath definitions of the filled teiHeader elements from the XML example. 
				Select the relevant elements for the teiHeader by inserting a name for it in the Field name column.</i>";
		} else if ( $examplexml ) {
			$exampletxt .= "<p><i>Below the XPath definition is the corresponding value in the example XML";
		};	
		
		$maintext .= "<p>teiHeader currently being edited: $filename ({$_POST['tpl']} header)</p>";
		
		$maintext .= "<h2>Define fields</h2>
			$exampletxt
			<form action='index.php?action=$action&act=save' method=post>
			<input type=hidden name='tplfile' value='$filename'>
			$filesave
			<table><tr><th>Field name/Known field description<th>XPath definition/Current value</tr>";
		
		if ( $headerfile ) {
			preg_match_all ( "/<tr><th>(.*?)<\/th><td>{#(.*?)}<\/td><\/tr>/", $headerfile, $matches );
			for ( $i = 0; $i<count($matches[0]); $i++ ) {
				$name = preg_replace("/{%(.*?)}/", "\\1", $matches[1][$i]); $xpath = $matches[2][$i];
				array_push( $headerrows, array("name" => $name, "xpath" => $xpath ) );
				$compare .= $matches[0][$i]."\n";
			};
			$newrows = 4;
		};
		
		# Add a number of empty rows
		for ( $i = 0; $i < $newrows; $i++ ) {
			array_push( $headerrows, array("name" => "", "xpath" => "" ) );
		};

		$compare = "<table>\n$compare</table>";
		if ( $compare != $headerfile && $headerfile) {
			$warning = "<div style='font-weight: bold; color: #992000'>The stored $filename was not automatically generated - saving from this tool might overwrite manually set parameters in the file, it might be better to edit the raw file instead.</div>";
		};

		$i=0;
		foreach ( $headerrows as $row ) {
			
			$name = $row['name']; $xpath = $row['xpath'];
			
			if ( $headerxml ) {
				$tmp = $headerxml->xpath($xpath);
				$defname = $tmp[0].""; 
				if ( $defname == "" && $xpath ) $defname = "<i style='color: #888888;'>--</i>";
				if ( $name == "" ) $name = $tmp[0]['tt:name'];
			};
			
			if ( $examplexml ) {
				$tmp = $examplexml->xpath($xpath);
				$curval = $tmp[0].""; if ( $curval == "" && $xpath )  $curval = "<i style='color: #888888;'>--</i>";
			};
			
			$maintext .= "<tr>
					<td><input size=40 name='name[$i]' value='$name'><br>$defname
					<td><input size=100 name='xpath[$i]' value='$xpath'><br>$curval
					";
			$i++;
		};
		$maintext .= "</table>
				<input type=submit value=Save> 
				</form>
				
				$warning
				
				<script langauge=Javascript>
				document.onmousedown = function(e) {
					if ( e.altKey ) {
						var xpath = document.activeElement.value;
					};
				};		
				</script>
				<div id='teidesc'></div>
		";
		
		if ($headerfile) $maintext .= "<h2>Current raw teiHeader File</h2><p><pre>".htmlentities($headerfile)."</pre>";
		
	} else {
		
		$maintext .= "<h1>teiHeader Template Creator</h1>
		
			<p>TEI is a very expressive framework, which makes it difficult to figure out which information
				from the metadata to put where, and it is difficult to actually write the XML. 
				To make that easier, TEITOK provides a simple interface where you can edit the metadata in 
				a simple table. In order for that to work, you first need to define which metadata are 
				relevant for your project, which is done in a so-called teiHeader template. Similar templates
				are also used to place a table with selected metadata on top of each XML file.
			<p>There are three templates: a short header (teiHader.tpl), a long header (teiHeader-long.tpl) and 
				a header for editing (teiHeader-edit.tpl). These files are HTML files with XPath definitions inside. 
				These files can be edited by hand in the 'edit settings' section, but to make it easier to create 
				them, this tool helps to build them either from scratch, or from an existing TEI XML. To create 
				a header, select which header to use, and which XML to use as an example.
		
		<form action='index.php?action=$action' method=post   enctype=\"multipart/form-data\">
		
		<h2>Select a template</h2>
		
		<ul>
			<li><input type=radio value='short' name=tpl> teiHeader.tpl - the short header
			<li><input type=radio value='long' name=tpl> teiHeader-long.tpl - the long header
			<li><input type=radio value='edit' name=tpl> teiHeader-edit.tpl - the edit header
		</ul>

		<h2>Select an XML example</h2>
		
		<ul>
			<li><input type=radio value='xmlfiles' name=xml> 
				Use an XML file from this project: 
				<input name=localfile size=50>
			<li><input type=radio value='upload' name=xml> 
				Upload an XML file: 
				<input type=file name=upload id=upload>
			<li><input type=radio value='common' name=xml> do not use an XML example
		</ul>
		<input type=submit value=Go>
		</form>
		";
		
	};

	function makelist ( $xml ) {
		$array = array();
		
		foreach ( $xml->children() as $childnode ) {
			if ( $childnode->count() ) {
				$array = array_merge($array, makelist($childnode));
			} else {
				$nodexpath = makexpath($childnode);
				// $nodexpath = preg_replace("/\/p$/", "", $nodexpath); // forget any <p> at the end
				if ( $childnode."" != "" ) {
					array_push($array, $nodexpath );
				};
				$noatts = preg_replace("/\[[^\]]+\]$/", "", $nodexpath);
				foreach ( $childnode->attributes() as $a => $b ) {
					array_push($array, "$noatts/@$a" );					
				};
			};
		};
		
		return $array;
	};

	function makexpath ( $node ) {
		$thisnode = $node; $sep = "";
		while ( $thisnode->xpath("..") ) {
			$nodename = $thisnode->getName();
			if  ( $thisnode->attributes() ) { 
				$asep = ""; $atts = "";
				foreach ( $thisnode->attributes() as $a => $b ) {
					$atts .= $asep."@$a=\"$b\""; $asep = " & ";
				};
				$nodename = $nodename."[$atts]";
			}
			$xpath = $nodename."$sep$xpath"; $sep = "/";
			$tmp = $thisnode->xpath(".."); $thisnode = $tmp[0];
		};
		$nodename = $thisnode->getName();
		return "/".$nodename."$sep$xpath"; 
	};

?>