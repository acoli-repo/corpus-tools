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
	$ttxml = new TTXML($cid, true);
	
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
			if ( $tok['deps']."" != $newtok['deps']."" ) {
				print "<p>$tokid: {$tok['deps']} => {$newtok['deps']}</p>";
				$tok['deps'] = $newtok['deps'];
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

		if ( $_GET['auto'] ) {
			foreach ( $deplabels['labels'] as $key => $val ) { if ( $val['base'] ) $nonamb[$val['base']] = $key; };
			foreach ( $sent->xpath($toksel) as $tok ) {
				if ( !$tok['deps'] && $nonamb[$tok['pos'].""] ) { 
					$tok['deps'] = $nonamb[$tok['pos'].""];
				}; 
			};
		};

		# Find the next/previous sentence (for navigation)
		$nextsent = current($sent->xpath("following::s")); 
		if ( $nextsent ) {
			$nid = $nextsent['id'];
			$nexts = $nextsent['n'] or $nexts = $nid;
			$nnav = "<a href='index.php?action=$action&cid=$fileid&sid=$nid'>> $nexts</a>";
		};
		$prevsent = current($sent->xpath("preceding::s[1]")); 
		if ( $prevsent ) {
			$pid = $prevsent['id'];
			$prevs = $prevsent['n'] or $prevs = $pid;
			$bnav = "<a href='index.php?action=$action&cid=$fileid&sid=$pid'>$prevs <</a>";
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
			if ( $_POST['sent'] ) {
				$maintext .= " - <span style='color: #cc2000;' onClick=\"document.sentform.action.value = 'save'; document.sentform.submit();\"><b>Click here to save the modified dependencies</b><span>";
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

		$maintext .= "			
			<div id=mtxt>".$sent->asXML()."</div><hr>";

		$graph = drawgraph($sent);
		$width = $xpos + 50;
		# $height = $width/2.5;
		$height = $maxheight;

		if ( $username && $act == "edit" ) {
			$maintext .= "
			<script language=\"Javascript\">var labelstxt = 'Select a dependency label from the popout list <div class=\"popoutlist\">$labelstxt</div>';</script>
			";
		};
	
		$maintext .= "\n
<script language=\"Javascript\" src=\"$jsurl/tokview.js\"></script>
<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"$width\" height=\"$height\">
	<defs>
		<marker id=\"arrow\" markerWidth=\"10\" markerHeight=\"8\" refx=\"0\" refy=\"3\" orient=\"auto\" markerUnits=\"strokeWidth\">
			<path d=\"M0,0 L0,6 L9,3 z\" fill=\"#000\" />
		</marker>
	</defs>
	$graph
</svg>
</div>
<style>
.toktext { 
    text-decoration: underline;
    -moz-text-decoration-color: red; 
    text-decoration-color: red;
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
				if ( $x2 ) $treearches .= "\n\t<path title=\"{$tok['deps']}\" d=\"M$x1 $y1 C $r1 $y2, $r2 $y2, $x2 $y1\" stroke=\"black\" fill=\"transparent\" />"; #  marker-end=\"url(#arrow)\"
				// place a dot on the end (better than arrow)
				if ( $x2 ) $treearches .= "<circle cx=\"$x1\" cy=\"$y1\" r=\"2\" stroke=\"black\" stroke-width=\"1\" fill=\"black\" />";

 				if ( $tok['deps'] || ( $username && $act == "edit" ) ) {
 					$lw = 5.8*(mb_strlen($tok['deps']));
 					$lx = $x1 + $w/2 - ($lw/2); // X of the text
 					$ly = $y1 + $h*0.75 - 5; // Y of the text
 					$deplabel = $tok['deps']."";
					if ( $username && $act == "edit" ) {
						$onclick = " onClick=\"relabel(this);\"";
					};
 					if ( $username && $act == "edit"  && !$deplabel ) { 
 						$deplabel = "??"; 
 					};
 					$labeltxt = $deplabels[$tok['deps'].""]['description'];
 					$treelabels .= "\n\t<text x=\"$lx\" y=\"$ly\" font-family=\"Courier\" fill=\"#aa2000\" font-size=\"10\" type=\"label\" onMouseOver=\"this.setAttribute('fill', '#20aa00');\" baseid=\"{$tok['id']}\" title=\"$labeltxt\" $onclick>$deplabel</text> ";
 				};
			};
		};
		$treetxt .= $treearches;
		$treetxt .= $treelabels;
				
		return $treetxt;
	};

?>