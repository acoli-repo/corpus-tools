<?php

	# Name-oriented document view and name index
	# Maarten Janssen, 2020

	$viewname = getset('xmlfile/ner/title', "Named Entity View");
	$correspatt = getset('xmlfile/ner/corresp', "corresp"); # Corrspondence attribute
	
	if ( !$_GET['cid'] ) $_GET['cid']  = $_GET['id'];
	$nertitle = getset('xmlfile/ner/title', "Named Entities"); # Same as viewname??
	$neritemname = getset('xmlfile/ner/item', "entity"); # Name of an entity

	$nerlist = getset('xmlfile/ner/tags', 
		array(
			"placename" => array ( "display" => "Place Name", "cqp" => "place", "node" => "place", "elm" => "placeName", "nerid" => "ref" ), 
			"persname" => array ( "display" => "Person Name", "cqp" => "person", "node" => "person", "elm" => "persName", "nerid" => "ref" ), 
			"orgname" => array ( "display" => "Organization Name", "cqp" => "org", "node" => "org", "elm" => "orgName", "nerid" => "ref" ),
			// "term" => array ( "display" => "Term", "cqp" => "term", "elm" => "term", "nerid" => "ref" ),
			// "name" => array ( "display" => "Name", "cqp" => "name", "elm" => "name", "nerid" => "ref" ),
			// "time" => array ( "display" => "Time", "cqp" => "time", "elm" => "time", "nerid" => "none" ),
			// "num" => array ( "display" => "Number", "cqp" => "num", "elm" => "num", "nerid" => "none" ),
			// "date" => array ( "display" => "Date", "cqp" => "date", "elm" => "date", "nerid" => "none" ),
			// "unit" => array ( "display" => "Unit", "cqp" => "unit", "elm" => "unit", "nerid" => "none" ),
			));
	$nerjson = array2json($nerlist);
	
	$nn2rn = array (
		"person" => "persName",
		"org" => "orgName",
		"place" => "placeName",
	);
	$nn2sn = array (
		"person" => "listPerson",
		"org" => "listOrg",
		"place" => "listPlace",
	);
	$rn2nn = array (
		"persName" => "person",
		"orgName" => "org",
		"placeName" => "place",
	);

	// Load the tagset (for the NER type tag)
	if ( getset('xmlfile/ner/tagset') != "none" ) {
		$tagsetfile = getset('xmlfile/ner/tagset', "tagset-ner.xml");
		require ( "$ttroot/common/Sources/tttags.php" );
		$tttags = new TTTAGS($tagsetfile, false);
		if ( is_array($tttags->tagset) && $tttags->tagset['positions'] ) {
			$tmp = $tttags->xml->asXML();
			$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
			$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
		};
	};

	$nerfile = getset('xmlfile/ner/nerfile', "ner.xml"); # NER file
	$nerbase = $nerfile;
	if ( strpos($nerfile, "/") == false ) $nerfile = "Resources/$nerfile";
	if ( file_exists($nerfile) ) $nerxml = simplexml_load_file($nerfile); 

	if ( $_GET['cid'] && $act == "list" ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%$nertitle}</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();		
		if ( $tmp = getset("defaults/topswitch") ) {
			if ( $tmp == "1" ) $tmp = "Switch visualization";
			$maintext .= "<p>{%$tmp}: ".$ttxml->viewswitch()."<hr>"; 
		};

		$maintext .= "<table id='nertable'>";
		foreach ( $nerlist as $key => $val ) {
			$nodelist = $xml->xpath("//text//{$val['elm']}");
			unset($idnames);
			if ( $nodelist ) {
				$maintext .= "<tr><td colspan=2 style='padding-top: 10px; padding-bottom: 10px; '><b style='font-size: larger;'>{$val['display']}</b></tr>";
			
				foreach ( $nodelist as $node ) {
					$nerid = $node[$val['nerid'].""]."";
					if ( $nerid ) { $idlist[$nerid] = 1; }; # use form if no ID is present
					if ( !$nerid ) { $nerid = $node['lemma']; }; # use form if no ID is present
					$name = makexml($node);
					$name = trim(preg_replace("/<[^>]+>/", "", $name));
					if ( getset('xmlfile/nospace') == "2" ) $name = $name = preg_replace("/<\/tok>/", " ", $name);
					if ( !$nerid ) { $nerid = $name; }; # use form if no ID is present
					$nerid = trim($nerid);
					$idnames[$nerid.""][$name.""]++;
					$idcnt[$nerid.""]++;
				};
			};	
			if (is_array($idnames)) {
				ksort($idnames);
				foreach ( $idnames as $nerid => $val ) {
					$vallist = array_keys($val); natcasesort($vallist);
					$name = join("<br/>", $vallist);
					if ( substr($nerid, 0, 4) == "http") $idtxt = "<a href='$nerid'>$nerid</a>";
					else if ( substr($nerid, 0, 1) == "#" ) {
						$idxp = "//*[@id=\"".substr($ref,1)."\" or @xml:id=\"".substr($nerid,1)."\"]";
						$idnode = $xml->xpath($idxp);
						$idtxt = ""; $sep = "";
						if ( !$idnode ) $idtxt = ""; else 
						foreach ( $idnode[0]->xpath(".//link") as $linknode ) {
							$idname = $linknode['type'] or $idname = $linknode['target'];
							$idtxt = $sep."<a href='{$linknode['target']}'>$idname</a>"; 
							$sep = "<br/>";
						};
					} else if ( $username ) $idtxt = "<i style='opacity: 0.5'>$nerid</i>";
					else $idtxt = "<i style='opacity: 0'>$nerid</i>";
					if ( $idlist[$nerid] ) $idtxt = "<a href='index.php?action=ner&nerid=".urlencode($nerid)."' target=ner>$idtxt</a>";
					$cidr = ""; if ( substr($nerid,0,1) == "#" ) $cidr = "&cid=".$ttxml->fileid;
					if ( $trc == "odd" ) $trc = "even"; else $trc = "odd";
					$nametxt = "<td>".$name; 
					if ( $idlist[$nerid] ) $nametxt = "<td title='{%Lemma}'><a href='index.php?action=$action&type=$key&nerid=".urlencode($nerid)."$cidr'>$name</a>";
					$maintext .= "<tr key='$name' class='$trc'>$nametxt 
						<td>$idtxt
						<td style='opacity: 0.5; text-align: right; padding-left: 10px;' title='{%Occurrences}'>{$idcnt[$nerid]}";
				};
			};
		};
		$maintext .= "</table>
				<hr> <a href='index.php?action=$action&cid={$ttxml->fileid}'>{%back}</a>";

	} else if ( $act == "neredit" ) {

		check_login(); 
		
		list ( $nerfl, $nerid ) = explode("#", $_GET['nerid']);
		if ( !$nerid ) $nerid = $nerfl;
		
		$maintext .= "<h1>Edit NER Record $nerid</h1>";

		if ( !$nerxml ) { fatal("Failed to load NER file $nerfile"); exit; };

		$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
		if ( !$nerrec ) {
			if ( $_GET['create'] ) {
				$maintext .= "<p><i>New record</i></p>";
				$etype = $_GET['type'];
				$nerdef = $nerlist[$etype] or $nerdef = $nerlist[strtolower($etype)];
				$nerelm = $nerdef['node'] or $nerelm = $etype;
				if ( !$etype ) fatal ("No NER type given");
				$nerrec = simplexml_load_string("<$nerelm id=\"$nerid\">\n</$nerelm>");
				$type .= "&create=1";
			} else {
				fatal("No such NER record: $nerid");
			};
		} else {
			$etype = $nerrec->getName()."";
		};
		foreach ( getset('xmlfile/ner/tags', array()) as $tmp ) {
			if ( $tmp['elm']."" == $etype || $tmp['node']."" == $etype ) $nerdef = $tmp;
		};

		$maintext .= "
			<p>Record type: <b>{$nerdef['display']}</b>
			";

		if ( $nerdef['options'] && !$_GET['raw'] ) {
		
			$maintext .= "
			<form action=\"index.php?action=$action&act=nersave&cid=$fileid$type\" id=frm name=frm method=post>
			<input type=hidden name=nerid value='$nerid'>
			<table>";
			foreach ( $nerdef['options'] as $key => $val ) {
				$fldname = $val['display'];
				$nerxp = $val['xpath'];
				$fldval = current($nerrec->xpath($nerxp));
				$maintext .= "<tr><th>$fldname<td><input name=xp[$nerxp] value='$fldval' size=80>";
			};
			$maintext .= "</table>
			<p><input type=submit value=Save> &bull; <a href='index.php?action=$action&act=$act&raw=1&cid=$fileid&nerid=$nerid$type'>edit raw XML</a> 
				&bull; <a href='index.php?action=$action&ner=$nerid'>cancel</a>
			</form>";
			
		} else {
			$domxml = dom_import_simplexml($nerrec);
			$editxml = $domxml->ownerDocument->saveXML($domxml);
			
			$maintext .= "
			<div id=\"editor\" style='width: 100%; height: 400px;'>".htmlentities($editxml)."</div>
	
			<form action=\"index.php?action=$action&act=nersave&cid=$fileid$type\" id=frm name=frm method=post>
			<input type=hidden name=nerid value='$nerid'>
			<input type=hidden name=etype value='$etype'>
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"return runsubmit();\"> $switch
			</form>
		
			<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var editor = ace.edit(\"editor\");
				editor.setTheme(\"ace/theme/chrome\");
				editor.getSession().setMode(\"ace/mode/xml\");

				function runsubmit ( ) {
					var rawxml = editor.getSession().getValue();
					var oParser = new DOMParser();
					var oDOM = oParser.parseFromString(rawxml, 'text/xml');
					if ( oDOM.documentElement.nodeName == 'parsererror' ) {
						alert('Invalid XML - please revise before saving.'); 
						return -1; 							
					} else {
						document.frm.rawxml.value = rawxml;
						document.frm.submit();
					};						
				};
			</script>
		";
		};
		// Add a session logout tester
		$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";	

	} else if ( $act == "nersave" ) {

		check_login(); 
		
		$nerid = $_POST['nerid'];
		if ( !$nerid ) fatal("No NER id provided");
		
		print "<p>Saving NER record $nerid";

		if ( !$nerxml ) { fatal("Failed to load NER file $nerfile"); exit; };

		$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
		if ( !$nerrec ) {
			if ( $_GET['create'] ) {
				print "<p>FULL: ".showxml($nerxml);
				$etype = $_POST['etype'];
				$nerdef = $nerlist[$etype] or $nerdef = $nerlist[strtolower($etype)];
				$nerelm = $nerdef['node'] or $nerelm = $etype;
				print "<p><i>New record</i></p>";
				$parxp = $nerdef['section'] or $parxp = $nn2sn[$nerelm] or $parxp = "list";
				$prnt = xpathnode($nerxml, "/settingsDesc/$parxp");
				$nerrec = $prnt->addChild($nerelm, "");
			} else {
				fatal("No such NER record: $nerid");
			};
		};
		
		if ( $_POST['rawxml'] ) {
			$newxml = $_POST['rawxml'];
		} else {
			$etype = $nerrec->getName();
			$newrec = simplexml_load_string($nerrec->asXML());
			foreach ( $_POST['xp'] as $key => $val ) {
				print "<p>$key => $val";
				$nerxp = "/$etype/$key";
				$xnode = xpathnode($newrec, $nerxp);
				$xnode[0] = $val;
			};
			$newxml = $newrec->asXML();
		};
		replacenode($nerrec, $newxml);

		# First - make a once-a-day backup
		$date = date("Ymd"); 
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $nerfile );
		$buname = preg_replace ( "/.*\//", "", $buname );
		if ( !file_exists("backups/$buname") ) {
			copy ( $nerfile, "backups/$buname");
		};
		# Now, make a safe XML text out of this and save it to file
		file_put_contents($nerfile, $nerxml->asXML());
		print "<p>Your changes have been saved - reloading
			<script>top.location='index.php?action=$action&nerid=$nerid';</script>
			";
		exit;

	} else if ( $_GET['cid'] && $act == "edit" ) {

		check_login(); 
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		if ( !$ttxml ) fatal("Failed to load $ttxml");
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;


		$nerid = $_GET['nerid'] or $nerid = $_GET['id'];
		$result = $xml->xpath("//*[@id='$nerid']"); 
		$nernode = $result[0]; # print_r($token); exit;
		if ( !$nernode ) fatal("No such element: $nerid");

		$form = $nernode['form'] or $form = trim(preg_replace("/<[^<>]+>/", "", $nernode->asXML()));
		$lemma = $nernode['lemma']; 
		if ( !$lemma ) {
			$clone = simplexml_load_string($nernode->asXML());
			foreach ( $clone->xpath("//tok") as $tok ) {
				if ( $tok['lemma'] ) $tok[0] = $tok['lemma'];
			};
			$lemma = trim(preg_replace("/<[^<>]+>/", "", $clone->asXML()));	
		};

		$nodetype = $nernode->getName();
		if ( $tttags && $nernode['type'] ) {
			$tmp = substr($nernode['type'], 0, 1);
			$rectype = $tttags->tagset['positions'][$tmp]['tei'];
			if ( $rectype ) {
				$etype = $nn2rn[$rectype];
				$ename = $tttags->tagset['positions'][$tmp]['display']." ($nodetype + type = {$nernode['type']})";
				$moredef['type'] = array ( 'display' => "Type");
			};
		};
		if ( !$etype ) {
			$etype = $nodetype;
		};
		
		foreach ( getset('xmlfile/ner/tags', array()) as $tmp ) if ( $tmp['elm'] == $etype ) $nerdef = $tmp;
		$sattdef = getset("xmlfile/sattributes/$etype");

		if ( !$ename ) $ename = $sattdef['display'];
		
		if ( !$sattdef && $nerxml ) $sattdef = array ( $correspatt => array ("display" => "NER id") );
		
		$maintext .= "
			<h2>".$ttxml->title()."</h2>
			<h1>Edit Named Entity</h1>
			<h2>$form ($lemma)</h2>
			<div style='margin-bottom: 15px;'><b>Entity type ($nerid): ".$etype." = $ename</b></div>
			
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$nerid'>
			<input type=hidden name=next value='ner'>
			<table>";

		$defrecs = array ( $sattdef, $nerdef['options'], $moredef );
		foreach ( $defrecs as $defrec ) {
			foreach ( $defrec as $key => $item ) {
				if ( !is_array($item) ) continue;
				if ( $done[$key] ) continue;
				$itemtxt = $item['display'];
				$atv = $nernode[$key]; 
				$maintext .= "<tr><th>$key<td>$itemtxt<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
				$done[$key] = 1;
			};
		};

		# Display the first parent node as context
		$result = $nernode->xpath(".."); 
		$txtxml = $result[0]; 

		$maintext .= "</table>";

		$maintext .= "<hr>
		<input type=submit value=\"Save\">
		<a href=\"index.php?action=$action&cid=$fileid\">cancel</a>";
		$corresp = preg_replace("/.*#/", "", $nernode[$correspatt]);
		if ( $nernode[$correspatt] ) $maintext .= "
			&bull;
			<a href=\"index.php?action=$action&nerid=$corresp\">view record</a>
			";
		else $maintext .= "
			&bull;
			<a href=\"index.php?action=$action&act=lookup&cid=$ttxml->fileid&nerid=$nerid\">lookup entity</a>
			";
		
		$maintext .= "
				&bull;
			<a href=\"index.php?action=$action&act=delete&nerid=$nerid&cid=$ttxml->fileid\">remove NER</a>
		";
		
		$maintext .= "
		</form>
		<!-- <a href='index.php?action=file&cid=$fileid'>Cancel</a> -->
		<hr><div id=mtxt>".makexml($txtxml)."</div>
		<script language=Javascript>
			var telm = document.getElementById('$nerid');
			telm.style.backgroundColor = '#ffffaa';
		</script>
		";

		$correspid = $nernode[$correspatt]; $elmtext = preg_replace("/<[^>]+>/", "", makexml($nernode));
		if ( $correspid ) {
			$nerid = $correspid; if ( strpos($nerid, '#') ) $nerid = substr($nerid, strpos($nerid, '#')+1);
			$nertype = $nerdef['key'];
			$maintext .= "<hr><h2>Linked Entity $nerid</h2>
			<p><a href='index.php?action=$action&type=$nertype&nerid=".urlencode($correspid)."'>Go to occurrences</a>";
			if ( $nerxml ) {
				$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
				if ( $nerrec ) {
					$maintext .= "<p><pre>".htmlentities($nerrec->asXML())."</pre>
					<p><a href='index.php?action=$action&act=neredit&nerid=$nerid'>edit NER record</a>";
				} else {
					$maintext .= "<p><i>No such NER element: $nerid</i> (<a href='index.php?action=$action&act=neredit&nerid=$nerid&create=1&type=$etype'>create</a>)";
				};
			} else {
				$maintext .= "<i>Failed to load: $nerfile</i>";
			};
		} else {
			## Attempt to find a corresponding record by looking at CQP corpus
			$tmp = getcqpner($nerdef['key']); 
			$nerlist = $tmp[$nerdef['key']];

			foreach ( $nerlist as $line ) {
				list ($nertext, $ref) = explode("\t", $line);
				if ( $nertext == $elmtext && $nertext ) { 
					$nerid = $ref; if ( strpos($ref, '#') ) $nerid = substr($ref, strpos($ref, '#')+1);
					$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
					$nerlemma = "undefined"; 
					if ( $nerrec ) { 
						$nerlemma = current($nerrec->xpath(".//{$nerdef['elm']}"));
					};
					$linkoptions .= "<tr><td><a onclick=\"var celm = document.getElementById('tagform').elements['atts[$correspatt]']; if ( celm ) { celm.value = '$ref'; } else { alert('no corresp'); };\">$ref</a><td>$nertext<td>$nerlemma";
				};
			};
			
			if ( $linkoptions ) $maintext .= "<h2>Potential Links for '$elmtext'</h2>
				<table>
				<tr><th>Reference ID<th>Previous occurrence<th>Reference form
				$linkoptions
				</table>
				";
		};


	} else if ( $act == "remove" ) {
	
		check_login();
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		
		$nerid = $_POST['nerid'];
		if ( !$nerid ) fatal("No NER given");
		print "<p>Removing NER $nerid</p>";

		$nerrec = current($ttxml->xpath("//*[@id=\"$nerid\"]"));	
		if ( !$nerrec ) fatal("No such NER: $nerid");

		delparentnode($ttxml->xml, $nerid);

		$ttxml->save();
		print "<p>NER removed - reloading
			<script>top.location='index.php?action=$action&cid=$ttxml->fileid';</script>";
		exit;

	} else if ( $act == "delete" ) {
	
		check_login();
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		
		$nerid = $_GET['nerid'];
		if ( !$nerid ) fatal("No NER given");
		$maintext .= "<h1>Delete NER $nerid</h1>";

		$nerrec = current($ttxml->xpath("//*[@id=\"$nerid\"]"));
		if ( !$nerrec ) fatal("No such NER: $nerid");

		$maintext .= "<h2>Annotation to be removed</h2><p>".$nerrec->getName()." here:<br>".showxml($nerrec);
		
		$maintext .= "<hr><form action='index.php?action=$action&act=remove&cid=$ttxml->fileid' method=post>
			<input type=hidden name=nerid value='$nerid'>
			<input type=submit value='Remove'> <a href='index.php?action=$action&cid=$ttxml->fileid'>cancel</a>
			</form>";


	} else if ( $_GET['cid'] && $act == "multiadd" ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();

		print "Adding NER nodes";
		foreach ( $_POST['sels'] as $key => $val ) {
			$nertype = $_POST['type'][$key];
			$nernode = getset("xmlfile/ner/tags/$nertype/elm");
			$toklist = $_POST['toks'][$key];
			print "<hr>$key: {$_POST['toks'][$key]} = $nernode  / {$_POST[$correspatt][$key]}</hr>";
			$newner = addparentnode($ttxml->xml, $toklist , $nernode);
			$newner->setAttribute($correspatt, $_POST[$correspatt][$key]);
		};
		
		print "<hr>";

		$fileid = $ttxml->fileid;
		saveMyXML($ttxml->xml->asXML(), $ttxml->filename);
		$nexturl = "index.php?action=renumber&cid=$fileid";
		print "<hr><p>Your NERs have been inserted - reloading to renumber page";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;


	} else if ( $_GET['cid'] && $_GET['nerid'] && $act == "lookup" ) {

		check_login(); 
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		
		$nerid = $_GET['nerid'];
		$nernode = current($ttxml->xpath("//*[@id=\"$nerid\"]"));
		$nernodetype = $nernode->getName().'';

		$form = $nernode['form'] or $form = trim(preg_replace("/<[^<>]+>/", "", $nernode->asXML()));
		$lemma = $nernode['lemma']; 
		if ( !$lemma ) {
			$clone = simplexml_load_string($nernode->asXML());
			foreach ( $clone->xpath("//tok") as $tok ) {
				if ( $tok['lemma'] ) $tok[0] = $tok['lemma'];
			};
			$lemma = trim(preg_replace("/<[^<>]+>/", "", $clone->asXML()));	
		};
		if ( $tttags && $nernode['type'] ) {
			$tmp = substr($nernode['type'], 0, 1);
			$type = $tttags->tagset['positions'][$tmp]['tei'];
			if ( $type ) {
				$nodetype = $nn2rn[$type];
				$nertype = $type;
			};
		} 
		if ( !$type ) $type = $nernodetype;
		$nerdef = $nerlist[$type];
		if ( !$nodetype ) $nodetype = $nerdef['node'] or $nodetype = $rn2nn[$type] or $nodetype = $type;
		$namenode = $nerdef['elm'] or $nodetype = $rn2nn[$type] or $nodetype = $type;
		
		if ( !$nertype ) $nertype = $nerlist[$type]['elm'];
		if ( !$nertype  ) $nertype = $rn2nn[$type];
		$xp = "//{$nodetype}[{$namenode}[.=\"$lemma\"]]";
	
		$maintext .= "<h1>NER Lookup</h1>
			<p>NER: $lemma ($nerid - $type - $form)
			<p>Checking if record exists
			";
		
		if ( $xp && $nerxml) $nerrec = current($nerxml->xpath($xp));
		if ( $nerrec ) {
			$nerrecid = $nerrec['id'];
			$maintext .= showxml($nerrec);
			$nernode[$correspatt] = "$nerbase#".$nerrecid;
			# Save the XML
			$gotosave = 1;
		} else  if ( $_GET['wid'] ) {
			$wid = $_GET['wid'];
			$cmd = "perl $ttroot/common/Scripts/wikilookup.pl --recid='$wid' --type=$type";
			if ( $debug ) $maintext .= "<p>Lookup up in Wikidata $cmd";
			$newrec = shell_exec($cmd);
			$nerdata = simplexml_load_string($newrec);
			$maintext .= showxml($nerdata);
		} else {
			$qq = ";name=$lemma";
			if ( $_POST['q'] ) {
				$qq = "";
				foreach ( $_POST['q'] as $key => $val ) {
					$qq .= ";$key=$val";
				};
			};
			$cmd = "perl $ttroot/common/Scripts/wikilookup.pl --query='type=$nertype$qq'";
			if ( $debug ) $maintext .= "<p>Lookup up in Wikidata $cmd";
			$newrec = shell_exec($cmd);
			$nerdata = simplexml_load_string($newrec);
			# $maintext .= showxml($nerdata);
		};

		if ( $nerdata ) {
			if ( $nerdata->getName() == "sparql" ) {
				# Disambiguate
				if ( $type == "person" || $type == "persName" || $type == "persname" ) $morefld .= "<p>Birth year: <input name=q[birth] size=10></p>";
				if ( count($nerdata->xpath("//result")) > 1 ) $maintext .= "<p>Multiple matches - choose from the list below, or specify manually";
				else $maintext .= "<p>No matches - specify manually (or create NER record manually)";
				$maintext .= "<form action='index.php?action=$action&act=lookup&cid=$ttxml->fileid&nerid=$nerid' method=post>
					<p>Full name: <input name=q[name] size=80 value=\"$lemma\">
					$morefld
					<input type=submit value=Submit>
					</form></p><table>";
				foreach ( $nerdata->xpath("//result") as $ritem ) {
					$itemid = str_replace("http://www.wikidata.org/entity/", "", current($ritem->xpath(".//uri")));
					$label = current($ritem->xpath(".//binding[@name='itemLabel']//literal"));
					$desc = current($ritem->xpath(".//binding[@name='itemDescription']//literal"));
					$maintext .= "<tr><td><a href='index.php?action=$action&act=lookup&wid=$itemid&cid=$ttxml->fileid&nerid=$nerid&type=$type'>$itemid</a>
						<td>$label
						<td>$desc
						<td><a href='http://www.wikidata.org/wiki/$itemid' target=wikidata>wikidata</a>
						</tr>
						";
				};
				$maintext .= "<table>";
			} else {
				# New record
				
				$nerrecid = $nerdata['id'];
				$nernode[$correspatt] = "$nerbase#".$nerrecid;
				print  "<p>Updating NER : $nerid => {$nernode[$correspatt]}";
				$maintext .= showxml($nernode);
				
				# Save the XML
				$gotosave = 1;
				
				$have = $nerxml->xpath("//*[@id=\"$nerid\"]");
				if ( $have ) {
					print "<p>Node exists: $nerid";
				} else {
					# Add to ner.xml
					$newelm = $nerdata->getName();
					$secname = $nn2sn[$newelm] or $secname = "list";
					print "<p>Adding to section : $secname";
					$section = current($nerxml->xpath("//$secname"));
					if ( !$section ) {
						$root = current($nerxml->xpath("/settingsDesc"));
						$section = $root->addChild($secname."", "");
					};
					$newc = $section->addChild($nertype, "");
					replacenode($newc, $newrec);

					# Save the ner.xml
					$date = date("Ymd"); 
					$buname = preg_replace ( "/\.xml/", "-$date.xml", $nerfile );
					$buname = preg_replace ( "/.*\//", "", $buname );
					if ( !file_exists("backups/$buname") ) {
						copy ( $nerfile, "backups/$buname");
					};
					# Now, make a safe XML text out of this and save it to file
					file_put_contents($nerfile, $nerxml->asXML());
				};
								
			};
		};
		
		if ( $gotosave ) {
			# Check if there are more occurrences of the same NER
			$xp = "$mtxtelement//{$nernodetype}[not(@corresp)]";
			print "<p>Checking if there are more occurrences";
			print "<p>".$xp;
			foreach ( $ttxml->xpath($xp) as $morenode ) {
				$morelemma = $morenode['lemma']; 
				if ( !$morelemma ) {
					$clone = simplexml_load_string($morenode->asXML());
					foreach ( $clone->xpath("//tok") as $tok ) {
						if ( $tok['lemma'] ) $tok[0] = $tok['lemma'];
					};
					$morelemma = trim(preg_replace("/<[^<>]+>/", "", $clone->asXML()));	
				};
				if ( $morelemma == $lemma ) {
					print "<p>Match! {$morenode['id']} = $morelemma";
					$morenode[$correspatt] = $nernode[$correspatt];
				};
			};
			$ttxml->save();
			print "<p>Change saved - reloading
				<script>top.location = 'index.php?action=$action&cid=$ttxml->fileid&act=edit&nerid=$nerid';</script>";
			exit;
		};
		
	} else if ( $_GET['cid'] && $act == "detect" ) {

		check_login(); 
		
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		if ( !$ttxml ) fatal("Failed to load $ttxml");
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>Automatic NER linking</h2>
			<h1>".$ttxml->title()."</h1>
			<p>Below is the list of all possible Named Entities in the text, based on previously marked NER
				in the CQP corpus. Select the correctly identified names, and click the add button to add the
				corresponding nodes with their reference to the NER file to the TEI/XML.
			<script language=Javascript src=\"$jsurl/ner.js\"></script>";

		$toklist = array(); $tcnt=0;
		foreach ( $ttxml->xpath("//tok") as $tok ) {
			$toktext = preg_replace("/<[^>]+>/", "", $tok->asXML());
			array_push($toklist, $toktext);
			$tok2id[$tcnt] = $tok['id'];
			$tok2xml[$tcnt] = $tok;
			$tcnt++;
		};

		# Auto-detect possible NER
		# TODO: use UD tags (alternatively)
		$nerlist = getcqpner();
		$maintext .= "
			<form action='index.php?action=$action&cid=$ttxml->fileid&act=multiadd' method=post>
			<table>
			<tr><th>Add<th>Text<th>NER reference<th>NER record";
		$opts = " .//name "; $paropts = " .//ancestor::name "; 
		foreach ( getset('xmlfile/ner/tags', array()) as $key=>$tag ) {
			$opts .= " | .//{$tag['elm']}"; 
			$paropts .= " | .//ancestor::{$tag['elm']} "; 
		};
		foreach ( $nerlist as $nertype => $typelist )
		foreach ( $typelist as $line ) {
			list ($nertext, $ref) = explode("\t", $line);
			if ( $ref == "_" || $ref == "" ) continue;
			$nertoks = explode(" ", $nertext);
			$begins = array_keys($toklist, $nertoks[0]);
			foreach ( $begins as $ord => $opt ) {
				$matches = 1; $nermatch = ""; $idlist = "";
				foreach ( $nertoks as $key => $val ) {
					if ( $val != $toklist[$opt+$key] ) $matches = 0;
					$idlist .= $tok2id[$opt+$key].";";
				};
				if ( $matches ) {
					if ( !$nerlemmas[$ref] ) {
						$nerid = $ref; if ( strpos($nerid, '#') ) $nerid = substr($nerid, strpos($nerid, '#')+1);
						$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
						if ( $nerrec ) {
							$lemma = current($nerrec->xpath($opts));
							if ( !$lemma ) $lemma = "<i>No lemma found in record</i>";
							$nerlemmas[$ref] = $lemma;
						} else {
							$nerlemmas[$ref] = "<i>No such NER element: $nerid</i>";
						};
					};
					$style = "";
					$tmp = current($tok2xml[$opt]->xpath($paropts)); $parname = "";
					if ( $tmp ) { 
						$parelm = $tmp->getName();
						$parname = " ($parelm)";
						foreach ( getset('xmlfile/ner/tags', array()) as $tmp ) if ( $tmp['elm'] == $parelm ) $pardef = $tmp;
						if ( $_GET['show'] == "all" ) {
							$style = "style=\"opacity: 0.3; background-color: {$pardef['color']};\""; 
						} else {
							$style = "style=\"display: none;\""; 
						};
						$checkbox = "";
					} else {
						$checkbox = "<input type=checkbox name='sels[$ord]' value=\"1\">
							<input type=hidden name='toks[$ord]' value=\"$idlist\">
							<input type=hidden name='corresp[$ord]' value=\"$ref\">
							<input type=hidden name='type[$ord]' value=\"$nertype\">
							";
					};
					$maintext .= "<tr $style><td style='background-color: white;'>$checkbox
						<td onClick=\"jumpto('$idlist');\" onMouseOver=\"highlight('$idlist');\" style=\"color: {$typedef['color']}\">$nertext$parname
						<td>$ref<td>{$nerlemmas[$ref]}";
				};
			};
		}
		$maintext .= "</table>
			<p><input type=submit value='Add Selected NERs'>
			</form>
			<p><a href='index.php?action=$action&cid=$ttxml->fileid'>Cancel</a>";	
		if ( $_GET['show'] != "all" ) $maintext .= " &bull; <a href='{$_SERVER['REQUEST_URI']}&show=all'>Show marked NER</a>";

		$result = $ttxml->xpath($mtxtelement); 
		$txtxml = $result[0]; 
		$maintext .= "<hr><div id=mtxt>".makexml($txtxml)."</div>";

	} else if ( $_GET['cid'] && $act == "addner" ) {

		check_login(); 

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		if ( !$ttxml ) fatal("Failed to load $ttxml");
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;
		
		if ( !is_writable("xmlfiles/".$ttxml->fileid) ) fatal("Not writable: $ttxml->fileid");
	
		$nertype = $_POST['type'] or $nertype = "name";
		$nerdef = $nerlist[$nertype] or $nerdef = $nerlist[strtolower($nertype)];
		$nerelm = $nerdef['elm'] or $nerelm = "name";

		$newner = addparentnode($ttxml->xml, $_POST['toklist'], $nertype);

		$cnt=1;
		while ( $ttxml->xpath("//*[@id=\"ner-$cnt\"]") ) { $cnt++; };
		$newner->setAttribute("id", "ner-$cnt");
		
		saveMyXML($ttxml->xml->asXML(), $ttxml->fileid	);
		$nexturl = "index.php?action=$action&act=edit&cid=$fileid&nerid=".$newner->getAttribute("id");
		print "<hr><p>Your NER has been inserted - reloading to <a href='$nexturl'>the edit page</a>";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;
	
	} else if ( $act == "snippet" ) {
	
		$nerid = preg_replace("/.*#/", "", $_GET['nerid']);

		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
			if ( $nernode ) {
				$snippettxt = "<table>";
				$tmp = $nernode->getName();
				
				foreach ( $nerlist as $key => $val ) {
					$valelm = $val['indexelm'] or $valelm = $val['elm'];
					$tmp = $nernode->xpath(".//$valelm");
					if ( $tmp ) {
						$tmp2 = current($tmp);
						if ( $tmp2 ) $name = $tmp2->asXMl();
						else $name = $nerid;
						$type = $val['display'];
						break;
					};
				};
				if ( $type) $snippettxt .= "<tr><th>{%$type}:<td style='font-weight: bold;'>$name</th></tr>";

				# This does not work - think of how to do that properly...
// 				$taglist = $nerlist[$valelm.'']['options'];
// 				foreach ( $taglist as $key => $val ) {
// 					print $key;
// 					$vx = $val['xpath'];
// 					$tmp = $nernode->xpath(".//$vx");
// 					if ( $tmp ) {
// 						$vn = $val['display'];
// 						if ( $vn ) $snippettxt .= "<tr><th>{%$vn}:<td style='font-weight: bold;'>$tmp</th></tr>";
// 					};
// 				};
				
				$snippetelm = getset('xmlfile/ner/snippet', "label");
				$snippetxml = current($nernode->xpath(".//$snippetelm"));
				if ( $snippetxml ) $snippettxt .= "<tr><td colspan=2>".makexml($snippetxml)."</td></tr>";
				$snippettxt .= "</table>";
				if ( $snippettxt == "<table></table>" && $username ) {
					$snippettxt = "<i>No display data or snippet elm ($snippetelm) in <a href='index.php?action=$action&act=edit&id=$nerid'>$nerid</i>"; 
				};
			} else if ( $username ) {
				$snippettxt = "<i>Missing NER record: $nerid</i>";
			} else {
				print "<!-- Node $nerid not found -->";
			};
			if ( $_GET['debug'] ) $snippettxt .= "<hr><p>Snippet: $snippeelm <hr>".showxml($nernode);
		} else {
			print "<!-- Error loading NER -->";
		};
	
		if ( $snippettxt && $snippettxt != "<table></table>" ) print i18n($snippettxt);
		else if ( $_GET['debug'] ) print "No info: $nerid ".$nernode->asXML();
		else header("HTTP/1.0 404 Not Found");
		exit;

	} else if ( $_GET['cid'] && !$_GET['nerid'] ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%$viewname}</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();
		$maintext .= $ttxml->topswitch();
		$editxml = $ttxml->asXML();
		$maintext .= $ttxml->pagenav;

		if ( $username ) {
			$optlist = "";
			if ( getset('xmlfile/ner/tags') != '' ) {
				foreach ( getset('xmlfile/ner/tags', array()) as $key => $tag ) {
					$optlist .= "<option value='$key'>{$tag['display']}</option>";
				};
			} else $optlist = "<option value='term'>term</option><option value='placeName'>placeName</option><option value='persName'>persName</option><option value='orgName'>orgName</option>";
			$maintext .= "<div id='addner' style='float: right; width: 200px; display: none; border: 1px solid #aaaaaa;'>
				<form action='index.php?action=$action&act=addner&cid=$ttxml->fileid' method=post>
				<input id='toklist' name='toklist' type=hidden>
				<table width='100%'>
					<tr><th colspan=2>Add NER
					<tr><th>Span<td id='nerspan'>
					<tr><th>Type<td><select name=type>$optlist</select>
					<tr><td colspan=2><input type=submit value='Create'>
				</table>
				</form></div>
				";
			$maintext .= "<div id=mtxt onmouseup='makespan(event);'>$editxml</div>";
		} else  $maintext .= "<div id=mtxt>".$ttxml->asXML()."</div>";
		
		$maintext .= "<hr>".$ttxml->viewswitch();
		$maintext .= " &bull; <a href='index.php?action=$action&act=list&cid=$ttxml->fileid'>{%List names}</a>";
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=$action&act=detect&cid=$ttxml->fileid' class=adminpart>Auto-detect names</a>";
				
		$maintext .= "
			<style>
				#mtxt tok:hover { text-shadow: none;}
			</style>
			<script language=Javascript>
			var username = '$username';
			var nerlist = $nerjson;
			var hlid = '{$_GET['hlid']}';
			var jmp = '{$_GET['jmp']}';
			var fileid = '$ttxml->fileid';
			$moreaction
			</script>
			<script language=Javascript src=\"$jsurl/ner.js\"></script>";
		
	} else if ( $_GET['nerid'] ) {
	
		$nerid = preg_replace("/.*#/", "", $_GET['nerid']);
		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
		};

		$type = strtolower($_GET['type']);
		if ( !$type && $nernode ) $type = $nernode->getName();
		$nerdef = $nerlist[$type]; if ( !$nerdef ) foreach ( $nerlist as $key => $val ) {
			if ( $val['node'] == $type ) $nerdef = $val;
		};

		$subtype = $_GET['subtype'];

		if ( $nernode ) {
			$nameelm = $nerlist[$type]['elm'] or $nameelm = "name";
			$name = current($nernode->xpath(".//$nameelm"))."";
		};
		if ( !$name && $_GET['name'] ) $name = "<i>".$_GET['name']."</i>";
		if ( !$name ) $name = $nerdef['display'];
		if ( !$name ) $name = $nernode->textContent;
		if ( !$name ) $name = $nerid;
	
		if ( !$nernode ) {
			fatal("No such record: $nerid");
		};

		foreach ( $nernode->xpath("figure") as $fig ) {
			$src = getxpval($fig, "graphic/@url");
			$fh = getxpval($fig, "head");
			$fd = getxpval($fig, "figDesc");
			$maintext .= "<div style='float: right; width: 300px;'><p style='font-weight: bold'>$fh</p><img src='$src' width='300px'/><p style='font-style: italic'>$fd</p></div>";
		};

		$nername = $nerdef['display'] or $nername = $type;
		$maintext .= "<h2>{%$nertitle}</h2><h1>$name</h1>
		<p>Type of $neritemname: <b>$nername</b>";

		
		$snippetelm = getset('xmlfile/ner/snippet', "gloss");
		$snippetxml = current($nernode->xpath(".//$snippetelm"));
		if ( $snippetxml ) $maintext .= "<div style='padding: 10px; border: 1px solid #aaaaaa;'>".$snippetxml->asXML()."</div>";

	
		$subtypefld = $nerlist[$type]['subtypes']['fld'] or $subtypefld = "type";
		$subdisplay = $nerlist[$type]['subtypes'][$subtype]['display'] or $subdisplay = $subvalue;
		if ( $subdisplay ) $maintext .= "<p>Subtype: <b>$subdisplay</b>";
	
		$descflds = $nerdef['descflds'] or $descflds = array ("note", "desc", "gloss", "head", "label");
		if ( $nerdef['options'] && $nernode ) {
			$maintext .= "<p><table>";
			foreach ( $nerdef['options'] as $key => $val ) {
				$fxp = "./".$val['xpath'];
				$fval = current($nernode->xpath($fxp));
				if ( $fval ) {
					$nodename = $fval->getName();
					$fdisp = $val['display'] or $fdisp = $key;
					if ( in_array( $nodename, $descflds ) ) {
						$maintext .= "<tr><td colspan=2>".makexml($childnode);
					} else if ( trim($fval) != "" && !$val['noshow']  ) {
						$maintext .= "<tr><th>{$fdisp}<td>$fval";
					};
				};
			};
			$maintext .= "</table>";
		} else if ( $nernode ) {
			$maintext .= "<table>";
			foreach ( $nernode->children() as $childnode ) {
				$nodename = $childnode->getName();
				if ( $nodename == $snippetelm ) continue;
				if ( in_array( $nodename, $descflds ) ) {
					$maintext .= "<tr><td colspan=2>".makexml($childnode);
				} else if ( trim($childnode) != "" && $nodename != $nameelm ) {
					$maintext .= "<tr><th>{%$nodename}<td>$childnode";
				};
			};
			$maintext .= "</table>";
			$links = $nernode->xpath(".//linkGrp/link");
			if ( $links ) {
				$maintext .= "<p><h2>{%External links}</h2><table>";
				foreach ( $links as $key => $val ) {
					if ( substr($val['target'],0,4) == "http" ) {
						$linktitle = $val['display'] or $linktitle = "{%{$val['target']}}";
						$maintext .= "<tr><th>{%{$val['type']}}<td><a href='{$val['target']}' target=info>$linktitle</a>";
					};
				};
				$maintext .= "</table>";
			};
		} else if ( substr($nerid,0,4) == "http") {
			$maintext .= "<p>Reference: <a href='{$_GET['nerid']}'><b>{$nerid}</b></a></p>";
		} else if ( substr($nerid, 0, 1) == "#" ) {
			if ( $_GET['cid']) {
				require("$ttroot/common/Sources/ttxml.php");
				$ttxml = new TTXML();
				$fileid = $ttxml->fileid;
				$xmlid = $ttxml->xmlid;
				$xml = $ttxml->xml;

				$idxp = "//*[@id=\"".substr($nerid,1)."\" or @xml:id=\"".substr($nerid,1)."\"]";
				$idnode = $xml->xpath($idxp);
				$idtxt = ""; $sep = "";
				if ( $idnode ) $maintext .= makexml($idnode[0]);
			};
		};
	
		if ( $username ) {
			$maintext .= "<hr><p><a href='index.php?action=$action&act=neredit&nerid=$nerid'>edit NER record</a>   &bull; <a href='index.php?action=$action'>back to list</a>";
		};
	
		# Lookup all occurrences
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = getset('cqp/cqpfolder', "cqp");

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$nodetype = $nerdef['elm'];
		$nodeatt = $nerdef['cqp'];

		$tmp = getset("xmlfile/ner/deffld");
		if ( $tmp ) $defcol = ", match $tmp";

		$cql = "Matches = <$nodeatt> []+ </$nodeatt> :: match.{$nodeatt}_nerid=\".*#?{$_GET['nerid']}\"";
		$cqp->exec($cql); 
		$size = max(0, $cqp->exec("size Matches"));
		if ( $size ) {
			$maintext .= "<h2 style='margin-top: 20px;'>{%Occurrences}</h2>";
			$results = $cqp->exec("tabulate Matches match, matchend, match text_id, match id $defcol");
			$xidxcmd = findapp("tt-cwb-xidx");

			$csize = getset('xmlfile/ner/context', 0);
			if ( $csize) {
				$expand = "--context=$csize";			
			};

			$neridtxt = str_replace("/", "\/", preg_quote($nerid));			
		};
		
		$maintext .= "<div id=mtxt><table cellpadding=2>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $leftpos, $rightpos, $fileid, $tokid, $defval ) = explode("\t", $resline);
			if ( !$fileid ) continue;
			$cmd = "$xidxcmd --filename='$fileid' --cqp='$cqpfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);
			
			if ( $csize ) { 
				$resxml = preg_replace("/ ({$nerdef['nerid']}=\"([^\"]*#)?$neridtxt\")/", " \1 hl=\"1\"", $resxml);
				# Replace block-type elements by vertical bars
				$resxml = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(doc)>\s*|<(doc) [^>]*>\s*)+/", " <span style='color: #995555; font-weight: bold;' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml); # non-standard section break
				$resxml = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(table|cell|row)(?=[ >])[^>]*>\s*)+/", " ", $resxml);
			};
			$context = preg_replace("/.*\/(.*?)\.xml/", "\\1", $fileid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&jmp=$tokid&hlid=".urlencode($_GET['nerid'])."'>$context</a><td style='padding-left: 7px; padding-right: 7px; '>$resxml<td style='opacity: 0.5;'>$defval";
		};
		$maintext .= "</table></div>";
		

	} else if ( $_GET['type'] || count($nerlist) == 1 ) {
	
		$type = strtolower($_GET['type']) or $type = current(array_keys($nerlist));
		$subtypefld = $nerlist[$type]['subtypes']['fld'] or $subtypefld = "type";

		# List of types of NER we have
		$nername = $nerlist[$type]['display'];
		$neratt = $nerlist[$type]['cqp'];
		$nerform = getset('xmlfile/ner/form', "form");
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%$nername}</h1>";

		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = getset('cqp/cqpfolder', "cqp");

		# Sanity check

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cql = "Matches = <$neratt> []"; 
		$cqp->exec($cql); 
		
		$cql2 = "group Matches match {$neratt}_{$nerform} by match {$neratt}_nerid";
		$results = $cqp->exec($cql2); 

		if ( $debug ) $maintext .= "<p>Query: $cql / $cql2";
		
		$rowhash = array();
		foreach ( explode("\n", $results) as $resline ) {
			list ( $nerid, $form, $cnt ) = explode("\t", $resline);
			if ( $form == '' || $form == '_') $form = $nerid;
			if ( $form == '' || $form == '_') continue;
			$rowhash[$form] = "<tr key='$name'><td><a href='index.php?action=$action&nerid=".urlencode($nerid)."&type=$type&name=$form'>$form</a></tr>";
		};
		ksort($rowhash);
		$maintext .= "<table>".join("\n", array_values($rowhash))."</table>";

	} else {
	
		# List of types of NER we have
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%Select}</h1>";
		
		$maintext .= getlangfile("nerlisttext");
		
		foreach ( $nerlist as $key => $val ) {
			$maintext .= "<p><a href='index.php?action=$action&type=$key'>{$val['display']}</a>";
		};

	};
	
	function getcqpner( $type = "all" ) {
		# Get the list of all existing NER from the CQP corpus
		global $settings, $ttroot;
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = getset('cqp/cqpfolder', "cqp");

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$resarr	= array();
		foreach ( getset('xmlfile/ner/tags', array()) as $key => $tag ) {
			if ( ( $key == $type || $type == "all" ) && $tag['cqp'] ) {
				$cqpelm = $tag['cqp']."";
				$cqp->exec("Matches = <$cqpelm> []+ </$cqpelm>");
				$cqp->exec("sort Matches by form");
				$tmp = $cqp->exec("tabulate Matches match[0]..matchend[0] form, match {$cqpelm}_nerid");
				$resarr[$key] = array_unique(explode("\n", $tmp));
			};
		};
		return $resarr;
		
	};
	
	function addparentnode( $xml, $toklist, $parent ) {

		$dom = dom_import_simplexml($xml)->ownerDocument;
		$xpath = new DOMXpath($dom);

		# Attempt to add an element around the indicated tokens
		$idlist = explode(";", preg_replace("/;+$/", "", $toklist));
		$first = $idlist[0]; $last = end($idlist);
		
		$tmp = $xpath->query("//tok[@id=\"$first\"]");
		if ( !$tmp ) return -1; // fatal ("Token not found: $first");
		$el1 = $tmp->item(0);
		
		$newner = $dom->createElement($parent);
		
		$el1->parentNode->insertBefore($newner, $el1); $nextnode = $newner;
		if ( $debug ) { print "<p>Created: ".htmlentities($dom->saveXML($nextnode)); };
		while ( $nextnode  ) {
			$nextnode = $newner->nextSibling;
			if ( $debug ) { print "<p>Adding: ".htmlentities($dom->saveXML($nextnode)); };
			$tmp = $newner->appendChild($nextnode);
			if ( $nextnode->nodeType == 1 && $nextnode->getAttribute('id') == $last ) break;
		}; 
		
		return $newner;

	};

	function delparentnode( $xml, $parentid ) {

		$prnt = current($xml->xpath("//*[@id=\"$parentid\"]"));
		if ( !$prnt ) return -1;
	
		$dom = dom_import_simplexml($xml);
		$pd = dom_import_simplexml($prnt);
		print "<p>Moving childnodes out of parent $parentid";
				
		print showxml($prnt);
		$childs = $pd->childNodes;
		foreach ( $childs as $child  ) {
			print "<p>".htmlentities($dom->ownerDocument->saveXML($child));
			$newchild = $dom->ownerDocument->importNode($child->cloneNode(true),true);
			$pd->parentNode->insertBefore($newchild, $pd);
		}; 
		$pd->parentNode->removeChild($pd);
		
		return;

	};


?>