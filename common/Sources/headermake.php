<?php

	// An administration tool for creating teiHeader files
	
	check_login();

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
		
		if ( $_POST['xml'] == "xmlfiles" && $_POST['localfile'] ) {
			require ("../common/Sources/ttxml.php");
			$ttxml = new TTXML($_POST['localfile'], false);
			$examplexml = $ttxml->xml;
			$filesave = "<input type=hidden name='examplexml' value='".$ttxml->fileid."'>";
			$maintext .= "<p>Example XML file: <a href='index.php?action=file&cid=".$ttxml->fileid."' target=xml>".$ttxml->fileid."</a>";			
		} else if ( $_POST['xml'] == "upload" && is_uploaded_file($_POST['upload']) ) {
			$examplexml = simplexml_load_file($_POST['upload']['name']);
		};

		if ( $examplexml && !$headerfile ) {
			// Use all the filled header elements 
			$fieldslist = makelist ($examplexml->teiHeader);
			foreach ( $fieldslist as $xpf ) {
				$name = ""; $tmp = $headerxml->xpath($xpf);
				if ( $tmp ) $name = $tmp[0]['name'];
				array_push( $headerrows, array("name" => $name, "xpath" => $xpf ) );
			};
		};		
		
		$maintext .= "<h2>Define fields</h2>
			<form action='index.php?action=$action&act=save' method=post>
			<input type=hidden name='tplfile' value='$filename'>
			$filesave
			<table><tr><th>Field name/Known field description<th>XPath definition/Current value</tr>";
		
		
		if ( $headerfile ) {
			preg_match_all ( "/<tr><th>(.*?)<\/th><td>{#(.*?)}<\/td><\/tr>/", $headerfile, $matches );
			for ( $i = 0; $i<count($matches[0]); $i++ ) {
				$name = preg_replace("/{%(.*?)}/", "\\1", $matches[1][$i]); $xpath = $matches[2][$i];
				array_push( $headerrows, array("name" => $name, "xpath" => $xpath ) );
			};
			for ( $i = 0; $i < 4; $i++ ) {
				array_push( $headerrows, array("name" => "", "xpath" => "" ) );
			};
		};

		foreach ( $headerrows as $row ) {
			
			$name = $row['name']; $xpath = $row['xpath'];
			
			if ( $headerxml ) {
				$tmp = $headerxml->xpath($xpath);
				$defname = $tmp[0].""; if ( $defname == "" && $name )  $defname = "<i>Unknown field</i>";
			};
			
			if ( $examplexml ) {
				$tmp = $examplexml->xpath($xpath);
				$curval = $tmp[0].""; if ( $curval == "" && $xpath )  $curval = "<i>--</i>";
			};
			
			$maintext .= "<tr>
					<td><input size=40 name='name[$i]' value='$name'><br>$defname
					<td><input size=100 name='xpath[$i]' value='$xpath'><br>$curval
					";
		};
		$maintext .= "</table>
				<input type=submit value=Save>
				</form>
		";
		
		$maintext .= "<h2>Current raw teiHeader File</h2><p><pre>".htmlentities($headerfile)."</pre>";
		
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
		
		<form action='index.php?action=$action' method=post>
		
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
				<input type=file name=upload>
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