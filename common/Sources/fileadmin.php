<?php

	check_login();
	
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;

	$maintext .= "<h2>File Admin</h2><h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();

	$maintext .= "<h2>Edit modules</h2>
		<table>
		<tr><td><a href='index.php?action=header&act=edit&cid=$ttxml->fileid'>go</a><th>Header edit<td>Edit metadata in an HTML form table
		<tr><td><a href='index.php?action=block&cid=$ttxml->fileid'>go</a><th>Verticalized view<td>Edit multiple tokens in an HTML form table
		<tr><td><a href='index.php?action=xmllayout&cid=$ttxml->fileid'>go</a><th>XML Layour editor<td>View/edit the XML layout of the file
		<tr><td><a href='index.php?action=backups&cid=$ttxml->fileid'>go</a><th>Back-ups<td>View/restore the backups of this file
		<tr><td><a href='index.php?action=rawedit&cid=$ttxml->fileid'>go</a><th>Raw Edit<td>Edit raw XML
		";
	if ( !$ttxml->xpath("//tok") ) {
		$maintext .= "		<tr><td><a href='index.php?action=tokenize&cid=$ttxml->fileid'>go</a><th>Tokenize<td>Split XML into tokens";
	} else {
		$maintext .= "<tr><td><a href='index.php?action=renumber&cid=$ttxml->fileid'>go</a><th>Renumber<td>Renumber the nodes in this file (if needed)";
	};
	$maintext .= "
		</table>
		";

	$maintext .= "<h2>File Views</h2>";
	$defview = getset("defaults/fileview", "text");
	$maintext .= "<p>Default file view: $defview<p><table>";
	
	$views = getset("views");
	foreach ( $views as $key => $val ) {
		$rest = "Available";
		if ( is_object($val) )
		$maintext .= "<tr><td><a href='index.php?action=$key&cid=$ttxml->fileid'>go</th>
			<th>{$val['display']}
			<td>$rest
			";
	};
	if ( !$views['text'] ) {
		$maintext .= "<tr><td><a href='index.php?action=text&cid=$ttxml->fileid'>go</th>
			<th>Text view
			<td><i>Not explicitly defined - default option
			";
	};
	if ( !$views['deptree'] ) {
		if ( $ttxml->xpath("//tok[@head]") && $ttxml->xpath("//s") ) { $rest = " - default option"; } else { $rest = " - Not available, no heads and/or sentences"; };
		$maintext .= "<tr><td><a href='index.php?action=deptree&cid=$ttxml->fileid'>go</a>
			<th>Dependency view
			<td><i>Not used $rest
			";
	};
	if ( !$views['block'] ) {
		if ( $ttxml->xpath("//s") ) { $rest = " - default option"; } else { $rest = " - Not available, no sentences"; };
		$maintext .= "<tr><td><a href='index.php?action=block&cid=$ttxml->fileid'>go</a>
			<th>Sentence view
			<td><i>Not used $rest
			";
	};
	if ( !$views['ner'] ) {
		if ( $ttxml->xpath("//name") || $ttxml->xpath("//term") || $ttxml->xpath("//personName") ) { $rest = " - default option"; } else { $rest = " - Not available, no names"; };
		$maintext .= "<tr><td><a href='index.php?action=ner&cid=$ttxml->fileid'>go</a>
			<th>Named Entity view
			<td><i>Not used $rest
			";
	};
	if ( !$views['orgfile'] ) {
		if ( $ttxml->xpath("//note[@n=\"orgfile\"]") || $ttxml->xpath("//orgfile") ) { $rest = " - default option"; } else { $rest = " - Not available, no orgfile define"; };
		$maintext .= "<tr><td><a href='index.php?action=orgfile&cid=$ttxml->fileid'>go</a>
			<th>Original file viewer
			<td><i>Not used $rest
			";
	};
	$maintext .= "<tr><td><a href='index.php?action=header&act=rawview&cid=$ttxml->fileid'>go</a>
		<th>teiHeader view
		<td><i>Admin-only option
		";
	$maintext .= "</table>";
	
	$maintext .= "<h3>Domain-Specific File Views</h3>";
	
	$maintext .= "<table>";
	if ( !$views['wavesurfer'] ) {
		if ( $ttxml->xpath("//") ) { $rest = " - default option"; } else { $rest = " - Not available, no media node"; };
		$maintext .= "<tr><td><a href='index.php?action=wavesurfer&cid=$ttxml->fileid'>go</a>
			<th>Wavesurfer view<td>Audio-Aligned
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['lineview'] ) {
		if ( $ttxml->xpath("////lb[@bbox]") ) { $rest = " - default option"; } else { $rest = " - Not available, no lines with @bbox (Manuscripts only)"; };
		$maintext .= "<tr><td><a href='index.php?action=lineview&cid=$ttxml->fileid'>go</a>
			<th>Manuscript line view<td>Facsimile-Aligned
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['facsview'] ) {
		if ( $ttxml->xpath("////lb[@bbox]") ) { $rest = " - default option"; } else { $rest = " - Not available, no tok with @bbox"; };
		$maintext .= "<tr><td><a href='index.php?action=facsview&cid=$ttxml->fileid'>go</a>
			<th>Facsimile view<td>Facsimile-Aligned
			<td><i>Not explicitly defined $rest
			";
	};

	$maintext .= "</table>";


?>