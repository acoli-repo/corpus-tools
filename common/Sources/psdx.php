<?php

	$cid = $_GET['cid'];
	$cid = preg_replace("/.*\//", "", $cid);
	$cid = preg_replace("/\.xml/", "", $cid);
	$treeid = $_GET['treeid'];
	$sentenceid = $_GET['sentence'];
	$xpath = $_POST['xpath'] or $xpath = $_GET['xpath'];
	$xpath = stripslashes($xpath);
	$psdxfile = "Annotations/$cid.psdx";
	if ( !file_exists($psdxfile) ) {
		$test = array_merge(glob("Annotations/$cid.psdx"), glob("Annotations/*/$cid.psdx"), glob("Annotations/*/*/$cid.psdx"), glob("Annotations/*/*/*/$cid.psdx")); 
		$psdxfile = array_pop($test); 
	};

	if ( !is_array($_POST['atts']) ) $_POST['atts'] = $_GET['atts'];

	if  ( !$corpusfolder ) $corpusfolder = "cqp";
	
	$treestyle = $_GET['treestyle'] or $treestyle = $_POST['treestyle'] or $treestyle = $_COOKIE['treestyle']  or $treestyle = $_SESSION['treestyle'] or $treestyle = $settings['psdx']['treestyle'] or $treestyle = "horizontal";
	$_COOKIE['treestyle'] = $_SESSION['treestyle'] = $_GET['treestyle'];

	$treestyles = array ( 
		"text" => "Text",
		"xmltext" => "Sentence",
		"bracketstring" => "Brackets",
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
		
	if ( $act == "download" && $cid ) {
		
		$type = $_GET['type'];
		$formats = array ( "psd" => "Penn Treebank PSD", "psdx" => "PSDX" ); 
		if ( $type == "psd" ) {
			$filename = "$cid.psd";
			header("Content-type: text/txt"); 
			header('Content-disposition: attachment; filename="'.$filename.'"');
			$file = file_get_contents($psdxfile);
			$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			print "// PSD Tree generated from PSDX by TEITOK\n\n";
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
			
			print file_get_contents($psdxfile); 
			exit;
			
		} else {
			$maintext .= "<h2>{%Select download format}</h2>";
			foreach ( $formats as $key => $val ) {
				$maintext .= "<p><a href='index.php?action=$action&cid=$cid&act=$act&type=$key'>$val</a>";
			};
			$maintext .= "<hr><p><a href='index.php?action=$action&cid=$cid'>{%back}</a></p>";
		};
		
	} else if ( $act == "xpath" || $act == "query" ) {
	
		// Allow queries over PSDX files using xpath wrapped in xsltproc
		
		$searchfile = $_GET['cid'];
		if ( $searchfile ) {	
			$searchfiles = "Annotations/$searchfile.psdx"; 
			$subtit = "<p>Query over file: <a href='index.php?action=file&cid=$searchfiles'>$searchfiles</a>";
		} else $searchfiles = "Annotations/*.psdx";
		
		if ( $xpath == "" ) { 
			$test = 1; 
			$xpath = $_GET['query'] or $xpath = $settings['psdx']['default'] or $xpath = '//eTree[@Label="IP-SUB" and .//eTree[@Label="ADV"]]';
		};
		
		$maintext .= "<div style='position: absolute; top: 70px; right: 20px'><a href='index.php?action=xpathhelp'>{%help}</a></div>";
		
		$maintext .= "
			<table style='width: 100%'>
			<tr><td valign=top style='padding-right: 10px;'>
			<h3>XPath Search</h3>
			<p style='visibility: hidden; margin-top: -20px;'>{%Below you can search through the PSDX syntactic trees using XPath.} (<a href='index.php?action=xpath'>{%Help}</a>)
			
			<form action='index.php?action=$action&act=$act&cid=$searchfile' method=post id=xpf name=xpf>
			<textarea style='width: 100%; height: 50px;' name='xpath' id=xpathfield>$xpath</textarea>
			<p> {%Tree style}: 
			<select name=treestyle>";
			
		foreach ( $treestyles as $key => $val ) {
			if ( $key == $treestyle ) $slc = " selected"; else $slc = "";
			$maintext .= "<option value=\"$key\"$slc>$val</option>";
		}
		$maintext .= "</select>
			<p><input type=submit value=Search>";

		if ( $xpath && !$test ) {
			require_once ("$ttroot/common/Sources/cwcqp.php"); 
			$maintext .= "</form>";
			$sep = "";
			## Check if there is a CQP query to run first
			if ( is_array($_POST['atts']) && join("", array_values($_POST['atts'])) != "" ) {
				foreach ( $_POST['atts'] as $tmp => $val ) {
					if ( !$val ) continue;
					list ( $key, $type ) = explode ( ":", $tmp );
					if ( strstr($key, '_' ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
					list ( $keytype, $keyname ) = explode ( "_", $xkey );
					$attitem = $settings['cqp']['sattributes'][$keytype][$keyname]; 
						$attname = $attitem['display']; $atttype = $attitem['type'];
					if ( $type == "start" ) {
						$cql .= " $sep int(match.$xkey) >= $val"; $sep = "&";
						if (!$_POST['atts']["$key:end"]) {
							$subtit .= "<p>$attname > $val";
							$docsel .= "$attname > $val; ";
						};
					} else if ( $type == "end" ) {
						$cql .= " $sep int(match.$xkey) <= $val"; $sep = "&";
						if ( $start = $_POST['atts']["$key:start"] ) {
							$subtit .= "<p>$attname: $start - $val";
							$docsel .= "$attname: $start - $val; ";
						} else {
							$subtit .= "<p>$attname < $val";
							$docsel .= "$attname < $val; ";
						};
					} else if ( $atttype == "long" ) {
						$cql .= " $sep match.$xkey = \".*$val.*\" %cd"; $sep = "&";
						$subtit .= "<p>$attname {%contains} <i>$val</i>";
						$docsel .= "$attname contains $val; ";
					} else {
						$val = quotemeta($val);
						$cql .= " $sep match.$xkey = \"$val\""; $sep = "&";
						$subtit .= "<p>$attname = <i>$val</i>";
						$docsel .= "$attname = $val; ";
					};
				
					$xmllist = array();
					$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
					$cqp = new CQP();
					$cqp->exec($cqpcorpus); // Select the corpus
					$cqp->exec("Matches = <text> [] :: ".$cql);
					# print $cql;
					$cwbresults = $cqp->exec("tabulate Matches 0 5000 match text_id");
					if ( $cwbresults ) {
						$wrapper = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?> <xsl:stylesheet version=\"1.0\" xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\"> <xsl:output method=\"xml\"/> <xsl:template match=\"/\"> <xsl:for-each select='\"'\"'##'\"'\"'> <forest> <xsl:attribute name=\"File\"> <xsl:value-of select=\"./ancestor::forest/@File\"/> </xsl:attribute> <xsl:attribute name=\"Location\"> <xsl:value-of select=\"./ancestor::forest/@Location\"/> </xsl:attribute> <xsl:attribute name=\"sentid\"> <xsl:value-of select=\"./ancestor::forest/@sentid\"/> </xsl:attribute>  <xsl:attribute name=\"id\"> <xsl:value-of select=\"./ancestor::forest/@id\"/> </xsl:attribute> <xsl:copy-of select=\".\"/> </forest> </xsl:for-each> </xsl:template> </xsl:stylesheet>";
						$searchfiles = ""; $xsep = "";
						$results = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><results>";
						// This is not efficient
						$resultarray = explode ( "\n", $cwbresults );
						$xslt = preg_replace("/##/", $xpath, $wrapper);
						$fsize = 100; // size of the list of files to pass in 1 go to XSLTPROC
						for ( $i=0; $i< count($resultarray); $i+=$fsize ) { 
							$line = join(" ", array_slice($resultarray, $i, $fsize));
							$searchfiles = preg_replace("/( |^)[^ ]*?([^\/\. ]+)\.xml/", "\\1Annotations/\\2.psdx", $line);
							$cmd = "echo '$xslt' | xsltproc --novalid - $searchfiles";
							# print "<p>CMD: ".htmlentities($cmd);
							$tmp = shell_exec($cmd);
							$results .= str_replace('<?xml version="1.0"?>', '', $tmp); // remove multiple <?xml

						};
						$results .= "</results>";
						$subtit .= "<hr>";
					};
				};
			} else {
				$wrapper = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?> <xsl:stylesheet version=\"1.0\" xmlns:xsl=\"http://www.w3.org/1999/XSL/Transform\"> <xsl:output method=\"xml\"/> <xsl:template match=\"/\"> <xsl:for-each select='\"'\"'##'\"'\"'> <forest> <xsl:attribute name=\"File\"> <xsl:value-of select=\"./ancestor::forest/@File\"/> </xsl:attribute> <xsl:attribute name=\"Location\"> <xsl:value-of select=\"./ancestor::forest/@Location\"/> </xsl:attribute> <xsl:attribute name=\"sentid\"> <xsl:value-of select=\"./ancestor::forest/@sentid\"/> </xsl:attribute> <xsl:attribute name=\"id\"> <xsl:value-of select=\"./ancestor::forest/@id\"/> </xsl:attribute> <xsl:copy-of select=\".\"/> </forest> </xsl:for-each> </xsl:template> </xsl:stylesheet>";
				$xslt = preg_replace("/##/", $xpath, $wrapper);
				$cmd = "echo '$xslt' | xsltproc --novalid - $searchfiles";
				// print "<p>CMD: ".htmlentities($cmd);
				$results = shell_exec($cmd);
				$results = str_replace('<?xml version="1.0"?>', '', $results); // remove multiple <?xml
				$results = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><results>$results</results>";
			};

			if ( $_GET['dltype'] == "psd" ) {
				$filename = "results.psd";
				header("Content-type: text/txt"); 
				header('Content-disposition: attachment; filename="'.$filename.'"');
				$resxml = simplexml_load_string($results, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
				print "// PSD Tree generated from PSDX XPath search by TEITOK\n";
				print "// XPath query: $xpath\n\n";
				if ( $docsel ) print "/* Document selection: $docsel */\n\n";
				foreach  ($resxml->xpath('//forest') as $forest ) {
			
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
			} else {
				// Display the results
				$maintext .= "<h2>Results</h2>$subtit<p>";

				$resxml = simplexml_load_string($results, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
				if (strstr($results, '<results></results>')) {
					$maintext .= "<p>No results for found.</p>";
				} else if ( $resxml ) {
					$forestlist = $resxml->xpath("//forest");
					$maintext .= "<p>".count($forestlist)." matching (sub)trees";
					foreach ( $forestlist as $forest ) {
						$sentid = $forest['sentid'] or $sentid = $forest['Location'];
						$fileid = $forest['File'];
						$nodeid = $forest->eTree[0]['id'];
						if ( $treestyle != "xmltext" ) {
							$maintext .= "<hr>";
							if ( $sentid && $fileid ) {
								$maintext .= "<p>{%Text}: <a href='index.php?action=file&cid=$fileid'>$fileid</a>, 
									{%!sentence}: <a href='index.php?action=$action&cid=$fileid&sentence=$sentid&node={$nodeid}' target=sent>$sentid</a></p>";
							} else if ( $fileid ) {
								$maintext .= "<p>File: <a href='index.php?action=$action&cid=$fileid'>$fileid</a></p>";
							};
						};
						if ( $treestyle == "text" ) {
							$editxml = totext($forest);
							$maintext .=  "<div id=mtxt>".$editxml."</div>";
						} else if ( $treestyle == "xmltext" ) {
							$did++; $divid = "r-$did";
							list ( $editxml, $ids ) = toxmltext($forest, $divid);
							$editxml = preg_replace("/id=\"(.*?)\"/", "id=\"\\1_$divid\"", $editxml);
							$totids .= $ids;
							$linkids = preg_replace("/_r-\d+/", "", $ids);
							$sentlist .=  "<tr id=\"$divid\">
									<td style=\"padding-right: 10px;\"><a href='index.php?action=file&cid=$fileid&jmp=$linkids'>text</a>
										<br><a href='index.php?action=$action&cid=$fileid&sentence=$sentid&node={$nodeid}' target=sent>tree</a>
									<td id=mtxt>".$editxml."</td></tr>";
						} else if ( $treestyle == "bracketstring" ) {
							$maintext .=  "<div id=tree>".bracketstring($forest)."</div>";
						} else if ( $treestyle == "table" ) {
							$maintext .=  "<div id=tree>".drawtree($forest, true)."</div>";
						} else if ( $treestyle == "svg" ) {
							$svgtree = makesvgtree($forest, true);
							$maintext .= "\n".$svgtree;
						} else if ( $treestyle == "vertical" ) {	
							$maintext .= "<link href='$jsurl/treeul.css' rel='stylesheet' type='text/css'/><div class=tree>".drawtree2($forest)."</div>";
						} else {
							// Use table graph by default
							$treestyle = "horizontal";
							$maintext .=  "<div id=tree>".$forest->asXML()."</div>";
						};
					};
					if ( $treestyle == "horizontal" ) {
						$maintext .= "
							<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 3;'></div>
							<script language=Javascript src=\"$jsurl/tokedit.js\"></script>
							<script language=Javascript src=\"$jsurl/tokview.js\"></script>
							<script language=Javascript src=\"$jsurl/psdx.js\"></script>
							<script language=Javascript>
							var username = '$username';
							var cid = '$cid';
							var treeid = '{$forest['id']}';
							maketext();
							</script>
							<link href='$jsurl/psdx-hor.css' rel='stylesheet' type='text/css'/>
						";
					} else if ( $treestyle == "xmltext" ) {
						$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
						#Build the view options	
						foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
							$formcol = $item['color'];
							# Only show forms that are not admin-only
							if ( $username || !$item['admin'] ) {	
								if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
								$ikey = $item['inherit'];
								if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] ) || $key == "pform" || 1==1 ) { #  || $item['subtract'] && preg_match("/ $ikey=/", $editxml)
									$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
									$fbc++;
								};
								if ( $key != "pform" ) { 
									if ( !$item['admin'] || $username ) { $attlisttxt .= $alsep."\"$key\""; $alsep = ","; };
								};
							};
						};
						foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
							$val = $item['display'];
							if ( preg_match("/ $key=/", $editxml) || 1==1 ) {
								if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
								if ( !$item['admin'] || $username ) {
									if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
									$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
									$pcolor = $item['color'];
									$tagstxt .= " <button id='tbt-$key' style='background-color: #$bc; color: $pcolor;$bgcol' onClick=\"toggletag('$key')\">{%$val}</button>";
								};
							} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
								unset($labarray[$akey]);
							};
						};
						$maintext .= "
						<p>$formbuts 
						<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
							<div id=mtxt>
							<table>
							$sentlist
							</table>
							</div>
							<script language=Javascript src='$jsurl/tokedit.js'></script>
							<script language=Javascript src='$jsurl/tokview.js'></script>
							<script language=Javascript>
							var username = '$username';
							var formdef = $jsonforms;
							var orgtoks = new Object();
							var attributelist = Array($attlisttxt);
							$attnamelist
							formify(); 
							var jmps = '$totids';
							var jmpar = jmps.split(' ');
							for (var i = 0; i < jmpar.length; i++) {
								var jmpid = jmpar[i];
								highlight(jmpid, '#ffffbb', '#ffffbb');
							};
							</script>
						";
					};
					$xpathurl = addslashes($xpath);
					$maintext .= "<hr> <form  style='display: none;' id=xpdf name=xpdf action='index.php?action=$action&act=xpath&dltype=psd' method=post>
							<input type=hidden name=xpath value='$xpathurl'></form>
						<a onclick=\"document.getElementById('xpdf').submit();\">Download results as PSD</a>";
				} else { 
					$maintext .= "<p><i>Error while getting the result</i><hr>".htmlentities($results); 
				};
			};
		
		} else {

			# Display potential pre-defined queries
			if (  $settings['psdx']['querypage'] ) {
				$linktext = $settings['psdx']['querylink'] or $linktext = "Go to query page";
				$maintext .= "<hr><h3>{%Predefined Queries}</h3>
					<p><a href='{$settings['psdx']['querypage']}'>{%$linktext}</a>";

			} else if ( $settings['psdx']['queries'] ) {
				$maintext .= "<hr><h3>{%Predefined Queries}</h3>
					<p>{%Click on one of the named queries below to copy it to the search window}";
				foreach ( $settings['psdx']['queries'] as $key => $item ) { 
					$maintext .= "<p class=\"list\"><a onclick=\"document.getElementById('xpathfield').value=this.firstChild.innerHTML; document.getElementById('xpathfield').focus();\"><span style='display: none;'>{$key}</span>{%{$item['display']}}</a></p>";
				};
			};
		
			# Display text-level restrictions
			if ( $settings['psdx']['cqp'] ) {
			
				$cqpatts = $settings['cqp']['sattributes'];
				$maintext .= "</td><td valign=top style='border-left: 1px solid #aaaaaa; padding-left: 10px;'>";
				# Deal with old-style pattributes as xattribute
				# Deal with any additional level attributes (sentence, utterance)
				foreach ( $settings['cqp']['sattributes'] as $xatts ) {
					if ( !$xatts['display'] || $xatts['key'] != "text" ) continue; # This only works for text-level queries
					$maintext .= "$hr<h3>{%{$xatts['display']}}</h3><table>"; $hr = "<hr>";
					foreach ( $xatts as $key => $item ) {
						$xkey = "{$xatts['key']}_$key";
						$val = $item['long']."" or $val = $item['display']."";
						if ( $item['type'] == "group" ) { 
							$maintext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
						} else {
							if ( $item['nosearch'] ) $a = 1; # Ignore this in search 
							else if ( $item['type'] == "range" ) 
								$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
							else if ( $item['type'] == "select" || $item['type'] == "kselect" ) {
								# Read this index file
								$tmp = file_get_contents("$corpusfolder/$xkey.avs"); unset($optarr); $optarr = array();
								foreach ( explode ( "\0", $tmp ) as $kva ) { 
									if ( $kva ) {
										if ( $item['values'] == "multi" ) {
											$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
											$kvl = explode ( $mvsep, $kva );
										} else {
											$kvl = array ( $kva );
										}
								
										foreach ( $kvl as $kval ) {
											if ( $item['type'] == "kselect" ) $ktxt = "{%$key-$kval}"; else $ktxt = $kval;
											$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
										};
									};
									foreach ( $kvl as $kval ) {
										if ( $kval && $kval != "_" ) {
											if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
												else $ktxt = $kval;
											$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
										};
									};
								};
								if ( $item['sort'] == "numeric" ) sort( $optarr, SORT_NUMERIC ); 
								else sort( $optarr, SORT_LOCALE_STRING ); 
								$optlist = join ( "", $optarr );
								if ( $item['select'] == "multi" ) {
									$multiselect = "multiple";  $msarr = "[]";
									$mstext = "select choices";
								} else {
									$multiselect = ""; $msarr = "";
									$mstext = "select";
								};
								$maintext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>[{%$mstext}]</option>$optlist</select>";
							} else 
								$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
						};
					};
					$maintext .= "</table>"; 
				};	

			};
		};
		$maintext .= "</form>";
		$maintext .= "</td></tr></table>";

	} else if ( $act == "nodesave" ) {
		check_login();
		$cid = $_POST['cid'];
		$psdxfile = "Annotations/$cid.psdx";

		$file = file_get_contents($psdxfile);
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
		
		file_put_contents($psdxfile, $forestxml->asXML());
		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';</script>"; exit;

	} else if ( $act == "treesave" ) {

		check_login();
		$cid = $_POST['cid'];
		$treeid = $_POST['treeid'];
		$psdxfile = "Annotations/$cid.psdx";

		if ($_POST['newxml']) {
			$file = file_get_contents($psdxfile);
			$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$forestxml ) { print "Forest not found: $cid<hr>"; print $file; exit; };


			$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
			$forest = $result[0]; 
			if ( !$forest ) { fatal ("Tree not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
			
			$forest[0] = "##INSERT##";
			
			# Renumber the tree
			$newtree = simplexml_load_string($_POST['newxml'], NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			renumber($newtree);
			$newxml = $newtree->asXML();
			$newxml = preg_replace('/<\?xml[^>]*\?>/', '', $newxml); // remove redundant <?xml
			
			$source = $forestxml->asXML();
			$newfile = preg_replace("/<[^>]+>##INSERT##<\/[^>]+>/", $newxml, $source);
			
			print "top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';";
			file_put_contents($psdxfile, $newfile);
			print "Changes have been saved
				<script language=Javascript>top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';</script>"; exit;
		};

	} else if ( $act == "treeedit" && $treeid && $cid ) {
	
		check_login();
		if ( !is_writable($psdxfile)  ) {
			fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
		};
		
		$file = file_get_contents($psdxfile);
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
						<script language=Javascript src=\"$jsurl/psdx.js\"></script>
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
						<link href='$jsurl/psdx-hor.css' rel='stylesheet' type='text/css'/>
						
						<script language=Javascript src=\"$jsurl/psdxedit.js\"></script>
					";

	} else if ( $act == "nodedelete" && $_GET['nid'] && $_GET['treeid'] ) {
	
		check_login();
		if ( !is_writable($psdxfile)  ) {
			fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
		};
		
		$file = file_get_contents($psdxfile);
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$forestxml ) { fatal ("Failed to load PSDX file: Annotations/$cid.psdx"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
		$nid = $_GET['nid'];
		
		$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
		$forest = $result[0]; 
		if ( !$forest ) { fatal ("Forest not found: $treeid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
	
		$result = $forest->xpath(".//*[@id=\"$nid\"]"); 
		$node = $result[0]; 
		if ( !$node ) { fatal ("Node not found: $nid"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };

		if ( ( $node['Text'] != "" || $node['tokid'] != "" ) && !$_GET['force'] ) { 
			$maintext .= "<h2>Warning</h2>
				<p>You are about to delete a node which has a non-empty @Text - are you sure you want to do this?
				<p><pre>".htmlentities($node->asXML())."</pre>
				<p><a href='index.php?action=$action&act=nodedelete&cid=$cid&nid=$nid&treeid=$treeid&force=1'>confirm delete</a>
				";
		} else {
			print "<p>Deleting node: <pre>".htmlentities($node->asXML())."</pre>"; 
			$todel = current($node->xpath("parent::*"));
			unset($node[0][0]);
			// Remove empty parent nodes as well
			while ( $todel && count($todel->children()) == 0 ) {
				print "<p>Deleting empty parent: <pre>".htmlentities($todel->asXML())."</pre>"; 
				$tmp = current($todel->xpath("parent::*"));
				unset($todel[0][0]);
				$todel = $tmp;
			};
			unset($node[0][0]);

			renumber($forestxml);

			file_put_contents($psdxfile, $forestxml->asXML());
			print "Changes have been saved
				<script language=Javascript>top.location='index.php?action=$action&cid=$cid&treeid=$treeid&node=$nid';</script>"; exit;
			exit;
		};

	} else if ( $act == "nodeedit" && $_GET['nid'] && $treeid ) {
		check_login();
		if ( !is_writable($psdxfile)  ) {
			fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
		};
		
		$file = file_get_contents($psdxfile);
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
				or $editfields = array ( "tokid" => array ("display" => "Token ID"), "Text" => array ("display" => "Text"), "Notext" => array ("display" => "Nonword content") );			
		} else if ( $nodetype == "eTree" ) {
			$editfields = $settings['psdx']['eTree'] 
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
			<p><input type=submit value=Save> &bull; 
				<a href='index.php?action=$action&act=nodedelete&cid=$cid&nid=$nid&treeid=$treeid'>delete this node</a>
			</form>";
					
	} else if ( $cid && file_exists($psdxfile) ) {

		$file = file_get_contents($psdxfile);
		$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		# Check if there are <forest> <eTree> or <eLeaf> without @is - if so, renumber and reload
		if ( $forestxml->xpath("//forest[not(@id)] | //eTree[not(@id)] | //eLeaf[not(@id)]") ) {
			if ( !is_writable($psdxfile)  ) {
				if ( $username ) fatal ("File Annotations/$cid.psdx is not writable (and needs to be renumbered) - please contact admin"); 
			} else {
				renumber($forestxml);
				file_put_contents($psdxfile, $forestxml->asXML());
				print "<p>File not properly numbered - renumbering and reloading
				<script language=Javascript>location.reload(true);</script>";
				exit;
			};
		};

		require ("$ttroot/common/Sources/ttxml.php");
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
			$maintext .= "<h2>{%Tree} $treeid = {%!sentence} $sentid</h2>
				<script language=Javascript src=\"$jsurl/tokedit.js\"></script>
				<script language=Javascript>var tid = '$cid';</script>
				<div id='mtxt' style='margin-bottom: 20px;'>$sentence</div>
				<hr>";
			if ( $treestyle != "bracketstring" ) $maintext .= "<p><i>{%Move your mouse over the leaves in the tree to get info from the corresponding word in the sentence.}</i></p>";
			if ( $username ) $maintext .= "<p class=adminpart><i>Click on a node below or a word above to edit its content - use <a href='index.php?action=$action&act=treeedit&cid=$cid&treeid=$treeid'>tree edit</a> to edit the layout</i></p>";
			if ($forest) {
				if ( $treestyle == "vertical" ) {	
					$maintext .= "<link href='$jsurl/treeul.css' rel='stylesheet' type='text/css'/><div class=tree style='width: 2000px;'>".drawtree2($forest)."</div>";
				} else if ( $treestyle == "horizontal"  ) {	
					$maintext .= "\n<div id=tree>".$forest->asXML()."</div>
						<script language=Javascript src=\"$jsurl/psdx.js\"></script>
						<script language=Javascript>
						var username = '$username';
						var cid = '$cid';
						var treeid = '$treeid';
						maketext();
						</script>
						<link href='$jsurl/psdx-hor.css' rel='stylesheet' type='text/css'/>
					";
				} else if ( $treestyle == "svg"  ) {	
					$svgtree = makesvgtree($forest);
					$maintext .= "\n".$svgtree;
				} else if ( $treestyle == "bracketstring" ) {
					$maintext .=  "<div id=tree>".bracketstring($forest)."</div>";
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
			// $maintext .= "<hr><a href='index.php?action=$action&cid=$cid'>{%sentence list}</a> ";
			$maintext .= "<hr><a href='index.php?action=file&cid=$cid&sentence=1'>{%sentence list}</a> ";
			$maintext .= "&bull; <a href='index.php?action=file&cid=$cid&jmp=$sentid'>{%to text mode}</a> $options</p><br>";
		} else {
			$maintext .= "<div id=mtxt><table cellpadding=3px>";
			$result = $forestxml->xpath("//forest"); 
 			foreach ( $result as $tmp ) { 
 				$sentid = $tmp['sentid'] or $sentid = $tmp['Location'];
 				$forestid = $tmp['id'];

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

 				$maintext .= "<tr><td><a href='index.php?action=$action&cid=$cid&treeid=$forestid'>Sentence&nbsp;{$sentid}</a>
 								<td>$sentence";
 			};
			$maintext .= "</table></div><hr><a href='index.php?action=$action'>{%more files}</a> &bull; &bull; <a href='index.php?action=file&cid=$cid&jmp=$sentid'>{%to text mode}</a> <a href='index.php?action=$action&act=xpath&cid=$cid'>{%search in this file}</a>";
			if ( !$settings['psdx']['nodownload'] ) $maintext .= " &bull; <a href='index.php?action=$action&act=download&cid=$cid'>{%download file}</a>";
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
		if ( !$some ) $maintext .= "<p><i>{%No results found}</i>";
	
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
					// $leaftext = $leaf['Text'] or $text = "&empty;";
					$leaftext = $leaf['Text'] or $leaftext = $leaf['Notext'] or $leaftext = "&empty;";
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
					$leaftext = $leaf['Text'] or $leaftext = $leaf['Notext'] or $leaftext = "&empty;";
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
			$leaftext = $leaf['Text'] or $leaftext = $leaf['Notext'] or $leaftext = "&empty;";
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
		global $level; global $spacing;
		$spacing[1] = 2;
		$level++; $first = 1;
		$levstr = "({$node['Label']} ";
		print $levstr; $spacing[$level] = $spacing[$level-1] + strlen($levstr);
		foreach  ( $node->xpath("./eTree") as $child ) {
			if ( $first ) {
				$first = 0;
			} else {
				print "\n";
				#for ( $i=0; $i<$level; $i++ ) { print "   "; };
				print str_repeat(' ', $spacing[$level]);
			};
			psdtree($child);
		};
		foreach  ( $node->xpath("./eLeaf") as $leaf ) {
			print "".$leaf['Text'].$leaf['Notext'];
		};
		print ") ";
		$level--;
	};

	function totext ( $forestxml ) {
		# Turn the tree into text
		$text = "";
		$result = $forestxml->xpath(".//eLeaf[@Text]");
		foreach ( $result as $node ) { 
			$text .= $node['Text']." ";
		}; 
		return $text;
	};	

	function bracketstring ( $forestxml ) {
		# Turn the tree into text
		$string = "";
		if ( $forestxml->getName() == "eLeaf" ) {
			return $forestxml['Text'].$forestxml['Notext'];
		} else {
			$result = $forestxml->children();
			$cat = $forestxml['Label'];
			foreach ( $result as $node ) { 
				$text .= bracketstring($node);
			}; 
			return "[<sub>$cat</sub> $text] ";
		};
	};	

	function toxmltext ( $forestxml, $divid = "" ) {
		# Turn the tree into text
		global $txtlast; global $txtxml; global $ttroot;
		$txtid = $forestxml['File'];
		$sentid = $forestxml['sentid'];
		if ( $txtlast != $txtid ) {	
			require_once ("$ttroot/common/Sources/ttxml.php");
			$ttxml = new TTXML($txtid, false);
			$txtxml = $ttxml->xml;
			$txtlast = $txtid;
		};
		$text = ""; $idlist = "";
		$result = $forestxml->xpath(".//eLeaf[@tokid]");
		foreach ( $result as $node ) { 
			$idlist .= $node['tokid']."_".$divid." ";
		}; 
		
		$tmp = $txtxml->xpath("//s[@id=\"$sentid\"]"); $sentxml = $tmp[0];
		
		if ( $sentxml ) $text = $sentxml->asXML();
		else $text = "<i>".totext($forestxml)."</i>";
				
		return array ( $text, $idlist );
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