<?php

	# Visualization of dependency trees
	# (c) Maarten Janssen, 2016
	
	$deplabels = getset('deptree', array());
	if ( $deplabels['rooted'] ) $rooted = 1; 
	if ( !$deplabels['labels'] ) {
		$deptreexml = simplexml_load_file("$ttroot/common/Resources/deptree.xml");
		$deplabels = xmlflatten($deptreexml);
	}; 

	$flddeprel = $_GET['deprel'] or $flddeprel = "deprel";
	$fldhead = $_GET['head'] or $fldhead = "head";

	$stlist = getset('xmlfile/sattributes/s/status/options');
	if ( !$stlist )	$stlist = array ( 
		"auto" => array("display" => "automatically assigned"),
		"corrected" => array("display" => "manually corrected"),
		"checked" => array("display" => "manually verified", "color" => "green"),
		"wrong" => array("display" => "wrong - to correct"),
		);

	# Define what counts as a token in the dependency graph	
	$toksel = ".//mtok[not(./dtok)] | .//tok[not(dtok) and not(ancestor::mtok)] | .//dtok[not(ancestor::tok/ancestor::mtok)]";
	
	$maintext .= "<h1>Dependency Tree</h1>"; 

	$treeview = $_GET['view'] or $treeview = $_SESSION['treeview'] or $treeview = "tree";
	$_SESSION['treeview'] = $treeview;

	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, true); # TODO: we did keepns before, why? (breaks //s test)
	
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 

	$sid = $_GET['sid'] or $sid = $_GET['sentence'];
	$cid = $ttxml->fileid;

	if ( getset('xmlfile/basedirection') != '' ) {
		// Defined in the settings
		$textdir = "dir='".getset('xmlfile/basedirection')."'";
	} else {
		$dirxpath = getset('xmlfile/direction');
		if ( $dirxpath ) {
			$tdval = current($ttxml->xpath($dirxpath));
		};
		if ( $tdval ) {
			// Defined in the teiHeader for mixed-writing corpora
			$textdir = "dir='$tdval' style='text-direction: $tdval;'";
			$morejs .= "var basedirection = '$tdval';";
		};
	};

	if ( $_POST['action'] == "save" ) { $act = "save"; };


	if ( $act == "save" && $_POST['sent'] ) {

		$sent =	current($ttxml->xpath("//s[@id='$sid']"));
		$newsent = simplexml_load_string($_POST['sent']);
		
		foreach ( $newsent->xpath($toksel) as $newtok ) {
			$tokid = $newtok['id'];
			$tok = current($sent->xpath(".//*[@id='$tokid']"));
			if ( $tok['head']."" != $newtok['head']."" ) {
				print "<p>$tokid: {$tok['head']} => {$newtok['head']}</p>";
				$tok['head'] = $newtok['head'];
				$somedone = 1;
			};
			if ( $tok['deprel']."" != $newtok['deprel']."" ) {
				print "<p>$tokid: {$tok['deprel']} => {$newtok['deprel']}</p>";
				$tok['deprel'] = $newtok['deprel'];
				$somedone = 1;
			};
		};
		if ( $somedone && ( $sent['status'] == "" || $sent['status'] == "none" ) ) {
			$sent['status'] = "corrected";
		};
		$ttxml->save();
		print "<script>top.location='index.php?action=$action&act=edit&sid=$sid&cid=$ttxml->fileid';</script>";
		exit;		

	} else if ( $act == "autofill" ) {

		$sent =	current($ttxml->xpath("//s[@id='$sid']"));

		foreach ( $sent->xpath ($toksel) as $tok ) {
			
			if ( $tok['upos'] == "PUNCT" ) $tok['deprel'] = "punct";
			if ( $tok['upos'] == "DET" ) $tok['deprel'] = "det";
			if ( $tok['upos'] == "AUX" ) $tok['deprel'] = "aux";
		
			print "<p>".htmlentities($tok->asXML());
		};	 
		$ttxml->save();
		print "<p>Autofill completed. Reloading";
		print "<script>top.location='index.php?action=$action&act=edit&sid=$sid&cid=$ttxml->fileid';</script>";
		exit;	

	} else if ( $act == "metaedit" ) {

		$maintext .= "<h1>Edit Sentence Metadata</h1>";
		$sent =	current($ttxml->xpath("//s[@id='$sid']"));

		$maintext .= "<div id=mtxt>".makexml($sent)."</div>
		<hr>
		<form action='index.php?action=$action&cid=$ttxml->fileid&sid=$sid&act=changesent' method=post>
		<table>";

		foreach ( getset('xmlfile/sattributes/s', array()) as $key => $val ) {
			if ( !is_array($val) || $val['noshow'] || $val['nodeptree'] || $key == "id" ) continue;
			if ( $val['color'] ) $style = " style=\"color: {$val['color']}\"";
			$xval = $sent[$key];
			$maintext .= "<tr><td><th>{$val['display']}<td><input name='sent[$key]' size=80 value=\"$xval\"></tr>";
		};
		$maintext .= "</table><hr><p><input type=submit value=Save> &bull; <a href='index.php?action=$action&cid=$ttxml->fileid&sid=$sid'>cancel</a></form>";

	} else if ( $act == "changesent" ) {

		$sent =	current($ttxml->xpath("//s[@id='$sid']"));

		foreach ( $_POST['sent'] as $key => $val ) {
			$sent[$key] = $val;			
		};	 
		$ttxml->save();
		print "<p>Autofill completed. Reloading";
		print "<script>top.location='index.php?action=$action&act=edit&sid=$sid&cid=$ttxml->fileid';</script>";
		exit;	

	} else if ( $act == "conllu" ) {

		$ccid = $ttxml->xmlid;
		
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$ccid.'.conllu"');
		if ( file_exists("$toolroot/Scripts/teitok2conllu.pl") && !$_GET['sid'] && 1==2 ) {
			$cmd = "perl $toolroot/Scripts/teitok2conllu.pl xmlfiles/".$ttxml->filename;
			print shell_exec($cmd);			
			exit;
		} else {
			print "# newdoc id = $ccid\n";
			if ( $_GET['sid'] ) $sid = "[@id=\"{$_GET['sid']}\"]";

			$formfld = getset('udpipe/tagform', "form");
			if ( getset('xmlfile/wordfld') != '' ) $formfld = getset('xmlfile/wordfld');
			
			
			foreach ( $ttxml->xpath("//s$sid") as $sent ) {
				$toks = $sent->xpath($toksel);
			
				if ( !$toks ) {
					$rawtext = "";
					if ( !$sent['sameAs'] ) continue;
					
					print "# sent_id = $ccid:{$sent['id']}\n";
					$idlist = explode(" ", $sent['sameAs'] );
					if ( !$id2tok ) {
						foreach ( $ttxml->xpath($toksel) as $tok ) {
							$id2tok[$tok['id'].""] = $tok;
						};
					};
					$tnr = 0; $toklist = array ();
					foreach ( $idlist as $idx ) {
						$tok = $id2tok[substr($idx,1)];
						$form = forminherit($tok, $formfld);
						if ( $form != "--" ) {
							$tnr++;	 $toknr = $tnr;
							$ids[$tok['id'].""] = $tnr;
					
							array_push($toklist, $tok);
							$rawtext .= "$form";
							if ( $tok->xpath("following-sibling::node()") && current($tok->xpath("following-sibling::node()"))->getName()."" != "tok" ) $rawtext .= " ";
						};
					};
					print "# text = $rawtext\n";
				
				} else {
					print "# sent_id = $ccid:{$sent['id']}\n";
					$rawtext = trim(preg_replace("/[\n\r\s \t]+/", " ", strip_tags($sent->asXML())));
					print "# text = $rawtext\n";
					$tnr = 0; $toklist = array ();
					foreach ( $toks as $tok ) {
						$form = forminherit($tok, $formfld);
						if ( $form != "--" ) {
							$tnr++;	 $toknr = $tnr;
							$ids[$tok['id'].""] = $tnr;
					
							array_push($toklist, $tok);
						};
					};
				};
				
				foreach ( $toklist as $tok ) {		
			
					$form = forminherit($tok, $formfld);
					$lemma = $tok['lemma'] or $lemma = "_";
					$tokid = $tok['id']."";
					$tnr = $ids[$tokid];
				
					$upos = $tok['upos'] or $upos = $tok['pos'] or $upos = "_";
					$xpos = $tok['xpos'] or $xpos = "_";
					$head = $ids[$tok[$fldhead].""] or $head = "0";
					$feats = $tok['feats'] or $feats = "_";
					$deprel = $tok[$flddeprel] or $deprel = "_";
					$deps = $tok['deps'] or $deps = "_";
					$misc = "_";
					$maintok = "$form\t$lemma\t$upos\t$xpos\t$feats\t$head\t$deprel\t$deps\t$misc";
					
					print "$tnr\t$form\t$lemma\t$upos\t$xpos\t$feats\t$head\t$deprel\t$deps\t$misc\n";
			
				};
				print "\n";
			};
			exit;
		}
		
	} else if ( is_object($ttxml) && ( $sid || $_GET['jmp'] || $_GET['tid'] ) ) {

		$fileid = $ttxml->fileid;

		$jmp =  $_GET['jmp'] or $jmp = $_GET['tid'];
		$jmpfull = $jmp;
		$jmp = preg_replace("/ .*/", "", $jmp);
		if ( !$sid && $jmp ) {
			$sid =	current($ttxml->xpath("//s[.//tok[@id='$jmp']]/@id"));
			if ( !$sid ) $sid =	current($ttxml->xpath("//s[contains(@sameAs, '#$jmp')]/@id")); # TODO: This would not work is a longer ID with the same start would proceed the one we want
			if ( !$sid ) $sid =	current($ttxml->xpath("//s[@id='$jmp']/@id"));
			if ( !$sid ) $sid =	current($ttxml->xpath("//s[./following::tok[@id='$jmp']]/@id"));
		};

		if ( $_POST['sent'] ) {	
			$sent = simplexml_load_string($_POST['sent']);
		} else {
			$sent =	current($ttxml->xpath("//s[@id='$sid']"));
		};
		if ( !$sent ) { fatal("Sentence not found: $sid : "); };

		$puctnsh = $_GET['puctnsh'] or $puctnsh = $_SESSION['puctnsh'] or $puctnsh = getset('deptree/showpunct', "without");
		$_SESSION['puctnsh'] = $puctnsh;
		$hpos = $_GET['hpos'] or $hpos = $_SESSION['hpos'] or $hpos = getset('defaults/deptree/hpos', "branch");
		$_SESSION['hpos'] = $hpos;

		if ( $_GET['auto'] ) {
			foreach ( $deplabels['labels'] as $key => $val ) { if ( $val['base'] ) $nonamb[$val['base']] = $key; };
			foreach ( $sent->xpath($toksel) as $tok ) {
				if ( !$tok[$flddeprel] && $nonamb[$tok['pos'].""] ) { 
					$tok[$flddeprel] = $nonamb[$tok['pos'].""];
				}; 
			};
		};

		# Find the next/previous sentence (for navigation)
		$nextsent = current($sent->xpath("following::s")); 
		if ( $nextsent ) {
			$nid = $nextsent['id'];
			$nexts = $nextsent['n'] or $nexts = $nid;
			$nlink = "index.php?action=$action&cid=$fileid&sid=$nid&view={$treeview}";
			$nnav = "<a href='$nlink'>> $nexts</a>";
		};
		$prevsent = current($sent->xpath("preceding::s[1]")); 
		if ( $prevsent ) {
			$pid = $prevsent['id'];
			$prevs = $prevsent['n'] or $prevs = $pid;
			$plink = "index.php?action=$action&cid=$fileid&sid=$pid&view={$treeview}";
			$bnav = "<a href='$plink'>$prevs <</a>";
		};
		
		$pagenav = "<table style='width: 100%'><tr> <!-- /<$pbelm [^>]*id=\"{$_GET['pageid']}\"[^>]*n=\"(.*?)\"/ -->
						<td style='width: 33%' align=left>$bnav
						<td style='width: 33%' align=center>{%sentence} <a href='index.php?action=file&cid={$ttxml->fileid}&jmp=$sid'>$sid</a>
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
						";

		$maintext .= $pagenav;

		if ( $username ) {
			if ( getset('xmlfile/sattributes/s/status') != '' ) {
				$st = $sent['status']."" or $st = "none";
				if ( !$_POST ) $oncl = " onclick=\"document.getElementById('statbox').style.display='block';";
				foreach ( $stlist as $key => $val ) {
					$kval = $val['value'] or $kval = $key;
					$statsel .= "<option value='$kval'>{$val['display']}</option>";
				}
				$sttxt = $stlist[$st]['display'] or $sttxt = $st;
				$maintext .= "<span style='float: right; text-align: right;' $oncl\">Status: <span status='$st' title='$st'>$sttxt</span></div><div id=statbox style='display: none;'><form action='index.php?action=$action&act=changesent&cid=$ttxml->fileid&sid={$sent['id']}' method=post><select name='sent[status]' onChange='this.parentNode.submit();'>$statsel</select></form></div></span>";
			};
			$maintext .= "<p><span id='linktxt'>Click <a href='index.php?action=$action&act=edit&sid=$sid&cid=$cid'>here</a> to edit the dependency tree</a></span>";
			if ( $act != "edit" && $sent->xpath(".//tok[not(@$flddeprel)]") && $sent->xpath(".//tok[@upos]") ) {
				$maintext .= " &bull; <a href='index.php?action=$action&act=autofill&sid=$sid&cid=$cid'>Auto-fill</a> obligatory deprel";
			};
			if ( $_POST['sent'] ) {
				$maintext .= " - <span style='color: #cc2000;' onClick=\"document.sentform.action.value = 'save'; scriptedexit=true; document.sentform.submit();\"><b>Click here to save the modified dependencies</b><span>
<script language=Javascript>
var scriptedexit = false;
window.addEventListener(\"beforeunload\", function (e) {
    var confirmationMessage = 'You have edited the depedency tree. '
                            + 'If you leave before saving, your changes will be lost.';
	
	if ( !scriptedexit ) {
	    (e || window.event).returnValue = confirmationMessage; //Gecko + IE
    	return confirmationMessage; //Gecko + Webkit, Safari, Chrome etc.
    }
});
</script>";
			} else if ( $act == "edit" ) { $maintext .= " - <a href='index.php?action=$action&cid=$ttxml->fileid&sid=$sid'>cancel</a>"; };
			$maintext .= "</p><hr>";
			
			if ( $act == "edit" ) {
				if ( !$_GET['view'] ) $_GET['view'] = "tree";
				if ( !$_GET['hpos'] ) $_GET['hpos'] = "wordorder";
				if ( !$_GET['punctnsh'] ) $_GET['punctnsh'] = "with";
				$deptreename = $deplabels['description'] or $deptreename = "Dependency Relations";
				$labelstxt = "<h2 style=\"margin-top: 0px; margin-bottom: 5px;\">$deptreename</h2><table>";
				foreach ( $deplabels['labels'] as $key => $val ) {
					$labelstxt .= " <tr onclick=\"setlabel(this);\" key=\"$key\"><th>$key<td>{$val['description']}</tr>";
				};
				$labelstxt .= "<table>";
			};
		};

		
		$xmltxt = makexml($sent);

		if ( $username ) { 
			if ( $act == "edit" ) {
				$tokselect = " onClick='toksel(event);'";
			} else {
				$tokedit = "var tid = '$ttxml->fileid';";
			};
		};

		$maintext .= "<div id='mtxt' mod='$action' $textdir $tokselect style='padding: 10px;'>$xmltxt</div><table>";
		if ( count(getset('xmlfile/sattributes/s', array())) > 1 && $username ) $maintext .= "<a style='float: right' href='index.php?action=$action&cid=$ttxml->fileid&sid=$sid&act=metaedit'>edit metadata</a>";
		foreach ( getset('xmlfile/sattributes/s', array()) as $key => $val ) {
			if ( !is_array($val) ) continue;
			if ( $val['noshow'] || $val['nodeptree'] || $key == "id" ) continue;
			if ( $val['color'] ) $style = " style=\"color: {$val['color']}\"";
			$xval = $sent[$key]; $some = 1;
			if ( $xval ) $maintext .= "<div title='{$val['display']}' $style>$xval</div>";
		};
		$maintext .= "</table>";
		$maintext .= "<hr>";

		if ( $treeview == "graph" ) {
			$graph = drawgraph($sent);
		} else if ( $treeview == "php" ) {
			$graph = drawtree($sent);
			$morejs .= "scaletext();";
		} else  {
			$arraytree = drawjson($sent);
			$jsontree = json_encode($arraytree, JSON_PRETTY_PRINT);
			if ( $jsontree == "" ) {
				if ( $username && $_GET['debug'] ) {
					print haskey($arraytree, "w-156");
					print "Unable to convert array tree to JSON (see error): <pre>".print_r($arraytree, 1); 
					exit;
				} else {
					$jsontree = '{"children": {}}';				
				};
			} else if ( !$arraytree['children']) {
				# If we have a sameAs sent, read the tree from mtxt
				$morejs = "tree = parseteitok(document.getElementById('mtxt')); drawsvg(tree);";
			}
			$setopts = array2json($_SESSION['options']);
			$maintext .= "<div id=graph></div>\n<script language=Javascript>var tree = $jsontree; var options = $setopts;</script>\n";
			$senta = array(); foreach ( $sent->xpath($toksel) as $tok ) { array_push($senta, $tok['id']); };
			$maintext .= "<script language=Javascript>tree['words'] = ['".join("','", $senta)."'];</script>\n";
			$postaction .= "<script language=Javascript src=\"$jsurl/treeview.js\"></script>";

			if ( $puctnsh == "with" ) $paction = "vtoggle(document.getElementById('punctbut'))";

			foreach ( $_SESSION['viewopts'] as $key => $val ) {
				## Pre-click the buttons based on global view options
			}; 

			$morejs .= "var hpos = '$hpos'; var jmp = '$jmpfull'; drawsvg(tree); $paction";
		};
		
		if ( $username && $act == "edit" ) {
			$maintext .= "
			<script language=\"Javascript\">var labelstxt = 'Select a dependency label from the popout list <div class=\"popoutlist\">$labelstxt</div>';</script>
			";
			$editxml = $sent->asXML();
		} else {
			$maintext .= "<script language=\"Javascript\">
				var treeid = '$ttxml->xmlid-$sid';
				document.onkeydown = function(evt) {
					evt = evt || window.event;
				   if ( evt.keyCode == 37 ) {
						var plink = '$plink';
						if ( plink ) { window.open(plink, '_self'); };
				   } else if ( evt.keyCode == 39 ) {
						var nlink = '$nlink';
						if ( nlink ) { window.open(nlink, '_self'); };
				   };
				};</script>
				";
		};
		
		
		$maintext .= "\n
