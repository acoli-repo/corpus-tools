<?php
	// Script to allow editing and viewing XML file
	// This is the backbone of TEITOK
	// (c) Maarten Janssen, 2015
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	$oid = $fileid;
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$xmlid = $fileid; 
	$xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	$xmlid = preg_replace ( "/.*\//", "", $xmlid );
	
	
	// on "paged" display, determine what to show
	if ( !$_GET['pbtype'] && is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['element'] ) { 
		# allow special "page types" to be defined in the settings, which can be XML elements and not milestones
		$_GET['pbtype'] = $settings['xmlfile']['paged']['element'];
	};
	if ( !$_GET['pbtype'] || $_GET['pbtype'] == "pb" ) { 
		$pbelm = "pb";
		$titelm = "Page";
		$pbtype = "pb";
		$pbsel = "&pbtype={$_GET['pbtype']}";
	} else if ( $_GET['type'] == "chapter" ) { 
		$pbtype = "milestone[@type=\"chapter\"]";
		$titelm = "Chapter";
		$pbelm = "milestone";
		$pbsel = "&pbtype={$_GET['pbtype']}";
	} else if ( is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['closed'] ) {
		$pbtype = $_GET['pbtype'];
		$titelm = $settings['xmlfile']['paged']['display'] or $titelm = ucfirst($_GET['type']);
		$pbelm = $_GET['pbtype'];
	} else {
		$pbtype = "milestone[@type=\"{$_GET['pbtype']}\"]";
		$titelm = $settings['xmlfile']['paged']['display'] or $titelm = ucfirst($_GET['type']);
		$pbelm = "milestone";
		$pbsel = "&pbtype={$_GET['pbtype']}";
	};
	
	if ( !$fileid ) { 
		fatal ( "No XML file selected." );  
	};

	if ( !file_exists("$xmlfolder/$fileid") && substr($fileid,-4) != ".xml" ) { 
		$fileid .= ".xml";
	};
	
	if ( !file_exists("$xmlfolder/$fileid") ) { 
	
		$fileid = preg_replace("/^.*\//", "", $fileid);
		$test = array_merge(glob("$xmlfolder/**/$fileid")); 
		if ( !$test ) 
			$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid"), glob("$xmlfolder/*/*/*/$fileid")); 
		$temp = array_pop($test); 
		$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
		if ( $fileid == "" ) {
			fatal("No such XML File: {$oid}"); 
		};
	};

	# Determine the file date
	$tmp = filemtime("$xmlfolder/$fileid");
	$fdate = strftime("%d %h %Y", $tmp);

	$file = file_get_contents("$xmlfolder/$fileid"); 

