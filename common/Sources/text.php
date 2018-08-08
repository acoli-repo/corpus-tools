<?php
	// Text view of an XML file
	// Default view on any XML file
	// (c) Maarten Janssen, 2015
	
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
		
	if ( !$fileid ) { 
		fatal ( "No XML file selected." );  
	};
	
	# Determine the file date
	$tmp = filemtime("$xmlfolder/$fileid");
	$fdate = strftime("%d %h %Y", $tmp);
			
	$xml = $ttxml->xml;	
			
	$result = $xml->xpath("//title"); 
	$title = $result[0];
	if ( $title == "" ) $title = "<i>{%Without Title}</i>";

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
	} else if ( $_GET['type'] == "chapter"  ) { 
		$pbtype = "milestone[@type=\"chapter\"]";
		$titelm = "Chapter";
		$foliotxt = $titelm;
		$pbelm = "milestone";
		$pbsel = "&pbtype={$_GET['pbtype']}";
	} else if ( $_GET['pbtype'] && $settings['xmlfile']['milestones'][$_GET['pbtype']] ) { 
		$elm = $_GET['pbtype'];
		$pbtype = "milestone[@type=\"$elm\"]";
		$titelm = $settings['xmlfile']['milestones'][$elm]['display'] or $titelm = ucfirst($elm);
		$titelm = "{%$titelm}";
		$foliotxt = $titelm;
		$pbelm = "milestone";
		$pbsel = "&pbtype={$elm}";
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


	// TODO: move this to ttxml
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
			$tmp = substr($file, $pbef, $pblen); $cnt = 0;
			while ( preg_match('/empty="1"/', $tmp) && $cnt++ < 100 ) {
				# Jump over (explicit) empty pages
				$pbef = strpos($file, "<$pbelm", $pbef+1);
				$pbaf = strpos($file, ">", $pbef);
				$pblen = $pbaf-$pbef+1;
				if ( !$pbef ) {	
					$pbef = strpos($file, "<text"); # Allow for non-paged XML files
					$pblen = 500;
				};
				$tmp = substr($file, $pbef, $pblen); 
			};
			if ( preg_match("/<$pbelm [^>]*id=\"(.*?)\"/", $tmp, $matches) ) {
				$_GET['pageid'] = $matches[1];
			};
 		};
	};

	if ( $username ) $txtid = $fileid; else $txtid = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>$title </h1>";


	if ( $username && !is_writable("$xmlfolder/$fileid") ) {
		$warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>Due to filepermissions, this file cannot be
			modified by TEITOK - please contact the administrator of the server.</p>";
	};
	
	# Warn on <page> type temp files
	if ( $xml->xpath("//page") ) {
		$warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>This is not a pure TEI file,
			but a temporary file for <a href='index.php?action=pagetrans&id=$xmlid'>page-by-page transcription</a>. Best use the appropriate function for that.</p>";
	};
	
	$maintext .= $warnings;

	$maintext .= $ttxml->tableheader();

	$editxml = $ttxml->asXML(); # This got lost somehow

	if ( strstr($ttxml->xml->asXML(), '</tok>' ) ) $tokcheck = 1; // check whether this file is tokenized

	$pageid = $_GET['pageid'];
	$pagenav = $ttxml->pagenav;
	
	// Show a header above files that are only partially shown (to users) 
	if ( $restricted && $username ) { 
		$pagenav .= "<p class=adminpart>This text is only show partially to visitors due to copyright restrictions; 	
			to liberate this file, set ".$settings['xmlfile']['restriction']." in the header<hr>";
	};
	
	# Change any <desc> into i18n elements
	$editxml = preg_replace( "/<desc[^>]*>([^<]+)<\/desc>/smi", "<desc>{%\\1}</desc>", $editxml );
	
	// TODO: this somehow does not work	
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
		$postjsactions .= "\n				toggleint();";
	};
	if ( ( strpos($defaultview, "breaks") && !$_COOKIE['toggleshow'] ) || $_COOKIE['toggleshow'] == "true" ) {
		$postjsactions .= "\n				toggleshow();";
	};
	if ( ( strpos($defaultview, "pb") ) || $_COOKIE['pb'] == "true" ) {
		$postjsactions .= "\n				toggletn('pb');";
	};
	if ( ( strpos($defaultview, "lb") ) || $_COOKIE['lb'] == "true" ) {
		$postjsactions .= "\n				toggletn('lb');";
	};
	if ( ( strpos($defaultview, "colors") && !$_COOKIE['togglecol'] ) || $_COOKIE['togglecol'] == "true" ) {
		$postjsactions .= "\n				togglecol();";
	};
	if ( ( strpos($defaultview, "images") && !$_COOKIE['toggleimg'] ) || $_COOKIE['toggleimg'] == "true" ) {
		$postjsactions .= "\n				toggleimg();";
	};
	
	if ( $settings['xmlfile']['autonumber'] == "1" ) {
		$postjsactions .= "\n				var autonumber = 1;";
	};
	if ( $settings['xmlfile']['adminfacs'] == "1" && !$username ) {
		$prejsactions .= "\n				var nofacs = 1;";
	};
	

	# empty tags are working horribly in browsers - change
	$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

	foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
		$val = $item['direction'];
		if ( $val )	{
			 $fdlist .= "\n				formdir['$key'] = '$val';";
		};
	};
	if ( $fdlist ) { $postjsactions .= "\n				var formdir = [];$fdlist"; };
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
		$postjsactions .= "\n				labels=['$labtxt'];";
	};
	if ( $showform ) {
		$postjsactions .= "\n				setForm('$showform');";
	} else {
		$postjsactions .= "\n				setbut('but-pal');";
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
		$postjsactions .= "makeaudio();";
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
		
		# TODO: Check why this fails in the new version
		# if ( preg_match("/<text[^>]*>\s*<\/text>/", $editxml) ) $emptyxml = 1;
		
		if ( $tokcheck ) { 
			$maintext .= "<p class=adminpart>			
				Edit the information about each word of this file by clicking on the word in the text below, or click
				<a href='index.php?action=rawedit&id=$fileid'>here</a> to edit the raw XML
				</p><hr>
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
			$postjsactions .= "highlight('$hlid', '$hlcol'); ";
		};
		$moreaction .= "\n";
	};

	$cql = $_POST['myqueries'] or $cql = $_GET['cql'] or $cql = $_POST['cql']; 
	if ( $cql != "" || is_array($cql) ){
		// In case we have a (set of) CQL query - first load the results
		$collist = array( '#fff2a8', '#ffb7b7', '#a8d1ff', '#d1a8ff', '#d1ffa8', '#b7ffb7', '#b7b7ff', '#ffd4b7', 'cyan', 'green-dark', 'green', 'green-light', 'black' );
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		if ( is_array($cql) ) $cqpp = $cql; # For structured POST queries 
		else $cqpp = explode ( "||", urldecode($cql) );
		if ( is_array($_POST['cqlname']) ) $cqpptit = $_POST['cqlname']; # For structured POST queries 
		else $cqpptit = explode ( "||", urldecode($_GET['cqlname']) );
		
		foreach ( $cqpp as $i => $cql ) { 
			$cqpquery = $cql['cql'] or $cqpquery = $cql;  
			if ( !$cqpquery || is_array($cqpquery) ) continue; 
			
			if ( strstr($cqpquery, "<text" ) ) continue; 
			if ( !strstr($cqpquery, "Matches" ) ) $cqpquery = "Matches = $cqpquery"; 
			if ( !strstr($cqpquery, "::" ) ) {
				$sep = "::";
			} else {
				$sep = "&";
			};
			$cqpquery = str_replace(" within text", "", $cqpquery)." $sep match.text_id=\"xmlfiles/$fileid\"";
			$cqp->exec($cqpquery); 
			$cqpquery = "size Matches";
			$size = $cqp->exec($cqpquery); 
			if ( $size > 0 ) {
				$cqpquery = "tabulate Matches match id";
				$results = $cqp->exec($cqpquery); 
			
				$sep = "";
				foreach ( explode ( "\n", $results ) as $line ) {	
					list ( $tokid ) = explode ( "\t", $line );
					$postjsactions .= "highlight('$tokid', '{$collist[$i]}'); ";
				}; 
			
				$cqlname = $cql['display'] or $cqlname = $cqpptit[$i] or $cqlname = $_SESSION['myqueries'][urlencode($cql)]['name'] or $cqlname = $_SESSION['myqueries'][urlencode($cql)]['display'] or $cqlname = $cql;
				$cqptit .= "<p><a href='index.php?action=cqp&cql=$cqpquery'>{%view}</a> <span style='font-size: 10px; background-color: {$collist[$i]}; margin-right: 8px; padding: 2px;'>$size</span> ".htmlentities($cqlname);
			};
		}; 
		if ( $cqptit ) $maintext .= "<table><tr><td>{%Search Query}: </td><td>$cqptit</table><hr>";
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
		<script language=Javascript>$prejsactions</script>
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
			$postjsactions
			var jmps = '$highlights'; var jmpid;
			if ( jmps ) { 
				var jmpar = jmps.split(' ');
				for (var i = 0; i < jmpar.length; i++) {
					var jmpid = jmpar[i];
					highlight(jmpid, '$hlcol');
				};
				element = document.getElementById(jmpar[0])
				alignWithTop = true;
				if ( element != null && typeof(element) != null ) { 
					element.scrollIntoView(alignWithTop); 
				};
			};
			document.getElementById('jsoptions').style.display = 'block';
			document.getElementById('nojs').style.display = 'none';
		</script>
		";

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
	if ( $settings['download']['disabled'] != "1" ) {
		$maintext .= "$sep<a onClick='exporttxt();' style='cursor: pointer;'>{%Download current view as TXT}</a>
		"; $sep = " &bull; ";
	};
	
	if ( $settings['xmlfile']['search'] ) {
		$maintext .= "$sep<a href='index.php?action=multisearch&cid=$fileid'>{%Search inside}</a>
		"; $sep = " &bull; ";
	};
	
	if ( $audiobit ) {
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=audiomanage&cid=$fileid'>{%Audio management}</a>";
		$maintext .= " &bull; <a href='index.php?action=wavesurfer&cid=$fileid'>{%Waveform view}</a>";
	};
	
	// TODO: do a viewswitch in ttxml
	$maintext .= $ttxml->viewswitch(false);
	
	
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