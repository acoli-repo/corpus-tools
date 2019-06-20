<?php

	require_once("$ttroot/common/Sources/ttxml.php");

	$idlist = explode(",", $_GET['id']); 
	
	foreach ( $idlist as $cid ) {
		$versions[$cid] = new TTXML($cid); 
	};

	$maintext .= "<h1>Aligned Texts</h1>";

	$maintext .= "<script language=Javascript src=\"$jsurl/appalign.js\"></script>";
	
	$maintext .= "<table style='width: 100%; cellpadding: 5px; table-layout: fixed; '><tr>";
	foreach ( $idlist as $cid ) {
		$maintext .= "<td valign=top><h2>".$versions[$cid]->title()."</h2>";
	};
	$maintext .= "<tr>";
	foreach ( $idlist as $cid ) {
		$maintext .= "<td valign=top><div id=mtxt style=\"overflow-x: hidden; overflow-y: scroll; max-height: 600px; height: 600px;\">".$versions[$cid]->asXML()."</div></td>";
	};

?>