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
	
	# See if we are allowed special permissions on this file
	if ( !$username && $userid && file_exists("Sources/useredit.php") ) {
		require("Sources/useredit.php");
	};
	
	# Determine the file date
	$tmp = filemtime("$xmlfolder/$fileid");
	$fdate = strftime("%d %h %Y", $tmp);
			
	$xml = $ttxml->xml;	
			
	$title = $ttxml->title();

	// on "paged" display, determine what to show
	$_GET['pbtype'] = getset('xmlfile/paged/element');
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
	} else if ( $_GET['pbtype'] && getset("xmlfile/milestones/{$_GET['pbtype']}") ) { 
		$elm = $_GET['pbtype'];
		$pbtype = "milestone[@type=\"$elm\"]";
		$titelm = getset("xmlfile/milestones/$elm/display", ucfirst($elm));
		$titelm = "{%$titelm}";
		$foliotxt = $titelm;
		$pbelm = "milestone";
		$pbsel = "&pbtype={$elm}";
	} else if ( is_array($settings['xmlfile']['paged']) && $settings['xmlfile']['paged']['closed'] ) {
		$pbtype = $_GET['pbtype'];
		$titelm = getset('xmlfile/paged/display', ucfirst($_GET['type']));
		$pbelm = $_GET['pbtype'];
	} else {
		$pbtype = "milestone[@type=\"{$_GET['pbtype']}\"]";
		$titelm = getset('xmlfile/paged/display', ucfirst($_GET['type']));
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

	if ( $username || $ssouser ) $txtid = $fileid; else $txtid = $xmlid;
	$maintext .= "<h2>$txtid</h2><h1>$title</h1>";
	
	if ( !$ttxml->xml ) { fatal("Unable to load file"); };
	
	# Warn on <page> type temp files
	if ( $ttxml->xml->xpath("//page") ) {
		$warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>This is not a pure TEI file,
			but a temporary file for <a href='index.php?action=pagetrans&id=$xmlid'>page-by-page transcription</a>. Best use the appropriate function for that.</p>";
	};
	
	if ( $username ) {
		foreach ( $ttxml->warning as $msg ) { $warnings .= "<p style='background-color: #ffaaaa; padding: 5px;; font-weight: bold;'>$msg</p>"; };
	};
	$maintext .= $warnings;

	$maintext .= $ttxml->tableheader();

	$editxml = $ttxml->asXML(); # This got lost somehow

	if ( strpos($ttxml->xml->asXML(), '</tok>' ) !== false ) $tokcheck = 1; // check whether this file is tokenized

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

	if ( $settings['xmlfile']['mtokform'] ) $attnamelist .= "\nvar mtokform = true;";

	# Define which view to show
	$defaultview = $settings['xmlfile']['defaultview'];
	// Calculate where to start from settings and cookies
	$tagoptlist = array ( "interpret", "colors", "images", 'pb', 'lb', 'ee', 'milestone' );
	$setviews = explode(",", $_GET['setviews']);
	foreach ( $tagoptlist as $tagtmp ) {
		if ( ( strpos($defaultview, $tagtmp) && !$_COOKIE["toggle-$tagtmp"] ) 
				|| $_COOKIE["toggle-$tagtmp"] == "true" 
				|| in_array($tagtmp, $setviews) ) {
			$postjsactions .= "\n				toggletn('$tagtmp');";
		};
	};
	
	# Define some global view options
	if ( $settings['xmlfile']['autonumber'] == "1" ) {
		$postjsactions .= "\n				var autonumber = 1;";
	};
	if ( $settings['xmlfile']['adminfacs'] == "1" && !$username ) {
		$prejsactions .= "\n				var nofacs = 1;";
	};
	if ( $settings['xmlfile']['nogaps'] == "1" ) {
		$postjsactions .= "\n				var nogaps = 1;";
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

	
	# Build the view options	
	$viewforms = $settings['xmlfile']['pattributes']['forms'];
	if ( !$viewforms ) $viewforms = array(); # If you do not have any view forms, this generates an error
	if ( !$viewforms['pform'] ) $viewforms = array ( "pform" => array ("display" => "Transcription")) + $viewforms; # We always need a pform view
	foreach ( $viewforms as $key => $item ) {
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
	if ( $fbc > 1 ) $viewoptions .= "<p><span>{%Text}</span>: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

	$sep = "<p>";
	$buttonsep = " <sep>-</sep> ";
	if ( $fbc > 1 ) {
		$showoptions .= " <button id='btn-tag-colors' title='{%color-code form origin}' onClick=\"toggletn('colors');\">{%Colors}</button> ";
		$sep = $buttonsep;
	};
	
	# Some of these checks work after the first token, so first find the first token
	$tokpos = strpos($editxml, "<tok"); 
	
	if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) ||  strpos($editxml, "<lb", $tokpos)  ) ) {
		$showoptions .= " <button id='btn-tag-interpret' title='{%format breaks}' onClick=\"toggletn('interpret');\">{%Formatting}</button> ";
	};
	if ( !$nobreakoptions && ( strpos($editxml, "<pb", $tokpos) || ( $username && strpos($editxml, "<pb") )  ) ) {
		// Should the <pb> button be hidden if there is only one page? (not for admin - pb editing)
		$showoptions .= " <button id='btn-tag-pb' title='{%show pagebreaks}' onClick=\"toggletn('pb');\">&lt;pb&gt;</button> ";
	};
	if ( !$nobreakoptions && ( strpos($editxml, "<lb", $tokpos) ) ) {
		$showoptions .= " <button id='btn-tag-lb' title='{%show linebreaks}' onClick=\"toggletn('lb');\">&lt;lb&gt;</button> ";
	};
	if ( !$nobreakoptions && ( strpos($editxml, "<milestone", $tokpos) ) && ( $username || getset("xmlfile/show/milestone") ) ) {
		$showoptions .= " <button id='btn-tag-milestone' title='{%show milestones}' onClick=\"toggletn('milestone');\">&lt;milestone&gt;</button> ";
	};
	
	
	
	# Deal with conditional styling
	foreach ( $settings['xmlfile']['styles'] as $key => $item ) {
		if ( $item['recond'] && !preg_match("/{$item['recond']}/", $editxml ) ) continue;
		if ( $item['rerest'] && preg_match("/{$item['rerest']}/", $editxml ) ) continue;
		if ( $item['xpcond'] && !$xml->xpath($item['xpcond']) ) continue;
		if ( $item['xprest'] && $xml->xpath($item['xprest']) ) continue;
		$showoptions .= " <button id='btn-style-$key' title='{%{$item['long']}}' onClick=\"togglestyle('$key');\">{%{$item['display']}}</button> ";
		$showoptions .= "<link rel=\"stylesheet\" type=\"text/css\" id=\"style-$key\" media=\"not all\" href=\"Resources/{$item['css']}\">";
	};
	
	if ( !$username ) $noadmin = "(?![^>]*admin=\"1\")";
	if ( preg_match("/ facs=\"[^\"]+\"$noadmin/", $editxml) ) {
		# Toggle to have the image button work right from the start (images should be on by default)
		$postjsactions .= "\n				toggletn('images');toggletn('images');";		
		$showoptions .= " <button id='btn-tag-images' title='{%show facsimile images}' onClick=\"toggletn('images');\">{%Images}</button> ";
	};
					
	foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
		$val = $item['display'];
		if ( preg_match("/ $key=/", $editxml) ) {
			if ( is_array($labarray) && in_array($key, $labarray) ) $active = " active=\"1\""; else $active = "";
			if ( !$item['admin'] || $username ) {
				$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
				$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
				$pcolor = $item['color'];
				$tagstxt .= "<button id='tbt-$key' $active style='color: $pcolor;' onClick=\"toggletag('$key')\">{%$val}</button> ";
			};
		} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
			unset($labarray[$akey]);
		};
	};
	if ( $tagstxt ) $tagoptions = "<span>{%Tags}</span>: $tagstxt ";
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
	$dirxpath = $settings['xmlfile']['direction'];
	if ( $dirxpath ) {
		$textdir = current($xml->xpath($dirxpath));
	};
	if ( $textdir ) {
		// Defined in the teiHeader for mixed-writing corpora
		$attnamelist .= "\n				setbd('".$textdir."');";
	} else if ( $settings['xmlfile']['basedirection'] ) {
		// Defined in the settings
		$attnamelist .= "\n				setbd('".$settings['xmlfile']['basedirection']."');";
	};

	# See if there is a sound to display
	# TODO: defaults/base/@media is deprecated
	$mediabaseurl =  $settings['defaults']['media']['baseurl'] or $mediabaseurl =  $settings['defaults']['base']['media'] or $mediabaseurl = "Audio";
	if ( $settings['defaults']['media']['type'] == "inline" ) {
		$prejsactions .= "\t\tvar inlinemedia = true; var mediabaseurl = '$mediabaseurl';";
		# Only treat media in the teiHeader here if we want things inline
		$mediaxp = "//teiHeader//media"; 
	} else {
		$mediaxp = "//media"; 
	};
	$result = $xml->xpath("//teiHeader//media"); 
	if ( $result ) {
		if ( $settings['defaults']['playbutton'] ) $prejsactions .= "\t\tvar playimg1 = '{$settings['defaults']['playbutton']}';";
		foreach ( $result as $medianode ) {
			list ( $mtype, $mform ) = explode ( '/', $medianode['mimeType'] );
			if ( !$mtype ) $mtype = "audio";
			if ( $mtype == "audio" ) {
				# Determine the URL of the audio fragment
				$audiourl = $medianode['url'];
				if ( $settings['defaults']['media']['baseurl'] ) {
					$audiourl = $settings['defaults']['media']['baseurl'].$audiourl;
				} else if ( $settings['defaults']['base']['media'] ) {
					## Deprecated
					$audiourl = $settings['defaults']['base']['media'].$audiourl;
				} else if ( !strstr($audiourl, 'http') ) {
					if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
					else $audiourl = $baseurl."Audio/$audiourl"; 
				}
				if ( preg_match ( "/MSIE|Trident/i", $_SERVER['HTTP_USER_AGENT']) ) {	
					// IE does not do sound - so just put up a warning
					$audiobit .= "
							<p><i><a href='$audiourl'>{%Audio}</a></i> - {%Consider using Chrome or Firefox for better audio support}</p>
						"; 
				} else {
					$audiobit .= "<audio id=\"track\" src=\"$audiourl\" controls ontimeupdate=\"checkstop();\">
							<p><i><a href='$audiourl'>{%Audio}</a></i></p>
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
						</video>";
					$result = $medianode->xpath("desc"); 
					$audiobut = "Video";
					$desc = $result[0].'';
					if ( $desc ) {
						$videobit .= "<br><span style='font-size: small;'>$desc</span>";
					};
				};
			};
		};
	} else {
		# Define the audio button even for inline media nodes
		$result = $xml->xpath("//media"); 
		if ( $result ) {
			if ( $settings['defaults']['playbutton'] ) $prejsactions .= "\t\tvar playimg1 = '{$settings['defaults']['playbutton']}';";
			foreach ( $result as $medianode ) {
				list ( $mtype, $mform ) = explode ( '/', $medianode['mimeType'] );
				if ( !$mtype ) $mtype = "audio";
				if ( $mtype == "audio" ) {
					$audiobut = "Audio";
				} else if ( $mtype == "video" ) {
					$audiobut = "Video";
				};
			};
		};
	};
	
	# Check if there are sub-sounds to display
	$result = $xml->xpath("//*[@start]"); 
	if ( $result && $audiobut ) {
		$showoptions .= " <button id='btn-audio' onClick=\"toggleaudio();\">{%$audiobut}</button> ";
		$postjsactions .= "makeaudio();";
	};

	if ( $showoptions != "" ) {
		$viewoptions .= $sep."<span>{%Show}</span>: $showoptions";
	};
	if ( $viewoptions && $tagoptions ) $viewoptions .= $buttonsep;
	$viewoptions .= $tagoptions;
	
	if ( $viewoptions != "" ) {
		# Show the View options - hidden when Javascript does not fire.
		if ( $user['permissions'] == "admin" ) $javawarning = "Javascript is not working; <a href='index.php?action=admin&act=configcheck'>check your settings</a> if Javascript is not turned off.";
		else $javawarning = "{%Javascript seems to be turned off, or there was a communication error. Turn on Javascript for more display options.}"; 
		$maintext .= "
			<div style='display: none;' id=jsoptions><h2>{%View options}</h2>
			$viewoptions
			</div>
			<div style='display: block; color: #992000;' id=nojs>
			$javawarning
			</div>
			<hr>
			";
				
	};				

	if ( $audiobut ) $maintext .= "<script language='Javascript' src=\"$jsurl/audiocontrol.js\"></script>
		$audiobit
		<hr>";

	if ( $username ) {
		
		# TODO: Check why this fails in the new version
		if ( preg_match("/<text[^>]*>\s*<\/text>/", $editxml) ) $emptyxml = 1;
		
		# Check whether there are no unnumbered tokens
		if ( $ttxml->xpath("//tok[not(@id)]") )
			$maintext .= "<p class=warning>			
				This text has not been (fully) numbered, please click
				<a href='index.php?action=renumber&id=$fileid'>here</a> to renumber the XML
				</p><hr>
				";
		
		if ( $tokcheck ) { 
			$maintext .= "<p class=adminpart>			
				Edit the information about each word of this file by clicking on the word in the text below, or click
				<a href='index.php?action=rawedit&id=$fileid'>here</a> to edit the raw XML
				</p><hr>
				";
			
		} else if ( $emptyxml ) {
			
			$maintext .= "<div class=adminpart>
			<p>This XML does not (yet) have a text content. To edit the raw XML of the file, click  
			<a href='index.php?action=rawedit&cid=$fileid&full=1'>here</a>.</div>
				<hr>";
			
		} else {
		
			$maintext .= "<div class=adminpart>
			<p>This XML has not been tokenized yet, and only the text is shown below. To edit, click  
			<a href='index.php?action=rawedit&cid=$fileid'>here</a>.</p>
			<p><i>To tokenize the text and start editing token-level attributes, select
				the tokenization link from the bottom of the page</i></div>
				<hr>";
			
			if ( $settings['xmlfile']['linebreaks'] && !strpos($editxml, "</p>") ) {
				// Interpret linebreaks as <br/> - they will get interpreted in tokenization
				$editxml = preg_replace("/\n/", "<br/>", $editxml);
			};
			
		};
	} else if ( $ssouser ) {
		$maintext .= "<div class=adminpart>Click on a token in the text to edit its attributes</div><hr>";
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

	if ( $_GET['jmpname'] ) {
		$jmpname = str_replace("::", " &gt; ", $_GET['jmpname']);
		$jmpnode = current($ttxml->xpath("//*[@id='{$_GET['jmp']}']"));
		if ( $jmpnode['appid'] ) $applink = "(<a href='index.php?action=collate&act=cqp&from=$ttxml->xmlid&appid={$jmpnode['appid']}'>{%collation}</a>)";
		$pagenav .= "<p style='text-align: center;'><span style=' font-weight: bold;'>$jmpname</span> $applink</p>";
	};

	$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
	foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $val ) {
		if ( $val['i18n'] && is_array($val['options']) ) {
			foreach ( $val['options'] as $key2 => $val2 ) {
				$settings['xmlfile']['pattributes']['tags'][$key]['options'][$key2]['display'] = "{%{$val2['display']}}";
			};
		}
	};
	$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	if ( $settings['defaults']['wordinfo'] ) $settingsdefs .= "\n\t\tvar wordinfo = true;";
	$jsontrans = array2json($settings['transliteration']);
				
	$highlights = $_GET['tid'] or $highlights = $_GET['jmp'] or $highlights = $_POST['jmp'] or $highlights = $_GET['sid'];	

	// Load the tagset 
	require ( "$ttroot/common/Sources/tttags.php" );
	$tttags = new TTTAGS($tagsetfile, false);
	if ( $tttags->tagset && ( $tttags->tagset['positions'] || $tttags->tagset['upos'] ) ) {
		$tmp = $tttags->xml->asXML();
		$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
		$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
	};