<div id=svgdiv>
$graph
</div>
<style>
#svgtree { transform-origin: 0 0; }
.toktext { 
    text-decoration: underline;
    -moz-text-decoration-color: #992000; 
    text-decoration-color: #992000;
}
.popoutlist {
	position: fixed; 
	top: 5px; right: 5px;
	padding: 5px;
	background-color: #ffffee;
	border: 1px solid #aaaaaa;
	height: 50%;
	overflow: scroll;
	z-index: 1000;
}
</style>
$postaction
<form action='' method=post id=sentform name=sentform style='display: none;'>
<textarea id=sentxml name=sent>$editxml</textarea> <input type=submit>
<input type=hidden name=action value='edit'>
</form>
<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
<script language=\"Javascript\">$morejs</script>
";

	$maintext .= "<hr><p><a href='index.php?action=$action&cid=$ttxml->fileid'>{%back to list}</a> &bull; ".$ttxml->viewswitch();
	$minurl = "index.php?action=$action&cid={$ttxml->fileid}&sid=$sid";
	$maintext .= " &bull; <a href='index.php?action=$action&act=conllu&cid={$ttxml->fileid}&sid={$sent['id']}'>Download as CoNLL-U</a>";


	} else if ( $act == "parse" ) {
		
		check_login();

		$filename = $xmlfolder."/".$ttxml->fileid;
		if ( !file_exists($filename) ) { fatal ( "File does not exist: $filename" ); };
		
		$formfld = getset('udpipe/tagform', getset('xmlfile/wordfld', "form"));
		$sep ="";
		$exec = findapp("udpipe");
		
		// TODO: Check this only if we run locally
		// if ( !$exec ) fatal ("UDPIPE application not found");
		
		$model = $_GET['pid'];
		if ( !$pid ) {
			foreach ( getset('udpipe/parameters', array()) as $key => $val ) {
				if ( $ttxml->xpath("".$val['restriction']) ) {
					$pid = $key; $param = $val;
					break;
				};
			};
			$model = $param['params'];
		};

		$url = "http://lindat.mff.cuni.cz/services/udpipe/api/process";
		$data = array(
			'input' => 'conllu',
			'tagger' => '1',
			'parser' => '1',
			'model' => $model,
		);
		
		$tagfields = explode(",", $param['formtags']);
		
		## Verticalize the text in CoNLL-U format
		foreach ( $ttxml->xpath("//s[not(.//tok[@$flddeprel])]") as $sent ) {
			$tnr = 0; $verticalized = "";	
			unset($heads); unset($tid);
			foreach ( $sent->xpath(".//tok[not(dtok)] | //dtok") as $tok ) {		
				$tagform = forminherit($tok, $formfld);
				if ( $tagform != "--" && $tagform != "" ) {
					$tnr++;	
					$lemma = $tok['lemma'] or $lemma = "_";
					$tokid = $tok['id']."";
					$toks[$tokid] = $tok;
				
					$verticalized .= $sep."$tnr\t$tagform\t$lemma\t_\t_\t_\t_\t_\t_\t$tokid";
					$sep = "\n";
				};
			};	
			
			$data['data'] = $verticalized;

			$options = array(
				'http' => array(
					'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
					'method'  => 'POST',
					'content' => http_build_query($data)
				)
			);
			$context  = stream_context_create($options);
			$result = file_get_contents($url, false, $context);
			if ($result === FALSE) { 
				print "Failed to get data from the server<hr>"; 
				print_r($result); 
				print "<hr>(form field: $formfld)<pre>";
				print_r($data);
				print "</pre>";
				exit;
			}

			if ( preg_match('/"result": "([^"]*)"/', $result, $matches ) ) {
				$response = $matches[1];
				$response = str_replace("\\n", "\n", $response);
				$response = str_replace("\\t", "\t", $response);

				$parsetxt .= "<hr>".$response;
				
				foreach ( explode ( "\n", $response ) as $line ) {
					list ( $tnr, $word, $lemma, $upos, $xpos, $feats, $head, $drel, $x1, $tokid ) = explode ( "\t", $line );
					if ( $word ) {
						$tid[$tnr] = $tokid;
						$heads[$tokid] = $head;
						$toks[$tokid][$flddeprel] = $drel;
						
						# Optionally also introduce upos, xpos, feats, lemma
						if ( in_array("upos", $tagfields) ) { $toks[$tokid]['upos'] = $upos; };
						if ( in_array("xpos", $tagfields) ) { $toks[$tokid]['xpos'] = $xpos; };
						if ( in_array("feats", $tagfields) ) { $toks[$tokid]['feats'] = $feats; };				
						if ( in_array("lemma", $tagfields) ) { $toks[$tokid]['lemma'] = $lemma; };
						
					};
				};
				
				foreach ( $heads as $tokid => $head ) {
					if ( $tid[$head] ) $toks[$tokid][$fldhead] = $tid[$head];
				};
				
			} else {
				print "Unexpected result from the server<hr>"; print_r($result); 
				exit;
			};
			
			if ( getset('udpipe/batch') != '' && $scnt++ > intval(getset('udpipe/batch')) ) break;
			
			// Wait for a second to make sure we do not crash the connection
			sleep(0.5);
			
		}; 
						
		$maintext .= "<h1>File tagged</h1>
			<p>Parsing URL: $url
			<p>Reponse text: 
			<pre>$parsetxt</pre>
			<hr>
		";		
		
		# Add a revisionDesc node indicating it was parsed by UDPIPE
		$revnode = xpathnode($ttxml->xml, "//teiHeader/revisionDesc/change[@who=\"udpipe\"]");
		$revnode['when'] = date("Y-m-d");
		$revnode[0] = "dependency parsed with the udpipe web-service using model $model";
		
		$ttxml->save();
		print "<p>New XML file has been created. Reloading to deptree mode.
			<script language=Javascript>top.location='index.php?action=$action&cid=$ttxml->fileid'</script>"; exit;
		
	} else {
	
		$sentlist = $ttxml->xpath("//s");
	
		if ( !strstr($ttxml->asXML(), "<tok" ) ) {

			$maintext .= "<p>Dependency trees are not available for this text, since the text is not yet tokenized. ";

			if ( $username ) {
				$maintext .= "<p class=adminpart>You can tokenize by clicking <a href='index.php?action=tokenize&cid=$ttxml->fileid&display=tok&s=1'>here</a> -
					tokenization will also attempt to split the text into sentences";
			};
		
		} else if ( !$sentlist) {

			$maintext .= "<p>Dependency trees are not available for this text, since the text is not yet split into sentences. ";

			if ( $username ) {
				$maintext .= "<p class=adminpart>You can split the text into sentences by clicking 
					<a href='index.php?action=tokenize&id=$ttxml->fileid&display=tok&s=2'>here</a> -
					bear in mind that XML regions crossing sentences boundaries can hamper this process.
					If sentence splitting fails, please insert sentences directly in the raw XML";
			};
		
		
		} else if ( !strstr($ttxml->asXML(), "head=" ) ) {

			$maintext .= "<p>Dependency trees are not available for this text, since the text is not yet parsed with dependency relations.";
		
			if ( $username ) {
				if ( getset('udpipe') ) {
					$maintext .= "<p class=adminpart>You can parse the file using your parser definition by
						clicking <a href='index.php?action=$action&act=parse&id=$ttxml->fileid'>here</a> ";
				} else {
					$maintext .= "<p class=adminpart>No dependency parser has been set-up for this project. To use
						a dependency parser, please set-up the <a href='index.php?action=adminsettings&section=udpipe'>udpipe</a> section of the settings";
				};
			};
		
		} else {
		
			$max = $_GET['perpage'] or $max = 100;
			$tt = count($sentlist);
			if ( $tt > $max ) {
				$start = $_GET['start'] or $start = 0;
				$st = $start + 1;
				$lt = $start + $max;
				$nav = "<p>Showing $st - $lt of $tt ";
				if ( $st > 0 ) { 
					$pt = max(0,$st-$max);
					$nav .= " &bull; <a href='index.php?action=$action&cid=$cid&start=$pt'>previous</a>";
				};
				if ( $lt < $tt ) { 
					$nt = min($tt,$lt);
					$nav .= " &bull; <a href='index.php?action=$action&cid=$cid&start=$nt'>next</a>";
				};
				$sentlist = array_slice($sentlist,$start,$max);
			};
			$maintext .= "
				<p>Select a sentence

				<script src=\"https://cdnjs.cloudflare.com/ajax/libs/leader-line/1.0.3/leader-line.min.js\"></script>
				<script>
						var dllines = [];
						var tibel = 10;
				</script>
				$nav
				<table id='mtxt' mod='$action' $textdir>"; 
	
			foreach ( $sentlist as $sent ) {
			
				if ( $username && getset('xmlfile/sattributes/s/status') != '' ) {
					$st = $sent['status']."" or $st = "none";
					$stcol = ""; if ( $stlist[$st]['color'] ) $stcol = " color: {$stlist[$st]['color']};";
					$sttxt = $stlist[$st]['display']; if ( !$sttxt ) $sttxt = $st;
					$stattd = "<td><span status='$st' title='status: $sttxt' style='font-size: 10px; $stcol'>&#9638;</span>";
				};
				
				$sid = $sent['id'];
				$maintext .= "<tr>$stattd<td><a href='index.php?action=$action&cid={$ttxml->fileid}&sid=$sid'>$sid
					<td>".makexml($sent);
			
			};
			$maintext .= "</table>
			<hr><p><a href='index.php?action=text&cid={$ttxml->fileid}'>{%Text view}</a>
				&bull;
				<a href='index.php?action=$action&act=conllu&cid={$ttxml->fileid}'>{%Download CoNNL-U}</a>
				";
	
		};
	
	};	

	function drawjson ($node) {
		global $fldhead; global $flddeprel;
		$array = array(); $parents = array();
		global $xpos; global $username; global $act; global $deplabels; global $toksel; global $maxheight;
		foreach ( $node->xpath($toksel) as $tok ) {
			$text = $tok['form']."" or $text = $tok."";
			if ( $text == "" ) $text = "&#8709;";

			$tokid = $tok['id']."";
			$deprel = $tok[$flddeprel]."";
			$headid = $tok[$fldhead]."";

			if ( !$headid || $headid == $tokid ) $headid = "root";
			if ( $deprel == "root" ) $rootid = $tokid;

			if ( !is_array($array[$headid]) ) { $array[$headid] = array(); $array[$headid]['children'] = array(); };
			if ( !is_array($array[$tokid]) ) { $array[$tokid] = array(); };

			$array[$tokid]['label'] = $text;
			$array[$tokid]['sublabel'] = $deprel;
			$array[$tokid]['id'] = $tokid;
			if ( $deprel == 'punct' ) { $array[$tokid]['ispunct'] = 1; };
			
			if ( !haskey($array[$headid], $tokid) &&  !haskey($array[$tokid], $headid) ) # Avoid recursion
				$array[$headid]['children'][$tokid] = &$array[$tokid];
			$parents[$tokid] = $headid;

		};
		if ( !$array['root'] && $username )  {
			if ( $debug ) { $maintext .= "<p class=wrong>No root node generated</p>"; };
			if ( !$rootid ) {
				# If there is no root, climb the tree from the first word
				$rootid = array_keys($array)[0]; 
				while ( $parents[$rootid] &&  $parents[$rootid]['label']  ) {  $rootid = $parents[$rootid]; };
			};
			if ( $rootid ) {
				$array['root']['children'][$tokid] = &$array[$rootid];
				return $array['root'];
			};
			return array ( "children" => array() );
		};
		return $array['root'];
	};

	function drawgraph ( $node ) {
		$treetxt = "";
		global $fldhead; global $flddeprel;
		global $xpos; global $username; global $act; global $deplabels; global $toksel; global $maxheight;
		$xpos = 0; $maxheight = 100;
		foreach ( $node->xpath($toksel) as $tok ) {
		
			$text = $tok['form'] or $text = $tok."";
			# $text = str_replace(" ", "_", $text);
			$tokid = $tok['id']."";
			
			if ( $text != "" || $tok[$fldhead] ) { 		
				
				if ( $text == "" ) $text = "`";				

				if ( $username && $act == "edit" ) {
					$onclick = " onClick=\"relink(this);\"";
				};
				
				$treetxt .= "\n\t<text class='toktext' tokid=\"$tokid\" x=\"$xpos\" y=\"20\" font-family=\"Courier\" font-size=\"12\" type=\"tok\" $onclick>$text</text> ";
				$width = 6.9*(mb_strlen($text));
				$mid[$tokid] = $xpos + ($width/2);
				$xpos = $xpos+12+$width;
			
			};
				
		};

		$treetxt .= "\n";

		foreach ( $node->xpath($toksel) as $tok ) {
			if ( $tok[$fldhead] && $tok['drel'] != "0" ) {
				$in++;
				$x1 = $mid[$tok['id'].""]; 
				$x2 = $mid[$tok[$fldhead].""];

				# if ( $x1 > $x2 ) { $x1 -= 5; } else { $x1 += 5; }; // This puts the arrow next to each other..
				$y1 = 25; // Y of the arch
				$w = $x2-$x1; // width of the arch
				$h = floor(abs($w/2));  // height of the arch 
				$y2 = $y1 + $h; // top of the arch
				if ( $x2 ) $maxheight = max($y2, $maxheight);  # Leave out arches without a head
 
				$os = floor($w/8); // curve point distance
				$r1 = $x1+$os; $r2 = $x2-$os; // curve points
				// place the arch
				if ( $x2 ) $treearches .= "\n\t<path title=\"{$tok[$flddeprel]}\" d=\"M$x1 $y1 C $r1 $y2, $r2 $y2, $x2 $y1\" stroke=\"#992000\" stroke-width=\"0.5\" fill=\"transparent\" />"; #  marker-end=\"url(#arrow)\"
				// place a dot on the end (better than arrow)
				if ( $x2 ) $treearches .= "<circle cx=\"$x1\" cy=\"$y1\" r=\"2\" stroke=\"black\" stroke-width=\"1\" fill=\"black\" />";

 				if ( $tok[$flddeprel] || ( $username && $act == "edit" ) ) {
 					$lw = 5.8*(mb_strlen($tok[$flddeprel]));
 					$lx = $x1 + $w/2 - ($lw/2); // X of the text
 					$ly = $y1 + $h*0.75 - 5; // Y of the text
 					$deplabel = $tok[$flddeprel]."";
					if ( $username && $act == "edit" ) {
						if ( $deplabel == "" ) $deplabel = "??";
						$onclick = " onClick=\"relabel(this);\"";
					};
 					$labeltxt = $deplabels[$tok[$flddeprel].""]['description'];
 					$treelabels .= "\n\t<text x=\"$lx\" y=\"$ly\" font-family=\"Courier\" fill=\"#aa2000\" font-size=\"10\" type=\"label\" onMouseOver=\"this.setAttribute('fill', '#20aa00');\" baseid=\"{$tok['id']}\" title=\"$labeltxt\" $onclick>$deplabel</text> ";
 				};
			};
		};
		$treetxt .= $treearches;
		$treetxt .= $treelabels;

		$width = $xpos + 50;
		$height = $maxheight;
				
		$graph = "<svg id='svgtree' xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"$width\" height=\"$height\">
	<defs>
		<marker id=\"arrow\" markerWidth=\"10\" markerHeight=\"8\" refx=\"0\" refy=\"3\" orient=\"auto\" markerUnits=\"strokeWidth\">
			<path d=\"M0,0 L0,6 L9,3 z\" fill=\"#000\" />
		</marker>
	</defs>
	$treetxt
</svg>";

		return $graph;
	};

	function valsort ($a,$b) {
		global $tmp;
		if($tmp[$a] < $tmp[$b]){
			return -1;
		 }
		 if($tmp[$a] > $tmp[$b]){ 
			return 1;
		 }
		 return strcmp($a,$b);
	  };    

	function haskey($array, $key) {
		if ( !is_array($array) ) return false;
		foreach ( $array as $ak => $av ) {
			if ( $ak == $key ) return true;
			if ( haskey($av, $key) ) return true;
		};
		return false;
	};

?>