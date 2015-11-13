<?php

	$cid = $_GET['cid'];
	$treeid = $_GET['treeid'];
	$sentenceid = $_GET['sentence'];

	$treestyle = $_GET['treestyle'] or $treestyle = $_POST['treestyle'] or $treestyle = $_COOKIE['treestyle']  or $treestyle = $_SESSION['treestyle'] or $treestyle = $settings['psdx']['treestyle'] or $treestyle = "horizontal";
	$_COOKIE['treestyle'] = $_SESSION['treestyle'] = $_GET['treestyle'];

	$treestyles = array ( 
		"table" => "Table",
		"horizontal" => "Table graph",
		"vertical" => "Vertical graph",
		"svg" => "SVG Tree",
	);
	
	$maintext .= "<h1>Syntactic Trees</h1>
		<style>
		#tree { margin-bottom: 20px; }
		#tree table { border: none; }
		#tree tbody { display:block; margin: -1px; }
		#tree th, #tree td {
    		border: 0.5px solid #aa6666;
		}		
		#tree td { 
			padding: 0px;
		}
		#tree .node { 
			padding: 5px;
		};
		</style>";
		
	if ( $act == "xquery" ) {
		// Allow queries over PSDX files using Saxon
		// This by now is depricated in principle
		// Check if saxon exists
		if ( !file_exists("../bin/saxon.jar") ) { fatal("Saxon not found - please contact administration"); };
		
		$xquery = $_POST['xquery'];
		
		if (!$xquery) { 
			$test = 1; 
			$xquery = 'for $tree in //eTree[@Label="IP-MAT"]'."\n".' where exists($tree//eLeaf[@Text="a"])'."\n".'return $tree';
		};
		$maintext .= "<h2>XQuery Search</h2>
			<p>Below you can search through the PSDX syntactic trees using XQuery.
			
			<form action='index.php?action=$action&act=$act' method=post>
			<textarea style='width: 100%; height: 200px;' name='xquery'>$xquery</textarea>
			<p><input type=submit value=Search>
			</form>";

		$xquery = preg_replace("/[\n\r]/", " ", $xquery );
		
		
		if ( $xquery && !$test ) {
			$cmd = "/bin/find Annotations/*.psdx -exec /usr/bin/java -cp ../bin/saxon.jar net.sf.saxon.Query -qs:'$xquery' -s:{} \;";
			$results = shell_exec($cmd);
			$maintext .= "<h2>Results</h2><p>";
			# $results = str_replace('result:', '', $results); // remove the namespace
			# $results = preg_replace('/xmlns="[^"]+"/', '', $results); // remove the namespace
			
			$results = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $results); // remove multiple <?xml
			$results = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><results>$results</results>";
			# print $results; exit;
			$resxml = simplexml_load_string($results, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if (strstr($results, '<results></results>')) {
				$maintext .= "<p>No results for found.</p>";
			} else if ( $resxml ) {
				foreach ( $resxml->xpath("/results/*") as $resnode ) {
					# $maintext .= "<hr>".htmlentities($resnode->asXML());
					$maintext .= "<hr>";
					$tmp = $resnode->xpath("//*[@Location]"); $sentid = $tmp[0]['sentid'] or $sentid = $tmp[0]['Location'];
					$tmp = $resnode->xpath("//*[@File]"); $fileid = $tmp[0]['File'];
					if ( $sentid && $fileid ) {
						$maintext .= "<p>File: <a href='index.php?action=$action&cid=$fileid'>$fileid</a>, 
							Sentence: <a href='index.php?action=$action&cid=$fileid&sentence=$sentid'>$sentid</a>";
					} else if ( $fileid ) {
						$maintext .= "<p>File: <a href='index.php?action=$action&cid=$fileid'>$fileid</a>";
					}; 
					if ( $treestyle == "horizontal" ) {
						$maintext .=  "<div id=tree>".$resnode->asXML()."</div>";
					} else {
						$maintext .=  "<div id=tree>".drawtree($resnode, true)."</div>";
					};
				};
				if ( $treestyle == "horizontal" ) {
					$maintext .= "
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/tokedit.js\"></script>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/tokview.js\"></script>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/psdx.js\"></script>
						<script language=Javascript>
						var username = '$username';
						var cid = '$cid';
						var treeid = '$treeid';
						maketext();
						</script>
						<link href='http://alfclul.clul.ul.pt/teitok/Scripts/psdx-hor.css' rel='stylesheet' type='text/css'/>
					";
				};
			} else { $maintext .= "<p><i>Error while getting the result</i><hr>".htmlentities($results); };
		};

		
	} else if ( $act == "download" && $cid ) {
		
		$type = $_GET['type'];
		$formats = array ( "psd" => "Penn Treebank PSD", "psdx" => "PSDX" ); 
		if ( $type == "psd" ) {
			$filename = "$cid.psd";
			header("Content-type: text/txt"); 
			header('Content-disposition: attachment; filename="'.$filename.'"');
			$file = file_get_contents("Annotations/$cid.psdx");
			$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			print "/* PSD Tree generated from PSDX by TEITOK */\n\n";
			foreach  ($forestxml->xpath('//forest') as $forest ) {
			
				$level = 1;
				print "( ";
				foreach  ( $forest->xpath("./eTree") as $node ) {
					psdtree($node);
				};
				if ( $settings['psdx']['psd']['Location'] == "org" ) {
					print "\n  (ID {$forest['File']},{$forest['Location']}))";
				} else {
					print "\n  (ID {$forest['File']},{$forest['sentid']}.{$forest['id']}))";
				};
				print "\n\n";
			
			};

			exit;
		} else if ( $type == "psdx" ) {
			
			$filename = "$cid.psdx";
			header("Content-type: text/txt"); 
			header('Content-disposition: attachment; filename="'.$filename.'"');
			
			print file_get_contents("Annotations/$cid.psdx"); 
			exit;
			
		} else {
			$maintext .= "<h2>{%Select download format}</h2>";
			foreach ( $formats as $key => $val ) {
				$maintext .= "<p><a href='index.php?action=$action&cid=$cid&act=$act&type=$key'>$val</a>";
			};
			$maintext .= "<hr><p><a href='index.php?action=$action&cid=$cid'>{%back to file}</a></p>";
		};
		
	} else if ( $act == "xpath" ) {
		// Allow queries over PSDX files using xpath wrapped in xsltproc
		
		$xpath = $_POST['xpath'] or $xpath = $_GET['xpath'];
		$searchfile = $_GET['cid'];
		if ( $searchfile ) {	
			$searchfiles = $searchfile; 
			$subtit = "<p>Query over file: <a href='index.php?action=file&cid=$searchfiles'>$searchfiles</a>";
		} else $searchfiles = "*";
		
		if ( $xpath == "" ) { 
			$test = 1; 
			$xpath = '//eTree[@Label="IP-SUB" and .//eTree[@Label="ADV"]]';
		};
		$maintext .= "<h2>XPath Search</h2>
			<p>{%Below you can search through the PSDX syntactic trees using XPath.} (<a href='index.php?action=xpath'>{%Help}</a>)
			
			<form action='index.php?action=$action&act=$act&cid=$searchfile' method=post>
			<textarea style='width: 100%; height: 50px;' name='xpath'>$xpath</textarea>
			$subtit
			<p><input type=submit value=Search> {%Tree style}: 
			<select name=treestyle>";
			
		foreach ( $treestyles as $key => $val ) {
			if ( $key == $treestyle ) $slc = " selected"; else $slc = "";
			$maintext .= "<option value=\"$key\"$slc>$val</option>";
		}
		$maintext .= "</select>
			</form>";

		$xquery = preg_replace("/[\n\r]/", " ", $xquery );
		
		
		if ( $xpath && !$test ) {
			$wrapper = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?> <xsl:stylesheet version=\"1.0\" xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\"> <xsl:output method=\"xml\"/> <xsl:template match=\"/\"> <xsl:for-each select='\"'\"'##'\"'\"'> <forest> <xsl:attribute name=\"File\"> <xsl:value-of select=\"./ancestor::forest/@File\"/> </xsl:attribute> <xsl:attribute name=\"Location\"> <xsl:value-of select=\"./ancestor::forest/@Location\"/> </xsl:attribute>  <xsl:attribute name=\"id\"> <xsl:value-of select=\"./ancestor::forest/@id\"/> </xsl:attribute> <xsl:copy-of select=\".\"/> </forest> </xsl:for-each> </xsl:template> </xsl:stylesheet>";
			$xslt = preg_replace("/##/", $xpath, $wrapper);
			$cmd = "echo '$xslt' | xsltproc --novalid - Annotations/$searchfiles.psdx";
			# print "<p>CMD: ".htmlentities($cmd);
			$results = shell_exec($cmd);
			$results = str_replace('<?xml version="1.0"?>', '', $results); // remove multiple <?xml
			$results = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><results>$results</results>";
			# print "<p>Results: ".htmlentities($results); exit;

			$maintext .= "<h2>Results</h2><p>";

			$resxml = simplexml_load_string($results, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if (strstr($results, '<results></results>')) {
				$maintext .= "<p>No results for found.</p>";
			} else if ( $resxml ) {
				$forestlist = $resxml->xpath("//forest");
				$maintext .= "<p>".$forestlist.size()." matching (sub)trees";
				foreach ( $forestlist as $forest ) {
					$maintext .= "<hr>";
					$sentid = $forest['sentid'] or $sentid = $forest['Location'];
					$fileid = $forest['File'];
					if ( $sentid && $fileid ) {
						$nodeid = $forest->eTree[0]['id'];
						$maintext .= "<p>File: <a href='index.php?action=file&cid=$fileid'>$fileid</a>, 
							Sentence: <a href='index.php?action=$action&cid=$fileid&sentence=$sentid&node={$nodeid}'>$sentid</a></p>";
					} else if ( $fileid ) {
						$maintext .= "<p>File: <a href='index.php?action=$action&cid=$fileid'>$fileid</a></p>";
					}; 
					if ( $treestyle == "table" ) {
						$maintext .=  "<div id=tree>".drawtree($forest, true)."</div>";
					} else if ( $treestyle == "svg" ) {
						$maintext .= "\n".makesvgtree($forest, true);
					} else if ( $treestyle == "vertical" ) {	
						$maintext .= "<link href='http://alfclul.clul.ul.pt/teitok/Scripts/treeul.css' rel='stylesheet' type='text/css'/><div class=tree>".drawtree2($forest)."</div>";
					} else {
						// Use table graph by default
						$treestyle = "horizontal";
						$maintext .=  "<div id=tree>".$forest->asXML()."</div>";
					};
				};
				if ( $treestyle == "horizontal" ) {
					$maintext .= "
						<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 3;'></div>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/tokedit.js\"></script>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/tokview.js\"></script>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/psdx.js\"></script>
						<script language=Javascript>
						var username = '$username';
						var cid = '$cid';
						var treeid = '{$forest['id']}';
						maketext();
						</script>
						<link href='http://alfclul.clul.ul.pt/teitok/Scripts/psdx-hor.css' rel='stylesheet' type='text/css'/>
					";
				};
				$maintext .= "<hr>";
			} else { $maintext .= "<p><i>Error while getting the result</i><hr>".htmlentities($results); };
		};

	} else if ( $act == "nodesave" ) {
		check_login();
		$cid = $_POST['cid'];

		$file = file_get_contents("Annotations/$cid.psdx");
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$forestxml ) { fatal ("File not found or not parsable: $cid"); };
		
		$nid = $_POST['nid'];
		$treeid = $_POST['treeid'];

		$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
		$forest = $result[0]; 
		if ( !$forest ) { fatal ("Forest not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
	
		$result = $forest->xpath(".//*[@id=\"$nid\"]"); 
		$node = $result[0]; 
		if ( !$node ) { fatal ("Node not found: $nid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };

		foreach ( $_POST['vals'] as $key => $val ) {
			$node[$key] = $val;
		};
		
		renumber($forestxml);		
		
		file_put_contents("Annotations/$cid.psdx", $forestxml->asXML());
		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';</script>"; exit;

	} else if ( $act == "treesave" ) {

		check_login();
		$cid = $_POST['cid'];
		$treeid = $_POST['treeid'];

		if ($_POST['newxml']) {
			$file = file_get_contents("Annotations/$cid.psdx");
			$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$forestxml ) { print "Forest not found: $cid<hr>"; print $file; exit; };

			$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
			$forest = $result[0]; 
			if ( !$forest ) { fatal ("Forest not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
			
			$forest[0] = "##INSERT##";
			
			# Renumber the tree
			$newtree = simplexml_load_string($_POST['newxml'], NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			renumber($newtree);
			$newxml = $newtree->asXML();
			$newxml = preg_replace('/<\?xml[^>]*\?>/', '', $newxml); // remove redundant <?xml
			
			$source = $forestxml->asXML();
			$newfile = preg_replace("/<[^>]+>##INSERT##<\/[^>]+>/", $newxml, $source);
			
			print "top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';";
			file_put_contents("Annotations/$cid.psdx", $newfile);
			print "Changes have been saved
				<script language=Javascript>top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';</script>"; exit;
		};

	} else if ( $act == "treeedit" && $treeid && $cid ) {
		check_login();
		if ( !is_writable("Annotations/$cid.psdx")  ) {
			fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
		};
		
		$file = file_get_contents("Annotations/$cid.psdx");
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$forestxml ) { fatal ("Failed to load PSDX file: Annotations/$cid.psdx"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };

		$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
		$forest = $result[0]; 
		if ( !$forest ) { fatal ("Forest not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };

					$maintext .= "
						<h2>Edit Tree</h2>
						<p>Select an eTree in the tree, and select the buttons to move it around.</p><hr>
						\n<div id=tree></div>
						<hr>
						<div style='display: block' id=editbuttons>
							<p>Actions on the selected node:
							<p>
								<button id='moveup' disabled onClick='moveup()'>Move out of parent</button>
								<button id='moveleft' disabled onClick='moveleft()'>Move to previous tree</button>
								<button id='moveright' disabled onClick='moveright()'>Move to next tree</button>
								<button id='movedown' disabled onClick='movedown()'>Insert parent node</button>
								<button id='insert' disabled onClick='insertempty()'>Insert empty child node</button>
							<p id='changetag' style='display: none;'>Change nodename: <input id=tagtxt> <button id='tagchange' onClick='tagchange()'>Change</button></p>
							<p>
								<button id='source' onClick='togglesource()'>Show XML</button>
								<button id='update' style='display: none;' onClick='updatefromraw()'>Update</button>
								<button id='undo' disabled onClick='undo()'>Undo</button>
								<button id='save' disabled onClick='savetree()'>Save</button>
						</div>
						<form style='display: none;' action='index.php?action=$action&act=treesave' id=submitxml name=submitxml method=post>
							<h2>Raw XML Edit</h2>
							<p>Below you can edit the raw XML when needed. Click the <i>Update</i> button to apply the changes.
							<textarea name='newxml' id='newxml' style='width: 100%; height: 200px;'>".$forest->asXML()."</textarea>
							<input type=hidden name='cid' value='$cid'>
							<input type=hidden name='treeid' value='$treeid'>
						</form>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/psdx.js\"></script>
						<script language=Javascript>
						var username = '';
						var cid = '$cid';
						var treeid = '$treeid';
						var treetxt = document.submitxml.newxml.value;
						document.getElementById('tree').innerHTML = treetxt;
						parser = new DOMParser();
						treexml = parser.parseFromString(treetxt,'text/xml');
						maketext();
						</script>
						<link href='http://alfclul.clul.ul.pt/teitok/Scripts/psdx-hor.css' rel='stylesheet' type='text/css'/>
						
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/psdxedit.js\"></script>
					";

	} else if ( $act == "nodeedit" && $_GET['nid'] && $treeid ) {
		check_login();
		if ( !is_writable("Annotations/$cid.psdx")  ) {
			fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
		};
		
		$file = file_get_contents("Annotations/$cid.psdx");
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$forestxml ) { fatal ("Failed to load PSDX file: Annotations/$cid.psdx"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
		$nid = $_GET['nid'];
		
		$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
		$forest = $result[0]; 
		if ( !$forest ) { fatal ("Forest not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
	
		$result = $forest->xpath(".//*[@id=\"$nid\"]"); 
		$node = $result[0]; 
		if ( !$node ) { fatal ("Node not found: $nid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
		
		# $maintext .= htmlentities($node->asXML());
		
		$nodetype = $node->getName();
		if ( $nodetype == "eLeaf" ) {
			$editfields = $settings['psdx']['eLeaf'] 
				or $editfields = array ( "tokid" => array ("display" => "Token ID"), "Text" => array ("display" => "Text"), "Notext" => array ("display" => "Non-word content") );			
		} else if ( $nodetype == "eTree" ) {
			$editfields = $settings['psdx']['eLeaf'] 
				or $editfields = array ( "Label" => array ("display" => "Label") );
		};
		$maintext .= "<h2>Edit $nodetype: {$forest['id']}/{$node['id']}</h2>";

		$maintext .= "
		<form action='index.php?action=$action&act=nodesave' method=post>
			<input type=hidden name=cid value='$cid'>
			<input type=hidden name=nid value='$nid'>
			<input type=hidden name=treeid value='$treeid'>
			<table>";
		foreach ( $editfields as $key => $val ) {
			$maintext .= "<tr><th>{$val['display']}<td><input name='vals[$key]' value='{$node[$key]}'>";
		};
		$maintext .= "</table>
			<p><input type=submit value=Save>
			</form>";
					
	} else if ( $cid && file_exists("Annotations/$cid.psdx") ) {

		$file = file_get_contents("Annotations/$cid.psdx");
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		# Check if there are <forest> <eTree> or <eLeaf> without @is - if so, renumber and reload
		if ( $forestxml->xpath("//forest[not(@id)] | //eTree[not(@id)] | //eLeaf[not(@id)]") ) {
			if ( !is_writable("Annotations/$cid.psdx")  ) {
				if ( $username ) fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
			} else {
				renumber($forestxml);
				file_put_contents("Annotations/$cid.psdx", $forestxml->asXML());
				print "<p>File not properly numbered - renumbering and reloading
				<script language=Javascript>location.reload(true);</script>";
				exit;
			};
		};

		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($cid, false);
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 
		$maintext .= $ttxml->viewheader(); 


		$nodeid = $_GET['node'];
		if ( $treeid || $sentenceid ) {
			if ( $treeid ) $result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
			else if ( $sentenceid ) $result = $forestxml->xpath("//forest[@Location=\"$sentenceid\"]|//forest[@sentid=\"$sentenceid\"]"); 
			$forest = $result[0];
			$sentid = $forest['sentid'] or $sentid = $forest['Location']; $treeid = $forest['id'];
			if ( $ttxml->xml ) $tmp = $ttxml->xml->xpath("//s[@id=\"$sentid\"]"); $sentxml = $tmp[0];
			if ( $sentxml ) {
				$sentence = $sentxml->asXML();
				foreach ( $settings['xmlfile']['sattributes'] as $item ) {
					$key = $item['key'];
					$atv = preg_replace("/\/\//", "<lb/>", $sentxml[$key]);	
					if ($item['color']) { $scol = "style='color: {$item['color']}'"; } else { $scol = "class='s-$key'"; };
					if ( $atv && ( !$item['admin'] || $username ) ) {
						if ( $item['admin'] ) $scol .= " class='adminpart'";
						$sentence .= "<div $scol title='{$item['display']}'>$atv</div>"; 
					}
				};
			};
			$maintext .= "<h2>{%Tree} $treeid = {%Sentence} $sentid</h2>
				<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/tokedit.js\"></script>
				<script language=Javascript>var tid = '$cid';</script>
				<div id='mtxt' style='margin-bottom: 20px;'>$sentence</div>
				<hr><p><i>{%Move your mouse over the leaves in the tree to get info from the corresponding word in the sentence.}</i></p>";
			if ( $username ) $maintext .= "<p class=adminpart><i>Click on a node below or a word above to edit its content - use <a href='index.php?action=$action&act=treeedit&cid=$cid&treeid=$treeid'>tree edit</a> to edit the layout</i></p>";
			if ($forest) {
				if ( $treestyle == "vertical" ) {	
					$maintext .= "<link href='http://alfclul.clul.ul.pt/teitok/Scripts/treeul.css' rel='stylesheet' type='text/css'/><div class=tree style='width: 2000px;'>".drawtree2($forest)."</div>";
				} else if ( $treestyle == "horizontal"  ) {	
					$maintext .= "\n<div id=tree>".$forest->asXML()."</div>
						<script language=Javascript src=\"http://alfclul.clul.ul.pt/teitok/Scripts/psdx.js\"></script>
						<script language=Javascript>
						var username = '$username';
						var cid = '$cid';
						var treeid = '$treeid';
						maketext();
						</script>
						<link href='http://alfclul.clul.ul.pt/teitok/Scripts/psdx-hor.css' rel='stylesheet' type='text/css'/>
					";
				} else if ( $treestyle == "svg"  ) {	
					$maintext .= "\n".makesvgtree($forest);
				} else if ( $treestyle == "table" ) {
					$maintext .= "\n<div id=tree>".drawtree($forest)."</div>";
				};
				$options .= " &bull; tree style: ";
				$sep = "";
				foreach ( $treestyles as $key => $val ) {
					$val = strtolower($val);
					if ( $key == $treestyle ) 
						$options .= $sep.$val;
					else
						$options .= "$sep <a href='{$_SERVER['REQUEST_URI']}&treestyle=$key'>{%$val}</a>";
					$sep = " - ";
				}
			} else $maintext .= "<i>Forest not found: $treeid</i>";
			if ($nodeid) {
				if ( $treestyle == "table" || $treestyle == "horizontal" ) {
					$maintext .= "\n<script language=Javascript>document.getElementById('$nodeid').parentNode.style.backgroundColor = '#ffffcc';</script>";
				} else if ( $treestyle == "svg" ) {
					$maintext .= "\n<script language=Javascript>document.getElementById('$nodeid').setAttribute('fill' 'yellow');</script>";
				} else {
					$maintext .= "\n<script language=Javascript>document.getElementById('$nodeid').style.backgroundColor = '#ffffcc';</script>";
				}
			};
			if ( $warnings ) $maintext .= "<hr><div style='color: #992000; font-weight: bold;'>$warnings</div>";
 			$result = $forestxml->xpath("//forest[@id=\"$treeid\"]/preceding-sibling::forest"); 
 			$prevforest = array_pop($result); 
 			if ( $prevforest['id'] ) { $options .= " &bull; <a href='index.php?action=$action&cid=$cid&treeid={$prevforest['id']}&treestyle=$treestyle'>{%previous sentence}</a>"; };
			$result = $forestxml->xpath("//forest[@id=\"$treeid\"]/following-sibling::forest"); 
			$nextforest = $result[0]; 
			if ( $nextforest['id'] ) { $options .= " &bull; <a href='index.php?action=$action&cid=$cid&treeid={$nextforest['id']}&treestyle=$treestyle'>{%next sentence}</a>"; };
			$maintext .= "<hr><a href='index.php?action=$action&cid=$cid'>{%sentence list}</a> &bull; <a href='index.php?action=edit&cid=$cid&jmp=$sentid'>{%to text mode}</a> $options</p><br>";
		} else {
			$result = $forestxml->xpath("//forest"); 
 			foreach ( $result as $tmp ) { 
 				$sentid = $tmp['sentid'] or $sentid = $tmp['Location'];
 				$forestid = $tmp['id'];
 				$maintext .= "<p><a href='index.php?action=$action&cid=$cid&treeid=$forestid'>Sentence {$sentid}</a>";
 			};
			$maintext .= "<hr><a href='index.php?action=$action'>More files</a> &bull; <a href='index.php?action=$action&act=xpath&cid=$cid'>{%Search in this file}</a>";
			if ( !$settings['psdx']['nodownload'] ) $maintext .= " &bull; <a href='index.php?action=$action&act=download&cid=$cid'>{%Download file}</a>";
		};

	} else {
		
		$maintext .= "<p>{%Select a file from the list below or} <a href='index.php?action=$action&act=xpath'>{%Search}</a><hr>
			<table>";
	
		# Show all available PSDX files
		foreach (glob("Annotations/*.psdx") as $filename) {
			$some = 1; $anid = preg_replace( "/.*\/(.*?)\.psdx/", "\\1", $filename );
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$anid'>".ucfirst($anid)."</a><td>";
		};
		$maintext .= "</table>";
		if ( !$some ) $maintext .= "<p><i>{%No PSDX files found}</i>";
	
	};

	function drawtree ( $tree, $root = false ) {
		// Table tree style
		global $warnings; global $ttxml; global $username;
		
		if ( $root ) {
			$result = array ( $tree );
		} else {
			$result = $tree->xpath("eTree");
		}
		if ( $result ) {
			$text .= "<table border=0 style='border-collapse: collapse;'>";		
			foreach ( $result as $leaf ) {
				$text .= "<tr><td class='node'>{$leaf['Label']}<td style='padding: -1px;' id='{$leaf['id']}'>".drawtree($leaf);
			};
			$text .= "</table>";		
		} else {
			$result = $tree->xpath("eLeaf");
			if ( $result ) {
				foreach ( $result as $leaf ) {	
					$tid = $leaf['tokid'];
					$leaftext = $leaf['Text'] or $text = "&empty;";
					$text = "<span onMouseOver=\"highlight('$tid', '#ffff00'); showtokinfo(this, document.getElementById('$tid'), this);\" onMouseOut=\"unhighlight(); hidetokinfo();\" class='node'>$leaftext</span>";
					if ( $username && $tid != "" && $ttxml ) {
						# Check whether tree is properly aligned
						$tmp = $ttxml->xml->xpath("//tok[@id=\"$tid\"] | //dtok[@id=\"$tid\"]"); $token = $tmp[0]; #  
						if ( $leaf['Text'] != $token."" && 
							 $leaf['Text'] != $token['form']."" && 
							 $leaf['Text'] != $token['fform']."" && 
							 $leaf['Text'] != $token['nform']."" )
						{ $warnings .= "<p>Token $tid: text mismatch, {$token['nform']}/$token &ne; {$leaf['Text']}"; };
					};
				};
			} else {
				foreach ( $tree->children() as $child ) {	
					$text .= drawtree($child);
				};
			};
		};
		return $text;
	};

	function drawtree2 ( $tree, $root = false ) {
		// Vertical Graphic style, <ul> stylized by CSS
		global $warnings; global $ttxml; global $username;
		
		$result = $tree->xpath("eTree");
		if ( $result ) {
			$text .= "\n\t<ul>";		
			foreach ( $result as $leaf ) {
				$text .= "<li><a>{$leaf['Label']}</a>".drawtree2($leaf)."</li>"; #  id='{$leaf['id']}'
			};
			$text .= "</ul>";		
		} else {
			$result = $tree->xpath("eLeaf");
			if ( $result ) {
				$text .= "<ul>";
				foreach ( $result as $leaf ) {	
					$tid = $leaf['tokid'];
					$leaftext = $leaf['Text'] or $leaftext = "&empty;";
					$leafcontent = "<qq onMouseOver=\"highlight('$tid', '#ffff00'); showtokinfo(this, document.getElementById('$tid'), this);\" onMouseOut=\"unhighlight(); hidetokinfo();\">$leaftext</qq>";
					$text .= "<ul><li><span>$leafcontent</span></li></ul>";
				};
				$text .= "</ul>";
			} else {
				foreach ( $tree->children() as $child ) {	
					$text .= drawtree2($child);
				};
			};
		};
		return $text;
	};

	function drawtree3 ( $node, $root = false ) {
		// SVG Treestyle
		global $level;
		if ( !$root ) {
			$level++; 
			$treetxt .= "\n\t<svg:text id=\"{$node['id']}\" row=\"$level\"$from>{$node['Label']}</svg:text>";
		};
		foreach  ( $node->xpath("./eTree") as $child ) {
			$treetxt .= drawtree3($child);
			if ( $node['id'] && $child['id'] && !$root ) if ($node['id']) $treetxt .= "\n\t\t<svg:line from=\"{$node['id']}\" to=\"{$child['id']}\"/>";
		};
		foreach  ( $node->xpath("./eLeaf") as $leaf ) {
			$tid = $leaf['tokid'];
			$leaftext = $leaf['Text'] or $leaftext = "&empty;";
			$leafoptions = "onMouseOver=\"highlight('$tid', '#ffff00'); showtokinfo(this, document.getElementById('$tid'), this);\" onMouseOut=\"unhighlight(); hidetokinfo();\" class='node'";
			$level2 = $level+1;
			$treetxt .= "\n\t<svg:text type=\"leaf\" id=\"{$leaf['id']}\" row=\"$level2\" $leafoptions>{$leaftext}</svg:text>"; # font-weight=\"bold\" 
			if ( $node['id'] && $leaf['id'] ) $treetxt .= "\n\t<svg:line from=\"{$node['id']}\" to=\"{$leaf['id']}\"/>";
		};
		$level--;
		
		return $treetxt;
	};
	
	function makesvgtree ( $forest, $root = true ) {
		$rowheight = 60; global $level; $level = 0;
		$svgtxt .= "<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"100%\" height=\"500\">".drawtree3($forest, $root)."</svg>";
		$svgxml = simplexml_load_string($svgtxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING); // Put the leaves and lines on the canvas
		if ( !$svgxml ) return "<p><i>Error while drawing tree</i>";
		// Now position the leaves and lines
		foreach ( $svgxml->xpath("//text[@type=\"leaf\"]") as $textnode ) $maxrow = max($maxrow, $textnode['row']+0); 
		$result = $svgxml->xpath("//text[@type=\"leaf\"]"); $maxcol = count($result);
		foreach ( $result as $textnode ) { 
			$row = $maxrow;
			$colcnt[$row]++; $col = $colcnt[$row];
			$textnode['y'] = ( ($row -1) * $rowheight )  + 20;
			$textnode['x'] = ($col * 100);				
			$textnode['alignment-baseline'] = "middle";
			$textnode['text-anchor'] = "middle";
		}; $dorow = $maxrow - 1;	
		while ( $dorow > 0 ) {
			$result = $svgxml->xpath("//text[not(@type=\"leaf\") and @row=\"$dorow\"]"); 
			foreach ( $result as $textnode ) { 
				$row = $textnode['row'].'';
				$textnode['y'] = ( ($row -1) * $rowheight )  + 20;
				
				$totchildx = 0; $listchild = '';
				$tmp = $svgxml->xpath("//line[@from=\"{$textnode['id']}\"]"); $numchildx = count($tmp);
				foreach ( $tmp as $line ) { 
					$tmp = $svgxml->xpath("//text[@id=\"{$line['to']}\"]"); $child = $tmp[0];
					$listchild .= $child['id'].';'; $totchildx += $child['x'];
				};
				
				$textnode['x'] = ($totchildx/$numchildx);				
				$textnode['alignment-baseline'] = "middle";
				$textnode['text-anchor'] = "middle";
			}; 
			$dorow--;
		};
		$result = $svgxml->xpath("//line"); 
		foreach ( $result as $line ) { 
			$tmp1 = $line['from']; $tmp = $svgxml->xpath("//text[@id=\"$tmp1\"]"); $from = $tmp[0];
			$line['x1'] = $from['x'];
			$line['y1'] = $from['y'] + 10;						
			$tmp1 = $line['to']; $tmp = $svgxml->xpath("//text[@id=\"$tmp1\"]"); $to = $tmp[0];
			$line['x2'] = $to['x'];
			$line['y2'] = $to['y'] - 15;						
			$line['style'] = "stroke:rgb(60,60,60);stroke-width:0.5";
		}; 
		$svgxml[0]['height'] = ( ($maxrow -1) * $rowheight )  + 30; $svgxml[0]['width'] = ($maxcol*100) + 100;
		return $svgxml->asXML();
	};
	
	function psdtree ( $node ) {
		global $level;
		$level++; $first = 1;
		print "({$node['Label']} ";
		foreach  ( $node->xpath("./eTree") as $child ) {
			if ( $first ) {
				$first = 0;
			} else {
				print "\n";
				for ( $i=0; $i<$level; $i++ ) { print "   "; };
			};
			psdtree($child);
		};
		foreach  ( $node->xpath("./eLeaf") as $leaf ) {
			print " ".$leaf['Text'].$leaf['Notext'];
		};
		print ") ";
		$level--;
	};

	function renumber ( $forestxml ) {
		# Renumber the tree
		$num = 1;
		$result = $forestxml->xpath("//eTree | //eLeaf");
		foreach ( $result as $node ) { 
			$node['id'] = 'node-'.$num; $num++;
		}; 
		# Renumber the forests
		$num = 1;
		$result = $forestxml->xpath("//forest");
		foreach ( $result as $node ) { 
			$node['id'] = 'tree-'.$num; $num++;
		}; 
	};	

?>