// 	if ( ( preg_match("/<tok[^>]*>\s/", $file) || preg_match("/\s<\/tok>/", $file) ) && $username ) {
// 		$warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>This file seems to have been modified with external XML tags that 'normalized' the spaces.
// 			Since the &lt;text&gt; element of TEITOK is a portion of whitespace-sensitive XML, please consider reverting to a previous version.</p>";
// 	};

	$file = namespacemake($file);
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) { fatal ( "Failing to read/parse $fileid" ); };
			
	$result = $xml->xpath("//title"); 
	$title = $result[0];
	if ( $title == "" ) $title = "<i>{%Without Title}</i>";

	# In paged texts, always jump to a page
	if ( $settings['xmlfile']['paged'] && !$_GET['page'] && !$_GET['pageid'] && !$_GET['div'] ) {
		# We will by default jump to the page containing the tok we are looking for
		# IF there are multiple tokens, jump to the first one
		$tokids = $_GET['tid'] or $tokids = $_GET['jmp'];
		$tmp = explode ( " ", $tokids ); $tokid = $tmp[0];
		if ( $tokid ) {
			# The page with the word we are trying to show
			$tokpos = strpos($file, "id=\"$tokid\"");
			$pbef = rstrpos($file, "<$pbelm", $tokpos) or $pbef = strpos($file, "<text");
			$tmp = substr($file, $pbef, 30);
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) {$_GET['pageid'] = $matches[1]; }
			else if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) {$_GET['page'] = $matches[1]; };
		} else {
			# Or just the first page (pb)
			$pbef = strpos($file, "<$pbelm");
			$pbaf = strpos($file, ">", $pbef);
			$pblen = $pbaf-$pbef+1;
			if ( !$pbef ) {	
				$pbef = strpos($file, "<text"); # Allow for non-paged XML files
				$pblen = 500;
			};
			$tmp = substr($file, $pbef, $pblen); 
			if ( preg_match("/<$pbelm [^>]*id=\"(.*?)\"/", $tmp, $matches) ) {
				$_GET['pageid'] = $matches[1];
			};
 		};
	};

	// When so indicated, load the external PSDX file
	// Why would we want this?
	if ( $settings['psdx'] && file_exists( "Annotations/$xmlid.psdx") ) {
		$psdx = simplexml_load_file("Annotations/$xmlid.psdx", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	};

	if ( $username ) $txtid = $fileid; else $txtid = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>$title </h1>";


	if ( $username && !is_writable("$xmlfolder/$fileid") ) {
		$warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>Due to filepermissions, this file cannot be
			modified by TEITOK - please contact the administrator of the server.</p>";
	};

	$maintext .= $warnings;

	# Show optional additional headers
	if ( $_GET['tpl'] && file_exists("Resources/teiHeader-{$_GET['tpl']}.tpl") ) {
		$header = file_get_contents("Resources/teiHeader-{$_GET['tpl']}.tpl");
		$maintext .= xpathrun($header, $xml);
	} else if ( $_GET['headers'] == "full" && file_exists("Resources/teiHeader-long.tpl") ) {
		$header = file_get_contents("Resources/teiHeader-long.tpl");
		$maintext .= xpathrun($header, $xml);
		if ( file_exists("Resources/teiHeader.tpl") ) $maintext .= "<ul><li><a href='{$_SERVER['REQUEST_URI']}&headers=short'>{%less header data}</a></ul>";
		$maintext .= "<hr>"; 
	} else if ( file_exists("Resources/teiHeader.tpl") ) {
		$header = file_get_contents("Resources/teiHeader.tpl");
		$maintext .= xpathrun($header, $xml);
		$maintext .= "<ul>"; 
		if ( !$_GET['cid'] && !$_GET['id'] ) $cidurl = "&cid=$fileid";
		if ( file_exists("Resources/teiHeader-long.tpl") ) $maintext .= "<li><a href='{$_SERVER['REQUEST_URI']}$cidurl&headers=full'>{%more header data}</a>";
		if ( $settings['xmlfile']['teiHeader'] ) {
			foreach ( $settings['xmlfile']['teiHeader'] as $key => $item ) {
				$cond = $item['condition'];
				if ( $cond ) {
					$result = $xml->xpath($cond); 
					if ( !$result ) {
						continue; # Conditional header
					};
				};
				$tpl = $key;
				if ( $item['admin'] ) {
					if ($username) $maintext .= " &bull; <a href='index.php?action=file&cid=$fileid&tpl=$tpl' class=adminpart>{$item['display']}</a>";
				} else if ( !$item['admin'] ) {
					$maintext .= " &bull; <a href='index.php?action=file&cid=$fileid&tpl=$tpl'>{$item['display']}</a>";
				};
			};
		};
		if ( file_exists("Resources/teiHeader-edit.tpl") && $username ) $maintext .= " &bull; <a href='index.php?action=header&act=edit&cid=$fileid&tpl=teiHeader-edit.tpl' class=adminpart>edit teiHeader</a>";
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=header&act=rawview&cid=$fileid' class=adminpart>view teiHeader</a>";
		$maintext .= "</ul><hr>";
	} else {
		foreach ( $headershow as $hq => $hn ) {
			$result = $xml->xpath($hq); 
			$hv = $result[0];
			if ( $hv ) {
				$htxt = $hv->asXML();
				$maintext .= "<h3>{%$hn}</h3><p>$htxt</p>";
			};
		}; 
		if ( $headershow ) $maintext .= "<hr>";
	};
	
	# Too slow for large files
	#$result = $xml->xpath("//tok"); 
	#$tokcheck = $result[0]; 
	if ( strstr($file, '</tok>' ) ) $tokcheck = 1; 

	if ( $settings['xmlfile']['restriction'] && !$xml->xpath($settings['xmlfile']['restriction']) && !$username ) { 
		$restricted = 1;
		// This file is not accessible - restrict to limited amound of words
		$maxwords = $settings['xmlfile']['maxwords'] or $maxwords = 20;
		if ( $_GET['jmp'] ) $prevword = substr($_GET['jmp'], 2);
		else if ( $_GET['tid'] ) $prevword = substr($_GET['tid'], 2);
		$startword = $prevword or $startword = $maxwords+1;
		
		$firstword = $startword - $maxwords; $lastword = $startword + $maxwords;
		
		# $editxml = "<p>Showing ($startword+-$maxwords) $firstword to $lastword";
		$editxml = $file;
		$pb = "id=\"w-$lastword\"";
		$nidx = strpos($editxml, $pb);
		# print $pb; print $nidx;
		if ( !$nidx || $nidx == -1 ) { 
			$nidx = strpos($editxml, "</text");
		};

		$bidx = rstrpos($editxml, "<tok id=\"w-$firstword\"", $nidx-1); 
		if ( !$bidx || $bidx == -1 ) { 
			$bidx = strpos($editxml, "<text", 0);
		};
		
		$span = $nidx-$bidx;
		$maintext .= "<p>{%Due to copyright restrictions, only a fragment of this text is displayed}</p><hr>"; 
		$editxml = substr($editxml, $bidx, $span);
		
	} else if ( $_GET['elm'] ) {
	
		# Show sentence view
		$stype = $_GET['elm'] or $stype = "s";
		if ( $stype == "1" ) $stype = "s";
		$stype = str_replace("|", "| //", $stype);
		$result = $xml->xpath("//$stype"); 
		if ( $result > 100 ) { 
			$result = array_slice($result, 0, 100);
		};
		$sentnr = 1; $ewd = 25;
		foreach ( $result as $sent ) {
			$stxt = $sent->asXML(); 
			$sentid = $sent['n'] or $sentid = "[".$sentnr++."]";
			$treelink = ""; $nrblock = "";
			if ( $sent->xpath(".//tok[@head and @head != \"\"]") ) { 
				$treelink .= "<a href='index.php?action=deptree&cid=$fileid&sid={$sent['id']}' title='dependency tree'>tree</a>"; 
				$ewd = 50;
			};
			if ( $psdx  && $stype == "s" ) { // Allow a direct link to a PSDX tree 
				$nrblock = "
					<div style='display: inline-block; float: left; margin: 0px; padding: 0px; width: 80px;'>
					<table style='width: 100%; table-layout:fixed; margin: 0px;'><tr><td style='width: 25px;font-size: 10pt; '>";
				if ( $psdx->xpath("//forest[@sentid=\"$sentid\"]") ) {
					$editxml .= "<a href='index.php?action=psdx&cid=$xmlid&sentence=$sentid'>tree</a>";
				};
				$pl = "100px";
				if ( $username ) {
					$editxml .= " 
						<td style='width: 25px;font-size: 10pt; '><a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>edit</a>";
					$pl = "100px";
				};
				$nrblock .= " 
					<td style='width: 30px;font-size: 10pt;  text-align: right;'>$sentid </table></div>";
			}  else {
				$nrblock = "
					<div style='display: inline-block; float: left; margin: 0px; padding: 0px; padding-top: 0px; width: {$ewd}px; font-size: 10pt;'>
						<a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>$sentid</a>
						$treelink
					</div>";
				$pl = "50px";
			};
			$editxml .= "
				<div style='width: 90%; border-bottom: 1px solid #66aa66; margin-bottom: 6px; padding-bottom: 6px;'>
				$nrblock
				<div style='padding-left: $pl;'>
				$stxt";
			foreach ( $settings['xmlfile']['sattributes'][$stype] as $item ) {
				$key = $item['key'];
				$atv = preg_replace("/\/\//", "<lb/>", $sent[$key]);	
				if ( $item && $item['color']) { $scol = "style='color: {$item['color']}'"; } else { $scol = "class='s-$key'"; };
				if ( $atv && ( !$item['admin'] || $username ) ) {
					if ( $item['admin'] ) $scol .= " class='adminpart'";
					$editxml .= "<div $scol title='{$item['display']}'>$atv</div>"; 
				}
			};
			$editxml .= "</div></div>";
		};
		
	} else if ( ( $_GET['page'] || $_GET['pageid'] ) && $_GET['page'] != "all" ) {

		# Show specific page
		$editxml = $file;
		if ( $_GET['pageid'] ) {
			$pb = "<$pbelm id=\"{$_GET['pageid']}\"";
			$pidx = strpos($editxml, $pb);
		} else {
			$pb = "<$pbelm n=\"{$_GET['page']}\"";
			$pidx = strpos($editxml, $pb);
		};
		if ( !$pidx || $pidx == -1 ) { 
			# When @n is not the first attribute, we cannot use strpos - try regexp instead (slower)
			if ( $_GET['pageid'] ) {
				preg_match("/<$pbelm [^>]*id=\"{$_GET['pageid']}\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
			} else {
				preg_match("/<$pbelm [^>]*n=\"{$_GET['page']}\"/", $editxml, $matches, PREG_OFFSET_CAPTURE, 0);
			};
			$pidx = $matches[0][1];
		};
		if ( !$pidx || $pidx == -1 ) { fatal ("No such $pbelm in XML: {$_GET['page']} {$_GET['pageid']}"); };

		
		# Find the next page/chapter (for navigation, and to cut off editXML)
		$nidx = strpos($editxml, "<$pbelm", $pidx+1); 
		if ( !$nidx || $nidx == -1 ) { 
			$nidx = strpos($editxml, "</text", $pidx+1); $nnav = "";
		} else {
			$nidy = strpos($editxml, ">", $nidx); 
			$tmp = substr($editxml, $nidx, $nidy-$nidx ); 
			 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $npid = $matches[1]; };
			if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $npag = $matches[1]; };
			
			if ( $npid ) $nnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$npid&pbtype={$_GET['pbtype']}'>> $npag</a>";
			else $nnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$npag'>> $npag</a>";
		};
		
		# Find the previous page/chapter (for navigation)
		$bidx = rstrpos($editxml, "<$pbelm ", $pidx-1); 
		if ( !$bidx || $bidx == -1 ) { 
			$bidx = strpos($editxml, "<text", 0); $bnav = "<a href='index.php?action=pages&cid=$fileid$pbsel'>{%index}</a>";
		} else {
			$tmp = substr($editxml, $bidx, 150 ); 
			if ( preg_match("/id=\"(.*?)\"/", $tmp, $matches ) ) { $bpid = $matches[1]; };
			if ( preg_match("/n=\"(.*?)\"/", $tmp, $matches ) ) { $bpag = $matches[1]; } else { $bpag = ""; };
			if ( $bpid  )  $bnav = "<a href='index.php?action=$action&cid=$fileid&pageid=$bpid$pbsel'>$bpag <</a> ";
			else $bnav = "<a href='index.php?action=$action&cid=$fileid&page=$bpag'>$bpag <</a>";
			if ( !$firstpage ) { $bnav = "<a href='index.php?action=pages&cid=$fileid$pbsel'>{%index}</a> &nbsp; $bnav"; };
		};

		// when pbelm != pb, grab the <pb/> from just before the milestone
		if ( $pb && $pbelm != "pb") {
 			if ( strpos($editxml, "<tok", $pidx) < strpos($editxml, "<pb", $pidx) ) {
 				$bpb1 = rstrpos($editxml, "<pb ", $pidx-1); 
 				$bpb2 = strpos($editxml, ">", $bpb1);
 				$len = ($bpb2-$bpb1)+1;
				$facspb = substr($editxml, $bpb1, $len); 
 			};
		};		
		
		$span = $nidx-$pidx;
		$editxml = $facspb.substr($editxml, $pidx, $span); 
		
		if ( $_GET['page'] ) $folionr = $_GET['page']; // deal with pageid
		else if ( $_GET['pageid'] ) {
			if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"[^>]*id=\"{$_GET['pageid']}\"/", $editxml, $matches ) 
				|| preg_match("/<$pbelm [^>]*id=\"{$_GET['pageid']}\"[^>]*n=\"([^\"]+)\"/", $editxml, $matches ) ) 
					$folionr = $matches[1];
		} else if ( preg_match("/<$pbelm [^>]*n=\"(.*?)\"/", $tmp, $matches ) ) {
			$folionr = $matches[1]; 
		};
		
		if ( $pbelm == "pb" ) $foliotxt = "{%Folio}";
		
		# Build the page navigation
		$pagenav = "<table style='width: 100%'><tr> <!-- /<$pbelm [^>]*id=\"{$_GET['pageid']}\"[^>]*n=\"(.*?)\"/ -->
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>$foliotxt $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
						";

	} else if ( $_GET['div'] ) {
		$divtype = $_GET['divtype'] or $divtype = "tei:div";
		$mtxtelement = "//*[name() = '$divtype' and @id='{$_GET['div']}']";
		// TODO: Grab the pb/@facs just above the <div>
		// TODO: Create a navigation bar

		$result = $xml->xpath($mtxtelement); 
		if ( $result ) {
			$txtxml = $result[0]; 
			$editxml = $txtxml->asXML();
		} else {
			fatal ("Display element not found: $mtxtelement");
		};
	} else if ( $mtxtelement ) {
		$result = $xml->xpath($mtxtelement); 
		if ( $result ) {
			$txtxml = $result[0]; 
			$editxml = $txtxml->asXML();
		} else {
		 	fatal ("Display element not found: $mtxtelement");
		};
	} else {
	
		$editxml = "<text></text>";

	};
	$pageid = $_GET['pageid'];
	
	// Show a header above files that are only partially shown (to users) 
	if ( $restricted && $username ) { 
		$pagenav .= "<p class=adminpart>This text is only show partially to visitors due to copyright restrictions; 	
			to liberate this file, set ".$settings['xmlfile']['restriction']." in the header<hr>";
	};
	
	# Change any <desc> into i18n elements
	$editxml = preg_replace( "/<desc[^>]*>([^<]+)<\/desc>/smi", "<desc>{%\\1}</desc>", $editxml );
	
	$fp = $_GET['fp']; $lp = $_GET['lp'];
	if ( $fp ) {
		$fpx = preg_quote($fp);
		$editxml = preg_replace( "/.*([^\s]*<$pbelm [^>]*n=\"$fpx\")/smi", "\\1", $editxml );
	};
	if ( $lp ) {
		$lpx = preg_quote($lp);
		$editxml = preg_replace("/(<$pbelm [^>]*n=\"$lpx\"[^>]*\/?>[^\s]*).*/smi", "", $editxml);
	};

			if ( preg_match("/^<!\[CDATA\[/", $editxml) ) {
				 $shorthand = 1;
				 $editxml = $txtxml.'';
			};
	
	if ( file_exists("Pages/csslegenda.html") ) $customcss = file_get_contents("Pages/csslegenda.html");

	// <note> is ambiguous in TEITOK - make <note> into rollover notes optional
	if ( $settings['xmlfile']['textnotes'] ) {
		// for the correct order, abuse attnamelist 
		$attnamelist .= "\n				var floatnotes = false;";
	} else {
		$attnamelist .= "\n				var floatnotes = true;";
	};

	# Define which view to show
	$defaultview = $settings['xmlfile']['defaultview'];
	// Calculate where to start from settings and cookies
	if ( ( strpos($defaultview, "interpret") && !$_COOKIE['toggleint'] ) || $_COOKIE['toggleint'] == "true" ) {
		$moreactions .= "\n				toggleint();";
	};
	if ( ( strpos($defaultview, "breaks") && !$_COOKIE['toggleshow'] ) || $_COOKIE['toggleshow'] == "true" ) {
		$moreactions .= "\n				toggleshow();";
	};
	if ( ( strpos($defaultview, "pb") ) || $_COOKIE['pb'] == "true" ) {
		$moreactions .= "\n				toggletn('pb');";
	};
	if ( ( strpos($defaultview, "lb") ) || $_COOKIE['lb'] == "true" ) {
		$moreactions .= "\n				toggletn('lb');";
	};
	if ( ( strpos($defaultview, "colors") && !$_COOKIE['togglecol'] ) || $_COOKIE['togglecol'] == "true" ) {
		$moreactions .= "\n				togglecol();";
	};
	if ( ( strpos($defaultview, "images") && !$_COOKIE['toggleimg'] ) || $_COOKIE['toggleimg'] == "true" ) {
		$moreactions .= "\n				toggleimg();";
	};
	
	if ( $shorthand ) {

		if ( $username ) {
			$maintext .= "
			<div class=adminpart>
				<p>Edit the content of the XML in the form below, using the shorthand notation defined 
					for this project - the codes of which are displayed on the bottom.
				<p> To edit the whole XML including the headers, click <a href='index.php?action=rawedit&id=$fileid&full=1'>here</a>. 
				<br>To transform the shorthand notation into TEI, click <a href='index.php?action=unshorthand&id=$fileid'>here</a>
					<hr>";

			$maintext .= "
					<form action='index.php?action=rawsave&cid=$fileid' method=post>
					<textarea name=rawxml style='width: 100%; height: 250px;'>".$editxml."</textarea>
					<input type=submit value=Save>
					</form>
					";

			if ( file_exists("Resources/shorthand.tab") ) {
				$maintext .= "<hr><p>Shorthand symbols: "; $sep = "";
				foreach ( explode("\n", file_get_contents("Resources/shorthand.tab")) as $line ) {
					list ( $from, $desc, $to ) = explode ( "\t", $line ); 
					if ( $from ) $maintext .= "$sep <span style='color: #66aa66'>".htmlentities($from)."</span>: $desc"; $sep = " &bull; ";
				};
			};
	
			$maintext .= "
			</div>";
		

		} else {
			$editxml = preg_replace("/<lb\/>/", "<br/>", unshorthand($editxml));
			$maintext .= "
				<div id=mtxt>".$editxml."</div>";
		};
							
	
	} else {

		# empty tags are working horribly in browsers - change
		$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$val = $item['direction'];
			if ( $val )	{
				 $fdlist .= "\n				formdir['$key'] = '$val';";
			};
		};
		if ( $fdlist ) { $moreactions .= "\n				var formdir = [];$fdlist"; };
		$lablist = $_COOKIE['labels'] or $lablist = $settings['xmlfile']['defaultlabels'];
		if ( $lablist ) {
			$labarray = explode(",", $lablist);
		};
		$showform = $_COOKIE['showform'] or $showform = $settings['xmlfile']['defaultform'];
		if ( !$settings['xmlfile']['pattributes']['forms'][$showform] ) $showform = "form";
					
		$maintext .= "<div id=footnotediv style='display: none;'>This is where the footnotes go.</div>";

		
		#Build the view options	
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$formcol = $item['color'];
			# Only show forms that are not admin-only
			if ( $username || !$item['admin'] ) {
				if ( !$bestform ) $bestform = $key; 
				if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
				$ikey = $item['inherit'];
				if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
					$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
					$fbc++;
				};
				if ( $key != "pform" ) { 
					if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
				};
			} else if ( $showform == $key ) $showform = $bestform;
		};
		# Check whether we HAVE the form to show - or switch back
		if ( !strstr($editxml, " $showform=") 
			&& !$settings['xmlfile']['pattributes']['forms'][$showform]['subtract']
			) { $showform = $bestform;};
		
		
		# Only show text options if there is more than one form to show
		if ( $fbc > 1 ) $viewoptions .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

		$sep = "<p>";
		if ( $fbc > 1 ) {
			$showoptions .= "<button id='btn-col' style='background-color: #ffffff;' title='{%color-code form origin}' onClick=\"togglecol();\">{%Colors}</button> ";
			$sep = " - ";
		};
		
		# Some of these checks work after the first token, so first find the first token
		$tokpos = strpos($editxml, "<tok"); 
		
		if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) ||  strpos($editxml, "<lb", $tokpos)  ) ) {
 			$showoptions .= "<button id='btn-int' style='background-color: #ffffff;' title='{%format breaks}' onClick=\"toggleint();\">{%Formatting}</button>";
		};
		if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) || ( $username && strpos($editxml, "<pb") )  ) ) {
			// Should the <pb> button be hidden if there is only one page? (not for admin - pb editing)
 			$showoptions .= "<button id='btn-tag-pb' style='background-color: #ffffff;' title='{%show pagebreaks}' onClick=\"toggletn('pb');\">&lt;pb&gt;</button>";
		};
		if ( !$nobreakoptions && ( strpos($editxml, "<lb", $tokpos) ) ) {
 			$showoptions .= "<button id='btn-tag-lb' style='background-color: #ffffff;' title='{%show linebreaks}' onClick=\"toggletn('lb');\">&lt;lb&gt;</button>";
		};
		
		if ( !$username ) $noadmin = "(?![^>]*admin=\"1\")";
		if ( preg_match("/ facs=\"[^\"]+\"$noadmin/", $editxml) ) {
			$showoptions .= " <button id='btn-img' style='background-color: #ffffff;' title='{%show facsimile images}' onClick=\"toggleimg();\">{%Images}</button>";
		};
						
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$val = $item['display'];
			if ( preg_match("/ $key=/", $editxml) ) {
				if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
				if ( !$item['admin'] || $username ) {
					$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
					$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
					$pcolor = $item['color'];
					$tagstxt .= "<button id='tbt-$key' style='background-color: #$bc; color: $pcolor;' onClick=\"toggletag('$key')\">{%$val}</button>";
				};
			} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
			    unset($labarray[$akey]);
			};
		};
		if ( $tagstxt ) $showoptions .= " - {%Tags}: $tagstxt ";
		if ( $labarray ) {
			$labtxt = join ( "','", $labarray );
			$moreactions .= "\n				labels=['$labtxt'];";
		};
		if ( $showform ) {
			$moreactions .= "\n				setForm('$showform');";
		} else {
			$moreactions .= "\n				setbut('but-pal');";
		};
		// Set a default writing direction when defined
		if ( $settings['xmlfile']['basedirection'] ) {
			// Defined in the settings
			
			$attnamelist .= "\n				setbd('".$settings['xmlfile']['basedirection']."');";
		} else {
			$dirxpath = $settings['xmlfile']['direction'];
			if ( $dirxpath ) {
				$textdir = current($xml->xpath($dirxpath));
			};
			if ( $textdir ) {
				// Defined in the teiHeader for mixed-writing corpora
				$attnamelist .= "\n				setbd('".$textdir."');";
			};
		};

		# See if there is a sound to display
		$result = $xml->xpath("//media"); 
		if ( $result ) {
			foreach ( $result as $medianode ) {
				list ( $mtype, $mform ) = explode ( '/', $medianode['mimeType'] );
				if ( !$mtype ) $mtype = "audio";
				if ( $mtype == "audio" ) {
					# Determine the URL of the audio fragment
					$audiourl = $medianode['url'];
					if ( !strstr($audiourl, 'http') ) {
						if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
						else $audiourl = $baseurl."Audio/$audiourl"; 
					}
					if ( preg_match ( "/MSIE|Trident/i", $_SERVER['HTTP_USER_AGENT']) ) {	
						// IE does not do sound - so just put up a warning
						$audiobit .= "
								<p><i><a href='$audiourl'>{%Audio fragment for this text}</a></i> - {%Consider using Chrome or Firefox for better audio support}</p>
							"; 
					} else {
						$audiobit .= "<audio id=\"track\" src=\"$audiourl\" controls ontimeupdate=\"checkstop();\">
								<p><i><a href='{$medianode['url']}'>{%Audio fragment for this text}</a></i></p>
							</audio>
							"; 
						$result = $medianode ->xpath("desc"); 
						$audiobut = "Audio";
						$desc = $result[0].'';
						if ( $desc ) {
							$audiobit .= "<br><span style='font-size: small;'>$desc</span>";
						};
					};
				} else if ( $mtype == "video" ) {
					# Determine the URL of the video fragment
					$videourl = $medianode['url'];
					if ( !strstr($videourl, 'http') ) {
						if ( file_exists($videourl) ) $videourl =  "$baseurl/$videourl"; 
						else $videourl = $baseurl."Video/$videourl"; 
					}
					if ( preg_match ( "/MSIE|Trident/i", $_SERVER['HTTP_USER_AGENT']) ) {	
						// IE does not do video - so just put up a warning
						$audiobit .= "
								<p><i><a href='$audiourl'>{%Video fragment for this text}</a></i> - {%Consider using Chrome or Firefox for better video support}</p>
							"; 
					} else {
						$audiobit .= "<video id=\"track\" src=\"$videourl\" controls ontimeupdate=\"checkstop();\">
								<p><i><a href='{$medianode['url']}'>{%Video fragment for this text}</a></i></p>
							</video>
							<style>
							#track { display: block; position: fixed; right: 0px; top: 0px; }
							</style>
							"; 
						$result = $medianode->xpath("desc"); 
						$audiobut = "Video";
						$desc = $result[0].'';
						if ( $desc ) {
							$videobit .= "<br><span style='font-size: small;'>$desc</span>";
						};
					};
				};
			};
		};
		
		# Check if there are sub-sounds to display
		$result = $xml->xpath("//*[@start]"); 
		if ( $result && $audiobit ) {
			$showoptions .= " <button id='btn-audio' style='background-color: #ffffff;' onClick=\"toggleaudio();\">{%$audiobut}</button> ";
			$moreactions .= "makeaudio();";
		};



		if ( $showoptions != "" ) {
			$viewoptions .= $sep."{%Show}: $showoptions";
		};
		
		if ( $viewoptions != "" ) {
			# Show the View options - hidden when Javascript does not fire.
			$maintext .= "
				<div style='display: none;' id=jsoptions><h2>{%View options}</h2>
				$viewoptions
				</div>
				<div style='display: block; color: #992000;' id=nojs>
				{%Javascript seems to be turned off, or there was a communication error. Turn on Javascript for more display options.}
				</div>
				<hr>
				";
					
		};				

		if ( $audiobit ) $maintext .= "<script language='Javascript' src=\"$jsurl/audiocontrol.js\"></script>
			$audiobit
			<hr>";

		if ( $username ) {
			
			if ( preg_match("/<text[^>]*>\s*<\/text>/", $editxml) ) $emptyxml = 1;
			
			if ( $tokcheck ) { 
				$maintext .= "<p class=adminpart>			
					Edit the information about each word of this file by clicking on the word in the text below, or click
					<a href='index.php?action=rawedit&id=$fileid'>here</a> to edit the raw XML
					</p><hr>
					";

			} else if ( $emptyxml && 1 == 2 )  {
			 	# If the XML is empty, immediately show the edit mode
			 	# This does not seem to be good in most cases
			 	
				$maintext .= "<div class=adminpart>
 				<p>This XML file is empty. Please type or paste the text in the text box below, between the existing tags.
 				Since this is a browser interface, do not forget to save.</p>
 					<hr>";
 				
				$editxml = "
					<div id=\"editor\" style='width: 100%; height: 400px;'>".htmlentities($editxml)."</div>
	
					<form action=\"index.php?action=rawsave&cid=$fileid$type\" id=frm name=frm method=post>
					<textarea style='display:none' name=rawxml></textarea>
					<p><input type=button value=Save onClick=\"runsubmit();\"> $switch
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
 				
 			} else if ( $emptyxml ) {
 				
				$maintext .= "<div class=adminpart>
 				<p>This XML does not (yet) have a text content. To edit the raw XML of the file, click  
 				<a href='index.php?action=rawedit&cid=$fileid&full=1'>here</a>.
 					<hr>";
 				
 			} else {
			
				$maintext .= "<div class=adminpart>
 				<p>This XML has not been tokenized yet, and only the text is shown below. To edit, click  
 				<a href='index.php?action=rawedit&cid=$fileid'>here</a>.
 				<br>If you wish to tokenize the XML and proceed to the tokenized edit mode, click
 				<a href='index.php?action=tokenize&id=$fileid&display=tok'>here</a></div>
 					<hr>";
 				
 				if ( $settings['xmlfile']['linebreaks'] && !strpos($editxml, "</p>") ) {
 					// Interpret linebreaks as <br/> - they will get interpreted in tokenization
 					$editxml = preg_replace("/\n/", "<br/>", $editxml);
 				};
 				
 			};
 		};

		$atthl = $_POST['atthl'] or $atthl = $_GET['atthl'];
		$hlcol = $_POST['hlcol'] or $hlcol = $_GET['hlcol'] or $hlcol = $settings['defaults']['highlight']['color'] or $hlcol = "#ffffaa"; 
		if ( preg_match("/^[0-9a-f]+$/", $hlcol) ) $hlcol = "#".$hlcol; 
		if ( $atthl ) {
			list ( $att, $val ) = explode ( ":", $atthl );
			$moreaction .= "\n";
			foreach ( $xml->xpath("//tok[@$att=\"$val\"]") as $hltok ) {
				$hlid = $hltok['id'];
				$moreactions .= "highlight('$hlid', '$hlcol'); ";
			};
			$moreaction .= "\n";
		};

		$hltit = $_POST['hltit'] or $hltit = $_GET['hltit'];
		if ( $hltit ) $pagenav .= "<p>{$hltit}<hr>";

		$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
		$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
		$jsontrans = array2json($settings['transliteration']);
					
		$highlights = $_GET['tid'] or $highlights = $_GET['jmp'] or $highlights = $_POST['jmp'];	

		// Load the tagset 
		require ( "$ttroot/common/Sources/tttags.php" );
		$tttags = new TTTAGS($tagsetfile, false);
		if ( $tttags->tagset['positions'] ) {
			$tmp = $tttags->xml->asXML();
			$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
			$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
		};

		$maintext .= "
			<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
			$pagenav
			<div id=mtxt>".$editxml."</div>
			<script language=Javascript src='$jsurl/getplaintext.js'></script>
			<script language=Javascript src='$jsurl/tokedit.js'></script>
			<script language=Javascript src='$jsurl/tokview.js'></script>
			<script>
				var username = '$username';
				var lang = '$lang';
				$settingsdefs;
				var transl = $jsontrans;
				var hlbar;
				var orgtoks = new Object();
				var attributelist = Array($attlisttxt);
				$attnamelist
				formify(); 
				var orgXML = document.getElementById('mtxt').innerHTML;
				var tid = '$fileid'; 
				var previd = '{$_GET['tid']}';
				$moreactions
				var jmps = '$highlights'; var jmpid;
				if ( jmps ) { 
					var jmpar = jmps.split(' ');
					for (var i = 0; i < jmpar.length; i++) {
						var jmpid = jmpar[i];
						highlight(jmpid, '$hlcol');
					};
					element = document.getElementById(jmpar[0])
					alignWithTop = true;
					if ( typeof(element) != null ) { element.scrollIntoView(alignWithTop); };
				};
				document.getElementById('jsoptions').style.display = 'block';
				document.getElementById('nojs').style.display = 'none';
			</script>
			";
	
		# This legend on the bottom only means something if colours are shown
		# This is treated much better in the colours on the buttons on top