// 	if ( $ttxml->nospace ) { 
// 		$postjsactions .= " var nospace = $ttxml->nospace; setspaces(); ";
// 	};

	$edituser = $username.$ssouser; # Allow SSO users to click edit
	$maintext .= "
		<div id='tokinfo'></div>
		$pagenav
		<div id=mtxt>".$editxml."</div>
		<script language=Javascript>$prejsactions</script>
		<script language=Javascript src='$jsurl/getplaintext.js'></script>
		<script language=Javascript src='$jsurl/tokedit.js'></script>
		<script language=Javascript src='$jsurl/tokview.js'></script>
		<script>
			var username = '$edituser';
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
			if ( document.getElementById('jsoptions') ) {
				document.getElementById('jsoptions').style.display = 'block';
			};
			if ( document.getElementById('nojs') ) {
				document.getElementById('nojs').style.display = 'none';
			};
		</script>
		";

	if ( $customcss ) {
		$maintext .= "<hr style='clear: both;'>
			<table><tr><td valign=top style='padding-right: 15px;'>{%Legenda}:<td>";
		$maintext .= "<p style='margin-top: 5px;'>$customcss</table>";
	};
	
	$sep = "<hr style='clear: both; margin-top: 10px;'><p>";
	if ( !is_array($settings['download']) || ( ( $settings['download']['admin'] != "1" || $username ) && $settings['download']['disabled'] != "1" ) ) {
		$dltit = "Download XML";
		if ( is_array($settings['download']) && $settings['download']['title'] ) $dltit = $settings['download']['title'];
		$maintext .= "$sep<a href='index.php?action=getxml&cid=$fileid'>{%$dltit}</a> &bull; ";
		$sep = "";
	};
	if ( !is_array($settings['download']) || $settings['download']['disabled'] != "1" ) {
		$maintext .= "$sep<a onClick='exporttxt();' style='cursor: pointer;'>{%Download text}</a>
		"; $sep = " &bull; ";
	};
	
	if ( $settings['xmlfile']['search'] ) {
		$maintext .= "$sep<a href='index.php?action=multisearch&cid=$fileid'>{%Search inside}</a>
		"; $sep = " &bull; ";
	};
	
	if ( $audiobit ) {
		if ( $username ) $maintext .= " $sep <a href='index.php?action=audiomanage&cid=$fileid'>Audio management</a>";
		if ( !is_array(!$settings['views']) || !$settings['views']['wavesurfer'] ) $maintext .= " &bull; <a href='index.php?action=wavesurfer&cid=$fileid'>{%Waveform view}</a>";
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
				if ( $item['filerest'] ||  $item['filecond'] ) {
					$filerest = $item['filerest'];
					$filerest = preg_replace("/\[fn\]/", $ttxml->filename, $filerest);
					$filerest = preg_replace("/\[id\]/", $ttxml->xmlid, $filerest);
				};
				if ( $item['filecond'] && !file_exists($filerest) ) continue;
				if ( $item['filerest'] && file_exists($filerest) ) continue;
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
				$maintext .= "<p><ul><li><a class=adminpart href='index.php?action=filelist&act=edit&id=new&newid={$xmlid}'>Create file repository record</a></ul>";
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
		
		if ( !$tokcheck && $username && !$emptyxml ) {
			$maintext .= "<li><a href='index.php?action=tokenize&id=$fileid&display=tok'>Tokenize the text</a> (will introduce token nodes into the XML)</li>";
		};
		
		if ( glob("backups/$xmlid-*") ) { 
			$maintext .= "<li><a href='index.php?action=backups&cid=$fileid'>Recover a previous version of this file</a>
				<br> Last change to this file: <b>$fdate</b>";
		};
		
		if ( strstr($editxml, "<tok") ) {
			if ( $_GET['pageid'] ) $pnr = "&pageid=".$_GET['pageid'];
			else if ( $_GET['page'] ) $pnr = "&page=".$_GET['page'];
			$maintext .= "<li><a href='index.php?action=verticalize&act=define&cid=$fileid$pnr'>View verticalized version of this text</a>";
			$maintext .= "<li><a href='index.php?action=xmllayout&cid=$fileid'>Edit XML Layout</a>";
		};
		
		# Check if we can run the parser/neotag
		# TODO: This should be changed to the NLP pipeline and/or the API
		if ( $settings['parser'] ) {
			if ( $settings['parser']['xprest'] ) {
				if ( $ttxml->xpath($settings['parser']['xprest']) ) {
					$doparser = 1;
					$parsername = $settings['parser']['name'];
				};
			};
		} else {
		};
		if ( $doparser ) {
			if ( !$parsername ) $parsername = "parser";
			$maintext .= "<li><a href='index.php?action=parser&cid=$fileid'>Run $parsername</a>";
		};
		if ( $settings['neotag'] && !strstr($editxml, "pos=") && strstr($editxml, "<tok") ) {
			$maintext .= "<li><a href='index.php?action=neotag&act=tag&pid=auto&cid=xmlfiles/$fileid'>(Pre)tag this text with POS (and lemma)</a>";
		};
		
		if (is_array($filesources)) 
		foreach ( $filesources as $key => $val ) {
			$link = str_replace("[fn]", $fileid, $val[0]);
			
			$ln = $val[1];
			$maintext .= "<li><a href='$link'>$ln</a>";
		};
		
		$maintext .= "</ul></div>";
	} else if ( $ssouser ) {
		$maintext .= "$ssooptions";
	};


?>