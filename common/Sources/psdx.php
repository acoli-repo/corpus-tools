<?php

	$cid = $_GET['cid'];
	$cid = preg_replace("/.*\//", "", $cid);
	$cid = preg_replace("/\.xml/", "", $cid);
	$treeid = $_GET['treeid'];
	$sid = $_GET['sentence'] or $sid = $_GET['sid'];
	$xpath = $_POST['xpath'] or $xpath = $_GET['xpath'] or $xpath = $_POST['query'] or $xpath = $_GET['query'];
	if ( !$xpath && $_GET['qid'] && ( $userid || $username ) ) {
		require("$ttroot/common/Sources/querymng.php");
		$qid = $_GET['qid'];
		$xpath = getq($qid);
		$act = "xpath";
	};
	$xpath = stripslashes($xpath);
	if ( $cid ) {
		$psdxfile = "Annotations/$cid.psdx";
		if ( !file_exists($psdxfile) ) {
			fatal("No such PSDX annotation: $cid"); 
		};
	};
		
	if ( $act == 'query' ) {
	
		print "Reloading to BTQL
			<script language=Javascript>window.location='index.php?action=btql&type=PSDX&query={$_GET['query']}'</script>
			";
	
	} else if ( $sid && $psdxfile ) {
	 
		require_once("$ttroot/common/Sources/ttxml.php");
	
		$psdx = simplexml_load_file($psdxfile);
		if ( !$psdx ) {
			fatal("Unable to load PSDX file: $cid");
		};
	
		$maintext .= "<h2>Tree Viewer</h2>";
	
		$ttxml = new TTXML();

		$forest = current($psdx->xpath("//forest[@sentid=\"$sid\"]"));
	
		$maintext .= "<h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();
		$tmp = $forest->xpath("./preceding::forest[1]");
		if ( $tmp ) {
			$prid = current($tmp)['sentid'];
			$prev = "<a href='index.php?action=$action&cid=$ttxml->fileid&sid=$prid'>&lt;</a>";
		};
		$tmp = $forest->xpath("./following::forest[1]");
		if ( $tmp ) {
			$flid = current($tmp)['sentid'];
			$next = "<a href='index.php?action=$action&cid=$ttxml->fileid&sid=$flid'>&gt;</a>";
		};
		$maintext .= "<table style='width: 100%'><tr><td>$prev<td style='text-align: center'><b>Tree {$forest['id']} = Sentence $sid</b></td><td>$next</tr></table>
		<hr>";
		$tmp = current($ttxml->xml->xpath("//s[@id=\"$sid\"]"));
		if ( !$tmp ) { fatal("no such sentence: $sid"); };
		$editxml = $tmp->asXML();
		$maintext .= "<div id=mtxt>$editxml</div> <hr>";

		$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
		$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	

		$json = psdx2tree($forest);

		if ( $_GET['jmp'] ) {
			$jmp = $_GET['jmp'];
			$maintext .= "<script>
				function posttree () {
					var hlnode = document.evaluate('//*[@nodeid=\"$jmp\"]', document, null, XPathResult.ANY_TYPE, null).iterateNext();
					if ( hlnode ) {
						hlnode.setAttribute('fill', '#992200');
						hlnode.setAttribute('font-weight', 'bold');
					};
				};
			</script>";
		};
		
		$maintext .= "<div id=svgdiv></div>

			<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/tokview.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/treeview.js\"></script>
			<script>
				$settingsdefs
				var tree = $json;
				var treeid = '$ttxml->xmlid-$sid';
				var ctree = 1;
				var hpos = 'wordorder';
				document.getElementById('tokinfo').style['z-index'] = 3000;
				drawsvg(tree, 'svgdiv');

	
			</script>";
	
	$maintext .= "<hr><p><a href='index.php?action=$action&cid=$ttxml->fileid&'>{%Sentence list}</a> &bull; ".$ttxml->viewswitch();

	} else if ( $psdxfile ) {

		require_once("$ttroot/common/Sources/ttxml.php");
	
		$psdx = simplexml_load_file($psdxfile);
		if ( !$psdx ) {
			fatal("Unable to load PSDX file: $cid");
		};
	
		$maintext .= "<h2>Tree Viewer</h2>
			<p>Select a sentence from the tree below</p>
			<table>";
	
		$ttxml = new TTXML();

		foreach ( $psdx->xpath("//forest") as $forest ) {
			$sentid = $forest['sentid'];
			$fileid = $ttxml->fileid;
			$sent = sentbyid($fileid, $sentid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&sid=$sentid'>tree</a><td>".$sent;
		};
		$maintext .= "</table>";

	} else {
		
		$maintext .= "
			<h1>Tree Viewer</h1>
			<p>{%Select a file from the list below or} <a href='index.php?action=btql&type=PSDX'>{%Search}</a><hr>
			<table>";
	
		# Show all available PSDX files
		foreach (glob("Annotations/*.psdx") as $filename) {
			$some = 1; $anid = preg_replace( "/.*\/(.*?)\.psdx/", "\\1", $filename );
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$anid'>".ucfirst($anid)."</a><td>";
		};
		$maintext .= "</table>";
		if ( !$some ) $maintext .= "<p><i>{%No results found}</i>";

	};
	
	function psdx2tree($node) {
	
		$label = $node['Label'] or $label = $node['Text'] or $label = $node['Notext'] or $label = $node['NoText'];
		
		if ( count($node->children()) ) {
			$children = ", \"children\": [";
			$sep = "";
			foreach ( $node->children() as $child ) {
				$children .= "$sep ".psdx2tree($child);
				$sep = ",";
			};
			$children .= "]";
		};
		
		if ( in_array( $label, str_split("!?(){}.,:;") )  ) $children .= ", \"ispunct\": 1"; 
		
		# $nodeid = $node['tokid'] or $nodeid = $node['id'];
		$json = "{ \"label\": \"$label\", \"nodeid\": \"{$node['id']}\", \"tokid\": \"{$node['tokid']}\" $children }";
	
		return $json;
	};

?>