<?php

	# check_login();
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();

 	$colorlist = array ( '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999' );
	$empties = array ('pb', 'lb', 'cb', 'milestone');

	$globalatts = array ( "corresp" => "reference", "id" => "identifier", );

	if ( file_exists("Resources/teitags.xml") )  $tmp = "Resources/teitags.xml";
	else if ( file_exists("$sharedfolder/Resources/teitags.xml") )  $tmp = "$sharedfolder/Resources/teitags.xml";
	else $tmp = "$ttroot/common/Resources/teitags.xml";
	$tagxml = simplexml_load_string(file_get_contents($tmp));
	$teilist = xmlflatten($tagxml);
	
	if ( $settings['defaults']['largexml'] || count($ttxml->xml->xpath("//tok")) > 500 ) { $largexml = 1; };
	
	if ( $act == "addann" ) {

		check_login(); 		
		if ( !is_writable("xmlfiles/".$ttxml->fileid) ) fatal("Not writable: $ttxml->fileid");
	
		$nertype = $_POST['type']; if ( !$nertype ) fatal("No nodename indicated");
		
		if ( $_POST['before'] ) {
			print "<p>Adding $nertype before ".$_POST['toklist'];
			$idlist = explode(";", preg_replace("/;+$/", "", $_POST['toklist']));
			$newner = simplexml_load_string("<$nertype/>");
			$target = current($ttxml->xml->xpath("//*[@id=\"{$idlist[0]}\"]"));
			if ( !$target ) fatal("Element not found: {$idlist[0]}");
			$target_dom = dom_import_simplexml($target);
			$insert = $target_dom->ownerDocument->importNode(dom_import_simplexml($newner), true);
			$target_dom->parentNode->insertBefore($insert, $target_dom);
		} else {
			print "<p>Adding $nertype around ".$_POST['toklist'];
			$newner = addparentnode($ttxml->xml, $_POST['toklist'], $nertype);
		};
		
		
		saveMyXML($ttxml->xml->asXML(), $ttxml->fileid	);
		$nexturl = "index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}";
		print "<hr><p>Your annotation has been inserted - reloading to <a href='$nexturl'>the edit page</a>";
		print "<script langauge=Javasript>top.location='$nexturl';</script>";		
		exit;


	} else if ( $_POST['remid'] || $_POST['remnr'] ) {
	
		check_login();
		
		$remid = $_POST['remid'];
		$remnr = $_POST['remnr'];
		if ( !$remid && !$remnr	 ) fatal("No annotation given");
		$maintext .= "<h2>XML Layout Editor</h2>";
		if ( $remid ) {
			$remrec = current($ttxml->xml->xpath("//*[@id=\"$remid\"]"));
		} else {
			if ( $_GET['elmid'] ) {
				$id = $_GET['elmid'];
				$editxml = current($ttxml->xml->xpath("//*[@id=\"$id\"]"));
			};
			if ( !$editxml ) { $editxml = current($ttxml->xml->xpath("//$mtxtelement")); };
			$nodelist = $editxml->xpath(".//*");
			$remrec = $nodelist[$remnr];
		};
		if ( !$remrec ) fatal("No such annotation: $remid $remnr");

		 if ( $_POST['action'] == "edit" ) {
		 
			$maintext .= "<h1>Edit Annotation</h1>
				<form action='index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}' method=post>
				<input type=hidden name=action value='save'>
				<input type=hidden name=remid value='$remid'>
				<input type=hidden name=remnr value='$remnr'>
				<table>";
			
			$nn = $remrec->getName()."";
			$maintext .= "<tr><td><th>Node<td>&lt;$nn&gt;";
		 	if ( $teilist[$nn]['display'] ) $maintext .= "<tr><td><th>Description (TEI)<td>{$teilist[$nn]['display']}";

			foreach ( $settings['xmlfile']['sattributes'][$nn] as $key => $val ) {
				if ( !is_array($val) ) continue;
				$an = $val['display'];
				if ( $val['values'] ) {
					$edt = "<select name=atts[$key]><option>[select]</option></select>";
				} else $edt = "<input name=atts[$key] value=\"{$remrec[$key]}\" size=80>";
				$maintext .= "<tr><td style='font-size: smaller; color: #dd9999;'>@$key<th>$an<td>$edt";
				$done[$key] = 1;
			};
			foreach ( $teilist[$nn]['atts'] as $key => $val ) {
				$an = $val['display'];
				if ( $val['values'] ) {
					$edt = "<select name=atts[$key]><option>[select]</option></select>";
				} else $edt = "<input name=atts[$key] value=\"{$remrec[$key]}\" size=80>";
				$maintext .= "<tr><td style='font-size: smaller; color: #99dd99;'>@$key<th>$an<td>$edt";
				$done[$key] = 1;
			};
			foreach ( $remrec->attributes() as $key => $att ) {
				if ( $done[$key] ) continue;
				$an = ucfirst($globalatts[$key]);
				$maintext .= "<tr><td style='font-size: smaller; color: #dd9999;'>@$key<th>$an<td>$att";
				$done[$key] = 1;
			};
			$maintext .= "</table>
				<p><input type=submit value=Save> <a href='index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}'>cancel</a>
				</form>";
		 
		 } else if ( $_POST['action'] == "save" ) {
		 
		 	print "<p>Changing attributes";
		 	foreach ( $_POST['atts'] as $key => $val ) {
		 		if ( !$val ) unset($remrec[$key]);
		 		else $remrec[$key] = $val;
		 	};
		 	# print showxml($remrec); exit;
			$ttxml->save();
			print "<p>Annotation modified - reloading
				<script>top.location='index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}';</script>";
			exit;
		 
		 } else if ( $act == "remove" ) {
		 
			delparentnode($ttxml->xml, $remrec);

			$ttxml->save();
			print "<p>Annotation removed - reloading
				<script>top.location='index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}';</script>";
			exit;
		 } else {
			$maintext .= "<h1>Removing Annotation</h1><p>Annotation to be removed ($remid$remnr)".$remrec->getName()." here:<br><div style='border: 1px; solid #aaaaaa;'>".$remrec->asXML()."</div><hr>Raw XML:".showxml($remrec);
		
			$maintext .= "<hr><form action='index.php?action=$action&act=remove&cid=$ttxml->fileid&elmid={$_GET['elmid']}' method=post>
				<input type=hidden name=remid value='$remid'>
				<input type=hidden name=remnr value='$remnr'>
				<input type=submit value='Remove'> <a href='index.php?action=$action&cid=$ttxml->fileid'>cancel</a>
				</form>";
		 };
	
	} else if ( $act == "index" || ( !$_GET['elmid'] && $largexml )  ) {
	
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

		# Check all nodes
		foreach ( $editxml->xpath(".//*") as $i => $node ) {
			$nn = $node->getName().""; $node['pnv#nr'] = $i;
			if ( $nn == "a" ) $node['href'] = "javascript:void(0)";
			$taglist[$nn] = 1;
		};
		
		$protects = array ( "head", "opener", "address", "div", "option", "image", "a" );
		$edittxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml->asXML() );
		$maintext .= "<div id=dospans><div id=prv $editmode>$edittxt</div></div>";
	
		foreach ( $teilist as $key => $tag ) {
			$optlist .= "<option value='$key'>$key: {$tag['display']}</option>";
			if ( $key != "p" ) $unstyle .= "\n#prv $key { all: unset; }";
			if ( in_array($key, $protects) ) $unstyle .= "\n#prv tei_$key { all: unset; }";
		};
		$maintext .= "
		<div id='addner' style='position: fixed; right: 10px; top: 20px; width: 500px; display: none; border: 1px solid #aaaaaa;'>
		<form action='index.php?action=$action&act=addann&cid=$ttxml->fileid&elmid={$_GET['elmid']}' method=post>
		<input id='toklist' name='toklist' type=hidden>
		<table width='100%' style=' background-color: white;'>
			<tr><th colspan=2>Add Annotation
			<tr><th>Span<td id='nerspan'>
			<tr><th>Tag<td><select name=type>$optlist</select> <input type=checkbox name=before value=\"1\"> Before (empty)
			<tr><td colspan=2><input type=submit value='Create'>
			<a onClick=\"document.getElementById('addner').style.display='none';\">Cancel</a>
		</table>
		</form></div>
		<div id='elminfo' style='position: fixed; right: 10px; top: 20px; padding: 5px; width: 500px; display: none; border: 1px solid #aaaaaa; background-color: white;'>
			<div id='infotxt'></div>
			<p><form action='index.php?action=$action&id=$ttxml->fileid&act=delete&elmid={$_GET['elmid']}' method=post id='remfld' style='display: none;'>
			<input type=hidden name=remid id='remid' value=''>
			<input type=hidden name=remnr id='remnr' value=''>
			<input type=hidden name=action id='remaction' value=''>
			<input type=submit value='Remove annotation'>
			<input type=button value='Close' onClick=\"document.getElementById('elminfo').style.display = 'none';\">
			<input type=button value='Edit' onClick=\"document.getElementById('remaction').value='edit'; document.getElementById('remfld').submit();\">
			</form>
		</div>

		<style>
			#prv h1 { all: unset; }
			#prv h2 { all: unset; }
			#prv a { all: unset; }
			#prv b { all: unset; }
			#prv s { all: unset; }
			$unstyle
		</style>";

	
		$maintext .= "<hr><div id='xpath' style='height: 20px;'></div><hr>
			<p>
				<input type='checkbox' name='styleshow' onChange='togglestyles(this.checked);' value='1'> Show document styles
				<input type='checkbox' name='attshow' onChange='attshow = this.checked;' value='1'> Show node attributes
			<p>Select tags to display inline:</p><div style='padding: 10px; border: 1px solid #888888'>";
		foreach ( $taglist as $key => $val ) {
			$color = array_shift($colorlist);
			$keyname = str_replace("tei_", "", $key);
			$maintext .= " 
				<span id=\"span$key\"><a   style='color: $color;' onClick=\"toggle('$key')\">&lt;$keyname&gt;</a></span> 
				";
			if ( in_array($key, $empties) || $teilist[$key]['empty'] ) $maintext .= "<style id=\"class$key\" media=\"max-width: 1px;\">
						#prv $key { color: $color; }
						#prv $key::before { content: '<$keyname/>'; color: #bbbbbb; font-size: smaller; }
					</style>
				";
			else $maintext .= "<style id=\"class$key\" media=\"max-width: 1px;\">
						#dospans $key { color: $color; }
						#dospans $key::before { content: '<$keyname>'; color: #bbbbbb; font-size: smaller; }
						#dospans $key::after { content: '</$keyname>'; color: #bbbbbb; font-size: smaller; }
					</style>
				";
		};
		$maintext .= "</div>";
		
		if ( $username ) { 
			$maintext .= "<hr><p>Drag the cursor across one or more words to make a selection to annotate; click on the tag 
				of an in-line shown tag to remove it</p>";
		};

		$maintext .= "<hr>
			<a href='index.php?action=text&cid=$ttxml->fileid'>Text View</a>";

			
		if ( $username ) $maintext .= " &bull;
			<a href='index.php?action=rawedit&cid=$ttxml->fileid'>Edit raw XML</a>
			";
	
		$maintext .= "
			<style>span[on] { text-decoration: underline; text-decoration-color: red; text-decoration-thickness: 2px; }</style>
			<script>
			document.onmouseover = mouseEvent; 
			document.onclick = clickEvent; 

			var seq = []; var selstring = '';
			var prv = document.getElementById('prv');
			var xp = document.getElementById('xpath');
			var attshow;
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
		
			function togglestyles( onoff ) {
				if ( onoff || prv.getAttribute('id') == 'prv' ) {
					prv.setAttribute('id', 'mtxt');
				} else {
					prv.setAttribute('id', 'prv');
				};
			};
		
			function mouseEvent(evt) { 
				element = evt.toElement; 
				if ( !element ) { element = evt.target; };
	
				showxpath(element);
			};

			function clickEvent(evt) { 
				element = evt.toElement; 
				if ( !element ) { element = evt.target; };
				console.log('clicked');
				console.log(element);
				
				if ( seq[0] ) { return; };
				
				var tag = element.nodeName;
				var elid = element.getAttribute('id');
				if ( tag != 'TOK' && tag != 'TEXT' && prv.contains(element) ) { 
					var attrs = element.attributes;
					nn = element.nodeName.toLowerCase().replace('tei_', '');

					var infotxt = '<table style=\"width: 100%;\"><tr><th colspan=2>Annotation Info</th></tr><tr><th>Element</th><td>' + nn + '</td></tr>';
					if ( attrs ) { 
				        for(var i = 0; i <attrs.length; i++) {
							if ( attrs[i].name.substr(0,4) != 'pnv#' ) infotxt += '<tr><th>' + attrs[i].name + '</th><td>' + attrs[i].value + '</td></tr>';
						};
					};
					infotxt += '</table>'; 
					if ( element.getAttribute('id') ) {
						document.getElementById('remid').value = element.getAttribute('id');
						document.getElementById('remfld').style.display = 'block';
					} else {
						document.getElementById('remnr').value = element.getAttribute('pnv#nr');
						document.getElementById('remfld').style.display = 'block';
					}
					document.getElementById('infotxt').innerHTML = infotxt;
					document.getElementById('addner').style.display = 'none';
					document.getElementById('elminfo').style.display = 'block';
				};
			};
						
			function showxpath(element) {
				nn = element.nodeName.toLowerCase().replace('tei_', '');
				var xpath = nn;
				if ( !prv.contains(element) ) {
					xp.innerHTML = '';
					return;
				};
				var focusnode = element;
				while ( focusnode ) {
					focusnode = focusnode.parentNode;
					if ( !focusnode ) { break; };
					nn = focusnode.nodeName.toLowerCase().replace('tei_', '');
					var attrs = focusnode.attributes;
					if ( attshow && attrs && nn != 'text' ) { 
						atts = '';
				        for(var i = 0; i <attrs.length; i++) {
				        	if ( attrs[i].name == 'id' || attrs[i].name.substr(0,4) == 'pnv#' ) { continue; }
				        	var attval = attrs[i].value;
				        	if ( attval.length > 15 ) { attval = attval.substr(0,13) + '...'; };
							if ( attval ) { atts += '@' + attrs[i].name + '=\"' + attval + '\"'; };
						}
						if ( atts ) { nn += '<span style=\"color: #aaaaaa; font-size: smaller;\">[' + atts + ']</span>'; };
					};
					if ( focusnode.getAttribute('id') == 'prv' || focusnode.getAttribute('id') == 'mtxt' ) { break; };
					xpath = nn + ' > ' + xpath;
				}; 
			
				xp.innerHTML = xpath;
			};
		
			function makespan(event) { 
				var toks = document.getElementsByTagName('tok');
				selstring = '';
				
				if (window.getSelection) {
					sel = window.getSelection();
				} else if (document.selection && document.selection.type != 'Control') {
					sel = document.selection.createRange();
				}
	
				var node1 = sel.anchorNode; 
				if ( !node1 || sel.anchorOffset == 0) { 
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
					if ( tok ) {
						tok.style['background-color'] = null;
						tok.style.backgroundColor= null; 
					};
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
				document.getElementById('elminfo').style.display = 'none';
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

	function delparentnode( $xml, $prnt ) {

		if ( !$prnt ) return -1;
	
		$dom = dom_import_simplexml($xml);
		$pd = dom_import_simplexml($prnt);
		print "<p>Moving childnodes out of parent $parentid";
				
		print showxml($prnt);
		$childs = $pd->childNodes;
		foreach ( $childs as $child  ) {
			print "<p>".htmlentities($dom->ownerDocument->saveXML($child));
			$newchild = $dom->ownerDocument->importNode($child->cloneNode(true),true);
			$pd->parentNode->insertBefore($newchild, $pd);
		}; 
		$pd->parentNode->removeChild($pd);
		
		return;

	};
	
?>