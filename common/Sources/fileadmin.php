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
		<tr><td><a href='index.php?action=verticalize&act=&cid=$ttxml->fileid'>go</a><th>Verticalized view<td>Edit multiple tokens in an HTML form table
		<tr><td><a href='index.php?action=xmllayout&cid=$ttxml->fileid'>go</a><th>XML Layour editor<td>View/edit the XML layout of the file
		<tr><td><a href='index.php?action=rawedit&cid=$ttxml->fileid'>go</a><th>Raw Edit<td>Edit raw XML
		<tr><td><a href='index.php?action=files&act=mv&id=$ttxml->fileid'>go</a><th>Manage file<td>Rename or delete this file
		<tr><td><a href='index.php?action=backups&cid=$ttxml->fileid'>go</a><th>Back-ups<td>View/restore the backups of this file
		";
	if ( $bufs = glob("backups/$xmlid-*") ) { 
		$tmp = filemtime(end($bufs));
		$fdate = strftime("%d %h %Y", $tmp);
		$maintext .= "<br> &nbsp; - ".count($bufs)." backup files - most recent: <b>$fdate</b>";
	} else {
		$maintext .= "<br> &nbsp; - <i>No backups available</i>";
	};


	if ( !$ttxml->xpath("//tok") ) {
		$maintext .= "		<tr><td><a href='index.php?action=tokenize&cid=$ttxml->fileid'>go</a><th>Tokenize<td>Split XML into tokens";
	} else {
		$maintext .= "<tr><td><a href='index.php?action=renumber&cid=$ttxml->fileid'>go</a><th>Renumber<td>Renumber the nodes in this file (if needed)";
	};
	$maintext .= "
		</table>
		";

	if ( getset('scripts') ) {

		$maintext .= "<hr>
		<h2>Custom scripts</h2><table>";

		foreach ( getset('scripts', array()) as $id => $item ) {
			// See if this script is applicable
			$avai = "Applicable";
			if ( $item['recond'] && !preg_match("/{$item['recond']}/", $editxml ) ) { $avai = "<i>Not applicable: not matching {$item['recond']}</i>"; };
			if ( $item['rerest'] && preg_match("/{$item['rerest']}/", $editxml ) ) { $avai = "<i>Not applicable: matching {$item['rerest']}</i>"; };;
			if ( $item['xpcond'] && !$xml->xpath($item['xpcond']) )  { $avai = "<i>Not applicable: not matching {$item['xpcond']}</i>"; };
			if ( $item['xprest'] && $xml->xpath($item['xprest']) )  { $avai = "<i>Not applicable: matching {$item['xprest']}</i>"; };
			if ( $item['filerest'] ||  $item['filecond'] ) {
				$filerest = $item['filerest'];
				$filerest = preg_replace("/\[fn\]/", $ttxml->filename, $filerest);
				$filerest = preg_replace("/\[id\]/", $ttxml->xmlid, $filerest);
			};
			if ( $item['filecond'] && !file_exists($filerest) )  { $avai = "<i>Not applicable: not matching {$item['filecond']}</i>"; };;
			if ( $item['filerest'] && file_exists($filerest) )  { $avai = "<i>Not applicable:  matching {$item['filerest']}</i>"; };
			
			if ( $item['type'] == "php" ) {
				$url = $item['action'];
				$url = str_replace("[id]", $fileid, $url );
				$url = str_replace("[fn]", $filename, $url );
				$maintext .= "<tr><td><a href='$url'>go</a><th>$id<td>{$item['display']}<td>$avai";
			} else 
				$maintext .= "<tr><td><a href='index.php?action=runscript&script=$id&file=$fileid'>go</a><th>$id<td>{$item['display']}<td>$avai";
		};
		$maintext .= "</table>";
	
	};


	$anns = glob("Annotations/*_$ttxml->fileid");
	if ( $anns ) {
		$maintext .= "<hr>
		<h2>Annotation files</h2><table>";
		foreach ( $anns as $key => $val ) {
			if ( preg_match("/Annotations\/((.+)_(.+))/", $val, $matches ) ) {
				$file = $matches[1];
				$type = $matches[2];
				$xmlid = $matches[3];
				if ( $tmp = getset("annotations/$type") ) {
					$name = $tmp['display'];
				} else {
					$name = $type;
					$desc = "<i>Undefined annotation type</i>";
				};
				$maintext .= "<tr>";
				if ( $user['permissions'] == 'admin' )	$maintext .= "<td><a href='index.php?action=adminedit&id=$file&folder=Annotations'>raw</a>";
				$maintext .= "<td><a href='index.php?action=annotation&type=$type&cid=$xmlid'>go</a>
						<th>$name<td>$file<td>$desc";
			};
		};
		$maintext .= "</table>";
	};


	$maintext .= "<hr><h2>File Views</h2>";
	$defview = getset("defaults/fileview", "text");
	$maintext .= "<p>Default file view: $defview<p><table>";
	
	$views = getset("views");
	if ( !is_array($views) ) $views = array();
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
	if ( !$views['audiomanage'] ) {
		if ( $ttxml->xpath("//media") ) { $rest = " - default option"; } else { $rest = " - Not available, no media node"; };
		$maintext .= "<tr><td><a href='index.php?action=audiomanage&cid=$ttxml->fileid'>go</a>
			<th>Audio management<td>Audio-Aligned
			<td><i>Admin only option
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
	if ( !$views['geomap'] ) {
		if ( $ttxml->xpath("//text//*[@geo]") ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, no text-level elements with a @geo"; };
		$maintext .= "<tr><td><a href='index.php?action=geomap&act=xml&cid=$ttxml->fileid'>go</a>
			<th>Geolocation view<td>Geolocations
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['igt'] ) {
		if ( $ttxml->xpath("//text//s") ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, no sentences"; };
		$maintext .= "<tr><td><a href='index.php?action=igt&act=xml&cid=$ttxml->fileid'>go</a>
			<th>Interlinear glossed view<td>Glosses
			<td><i>Not explicitly defined $rest
			";
	};
	if ( !$views['trans'] ) {
		if ( $ttxml->xpath("//text//s[@gloss or @trans]") || getset("xmlfile/translation")  || getset("xmlfile/sattributes/s/gloss") ) { $rest = " - activate by adding to settings"; } else { $rest = " - Not available, no sentences with a @gloss or @trans"; };
		$maintext .= "<tr><td><a href='index.php?action=trans&cid=$ttxml->fileid'>go</a>
			<th>Translation view<td>Sentence translations
			<td><i>Not explicitly defined $rest
			";
	};

	$maintext .= "</table>";

	## Analyze the XML file
	$maintext .= "<hr><h2>XML Analysis</h2>";
	$maintext .= "<h3>XML Nodes</h3><table>
			<tr><td><th>Count<th>XMLFile Settings<th>CQP";
	foreach ( $ttxml->xpath("//text//*") as $node ) {
		$nn = str_replace("tei_", "", $node->getName());
		$ncnts[$nn]++;
	};
	foreach ( $ncnts as $nn => $cnt ) {
		if ( $nn == "tok" ) {
			$xmlt = "token"; $cqpt = "word";
		} else {
			$xmlt = getset("xmlfile/sattributes/$nn/display", "<i style='color: #aaaaaa;'>Not in /xmlfile</i>");
			$cqpt = getset("cqp/sattributes/$nn/key", "<i style='color: #aaaaaa;'>Not in /cqp</i>");
		};
		$maintext .= "<tr><th>$nn<td style='padding-left: 15px; padding-right: 8px; text-align: right;'>$cnt<td>$xmlt<td>$cqpt";
	};
	$maintext .= "</table>";
	if ( $ncnts['tok'] ) {
		$maintext .= "<p><h3>Token Attributes</h3><table>
			<tr><td><th>XMLFile Settings<th>Count<th>CQP";
		foreach ( $ttxml->xpath("//text//tok") as $tok ) {
			foreach ( $tok->attributes() as $ak => $av ) {
				$acnts[$ak]++;
				if ( !$vcnts[$ak] ) $vcnts[$ak] = array();
				$vcnts[$ak][$av.""]++;
			};
		};
		foreach ( $acnts as $an => $cnt ) {
			$ad = getset("xmlfile/pattributes/forms/$an") or $ad = getset("xmlfile/pattributes/tags/$an");
			if ( $ad ) { $at = $ad['display'] or $at = $an; } else $at = "<i style='color: #aaaaaa;'>Not in /xmlfile</i>";
			$cqpt = getset("cqp/pattributes/$an/key", "<i style='color: #aaaaaa;'>Not in /cqp</i>");
			$maintext .= "<tr><td>$an<th>$at<td style='padding-left: 15px; padding-right: 8px; text-align: right;'>$cnt<td>$cqpt";
		};
		$maintext .= "</table>";
	};

	$maintext .= "<hr><h2>Consistency Checks</h2>";
	$sd = 0;
	# Check that the file is valid in all necessary tools
// 	if ( $xmlwf = findapp("xmlwf") ) {
// 		$result = shell_exec("$xmlwf $filename");
// 		print "XMLWF: <pre>$result</pre>"; exit;
// 	};
	if ( $prl = findapp("perl") ) {
 		$result = shell_exec("use XML::LibXML; \$parser = XML::LibXML->new(); $index = $parser->load_xml(location => \"$filename\")");
 		if ( $result ) {
			$sd = 1;
 			$maintext .= "<h1>Perl Error</h1><p>Perl reports an error in the XML file: <pre>$result</pre>"; 
 		};
	};
	$nonum = $ttxml->xpath("//tok[not(@id)]");
	$numd = $ttxml->xpath("//tok[@id]");
	if ( $nonum ) {
		$sd = 1;
		if ( $numd ) {
			$maintext .= "<p>This text has tokens, but not all tokens have an identifier. Unnumbered token(s):"; $nn = 0; 
			foreach ( $nonum as $nn ) {
				if ( $cnt++ > 10 ) break;
				$maintext .= " <i>".$nn."</i>";
			};
		} else {
			$maintext .= "<p>This text has tokens, but has not been renumbered.";
		};
	};
	if ( !$sd ) $maintext .= "<p><i>No issues were detected</i></p>";

?>