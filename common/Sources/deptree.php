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

		$maintext .= "<div id=mtxt>".$sent->asXML()."</div><hr>";

		$graph = drawgraph($sent);
		$width = $xpos + 50;
	
		$maintext .= "\n\n<svg xmlns=\"http://www.w3.org/2000/svg\" version=\"1.1\" width=\"$width\" height=\"300\">
			  <defs>
		<marker id=\"arrow\" markerWidth=\"10\" markerHeight=\"10\" refx=\"0\" refy=\"3\" orient=\"auto\" markerUnits=\"strokeWidth\">
		  <path d=\"M0,0 L0,6 L9,3 z\" fill=\"#f00\" />
		</marker>
	  </defs>
	  $graph
	  </svg>\n</div>";
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
		foreach ( $node->xpath(".//tok[not(dtok)] | .//dtok") as $tok ) {
		
			$text = $tok['form'] or $text = $tok."";
			$tokid = $tok['id']."";
			
			if ( $text != "" ) { 		
				
				# $bbox = imagettfbbox(12, 0, "tmp/Arial.ttf", $text);
				$treetxt .= "\n\t<text x=\"$xpos\" y=\"20\" font-family=\"Courier\" font-size=\"12\">$text</text> ";
				$width = 6.2*(mb_strlen($text));
				$mid[$tokid] = $xpos + ($width/2) + 2;
				$xpos = $xpos+12+$width;
			
			};
				
		};

		$treetxt .= "\n";

		foreach ( $node->xpath(".//tok[not(@dtok)] | .//dtok") as $tok ) {
			if ( $tok['head'] ) {
				$x1 = $mid[$tok['id'].""]; $x2 = $mid[$tok['head'].""];
				$y1 = 25;
				$w = $x2-$x1; 
				$h = floor(abs($w/2.5));  $os = floor($w/8);
				$y2 = $y1 + $h;
				$r1 = $x1+$os; $r2 = $x2-$os;
				$treetxt .= "\n\t<path d=\"M$x1 $y1 C $r1 $y2, $r2 $y2, $x2 $y1\" stroke=\"black\" fill=\"transparent\" marker-end=\"url(#arrow)\"/>";
				$treetxt .= "<circle cx=\"$x1\" cy=\"$y1\" r=\"2\" stroke=\"black\" stroke-width=\"1\" fill=\"black\" />";
			};
		};
				
		return $treetxt;
	};

?>