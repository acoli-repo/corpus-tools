<?php
	// Script to edit sentences <s> in an XML file
	// only partially finished - avoid using
	// (c) Maarten Janssen, 2015

	check_login();

	$sentatts = $settings['xmlfile']['sattributes'];

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$sentid = $_POST['sid'] or $sentid = $_GET['sid'];
	$sentname = $sentatts[$stf]['display'] or $sentname = "Sentence";
	$stype = $_GET['sentence'] or $stype = $_GET['elm'] or $stype = "s";

	if ( $fileid ) { 
	
		require("$ttroot/common/Sources/ttxml.php");
		
		$ttxml = new TTXML($fileid);
		$xml = $ttxml->xml;

		if ( $act == "save" ) {
		
			foreach ( $_POST['matts'] as $sentid => $val ) {
				print "<p>$sentid: ";
				$sent = current($xml->xpath("//*[@id='$sentid']"));
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
			print "<script>top.location='index.php?action=block&cid=$ttxml->fileid&elm=$stype'</script>";
			exit;
		
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
				<p>Element type: $sentname $drest
			
				<p>
				<form action='index.php?action=$action&act=save' method=post name=tagform id=tagform>
				<input type=hidden name=cid value='$fileid'>
				<table id=rollovertable><tr><th>ID<th>$sentname
				";
						
			# Show the title bar
			foreach ( $doatts as $key2 => $val2 ) {
				if ( !is_array($val2) ) continue;
				if ( $doatt ) $maintext .= "<th>ID</th><th>{$val2['display']}</th>";
				else  $maintext .= "<th><a href='".modurl("doatt", $key2)."'>{$val2['display']}</a></th>";
			};
			$results = $xml->xpath($sxp); 

			$start = $_GET['start'] or $start = 0;
			$pp = $_GET['perpage'] or $pp = 100;
			$tot = count($results); $end = $start + $pp;
			if ( $tot > $pp ) {
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
			$maintext .= $xrest;
			
			foreach ( $slice as $sent ) {
				$sid = $sent['id'];
				$maintext .= "<tr><td><a href='index.php?action=file&cid=$ttxml->fileid&jmp=$sid'>$sid<td id=mtxt>".makexml($sent);
				foreach ( $doatts as $key2 => $val2 ) {
					if ( !is_array($val2) ) continue;
					$atv = $sent[$key2]; 
					$width = $val2['size'] or $width = 35;
					$maintext .= "<td><input size='$width' name=matts[$sid][$key2] value='$atv'>";
				};
			};
			$maintext .= "</table><p><input type='submit' value='Save'> <a href='index.php?action=file&cid=$fileid'>cancel</a>
				</form>";
			
		} else {
		
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

			$maintext .= "</table>
				<p><input type=submit value=Save>
				</form>
				<hr><p>Click <a href='index.php?action=$action&cid=$ttxml->fileid&sid=multi'>here</a> to edit multiple sentences";
		};
		
	} else {
		print "Oops"; exit;
	};
	
?>