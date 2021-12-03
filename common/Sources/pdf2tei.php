<?php

	# Module to create a TEI/XML file from a PDF
	# (c) Maarten Janssen, 2017
	
	check_login();

	// Check for pdfimages
	$pdfimg = findapp("pdfimages");

	if ( $_POST['xmlid'] ) {
		set_time_limit(300);
		
		$fileid = $_POST['xmlid'];
		$fileid = preg_replace("/\.xml$/", "", $fileid);

		$logfile = fopen("tmp/$fileid.create.log", 'w') or die('Cannot create log file');
		fwrite($logfile, "Creating a file $fileid.xml\n");
		
		# Handle the PDF document
		$target_file = "pdf/$fileid.pdf";
		if (  $_POST['source'] == "file" ) {
			check_folder("pdf");
			if ( !move_uploaded_file($_FILES["pdffile"]["tmp_name"], $target_file) ) {
				fatal ("File upload failed");
			}
			fwrite($logfile, "Using uploaded PDF $target_file\n");
		} else if ( $_POST['source'] == "url" ) {
			
			$pdfurl = $_POST['pdfurl'];
			if ( strstr($pdfurl,  ".pdf") == false ) fatal ("Only PDF files can be downloaded");
			downloadFile($target_file, $pdfurl);
			if ( !file_exists($target_file) ) {
				fatal ("File download failed");
			};
			fwrite($logfile, "Using downloaded PDF $target_file from $pdfurl\n");
		};

		if ( $_POST['postprocess'] == "page" ) {
			$editaction = "pagetrans";
			$savefolder = "pagetrans";
			check_folder("pagetrans");
			fwrite($logfile, "Creating a page-by-page file in pagetrans\n");
		} else {
			$editaction = "file";
			$savefolder = "xmlfiles";
			fwrite($logfile, "Creating a TEI/XML file in xmlfiles\n");
		};
				 
		# Create an XML document
		# Then, deal with the teiHeader or template
		if ( $_POST['header'] == "template" || $_POST['withtemplate'] ) {
			$xmltemplate = $_POST['template'] or $xmltemplate = $_POST['withtemplate'];
			$file = file_get_contents("Resources/$xmltemplate"); 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };
			fwrite($logfile, "Incorporating header from template: $xmltemplate\n");
		} else if ( $_POST['header'] == "tei" ) {
			$file = $_POST['tei']; 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse $xmltemplate<hr>"; print $file; exit; };			
			fwrite($logfile, "Incorporating header from posted TEI/XML text\n");
		} else if ( $_POST['header'] == "existing" ) {
			$file = file_get_contents("xmlfiles/{$_POST['fromfile']}"); 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse xmlfiles/{$_POST['fromfile']}<hr>"; print $file; exit; };			
			if ( !$_POST['keeptext'] ) {
				$result = current($xml->xpath("//text")); 
				$result[0] = "";
			};
			fwrite($logfile, "Incorporating header from existing file: $file\n");
		} else {
			$file = "<TEI>
<teiHeader/>
<text/>
</TEI>";
			fwrite($logfile, "Generating empty header\n");
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
		$node[0] = trim($user['fullname']);

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
		$node['when'] = date("Y-m-d");
		fwrite($logfile, "Added creation metadata to teiHeader\n");
		
		while ( file_exists("$savefolder/$fileid$ext.xml" ) ) {
			$ext = "_".$cnt++;
		};
		file_put_contents("$savefolder/$fileid.xml", $dom->saveXML());
		
		
		if ( $_POST['postprocess'] == "page" ) $editaction = "pagetrans";
		else $editaction = "file";
		
		$editurl = "index.php?action=$editaction&cid=$fileid";

		if ( $_POST['pagetype'] == "gs" ) {
			$ghostscript = 1; $_POST['pagetype'] = 1;
		};

		# Wait for a couple of seconds to avoid file conflicts
		sleep(2);

		$offset = $_POST['offset'] or $offset = 0;
		$pagtype = $_POST['pagtype'];
		# Run the pdf2tei.pl script in the background
		if ( $pagtype ) {
			$cmd = "perl $ttroot/common/Scripts/pdf2tei.pl --parse={$_POST['postprocess']} --retok --pagtype='$pagtype' --offset=$offset --input=$fileid.pdf > /dev/null &";
			fwrite($logfile, "Running post-command:\n$cmd\n");
			fclose($logfile);

			$start = shell_exec($cmd); $bg = 1;

			print "<p>New XML file has been created. Reloading to progress check
				<script language=Javascript>top.location='index.php?action=$action&act=log&cid=$fileid&bg=$bg&na=$savefolder'</script>"; exit;
		} else {
			print "<p>New XML file has been created. Reloading to edit mode.
				<script language=Javascript>top.location='index.php?action=pagetrans&cid=$fileid'</script>"; exit;
		};
					
	} else if ( $act == "log" ) {
	
		$maintext .= "<h1>Manuscript PDF to TEI</h1>
			<h2>Progress report</h2>";

		if ( $_GET['na'] == "pagetrans" ) $newaction = "pagetrans"; else $newaction = "file";

		$log = file_get_contents("tmp/{$_GET['cid']}.create.log");

		if ( $log == "" ) {
			$maintext .= "<p>The creation of your file seems not to have started properly.";
		} else if ( !strstr($log, "(aborting)") && !strstr($log, "DONE") ) {
			$maintext .= "<script type=\"text/javascript\">
					setTimeout(function () { 
					  location.reload();
					}, 2 * 1000);
				</script>";
			$maintext .= "<p>The creation of your file is running in the background. You can see in the log file below. This page will reload until the process finishes.";
		} else if ( !strstr($log, "DONE") ) {
			$maintext .= "<p>The creation of your file seems to have gone wrong. You can see in the log file below where it failed.";
		} else {
			$maintext .= "<p>The creation of your file is finished, you can see
				the creation log below, and you can open it <a href='index.php?action=$newaction&cid={$_GET['cid']}'>here</a>";
			if ( !$_GET['stay'] ) {
				print "<p>New XML file has been created. Reloading to edit mode.
					<script language=Javascript>top.location='index.php?action=$newaction&cid={$_GET['cid']}'</script>"; exit;
			};
		};
				
		$maintext .= "<hr><pre>$log</pre>";
		
		
	} else {
		
		$maintext .= "<h1>Manuscript PDF to TEI</h1>
		
		<p>Many manuscripts are available as PDF files, or collections of image files. This module will
			help you create a TEI/XML file from such files. Depending on the type of manuscripts, the process 
			can either help you transcribe the text by hand, or attempt to use OCR techniques to read
			the text automatically. (<a href='http://teitok.corpuswiki.org/site/index.php?action=pdf2tei-details' target=teitok>more info</a>)
			
		<hr>
		
		<form action=\"index.php?action=$action\" id=pdfform name=pdfform method=\"post\" enctype=\"multipart/form-data\" onSubmit='return checkform();'>
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
			function checkform() {
				if ( document.forms['pdfform']['xmlid'].value == '' ) {
					alert('Please provide an XML ID for this file');
					return false;
				};
				return true;
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
		<p><input type=radio name=header value=url> Download metadata form URL : <input name='metaurl' size=70>
		<p>Type of metadata document: <select name=metatype><option value=mard>MARC</option></select>
 		-->";
		# None
		$maintext .= "<p><input checked type=radio name=header value='empty' onChange='metachoose(this);'> Leave empty";
 		
 		
		$maintext .= "<hr><h2>Content</h2>";
		
		if ( $pdfimg ) { // Only show the PDF parse options if we have 
			$maintext .= "
				<h3>PDF Document</h3>
		
			<p>
				For security, all images are uploaded as PDF - to upload images, please first combine them into a PDF, which 
				you can do with various programs or sites such as <a href='http://jpg2pdf.com/' target=_new>jpg2pdf</a>.
				Uploading or downloading PDF files can take a considerable amount of time, so wait for the browser to finish...
		
			<p><input type=radio name=source value=\"file\"> Upload a PDF file <input type='file' name=\"pdffile\" accept=\"application/pdf\">
			<p><input type=radio name=source value=url> Download PDF form URL : <input name='pdfurl'  size=70>
		
			<p>Type of PDF document: 
				<select name=pagtype>
					<option value=1>image-based, 1 folio per page</option>
					<option value=2>image-based, 2 folios per page</option>
					<option value='gs'>text-based, render with ghostscript</option>
				</select>
			<p>Number of initial pages to skip: <select name=offset><option value='0' selected>0</option><option value='1'>1</option><option value='2'>2</option><option value='3'>3</option><option value='4'>4</option></select>
			";
		} else {
			$maintext .= "<p class=adminpart>Parsing the PDF into images is done using <tt>pdfimages</tt> which is not installed on the server.";
		};
		
		if ( $tesseractworking ) { # TODO: Make tesseract work
			$tessopt = "<p><input type=radio name=postprocess value=ocr> Process with Tesseract OCR tool";
		};
			
		$maintext .= "<hr>
		<h3>Post-processing</h3>

		<p><input type=radio name=postprocess value=none> Create an empty TEI/XML files with page breaks
		<p><input type=radio name=postprocess value=page checked> Create a file for page-by-page transcription
		$tessopt
		
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