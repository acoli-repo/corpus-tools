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

	$maintext .= "<hr><h2>File Views</h2>";
	$defview = getset("defaults/fileview", "text");
	$maintext .= "<p>Default file view: $defview<p><table>";
	
	$views = getset("views");
	foreach ( $views as $key => $val ) {
		$rest = "Available";
		if ( preg_match("/(.*?)\&/", $key, $matches) ) {
			$skey = $matches[1]; 
			if ( !$views[$skey] ) {
				$views[$skey] = 1;
			};
		};
		if ( !is_array($val) ) continue;
		if ( $val['xpcond'] && !$ttxml->xpath($val['xpcond']) ) $rest = "<i>Not avaiable - file not matching {$val['xpcond']}";
		if ( $val['xprest'] && $ttxml->xpath($val['xprest']) ) $rest = "<i>Not avaiable - file matching {$val['xprest']}";
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
		if ( $ttxml->xpath("//tok[@head]") && $ttxml->xpath("//s") ) { $rest = " - active by adding to settings"; } else { $rest = " - Not available, no heads and/or sentences"; };
		$maintext .= "<tr><td><a href='index.php?action=deptree&cid=$ttxml->fileid'>go</a>
			<th>Dependency view
			<td><i>Not used $rest
			";
	};
	if ( !$views['block'] ) {
		if ( $ttxml->xpath("//s") ) { $rest = " - active by adding to settings"; } else { $rest = " - Not available, no sentences"; };
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
		if ( $ttxml->xpath("//note[@n=\"orgfile\"]") || $ttxml->xpath("//orgfile") ) { $rest = " - Admin only option"; } else { $rest = " - Not available, no orgfile defined"; };
		$maintext .= "<tr><td><a href='index.php?action=orgfile&cid=$ttxml->fileid'>go</a>
			<th>Original file viewer
			<td><i>$rest
			";
	};
	if ( !$views['stats'] ) {
		if ( file_exists('cqp/word.corpus') ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, corpus not yet indexed"; };
		$maintext .= "<tr><td><a href='index.php?action=stats&cid=$ttxml->fileid'>go</a>
			<th>Statistics
			<td><i>Not used $rest
			";
		$maintext .= "<tr><td><a href='index.php?action=wordcloud&cid=$ttxml->fileid'>go</a>
			<th>Word cloud
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
		if ( $ttxml->xpath("//media") ) { $rest = " - default option"; } else { $rest = " - Not available, no media node"; };
		$maintext .= "<tr><td><a href='index.php?action=wavesurfer&cid=$ttxml->fileid'>go</a>
			<th>Wavesurfer view<td>Audio-Aligned
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['lineview'] ) {
		if ( $ttxml->xpath("//lb[@bbox]") ) { $rest = " - default option"; } else { $rest = " - Not available, no lines with @bbox (Manuscripts only)"; };
		$maintext .= "<tr><td><a href='index.php?action=lineview&cid=$ttxml->fileid'>go</a>
			<th>Manuscript line view<td>Facsimile-Aligned
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['facsview'] ) {
		if ( $ttxml->xpath("//lb[@bbox]") ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, no tok with @bbox"; };
		$maintext .= "<tr><td><a href='index.php?action=facsview&cid=$ttxml->fileid'>go</a>
			<th>Facsimile view<td>Facsimile-Aligned
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['tualign'] ) {
		if ( $ttxml->xpath("//*[@tuid]") ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, no elements with a @tuid"; };
		$maintext .= "<tr><td><a href='index.php?action=tualign&cid=$ttxml->fileid'>go</a>
			<th>Translation Unit view<td>Translation-Aligned
			<td><i>Not explicitly defined $rest
			";
	};

	$maintext .= "</table>";

	## Analyze the XML file
	$maintext .= "<hr><h2>XML Analysis</h2>";
	$maintext .= "<h3>XML Nodes</h3><table>
			<tr><td><th>Count<th>XMLFile<th>CQP";
	foreach ( $ttxml->xpath("//text//*") as $node ) {
		$nn = str_replace("tei_", "", $node->getName());
		$ncnts[$nn]++;
	};
	foreach ( $ncnts as $nn => $cnt ) {
		if ( $nn == "tok" ) {
			$xmlt = "token"; $cqpt = "word";
		} else {
			$xmlt = getset("xmlfile/sattributes/$nn/display", "<i>Not in XML Settings</i>");
			$cqpt = getset("cqp/sattributes/$nn/key", "<i>Not in CQP</i>");
		};
		$maintext .= "<tr><th>$nn<td>$cnt<td>$xmlt<td>$cqpt";
	};
	$maintext .= "</table>";
	if ( $ncnts['tok'] ) {
		$maintext .= "<h3>Token Attributes</h3><table>
			<tr><td><td><th>Count<th>CQP";
		foreach ( $ttxml->xpath("//text//tok") as $tok ) {
			foreach ( $tok->attributes() as $ak => $av ) {
				$acnts[$ak]++;
				$vcnts[$ak][$av]++;
			};
		};
		foreach ( $acnts as $an => $cnt ) {
			$at = getset("xmlfile/pattributes/forms/$an/display") or $at = getset("xmlfile/pattributes/tags/$an/display", "<i>Not in XML Settings</i>");
			$cqpt = getset("cqp/pattributes/$an/key", "<i>Not in CQP</i>");
			$maintext .= "<tr><td>$an<th>$at<td>$cnt<td>$cqpt";
		};
		$maintext .= "</table>";
	};

?>