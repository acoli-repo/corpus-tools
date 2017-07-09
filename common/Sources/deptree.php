<?php

	# Visualization of dependency trees
	# (c) Maarten Janssen, 2016
	
	$deplabels = $settings['deptree'];
	if ( !$deplabels ) {
		$deptreexml = simplexml_load_file("$ttroot/common/Resources/deptree.xml");
		$deplabels = xmlflatten($deptreexml);
	}; 

	# Define what counts as a token in the dependency graph	
	$toksel = ".//mtok[not(./dtok)] | .//tok[not(dtok) and not(ancestor::mtok)] | .//dtok[not(ancestor::tok/ancestor::mtok)]";
	
	$maintext .= "<h1>Dependency Tree</h1>"; 

	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, true, "keepns");
	
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 

	$sid = $_GET['sid'] or $sid = $_GET['sentence'];
	$cid = $ttxml->fileid;

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
		print "<script>top.location='{$_SERVER['REQUEST_URI']}';</script>";
		exit;		

	} else if ( $sid ) {

		if ( $_POST['sent'] ) {	
			$sent = simplexml_load_string($_POST['sent']);
		} else {
			$sent =	current($ttxml->xml->xpath("//s[@id='$sid']"));
		};
		$fileid = $ttxml->fileid;
		if ( !$sent ) { fatal("Sentence not found: $sid"); };

		# Determine whether to show punctuation in tree
		if ( isset($_GET['showpunct']) ) {
			$showpunct = $_GET['showpunct'];
			$_SESSION['showpunct'] = $showpunct;
		} else {
			if ( isset($_SESSION['showpunct']) ) {
				$showpunct = $_SESSION['showpunct'];
			} else if ( isset($settings['deptree']['showpunct']) ) {
				$showpunct = $settings['deptree']['showpunct'];
			} else {
				$showpunct = 0;
			};
		};

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
			$nnav = "<a href='index.php?action=$action&cid=$fileid&sid=$nid&view={$_GET['view']}'>> $nexts</a>";
		};
		$prevsent = current($sent->xpath("preceding::s[1]")); 
		if ( $prevsent ) {
			$pid = $prevsent['id'];
			$prevs = $prevsent['n'] or $prevs = $pid;
			$bnav = "<a href='index.php?action=$action&cid=$fileid&sid=$pid&view={$_GET['view']}'>$prevs <</a>";
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
			$maintext .= "<p><span id='linktxt'>Click <a href='index.php?action=$action&act=edit&sid=$sid&cid=$cid&view={$_GET['view']}'>here</a> to edit the dependency tree</a></span>";
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
				$deptreename = $deplabels['description'] or $deptreename = "Dependency Relations";
				$labelstxt = "<h2 style=\"margin-top: 0px; margin-bottom: 5px;\">$deptreename</h2><table>";
				foreach ( $deplabels['labels'] as $key => $val ) {
					$labelstxt .= " <tr onclick=\"setlabel(this);\" key=\"$key\"><th>$key<td>{$val['description']}</tr>";
				};
				$labelstxt .= "<table>";
			};
		};

		if ( $settings['xmlfile']['basedirection'] ) {
			// Defined in the settings
			$textdir = "dir='{$settings['xmlfile']['basedirection']}'";
		} else {
			$dirxpath = $settings['xmlfile']['direction'];
			if ( $dirxpath ) {
				$textdir = current($ttxml->xml->xpath($dirxpath));
			};
			if ( $textdir ) {
				// Defined in the teiHeader for mixed-writing corpora
				$textdir = "dir='$textdir'";
			};
		};
		
		$maintext .= "			
			<div id=mtxt $textdir>".$sent->asXML()."</div><hr>";

		if ( $_GET['view'] == "graph" ) {
			$graph = drawgraph($sent);
		} else {
			$graph = drawtree($sent);
		};
		
		if ( $username && $act == "edit" ) {
			$maintext .= "
			<script language=\"Javascript\">var labelstxt = 'Select a dependency label from the popout list <div class=\"popoutlist\">$labelstxt</div>';</script>
			";
		};
	
		$maintext .= "\n
<script language=\"Javascript\" src=\"$jsurl/tokview.js\"></script>
<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
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
<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
<hr>
<p>
	<a href='index.php?action=file&cid={$ttxml->fileid}&tid=$sid'>{%Text view}</a>
	&bull;
	<a href='index.php?action=file&cid={$ttxml->fileid}&tid=$sid&elm=s'>{%Sentence view}</a>
";
if ( $_GET['view'] != "graph" ) {
	$maintext .= "
	&bull;
	<a href='index.php?action=$action&cid={$ttxml->fileid}&sid=$sid&view=graph'>{%Graph view}</a>
	&bull; ";
	if ( $showpunct ) 
		$maintext .= "<a href='index.php?action=$action&cid={$ttxml->fileid}&sid=$sid&view=tree&showpunct=0'>{%Hide punctuation}</a>";
	else
		$maintext .= "<a href='index.php?action=$action&cid={$ttxml->fileid}&sid=$sid&view=tree&showpunct=1'>{%Show punctuation}</a>";

} else {
	$maintext .= "
	&bull;
	<a href='index.php?action=$action&cid={$ttxml->fileid}&sid=$sid&view=tree'>{%Tree view}</a>
	";
};

