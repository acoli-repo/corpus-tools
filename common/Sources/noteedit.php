<?php
	// Script to edit <note> elements in an XML file
	// (c) Maarten Janssen, 2015

	check_login();

	$fileid = $_POST['cid'] or $fileid = $_GET['cid'];
	$tokid = $_POST['tid'] or $tokid = $_GET['tid'];
		
	if ( $fileid ) { 
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			print "No such XML File: $xmlfolder/$fileid"; 
			exit;
		};
		
		$file = file_get_contents("$xmlfolder/$fileid"); 

		$xml = simplexml_load_string($file);

		$result = $xml->xpath("//note[@id='$tokid']"); 
		$token = $result[0]; # print_r($token); exit;
		if ( !$token ) { print "Note not found: $tokid<hr>"; print $file; exit; };

	if ( $_POST['innerxml'] ) {
	

		$file = preg_replace("/(<note[^>\/]+id=\"$tokid\"[^>\/]*>).*?(<\/note>)/", "$1{$_POST['innerxml']}$2", $file);
		$file = preg_replace("/(<note[^>]+id=\"$tokid\"[^>]*)\/>/", "$1>{$_POST['innerxml']}</note>", $file);

		saveMyXML($file, $fileid);
		print "<p>Note saved. Reloading.";
		header("location:index.php?action=edit&id=$fileid&tid=$tokid$slnk");
		
	} else {


		$maintext .= "<h1>Edit Note</h1>
			<h2>Note text ($tokid):</h2>

			<form action='index.php?action=$action' method=post name=tagform id=tagform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tid value='$tokid'>
			";


		// show the innerHTML
		$xmlword = $token->asXML(); $xmlword = preg_replace("/<\/?note[^>]*>/", "", $xmlword); 
		$maintext .= "
			<textarea style='width: 100%; height: 200px;' name=innerxml id='innerxml'>$xmlword</textarea>
			";


		if ( $settings['xmlfile']['paged'] ) {
		
			$tokpos = strpos($file, "id=\"$tokid\"");
			$pbef = rstrpos($file, "<pb", $tokpos) or $pbef = strpos($file, "<text");
			$paft = strpos($file, "<pb", $tokpos) or $pbef = strpos($file, "</text");
			$span = $paft-$pbef;
			$editxml = substr($file, $pbef, $span);
			
		} else {
			$result = $xml->xpath($mtxtelement); 
			$txtxml = $result[0]; 
			$editxml = $txtxml->asXML();
		};

		$maintext .= "<hr><p>
		<input type=submit value=\"Save\">
		
		<hr>
		<div id=mtxt>".$editxml."</div>
		<button onClick=\"window.open('index.php?action=edit&cid=$fileid', '_self');\">Cancel</button>
		<a href='index.php?action=edit&cid=$fileid'>Cancel</a>
		<script language=Javascript>
			document.getElementById('fnform').focus();
			highlight('$tokid',  '#ffee88');
		</script>
		
		</form>
		";
		
	};
	} else {
		print "Oops"; exit;
	};
	
?>