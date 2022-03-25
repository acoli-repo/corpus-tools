<?php
	// Script to edit an XML file by raw XML
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fileid = $_POST['id'] or $fileid = $_GET['id'] or $fileid = $_GET['cid'];
	if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	
	if ( !$fileid ) { 
		print "No XML file selected."; 
		exit;
	};

	if ( !file_exists("$xmlfolder/$fileid") ) { 
		print "No such XML File: $xmlfolder/$fileid"; 
		exit;
	}; # $template = "iframe";
	
	$file = file_get_contents("$xmlfolder/$fileid"); 
	
	// Remove all <tok> elements
	if ( $_GET['remtok'] ) {
		$file = preg_replace ( "/<\/?d?tok[^>]*>/", "", $file );
		$file = preg_replace ( "/<ee\/>/", "", $file );
		$file = preg_replace ( "/ id=\".*?\"/", "", $file );
	};
	
	# We need to turn of the xmlns here
	# TODO: This should also turn off the internal things 
	$file = preg_replace ( "/ xmlns=/", " xmlnsoff=", $file );	
	
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };

	if ( $settings['xmlfile']['title'] == "[id]" ) {
		$title = $fileid;
	} else {			
		$result = $xml->xpath("//title"); 
		$title = $result[0];
	};
	
	$maintext .= "<h2>$fileid</h2><h1>$title </h1>";

	# Show optional additional headers
	if ( $shortheader ) $maintext .= "<table>";
	if ( is_array($headershow) ) 
	foreach ( $headershow as $hq => $hn ) {
		$result = $xml->xpath($hq); 
		$hv = $result[0];
		if ( $hv ) {
			$htxt = $hv->asXML();
			if ( $shortheader ) 
				$maintext .= "<tr><th style='padding: 5px;'>$hn</th><td>$htxt</td></tr>";
			else 
				$maintext .= "<h3>$hn</h3><p>$htxt</p>";
		};
	}; 
	if ( $shortheader ) $maintext .= "</table>";
	if ( $headershow ) $maintext .= "<hr>";

	$result = $xml->xpath("//tok"); 
	$tokcheck = $result[0]; 
	
	if ( !$mtxtelement ) $mtxtelement = "//text";
			
	$sep = "";
	if ( $_GET['full'] ) {
		$editxml = $file;
		$type = "&type=full";
		$switch = "<a href='index.php?action=rawedit&cid=$fileid'>switch to only text element</a>";
	} else {
		$result = $xml->xpath($mtxtelement); 
		$txtxml = $result[0]; $sep = "";
		if ( !$txtxml ) {
			$mtxtelement = "//text";
			$result = $xml->xpath($mtxtelement); 
			$txtxml = $result[0]; 
		};
		if ( $txtxml ) {
			$editxml = $txtxml->asXML();
			$switch = "<a href='index.php?action=rawedit&cid=$fileid&full=1'>switch to full XML including header</a>";
			$sep = "&bull;";
		} else $editxml = $file; # Default to full XML if the mtxtelm is missing
	}; $switch .= " $sep <a href='index.php?action=file&cid=$fileid&full=1'>back to view mode</a>";

	# Toggle softwrap
	# $switch .= " <a style='text-: right;' onclick='softwrap();'>softwrap</a><script>editor.setOption(\"wrap\", true)</script>";

	$editxml = preg_replace( "/<text([^>]*)\/>/", "<text\\1>\n</text>", $editxml );

	if ( $_GET['view'] != "wysiwyg" ) $editxml = htmlentities($editxml, ENT_COMPAT, 'UTF-8');
	
	if ( file_exists("Resources/teitags.xml") )  $tmp = "Resources/teitags.xml";
	else if ( file_exists("$sharedfolder/Resources/teitags.xml") )  $tmp = "$sharedfolder/Resources/teitags.xml";
	else $tmp = "$ttroot/common/Resources/teitags.xml";
	$teilist = array2json(xmlflatten(simplexml_load_string(file_get_contents($tmp))));
	
	$acelturl = str_replace("ace.js", "ext-language_tools.js", $aceurl);
	$maintext .= "
		<div id=\"editor\" style='width: 100%; height: 400px;'>".$editxml."</div>
	
		<form action=\"index.php?action=rawsave&cid=$fileid$type\" id=frm name=frm method=post>
		<textarea style='display:none' name=rawxml></textarea>
		<p><input type=button value=Save onClick=\"return runsubmit();\"> $switch
		</form>
		
		<script src=\"$aceurl\" type=\"text/javascript\" charset=\"utf-8\"></script>
		<script src=\"$acelturl\" type=\"text/javascript\" charset=\"utf-8\"></script>
		<script>
			var editor = ace.edit(\"editor\");
			editor.setTheme(\"ace/theme/chrome\");
			editor.getSession().setMode(\"ace/mode/xml\");

			var teiList = $teilist;
			var langTools = ace.require(\"ace/ext/language_tools\");

			var myCompleter = {
				getCompletions: function(editor, session, pos, prefix, callback) {
					var optList = {};
					if ( session.getTokenAt(pos.row,pos.column).type == 'meta.tag.tag-name.xml' || session.getTokenAt(pos.row,pos.column).type == 'text.tag-open.xml' ) {
						optList = teiList;
					} else if ( session.getTokenAt(pos.row,pos.column).type == 'entity.other.attribute-name.xml' || session.getTokenAt(pos.row,pos.column).type == 'text.tag-whitespace.xml' ) {
						// Get the node this attribute belongs to
						var prnt;
						for ( var i=pos.column; i>-1; i--  ) {
							if ( session.getTokenAt(pos.row,i).type == 'meta.tag.tag-name.xml' ) {
								prnt = session.getTokenAt(pos.row,i).value;
								break;
							};
						};
						if ( teiList[prnt] )  optList  = teiList[prnt]['atts'];
					} else if ( session.getTokenAt(pos.row,pos.column).type == 'string.attribute-value.xml' ) {
					};
					if ( optList !== undefined && Object.keys(optList).length > 0 ) {
						callback(
							null,
							Object.keys(optList).filter(entry=>{
								return entry[0].startsWith(prefix);
							}).map(entry=>{
								return {
									value: entry, meta: optList[entry]['display']
								};
							})
						);
					}; // else { console.log(session.getTokenAt(pos.row,pos.column)); };
				}
			}
			langTools.setCompleters([myCompleter]);
			editor.setOptions({
				enableBasicAutocompletion: true,
				enableLiveAutocompletion: true,
				enableSnippets: false
			});
			
			function runsubmit ( ) {
				var rawxml = editor.getSession().getValue();
				var oParser = new DOMParser();
				var oDOM = oParser.parseFromString(rawxml, 'text/xml');
				if ( oDOM.documentElement.nodeName == 'parsererror' ) {
					alert('Invalid XML - please revise before saving.'); 
					return -1; 							
				} else {
					document.frm.rawxml.value = rawxml;
					document.frm.submit();
				};						
			};
		</script>
	";

	// Add a session logout tester
	$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";

?>