$graphbase = base64_encode(str_replace('<svg ', '<svg xmlns="http://www.w3.org/2000/svg" ', $graph));
$maintext .= " &bull; <a href='data:image/svg+xml;base64,$graphbase' download=\"deptree.svg\">{%Download SVG}</a>";


	} else {
	
		$maintext .= "
			<p>Select a sentence
			<table id=mtxt>"; 
		
		foreach ( $ttxml->xml->xpath("//s") as $sent ) {
			
			$maintext .= "<tr><td><a href='index.php?action=$action&cid={$ttxml->fileid}&sid={$sent['id']}'>{$sent['id']}
				<td>".$sent->asXML();
			
		};
		$maintext .= "</table>
		<hr><p><a href='index.php?action=file&cid={$ttxml->fileid}'>{%Text view}</a>";
	
	};	

	function drawtree ( $node ) {
		$treetxt = "";
		global $xpos; global $username; global $act; global $deplabels; global $toksel; global $maxheight; global $maxwidth;

		if ( $username && $act == "edit" ) {
			$onclick = " onClick=\"relink(this);\"";
		};

		# Read the tokens
		$i = 0;
		$svgtxt .= "<svg version=\"1.1\" width=\"100%\" height=\"500\">"; # xmlns=\"http://www.w3.org/2000/svg\" 
		foreach ( $node->xpath($toksel) as $tok ) {
			$text = $tok['form'] or $text = $tok."";
			if ( $text == "" ) $text = "∅";
			if ( $tok['pos'] != "PUNCT" || $showpunct ) {
				$svgtxt .= "\n\t<text text-anchor='middle' tokid=\"{$tok['id']}\" font-size=\"12pt\" type=\"tok\" head=\"{$tok['head']}\" deprel=\"{$tok['deprel']}\" $onclick>$text</text> ";
			};
		};
		$svgtxt .= "</svg>";
		
		$svgxml = simplexml_load_string($svgtxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING); // Put the leaves and lines on the canvas
		if ( !$svgxml ) print "ERROR!"; #return "<p><i>Error while drawing tree</i>";

		while (	$result = $svgxml->xpath('//text[not(@row)]') ) {
			foreach ( $result as $textnode ) { 
				
				unset($row);
				if ( !$textnode['head'] ) {
					$row = 0;
				} else {
					$headnode = current($svgxml->xpath("//text[@tokid=\"{$textnode['head']}\"]"));
					if ( !$headnode ) {
						$row = 0;
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
				};
			};
		};

		$mainnode = current($svgxml->xpath("//svg"));
		$mainnode['height'] = $maxheight*100 + 50;
		$mainnode['width'] = $maxwidth*100 + 50;
				
		$j = ($maxwidth - $elms[0])/2; 
		foreach ( $svgxml->xpath("//text[@row=\"0\"]") as $textnode ) {
			$textnode['col'] = $j++;
			$textnode['x'] = $textnode['col']*100 + 70; # Simply distribute roots horizontally
		};
		for ( $i=1; $i<$maxheight+1; $i++ ) {
			$cols = array(); $k = 0;
			# Try to put every node under its parent
			foreach ( $svgxml->xpath("//text[@row=\"$i\"]") as $j => $textnode ) {
				$headnode = current($svgxml->xpath("//text[@tokid=\"{$textnode['head']}\"]"));
				$cols[$j] = $headnode['col'].'';
			};

			# Order the nodes to avoid needless crossing
			$tmp = $cols;
			uksort($tmp,function($a,$b)use($tmp){
				if($tmp[$a] < $tmp[$b]){
					return -1;
				 }
				 if($tmp[$a] > $tmp[$b]){ 
					return 1;
				 }
				 return strcmp($a,$b);
			  });          			
			
			$headcols = $cols;
			$ocols = array_keys($tmp);

			$conflict = 0; $k = 0; $conflicted = 1;
			# Find the optimal horizontal distribution for this row
			while ( $conflicted && $k < 10 ) {
				$k++; $conflicted = 0; 
				for ( $ji=0; $ji<count($ocols); $ji++ ) {
					$j = $ocols[$ji];
					$nextj = $ocols[($ji+1)];
					$cols[$j] = floatval($cols[$j]) + floatval($conflict);
					if ( $nextj && floatval($cols[$nextj])-floatval($cols[$j])+floatval($conflict) < 1 ) {	
						$cols[$j] = max(0, floatval($cols[$j]) - 0.5);
						$conflict = floatval($conflict) + 0.5;
						$conflicted = 1;
					} else if ( $nextj && floatval($cols[$nextj])-floatval($cols[$j])+floatval($conflict) > 1 && $cols[$nextj] > $headcols[$nextj] ) {	
						# TODO: This does not work - we should close a gap to 1 if that moves it left of its parent...
						$conflict = floatval($conflict) - 0.5;
						$conflicted = 1;
					};
					$maxwidth = max($maxwidth, $cols[$j]);
				};
				if ( floatval($conflict) > 1 ) $conflict = -0.5; else $conflict = 0;
			};
			foreach ( $svgxml->xpath("//text[@row=\"$i\"]") as $j => $textnode ) {
				$textnode['col'] = $cols[$j];
				$textnode['x'] = $cols[$j]*100 + 70;
			};
		};

		$mainnode['width'] = $maxwidth*100 + 170;

		# Draw the lines
		foreach ( $svgxml->xpath("//text") as $textnode ) {

			$headnode = current($svgxml->xpath("//text[@tokid=\"{$textnode['head']}\"]"));
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
			$newline['stroke'] ='#992000';

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
				
				if ( $text == "" ) $text = "∅";				

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

?>