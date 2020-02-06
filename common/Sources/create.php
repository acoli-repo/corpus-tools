<?php
	// Script to create a new XML file
	// (c) Maarten Janssen, 2015

	check_login();
	
	# Check whether we are allowed to write to xmlfiles
	if ( !is_writable("xmlfiles") ) {
		fatal ("The folder xmlfiles cannot be written by the system. Please contact the server administrator.");
	};
		
	if ( $_POST['fname'] ) {

		# First, determine the filename
		$cardid = $_POST['fname'];
		$cardid = preg_replace("/[+ '\"]+/", "_", $cardid); # Remove problematic characters from the name
		$filename = $cardid;
		$cardid = str_replace(".xml", "", $cardid);
		if ( substr($filename, -4) != ".xml" ) { # Add .xml to the end of the filename
			$filename .= ".xml";
		};
		if ( $_POST['folder'] ) $filename = "{$_POST['folder']}/$filename"; 
		if ( file_exists("$xmlfolder/$filename") ) {
			fatal("File $filename already exists");
		}; 
	
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
		} else if ( $_POST['header'] == "audio" ) {
			$soundtype = $_POST['soundtype'] or $soundtype = "wav";
			$file = "<TEI>
<teiHeader>
<recordingStmt>
<recording type=\"audio\">
<media mimeType=\"audio/$soundtype\" url=\"Audio/$cardid.$soundtype\"> <desc></desc>
</media>
</recording>
</recordingStmt>
</teiHeader> 
<text xml:space=\"preserve\"></text>
</TEI>";
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
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
<text xml:space=\"preserve\"/>
</TEI>";
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		};

		$dom = dom_import_simplexml($xml)->ownerDocument; #->ownerDocument		
		
		if ( $_POST['header'] == "teiheader" ) {
			# Fill in from XPath commands
			foreach ( $_POST['values'] as $key => $value ) {
				$xquery = $_POST['queries'][$key];
				# print "\n<p>$xquery => $value ";
			
				# If there is a new value to save, make sure the node exists (or create it)
				if ( $value ) { $dom = createnode($dom, $xquery); };
			
				$xpath = new DOMXpath($dom);
				$result = $xpath->query($xquery); 
				if ( $result->length == 1 )
				foreach ( $result as $node ) {
					if ( $node->nodeType == XML_ATTRIBUTE_NODE ) {
						$node->parentNode->setAttribute($node->nodeName, $value);
					} else {
						$tmp = $node->ownerDocument->saveXML($node);
						if ( preg_match("/^(<[^>]+>)(.*?)(<\/[^>]+>)$/si", $tmp, $matches ) ) { 
							$toinsert = $matches[1].$value.$matches[3]; 
						} else if ( preg_match("/^<(([a-z]+)[^>]*?)\/>$/si", $tmp, $matches ) ) { 
							$toinsert = '<'.$matches[1].'>'.$value.'</'.$matches[2].'>'; 
							# print "\nAbout to insert: ".htmlentities($toinsert);
						} else { print "\n<p>Cannot insert node, does not have start and end tag: {".htmlentities($tmp).'}'; exit; };
						$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
						if ( !$sxe && $value ) {
							# This is not proper XML - try to repair
							# print "\n<p>Repairing XML - $toninsert";
							$toinsert = preg_replace("/\&(?![a-z+];)/", "&amp;", $toinsert);
							$sxe = simplexml_load_string($toinsert, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);					
						};
						if ( !$sxe && $value ) {
							print "\n<p>Cannot insert node, invalid XML: {".htmlentities($toinsert).'}'; exit;
						};
						$newelement = dom_import_simplexml($sxe);
						$newelement = $dom->importNode($newelement, true);
						$node->parentNode->replaceChild($newelement, $node);
					};
				};
			};
		};
			
		# Finally, deal with the content	
		if ( $_POST['body'] == "shorthand" ) {
			$text = $_POST['shorthand'];
			$text = preg_replace("/\n\r?[\n\r]+/", "</p>\n\n<p>", $text);
			# $text = preg_replace("/<([^>]+)>/", "<ex>\\1</ex>", $text);
			# $text = preg_replace("/\(([^\)]+)\)/", "<del>\\1</del>", $text);
			$newtext = "<text xml:space=\"preserve\">\n<p>$text</p>\n</text>";
		} else if ( $_POST['body'] == "html" ) {
			$text = $_POST['html'];
			$text = preg_replace("/\n\r?[\n\r]+/", "</p>\n\n<p>", $text);
			# TODO: convert shorthand
			$newtext = "<text xml:space=\"preserve\">\n$text\n</text>";
		};


		if ( $newtext ) { 
			
			# Hack converting all HTML entities to Unicode, minus those that make XML invalid... (prob not perfect)
			$newtext = str_replace('&lt;', 'x<x', $newtext);
			$newtext = str_replace('&gt;', 'x>x', $newtext);
			$newtext = str_replace('&amp;', 'x&x', $newtext);
			$newtext = decode_entities_full($newtext);
			$newtext = str_replace( 'x<x', '&lt;', $newtext);
			$newtext = str_replace( 'x>x', '&gt;', $newtext);
			$newtext = str_replace( 'x&x', '&amp;', $newtext);
			
			# TODO: This needs to be done differently since it can only handle ISO characters like this
			# $newtext = utf8_encode(utf8_decode($newtext)); # Correcting UTF8 errors 0xa0			
			$newtext = mb_convert_encoding($newtext, "UTF-8");

			libxml_use_internal_errors(true);
			$newentry = simplexml_load_string($newtext, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

			if ($newentry === false) {
				echo "Failed loading XML\n";
				foreach(libxml_get_errors() as $error) {
					echo "\t", $error->message;
				}
				print "<hr>".htmlentities($newtext);
			}			
			
			$tmp = dom_import_simplexml($newentry);
			$xpath = new DOMXPath($dom);
			$newelement = dom_import_simplexml($newentry);
			$newelement = $dom->importNode($newelement, true);
			$element = $xpath->query("//text")->item(0);
			$element->parentNode->replaceChild($newelement, $element); 
		};			
				
		
		# Now add a revision statement
		$dom = dom_import_simplexml($xml)->ownerDocument; #->ownerDocument		
		$xp = "/TEI/teiHeader/revisionDesc/change";
		createnode($dom, $xp);
		$node = current($xml->xpath($xp));
		$node[0] = "XML file created";
		$node['when'] = date("Y-m-d");
		$node['who'] = $user['short'];

		$newfile = $xml->asXML();
		
		# Make sure the ID is in the <text> element
		if ( !preg_match("/<text[^>]+id=/", $newfile) ) {
			$newfile = preg_replace("/<text(?=[ >])/", "<text id=\"$fileid\"", $newfile);
		};
		
		saveMyXML($newfile, $filename);
		print "<p>New XML file has been created. Reloading to edit mode.
			<script language=Javascript>top.location='index.php?action=file&cid=$filename&display=shand'</script>"; exit;

	} else {
	
		$maintext .= "<h1>Create New XML File</h1>
					<form action='index.php?action=$action' method=post  name=frm id=frm enctype=\"multipart/form-data\">

		<h2>XML Filename</h2>
		<p>XML id (filename): <input name=fname size=30>
		";
		
		# Choose a folder
		foreach ( scandir("xmlfiles") as $dir ) {
			if ( is_dir($dir)  && substr($dir,0,1) != '.') $dirlist .= "<option value='$dir'>$dir</option>";
		}; 
		if ( $dirlist ) {
			$maintext .= "<p>Choose directory: <select name='dir'><option value=''>[root]</option>$dirlist</select>";
		};

			

		$maintext .= "\n\n<hr/><h2>Initial Metadata</h2>
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

		# None
		$maintext .= "<p><input checked type=radio name=header value='empty' onChange='metachoose(this);'> Leave empty";

		# Use a template
		if ( $settings['xmltemplates'] ) {
			while ( list ( $key, $item ) = each ( $settings['xmltemplates'] ) ) {
				$templatelist .= "<option value='$key'>{$item['display']}</option>";
			};
			$maintext .= "<p><input type=radio name=header value='template' onChange='metachoose(this);'> Use a template
				<div id='template' style='display: none; padding-left: 40px;'><p>Choose template file: <select name=template>$templatelist</select></div>";
		};
		
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

		# Paste an XML file
		$maintext .= "<p><input type=radio name=header value='tei' onChange='metachoose(this);'> Paste a TEI/XML file (will keep text content as well)
			<div id='tei' style='display: none; padding-left: 40px;'>
				<p>Paste TEI/XML file below: <textarea name=tei style='width: 100%; height: 300px;'></textarea>"; 
		$maintext .= "</div>";


	
		# Copy from an existing XML file
		$maintext .= "<p><input type=radio name=header value='existing' onChange='metachoose(this);'> Use an existing XML file
			<div id='existing' style='display: none; padding-left: 40px;'>
				<p>Enter filename: <input name=fromfile size=50> <input type=checkbox value=1 name=keeptext> Keep text content as well"; 
		$maintext .= "</div>";

		# If we have sound files defined, also allow starting from a sound-file
		if ( $settings['files']['audio'] ) {
			$maintext .= "<p><input type=radio name=header value='audio' onChange='metachoose(this);'> Transcribe from a sound file";
		};


		$maintext .= "\n\n<hr/><h2>Initial Content</h2>";

		# None
		$maintext .= "<p><input checked type=radio name=body value='nobody' onChange='bodychoose(this);'> Leave empty";

		# Paste as HTML
		$maintext .= "<p><input type=radio name=body value='html' onChange='bodychoose(this);'> Create as WYSIWYG (or paste from Word, etc.)
			<div id='html' style='display: none; padding-left: 40px;'>
				<p><i>Here you can write or paste rich text, which will then be converted to TEI/XML. This conversion keeps only limited typesetting information, and can only be used for the initial creation of the XML file; after the file is in TEI/XML this editor will no longer work.</i></p>";
		$maintext .= "<script type=\"text/javascript\" src=\"$tinymceurl\"></script>";
		$maintext .= '<script type="text/javascript">
			tinymce.init({
				selector: "textarea.wysiwyg",

  menu: {
    edit: {title: "Edit", items: "undo redo | cut copy paste pastetext | searchreplace | selectall"},
    insert: {title: "Insert", items: "charmap pagebreak"},
    format: {title: "Format", items: "bold italic | formats | removeformat | code"},
    view: {title: "View", items: "fullscreen restoredraft"},
  },
  				convert_urls: false,
				plugins: [
					 "lists charmap searchreplace",
					 "paste pagebreak code fullscreen autosave"
			   ],
			    extended_valid_elements: "supplied,add,unclear,ex,hi[rend],s,b,i,b/strong,i/em",
			    custom_elements: "~supplied,~add,~unclear,~ex,~hi[rend]",
			    valid_children : "+p[supplied|add|unclear|ex|hi]",
			    paste_word_valid_elements: "b,i,b/strong,i/em,h1,h2,p",
				content_css: "Resources/xmlstyles.css", 
				toolbar: "undo redo | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent ", 
			    width: "100%",
			    height: 400,
			    pagebreak_separator : "<pb/>",

    style_formats: [
    { title: "Expanded text", inline: "ex" },
    { title: "Deleted text", inline: "del" },
    { title: "Added text", inline: "add" },
    { title: "Unclear text", inline: "unclear" },
    { title: "Supplied text", inline: "supplied" },
  ],
  formats: {
    bold: { inline: "b" },
    italic: { inline: "i" },  
    strikethrough: { title: "deleted", inline: "del" },
  }			    
			 });
			</script>';
			
		$maintext .= "
			<textarea name=html class=wysiwyg>$content</textarea>
			";
		$maintext .= "</div>";

		# Post as shorthand
		$maintext .= "<p><input type=radio name=body value='shorthand' onChange='bodychoose(this);'> Create from plain text
			<div id='shorthand' style='display: none; padding-left: 40px;'>
				<p>Paste text - double lines breaks will convert to paragraphs
				<textarea name=shorthand style='height: 300px; width: 100%'></textarea>"; # , and code can be used for (delete) and &lt;expand&gt;: 
		$maintext .= "</div>";

		# Convert from Word
// 		$maintext .= "<p><input type=radio name=body value='oxgarage' onChange='metachoose(this);'> Convert from Word
// 			<div id='oxgarage' style='display: none; padding-left: 40px;'>
// 				<p>Select .docx file: <input type=file name=wordfile>";
// 		$maintext .= "</div>";
	
	
		$maintext .= "\n\n<hr/><p><input type=submit value='Create XML File' onClick=\"return runsubmit();\">
			 - instead of the methods here, you can also create a new XML <a href='index.php?action=pdf2tei'>starting from a PDF document or Facsimile images</a>
			</form>
			<script language=Javascript>
			function runsubmit() {
				if ( document.frm.fname.value == '' ) { alert('Please provide a valid XML id!'); return false; };
				document.frm.submit();
			};
			</script>";
			
		if ( !file_exists("Resources/teiHeader-edit.tpl") ) $warnings .= "<li>You do not have a teiHeader template defined for editing; using such a template allows you to easily edit the metadata in an HTML form. You can create an edit template <a href='index.php?action=headermake'>here</a>";
		if ( !$settings['xmltemplates'] ) $warnings .= "<li>You do not have an XML template defined; using such a template allows you to have your teiHeader pre-filled with data about concerning project, institute, etc. You can create an XML template <a href='index.php?action=templatemake'>here</a>";
		if ( $warnings ) $maintext .= "<hr><h2>Provide more options</h2><ul>$warnings</ul>";
		
	};


	/**
	 * Helper function for drupal_html_to_text().
	 *
	 * Calls helper function for HTML 4 entity decoding.
	 * Per: http://www.lazycat.org/software/html_entity_decode_full.phps
	 */
	function decode_entities_full($string, $quotes = ENT_COMPAT, $charset = 'UTF-8') {
	  return html_entity_decode(preg_replace_callback('/&([a-zA-Z][a-zA-Z0-9]+);/', 'convert_entity', $string), $quotes, $charset); 
	}

	/**
	 * Helper function for decode_entities_full().
	 *
	 * This contains the full HTML 4 Recommendation listing of entities, so the default to discard  
	 * entities not in the table is generally good. Pass false to the second argument to return 
	 * the faulty entity unmodified, if you're ill or something.
	 * Per: http://www.lazycat.org/software/html_entity_decode_full.phps
	 */
	function convert_entity($matches, $destroy = true) {
	  static $table = array('quot' => '&#34;','amp' => '&#38;','lt' => '&#60;','gt' => '&#62;','OElig' => '&#338;','oelig' => '&#339;','Scaron' => '&#352;','scaron' => '&#353;','Yuml' => '&#376;','circ' => '&#710;','tilde' => '&#732;','ensp' => '&#8194;','emsp' => '&#8195;','thinsp' => '&#8201;','zwnj' => '&#8204;','zwj' => '&#8205;','lrm' => '&#8206;','rlm' => '&#8207;','ndash' => '&#8211;','mdash' => '&#8212;','lsquo' => '&#8216;','rsquo' => '&#8217;','sbquo' => '&#8218;','ldquo' => '&#8220;','rdquo' => '&#8221;','bdquo' => '&#8222;','dagger' => '&#8224;','Dagger' => '&#8225;','permil' => '&#8240;','lsaquo' => '&#8249;','rsaquo' => '&#8250;','euro' => '&#8364;','fnof' => '&#402;','Alpha' => '&#913;','Beta' => '&#914;','Gamma' => '&#915;','Delta' => '&#916;','Epsilon' => '&#917;','Zeta' => '&#918;','Eta' => '&#919;','Theta' => '&#920;','Iota' => '&#921;','Kappa' => '&#922;','Lambda' => '&#923;','Mu' => '&#924;','Nu' => '&#925;','Xi' => '&#926;','Omicron' => '&#927;','Pi' => '&#928;','Rho' => '&#929;','Sigma' => '&#931;','Tau' => '&#932;','Upsilon' => '&#933;','Phi' => '&#934;','Chi' => '&#935;','Psi' => '&#936;','Omega' => '&#937;','alpha' => '&#945;','beta' => '&#946;','gamma' => '&#947;','delta' => '&#948;','epsilon' => '&#949;','zeta' => '&#950;','eta' => '&#951;','theta' => '&#952;','iota' => '&#953;','kappa' => '&#954;','lambda' => '&#955;','mu' => '&#956;','nu' => '&#957;','xi' => '&#958;','omicron' => '&#959;','pi' => '&#960;','rho' => '&#961;','sigmaf' => '&#962;','sigma' => '&#963;','tau' => '&#964;','upsilon' => '&#965;','phi' => '&#966;','chi' => '&#967;','psi' => '&#968;','omega' => '&#969;','thetasym' => '&#977;','upsih' => '&#978;','piv' => '&#982;','bull' => '&#8226;','hellip' => '&#8230;','prime' => '&#8242;','Prime' => '&#8243;','oline' => '&#8254;','frasl' => '&#8260;','weierp' => '&#8472;','image' => '&#8465;','real' => '&#8476;','trade' => '&#8482;','alefsym' => '&#8501;','larr' => '&#8592;','uarr' => '&#8593;','rarr' => '&#8594;','darr' => '&#8595;','harr' => '&#8596;','crarr' => '&#8629;','lArr' => '&#8656;','uArr' => '&#8657;','rArr' => '&#8658;','dArr' => '&#8659;','hArr' => '&#8660;','forall' => '&#8704;','part' => '&#8706;','exist' => '&#8707;','empty' => '&#8709;','nabla' => '&#8711;','isin' => '&#8712;','notin' => '&#8713;','ni' => '&#8715;','prod' => '&#8719;','sum' => '&#8721;','minus' => '&#8722;','lowast' => '&#8727;','radic' => '&#8730;','prop' => '&#8733;','infin' => '&#8734;','ang' => '&#8736;','and' => '&#8743;','or' => '&#8744;','cap' => '&#8745;','cup' => '&#8746;','int' => '&#8747;','there4' => '&#8756;','sim' => '&#8764;','cong' => '&#8773;','asymp' => '&#8776;','ne' => '&#8800;','equiv' => '&#8801;','le' => '&#8804;','ge' => '&#8805;','sub' => '&#8834;','sup' => '&#8835;','nsub' => '&#8836;','sube' => '&#8838;','supe' => '&#8839;','oplus' => '&#8853;','otimes' => '&#8855;','perp' => '&#8869;','sdot' => '&#8901;','lceil' => '&#8968;','rceil' => '&#8969;','lfloor' => '&#8970;','rfloor' => '&#8971;','lang' => '&#9001;','rang' => '&#9002;','loz' => '&#9674;','spades' => '&#9824;','clubs' => '&#9827;','hearts' => '&#9829;','diams' => '&#9830;','nbsp' => '&#160;','iexcl' => '&#161;','cent' => '&#162;','pound' => '&#163;','curren' => '&#164;','yen' => '&#165;','brvbar' => '&#166;','sect' => '&#167;','uml' => '&#168;','copy' => '&#169;','ordf' => '&#170;','laquo' => '&#171;','not' => '&#172;','shy' => '&#173;','reg' => '&#174;','macr' => '&#175;','deg' => '&#176;','plusmn' => '&#177;','sup2' => '&#178;','sup3' => '&#179;','acute' => '&#180;','micro' => '&#181;','para' => '&#182;','middot' => '&#183;','cedil' => '&#184;','sup1' => '&#185;','ordm' => '&#186;','raquo' => '&#187;','frac14' => '&#188;','frac12' => '&#189;','frac34' => '&#190;','iquest' => '&#191;','Agrave' => '&#192;','Aacute' => '&#193;','Acirc' => '&#194;','Atilde' => '&#195;','Auml' => '&#196;','Aring' => '&#197;','AElig' => '&#198;','Ccedil' => '&#199;','Egrave' => '&#200;','Eacute' => '&#201;','Ecirc' => '&#202;','Euml' => '&#203;','Igrave' => '&#204;','Iacute' => '&#205;','Icirc' => '&#206;','Iuml' => '&#207;','ETH' => '&#208;','Ntilde' => '&#209;','Ograve' => '&#210;','Oacute' => '&#211;','Ocirc' => '&#212;','Otilde' => '&#213;','Ouml' => '&#214;','times' => '&#215;','Oslash' => '&#216;','Ugrave' => '&#217;','Uacute' => '&#218;','Ucirc' => '&#219;','Uuml' => '&#220;','Yacute' => '&#221;','THORN' => '&#222;','szlig' => '&#223;','agrave' => '&#224;','aacute' => '&#225;','acirc' => '&#226;','atilde' => '&#227;','auml' => '&#228;','aring' => '&#229;','aelig' => '&#230;','ccedil' => '&#231;','egrave' => '&#232;','eacute' => '&#233;','ecirc' => '&#234;','euml' => '&#235;','igrave' => '&#236;','iacute' => '&#237;','icirc' => '&#238;','iuml' => '&#239;','eth' => '&#240;','ntilde' => '&#241;','ograve' => '&#242;','oacute' => '&#243;','ocirc' => '&#244;','otilde' => '&#245;','ouml' => '&#246;','divide' => '&#247;','oslash' => '&#248;','ugrave' => '&#249;','uacute' => '&#250;','ucirc' => '&#251;','uuml' => '&#252;','yacute' => '&#253;','thorn' => '&#254;','yuml' => '&#255;'
						   );
	  if (isset($table[$matches[1]])) return $table[$matches[1]];
	  // else 
	  return $destroy ? '' : $matches[0];
	}


?>