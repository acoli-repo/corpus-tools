<?php

	// Word at a glance
	// (c) Maarten Janssen, 2019
	
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;

	$tid = $_GET['tid'];

	$maintext .= "<h1>{%Word Info}</h1><table>";
	
	$node = current($ttxml->xml->xpath("//*[@id=\"$tid\"]"));

	$cqlbase = $settings['cqp']['cqlbase'] or $cqlbase = "index.php?action=cqp&cql=";
	
	$maintext .= "<tr><th>{%Attributes}<td><table>";
	$tags = array_merge($settings['xmlfile']['pattributes']['forms'], $settings['xmlfile']['pattributes']['tags']);
	foreach ( $tags as $key => $item ) {
		if ( $item['admin'] && !$username ) continue;
		$form = forminherit($node, $key);
		$formtxt = $form;
		if ( $item['link'] ) {
			$formtxt = "<a href='".str_replace('{$val}', $form, $item['link'])."'>$form</a>";
		} else if ( $item['type'] == "pos" && file_exists("Resources/tagset.xml") ) {
			$formtxt = "<a href='index.php?action=tagset&act=analyze&tag=$form'>$form</a>";
		}
		if ( $node[$key.''] || $key == "form" ) $maintext .= "<tr><th>".pattname($key)."<td>$formtxt<td style='padding-left: 20px;'><a href='{$cqlbase}[$key=\"$form\"]'>{%search similar}</i></a>";
	};
	$maintext .= "</table>";
	
	$cntx = $ttxml->context($tid);
	$cntx = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $cntx );

	if ( $cntx ) $maintext .= "<tr><th>{%Context}<td id=mtxt>".$cntx;

	$maintext .= "
		</td></tr></table>
		<script  type=\"text/javascript\" src=\"$jsurl/tokedit.js\"></script>
		<script  type=\"text/javascript\">
			console.log('ieps');
			highlight('{$node['id']}');
		</script>
		";

?>