// 		$sep = ""; foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
// 			$formcol = $item['color']; $val = $item['display'];
// 			if ( !$item['admin'] || $username )
// 				$maintext .= "$sep<span style='color: $formcol'>{%$val}</span> ";
// 				$sep = " &bull; ";
// 		};

		if ( $customcss ) {
			$maintext .= "<hr style='clear: both;'>
				<table><tr><td valign=top style='padding-right: 15px;'>{%Legenda}:<td>";
			$maintext .= "<p style='margin-top: 5px;'>$customcss</table>";
		};
		
		$sep = "<hr style='clear: both; margin-top: 10px;'><p>";
		if ( ( $settings['download']['admin'] != "1" && $settings['download']['disabled'] != "1" ) || $username ) {
			$maintext .= "$sep<a href='index.php?action=getxml&cid=$fileid'>{%Download XML}</a> &bull; ";
			$sep = "";
		};
		if ( $settings['download']['disabled'] != "1" ) 
			$maintext .= "$sep<a onClick='exporttxt();' style='cursor: pointer;'>{%Download current view as TXT}</a>
			";
		
		// Show s-attribute level views
		foreach ( $settings['xmlfile']['sattributes'] as $key => $item ) {	
			$lvl = $item['level'];	
			if ( $_GET['elm'] != $lvl && $key == "lb" ) {
				$lvltxt = $item['display'] or $lvltxt = "Manuscript Line";
				$maintext .= " &bull; <a href='index.php?action=lineview&cid=$fileid&pageid=$pageid'>{%{$lvltxt} view}</a>";
			} else if ( $_GET['elm'] != $lvl && strstr($editxml, "<$key ") ) {
				if ( !$_GET['id'] ) { $cidu = "&id=$fileid"; };
				$lvltxt = $item['display'] or $lvltxt = "Sentence";
				$maintext .= " &bull; <a href='{$_SERVER['REQUEST_URI']}$cidu&elm=$lvl'>{%{$lvltxt} view}</a>";
			}; 
		};
		if ( $_GET['elm'] ) {
			$maintext .= " &bull; <a href='index.php?action=$action&cid=$fileid&pageid={$_GET['pageid']}'>{%Text view}</a>";
		};
		
		if ( $settings['annotations'] ) {
			foreach ( $settings['annotations'] as $key => $val ) {
				if ( $val['type'] == "standoff" && file_exists("Annotations/{$key}_$xmlid.xml") &&  ( !$val['admin'] || $username ) ) {
					$maintext .= " &bull; <a href='index.php?action=annotation&annotation=$key&cid=$xmlid.xml'>{%{$val['display']}}</a>";
				} else if ( $val['type'] == "standoff" && !file_exists("Annotations/{$key}_$xmlid.xml") && $username ) {
					$maintext .= " &bull; <a href='index.php?action=annotation&act=edit&annotation=$key&cid=$xmlid.xml'>Create {%{$val['display']}}</a>";
				} else if ( $val['type'] == "psdx" && file_exists("Annotations/$xmlid.psdx") ) {
					$maintext .= " &bull; <a href='index.php?action=psdx&cid=$xmlid'>{%{$val['display']}}</a>";
				} else if ( $val['type'] == "morph" && strstr($editxml, "<morph") ) {
					$maintext .= " &bull; <a href='index.php?action=igt&cid=$xmlid'>{%{$val['display']}}</a>";
				};
			}; 
		};
				
		$maintext .= "<br>";

	};
	

	if ( $username ) {
		$maintext .= "<hr><div class=adminpart><h3>Admin options</h3>";
		
		if ( $settings['scripts'] ) {
	
			$maintext .= "
			<p>Custom actions:<ul>";
	
			foreach ( $settings['scripts'] as $id => $item ) {
				// See if thsi script is applicable
				if ( $item['recond'] && !preg_match("/{$item['recond']}/", $editxml ) ) continue;
				if ( $item['rerest'] && preg_match("/{$item['rerest']}/", $editxml ) ) continue;
				if ( $item['xpcond'] && !$xml->xpath($item['xpcond']) ) continue;
				if ( $item['xprest'] && $xml->xpath($item['xprest']) ) continue;
				if ( $item['type'] == "php" ) {
					$url = $item['action'];
					$url = str_replace("[id]", $fileid, $url );
					$url = str_replace("[fn]", $filename, $url );
					$maintext .= "<li><a href='$url'>{$item['display']}</a>";
				} else 
					$maintext .= "<li><a href='index.php?action=runscript&script=$id&file=$fileid'>{$item['display']}</a>";
			};
			$maintext .= "</ul>";
		
		};
		
		if ( file_exists("Resources/filelist.xml") ) {
			$fxml = getxmlrec("Resources/filelist.xml", $xmlid, "file");
			$frec = simplexml_load_string($fxml);

			if ( !$frec ) { 
				$maintext .= "<h3>XML File Repository - no record for $xmlid</h3>";
				$maintext .= "<p><ul><li><a href='index.php?action=filelist&act=edit&id=new&newid={$xmlid}'>Create file repository record</a></ul>";
			} else {
				$maintext .= "<h3>XML File Repository</h3>
				<table>";
				foreach ( $frec as $showf => $val ) {
					$showh = $settings['filelist']['fields'][$showf]['display'] or $showh = $showf;

					$maintext .= "<tr><th>$showh<td>$val";
				};
				$maintext .= "</table>";
				$maintext .= "<p><ul><li><a href='index.php?action=filelist&act=edit&id={$xmlid}'>Edit file repository data</a></ul>";
			};
		};		

		$maintext .= "<ul>";
		if ( glob("backups/$xmlid-*") ) { 
			$maintext .= "<li><a href='index.php?action=backups&cid=$fileid'>Recover a previous version of this file</a>
				<br> Last change to this file: <b>$fdate</b>";
		};
		
		if ( strstr($editxml, "<tok") ) {
			if ( $_GET['pageid'] ) $pnr = "&pageid=".$_GET['pageid'];
			else if ( $_GET['page'] ) $pnr = "&page=".$_GET['page'];
			$maintext .= "<li><a href='index.php?action=verticalize&act=define&cid=$fileid$pnr'>View verticalized version of this text</a>";
		};
		
		if ( $audiobit ) {
			$maintext .= "<li><a href='index.php?action=audioalign&cid=$fileid'>Edit audio alignment</a>";
		};
		
		if (is_array($filesources)) 
		foreach ( $filesources as $key => $val ) {
			$link = str_replace("[fn]", $fileid, $val[0]);
			
			$ln = $val[1];
			$maintext .= "<li><a href='$link'>$ln</a>";
		};
		
		if ( $settings['neotag'] && !strstr($editxml, "pos=") && strstr($editxml, "<tok") ) {
			$maintext .= "<li><a href='index.php?action=neotag&act=tag&pid=auto&cid=xmlfiles/$fileid'>(Pre)tag this text with POS (and lemma)</a>";
		};
		$maintext .= "</ul></div>";
	};


?>