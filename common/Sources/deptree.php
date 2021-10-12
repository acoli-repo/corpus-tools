<?php

	# Visualization of dependency trees
	# (c) Maarten Janssen, 2016
	
	$deplabels = $settings['deptree'];
	if ( $deplabels['rooted'] ) $rooted = 1; 
	if ( !$deplabels['labels'] ) {
		$deptreexml = simplexml_load_file("$ttroot/common/Resources/deptree.xml");
		$deplabels = xmlflatten($deptreexml);
	}; 

	# Define what counts as a token in the dependency graph	
	$toksel = ".//mtok[not(./dtok)] | .//tok[not(dtok) and not(ancestor::mtok)] | .//dtok[not(ancestor::tok/ancestor::mtok)]";
	
	$maintext .= "<h1>Dependency Tree</h1>"; 

	$treeview = $_SESSION['treeview'] or $treeview = $_GET['view'] or $treeview = "tree";
	$_SESSION['treeview'] = $treeview;

	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, true); # TODO: we did keepns before, why? (breaks //s test)
	
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 

	$sid = $_GET['sid'] or $sid = $_GET['sentence'];
	$cid = $ttxml->fileid;

	if ( $settings['xmlfile']['basedirection'] ) {
		// Defined in the settings
		$textdir = "dir='{$settings['xmlfile']['basedirection']}'";
	} else {
		$dirxpath = $settings['xmlfile']['direction'];
		if ( $dirxpath ) {
			$tdval = current($ttxml->xml->xpath($dirxpath));
		};
		if ( $tdval ) {
			// Defined in the teiHeader for mixed-writing corpora
			$textdir = "dir='$tdval' style='text-direction: $tdval;'";
			$morejs .= "var basedirection = '$tdval';";
		};
	};

	if ( $_POST['action'] == "save" ) { $act = "save"; };

	if ( $act == "save" && $_POST['sent'] ) {

		$sent =	current($ttxml->xml->xpath("//s[@id='$sid']"));
		$newsent = simplexml_load_string($_POST['sent']);
		
		foreach ( $newsent->xpath($toksel) as $newtok ) {
			$tokid = $newtok['id'];
			$tok = current($sent->xpath(".//*[@id='$tokid']"));
			if ( $tok['head']."" != $newtok['head']."" ) {
				print "<p>$tokid: {$tok['head']} => {$newtok['head']}</p>";
				$tok['head'] = $newtok['head'];
			};
			if ( $tok['deprel']."" != $newtok['deprel']."" ) {
				print "<p>$tokid: {$tok['deprel']} => {$newtok['deprel']}</p>";
				$tok['deprel'] = $newtok['deprel'];
			};
		};
		$ttxml->save();
		print "<script>top.location='index.php?action=$action&act=edit&sid=$sid&cid=$ttxml->fileid';</script>";
		exit;		

	} else if ( $act == "autofill" ) {

		$sent =	current($ttxml->xml->xpath("//s[@id='$sid']"));

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

	} else if ( $act == "conllu" ) {

		$ccid = $ttxml->xmlid;
		header('Content-Type: text/plain; charset=utf-8');
		header('Content-Disposition: attachment; filename="'.$ccid.'.conllu"');
		print "# newdoc id = $ccid\n\n";
		if ( $_GET['sid'] ) $sid = "[@id=\"{$_GET['sid']}\"]";

		$formfld = $settings['udpipe']['tagform'] or $settings['xmlfile']['wordfld'] or $formfld = "form";
		foreach ( $ttxml->xml->xpath("//s$sid") as $sent ) {
			$toks = $sent->xpath($toksel);
			
			if ( !$toks ) continue;
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

			foreach ( $toklist as $tok ) {		
			
				$form = forminherit($tok, $formfld);
				$lemma = $tok['lemma'] or $lemma = "_";
				$tokid = $tok['id']."";
				$tnr = $ids[$tokid];
				
				$upos = $tok['upos'] or $upos = $tok['pos'] or $upos = "_";
				$xpos = $tok['xpos'] or $xpos = "_";
				$head = $ids[$tok['head'].""] or $head = "0";
				$feats = $tok['feats'] or $feats = "_";
				$deprel = $tok['deprel'] or $deprel = "_";
				$deps = $tok['deps'] or $deps = "_";
				$misc = "_";
				$maintok = "$form\t$lemma\t$upos\t$xpos\t$feats\t$head\t$deprel\t$deps\t$misc";
					
				print "$tnr\t$form\t$lemma\t$upos\t$xpos\t$feats\t$head\t$deprel\t$deps\t$misc\n";
			
			};

		};
		exit;

	} else if ( $sid || $_GET['jmp'] || $_GET['tid'] ) {

		$jmp =  $_GET['jmp'] or $jmp = $_GET['tid'];
		$jmp = preg_replace("/ .*/", "", $jmp);
		if ( !$sid && $jmp ) {
			$sid =	current($ttxml->xml->xpath("//s[.//tok[@id='$jmp']]/@id"));
		};

		if ( $_POST['sent'] ) {	
			$sent = simplexml_load_string($_POST['sent']);
		} else {
			$sent =	current($ttxml->xml->xpath("//s[@id='$sid']"));
		};
		$fileid = $ttxml->fileid;
		if ( !$sent ) { fatal("Sentence not found: $sid"); };

		$puctnsh = $_GET['puctnsh'] or $puctnsh = $_SESSION['puctnsh'] or $puctnsh = $settings['deptree']['showpunct'] or $puctnsh = "without";
		$_SESSION['puctnsh'] = $puctnsh;
		$hpos = $_GET['hpos'] or $hpos = $_SESSION['hpos'] or $hpos = $settings['deptree']['hpos'] or $hpos = "branch";
		$_SESSION['hpos'] = $hpos;

		if ( $_GET['auto'] ) {
			foreach ( $deplabels['labels'] as $key => $val ) { if ( $val['base'] ) $nonamb[$val['base']] = $key; };
			foreach ( $sent->xpath($toksel) as $tok ) {
				if ( !$tok['deprel'] && $nonamb[$tok['pos'].""] ) { 
					$tok['deprel'] = $nonamb[$tok['pos'].""];
				}; 
			};
		};

		# Find the next/previous sentence (for navigation)
		$nextsent = current($sent->xpath("following::s")); 
		if ( $nextsent ) {
			$nid = $nextsent['id'];
			$nexts = $nextsent['n'] or $nexts = $nid;
			$nnav = "<a href='index.php?action=$action&cid=$fileid&sid=$nid&view={$treeview}'>> $nexts</a>";
		};
		$prevsent = current($sent->xpath("preceding::s[1]")); 
		if ( $prevsent ) {
			$pid = $prevsent['id'];
			$prevs = $prevsent['n'] or $prevs = $pid;
			$bnav = "<a href='index.php?action=$action&cid=$fileid&sid=$pid&view={$treeview}'>$prevs <</a>";
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
			$maintext .= "<p><span id='linktxt'>Click <a href='index.php?action=$action&act=edit&sid=$sid&cid=$cid'>here</a> to edit the dependency tree</a></span>";
			if ( $act != "edit" && $sent->xpath(".//tok[not(@deprel)]") && $sent->xpath(".//tok[@upos]") ) {
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
			}; 
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

		
		$xmltxt = $sent->asXML();
		$xmltxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $xmltxt );

		if ( $username ) { 
			if ( $act == "edit" ) {
				$tokselect = " onClick='toksel(event);'";
			} else {
				$tokedit = "var tid = '$ttxml->fileid';";
			};
		};

		$maintext .= "			
			<div id='mtxt' mod='$action' $textdir $tokselect>$xmltxt</div><hr>";

		if ( $treeview == "graph" ) {
			$graph = drawgraph($sent);
		} else {
			$graph = drawtree($sent);
			$morejs .= "scaletext();";
		};
		
		if ( $username && $act == "edit" ) {
			$maintext .= "
			<script language=\"Javascript\">var labelstxt = 'Select a dependency label from the popout list <div class=\"popoutlist\">$labelstxt</div>';</script>
			";
		};
		
	
		$maintext .= "\n
<div id=svgdiv>
$graph
</div>
<style>
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
}
</style>
<form action='' method=post id=sentform name=sentform style='display: none;'>
<textarea id=sentxml name=sent></textarea> <input type=submit>
<input type=hidden name=action value='edit'>
</form>
<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
<script language=\"Javascript\">$morejs</script>";

	$maintext .= "<hr><p>".$ttxml->viewswitch();
	$minurl = "index.php?action=$action&cid={$ttxml->fileid}&sid=$sid";


