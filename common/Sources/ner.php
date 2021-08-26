<?php

	# Name-oriented document view and name index
	# Maarten Janssen, 2020

	$viewname = $settings['xmlfile']['ner']['title'] or $viewname = "Named Entity View";

	if ( !$_GET['cid'] ) $_GET['cid']  = $_GET['id'];
	$nertitle = $settings['xmlfile']['ner']['title'] or $nertitle = "Named Entities";
	$neritemname = $settings['xmlfile']['ner']['item'] or $neritemname = "entity";

	$nerlist = $settings['xmlfile']['ner']['tags'] 
		or 
		$nerlist = array(
			"placename" => array ( "display" => "Place Name", "cqp" => "place", "elm" => "placeName", "nerid" => "ref" ), 
			"persname" => array ( "display" => "Person Name", "cqp" => "person", "elm" => "persName", "nerid" => "ref" ), 
			"name" => array ( "display" => "Name", "cqp" => "name", "elm" => "name", "nerid" => "ref" ),
			"term" => array ( "display" => "Term", "cqp" => "term", "elm" => "term", "nerid" => "ref" ),
			);
	$nerjson = array2json($nerlist);

	$nerfile = $settings['xmlfile']['ner']['nerfile'] or $nerfile = "ner.xml";
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

		$maintext .= "<table id='nertable'>";
		foreach ( $nerlist as $key => $val ) {
			$nodelist = $xml->xpath("//text//{$val['elm']}");
			unset($idnames);
			if ( $nodelist ) {
				$maintext .= "<tr><td colspan=2 style='padding-top: 10px; padding-bottom: 10px; '><b style='font-size: larger;'>{$val['display']}</b></tr>";
			
				foreach ( $nodelist as $node ) {
					$nerid = $node[$val['nerid']];
					$name = $node->asXML() or $name = $nerid;
					if ( $settings['xmlfile']['nospace'] == "2" ) $name = $name = preg_replace("/<\/tok>/", " ", $name);
					$name = preg_replace("/<[^>]+>/", "", $name);
					$idnames[$nerid.""][$name.""]++;
					$idcnt[$nerid.""]++;
				};
			};	
			foreach ( $idnames as $nerid => $val ) {
				$name = join("<br/>", array_keys($val));
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
				} else $idtxt = "<i style='opacity: 0.5'>$nerid</i>";
				$cidr = ""; if ( substr($nerid,0,1) == "#" ) $cidr = "&cid=".$ttxml->fileid;
				if ( $trc == "odd" ) $trc = "even"; else $trc = "odd";
				$maintext .= "<tr key='$name' class='$trc'><td title='{%Lemma}'><a href='index.php?action=$action&type=$key&nerid=".urlencode($nerid)."$cidr'>$name</a>
					<td>$idtxt
					<td style='opacity: 0.5; text-align: right; padding-left: 10px;' title='{%Occurrences}'>{$idcnt[$nerid]}";
			};
		};
		$maintext .= "</table>
				<hr> <a href='index.php?action=$action&cid={$ttxml->fileid}'>{%back}</a>";

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
		$elm = $result[0]; # print_r($token); exit;
		if ( !$elm ) fatal("No such element: $nerid");
		$etype = $elm->getName();
		
		foreach ( $settings['xmlfile']['ner']['tags'] as $tmp ) if ( $tmp['elm'] == $etype ) $nerdef = $tmp;
		$sattdef = $settings['xmlfile']['sattributes'][$etype];
		
		$maintext .= "<h2>Edit Named Entity</h2>
			<h1>".$ttxml->title()."</h1>
			<h2>Entity type ($nerid): ".$etype." = {$sattdef['display']}</h2>
			
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$nerid'>
			<table>";

		foreach ( $sattdef as $key => $item ) {
			if ( !is_array($item) ) continue;
			$itemtxt = $item['display'];
			$atv = $elm[$key]; 
			$maintext .= "<tr><th>$key<td>$itemtxt<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
		};

		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 

		$maintext .= "</table>";

		$maintext .= "<hr>
		<input type=submit value=\"Save\">
		<button onClick=\"window.open('index.php?action=file&cid=$fileid', '_self');\">Cancel</button></form>
		<!-- <a href='index.php?action=file&cid=$fileid'>Cancel</a> -->
		<hr><div id=mtxt>".$txtxml->asXML()."</div>
		<script language=Javascript>
			var telm = document.getElementById('$nerid');
			telm.style.backgroundColor = '#ffffaa';
		</script>
		";

		$correspid = $elm['corresp']; $elmtext = preg_replace("/<[^>]+>/", "", $elm->asXML());
		if ( $correspid ) {
			$nerid = $correspid; if ( strpos($nerid, '#') ) $nerid = substr($nerid, strpos($nerid, '#')+1);
			$nertype = $nerdef['key'];
			$maintext .= "<hr><h2>Linked Entity $nerid</h2>
			<p><a href='index.php?action=$action&type=$nertype&nerid=".urlencode($correspid)."'>Go to occurrences</a>";
			if ( $nerxml ) {
				$nerrec = current($nerxml->xpath("//*[@id=\"$nerid\"]"));
				if ( $nerrec ) {
					$maintext .= "<p><pre>".htmlentities($nerrec->asXML())."</pre>";
				} else {
					$maintext .= "<i>No such NER element: $nerid</i>";
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
					$linkoptions .= "<tr><td><a onclick=\"var celm = document.getElementById('tagform').elements['atts[corresp]']; if ( celm ) { celm.value = '$ref'; } else { alert('no corresp'); };\">$ref</a><td>$nertext<td>$nerlemma";
				};
			};
			
			if ( $linkoptions ) $maintext .= "<h2>Potential Links for '$elmtext'</h2>
				<table>
				<tr><th>Reference ID<th>Previous occurrence<th>Reference form
				$linkoptions
				</table>
				";
		};

	} else if ( $_GET['cid'] && $act == "multiadd" ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();

		print "Adding NER nodes";
		foreach ( $_POST['sels'] as $key => $val ) {
			$nertype = $_POST['type'][$key];
			$nernode = $settings['xmlfile']['ner']['tags'][$nertype]['elm'];
			$toklist = $_POST['toks'][$key];
			print "<hr>$key: {$_POST['toks'][$key]} = $nernode  / {$_POST['corresp'][$key]}</hr>";
			$newner = addparentnode($ttxml->xml, $toklist , $nernode);
			$newner->setAttribute('corresp', $_POST['corresp'][$key]);
		};
		
		print "<hr>";

		$fileid = $ttxml->fileid;
		saveMyXML($ttxml->xml->asXML(), $ttxml->filename);
		$nexturl = "index.php?action=renumber&cid=$fileid";
		print "<hr><p>Your NERs have been inserted - reloading to renumber page";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;

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
		foreach ( $ttxml->xml->xpath("//tok") as $tok ) {
			$toktext = preg_replace("/<[^>]+>/", "", $tok->asXML());
			array_push($toklist, $toktext);
			$tok2id[$tcnt] = $tok['id'];
			$tok2xml[$tcnt] = $tok;
			$tcnt++;
		};

		# Auto-detect possible NER
		$nerlist = getcqpner();
		$maintext .= "
			<form action='index.php?action=$action&cid=$ttxml->fileid&act=multiadd' method=post>
			<table>
			<tr><th>Add<th>Text<th>NER reference<th>NER record";
		$opts = " .//name "; $paropts = " .//ancestor::name "; 
		foreach ( $settings['xmlfile']['ner']['tags'] as $key=>$tag ) {
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
						foreach ( $settings['xmlfile']['ner']['tags'] as $tmp ) if ( $tmp['elm'] == $parelm ) $pardef = $tmp;
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

		$result = $ttxml->xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 
		$maintext .= "<hr><div id=mtxt>".$txtxml->asXML()."</div>";

	} else if ( $_GET['cid'] && $act == "addner" ) {

		check_login(); 

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		if ( !$ttxml ) fatal("Failed to load $ttxml");
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;
		
		if ( !is_writable("xmlfiles/".$ttxml->filename) ) fatal("Not writable: $ttxml->filename");
	
		$nertype = $_POST['type'] or $nertype = "name";
		$nerdef = $settings['xmlfile']['ner']['tags'][$nertype];
		$nerelm = $nerdef['elm'] or $nerelm = "name";

		$newner = addparentnode($ttxml->xml, $_POST['toklist'], $nerelm);

		$cnt=1;
		while ( $ttxml->xml->xpath("//*[@id=\"ner-$cnt\"]") ) { $cnt++; };
		$newner->setAttribute("id", "ner-$cnt");
		
		saveMyXML($ttxml->xml->asXML(), $ttxml->filename);
		$nexturl = "index.php?action=$action&act=edit&cid=$fileid&nerid=".$newner->getAttribute("id");
		print "<hr><p>Your NER has been inserted - reloading to <a href='$nexturl'>the edit page</a>";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;
	
	} else if ( $act == "snippet" ) {
	
		$nerid = preg_replace("/.*#/", "", $_GET['nerid']);

		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
			if ( $nernode ) {
				# print $nernode->asXML();
				$snippettxt = "<table>";
				$tmp = $nernode->getName();
				foreach ( $nerlist as $key => $val ) {
					$valelm = $val['indexelm'] or $valelm = $val['elm'];
					$tmp = $nernode->xpath(".//$valelm");
					if ( $tmp ) {
						$name = current($tmp)."";
						$type = $val['display'];
						last;
					};
				};
				if ( $type) $snippettxt .= "<tr><th>{%$type}:<td style='font-weight: bold;'>$name</th></tr>";
				$snippetelm = $settings['xmlfile']['ner']['snippet'] or $snippetelm = "label";
				$snippetxml = current($nernode->xpath(".//$snippetelm"));
				if ( $snippetxml ) $snippettxt .= "<tr><td colspan=2>".$snippetxml->asXML()."</td></tr>";
				$snippettxt .= "</table>";
			} else {
				print "<!-- Node $nerid not found -->";
			};
		} else {
			print "<!-- Error loading NER -->";
		};
	
		if ( $snippettxt && $snippettxt != "<table></table>" ) print i18n($snippettxt);
		else if ( $_GET['debug'] ) print "No info: $nerid ".$nernode->asXML();
		else header("HTTP/1.0 500 Internal Server Error");
		exit;

	} else if ( $_GET['cid'] && !$_GET['nerid'] ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%$viewname}</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();
		$editxml = $ttxml->asXML();
		$maintext .= $ttxml->pagenav;

		if ( $username ) {
			$optlist = "";
			if ( $settings['xmlfile']['ner']['tags'] ) {
				foreach ( $settings['xmlfile']['ner']['tags'] as $key => $tag ) {
					$optlist .= "<option value='$key'>{$tag['display']}</option>";
				};
			} else $optlist = "<option value='term'>term</option><option value='placeName'>placeName</option><option value='personName'>personName</option><option value='orgName'>orgName</option>";
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
		if ( $username ) $maintext .= " &bull; <a href='index.php?action=$action&act=detect&cid=$ttxml->fileid' class=adminpart>{%Auto-detect names}</a>";

		// Load the tagset 
		if ( $settings['xmlfile']['ner']['tagset'] != "none" ) {
			$tagsetfile = $settings['xmlfile']['ner']['tagset'] or $tagsetfile = "tagset-ner.xml";
			require ( "$ttroot/common/Sources/tttags.php" );
			$tttags = new TTTAGS($tagsetfile, false);
			if ( $tttags->tagset['positions'] ) {
				$tmp = $tttags->xml->asXML();
				$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
				$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
			};
		};
				
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
		$type = strtolower($_GET['type']);
		$subtype = $_GET['subtype'];
		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
			if ( $nernode ) {
				$nameelm = $nerlist[$type]['elm'] or $nameelm = "name";
				$name = current($nernode->xpath(".//$nameelm"))."";
			};
		};
		if ( !$name && $_GET['name'] ) $name = "<i>".$_GET['name']."</i>";
		if ( !$name ) $name = $nerid;
	
		$maintext .= "<h2>{%$nertitle}</h2><h1>$name</h1>
		<p>Type of $neritemname: <b>{$nerlist[$type]['display']}</b>";
	
		$subtypefld = $nerlist[$type]['subtypes']['fld'] or $subtypefld = "type";
		$subdisplay = $nerlist[$type]['subtypes'][$subtype]['display'] or $subdisplay = $subvalue;
		if ( $subdisplay ) $maintext .= "<p>Subtype: <b>$subdisplay</b>";
	
		if ( $nernode ) {
			$descflds = $nerlist[$type]['descflds'] or $descflds = array ("note", "desc", "head", "label");
			# $maintext .= "<div>".htmlentitieS($nernode->asXML())."</div>";
			$maintext .= "<table>";
			foreach ( $nernode->children() as $childnode ) {
				$nodename = $childnode->getName();
				if ( in_array( $nodename, $descflds ) ) {
					$maintext .= "<tr><td colspan=2>".$childnode->asXML();
				} else if ( trim($childnode) != "" && $nodename != $nameelm ) {
					$maintext .= "<tr><th>{%$nodename}<td>$childnode";
				};
			};
			$maintext .= "</table>";
			$links = $nernode->xpath(".//linkGrp/link");
			if ( $links ) {
				$maintext .= "<h2>{%External links}</h2><table>";
				foreach ( $links as $key => $val ) {
					if ( substr($val['target'],0,4) == "http" ) $maintext .= "<tr><th>{%{$val['type']}}<td><a href='{$val['target']}' target=info>{%{$val['target']}}</a>";
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
				if ( $idnode ) $maintext .= $idnode[0]->asXML();
			};
		};
	
		# Lookup all occurrences
		$maintext .= "<h2 style='margin-top: 20px;'>{%Occurrences}</h2>";
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$nodetype = $nerlist[$type]['elm'];
		$nodeatt = $nerlist[$type]['cqp'];


		$cql = "Matches = <$nodeatt> []+ </$nodeatt> :: match.{$nodeatt}_nerid=\"{$_GET['nerid']}\"";
		$cqp->exec($cql); 
		$results = $cqp->exec("tabulate Matches match, matchend, match text_id, match id");

		$xidxcmd = findapp("tt-cwb-xidx");

		$csize = $settings['xmlfile']['ner']['context'] or $csize = 0;
		if ( $csize) {
			$expand = "--context=$csize";			
		};

		$neridtxt = str_replace("/", "\/", preg_quote($nerid));
			
		$maintext .= "<div id=mtxt><table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $leftpos, $rightpos, $fileid, $tokid ) = explode("\t", $resline);
			if ( !$fileid ) continue;
			$cmd = "$xidxcmd --filename='$fileid' --cqp='$cqpfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);
			
			if ( $csize ) {
				$resxml = preg_replace("/ ({$nerlist[$type]['nerid']}=\"([^\"]*#)?$neridtxt\")/", " \1 hl=\"1\"", $resxml);
				# Replace block-type elements by vertical bars
				$resxml = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(doc)>\s*|<(doc) [^>]*>\s*)+/", " <span style='color: #995555; font-weight: bold;' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml); # non-standard section break
				$resxml = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(table|cell|row)(?=[ >])[^>]*>\s*)+/", " ", $resxml);
			};
			$context = preg_replace("/.*\/(.*?)\.xml/", "\\1", $fileid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&jmp=$tokid&hlid=".urlencode($_GET['nerid'])."'>$context</a><td>$resxml";
		};
		$maintext .= "</table></div>";

	} else if ( $_GET['type'] ) {

		$type = strtolower($_GET['type']);
		$subtypefld = $nerlist[$type]['subtypes']['fld'] or $subtypefld = "type";

		# List of types of NER we have
		$nername = $nerlist[$type]['display'];
		$neratt = $nerlist[$type]['cqp'];
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%$nername}</h1>";

		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cql = "Matches = <$neratt> []"; 
		$cqp->exec($cql); 
		
		$cql = "group Matches match {$neratt}_form by match {$neratt}_nerid";
		$results = $cqp->exec($cql); 
		
		foreach ( explode("\n", $results) as $resline ) {
			list ( $nerid, $form, $cnt ) = explode("\t", $resline);
			if ( $form == '' || $form == '_') $form = $nerid;
			$rowhash[$form] = "<tr key='$name'><td><a href='index.php?action=$action&nerid=".urlencode($nerid)."&type=$type&name=$form$'>$form</a></tr>";
		};
		ksort($rowhash);
		$maintext .= "<table>".join("\n", array_values($rowhash))."</table>";

	} else {
	
		# List of types of NER we have
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%Select}</h1>";
		
		foreach ( $nerlist as $key => $val ) {
			$maintext .= "<p><a href='index.php?action=$action&type=$key'>{$val['display']}</a>";
		};

	};
	
	function getcqpner( $type = "all" ) {
		# Get the list of all existing NER from the CQP corpus
		global $settings, $ttroot;
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$resarr	= array();
		foreach ( $settings['xmlfile']['ner']['tags'] as $key => $tag ) {
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

		# Attermpt to add an element around the indicated tokens
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

?>