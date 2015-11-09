<?php
	// Script to allow editing non-tok elements
	// <pb/> <lb/> <deco/> <gap/>
	// similar to tokedit.php
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
	
	# $template = "empty";
		
	if ( $fileid ) { 
	
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			print "No such XML File: $xmlfolder/$fileid"; 
			exit;
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 
		$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		$result = $xml->xpath("//*[@id='$tokid']"); 
		$elm = $result[0]; # print_r($token); exit;
		$etype = $elm->getName();

		$maintext .= "<h1>Edit Element</h1>
			<h2>Structural element ($tokid): ".$etype."</h2>
			
			<form action='index.php?action=toksave' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$tokid'>
			<table>";


		$elmatts = Array ( 
			"pb" => Array ( "n" => "Page number", "facs" => "Facsimile image", "admin" => "Admin-only image"  ),
			"lb" => Array ( "n" => "True linebreak",  ),
			"deco" => Array ( "decoRef" => "decoration ID",  ),
			"gap" => Array ( "extent" => "Gap size", "reason" => "Gap reason",  ),
		);

		// Show all the defined attributes
		foreach ( $elmatts[$etype] as $key => $val ) {
			$atv = $elm[$key]; 
			if ( $key == "facs" ) {
				$maintext .= "<tr><th>$key<td>$val<td><input size=40 name=atts[$key] id='f$key' value='$atv'>
					<a href='index.php?action=images&act=list' target=select>(see list)</a>";
			} else if ( $key == "admin" ) {
				if ( $atv == "1" ) $aon = "selected";
				$maintext .= "<tr><th>$key<td>$val<td><select name=atts[$key] id='f$key'><option value=''>no</option><option value='1' $aon>yes</option></select>";
			} else $maintext .= "<tr><th>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
		};

		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; 

		$maintext .= "</table>";

		$maintext .= "<hr>
		<input type=submit value=\"Save\">
		<button onClick=\"window.open('index.php?action=edit&cid=$fileid', '_self');\">Cancel</button></form>
		<!-- <a href='index.php?action=edit&cid=$fileid'>Cancel</a> -->
		<hr><div id=mtxt>".$txtxml->asXML()."</div>
		<script language=Javascript>
			var telm = document.getElementById('$tokid');
			 var sp1 = document.createElement('span');
			 var sp1_content = document.createTextNode('[$etype]');
			 sp1.appendChild(sp1_content);
			 sp1.style['color'] = '#0000ff';
			 sp1.style['font-size'] = '11pt';
 			telm.insertBefore(sp1, telm.firstChild);
		</script>
		";
	
	} else {
		print "Oops"; exit;
	};
	
?>