$formfld = $_GET['form'] or $formfld = "";
$graphbase = base64_encode(str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $graph));
$maintext .= "
	 &bull; {%Download}: 
	 	<select name='dlopt' onChange=\"makelink(this)\">
	 		<option value=''>[{%select}]</option>
	 		<option value='svg' link='data:image/svg+xml;base64,$graphbase'>SVG</option>
			<option value='png' id='pnglink'>PNG</option>
	 		<option value='conll-u' link='index.php?action=$action&act=conllu&cid={$ttxml->fileid}&sid={$sent['id']}'>CoNLL-U</option>
	 	</select>
	<canvas style='display: none;' id='myCanvas' width='800' height='400' ></canvas>
	<script language=Javascript>
		document.getElementById('svgdiv').style.height = document.querySelector('svg').getAttribute('height') + 'px';
		var localhl = 1; // use a local highlight function
		var orgtoks = new Object();
		var username = '$username';
		$tokedit
		formify(); setForm('$formfld');
		var canvas=document.getElementById('myCanvas');
		var ctxt = canvas.getContext('2d');
		var svgelm = document.querySelector('svg');
		function drawInlineSVG(svgElement, ctx, callback){
		  var svgURL = new XMLSerializer().serializeToString(svgElement);
		  var img  = new Image();
		  img.onload = function(){
			ctx.drawImage(this, 0,0);
			callback();
			}
		  	img.src = 'data:image/svg+xml; charset=utf8, '+encodeURIComponent(svgURL);
		  }

		canvas.height = svgelm.height.baseVal.value;
		canvas.width = svgelm.width.baseVal.value;
		drawInlineSVG(svgelm, ctxt, function(){
			document.getElementById('pnglink').setAttribute('link', canvas.toDataURL());
		});
	</script>
	<style>a.selected { font-weight: bold; }</style>";
	$selopt = "class=\"selected\"";
	if ( $treeview == "graph" ) $selvg = $selopt; else $selvt = $selopt;
	if ( $_GET['puctnsh'] == "with" ) $selpz = $selopt; else $selpm = $selopt;
	if ( $_GET['hpos'] == "narrow" ) $selhn = $selopt; else if ( $_GET['hpos'] == "wordorder" ) $selhw = $selopt;  else $selhb = $selopt;
	$maintext .= "
	<h3>{%View options}</h3>
	<table>
	<tr><th>{%Main view}<td>
		<ul>
		<li><a href='$minurl&view=tree' $selvt>{%Tree}</a>
		<li><a href='$minurl&view=graph' $selvg>{%Graph}</a>		
		</ul>	
	<tr><th>{%Punctuation}<td>
		<ul>	
		<li><a href='$minurl&puctnsh=without' $selpm>{%Hide}</a>
		<li><a href='$minurl&puctnsh=with' $selpz>{%Show}</a>			
		</ul>";
	if ( $treeview != "graph" ) $maintext .= "
	<tr><th>{%Horizontal placement}<td>
		<ul>	
		<li><a href='$minurl&hpos=branch' $selhb>{%Branching}</a>
		<li><a href='$minurl&hpos=wordorder' $selhw>{%Word order}</a>			
		<li><a href='$minurl&hpos=narrow' $selhn>{%Centralized}</a>	
		</ul>";
	$maintext .= "
	</table>		
	";


