<?php

	// Word at a glance
	// (c) Maarten Janssen, 2019
	
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;

	$tid = $_GET['tid'];

	$maintext .= "<h1>{%Word Info}</h1><table>";
	
	$node = current($ttxml->xml->xpath("//*[@id=\"$tid\"]"));
	
	$maintext .= "<tr><th>{%Attributes}<td><table>";
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		$form = forminherit($node, $key);
		if ( $node[$key.''] || $key == "form" ) $maintext .= "<tr><th>".pattname($key)."<td>$form<td style='padding-left: 20px;'><a href='index.php?action=cqp&cql=[$key=\"$form\"]'>{%search similar}</i></a>";
	};
	$maintext .= "</table>";
	
	$cntx = $ttxml->context($tid);

	if ( $cntx ) $maintext .= "<tr><th>{%Context}<td id=mtxt>".$cntx->asXML();

	$maintext .= "</table>";

?>