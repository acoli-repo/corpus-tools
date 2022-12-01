<?php

	# Page-by-page transcription
	# (c) Maarten Janssen, 2017

	check_login();
	
	if ( !$_GET['cid'] ) $_GET['cid'] = $_POST['fileid'];	

	if ( $settings['xmlfile']['basedirection'] ) $morestyle .= "direction: {$settings['xmlfile']['basedirection']}";

	if ( $_GET['cid'] ) {
		require ("$ttroot/common/Sources/ttxml.php");
	
		$ttxml = new TTXML($_GET['cid'], false, "pagetrans");
		if ( !$ttxml->xml ) fatal("Could not load page-by-page XML file: {$_GET['cid']}");

		$tmp = $ttxml->xml->xpath("//text"); $textnode = $tmp[0];
		if ( $textnode->asXML() == "<text/>" ) {
			# Completely empty - add a page
			$pagexml = $textnode->addChild("page");
			$pagexml["id"] = "page-1";
		};

		if ( !$ttxml->xml->xpath("//page") ) fatal("This is not a page-by-page edit XML file");
		$fileid = $ttxml->xmlid;
	};

	if ( $fileid && $username && !is_writable("$xmlfolder/$fileid.xml") ) {
		fatal ("The file $xmlfolder/$fileid.xml cannot be modified due to permission problems");
	};
	
	if ( $act == "save" ) {
	
		$pagexml = current($ttxml->xml->xpath("//page[@id='{$_POST['pageid']}']"));
		if ( $_POST['folionr'] ) {
			$pagexml['n'] = $_POST['folionr'];
		};
		if ( $_POST['tok'] ) {
			foreach ( $_POST['tok'] as $key => $val ) {
				$tokxml = current($pagexml->xpath(".//tok[@id='$key']"));
				if ( $tokxml ) $tokxml[0] = $val;
			};	
			foreach ( $_POST['bb'] as $key => $val ) {
				$linexml = current($pagexml->xpath(".//line[@id='$key']"));
				$linexml['bbox'] = $val;
			};
			foreach ( $_POST['status'] as $key => $val ) {
				$linexml = current($pagexml->xpath(".//line[@id='$key']"));
				$linexml['status'] = $val;
			};
		} else if ( $_POST['ta'] ) {
			foreach ( $_POST['ta'] as $key => $val ) {
				$linexml = current($pagexml->xpath(".//line[@id='$key']"));
				$linexml[0] = $val;
			};	
			foreach ( $_POST['bb'] as $key => $val ) {
				$linexml = current($pagexml->xpath(".//line[@id='$key']"));
				$linexml['bbox'] = $val;
			};
			foreach ( $_POST['status'] as $key => $val ) {
				$linexml = current($pagexml->xpath(".//line[@id='$key']"));
				$linexml['status'] = $val;
			};
		} else {
			$pagexml = current($ttxml->xml->xpath("//page[@id='{$_POST['pageid']}']"));
			$pagexml[0] = $_POST['newcontent'];
		};
			
		if ( $_POST['done'] ) {
			$pagexml['status'] = "2";
			if ( $_POST['newcontent'] == "" ) $pagexml['empty'] = "1";
		} else $pagejump = "&page={$_POST['pageid']}";
		
		$filename = "pagetrans/".$ttxml->filename;
		file_put_contents($filename, $ttxml->xml->asXML());

		if ( $_POST['tok'] ) {
			shell_exec("perl $ttroot/common/Scripts/bboxretok.pl $filename");
		};

		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&cid=$ttxml->fileid$pagejump';</script>"; exit;

	} else if ( $act == "index" ) {
	
		$maintext .= "<h2>Page Index</h2><ul>";
	
		foreach ( $ttxml->xml->xpath("//text/page") as $pag) {	
			$pageid = $pag['id'];
			$pagenr = $pag['n'] or $pagenr = "[$pageid]";
			$maintext .= "<li><a href='index.php?action=$action&cid=$ttxml->fileid&page=$pageid'>$pagenr</a>";
			$pid++;
		};
		$maintext .= "</ul><p><a href='index.php?action=$action&cid=$ttxml->fileid&act=insert&pageid=$pageid'>add page</a>";

	} else if ( $act == "convert" && $ttxml->fileid ) {
	
		if ( $ttxml->xml->xpath("//text/page[not(@empty) and not(@status=\"2\")]") ) {	
			$warning = "<p style='color: #cc0000; font-weight: bold;'>Not all your pages are marked as verified yet!</p>";
		};
	
		$maintext .= "<h1>Convert page-by-page to TEI/XML</h1>
		
			<p>Once a manuscript has been transcribed page-by-page, it should be converted to proper TEI/XML.
				Bear in mind that this process is irreversible (although backup is made) since especially the
				tokenization in TEITOK is imcompatible with page nodes in XML. Below you can customize the conversion
				process.$warning<hr>";
		
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		# Display the teiHeader data as a table
		$maintext .= $ttxml->tableheader(); 
			
		$maintext .= "<form action='index.php?action=$action&act=apply' method=post>
			<input type=hidden name=fileid value='$ttxml->fileid'>";
		
		if ( !$ttxml->xml->xpath("//line") ) $maintext .= "<h2>Treatment of lines</h2>
			
			<p><input type=radio name=lines value='lb' checked> Treat each new line as an &lt;lb/&gt; (line beginning)
			<p><input type=radio name=lines value='plb'> Treat empty lines as new paragraphs and other line as an &lt;lb/&gt; (line beginning)
			<p><input type=radio name=lines value='p'> Treat empty lines as new paragraphs and ignore other lines
			<p><input type=radio name=lines value='none'> Ignore all lines

			<hr>";
		$maintext .= "<h2>Conversions of codes</h2>

			<p><input type=checkbox name=convert value='1' checked> Convert <a href='index.php?action=$action&act=conversions' target=help>hard to type characters</a>
			<br/>
			<!-- <p><input type=radio name=codes value='md' > Convert from <a href='http://www.teitok.org/site/index.php?action=help&id=pagetrans' target=help>markdown-style codes</a> [del:this] -->
			<p><input type=radio name=codes value='xml' checked> Treat as XML (this may make your conversion fail if the resulting XML is invalid)
			<p><input type=radio name=codes value='none'> Keep as-is
			
			<hr>			
			<p><input type=submit action='index.php?action=$action&cid=$fileid&act=apply' method=post value='Convert'>
			<a href='index.php?action=$action&cid=$ttxml->fileid'>cancel</a>
			</form>";

	} else if ( $act == "apply" && $_POST['fileid'] ) {


		$date = date("Y-m-d"); 	
		$newrev = xpathnode($ttxml->xml, "//teiHeader/revisionDesc/change[@when='$date']");
		$newrev['who'] = $user['short'];
		$newrev[0] = "Converted from page-by-page transcription";

		$dom = dom_import_simplexml($ttxml->xml)->ownerDocument; 		
		$xpath = new DOMXpath($dom);
		
		
		foreach ( $xpath->query("//text/page") as $pagenode ) {
			$pagebody = $dom->saveXML($pagenode);
		
			# Convert document specific codes
			if ( $_POST['convert'] ) {
				foreach ( $settings['input']['replace'] as $key => $item ) {
					$pagebody = str_replace($key, $item['value'], $pagebody);
				};
			};
			
			# Remove the Facsimile from the image name
			$pagebody = preg_replace("/facs=\"Facsimile\//", "facs=\"", $pagebody);

			# Remove any <lineblock>
			$pagebody = preg_replace("/<\/?lineblock[^>]*>/", "", $pagebody);
			
			# Convert linebreaks
			if ( !$pagenode->getAttribute("empty") && $pagenode->textContent != "" ) {
				if ( $_POST['lines'] == "lb" ) {
					$pagebody = preg_replace("/^(<page[^>]+>)/", "\\1<lb/>", $pagebody);
					$pagebody = preg_replace("/(\&#13;|[\n\r])+/", "\n<lb/>", $pagebody);
				} else if ( $_POST['lines'] == "plb" ) {
					$pagebody = preg_replace("/^(<page[^>]+>)/", "\\1<p><lb/>", $pagebody);
					$pagebody = preg_replace("/(<\/page>)$/", "</p>\\1", $pagebody);
					# Normalize returns
					$pagebody = preg_replace("/\n\r/", "\n", $pagebody); $pagebody = preg_replace("/\r/", "\n", $pagebody);
					# Convert double lines to <p> breaks
					$pagebody = preg_replace("/\n\n+/", "</p><p><lb/>", $pagebody);
					$pagebody = preg_replace("/(&#13;|[\n\r])+/", "\n<lb/>", $pagebody);
				} else if ( $_POST['lines'] == "p" ) {
					$pagebody = preg_replace("/^(<page[^>]+>)/", "\\1<p>", $pagebody);
					$pagebody = preg_replace("/(<\/page>)$/", "</p>\\1", $pagebody);
					# Normalize returns
					$pagebody = preg_replace("/\n\r/", "\n", $pagebody); $pagebody = preg_replace("/\r/", "\n", $pagebody);
					# Convert double lines to <p> breaks
					$pagebody = preg_replace("/\n\n+/", "</p>\n<p>", $pagebody);
				};
			};
			
			# Convert codes
			if ( $_POST['codes'] == "md" ) {
				# some special codes
				$pagebody = preg_replace("/\[i:([^\]]*)\]/", "<hi rend=\"italics\">\\1</hi>", $pagebody);
				$pagebody = preg_replace("/\[b:([^\]]*)\]/", "<hi rend=\"bold\">\\1</hi>", $pagebody);
				$pagebody = preg_replace("/\[dc:([^\]]*)\]/", "<hi type=\"dropcap\">\\1</hi>", $pagebody);
				# [tag@feature=value:content]
				$pagebody = preg_replace("/\[([^\]]+)@([^\]]+)=([^\]]+):([^\]]*)\]/", "<\\1 \\2=\"\\3\">\\4</\\1>", $pagebody);
				# [tag:content]
				$pagebody = preg_replace("/\[([^\]]+):([^\]]*)\]/", "<\\1>\\2</\\1>", $pagebody);
				# $pagebody = preg_replace("/\[([^\]]+)\]/", "<\\1/>", $pagebody); # This is too scary
			} else if ( $_POST['codes'] == "xml" ) {					
				# Convert xml tags
				$pagebody = str_replace("&lt;", "<", $pagebody);
				$pagebody = str_replace("&gt;", ">", $pagebody);
			};
			# Remove any @status
			$pagebody = preg_replace("/ status=\"[^\"]*\"/", "", $pagebody);
			
			$sxe = simplexml_load_string($pagebody, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$sxe && $value ) {
				# This is not proper XML - try to repair
				$toinsert = preg_replace("/\&(?![a-z+];)/", "&amp;", $toinsert);
				$sxe = simplexml_load_string($pagebody, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);					
			};
			if ( !$sxe && $pagebody ) {
				print "\n<p>Cannot insert node, invalid XML: {".htmlentities($pagebody).'}'; exit;
			};
			$newelement = dom_import_simplexml($sxe);
			$newelement = $dom->importNode($newelement, true);
			$pagenode->parentNode->replaceChild($newelement, $pagenode);

		};
		$newxml = $dom->saveXML();
		
		# Now change <line> into <lb/>
		$newxml = str_replace("<line ", "\n<lb ", $newxml);
		$newxml = preg_replace("/<lb([^>]+)(?<!\/)> */", "<lb\\1/> ", $newxml);
		$newxml = str_replace("</line>", "", $newxml);

		# This should actally be done in the tokenization <= Why?
		$newxml = preg_replace("/\|\s*(<lb[^>]*>)\s*/", "\\1", $newxml);
		
		# Now change <page> into <pb/>
		$newxml = str_replace("<page ", "<pb ", $newxml);
		$newxml = preg_replace("/<pb([^>]+)(?<!\/)>/", "<pb\\1/>", $newxml);
		$newxml = str_replace("</page>", "", $newxml);

		# This should actally be done in the tokenization <= Why?
		$newxml = preg_replace("/\|\s*<pb/", "<pb", $newxml);
		
		$xmlfolder = "xmlfiles";
		saveMyXML($newxml, $ttxml->fileid);
		rename("pagetrans/$ttxml->filename", "backups/$ttxml->fileid-pagetrans.xml");
		print "<p>Page-by-page file has been converted to TEI/XML. Reloading to view mode.
			<script language=Javascript>top.location='index.php?action=file&cid=$fileid'</script>"; exit;

	} else if ( $act == "conversions" ) {
	
		$maintext .= "<h1>Hard to type characters</h1>
			<p>TEITOK provides the option to convert hard-to-type symbols on-the-fly, meaning you can define
				symbols that are easy to type, but not used in your corpus to function as stand-in for those 
				symbols you do need. For instance, mediaeval texts often use a long s (ſ) which is not easy to 
				type. If your corpus does not contain a German sharp s (ß), which on many keyboards you can type
				as alt-s, then you can use ß to mean ſ in your corpus - that is to say, have TEITOK convert every
				ß to ſ. In the page-by-page transcription, you can have those all be replaced when you convert your
				pre-TEI document to TEI, or you can have them be replaced as you type. Below is the list 
				of character conversionscurrently defined in this project.";
		if ( !$settings['input']['replace'] )  $maintext .= "<p><i>No conversions defined yet</i>";
		else {
			$maintext .= "<table>
			<tr><th>Source<th>Target";
			foreach ( $settings['input']['replace'] as $key => $item ) {
				$val = $item['value'];
				$chareqjs .= "$sep $key = $val"; 
				$charlist .= "ces['$key'] = '$val';";
				$sep = ",";
				$maintext .= "<tr><td>$key<td>{$item['value']}";
			};
			$maintext .= "</table>";
		};				
		
	
	} else if ( $act == "insert" && $ttxml->xml && $_GET['pageid'] ) {

		# Insert a new page
		$pageid = $_GET['pageid'];
		$pagexp = "//text/page[@id='$pageid']";
		$pagexml = current($ttxml->xml->xpath($pagexp));
	
		if ( !$pagexml ) fatal("Page not found: $pageid");
		
		$insert = new SimpleXMLElement("<page></page>");
		simplexml_insert_after($insert, $pagexml);

		$options = "";
		foreach ( $ttxml->xml->xpath("//text//page") as $page ) {
			$pagetxt = $page['n'] or $pagetxt = $page['id'];
			if ( $page == $pagexml )  $sel = "selected"; else $sel = "";	
			if ( $pagetxt ) $options .= "<option value='{$page['id']}'>$pagetxt</option>";
		}; 
		$maintext .= "<h1>Add Page</h1><h2>".$ttxml->title()."</h2>"; 
		# Display the teiHeader data as a table
		$maintext .= $ttxml->tableheader(); 
		$maintext .= "
				
				<form action='index.php?action=$action&act=addpage&cid=$ttxml->fileid' method=post>
				<img id=sim name=sim src='' style='float: right; width: 200px;' onClick=\"window.open(this.src, '_new');\">
				
				<p>Insert a new page <select name=beforeafter><option value='after'>after</option><option value='before'>before</option></select>
				page <select name=pageid>$options</select>
				
				<p>Facsimile image: <input name=facs size=60 onChange='showimg(this);'>
				
				<p>Page number:  <input name=n size=10>
				
				<p><input type=submit value=Insert>
				<a href='index.php?action=$action&cid=$ttxml->fileid&page=$pageid'>cancel</a></form>
				<script language=Javascript>
					function showimg(data) {
						document.getElementById('sim').src = data.value;
					};
				</script>
				";

		
	} else if ( $act == "addpage" && $ttxml->xml && $_POST['pageid'] ) {

		# Insert a new page
		$pageid = $_POST['pageid'];
		$pagexp = "//text/page[@id='$pageid']";
		$pagexml = current($ttxml->xml->xpath($pagexp));
	
		if ( !$pagexml ) fatal("Page not found: $pageid");
		
		$insert = new SimpleXMLElement("<page></page>");
		if ( $_POST['facs'] ) $insert['facs'] = $_POST['facs'];
		if ( $_POST['n'] ) $insert['n'] = $_POST['n'];
		simplexml_insert_after($insert, $pagexml, $_POST['beforeafter']);

		$pn = 1; // Renumber
		foreach ( $ttxml->xml->xpath("//page") as $page ) {
			$page['id'] = "page-".$pn++;
			if ( $page == $pagexml ) {
				$newid = "page-".$pn;
			};
		};

		file_put_contents("pagetrans/$ttxml->filename", $ttxml->asXML());
		print "<p>Your page has been inserted. Reloading to $newid
			<script language=Javascript>top.location='index.php?action=$action&cid={$_GET['cid']}&page=$newid';</script>";
		exit;
		
	} else if ( $act == "status" && $ttxml->xml ) {
	
		$maintext .= "<h1>Facsimile Page-by-Page Transcription (pre TEI)</h1>";
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 

		# Display the teiHeader data as a table
		$maintext .= $ttxml->tableheader(); 

		if ( $resp = current($ttxml->xml->xpath('//resp[@n="transcription"]')) && $resp != $user['realname'] ) {
			$maintext .= "<p><b>Transcription</b>: $resp<hr>";
		};

		$maintext .= "<h2>Status of each page</h2><table>";
		foreach ( $ttxml->xml->xpath("//page") as $pagexml ) {
			if ( $pagexml['empty'] == "1" ) {
				$status = "empty";
				$color = "#00bb00";
			} else if ( $pagexml['status'] == "2" ) {
				$status = "verified";
				$color = "#00bb00; font-weight: bold;";
			} else if ( $pagexml."" != "" || $pagexml['status'] == "2" ) {
				$status = "partially verified";
				$color = "#666600";
			} else if ( $pagexml."" != "" ) {
				$status = "to be verified";
				$color = "#666666";
			} else {
				$status = "to be done";
				$color = "#666666";
			};
			$cnt[$status]++;
			$maintext .= "<tr><th><a href='index.php?action=$action&cid=$ttxml->fileid&page={$pagexml['id']}'>{$pagexml['id']}</a><td>{$pagexml['n']}<td style='color: $color;'>$status";
		};
		$maintext .= "</table>
		
		<h2>Overview</h2>
		<table>";
		foreach ( $cnt as $key => $val ) {
			$maintext .= "<tr><th>$key<td align=right>$val";
		};
		$maintext .= "</table>";
	
	} else if ( $ttxml->xml ) {

		$maintext .= "<h1>Facsimile Page-by-Page Transcription (pre TEI)</h1>";
		# $maintext .= "<p style='color: #888888'>This page-by-page transcription tool is still in beta at this point - use with care, and please inform us about bugs or suggestions for improvements.</p><hr>";
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		# Display the teiHeader data as a table
		$maintext .= $ttxml->tableheader(); 

		if ( $resp = current($ttxml->xml->xpath('//resp[@n="transcription"]')) && $resp != $user['realname'] ) {
			$maintext .= "<p><b>Transcription</b>: $resp<hr>";
		};
		
		$pageid = $_GET['page'] or $pageid = $_GET['page'] or $pageid = $_GET['pid'];
		if ( $_GET['page']) $pagexp = "//text/page[@id='$pageid']";
		else $pagexp = "//text/page[not(@empty) and not(@status=\"2\")]";

		$pagexml = current($ttxml->xml->xpath($pagexp));
		if ( !$pagexml && !$_GET['page'] ) $pagexml = current($ttxml->xml->xpath("//page")); 
		
		if ( !$pagexml ) fatal ("Page not found: {$_GET['page']}");

		if ( !$ttxml->xml->xpath("//text/page[not(@empty) and not(@status=\"2\")]") ) {
			$converttxt .= "All pages marked as verified, click <a href='index.php?action=$action&cid=$fileid&act=convert'>here</a> to finish";
		} else {
			if ( $pagexml['status'] != "2" ) $noconvert .= "<input type=checkbox name=done value=1> Mark page as verified ";
			$noconvert .= "- <a href='index.php?action=$action&cid=$fileid&act=convert'>convert to TEI/XML</a>";
			if ( $pagexml['status'] == "2" ) $converttxt .= "click <a href='index.php?action=$action&cid=$fileid'>here</a> to jump to the first non-finished page";
		};

		$imgheight = 600;
		# This should become innerXML
		
		$oldcontent = preg_replace("/^<page[^>]*>|<\/page>$/", "", $pagexml->asXML());

		$next = current($pagexml->xpath("following::page"));
		if ( $next['n'] ) { 
			$nxp = " and following::page[@id='{$next['id']}']"; 
			$nnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&page={$next['id']}'>> {$next['n']}</a> ";
		} else	if ( $next['id'] ) { 
			$nxp = " and following::page[@id='{$next['id']}']"; 
			$nnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&page={$next['id']}'>> [{$next['id']}]</a> ";
		};
		
		if ( !$onlylb ) $lbxpath = "//lb[preceding::page[@id='{$pagexml['id']}']$nxp] | //l[preceding::page[@id='{$pagexml['id']}']$nxp]"; 
		else $lbxpath = "//lb[preceding::page[@id='{$pagexml['id']}']$nxp]"; 
	
		$prev = array_pop($pagexml->xpath("preceding::page"));
		if ( $prev['n'] ) { 
			$bnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&page={$prev['id']}'>{$prev['n']} <</a> ";
		} else	if ( $prev['id'] ) { 
			$bnav = "<a href='index.php?action=$action&act=$act&cid=$fileid&page={$prev['id']}'>[{$prev['id']}] <</a> ";
		};

		$folionr = $pagexml['n'];

		# Build the page navigation
		$maintext .= "
				<form action='index.php?action=$action&act=save&cid=$ttxml->xmlid' method=post>
				<table style='width: 100%'><tr> 
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>{%Page}/{%Folio} <input size=5 name=folionr value=\"$folionr\"> ({$pagexml['id']})
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
				<input type=hidden name=pageid value=\"{$pagexml['id']}\">
						";
						
		// Add a session logout tester
		$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";
		
		if ( $pagexml->xpath(".//line") ) {
			$imgsrc = $pagexml['facs'];
			$imgsrc = preg_replace("/^Facsimile\//", "" , $imgsrc );
			if ( !strstr($imgsrc, "http") ) $imgsrc = "Facsimile/$imgsrc";
			
			$maintext .= "<img src='$imgsrc' style='display: none;' id='facs'/>";
			$maintext .= "<table style='width: 100%;' id='lines'>";
			foreach ( $pagexml->xpath(".//line") as $line ) {
				$nr++;
		
				$linenr = $line['n'] or $linenr = $line['id'];
								
				$bb = explode ( " ", $line['bbox'] );
				$divheight = $bb[3] - $bb[1];

				$statbox = "<input type=hidden name=\"status[{$line['id']}]\" value=\"{$line['status']}\" id=\"linestat-{$line['id']}\"><span class=linestat  id=\"statbox-{$line['id']}\" tid=\"{$line['id']}\" style='cursor: pointer; width: 15px; height: 100%; background-color: #dddddd;' title='status: unverified' onClick=\"changestatus(this)\">&nbsp;</span> ";
				if ( $line->xpath(".//tok") ) {
					# Tokenize lines (probably via OCR)
					$linetxt = $statbox;
					foreach ( $line->xpath(".//tok") as $token ) {
						$toktxt = $token."";
						$boxlen = max(strlen($toktxt)+0, 2);
						$toktxt = str_replace('"', "&quot;", $toktxt);
						$linetxt .= "<input style='font-size: 16px; height: 30px; margin-bottom: 20px; margin-top: 5px;' name=\"tok[{$token['id']}]\" id='tok-{$token['id']}' size=$boxlen value=\"$toktxt\" onkeyup='chareq(this);'> ";
					};

					$helptxt = "Use || to split a token, and |~ at the end to merge it with the next";

					// Add the data of the line
					$maintext .= "\n<tr><td>
					<div bbox='{$line['bbox']}' class='resize' id='reg_{$line['id']}' tid='{$line['id']}' style='width: 100%; height: {$divheight}px; background-image: url(\"$imgsrc\"); background-size: cover;'></div>
					$debug
					<span tid='{$line['id']}'>$linetxt</span>
					<input type=hidden name=\"bb[{$line['id']}]\" id='bb-{$line['id']}' style='width: 100%;' value=\"{$line['bbox']}\"/>
					";
				} else {
					$linetxt = $line->asXML();
					$linetxt = preg_replace("/^<line[^>]*>|<\/line>$/", "", $linetxt);

					$helptxt = "Drag the corners of the facsimile cut-out to adjust the line";

					// Add the data of the line
					$maintext .= "\n<tr><td style='padding-bottom: 20px;'>
					<div bbox='{$line['bbox']}'  rotate='{$line['rotate']}' class='resize' id='reg_{$line['id']}' tid='{$line['id']}' style='width: 100%; height: {$divheight}px; background-image: url(\"$imgsrc\"); background-size: cover;'></div>
					<span style='margin-bottom: 20px;'>$statbox</span> <textarea style='font-size: 16px; width: 96%; height: 30px; margin-top: 5px; $morestyle' name='ta[{$line['id']}]' id='line-{$line['id']}' onkeyup='chareq(this); checkxml(this);' >$linetxt</textarea>
					<input type=hidden name=\"bb[{$line['id']}]\" id='bb-{$line['id']}' style='width: 100%;' value=\"{$line['bbox']}\"/>
					";

				};
						
			};
			$maintext .= "</table>

			<script language=Javascript>
				function showlines () {
					for ( var i=0; i<linedivs.length; i++ ) {
						var linediv = linedivs[i];
						var bbox = linediv.getAttribute('bbox').split(' ');
						// Never scale more than 50% up
						var imgscale  = Math.min(1.5, linediv.offsetWidth/(bbox[2]-bbox[0]));

						var biw = facsimg.naturalWidth*imgscale;
						var bih = biw*(facsimg.naturalHeight/facsimg.naturalWidth);
						var bix = bbox[0]*imgscale;
						var biy = bbox[1]*imgscale;

						linediv.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
						linediv.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
						linediv.style['background-size'] = biw+'px '+bih+'px';
						linediv.style['background-position'] = '-'+bix+'px -'+biy+'px';
						linediv.setAttribute('orgbpos', '-'+bix+'px -'+biy+'px');
	
					};			
				};

				var facsimg = document.getElementById('facs');
				var linedivs = document.getElementById('lines').getElementsByTagName('div');
				
				facsimg.onload = function () {
					// Wait until image is loaded before resizing the background
					redraw();
					showlines();
				};
				
			</script>
			<script language=Javascript src='https://code.interactjs.io/v1.3.0/interact.min.js'></script>
			<script language=Javascript>



			interact('.resize')
			  .resizable({
				// resize from all edges and corners
				edges: { left: true, right: true, bottom: true, top: true },

			  })
			  .on('resizemove', function (event) {
				var target = event.target,
					x = (parseFloat(target.getAttribute('data-x')) || 0),
					y = (parseFloat(target.getAttribute('data-y')) || 0);

				// update the element's style
				target.style.width  = event.rect.width + 'px';
				target.style.height = event.rect.height + 'px';

				// translate when resizing from top or left edges
				x += event.deltaRect.left;
				y += event.deltaRect.top;
	
				target.style.webkitTransform = target.style.transform =
					'translate(' + x + 'px,' + y + 'px)';
	
				var bsize = target.style['background-size'].replace(/px/g,'').split(' ');
				var imgscale = bsize[0]/document.getElementById('facs').naturalWidth;

				bpos = target.getAttribute('orgbpos').replace(/px/g,'').split(' ');
				bpos[0] = bpos[0]*1 - x*1;
				bpos[1] = bpos[1]*1 - y*1;
				target.style['margin-bottom'] = y+'px';
				target.style['margin-top'] = (0-y)+'px';
				target.style['background-position'] = bpos[0]+'px '+bpos[1]+'px';
				var nl = (0-bpos[0])/imgscale;
				var nt = (0-bpos[1])/imgscale;
				var nr = nl + target.offsetWidth/imgscale;
				var nb = nt + target.offsetHeight/imgscale;
				var newbbox = nl+' '+nt+' '+nr+' '+nb;
				document.getElementById('bb-'+target.getAttribute('tid')).value=newbbox;

				target.setAttribute('data-x', x);
				target.setAttribute('data-y', y);

			  });

			</script>	
			<p><span  style='color: #888888;'>$helptxt</span>
			<span style='display: inline; float: right;'>
				<span onClick='fontchange(-1);' id='font1' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='decrease font size'>A-</span>
				<span onClick='fontchange(1);' id='font2' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='increase font size'>A+</span>
				<span onClick='toggleconv();' id='conv' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='convert symbols as you type'>$ → ¶</span>
			</span>
		";
		} else {	

			// If the page has no FACS, assign it a FACS and reload
			if ( !$pagexml['facs']  ) {
				$pagenr = 1;
				foreach ( $ttxml->xml->xpath("//page") as $i => $ptmp ) {
					if ( $ptmp == $pagexml ) {
						$pagenr = $i+1;
					};
				};
				$pagexml['facs'] = $ttxml->xmlid."_$pagenr.jpg";
				if ( !$pagexml['id'] ) $pagexml['id'] = "page-$pagenr";
				file_put_contents("pagetrans/$ttxml->xmlid.xml", $ttxml->xml->asXML());
				print "<p>Assigned FACS automatically to the page - reloading
					<script language=Javascript>location.reload();</script>"; exit;
			};

			$imgsrc = $pagexml['facs'];
			$imgsrc = preg_replace("/^Facsimile\//", "" , $imgsrc );
			if ( !strstr($imgsrc, "http") ) $imgsrc = "Facsimile/$imgsrc";
				
			if ( $pagexml['crop'] == "right"  ) 
				$crop = "width: 200%; float: right;";
			else if ( $pagexml['crop'] == "left"  ) 
				$crop = "width: 200%; float: left;";
			else 
				$crop = "width: 100%";
			
			if ( !strstr("http", $pagexml['facs']) && !file_exists("Facsimile/{$pagexml['facs']}") ) {
				# TODO: create an upload button to upload the facs
				
				if ( $settings['files']['facs']['folder'] == "Facsimile" ) {
					$imgfld = "<h2>Facsimile Image missing</h2>
						<p>Please upload a facsimile image ({$pagexml['facs']})</p>
						</form>
						<p><form action='index.php?action=upload&act=save' method=post enctype=\"multipart/form-data\">
						<input type=hidden name=type value='$type'>
						<p>Add new file:
							<input type=file name=upfile accept=\"$accept\">
							<input name=filename type=hidden value=\"{$pagexml['facs']}\">
							<input name=type type=hidden value=\"facs\">
							<input name=goon type=hidden value=\"index.php?action=$action&cid={$_GET['cid']}&pageid={$pagexml['id']}\">
							<input type=submit value=Save name=submit>
						</form> ";				
				} else {
					$imgfld = "<h2>Facsimile Image missing</h2>
						<p class=wrong>You should upload an image called {$pagexml['facs']} (or change the filename in the XML),
						but your settings do not allow Facsimile image uploads. ";
					if ( $user['permissions'] == "admin" ) $imgfld .= "Please add the option to upload facsimile images in the settings, by adding the following to the files section of the settings:
							    <br><br>&lt;item key=\"facs\" display=\"Facsimile images\" folder=\"Facsimile\" extension=\"*.jpg\" description=\"Facsimile images corresponding to pages in the corpus\"/&gt;";
					else $imgfld .= "Please ask the administrator of the corpus to modify the settings.";
				};
			} else {
				$imgfld = "<img id=facs src=\"$imgsrc\" style=\"$crop\" onmousemove='zoomIn(event)' onmouseout='zoomOut();'/>";
			};
			
				
			$maintext .= "<p>
				<div id='buttons' style='padding: 2px; height: 20px; z-index: 200; left: 5px; top: 5px; width: 50%;'>
				<span onClick='togglefull();' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='fullscreen mode'>Fullscreen</span>
				<span onClick='togglezoom();' id='zoomset' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='show zoom window'>Zoom</span>
				<span onClick='fontchange(-1);' id='font1' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='decrease font size'>A-</span>
				<span onClick='fontchange(1);' id='font2' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='increase font size'>A+</span>
				<span onClick='toggleconv();' id='conv' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='convert symbols as you type'>$ → ¶</span>
				$converttxt
				</div>
				<div id=transtab style='background-color: white; width: 100%;'>
				<div style='position: fixed; right: 5px; top: 5px; width: 300px; height: 300px; display: none; background-image: url(Facsimile/{$pagexml['facs']});' id='overlay'></div>
				<table style='width: 100%; table-layout: fixed;'><tr>
				<td style='width: 50%;  vertical-align: top;'><div style='overflow: hidden;'>$imgfld</div>
				<td style='width: 50%; vertical-align: top;'><textarea id='textfld' name=newcontent onkeyup='chareq(this);' style='padding: 5px; width: 100%; height: {$imgheight}px; border: none; font-size: 16px;' >$oldcontent</textarea></table>
				</div>";
						
			};


			foreach ( $settings['input']['replace'] as $key => $item ) {
				$val = $item['value'];
				$chareqjs .= "$sep $key = $val"; 
				$charlist .= "ces['$key'] = '$val';";
				$sep = ",";
			};
			$maintext .= "<script language=Javascript>
				var ces = {};
				$charlist
				</script>";
			$maintext .= "<script language=Javascript src='$jsurl/pagetrans.js'></script>";
		
			$maintext .="
				<hr>
				<p><input type='submit' value='Save page'> 

				$noconvert $converttxt
				- <a href='http://teitok.corpuswiki.org/site/index.php?action=help&id=pagetrans' target=help>Help</a>
				- <a href='index.php?action=$action&cid=$ttxml->fileid&act=status'>Status</a>
				- <a href='index.php?action=$action&act=conversions' target=help>Special characters</a>
				- <a href='index.php?action=regionedit&cid=$ttxml->filename&pageid={$pagexml['id']}'>Edit line regions</a>
				- <a href='index.php?action=$action&act=insert&cid=$ttxml->filename&pageid={$pagexml['id']}'>Add page</a>
				</form>
				";
			$maintext .= "<script language=Javascript>
				var facsimg = document.getElementById('facs');
				
				facsimg.onload = function () {
					redraw();
					showlines();
				};
			</script>";
	
	} else {
		# List the files in the pagetrans folder
		
		$maintext .= "<h1>Page-by-Page Transcription</h1>
		
			<p>Select a pre-TEI file from the list below to transcribe from Facsimile pages. Create a new page-by-page file useing the <a href='index.php?action=pdf2tei'>PDF to TEI</a> function
				<table>
					<tr><th>Filename<th>Progress<th>Transcriber";
			
		foreach ( scandir("pagetrans") as $file ) {
			if ( substr($file,0,1) != "." ) {
				$done = 0; $tot = 0; $resp = "";

				foreach ( explode("\n", shell_exec("grep '<page ' pagetrans/$file")) as $line ) {
					if ( strstr($line, 'status="2"') != false ) $done++;
					if ( strstr($line, 'page') != false ) $tot++;
				};
				$tmp = shell_exec("grep 'n=\"transcription\"' pagetrans/$file");
				if ( preg_match("/<resp[^>]*>(.*?)<\/resp>/", $tmp, $matches) ) $resp = $matches[1];
				$maintext .= "<tr><td><a href='index.php?action=$action&cid=$file'>$file</a>
					<td align=right>$done of $tot pages
					<td>{$resp}";
			};
		};
		$maintext .= "</table>
		<hr><p><a href='index.php?action=pdf2tei'>Create new page-by-page file</a>";
		
	};

	function simplexml_insert_after(SimpleXMLElement $insert, SimpleXMLElement $target, $beforeafter = "after" )
	{
		$target_dom = dom_import_simplexml($target);
		$insert_dom = $target_dom->ownerDocument->importNode(dom_import_simplexml($insert), true);
		if ( $beforeafter == "before" ) {
			return $target_dom->parentNode->insertBefore($insert_dom, $target_dom);
		} else if ($target_dom->nextSibling) {
			return $target_dom->parentNode->insertBefore($insert_dom, $target_dom->nextSibling);
		} else {
			return $target_dom->parentNode->appendChild($insert_dom);
		}
	}	
	
?>