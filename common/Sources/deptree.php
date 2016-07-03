<?php

	# Visualization of dependency trees
	# (c) Maarten Janssen, 2016
	
	$maintext .= "<h1>Dependency Tree</h1>"; 

	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, true);
	
	$maintext .= "<h2>".$ttxml->title()."</h2>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 

	$sid = $_GET['sid'] or $sid = $_GET['sentence'];

	if ( $sid ) {

		$sent =	current($ttxml->xml->xpath("//s[@id='$sid']"));
		$fileid = $ttxml->fileid;

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
						<td style='width: 33%' align=center>{%sentence} $sid
						<td style='width: 33%' align=right>$nnav
						</table>
						<hr>
						";

		$maintext .= "		$pagenav
			<div id=mtxt>".$sent->asXML()."</div><hr>";

		$graph = drawgraph($sent);
		$width = $xpos + 50;
		$height = $width/3;
	
		$maintext .= "\n
<script language=\"Javascript\" src=\"$jsurl/tokview.js\"></script>
<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"$width\" height=\"$height\">
	<defs>
		<marker id=\"arrow\" markerWidth=\"10\" markerHeight=\"10\" refx=\"0\" refy=\"3\" orient=\"auto\" markerUnits=\"strokeWidth\">
			<path d=\"M0,0 L0,6 L9,3 z\" fill=\"#f00\" />
		</marker>
	</defs>
	$graph
</svg>
</div>
<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
";
		# $maintext .= "<div id=svg>".drawgraph($sent)."</div><hr>";

	} else {
	
		$maintext .= "
			<p>Select a sentence
			<table id=mtxt>"; 
		
		foreach ( $ttxml->xml->xpath("//s") as $sent ) {
			
			$maintext .= "<tr><td><a href='index.php?action=$action&cid={$ttxml->fileid}&sid={$sent['id']}'>{$sent['id']}
				<td>".$sent->asXML();
			
		};
		$maintext .= "</table>";
	
	};	

	function drawgraph ( $node ) {
		$treetxt = "";
		global $xpos;
		$xpos = 0;
		foreach ( $node->xpath(".//mtok[not(.//dtok)] | .//tok[not(dtok) and not(ancestor::mtok)] | .//dtok") as $tok ) {
		
			$text = $tok['form'] or $text = $tok."";
			$tokid = $tok['id']."";
			
			if ( $text != "" ) { 		
				
				# $bbox = imagettfbbox(12, 0, "tmp/Arial.ttf", $text);
				$treetxt .= "\n\t<text tokid=\"$tokid\" x=\"$xpos\" y=\"20\" font-family=\"Courier\" font-size=\"12\">$text</text> ";
				$width = 6.9*(mb_strlen($text));
				$mid[$tokid] = $xpos + ($width/2) + 2;
				$xpos = $xpos+12+$width;
			
			};
				
		};

		$treetxt .= "\n";

		foreach ( $node->xpath(".//mtok[not(.//dtok)] | .//tok[not(dtok) and not(ancestor::mtok)] | .//dtok") as $tok ) {
			if ( $tok['head'] ) {
				$x1 = $mid[$tok['id'].""]; $x2 = $mid[$tok['head'].""];
				$y1 = 25;
				$w = $x2-$x1; 
				$h = floor(abs($w/2.5));  $os = floor($w/8);
				$y2 = $y1 + $h;
				$r1 = $x1+$os; $r2 = $x2-$os;
				$treetxt .= "\n\t<path title=\"{$tok['deps']}\" d=\"M$x1 $y1 C $r1 $y2, $r2 $y2, $x2 $y1\" stroke=\"black\" fill=\"transparent\" marker-end=\"url(#arrow)\"/>";
				$treetxt .= "<circle cx=\"$x1\" cy=\"$y1\" r=\"2\" stroke=\"black\" stroke-width=\"1\" fill=\"black\" />";
// 				if ( $tok['deps'] ) {
// 					$lx = $x1 + $w/2;
// 					$ly = $y2;
// 					$treetxt .= "\n\t<text x=\"$lx\" y=\"$ly\" font-family=\"Courier\" font-size=\"12\">{$tok['deps']}</text> ";
// 				};
			};
		};
				
		return $treetxt;
	};

?>