# SVG:  value='data:image/svg+xml;base64,$graphbase' download=\"deptree.svg\"
# PNG:  value='pnglink' download=\"deptree.png\"
# CoNLL-U index.php?action=$action&act=conllu&cid={$ttxml->fileid}&sid={$sent['id']}

	} else if ( $act == "parse" ) {
		
		check_login();

		$filename = $xmlfolder."/".$ttxml->fileid;
		if ( !file_exists($filename) ) { fatal ( "File does not exist: $filename" ); };
		
		$formfld = $settings['udpipe']['tagform'] or $settings['xmlfile']['wordfld'] or $formfld = "form";
		$sep ="";
		$exec = findapp("udpipe");
		
		// TODO: Check this only if we run locally
		// if ( !$exec ) fatal ("UDPIPE application not found");
		
		$model = $_GET['pid'];
		if ( !$pid ) {
			foreach ( $settings['udpipe']['parameters'] as $key => $val ) {
				if ( $ttxml->xml->xpath("".$val['restriction']) ) {
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
		foreach ( $ttxml->xml->xpath("//s[not(.//tok[@deprel])]") as $sent ) {
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
						$toks[$tokid]['deprel'] = $drel;
						
						# Optionally also introduce upos, xpos, feats, lemma
						if ( in_array("upos", $tagfields) ) { $toks[$tokid]['upos'] = $upos; };
						if ( in_array("xpos", $tagfields) ) { $toks[$tokid]['xpos'] = $xpos; };
						if ( in_array("feats", $tagfields) ) { $toks[$tokid]['feats'] = $feats; };				
						if ( in_array("lemma", $tagfields) ) { $toks[$tokid]['lemma'] = $lemma; };
						
					};
				};
				
				foreach ( $heads as $tokid => $head ) {
					if ( $tid[$head] ) $toks[$tokid]['head'] = $tid[$head];
				};
				
			} else {
				print "Unexpected result from the server<hr>"; print_r($result); 
				exit;
			};
			
			if ( $settings['udpipe']['batch'] && $scnt++ > $settings['udpipe']['batch'] ) break;
			
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
	
		$sentlist = $ttxml->xml->xpath("//s");
	
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
				if ( $settings['udpipe'] ) {
					$maintext .= "<p class=adminpart>You can parse the file using your parser definition by
						clicking <a href='index.php?action=$action&act=parse&id=$ttxml->fileid'>here</a> ";
				} else {
					$maintext .= "<p class=adminpart>No dependency parser has been set-up for this project. To use
						a dependency parser, please set-up the <a href='index.php?action=adminsettings&section=udpipe'>udpipe</a> section of the settings";
				};
			};
		
		} else {
		
			$maintext .= "
				<p>Select a sentence
				<table id='mtxt' mod='$action' $textdir>"; 
		
			foreach ( $sentlist as $sent ) {
			
				$maintext .= "<tr><td><a href='index.php?action=$action&cid={$ttxml->fileid}&sid={$sent['id']}'>{$sent['id']}
					<td>".$sent->asXML();
			
			};
			$maintext .= "</table>
			<hr><p><a href='index.php?action=text&cid={$ttxml->fileid}'>{%Text view}</a>
				&bull;
				<a href='index.php?action=$action&act=conllu&cid={$ttxml->fileid}'>{%Download CoNNL-U}</a>
				";
	
		};
	
	};	

	function drawtree ( $node, $tokform = "form" ) {
		global $puctnsh, $rooted, $hpos, $tdval;
		$jmp =  $_GET['jmp'] or $jmp = $_GET['tid'];
		$treetxt = ""; if ( $_GET['form'] ) $tokform = $_GET['form'];
		global $xpos; global $username; global $act; global $deplabels; global $toksel; global $maxheight; global $maxwidth;

		if ( $username && $act == "edit" ) {
			$onclick = " onClick=\"relink(this);\"";
		};

		# Read the tokens
		$i = 0;
		$svgtxt .= "<svg version=\"1.1\" style='z-index: 2; position: absolute;' width=\"100%\" height=\"500\">"; # xmlns=\"http://www.w3.org/2000/svg\" 
		$tokar = array();
		foreach ( $node->xpath($toksel) as $tok ) {
			$text = forminherit($tok, $tokform, false);
			if ( $text == "--" ) continue;
			if ( $text == "" ) $text = "`";
			if ( strtoupper($tok['deprel']) != "PUNCT" || $puctnsh == "with" ) {
				if ( $jmp != '' && $jmp == $tok['id'] ) { $highl = " font-weight='bold' fill='#aa2200' "; } else { $highl = ""; };
				$svgtxt .= "\n\t<text text-anchor='middle' tokid=\"{$tok['id']}\" font-size=\"12pt\" type=\"tok\" head=\"{$tok['head']}\" deprel=\"{$tok['deprel']}\" $onclick $highl>$text</text> ";
			};
			array_push($tokar, $tok);
		};
		$svgtxt .= "</svg>";
		
		$svgxml = simplexml_load_string($svgtxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING); // Put the leaves and lines on the canvas
		if ( !$svgxml ) print "ERROR!"; #return "<p><i>Error while drawing tree</i>";

		if ( $rooted ) $rootrow = 1; else $rootrow = 0;
		$result = $svgxml->xpath('//text[not(@row)]'); $somedone = 1;
		while (	count($result) > 0 && $somedone ) {
			$somedone = 0;
			foreach ( $result as $textnode ) { 
				$id2node[$textnode['tokid'].""] = $textnode;
				unset($row);
				if ( !$textnode['head'] ) {
					$row = $rootrow ;
				} else if ( $textnode['head'] == $textnode['tokid'] || $textnode['deprel'] == "root" ) {
					$row = $rootrow ;
				} else {
					$headnode = current($svgxml->xpath("//text[@tokid=\"{$textnode['head']}\"]"));
					if ( !$headnode ) {
						$row = $rootrow;
					} else if ( isset($headnode['row']) ) {
						$row = $headnode['row'] + 1;
					};
				};
				
				if ( isset($row) ) {
					$textnode['row'] = $row;
					$textnode['y'] = $row*100 + 20;
					$maxheight = max($row, $maxheight);
					$elms[$row]++;
					$maxwidth = max($elms[$row], $maxwidth);
					$somedone = 1;
				};
			};
			$result = $svgxml->xpath('//text[not(@row)]');
		};

		$mainnode = current($svgxml->xpath("//svg"));
		$mainnode['height'] = $maxheight*100 + 50;
		$mainnode['width'] = $maxwidth*100 + 50;

		# Distribute the nodes horizontally		
		$maxcol = $maxwidth;		
		if ( $hpos == "narrow" ) {
			foreach ( $svgxml->xpath("//text") as $textnode ) {	
				$tokid = $textnode['tokid'].""; 
				$tr = $textnode['row']."";
				$colpos = $colcnt[$tr]++;
				$thiscol = ($colpos+1) * ($maxwidth/($elms[$tr]+1));
				$textnode['col'] = $thiscol;
				$id2col[$tokid] = $thiscol;
			};
		} else if ( $hpos == "wordorder" ) {
			# Put each word in sentence order
			$colpos = -0.3; 
			foreach ( $svgxml->xpath("//text") as $i => $textnode ) {	
				$tokid = $textnode['tokid'].""; 
				$tr = $textnode['row'].""; 
				if ( !isset($lastpos[$tr]) ) $colpos = $colpos + 0.3; 
				else $colpos = max($colpos + 0.3, $lastpos[$tr] + 1);
				
				$lastpos[$tr] = $colpos;
				$textnode['col'] = $colpos;
				$id2col[$tokid] = $colpos;
			};
			$maxcol = $colpos + 1;
		} else if ( $hpos == "branch" ) {
			# Put each word below its head
			$maxcol = 0; $mincol = $maxwidth;
			foreach ( $svgxml->xpath("//text") as $i => $textnode ) {	
				$headid = $textnode['head']."";
				$head = $id2node[$headid];
				if ( $textnode['deprel']."" == "root" || $textnode['tokid']."" == $textnode['head']."" ) continue;
				$brcnt[$headid]++;
			};
			for ( $tr=0; $tr<$maxheight+1; $tr++ ) {
				$rowres = $svgxml->xpath("//text[@row=\"$tr\"]");
				$lasthead = ""; $firstfree = "";
				foreach ( $rowres as $colpos => $textnode ) {
					$headid = $textnode['head'].""; 
					$tokid = $textnode['tokid'].""; 
					if ( $tr == 0 ) {
						$thiscol = ($colpos+1) * ($maxwidth/($elms[$tr]+1));
						$textnode['col'] = $thiscol;
						$id2col[$tokid] = $thiscol;
					} else {
						if ( $lasthead != $headid ) {
							$ho = 0.5 - ($brcnt[$headid]/2);
							$wh = 0;
							$lasthead = $headid;
							$thisfirst =  $id2col[$headid] + $ho + $wh + 0.5 - ($brcnt[$tokid]/2);
							$overlap = 0;
							if ( $firstfree != "" ) $overlap = max(0, $firstfree-$thisfirst);
						} else {
							$wh++;
						};
						$thiscol = $id2col[$headid] + $ho + $wh + $overlap;
						$textnode['col'] = $thiscol;
						$id2col[$tokid] = $thiscol;
						if ( $brcnt[$tokid] > 0 ) $firstfree = $id2col[$tokid] + ($brcnt[$tokid]/2) + 0.5;
					};
					$maxcol = max($maxcol, $textnode['col']+0);
					$mincol = min($mincol, $textnode['col']+0);
				};		
			};
		};
		# Place the node horizontally (taking into account RTL and left margin)
		$maxcol = $maxcol - $mincol; 
		foreach ( $svgxml->xpath("//text") as $i => $textnode ) {	
			$tokid = $textnode['tokid'].""; 
			$poscol =  $id2col[$tokid] - $mincol;
			if ( $tdval == "rtl" ) { 
				$textnode['x'] = ($maxcol-$poscol)*100 + 70; 
			} else {
				$textnode['x'] = $poscol*100 + 70; 
			};
		};
		$mincol = 0;

		$mainnode['maxcol'] = $maxcol;
		$mainnode['mincol'] = $mincol;
		$width = $maxcol*100 + 170;
		$height = $maxheight*100 + 70;
		$mainnode['width'] = $width;
		$mainnode['height'] = $height;
		$mainnode['viewBox'] = "0 0 $width $height";

		# Draw the lines
		foreach ( $svgxml->xpath("//text") as $textnode ) {
			
			$strokecolor = '#992000';
			$headnode = current($svgxml->xpath("//text[@tokid=\"{$textnode['head']}\"]"));
			if ( !$headnode && $rooted ) { 
				$headnode['x'] = $maxwidth*100/2; 
				$strokecolor = '#cccccc';
			};
			$deplabel = $textnode['deprel']."";
			if ( $username && $act == "edit" ) {
				if ( $deplabel == "" ) $deplabel = "(norel)";
				$onclick = " onClick=\"relabel(this);\"";
			};
			if ( $deplabel ) {
				$newlabel = $svgxml->addChild('text', $deplabel );
				$newlabel['text-anchor'] = 'middle';
				$newlabel['x'] = $textnode['x'];
				$newlabel['y'] = $textnode['y'] + 15;
				$newlabel['font-size'] = '9pt';
				$newlabel['fill'] = '#ff8866';
				$newlabel['type'] = 'label';
				$newlabel['baseid'] = $textnode['tokid'];
				if ( $username && $act == "edit" ) $newlabel['onClick'] = 'relabel(this)';
			};

			if ( $textnode['row'] == 0 ) continue;
			
			$newline = $svgxml->addChild('line');				
			$newline['stroke-width'] ='0.5';
			$newline['stroke'] = $strokecolor;

			$newline['x1'] = $textnode['x'] + 0;
			$newline['y1'] = $textnode['y'] - 18;
			
			$newline['x2'] = $headnode['x'] + 0;
			if ( $headnode['deprel'] ) {
				$newline['y2'] = $headnode['y'] + 22;
			} else {
				$newline['y2'] = $headnode['y'] + 10;
			};
					
			
		};
		
		
		return $svgxml->asXML();
	};

	function drawgraph ( $node ) {
		$treetxt = "";
		global $xpos; global $username; global $act; global $deplabels; global $toksel; global $maxheight;
		$xpos = 0; $maxheight = 100;
		foreach ( $node->xpath($toksel) as $tok ) {
		
			$text = $tok['form'] or $text = $tok."";
			# $text = str_replace(" ", "_", $text);
			$tokid = $tok['id']."";
			
			if ( $text != "" || $tok['head'] ) { 		
				
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
			if ( $tok['head'] && $tok['drel'] != "0" ) {
				$in++;
				$x1 = $mid[$tok['id'].""]; 
				$x2 = $mid[$tok['head'].""];

				# if ( $x1 > $x2 ) { $x1 -= 5; } else { $x1 += 5; }; // This puts the arrow next to each other..
				$y1 = 25; // Y of the arch
				$w = $x2-$x1; // width of the arch
				$h = floor(abs($w/2));  // height of the arch 
				$y2 = $y1 + $h; // top of the arch
				if ( $x2 ) $maxheight = max($y2, $maxheight);  # Leave out arches without a head
 
				$os = floor($w/8); // curve point distance
				$r1 = $x1+$os; $r2 = $x2-$os; // curve points
				// place the arch
				if ( $x2 ) $treearches .= "\n\t<path title=\"{$tok['deprel']}\" d=\"M$x1 $y1 C $r1 $y2, $r2 $y2, $x2 $y1\" stroke=\"#992000\" stroke-width=\"0.5\" fill=\"transparent\" />"; #  marker-end=\"url(#arrow)\"
				// place a dot on the end (better than arrow)
				if ( $x2 ) $treearches .= "<circle cx=\"$x1\" cy=\"$y1\" r=\"2\" stroke=\"black\" stroke-width=\"1\" fill=\"black\" />";

 				if ( $tok['deprel'] || ( $username && $act == "edit" ) ) {
 					$lw = 5.8*(mb_strlen($tok['deprel']));
 					$lx = $x1 + $w/2 - ($lw/2); // X of the text
 					$ly = $y1 + $h*0.75 - 5; // Y of the text
 					$deplabel = $tok['deprel']."";
					if ( $username && $act == "edit" ) {
						if ( $deplabel == "" ) $deplabel = "??";
						$onclick = " onClick=\"relabel(this);\"";
					};
 					$labeltxt = $deplabels[$tok['deprel'].""]['description'];
 					$treelabels .= "\n\t<text x=\"$lx\" y=\"$ly\" font-family=\"Courier\" fill=\"#aa2000\" font-size=\"10\" type=\"label\" onMouseOver=\"this.setAttribute('fill', '#20aa00');\" baseid=\"{$tok['id']}\" title=\"$labeltxt\" $onclick>$deplabel</text> ";
 				};
			};
		};
		$treetxt .= $treearches;
		$treetxt .= $treelabels;

		$width = $xpos + 50;
		$height = $maxheight;
				
		$graph = "<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"$width\" height=\"$height\">
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

?>