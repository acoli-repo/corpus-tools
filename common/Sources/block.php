<?php
	// Script to display the <s> elements in an XML file
	// and all the features defined over each sentence
	// Should prob. be extended to do <u> as well
	// (c) Maarten Janssen, 2015
	
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;

	# Show sentence view
	$stype = $_GET['elm'] or $stype = "s";
	if ( $stype == "1" ) $stype = "s";

	// When so indicated, load the external PSDX file so we can link to existing trees
	if ( $settings['psdx'] && file_exists( "Annotations/$xmlid.psdx") ) {
		$psdx = simplexml_load_file("Annotations/$xmlid.psdx", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	};
	
	$blockdef = $settings['xmlfile']['sattributes'][$stype];
	$defdef = array ( "s" => "Sentence", "p" => "Paragraph", ); 

	// Set a default writing direction when defined
	$dirxpath = $settings['xmlfile']['direction'];
	if ( $dirxpath ) {
		$textdir = current($xml->xpath($dirxpath));
	};
	if ( $textdir ) {
		$dircss = "direction: $textdir";
	} else if ( $settings['xmlfile']['basedirection'] ) {
		$dircss = "direction: {$settings['xmlfile']['basedirection']}";
	};
		
	$ssel = $_GET['jmp'] or $ssel = $_GET['sel'];
	if ( $ssel ) $sel = "[.//tok[@id='$ssel']]";

	$jmp = $_GET['jmp'] or $ssel = $_GET['sel'] or $ssel = $_GET['sid'];
	
	$stype = str_replace("|", "| //", $stype);
	$result = $xml->xpath("//$stype$sel"); 


	$sentnr = 1; $ewd = 25; $strt = 0; $perpage = $_GET['perpage'] or $perpage = 100;
	$rescnt = count($result);
	foreach ( $result as $sent ) {
		$stxt = makexml($sent); 
		
		if ( $_GET['jmp'] && !$jumped && $sent['id'] != $_GET['jmp'] ) { $strt++; continue; };
		if ( $strt < $_GET['start'] && !$jumped  ) { $strt++; continue; };
		if ( $cnt >= $perpage ) break;
		$jumped = 1; $cnt++;
		
		if ( $stype == "lb" ) {
			$linepos = strpos($ttxml->rawtext, $stxt);
			$nextlb = strpos($ttxml->rawtext, "<lb", $linepos+1);
			$nextpb = strpos($ttxml->rawtext, "<pb", $linepos+1);
			$lineend = min($nextlb, $nextpb) or $lineend = $nextlb or $lineend = $nextpb;
			if ( !$lineend ) $lineend = strpos($ttxml->rawtext, "</text", $linepos+1);
			$stxt = substr($ttxml->rawtext, $linepos, $lineend-$linepos);
		};
		
		$sentid = $sent['n'] or $sentid = "[".$sentnr++."]";
		$treelink = ""; $nrblock = "";
		if ( $sent->xpath(".//tok[@head and @head != \"\"]") ) { 
			$treelink .= "<a href='index.php?action=deptree&cid=$fileid&sid={$sent['id']}' title='dependency tree'>tree</a>"; 
			$ewd = 70;
		};
		if ( $sent['appid'] ) {
			$cid = $xmlid;
			$treelink .= "<a href='index.php?action=collate&act=cqp&baselevel=$stype&appid={$sent['appid']}&from=$cid' title='witness collation'>app</a>"; 
			$ewd = 70;
		};
		if ( $psdx  && $stype == "s" ) { // Allow a direct link to a PSDX tree 
			$nrblock = "
				<div style='display: inline-block; float: left; margin: 0px; padding: 0px; width: 80px;'>
				<table style='width: 100%; table-layout:fixed; margin: 0px;'><tr><td style='width: 25px;font-size: 10pt; '>";
			if ( $psdx->xpath("//forest[@sentid=\"$sentid\"]") ) {
				$editxml .= "<a href='index.php?action=psdx&cid=$xmlid&sentence=$sentid'>tree</a>";
			};
			$pl = "100px";
			if ( $username ) {
				$editxml .= " 
					<td style='width: 25px;font-size: 10pt; '><a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>edit</a>";
				$pl = "100px";
			};
			$nrblock .= " 
				<td style='width: 30px;font-size: 10pt;  text-align: right;'>$sentid </table></div>";
		}  else {
			if ( $username ) $sentnumtxt = "<a href='index.php?action=sentedit&cid=$fileid&sid={$sent['id']}'>$sentid</a>";
			else $sentnumtxt = "$sentid";

			$nrblock = "
				<div style='display: inline-block; float: left; margin: 0px; padding: 0px; padding-top: 0px; width: {$ewd}px; font-size: 10pt;'>
					$sentnumtxt
					$treelink
				</div>";
		
			$pl = $ewd."px";
		};
		$editxml .= "
			<div style='width: 90%; border-bottom: 1px solid #66aa66; margin-bottom: 6px; padding-bottom: 6px; clear: left;'>
			$nrblock
			<div style='padding-left: $pl;'>
			<div style='$dircss'>$stxt</div>
			<div class=blockdata>";
		foreach ( $settings['xmlfile']['sattributes'][$stype] as $item ) {
			if ( !is_array($item) ) continue;
			$key = $item['key'];
			if ( $item['noshow'] ) continue;
			$atv = preg_replace("/\/\//", "<lb/>", $sent[$key]);	
			if ( $item && $item['color']) { $scol = "style='color: {$item['color']}'"; } else { $scol = "class='s-$key'"; };
			if ( $atv && ( !$item['admin'] || $username ) ) {
				if ( $item['admin'] ) $scol .= " class='adminpart'";
				$editxml .= "<div $scol title='{$item['display']}'>$atv</div>"; 
			}
		};
		$editxml .= "</div></div></div>";
	};
	
	$blockname = $blockdef['display'] or $blockname = $defdef[$stype] or $blockname = $stype;
	$maintext .= "<h2>{%$blockname view}</h2><h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();
	$tmp = $ttxml->viewopts();
	$viewoptions = $tmp['view']; $showoptions = $tmp['show'];

				$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
				$jsontags = array2json($settings['xmlfile']['pattributes']['tags']);
				$jsontrans = array2json($settings['transliteration']);

				if ( $tagstxt ) $showoptions .= "<p>{%Tags}: $tagstxt ";

			$miniurl = preg_replace("/(&start=\d+|&jmp=[^&]+)/", "", "index.php?".$_SERVER['QUERY_STRING']);

			if ( $perpage < $rescnt ) $countrow = "{%showing} ".($strt+1)." - ".($strt+$perpage)." {%of} $rescnt";
			if ( $strt ) { $countrow .= " &bull; <a href='$miniurl&start=".max(0,$strt-$perpage)."'>{%previous}</a>"; };
			if ( $strt + $perpage < $rescnt ) { $countrow .= " &bull; <a href='$miniurl&start=".min($rescnt,$strt+$perpage)."'>{%next}</a>"; $sep = " &bull; "; };
			if ( $countrow ) $countrow = "<p>$countrow</p><hr>";

			$maintext .= "
					$viewoptions $showoptions						<hr>
				<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				$countrow
				<div id='mtxt'$tba><text><table $direc>$editxml</table></text></div>

					<script language=Javascript src='$jsurl/tokedit.js'></script>
					<script language=Javascript src='$jsurl/tokview.js'></script>
					<script language=Javascript>
						var username = '$username';
						var formdef = $jsonforms;
						var tagdef = $jsontags;
						var orgtoks = new Object();
						var noimg = true;
						var tid = '$ttxml->fileid';
						var attributelist = Array($attlisttxt);
						$attnamelist
						formify();
						var orgXML = document.getElementById('mtxt').innerHTML;
						setForm('$showform');

						function hllist ( ids, container, color ) {
							idlist = ids.split(' ');
							for ( var i=0; i<idlist.length; i++ ) {
								var id = idlist[i];
								// node = getElementByXpath('//*[@id=\"'+container+'\"]//*[@id=\"'+id+'\"]');
								node = document.getElementById(container+'_'+id);
								if ( node ) {
									if ( node.nodeName == 'DTOK' ) {
										node = node.parentNode;
										if ( color == '#ffffaa' ) {
											node.style['background-color'] = '#ffeeaa';
											node.style.backgroundColor= '#ffeeaa';
										} else {
											node.style['background-color'] = '#ffcccc';
											node.style.backgroundColor= '#ffcccc';
										};
									} else {
										node.style['background-color'] = color;
										node.style.backgroundColor= color;
									};
								};
							};
						};
						function getElementByXpath(path) {
							return document.evaluate(path, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
						}
					</script>

				<script language=Javascript>$moreactions</script>
				";
			

	$maintext .= "<hr><p><a href='index.php?action=sentedit&cid=$ttxml->fileid&elm=$stype&sid=multi'>Edit as list</a> &bull; ".$ttxml->viewswitch();
	

?>