<?php

	# check_login();
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();

 	$colorlist = array ( '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999' );
	$empties = array ('pb', 'lb', 'cb', 'milestone');

	if ( file_exists("Resources/teitags.xml") )  $tmp = "Resources/teitags.xml";
	else if ( file_exists("$sharedfolder/Resources/teitags.xml") )  $tmp = "$sharedfolder/Resources/teitags.xml";
	else $tmp = "$ttroot/common/Resources/teitags.xml";
	$tagxml = simplexml_load_string(file_get_contents($tmp));
	$teilist = xmlflatten($tagxml);
	
	if ( $act == "addann" ) {

		check_login(); 		
		if ( !is_writable("xmlfiles/".$ttxml->fileid) ) fatal("Not writable: $ttxml->fileid");
	
		$nertype = $_POST['type']; if ( !$nertype ) fatal("No nodename indicated");
		print "<p>Adding $nertype around ".$_POST['toklist'];

		$newner = addparentnode($ttxml->xml, $_POST['toklist'], $nertype);
		
		saveMyXML($ttxml->xml->asXML(), $ttxml->fileid	);
		$nexturl = "index.php?action=$action&cid=$ttxml->fileid";
		print "<hr><p>Your annotation has been inserted - reloading to <a href='$nexturl'>the edit page</a>";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;
	
	} else if ( $act == "index" || ( !$_GET['elmid'] && $settings['defaults']['largexml'] )  ) {
	
		$maintext .= "<h2>XML Layout Index</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();		
		
		$maintext .= "<p>Select a part of the XML to edit<hr>";
		
		if ( $_GET['selid'] ) { 
			$id= $_GET['selid'];
			$root = current($ttxml->xml->xpath("//$mtxtelement//*[@id=\"$id\"]"));
			$focusxml = $root;
			$nodepath = $focusxml->getName()."<span style='color: #aaaaaa'>[@id=\"".$focusxml['id']."\"]</span>";
			while ( $focusxml ) {
				$focusxml = current($focusxml->xpath('parent::*'));
				$focusname = str_replace("tei_", "", $focusxml->getName());
				if ( $focusxml['id'] ) $focusname .= "[@id=<a href='index.php?action=$action&act=index&id=$ttxml->fileid&selid={$focusxml['id']}'>{$focusxml['id']}</a>]";
				else if ( $focusname == "text" ) $focusname = "<a href='index.php?action=$action&act=index&id=$ttxml->fileid'>$focusname</a>";
				$nodepath = "$focusname > $nodepath";
				if ( $focusxml->getName() == "text" ) { break; };
			};
		} else {
			$root = current($ttxml->xml->xpath("//$mtxtelement"));
			$nodepath = $root->getName();
		};
		$maintext .= "<p>$nodepath";
		foreach ( $root->children() as $child ) {
			$maintext .= "<p> - ".$child->getName();
			if ( $child['id'] ) $maintext .= "[@id=<a href='index.php?action=$action&act=index&id=$ttxml->fileid&selid={$child['id']}'>".$child['id']."</a>] - <a href='index.php?action=$action&id=$ttxml->fileid&elmid={$child['id']}'>select</a>";
		};
	
	} else if ( $act == "taglist" ) {
	
		$maintext .= "<h1>TEI Tag List</h1>
			<p>Below is the list of tags defined for this project
			<table><pre>";
		foreach ( $teilist as $key => $tag ) {
			$maintext .= "<tr><th>$key<td>{$tag['display']}</tr>";
		};
		$maintext .= "</table>";
				
	} else {

		if ( $username ) {
			$mode = "Editor";
			$editmode = "onmouseup='makespan(event);'";
			if ( !$ttxml->xml->xpath("//tok") ) fatal("text not tokenized yet");
		} else {
			$mode = "Viewer";
		};
	
		$maintext .= "<h2>XML Layout $mode</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();		
		
		if ( $_GET['elmid'] ) {
			$id = $_GET['elmid'];
			$editxml = current($ttxml->xml->xpath("//*[@id=\"$id\"]"));
			if ( $editxml ) {
				$focusxml = $editxml;
				$nodepath = $focusxml->getName()."<span style='color: #aaaaaa'>[@id=\"".$focusxml['id']."\"]</span>";
				while ( $focusxml ) {
					$focusxml = current($focusxml->xpath('parent::*'));
					$focusname = str_replace("tei_", "", $focusxml->getName());
					if ( $focusxml['id'] ) $focusname = "<a href='index.php?action=$action&id=$ttxml->fileid&elmid={$focusxml['id']}'>$focusname</a>";
					$nodepath = "$focusname > $nodepath";
					if ( $focusxml->getName() == "text" ) { break; };
				};
				$maintext .= "<p>Editing: $nodepath</p><hr>";
			};
		};
		if ( !$editxml ) { $editxml = current($ttxml->xml->xpath("//$mtxtelement")); };
		
		if ( !$editxml ) fatal("Failed to get edit element {$_GET['elmid']}");
		
		$protects = array ( "head", "opener", "address", "div", "option", "image", "a" );
		$edittxt = $editxml->asXML();
		$maintext .= "<div id=prv $editmode>$edittxt</div>";

		foreach ( $editxml->xpath(".//*") as $node ) {
			$nn = $node->getName()."";
			$taglist[$nn] = 1;
		};
	
		foreach ( $teilist as $key => $tag ) {
			$optlist .= "<option value='$key'>$key: {$tag['display']}</option>";
			if ( $key != "p" ) $unstyle .= "\n#prv $key { all: unset; }";
		};
		$maintext .= "<div id='addner' style='position: absolute; right: 10px; top: 20px; width: 500px; display: none; border: 1px solid #aaaaaa;'>
		<form action='index.php?action=$action&act=addann&cid=$ttxml->fileid' method=post>
		<input id='toklist' name='toklist' type=hidden>
		<table width='100%' style=' background-color: white;'>
			<tr><th colspan=2>Add Annotation
			<tr><th>Span<td id='nerspan'>
			<tr><th>Tag<td><select name=type>$optlist</select>
			<tr><td colspan=2><input type=submit value='Create'>
			<a onClick=\"document.getElementById('addner').style.display='none';\">Cancel</a>
		</table>
		</form></div>
		<style>
			#prv h1 { all: unset; }
			#prv h2 { all: unset; }
			#prv a { all: unset; }
			#prv b { all: unset; }
			#prv s { all: unset; }
			$unstyle
		</style>";

	
		$maintext .= "<hr><div id='xpath' style='height: 20px;'></div><hr><p>Show tags:</p>";
		foreach ( $taglist as $key => $val ) {
			$color = array_shift($colorlist);
			$keyname = str_replace("tei_", "", $key);
			$maintext .= " <span id=\"span$key\"><a   style='color: $color;' onClick=\"toggle('$key')\">&lt;$keyname&gt;</a></span> ";
			if ( in_array($key, $empties) || $teilist[$key]['empty'] ) $maintext .= "<style id=\"class$key\" media=\"max-width: 1px;\">
						#prv $key { color: $color; }
						#prv $key::before { content: '<$keyname/>'; color: #bbbbbb; font-size: smaller; }
					</style>
				";
			else $maintext .= "<style id=\"class$key\" media=\"max-width: 1px;\">
						#prv $key { color: $color; }
						#prv $key::before { content: '<$keyname>'; color: #bbbbbb; font-size: smaller; }
						#prv $key::after { content: '</$keyname>'; color: #bbbbbb; font-size: smaller; }
					</style>
				";
		};
	
		$maintext .= "
			<style>span[on] { text-decoration: underline; text-decoration-color: red; text-decoration-thickness: 2px; }</style>
			<script>
			document.onmouseover = mouseEvent; 

			var seq = []; var selstring = '';
			var prv = document.getElementById('prv');
			var xp = document.getElementById('xpath');
			function toggle(elm) {
				var sp = document.getElementById('span'+elm);
				var cls = document.getElementById('class'+elm);
				if ( sp.getAttribute('on') ) {
					sp.removeAttribute('on');
					cls.setAttribute('media', 'max-width: 1px');
				} else {
					sp.setAttribute('on', '1');
					cls.removeAttribute('media');
				}; 
			};
		
			function mouseEvent(evt) { 
				element = evt.toElement; 
				if ( !element ) { element = evt.target; };
	
				nn = element.nodeName.toLowerCase().replace('tei_', '');
				var xpath = nn;
				if ( !prv.contains(element) ) {
					xp.innerHTML = '';
					return;
				};
				var focusnode = element;
				while ( focusnode ) {
					focusnode = focusnode.parentNode;
					nn = focusnode.nodeName.toLowerCase().replace('tei_', '');
					if ( focusnode.getAttribute('id') == 'prv' ) { break; };
					xpath = nn + ' > ' + xpath;
				}; 
			
				xp.innerHTML = xpath;
	
			};
		
			function makespan(event) { 
				var toks = document.getElementsByTagName('tok');

				if (window.getSelection) {
					sel = window.getSelection();
				} else if (document.selection && document.selection.type != 'Control') {
					sel = document.selection.createRange();
				}
	
				var node1 = sel.anchorNode; 
				if ( !node1 ) { 
					for ( var a = 0; a<seq.length; a++ ) {
						var tok = seq[a];
						tok.style['background-color'] = null;
						tok.style.backgroundColor= null; 
					};
					seq = []; selstring = '';
					return -1;
				};
				var noden = sel.focusNode;
				var order = 0;
				if ( node1.compareDocumentPosition(noden) == 2 ) {
					// switch if selection is inverse
					var tmp = node1;
					node1 = noden;
					noden = tmp;
				};

				while ( node1 && node1.nodeName != 'TOK' && node1.nodeName != 'tok'  ) { node1 = node1.parentNode; };
				while ( noden && noden.nodeName != 'TOK' && noden.nodeName != 'tok'  ) { noden = noden.parentNode; };

				// Reset the selection
				for ( var a = 0; a<seq.length; a++ ) {
					var tok = seq[a];
					tok.style['background-color'] = null;
					tok.style.backgroundColor= null; 
				};
				seq = []; 

				var nodei = node1;

				seq.push(node1); 
				while ( nodei != noden && nodei ) {
					nodei = nodei.nextSibling;
					if ( nodei && ( nodei.nodeName == 'TOK' || nodei.nodeName == 'tok' )  ) { 
						seq.push(nodei);			
					};
				};
				window.getSelection().removeAllRanges();
	
				color = '#88ffff';  selstring = '';  idlist = ''; 
				for ( var a = 0; a<seq.length; a++ ) {
					var tok = seq[a];
					if ( tok == null ) continue;
					tok.style['background-color'] = color;
					tok.style.backgroundColor= color; 
					selstring += tok.innerHTML + ' ';
					idlist += tok.getAttribute('id') + ';';
				};
	
				document.getElementById('toklist').value = idlist;
				document.getElementById('addner').style.display = 'block';
				document.getElementById('nerspan').innerHTML = selstring;
	
			};
		</script>";
	};

	function addparentnode( $xml, $toklist, $parent ) {

		$dom = dom_import_simplexml($xml)->ownerDocument;
		$xpath = new DOMXpath($dom);

		# Attempt to add an element around the indicated tokens
		$idlist = explode(";", preg_replace("/;+$/", "", $toklist));
		$first = $idlist[0]; $last = end($idlist);
		
		$tmp = $xpath->query("//tok[@id=\"$first\"]");
		if ( !$tmp ) return -1; // fatal ("Token not found: $first");
		$el1 = $tmp->item(0);
		
		$newner = $dom->createElement($parent);
		
		$el1->parentNode->insertBefore($newner, $el1); $nextnode = $newner;
		if ( $debug ) { print "<p>Created: ".htmlentities($dom->saveXML($nextnode)); };
		while ( $nextnode  ) {
			$nextnode = $newner->nextSibling;
			if ( $debug ) { print "<p>Adding: ".htmlentities($dom->saveXML($nextnode)); };
			$tmp = $newner->appendChild($nextnode);
			if ( $nextnode->nodeType == 1 && $nextnode->getAttribute('id') == $last ) break;
		}; 
		
		return $newner;

	};
	
?>