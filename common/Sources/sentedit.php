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
		
			$maintext .= "<h1>Multi-element edit</h1>
				<p>Element type: $sentname
			
				<p>
				<form action='index.php?action=$action&act=save' method=post name=tagform id=tagform>
				<input type=hidden name=cid value='$fileid'>
				<table id=rollovertable><tr><th>$sentname
				";
				
			$results = $xml->xpath("//$stype"); 
			
			if ( $_GET['doatt'] ) {
				$doatt = $_GET['doatt'];
				$doatts = array ( $doatt => $sentatts[$stype][$doatt] );
			} else $doatts = $sentatts[$stype];
			foreach ( $doatts as $key2 => $val2 ) {
				if ( !is_array($val2) ) continue;
				$maintext .= "<th>{$val2['display']}</th>";
			};

			$start = $_GET['start'] or $start = 0;
			$pp = $_GET['perpage'] or $pp = 100;
			$tot = count($results); $end = $start + $pp;
			if ( $tot > $pp ) {
				$slice = array_slice($results, $start, $pp);
				$maintext .= "<p>showing ".($start+1)." - $end of $tot";
			} else $slice = $results;
			
			foreach ( $slice as $sent ) {
				$maintext .= "<tr><td id=mtxt>".makexml($sent);
				$sid = $sent['id'];
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
				</form>";
		};
		
	} else {
		print "Oops"; exit;
	};
	
?>