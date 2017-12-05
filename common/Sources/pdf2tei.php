<?php

	# Module to create a TEI/XML file from a PDF
	# (c) Maarten Janssen, 2017
	
	check_login();

	if ( $_POST['xmlid'] ) {
		set_time_limit(300);
		
		$fileid = $_POST['xmlid'];
	
		# Handle the PDF document
		$target_file = "pdf/$fileid.pdf";
		if (  $_POST['source'] == "file" ) {
			if ( !file_exists("pdf") ) mkdir ("pdf");
			if ( !move_uploaded_file($_FILES["pdffile"]["tmp_name"], $target_file) ) {
				fatal ("File upload failed");
			}
		} else if ( $_POST['source'] == "url" ) {
			
			$pdfurl = $_POST['pdfurl'];
			if ( strstr($pdfurl,  ".pdf") == false ) fatal ("Only PDF files can be downloaded");
			downloadFile($target_file, $pdfurl);
			if ( !file_exists($target_file) ) {
				fatal ("File download failed");
			};
		};

		if ( $_POST['postprocess'] == "page" ) {
			$editaction = "pagetrans";
			$savefolder = "pagetrans";
			if ( !is_dir("pagetrans") ) mkdir("pagetrans");
		} else {
			$editaction = "file";
			$savefolder = "xmlfiles";
		};
				 
		# Create an XML document
		# Then, deal with the teiHeader or template
		if ( $_POST['header'] == "template" || $_POST['withtemplate'] ) {
			$xmltemplate = $_POST['template'] or $xmltemplate = $_POST['withtemplate'];
			$file = file_get_contents("Resources/$xmltemplate"); 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };			
		} else if ( $_POST['header'] == "tei" ) {
			$file = $_POST['tei']; 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };			
		} else if ( $_POST['header'] == "existing" ) {
			$file = file_get_contents("xmlfiles/{$_POST['fromfile']}"); 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse xmlfiles/{$_POST['fromfile']}<hr>"; print $file; exit; };			
			if ( !$_POST['keeptext'] ) {
				$result = current($xml->xpath("//text")); 
				$result[0] = "";
			};
		} else {
			$file = "<TEI>
<teiHeader/>
<text/>
</TEI>";
		};
		$xml = simplexml_load_string($file, NULL);
		$dom = dom_import_simplexml($xml)->ownerDocument; #->ownerDocument		
		$dom->formatOutput = true;
		
		# Add the respst and the orgfile (and url) in the XML
		$xp = '/TEI/teiHeader/fileDesc/titleStmt/respStmt/resp';
		createnode($dom, $xp);
		$node = current($xml->xpath($xp));
		$node['n'] = "transcription";
		$node['id'] = $user['short'];
		$node[0] = $user['fullname'];

		$xp = "/TEI/teiHeader/notesStmt/note[@n='orgfile']";
		createnode($dom, $xp);
		$node = current($xml->xpath($xp));
		$node[0] = "$fileid.pdf";
		$node['folder'] = "pdf";
		if ( $pdfurl ) {
			$node['url'] = $pdfurl;
		};
		
		$xp = "/TEI/teiHeader/revisionDesc/change";
		createnode($dom, $xp);
		$node = current($xml->xpath($xp));
		$node[0] = "XML file created from PDF";
		$node['when'] = date("Y-M-d");
		
		while ( file_exists("$savefolder/$fileid$ext.xml" ) ) {
			$ext = "_".$cnt++;
		};
		file_put_contents("$savefolder/$fileid.xml", $dom->saveXML());
		
		
		if ( $_POST['postprocess'] == "page" ) $editaction = "pagetrans";
		else $editaction = "file";
		
		$editurl = "index.php?action=$editaction&cid=$fileid";

		if ( $_POST['postprocess'] == "page" && !$ghostscript ) {
			# Run the pdf2tei.pl script
			$cmd = "perl ../common/Scripts/pdf2tei.pl --parse={$_POST['postprocess']} --pagtype={$_POST['pagtype']} --offset={$_POST['offset']} --input=$fileid.pdf";
			$done = shell_exec($cmd);

			print "<p>New XML file has been created. Reloading to edit mode.
				<script language=Javascript>top.location='index.php?action=pagetrans&cid=$fileid'</script>"; exit;
		} else {
			# Run the pdf2tei.pl script
			$cmd = "perl ../common/Scripts/pdf2tei.pl --parse={$_POST['postprocess']} --pagtype={$_POST['pagtype']} --input=$fileid.pdf > /dev/null &";
			$start = shell_exec($cmd);

			$maintext .= "<h1>File being created</h1>
			<p>$cmd</p>
				<p>Your XML file ($savefolder/$fileid.xml) is being created, and getting filled with image files. Depending of the size of the
				PDF file, this might take a while. Once it is done, you can edit the 
				XML file <a href='$editurl'>here</a>";
		};
			
	} else {
		
		$maintext .= "<h1>Manuscript PDF to TEI</h1>
		
		<p>Many manuscripts are available as PDF files, or collections of image files. This module will
			help you create a TEI/XML file from such files. Depending on the type of manuscripts, the process 
			can either help you transcribe the text by hand, or attempt to use OCR techniques to read
			the text automatically. (<a href='http://teitok.corpuswiki.org/site/index.php?action=pdf2tei-details' target=teitok>more info</a>)
			
		<hr>
		
		<form action=\"index.php?action=$action\" method=\"post\" enctype=\"multipart/form-data\">
		<h2>XML Document</h2>
		
		<p>Provide an ID for the TEI document to be created: <input name=xmlid size=40>

		";

		$maintext .= "\n\n<hr/><h2>Metadata</h2>
			<script language=Javascript>
			metas = Array ('empty','existing', 'template','teiheader','tei');
			function metachoose(e) {
				var fld = e.value;
				for ( a in metas ) {
					if ( document.getElementById(metas[a]) ) { document.getElementById(metas[a]).style.display = 'none'; };
				};
				if ( document.getElementById(fld) ) { document.getElementById(fld).style.display = 'block'; };
			};
			bodies = Array ('nobody','html','shorthand','oxgarage');
			function bodychoose(e) {
				var fld = e.value;
				for ( a in bodies ) {
					if ( document.getElementById(bodies[a]) ) { document.getElementById(bodies[a]).style.display = 'none'; };
				};
				if ( document.getElementById(fld) ) { document.getElementById(fld).style.display = 'block'; };
			};
			</script>
			";
 
		# Fill in from teiHeader
		if ( file_exists("Resources/teiHeader-edit.tpl") ) {
			$text = file_get_contents("Resources/teiHeader-edit.tpl");
			preg_match_all ( "/\{#([^\}]+)\}/", $text, $matches );		

			foreach ( $matches[0] as $key => $match ) {

				$from = preg_quote($match, '/'); 

				$xquery = $matches[1][$key];
				$xquery = str_replace("'", '&#039;', $xquery);
						
				$rowcnt = min(8,ceil(strlen($xval)/80));
				$to = "<textarea name=\"values[$key]\" cols='80' rows='$rowcnt'></textarea>";
				$to .= "<input type=hidden name='queries[$key]' value='$xquery'>";
				$text = preg_replace("/$from/", "$to", $text);
			};
			if ( $settings['xmltemplates'] ) {
				while ( list ( $key, $item ) = each ( $settings['xmltemplates'] ) ) {
					$templatelist .= "<option value='$key'>{$item['display']}</option>";
				};
				$text .= "<p>Also use template file: <select name=withtemplate><option value=''>[none]</option>$templatelist</select></p>";
			};
			
			$maintext .= "<p><input type=radio name=header value='teiheader' onChange='metachoose(this);'> Use teiHeader-edit
				<div id='teiheader' style='display: none; padding-left: 40px;'>$text</div>";
		};

		$maintext .= "<!--
		<p><input type=radio name=header value=file> Upload a metadata file <input type='file' name='metafile' filetype='pdf'>
		<p><input type=radio name=header value=url> Download metadata form URL : <input name='metaurl'>
		<p>Type of metadata document: <select name=metatype><option value=mard>MARC</option></select>
 		-->";
		# None
		$maintext .= "<p><input checked type=radio name=header value='empty' onChange='metachoose(this);'> Leave empty";
 		
 		
		$maintext .= "<hr><h2>Content</h2>
			<h3>PDF Document</h3>
		
		<p>Uploading or downloading PDF files might take a considerable amount of time, so wait for the browser to finish...
		
		<p><input type=radio name=source value=\"file\"> Upload a PDF file <input type='file' name=\"pdffile\" accept=\"application/pdf\">
		<p><input type=radio name=source value=url> Download PDF form URL : <input name='pdfurl'>
		
		<p>Type of PDF document: <select name=pagtype><option value=1>1 folio per page</option><option value=2>2 folios per page</option></select>
		<p>Number of initial pages to skip: <select name=offset><option value='0'>0</option><option value='1' selected>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option></select>
		";
		
		$maintext .= "<hr>
		<h3>Post-processing</h3>

		<p><input type=radio name=postprocess value=page checked> Transcribe page-by-page in the interface
		<p><input type=radio name=postprocess value=none> Only create an empty XML files with page breaks
		<p><input type=radio name=postprocess value=ocr> Process with Tesseract OCR tool
		<!-- <p><input type=radio name=postprocess value=line> Transcribe line-by-line in the interface -->
		
		<hr>
		<p><input type=submit value='Start processing'>
		</form>
		";
		
	};



	function downloadFile($path, $url) {
		$newfname = $path;
		$file = fopen ($url, 'rb');
		if ($file) {
			$newf = fopen ($newfname, 'wb');
			if ($newf) {
				while(!feof($file)) {
					fwrite($newf, fread($file, 1024 * 8), 1024 * 8);
				}
			}
		}
		if ($file) {
			fclose($file);
		}
		if ($newf) {
			fclose($newf);
		}
	};


?>