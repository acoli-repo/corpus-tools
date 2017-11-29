<?php

	# Page-by-page transcription
	# (c) Maarten Janssen, 2017

	check_login();
	
	if ( !$_GET['cid'] ) $_GET['cid'] = $_POST['fileid'];	

	
	if ( $_GET['cid'] ) {
		require ("$ttroot/common/Sources/ttxml.php");
	
		$ttxml = new TTXML($_GET['cid'], false, "pagetrans");
		if ( !$ttxml->xml ) fatal("Could not load page-by-page XML file: {$_GET['cid']}");

		if ( !$ttxml->xml->xpath("//page") ) fatal("This is not a page-by-page edit XML file");
		$fileid = $ttxml->xmlid;
	};
	
	if ( $act == "save" ) {
	
		$pagexml = current($ttxml->xml->xpath("//page[@id='{$_POST['pageid']}']"));
		$pagexml[0] = $_POST['newcontent'];
		
		if ( $_POST['done'] ) {
			$pagexml['done'] = "1";
			if ( $_POST['newcontent'] == "" ) $pagexml['empty'] = "1";
		} else $pagejump = "&page={$_POST['pageid']}";
		
		$filename = "pagetrans/".$ttxml->filename;
		file_put_contents($filename, $ttxml->xml->asXML());

		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&cid=$ttxml->fileid$pagejump';</script>"; exit;
	
	} else if ( $act == "convert" && $ttxml->fileid ) {
	
		if ( $ttxml->xml->xpath("//text/page[not(@empty) and not(@done)]") ) {	
			$warning = "<p style='color: #cc0000; font-weight: bold;'>Not all your pages are marked as done yet!</p>";
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
			<input type=hidden name=fileid value='$ttxml->fileid'>
			<h2>Treatment of lines</h2>
			
			<p><input type=radio name=lines value='lb' checked> Treat each new line as an &lt;lb/&gt; (line beginning)
			<p><input type=radio name=lines value='plb'> Treat empty lines as new paragraphs and other line as an &lt;lb/&gt; (line beginning)
			<p><input type=radio name=lines value='p'> Treat empty lines as new paragraphs and ignore other lines
			<p><input type=radio name=lines value='none'> Ignore all lines

			<hr>
			<h2>Conversions of codes</h2>

			<p><input type=checkbox name=convert value='1' checked> Convert <a href='index.php?action=$action&act=conversions' target=help>hard to type characters</a>
			<br/>
			<p><input type=radio name=codes value='md' checked> Convert from <a href='http://teitok.corpuswiki.org/site/index.php?action=help&id=pagetrans' target=help>markdown-style codes</a> [del:this]
			<p><input type=radio name=codes value='xml'> Treat as XML (this may make your conversion fail if the resulting XML is invalid)
			<p><input type=radio name=codes value='none'> Keep as-is
			
			<hr>			
			<p><input type=submit action='index.php?action=$action&cid=$fileid&act=apply' method=post value='Convert'>
			<a href='index.php?action=$action&cid=$ttxml->fileid'>cancel</a>
			</form>";

	} else if ( $act == "apply" && $_POST['fileid'] ) {

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
			
			
			# Convert linebreaks
			if ( !$pagenode->getAttribute("empty") && $pagenode->textContent != "" ) {
				if ( $_POST['lines'] == "lb" ) {
					$pagebody = preg_replace("/^(<page[^>]+>)/", "\\1<lb/>", $pagebody);
					$pagebody = preg_replace("/(\&#13;|[\n\r])+/", "\n<lb/>", $pagebody);
					$pagebody = str_replace("|\n<lb/>", "<lb/>", $pagebody);
					$pagebody = str_replace("|\n", "", $pagebody);
				} else if ( $_POST['lines'] == "plb" ) {
					$pagebody = preg_replace("/^(<page[^>]+>)/", "\\1<p><lb/>", $pagebody);
					$pagebody = preg_replace("/(<\/page>)$/", "</p>\\1", $pagebody);
					# Normalize returns
					$pagebody = preg_replace("/\n\r/", "\n", $pagebody); $pagebody = preg_replace("/\r/", "\n", $pagebody);
					# Convert double lines to <p> breaks
					$pagebody = preg_replace("/\n\n+/", "</p><p><lb/>", $pagebody);
					$pagebody = preg_replace("/(&#13;|[\n\r])+/", "\n<lb/>", $pagebody);
					$pagebody = str_replace("|\n<lb/>", "<lb/>", $pagebody);
					$pagebody = str_replace("|\n", "", $pagebody);
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
			} else if ( $_POST['codes'] == "xml" ) {					
				# Convert xml tags
				$pagebody = str_replace("&lt;", "<", $pagebody);
				$pagebody = str_replace("&gt;", ">", $pagebody);
			};
			
			$sxe = simplexml_load_string($pagebody, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$sxe && $value ) {
				# This is not proper XML - try to repair
				# print "\n<p>Repairing XML - $toninsert";
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
		
		# Now change <page> into <pb/>
		$newxml = str_replace("<page ", "<pb ", $newxml);
		$newxml = preg_replace("/<pb([^>]+)(?<!\/)>/", "<pb\\1/>", $newxml);
		$newxml = str_replace("</page>", "", $newxml);

		$newxml = preg_replace("/\|\s*<pb/", "<pb", $newxml);
		
		rename("pagetrans/$ttxml->filename", "backups/$ttxml->fileid-pagetrans.xml");
		saveMyXML($newxml, $ttxml->fileid);
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
		
	
	} else if ( $act == "status" && $ttxml->xml ) {
	
		$maintext .= "<h1>Facsimile Page-by-Page Transcription (pre TEI)</h1>";
		# $maintext .= "<p style='color: #888888'>This page-by-page transcription tool is still in beta at this point - use with care, and please inform us about bugs or suggestions for improvements.</p><hr>";
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
			} else if ( $pagexml['done'] == "1" ) {
				$status = "done";
				$color = "#00bb00; font-weight: bold;";
			} else if ( $pagexml."" != "" ) {
				$status = "in progress";
				$color = "#666666";
			} else {
				$status = "to be done";
				$color = "#666666";
			};
			$cnt{$status}++;
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
		
		$pageid = $_GET['page'];
		if ( $_GET['page']) $pagexp = "//text/page[@id='$pageid']";
		else $pagexp = "//text/page[not(@empty) and not(@done)]";

		$pagexml = current($ttxml->xml->xpath($pagexp));
		if ( !$pagexml && !$_GET['page'] ) $pagexml = current($ttxml->xml->xpath("//page")); 
		
		if ( !$pagexml ) fatal ("Page not found: {$_GET['page']}");

		if ( !$ttxml->xml->xpath("//text/page[not(@empty) and not(@done)]") ) {
			$converttxt .= "All pages marked as done, click <a href='index.php?action=$action&cid=$fileid&act=convert'>here</a> to finish";
		} else {
			$noconvert = "- <a href='index.php?action=$action&cid=$fileid&act=convert'>Abandon page-by-page and convert to TEI/XML</a>";
			if ( $pagexml['done'] ) $converttxt .= "click <a href='index.php?action=$action&cid=$fileid'>here</a> to jump to the first non-finished page";
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

		$folionr = $pagexml['n'] or $folionr = $pagexml['id'];

		# Build the page navigation
		$maintext .= "<table style='width: 100%'><tr> 
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>{%Page} $folionr
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
						";
		
		if ( $pagexml['crop'] == "right"  ) 
			$crop = "width: 200%; float: right;";
		else if ( $pagexml['crop'] == "left"  ) 
			$crop = "width: 200%; float: left;";
		else 
			$crop = "width: 100%";
		$maintext .= "<p>
			<div id='buttons' style='padding: 2px; height: 20px; z-index: 200; left: 5px; top: 5px; width: 50%;'>
			<span onClick='togglefull();' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='fullscreen mode'>Fullscreen</span>
			<span onClick='togglezoom();' id='zoomset' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='show zoom window'>Zoom</span>
			<span onClick='fontchange(-1);' id='font1' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='decrease font size'>A-</span>
			<span onClick='fontchange(1);' id='font2' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='increase font size'>A+</span>
			<span onClick='toggleconv();' id='conv' style='cursor: pointer; background-color: #f2f2f2; border: 1px solid #777777; border-radius: 5px; padding: 3px;' title='convert symbols as you type'>$ → ¶</span>
			$converttxt
			</div>
			<div id=transtab style='background-color: white;'>
			<form action='index.php?action=$action&act=save&cid=$ttxml->xmlid' method=post>
			<input type=hidden name=pageid value=\"{$pagexml['id']}\">
			<div style='position: fixed; right: 5px; top: 5px; width: 300px; height: 300px; display: none; background-image: url(Facsimile/{$pagexml['facs']});' id='overlay'></div>
			<table style='width: 100%;'><tr>
			<td style='width: 50%'><div style='overflow: hidden;'><img id=facs src=\"Facsimile/{$pagexml['facs']}\" style=\"$crop\" onmousemove='zoomIn(event)' onmouseout='zoomOut();'/></div>
			<td style='width: 50%; vertical-align: top;'><textarea id='textfld' name=newcontent onkeyup='chareq(this);' style='padding: 5px; width: 100%; height: {$imgheight}px; border: none; font-size: 16px;' >$oldcontent</textarea></table>
			</div>
	
			<hr>
			<p><input type='submit' value='Save page'> <input type=checkbox name=done value=1> Mark page as done</form>

			$noconvert
			- <a href='http://teitok.corpuswiki.org/site/index.php?action=help&id=pagetrans' target=help>Help</a>
			- <a href='index.php?action=$action&cid=$ttxml->fileid&act=status'>Status</a>
			- <a href='index.php?action=$action&act=conversions' target=help>Special characters</a>";
				
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
			
	
	} else {
		# List the files in the pagetrans folder
		
		$maintext .= "<h1>Page-by-Page Transcription</h1>
		
			<p>Select a pre-TEI file from the list below to transcribe from Facsimile pages. Create a new page-by-page file useing the <a href='index.php?action=pdf2tei'>PDF to TEI</a> function
				<table>
					<tr><th>Filename<th>Progress<th>Transcriber";
			
		foreach ( scandir("pagetrans") as $file ) {
			if ( substr($file,0,1) != "." ) {
				$done = 0; $tot = 0;
				foreach ( explode("\n", shell_exec("grep '<page ' pagetrans/$file")) as $line ) {
					if ( strstr($line, 'done="1"') != false ) $done++;
					$tot++;
				};
				$resp = shell_exec("grep 'n=\"transcription\"' pagetrans/$file");
				$maintext .= "<tr><td><a href='index.php?action=$action&cid=$file'>$file</a>
					<td align=right>$done of $tot pages
					<td>{$resp}";
			};
		};
		$maintext .= "</table>
		<hr><p><a href='index.php?action=pdf2tei'>Create new page-by-page file</a>";
		
	};
	
	
?>