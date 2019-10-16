<?php
	// Script to allow editing <tok> in an XML file
	// Which features are edited is defined in settings.xml
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'] or $fileid = $_GET['fileid'];
	$oid = $fileid;
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
	$tokid = preg_replace("/r-\d+_/", "", $tokid); // for row-driven ids

	$partform = $settings['xmlfile']['formpart'] or $partform = "form"; // 
	
	if ( !strstr( $fileid, '.xml') ) { $fileid .= ".xml"; };
	
	if ( $fileid ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
	
			$fileid = preg_replace("/^.*\//", "", $fileid);
			$test = array_merge(glob("$xmlfolder/**/$fileid")); 
			if ( !$test ) 
				$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
			$temp = array_pop($test); 
			$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
			if ( $fileid == "" ) {
				fatal("No such XML File: {$oid}"); 
			};
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file);

		$result = $xml->xpath("//tok[@id='$tokid']"); 
		$token = $result[0]; # print_r($token); exit;
		if ( !$token ) { print "Token not found: $tokid<hr>"; print $file; exit; };

		$tokform = $token['form'] or $tokform = $token."";

		$rawtok = preg_replace( "/<\/?tok[^>]*>/", "", $token->asXML() );

	if ( !$token['id'] || $token->xpath(".//dtok[not(@id)]") ) {
		$warning = "<div class=warning>There are TOK or DTOK without @id, which will not allow TEITOK 
			tok save changes made here. Click <a target=renum href=\"index.php?action=renumber&cid=$fileid\">here</a> to renumber the XML file
			which will provide all TOK and DTOK with an @id.</div>";
	}

	if ( $token->xpath(".//dtok[not(@form)]") ) {
		$warning = "<div class=warning>There are DTOK without @form, which will make the dtoks not correctly 
			export to the CQP corpus. Please provide a written form for each dtok, which should correspond
			to the form of the word if it would not have been part of this contraction/clitic/...</div>";
	}

	$result = $xml->xpath("//title"); 
	$title = $result[0];
	if ( $title == "" ) $title = "<i>{%Without Title}</i>";

	// Allow replacing special symbols by simple ASCII sequences
	if (  $settings['input']['replace'] ) {
		$chareqjs .= "<p>{%Special characters}: "; $sep = "";
		foreach ( $settings['input']['replace'] as $key => $item ) {
			$val = $item['value'];
			$chareqjs .= "$sep $key = $val"; 
			$charlist .= "ces['$key'] = '$val';";
			$sep = ",";
		};
		$chareqtxt = $chareqjs; 
		$chareqjs .= "
			<script language=\"Javascript\">
			var ces = {};
			$charlist
			function chareq (fld) {
				for(i in ces) {
					fld.value = fld.value.replace(i, ces[i]);
				}
			};
			</script>
		";
		$chareqfn = "onkeyup=\"chareq(this);\"";
	};			

		if ( $token['bbox'] ) {
			$curr = current($token->xpath("./preceding::pb[1]")); 
			$imgsrc = $curr['facs']; 
			if ( strpos($imgsrc, "http" ) === false ) $imgsrc = "Facsimile/$imgsrc";
		
			$bb = explode ( " ", $token['bbox'] );
			$cropwidth = $bb[2]-$bb[0] + 10;
			$cropheight = $bb[3]-$bb[1] + 10; 

			list($imgwidth, $imgheight, $imgtype, $imgattr) = getImageSize($imgsrc);

			$divwidth = 300;
			$divheight = $divwidth*($cropheight/$cropwidth);
			if ( $divheight > 150 ) {
				$divheight = 120;
				$divwidth = $divheight*($cropwidth/$cropheight);
			};
			$imgscale = $divwidth/$cropwidth;
			$setwidth = $imgscale*$imgwidth;
			$setheight = $imgscale*$imgheight;
			$topoffset = ($bb[1]-5)*$imgscale;
			$leftoffset = ($bb[0]-5)*$imgscale;
				
			// Add the data of the line
			$bboxpart = "<div style='float: right; width: {$divwidth}px; height: {$divheight}px; overflow: hidden; margin: 3px;'>
				<img style='width: {$setwidth}px; height: {$setheight}px; margin-top: -{$topoffset}px; margin-left: -{$leftoffset}px;' src='$imgsrc'/>
				</div>";
 		};

		$maintext .= "<h1>Edit Token</h1>
		
				<table>
				<tr><th span='row'>Filename</th><td>$fileid</td></tr>
				<tr><th span='row'>Title</th><td>$title</td></tr>
				</table>
				<hr>
		
			<h2>Token value ($tokid): ".$rawtok."</h2>
			$bboxpart
			$chareqjs
			<script language=Javascript>
				function addvalue ( ak, sel ) {	
					document.getElementById('f'+ak).value += '+'+ sel.value;
					sel.selectedIndex = 0;
				};
				var inherit = []; 
				inherit['nform'] = 'fform';
				inherit['fform'] = 'form';
				function fillfrom ( selobj, frm, att ) {
					var i;
					for(i=selobj.options.length-1;i>=0;i--)
					{
						selobj.remove(i);
					}
					var fromform = document.tagform['atts['+frm+']'].value;
					ifrm = frm;
					while ( ( fromform == '' || fromform == undefined ) && inherit[ifrm] != '' && document.tagform['atts['+inherit[ifrm]+']'] ) { 
						ifrm = inherit[ifrm];
						fromform = document.tagform['atts['+ifrm+']'].value; 
					};
					while ( ( fromform == '' || fromform == undefined ) && inherit[ifrm] != '' ) { 
						fromform = document.tagform['word'].value; 
					};
					var xmlhttp;
					if (window.XMLHttpRequest) {
					  xmlhttp=new XMLHttpRequest();
					} else {
					  xmlhttp=new ActiveXObject(\"Microsoft.XMLHTTP\");
					};
					xmlhttp.selector = selobj;
					xmlhttp.onreadystatechange = function() {
					  if (xmlhttp.readyState==4 && xmlhttp.status==200) {
						    xmlDoc = xmlhttp.responseXML;
						    if ( !xmlDoc ) { 
						    	return -1;
						    };
							x=xmlDoc.getElementsByTagName('option');
							for (i=0;i<x.length;i++) {
								var optval = x[i].childNodes[0].nodeValue;
								var j = i+1;
								this.selector.options[j] = new Option(optval, optval, true);
							}
						}
					  }
					xmlhttp.open('GET','index.php?action=getvals&att='+att+'&key='+frm+'&val='+fromform,true);
					xmlhttp.send();
				};
				
			</script>
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$tokid'>

			<table>";


		// show the innerHTML
		$xmlword = $token->asXML(); 
		$xmlword = preg_replace("/<\/?d?tok[^>]*>/", "", $xmlword); // Remove all dtoks from the raw XML - will be edited separately
		$xmlword = preg_replace("/<\/?m(?=[ >])[^>]*>/", "", $xmlword); // Remove all morphological elements from the raw XML - will be edited separately
		$xmlword = str_replace("'", "&#039;", $xmlword); // Protect quotes
		$maintext .= "<tr><td>pform<td>Transcription (Inner XML)<td><input size=60 name=word id='word' value='$xmlword'>";

		// Show all the defined forms and make them editable
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$atv = $token[$key]; 
			$val = $item['display'];
			if ( $key != "pform" && !$item['noedit'] ) { // the raw XML is not an attribute, and some attribute are set to be non-editable
				$atv = str_replace("'", "&#039;", $atv); // protect the HTML field
				$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv' $chareqfn>";
			};
		};
		
		$maintext .= "<tr><td colspan=10><hr>";
		// Show all the defined tags
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$atv = $token[$key]; 
			$val = $item['display'];
			if ( $item['noedit'] ) {
				if ( $val && $atv ) $maintext .= "<tr><td>$key<td>$val<td>$atv";
				continue;
			};
			if ( $key != "pform" ) {
				if ( $item['type'] == "Select" || $item['type'] == "ESelect" || $item['type'] == "MSelect" ) {
					if (file_exists("cqp/$key.lexicon")) {
						$tmp = file_get_contents("cqp/$key.lexicon"); 
						$optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kval ) { 
							if ( $kval ) {
								if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
								if ( ( $attype[$key] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) $optarr[$kval] = "<option value='$kval' $seltxt>$kval</option>"; 
							};
						};
						sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
					} else { $optlist = ""; };
					
					if ( $item['type'] == "ESelect" ) {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
						$maintext .= "<input type=checkbox>new value: <span id='newat'><input size=30 name=newatt[$key] id='f$key' value=''></span>";
					} else if ( $item['type'] == "Select" ) {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
					} else if ( $item['type'] == "MSelect" ) {
						$optlist = preg_replace("/<option[^>]+selected>.*?<\/option>/", "", $optlist);
						$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=atts[$key] id='f$key' value='$atv'>
							add: <select name=null[$key] onChange=\"addvalue('$key', this);\"><option value=''>[select]</option>$optlist</select>";
					} else {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
					};
					 
				} else if ( $item['type'] == "LSelect" ) {
					$fromform = $item['form'] or $fromform = "form";
					$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=atts[$key] id='f$key' value='$atv'> Alternatives: <select name='' onchange=\"document.tagform['atts[{$key}]'].value = this.value;\" onfocus=\"fillfrom(this, '$fromform', '$key');\" onload=\"fillfrom(this, '$fromform', '$key');\"><option value=''>[choose]</option></select>";					
				} else {
					$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
				};
			};
		};

		$maintext .= "</table>";


		// Show all the DTOKS
		$result2 = $token->xpath("dtok"); $dtk = 0;
		foreach ( $result2 as $dtoken ) {
			$did = $dtoken['id']; $dtk++; 
			if ( !$did ) { 
				$warning = "<div class=warning>TOK or DTOK without @id, which will not allow TEITOK 
					tok save changes made here. Click <a target=renum href=\"index.php?action=renumber&cid=$fileid\">here</a> to renumber the XML file
					which will provide all TOK and DTOK with an @id.</div>";
				$did = $token['id'].'-'.$dtk; 
			};
			$dform = $dtoken['form'];
			$dpart = $dtoken['formpart'] or $dpart = $dtoken['form'];
			$totform .= $dpart;
			$rawdxml = $dtoken->asXML();
			$rawdxml = preg_replace("/'/", "&#039;", $rawdxml ); # We need to protect apostrophs in the HTML form
			$maintext .= "<hr style='background-color: #aaaaaa;'><h2>D-Token</h2> 
				<input type=hidden name='dtok[$did]' size=70 value='$rawdxml'>
				<table>
				";
			if ( $settings['xmlfile']['formpart'] ) {
					$maintext .= "<tr><td>formpart<td>Part of token $tagform<td><input size=60 name=datts[$did:formpart] id='fformpart' value='{$dtoken['formpart']}'>";
			};
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
				$atv = $dtoken[$key]; 
				$val = $item['display'];
				if ( $key != "pform" ) {
					$atv = str_replace("'", "&#039;", $atv);
					$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=datts[$did:$key] id='f$key' value='$atv'>";
				};
			};
			foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
				$atv = $dtoken[$key]; 
				$val = $item['display'];
				if ( $key != "pform" ) {
					// if ( $attype[$key] == "select" || $attype[$key] == "eselect" || $attype[$key] == "mselect" ) {
					if ( $item['type'] == "Select" || $item['type'] == "ESelect" || $item['type'] == "MSelect" ) {
						$tmp = file_get_contents("cqp/$key.lexicon"); $optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kval ) { 
							if ( $kval ) {
								if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
								if ( ( $attype[$key] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) $optarr[$kval] = "<option value='$kval' $seltxt>$kval</option>"; 
							};
						};
						sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				
						if ( $item['type'] == "ESelect" ) {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
							$maintext .= "<input type=checkbox>new value: <span id='newat'><input size=30 name=newatt[$key] id='f$key' value=''></span>";
						} else if ( $item['type'] == "Select" ) {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
						} else if ( $item['type'] == "MSelect" ) {
							$optlist = preg_replace("/<option[^>]+selected>.*?<\/option>/", "", $optlist);
							$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=datts[$did:$key] id='f$key' value='$atv'>
								add: <select name=null[$key] onChange=\"addvalue('$key', this);\"><option value=''>[select]</option>$optlist</select>";
						} else {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
						};
					 
					} else {
						$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=datts[$did:$key] id='f$key' value='$atv'>";
					};
				};
			};
			$maintext .= "</table>";
			if ( !$warning ) $maintext .= "<p><ul><li><a href='index.php?action=toksave&act=delete&cid=$fileid&tid=$did'>delete</a> this dtok</ul>"; 
		};
		

		// Show all the Morphemes
		if ( $settings['annotations']['m'] ) {
			$result2 = $token->xpath("m"); $dtk = 0;
			foreach ( $result2 as $dtoken ) {
				$did = $dtoken['id']; $dtk++; 
				if ( !$did ) { 
					$warning = "<div class=warning>M(orphology) without @id, which will not allow TEITOK 
						tok save changes made here. Click <a target=renum href=\"index.php?action=renumber&cid=$fileid\">here</a> to renumber the XML file
						which will provide all TOK, DTOK, and M with an @id.</div>";
					$did = $token['id'].'-'.$dtk; 
				};
				$dform = $dtoken['form'];
				$rawdxml = $dtoken->asXML();
				$rawdxml = preg_replace("/'/", "&#039;", $rawdxml ); # We need to protect apostrophs in the HTML form
				$maintext .= "<hr style='background-color: #aaaaaa;'><h2>Morpheme</h2> 
					<input type=hidden name='dtok[$did]' size=70 value='$rawdxml'>
					<table>
					";
				foreach ( $settings['annotations']['m'] as $key => $item ) {
					if ( !is_array($item) ) continue;
					$atv = $dtoken[$key]; 
					$val = $item['display'];
					if ( $key != "pform" ) {
						// if ( $attype[$key] == "select" || $attype[$key] == "eselect" || $attype[$key] == "mselect" ) {
						if ( $item['type'] == "Select" || $item['type'] == "ESelect" || $item['type'] == "MSelect" ) {
							$tmp = file_get_contents("cqp/$key.lexicon"); $optarr = array();
							foreach ( explode ( "\0", $tmp ) as $kval ) { 
								if ( $kval ) {
									if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
									if ( ( $attype[$key] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) $optarr[$kval] = "<option value='$kval' $seltxt>$kval</option>"; 
								};
							};
							sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				
							if ( $item['type'] == "ESelect" ) {
								$maintext .= "<tr><td>$key<td>$val
											<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
								$maintext .= "<input type=checkbox>new value: <span id='newat'><input size=30 name=newatt[$key] id='f$key' value=''></span>";
							} else if ( $item['type'] == "Select" ) {
								$maintext .= "<tr><td>$key<td>$val
											<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
							} else if ( $item['type'] == "MSelect" ) {
								$optlist = preg_replace("/<option[^>]+selected>.*?<\/option>/", "", $optlist);
								$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=datts[$did:$key] id='f$key' value='$atv'>
									add: <select name=null[$key] onChange=\"addvalue('$key', this);\"><option value=''>[select]</option>$optlist</select>";
							} else {
								$maintext .= "<tr><td>$key<td>$val
											<td><select name=datts[$did:$key]><option value=''>[select]</option>$optlist</select>";
							};
					 
						} else {
							$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=datts[$did:$key] id='f$key' value='$atv'>";
						};
					};
				};
				$maintext .= "</table>";
				if ( !$warning ) $maintext .= "<p><ul><li><a href='index.php?action=toksave&act=delete&cid=$fileid&tid=$did'>delete</a> this morpheme</ul>"; 
			};
		};		

		# Check if there is no XML in toks without a @form
		# Which for now is any end of tag inside
		if ( preg_match("/<\/.*<\/tok>/", $token->asXML()) && $token['form'] == "" ) {
			$maintext .= "<hr><p style='background-color: #ffaaaa; padding: 5px; font-weight: bold'>Every token with XML inside should ALWAYS have a @form - consider correcting</p>";
		};
		
		$totform = preg_replace("/[|]./", "", $totform);

		# Check if the join of all @formpart of the dtoks equals the @form of the tok (when using formpart)
		if ( $totform != "" && $totform != forminherit($token, $tagform) && $settings['xmlfile']['formpart'] ) { 
			$maintext .= "<hr><p style='background-color: #ffaaaa; padding: 5px; font-weight: bold'>
				The join of the @formpart of the &lt;dtok&gt; does not match the @$tagform of the &lt;tok&gt; - consider revising
				</p>";
		};
		
		
		# Allow adding/deleting tokens 
		$maintext .= "
		<hr>
			$warning
		<!-- <a href=''>join to previous token</a> &bull;  -->
			insert tok after:
			<a href='index.php?action=retok&dir=after&cid=$fileid&tid=$tokid&pos=left'>attached</a> /
			<a href='index.php?action=retok&dir=after&cid=$fileid&tid=$tokid'>separate</a>
		&bull; before:
			<a href='index.php?action=retok&dir=before&cid=$fileid&tid=$tokid&pos=right'>attached</a> /
			<a href='index.php?action=retok&dir=before&cid=$fileid&tid=$tokid'>separate</a>
		&bull;
			insert elm before:
			<a href='index.php?action=retok&dir=before&cid=$fileid&tid=$tokid&node=par'>paragraph</a> ;
			<a href='index.php?action=retok&dir=before&cid=$fileid&tid=$tokid&node=lb'>linebreak</a>";
		
		if ( $dtk ) {
			$maintext .= " &bull;
  				add: <a href='index.php?action=retok&node=dtok&cnt=1&cid=$fileid&tid=$tokid'>dtok</a>";
  		} else {
			$maintext .= " &bull;
  				split in dtoks: <a href='index.php?action=retok&node=dtok&cnt=2&cid=$fileid&tid=$tokid'>2</a> ;
  					<a href='index.php?action=retok&node=dtok&cnt=3&cid=$fileid&tid=$tokid'>3</a>";
  		};

		$maintext .= "<br><a href='index.php?action=contextedit&cid=$fileid&tid=$tokid'>edit</a> context XML";

		# Lookup word to the left (adepted to large files)
		#	$tmp = $token->xpath('preceding-sibling::tok');
		$tokpar = current($token->xpath(".."));
		if ( $tokpar ) {
			$tmp = $tokpar->asXML(); 
			$tokpos = strpos($tmp, "id=\"$tokid\"");
			$tmp2 = rstrpos($tmp, "<tok ", $tokpos);
			$pbef = rstrpos($tmp, "<tok ", $tmp2-1);
			$tmp2 = substr($tmp, $pbef, 30);
			if ( preg_match("/id=\"([^\"]+)\"/", $tmp2, $matches ) ) $previd = $matches[1];
		};
		if ( $previd ) { 		
			$maintext .= "&bull; <a href='index.php?action=mergetoks&cid=$fileid&tid1=$previd&tid2=$tokid'>merge</a> left to $previd";
		};

		
		$mtok = current($token->xpath('ancestor::mtok'));
		if ( !$mtok && $prevtok ) {
			$maintext .= " &bull; create mtok left: <a href='index.php?action=makemtok&cid=$fileid&tid=$tokid&num=1'>1</a> ; <a href='index.php?action=makemtok&cid=$fileid&tid=$tokid&num=2'>2</a>";
		};

		# Similar tokens only works on tokens that are displayed here - which no longer works if we clip 
		$maintext .= "<br>
			<script language=Javascript src='$jsurl/simtoks.js'></script>
			<span onClick=\"simtoks('$tokid');\">treat similar tokens</span>
			<div id='simtoks'></div>
		
		";
		

		
		# In case this is part of an <mtok>, show that as well
		if ( $mtok ) {
			$mtokid = $mtok['id'];

			$maintext .= "<hr><h2>Multi-token value ({$mtok['id']}): {$mtok['form']}</h2>
				<table>";
			
			// Show all the defined forms and make them editable
			foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
				$atv = $mtok[$key]; 
				$val = $item['display'];
				if ( $key != "pform" && !$item['noedit'] ) { // the raw XML is not an attribute, and some attribute are set to be non-editable
					$atv = str_replace("'", "&#039;", $atv); // protect the HTML field
					$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=matts[$mtokid:$key] id='f$key' value='$atv' $chareqfn>";
				};
			};
			foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
				$atv = $mtok[$key]; 
				$val = $item['display'];
				if ( $key != "pform" ) {
					// if ( $attype[$key] == "select" || $attype[$key] == "eselect" || $attype[$key] == "mselect" ) {
					if ( $item['type'] == "Select" || $item['type'] == "ESelect" || $item['type'] == "MSelect" ) {
						$tmp = file_get_contents("cqp/$key.lexicon"); $optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kval ) { 
							if ( $kval ) {
								if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
								if ( ( $attype[$key] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) $optarr[$kval] = "<option value='$kval' $seltxt>$kval</option>"; 
							};
						};
						sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				
						if ( $item['type'] == "ESelect" ) {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=matts[$mtokid:$key]><option value=''>[select]</option>$optlist</select>";
							$maintext .= "<input type=checkbox>new value: <span id='newat'><input size=30 name=newatt[$key] id='f$key' value=''></span>";
						} else if ( $item['type'] == "Select" ) {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=matts[$mtokid:$key]><option value=''>[select]</option>$optlist</select>";
						} else if ( $item['type'] == "MSelect" ) {
							$optlist = preg_replace("/<option[^>]+selected>.*?<\/option>/", "", $optlist);
							$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=matts[$mtokid:$key] id='f$key' value='$atv'>
								add: <select name=null[$key] onChange=\"addvalue('$key', this);\"><option value=''>[select]</option>$optlist</select>";
						} else {
							$maintext .= "<tr><td>$key<td>$val
										<td><select name=matts[$mtokid:$key]><option value=''>[select]</option>$optlist</select>";
						};
					 
					} else {
						$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=matts[$mtokid:$key] id='f$key' value='$atv'>";
					};
				};
			};
			$maintext .= "</table>";

		};
		
		// Get the first parent node for the context
		$xp = "//tok[@id='$tokid']/..";
		$result = $xml->xpath($xp); 
		$txtxml = $result[0];
		$editxml = $txtxml->asXML();

		
		# empty tags are working horribly in browsers - change
		$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );
		
		
		# Show the context
		$maintext .= "<hr><div id=mtxt>".$editxml."</div>
		<hr><input type=submit value=\"Save\">
		<!-- <button onClick=\"window.open('index.php?action=file&cid=$fileid', '_self');\">Cancel</button> -->
		<a href='index.php?action=file&cid=$fileid'>Cancel</a>
		<script language=Javascript>
			document.getElementById('fnform').focus();
			highlight('$tokid',  '#ffee88');
		</script>
		&bull; <a href='index.php?action=wordinfo&cid=$fileid&tid=$tokid'>Token Details</a>
		
		</form>
		";

		// Add a session logout tester
		$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";
	
	} else {
		print "Oops"; exit;
	};
	
?>