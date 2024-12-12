<?php
	// Script to edit sentences <s> in an XML file
	// only partially finished - avoid using
	// (c) Maarten Janssen, 2015

	check_login();

	$sentatts = getset('xmlfile/sattributes');

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$sentid = $_POST['sid'] or $sentid = $_GET['sid'];
	$stype = $_GET['sentence'] or $stype = $_GET['elm'] or $stype = "s";
	if ( !is_array($sentatts) || !is_array($sentatts[$stype]) ) fatal("No definitions provided for $stype");
	$sentname = $sentatts[$stf]['display'] or $sentname = "Sentence";

	if ( $fileid ) { 
	
		require("$ttroot/common/Sources/ttxml.php");
		
		$ttxml = new TTXML($fileid, 1, "keepns");
		$xml = $ttxml->xml;

		if ( $act == "save" ) {
		
			foreach ( $_POST['matts'] as $sentid => $val ) {
				print "<p>$sentid: ";
				$sent = current($xml->xpath("//*[@id='$sentid' or @xml:id='$sentid']"));
				if ( !$sent ) {
					print "<p>Oops - $sentname not found: $sentid";
					continue;
				}; 
				foreach ( $val as $att => $val2 ) {
					print " $att => $val2";
					$sent[$att] = $val2;
				};
			};
			
			print "<p>Changes made - saving";
			$ttxml->save();
			if ( $_POST['last'] ) {
				$sxp = urlencode($_POST['sxp']);
				$start = $_POST['last']	;
				$pp = $_POST['pp'];
				$stype = $_POST['stype'];
				$doatt = $_POST['doatt'];
				print "<script>top.location='index.php?action=$action&cid=$ttxml->fileid&elm=$stype&perpage=$pp&start=$start&sid=multi&sxp=$sxp&doatt=$doatt'</script>";
			} else {
				print "<script>top.location='index.php?action=block&cid=$ttxml->fileid&elm=$stype'</script>";
			};
			exit;
		
		} else if ( $act == "merge" && $sentid ) {
		
		    
			$sent = current($xml->xpath("//*[@id='$sentid']")); 
			$stxt = sentbyid($ttxml->fileid, $sentid);
			$prevxp = "./preceding::$stype"."[1]";
			$prev = current($sent->xpath($prevxp));
			$psid = $prev['id'].'';
			if ( !$prev ) { 
				fatal("No element &lt;$stype&gt; on the left of $sentid - $prevxp");
			};
			$ptxt = sentbyid($ttxml->fileid, $psid);

			$ppar = current($prev->xpath("parent::*"));
			$spar = current($sent->xpath("parent::*"));
		    $pdom = dom_import_simplexml($ppar);

			if ( $sent->xpath(".//tok") && $prev->xpath(".//tok") ) {
				if ( $ppar != $spar ) {
					fatal("Sentences are not siblings - unable to merge");
				};
			};

			$stf = $sent->getName();
			foreach ( $sent->attributes() as $key => $val ) {
				$pval =  trim($prev[$key]);
				$op[$key] = $pval;
				$sval =  trim($sent[$key]);
				if ( $sval != ""  ) {
					if ( $key == "id" && $pval != "" ) continue;
					$nval = $sval;
					$lnk = " + ";
					if ( $key == "corresp" || $key == "sameAs" ) $lnk = " "; 
					if ( $pval != "" && $sval != $pval ) $nval = $pval . $lnk . $sval;
					$prev[$key] = $nval;
				};
			};

			if ( $_GET['confirm'] ) {

				$pdom = dom_import_simplexml($prev);
				$sdom = dom_import_simplexml($sent);
				$sch = $sdom->hasChildNodes();
				$pch = $pdom->hasChildNodes();
				if ( ( $sch && !$pch ) || ( !$sch && $pch ) ) {
					print "<p>Resolving emptys mismatch";
					# We cannot have one s with and one without children - move everything out
					foreach ( $pdom->childNodes as $child ) {
						dom_insert_after($child, $pdom);
					};
					# We cannot have one s with and one without children - move everything out
					foreach ( $sdom->childNodes as $child ) {
						dom_insert_after($child, $sdom);
					};
				};			
				
				if ( $sch && $pch ) {
					print "<p>With children: ".count($sdom->childNodes)." - moving inside";
					$pnext = $pdom->nextSibling;
					while ( $pnext && $pnext != $sdom ) {
						# print htmlentities($pnext->ownerDocument->saveXML($pnext))."<hr>";
						$pdom->appendChild($pnext);	
						$pnext = $pdom->nextSibling;
					};
					while ( $sdom->hasChildNodes() ) {
						$child = $sdom->childNodes->item(0);
						# print htmlentities($child->ownerDocument->saveXML($child))."<hr>";
						$pdom->appendChild($child);	
					};
				} else {
					print "Without children";
					// If both are empty, we don't need to do anything
				};
				$sdom->parentNode->removeChild($sdom);					
				
				print "<hr>";
				$ppar = current($prev->xpath("parent::*"));
				$ttxml->save();
				print "<p>Correctly merged - reloading
					<script>top.location = 'index.php?action=$action&cid=$ttxml->fileid&sid=$psid'</script>";
				
			} else { 
				$maintext .= "<h1>Merge $sentname</h1>";
			
				$maintext .= "<p>New sentence: <div id=mtxt>".$ptxt." ".$stxt."</div>";

				// Show all the defined attributes
				$maintext .= "<hr><table>
					<tr><th>Attribute<th>Field name<th>Merged value<th>$sentname 1<th>$sentname 2";
				foreach ( $prev->attributes() as $key => $val ) {
					$atv = $prev[$key]; 
					$atn = $sentatts[$stf][$key]['display'] or $atn = "<i>Undefined</i>";
					$maintext .= "<tr><th>$key<td>$atn<td>$atv<td> {$op[$key]} <td>{$sent[$key]}";
				};
				$maintext .= "</table>";
			
				$maintext .= "<hr><p><a href='index.php?action=$action&act=merge&confirm=1&cid=$ttxml->fileid&sid=$sentid'>confirm merge</a>";
			};
					
		} else if ( $sentid == "multi" ) {
		
				
			$doatt = $_GET['doatt'];			
			$sxp = $_GET['sxp'];
			if ( $doatt ) {
				$dodef = $sentatts[$stype][$doatt];
				$doatts = array ( $doatt => $dodef );
				$drest = " - editing element <span style='font-style:italic' title='$doatt'>{$dodef['display']}</span> (<a href='".modurl("doatt", "")."'>reset</a>)";
				if ( $_GET['show'] == "all" ) {
					$xrest = "<p>Click <a href='".modurl("show", $doatt)."'>here</a> to show only <span title='$stype' style='font-style:italic'>$sentname</span> 
						without <span style='font-style:italic' title='$doatt'>{$dodef['display']}</span> 
						";
				} else {
					$srest = "[not(@$doatt) or @$doatt=\"\"]";
					$xrest = "<p>Showing only <span title='$stype' style='font-style:italic'>$sentname</span> 
						without <span style='font-style:italic' title='$doatt'>{$dodef['display']}</span> 
						- <a href='".modurl("show", "all")."'>show all</a>";
				};
			} else {
				$doatts = $sentatts[$stype];
				$xrest = "<p>Click on a column title to edit only one of the attributes";
			};
			if ( !$sxp ) $sxp = "//$stype$srest";
			
			$maintext .= "<h1>Multi-element edit</h1>
				<p>Element type: $sentname $drest";
				
			if ( $sxp ) { 
				$maintext .= "<p>Editable nodes selected by XPath: <b>$sxp</b>";
			};
				
			if ( !is_writable("xmlfiles/".$ttxml->fileid) ) $maintext .= "<p class='wrong adminpart'>This file $ttxml->fileid is not editable by the browser and changes will not be saved - please contact the corpus admin</p>";
			
			$maintable .= "<p>
				<form action='index.php?action=$action&act=save' method=post name=tagform id=tagform>
				<input type=hidden name=cid value='$fileid'>
				<input type=hidden name=sid value='$sentid'>
				<input type=hidden name=sxp value='$sxp'>
				<input type=hidden name=stype value='$stype'>
				<table id=rollovertable>\n<tr><th>ID<th>$sentname
				";
						
			# Show the title bar
			foreach ( $doatts as $key2 => $val2 ) {
				if ( !is_array($val2) ) continue;
				if ( $sentatts[$stype][$key2]['options'] ) {
					if ( $sentatts[$stype][$key2]['type'] == "radio" ) {
						$binary[$key2] = 1;
					} else {
						$attopts[$key2] = "<option value=\"\" disabled>[select]</option>";
						foreach ( $sentatts[$stype][$key2]['options'] as $key3 => $val3 ) {
							$attopts[$key2] .= "<option value='$key3'>{$val3['display']}</option>";
						};
					};
				};
				if ( $doatt ) $maintable .= "<th>{$val2['display']}</th>";
				else  $maintable .= "<th><a href='".modurl("doatt", $key2)."'>{$val2['display']}</a></th>";
			};
			$results = $xml->xpath($sxp); 

			$start = $_GET['start'] or $start = 0;
			$pp = $_GET['perpage'] or $pp = 100;
			$tot = count($results); $end = $start + $pp;
			if ( $start >= $tot ) {
				# We are done - reload
				$maintext .= "<hr><p><b>All elements done</b></p><hr>";
				$slice = array();
			} else  if ( $tot > $pp ) {
				$slice = array_slice($results, $start, $pp);
				$maintext .= "<p>Showing ".($start+1)." - $end of $tot";
				if ( $start > 0 ) {
					$jt = max(0,$start-$pp);
					$maintext .= " &bull; <a href='".modurl("start", $jt)."'>previous</a>";
				};
				if ( $end < $tot ) {
					$jt = $end;
					$maintext .= " &bull; <a href='".modurl("start", $jt)."'>next</a>";
				};
			} else {
				$slice = $results;
			};
			if ( count($slice) ) {
				$maintext .= $maintable.$xrest;
			
				foreach ( $slice as $sent ) {
					$sid = $sent['id'] or $sid = $sent['xml:id'];
					if ( !$sid ) {
						$fattxt = "Not all elements you are attempting to edit have an @id, making it impossible to edit them in this module. ";
						$misid[$sent->getName().''] = 1;
						if ( $xml->xpath("//tok") ) {
							$thisurl = urlencode($_SERVER['REQUEST_URI']); # THIS DOES NOT WORK IF REQUEST_URI IS SET INCORRECTLY
							$fattxt .= "This should get resolved by <a href='index.php?action=renumber&id=$ttxml->fileid&nexturl=$thisurl&xxx'>renumbering</a> the document.";
						} else {
							$fattxt .= "The document also has not been tokenized - you can choose to <a href='index.php?action=renumber&id=$ttxml->fileid&xxx'>renumber</a> before tokenization, or <a href='index.php?action=tokenize&id=$ttxml->fileid'>tokenize</a> the document (which will also renumber).";
						};
						if ( $user['permissions'] == "admin" ) {
							$fattxt .= "<hr><p>The (first) unnumbered element:<div>".htmlentities($sent->asXML())."</div>";
						};
					};
					$maintext .= "\n<tr><td><a href='index.php?action=file&cid=$ttxml->fileid&jmp=$sid'>$sid<td id=mtxt>".makexml($sent);
					foreach ( $doatts as $key2 => $val2 ) {
						if ( !is_array($val2) ) continue;
						$atv = $sent[$key2]; 
						$width = $val2['size'] or $width = 35;
						if ( $binary[$key2] ) {
							$maintext .= "<td>";
							foreach ( $sentatts[$stype][$key2]['options'] as $key3 => $val3 ) {
								$seld = ""; if ( $key3 == $atv ) $seld = "checked";
								$maintext .= "<div class=listcheck><input type=radio size='$width' name=matts[$sid][$key2] value='$key3' $seld> {$val3['display']}</div>";
							};
						} else if ( $attopts[$key2] ) {
							$maintext .= "<td><select name=matts[$sid][$key2] id=\"atts[$sid][$key2]\" value=\"$atv\">{$attopts[$key2]}</select>";
							$moreaction .= "document.getElementById('atts[$sid][$key2]').value = '$atv';";
						} else {
							$maintext .= "<td><input size='$width' name=matts[$sid][$key2] value='$atv'>";
						};
					};
				};
				if ( $fattxt ) {
					$fattxt = str_replace("&xxx", "&xx=".join(',', array_keys($misid)), $fattxt);
					fatal($fattxt);
				};
				$maintext .= "</table><p><input type='submit' value='Save'> <a href='index.php?action=file&cid=$fileid'>cancel</a>
					<input type=hidden name=last value='$end'>
					<input type=hidden name=pp value='$pp'>
					<input type=hidden name=doatt value='$doatt'>
					</form>
					<script language=Javascript>$moreaction</script>";
			};
			
			// Add a session logout tester
			$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";

		} else {
		
			if ( !$sentid ) fatal ( "No sentence specified" );

			$result = $xml->xpath("//*[@id='$sentid']"); 
			$sent = $result[0]; # print_r($token); exit;
			if ( !$sent ) fatal ( "Sentence not found: $sentid" );
			$stf = $sent->getName();
		
			$sentname = $sentatts[$stf]['display'] or $sentname = "Sentence";
		
			$maintext .= "<h1>Edit {$sentname} $sentid</h1>
				<div>Full text: <div id=mtxt style='inlinde-block;'>".makexml($sent)."</div></div>
			
				<p>
				<form action='index.php?action=toksave' method=post name=tagform id=tagform>
				<input type=hidden name=cid value='$fileid'>
				<input type=hidden name=tid value='$sentid'>
				<input type=hidden name=stype value='$stype'>
				<table>";
			

			// Show all the defined attributes
			foreach ( $sentatts[$stf] as $key => $val ) {
				$atv = $sent[$key]; 
				if ( is_array($val) ) $maintext .= "<tr><th>$key<td>{$val['display']}<td><textarea style='width: 600px' name=atts[$key] id='f$key'>$atv</textarea>";
			};

			$result = $xml->xpath($mtxtelement); 
			$txtxml = $result[0]; 

			// Should this work for other blocks as well?
			if ( $stype == "s" ) {
				$mergelink = "<a href='index.php?action=$action&act=merge&cid=$ttxml->fileid&sid=$sentid'>merge left</a>";
			;}

			$maintext .= "</table>
				<p><input type=submit value=Save> $mergelink
				</form>
				<hr><p>Click <a href='index.php?action=$action&cid=$ttxml->fileid&sid=multi'>here</a> to edit multiple sentences
				&bull;
				<a href='index.php?action=block&elm=$stype&cid=$ttxml->fileid&sid=$sentid'>cancel</a> 
				";
		};
		
	} else {
		print "Oops"; exit;
	};

	function dom_insert_after( $insert_dom,  $target_dom) 
	{
		if ($target_dom->nextSibling) {
			return $target_dom->parentNode->insertBefore($insert_dom, $target_dom->nextSibling);
		} else {
			return $target_dom->parentNode->appendChild($insert_dom);
		}
	}
	
?>