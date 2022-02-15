<?php

	check_login();
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();

 	$colorlist = array ( '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999', '#990000', '#009900', '#000099', '#999900', '#990099', '#009999' );
	$empties = array ('pb', 'lb', 'cb', 'milestone', 'dtok');

	$globalatts = array ( 
		"corresp" => "reference", 
		"id" => "identifier", 
		"bbox" => "bounding box", 
		);

	if ( file_exists("Resources/teitags.xml") )  $tmp = "Resources/teitags.xml";
	else if ( file_exists("$sharedfolder/Resources/teitags.xml") )  $tmp = "$sharedfolder/Resources/teitags.xml";
	else $tmp = "$ttroot/common/Resources/teitags.xml";
	$tagxml = simplexml_load_string(file_get_contents($tmp));
	$teilist = xmlflatten($tagxml);
	
	if ( $settings['defaults']['largexml'] || count($ttxml->xml->xpath("//tok")) > 500 ) { $largexml = 1; };
	
	if ( $_GET['editid'] ) { $_POST['remid'] = $_GET['editid']; $_POST['action'] = "edit"; };
	
	if ( !$ttxml->xml->xpath("//tok") ) {
		
		$maintext .= "<h1>XML Layout Editor</h1>
			<p>This function relies on tokenization - and this text has not been tokenized yet. 
				Click <a href='index.php?action=tokenize&cid=$ttxml->fileid'>here</a> to tokenize.";
		
		
	} else if ( $act == "addann" ) {

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

			$attlist = getattlist($nn);
			foreach ( $attlist as $key => $val ) {
				$an = $val['display'];
				if ( $val['values'] ) {
					foreach ( $val['values'] as $key2 => $val2 ) {
						$disp = $val2['display'] or $disp = $key2;
						$options .= "<option value=\"$key2\">$disp</option>";
					};
					$edt = "<select name=atts[$key]><option>[select]</option>$options</select>";
				} else $edt = "<input name=atts[$key] value=\"{$remrec[$key]}\" size=80>";
				$maintext .= "<tr><td style='font-size: smaller; color: #99dd99;'>@$key<th>$an<td>$edt";
			};
			$maintext .= "</table>
				<p><input type=submit value=Save> 
					<a href='index.php?action=$action&cid=$ttxml->fileid&elmid={$_GET['elmid']}'>cancel</a>
					&bull;
					<a href='index.php?action=$action&cid=$ttxml->fileid&act=elm&elm=$nn'>list all &lt;$nn&gt;</a>
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
	
	} else if ( $act == "elm" && $_POST['vals']  ) {
	
		$nn = $_POST['elm'];
		$xpl = "$mtxtelement//$nn";		
		$att = $_POST['att'];
		print "<p>Changing $att in $nn";
		
		foreach ( $ttxml->xml->xpath($xpl) as $node ) {
			$cnt++;
			if ( $node[$att] || $_POST['vals'][$cnt] ) $node[$att] = $_POST['vals'][$cnt];
			print "<p>$cnt: ".$_POST['vals'][$cnt];
		};
		$ttxml->save();
		print "<p>Annotations modified - reloading
			<script>top.location='index.php?action=$action&cid=$ttxml->fileid&act=elm&elm=$nn';</script>";
		exit;
	
	} else if ( $act == "elm"  ) {
	
		$nn = $_GET['elm'];
		$att = $_GET['att'];
		$xpath = "//$mtxtelement//$nn";

		$nname = $settings['xmlfile']['sattributes'][$nn]['display'];
		if ( $nname ) $ntit = " ($nname)";
		$nxml = current($tagxml->xpath("//item[@key=\"$nn\"]"));

		$maintext .= "<h2>XML Layout Editor</h2><h1>Edit Element: $nn$ntit</h1>".$ttxml->tableheader();
		if ( $nxml->desc ) $maintext .= "<div style='padding:4px; margin: 4px; border: 1px solid #bbbbbb;'><a href='https://tei-c.org/release/doc/tei-p5-doc/en/html/ref-$nn.html' target=tei style='color: #aaaaaa;'>&lt;$nn&gt;:</a> {$nxml->desc}</div>";
		if ( $_GET['att'] ) $maintext .= "<form action='index.php?action=$action&act=elm&id=$ttxml->fileid' method=post>
			<input type=hidden name=cid value=\"$ttxml->fileid\">
			<input type=hidden name=elm value=\"$nn\">
			<input type=hidden name=att value=\"$att\">
			";
			
		$attlist = getattlist($nn);
		if ( $att ) $maintext .= "<div style='padding:4px; margin: 4px; border: 1px solid #bbbbbb;'><span style='color: #aaaaaa;'>@$att:</span> {$attlist[$att]['display']}</div>";

		$maintext .= "<hr><table id=mtxt><tr><td><td>";

		if ( $att ) {
			$val = $attlist[$att];
			$an = $val['display'];
			$maintext .= "<th>$att";	
			if ( $val['values'] ) {
				$options = "<option value=\"\">[select]</option>";
				foreach ( $val['values'] as $key2 => $val2 ) {
					$disp = $val2['display'] or $disp = $key2;
					$options .= "<option value=\"$key2\">$disp</option>";
				};
			};			
		} else foreach ( $attlist as $key => $val ) {
			$an = $val['display'];
			if  ( $key == "id" )
				$maintext .= "<th title=\"$an\">$key";	
			else {
				$attdef = 1;
				$maintext .= "<th title=\"$an\"><a href='index.php?action=$action&act=elm&id=$ttxml->fileid&att=$key&elm=$nn'>$key</a>";	
			};
		};
	
		foreach ( $ttxml->xml->xpath($xpath) as $node ) {
			$cnt++;
			if ( $node['id'] ) $idfld = "<a href='index.php?action=$action&act=edit&cid=$ttxml->fileid&editid={$node['id']}'>{$node['id']}</a>";
				else $idfld = "[$cnt]";
			$maintext .= "<tr><td>$cnt
				<th>$idfld";
			if ( $_GET['att'] ) {
				$key = $_GET['att'];
				$val = $node[$key];
				if ( $options ) $maintext .= "<td><select name=vals[$cnt]>$options</select>";	
				else $maintext .= "<td><input size=60 name=vals[$cnt] value=\"$val\">";				
			} else foreach ( $attlist as $key => $val ) {
				$maintext .= "<td>{$node[$key]}";
			};
			$maintext .= "<td><div>".$node->asXML()."</div>";
		};
		$maintext .= "</table>";
		if ( $_GET['att'] ) $maintext .= "<p><input type=submit value=Save>
			<a href='index.php?action=$action&act=elm&id=$ttxml->fileid&elm=$nn'>cancel</a>
			</form> ";
			
		if ( $attdef ) $maintext .= "<hr><p>Click on a column to edit a specific attribute for all &lt;$nn&gt;";	
			
		$maintext .= "<hr><p>Switch:";

		# Check all nodes
		foreach ( $ttxml->xml->xpath("//$mtxtelement//*") as $i => $node ) {
			$nn = $node->getName().""; $nntxt = str_replace("tei_", "", $nn);
			if ( !$done[$nn] ) $maintext .= " <a href='index.php?action=$action&act=elm&id=$ttxml->fileid&elm=$nn'>&lt;$nntxt&gt;</a>";
			$done[$nn] = 1;
		};
		$maintext .= "<hr><p><a href='index.php?action=$action&id=$ttxml->fileid'>back to layout edit</a>";
	
	
	} else if ( $act == "taglist" ) {

		foreach ( $ttxml->xml->xpath("//$mtxtelement//*") as $i => $node ) {
			$nn = $node->getName().""; $nntxt = str_replace("tei_", "", $nn);
			$have[$nn] = $nntxt;
		};
	
		$maintext .= "<h1>TEI Tag List</h1>
			<p>Below is the list of tags defined for this project (or by default in TEITOK)
			<table id=rollovertable><tr><th>Tag<th>Description<th>Attributes";
		foreach ( $teilist as $key => $tag ) {
			if ( $have[$key] ) $key = "<a href='index.php?action=$action&act=elm&elm=$key&id=$ttxml->fileid'>$key</a>";
			$maintext .= "<tr><th>$key<td>{$tag['display']}<td><table>";
			foreach ( $tag['atts'] as $key2 => $tag2 ) {
				$maintext .= "<tr><th>$key2<td>{$tag2['display']}";
			};
			$maintext .= "</table></td></tr>";
		};
		$maintext .= "</table>
			<hr><a href='index.php?action=$action&id=$ttxml->fileid'>back to layout</a>";


	} else if ( $act == "index" || ( !$_GET['elmid'] && $largexml )  ) {
	
		$maintext .= "<h2>XML Layout Index</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();		
		
		$maintext .= "<p>Select a part of the XML to edit<hr>";
		
		$basexp = "$mtxtelement";
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
		} else if ( $_GET['xpath'] ) {
			$basexp = $_GET['xpath'];
			$root = current($ttxml->xml->xpath($basexp));
			print "Nr of Children: ".count($root->children()); exit;
			if ( !$root ) fatal("Node not found $basexp");
			$nodepath = $root->getName();
		};
		if ( !$root ) {
			$root = current($ttxml->xml->xpath("//$mtxtelement"));
			$nodepath = $root->getName();
			while ( count($root->children()) == 1 ) {
				$cn = current($root->children())->getName()."";
				$nodepath .= " > $cn";
				$basexp .= "/{$cn}[1]";
				$root = current($ttxml->xml->xpath($basexp));
			};
		};
		$maintext .= "<p>$nodepath";
		foreach ( $root->children() as $child ) {
			$nn = $child->getName()."";
			$cnt[$nn]++;
			$maintext .= "<p> - $nn";
			$ncnt = $cnt[$nn];
			$xp = $basexp."/{$nn}[$ncnt]";
			if ( $child['id'] ) $maintext .= "[@id=<a href='index.php?action=$action&act=index&id=$ttxml->fileid&selid={$child['id']}'>".$child['id']."</a>] - <a href='index.php?action=$action&id=$ttxml->fileid&elmid={$child['id']}'>select</a>";
			else $maintext .= "[<a href='index.php?action=$action&act=index&id=$ttxml->fileid&xpath=$xp'>{$ncnt}</a>] - <a href='index.php?action=$action&id=$ttxml->fileid&xpath=$xp'>select</a>";
		};
				
	} else {

		$editmode = "onmouseup='makespan(event);'";
	
		$maintext .= "<h2>XML Layout Editor</h2><h1>".$ttxml->title()."</h1>";
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
		} else if ( $_GET['xpath'] ) {
			$basexp = $_GET['xpath'];
			$editxml = current($ttxml->xml->xpath($basexp));
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
			$tagnames[strtolower($key)] = $tag['display'];
			$optlist .= "<option value='$key'>$key: {$tag['display']}</option>";
			$unstyle .= "\n#prv $key { all: unset; }";
			if ( in_array($key, $protects) ) $unstyle .= "\n#prv tei_$key { all: unset; }";
		};
		$maintext .= "
		<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; padding: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa; z-index: 3;'></div>
		<div id='addner' style='position: fixed; right: 10px; top: 20px; width: 500px; display: none; border: 1px solid #aaaaaa;'>
		<form action='index.php?action=$action&act=addann&cid=$ttxml->fileid&elmid={$_GET['elmid']}' method=post>
		<input id='toklist' name='toklist' type=hidden>
		<table width='100%' style=' background-color: white;'>
			<tr><th colspan=2>Add Annotation
			<tr><th>Span<td id='nerspan'>
			<tr><th>Tag<td><select name=type>$optlist</select> <input type=checkbox name=before value=\"1\"> Before (empty)
			<tr><td colspan=2><input type=submit value='Create'>
			<a onClick=\"canceladdann();\">Cancel</a>
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
			#prv p, #prv tei_div, #prv tei_head { display: block; padding-bottom: 10px; }
		</style>";

		if ( $username ) $alltags = " (<a href='index.php?action=$action&act=taglist&id=$ttxml->fileid'>view all tags</a>)";

		// 				<input type='checkbox' name='attshow' onChange='attshow = this.checked;' value='1'> Show node attributes
		$maintext .= "<hr><div id='xpath' style='height: 20px;'></div><hr>
			<p>
				<input type='checkbox' name='styleshow' onChange='togglestyles(this.checked);' value='1'> Show document styles
			<p>Select tags to display inline $alltags:</p><div style='padding: 10px; border: 1px solid #888888'>";
		foreach ( $taglist as $key => $val ) {
			$color = array_shift($colorlist);
			$keyname = str_replace("tei_", "", $key);
			if ( $done[strtolower($keyname)] ) continue;
			$done[strtolower($keyname)] = 1;
			$ktit = $tagnames[strtolower($keyname)];
			$maintext .= " 
				<span id=\"span$key\" title='$ktit'><a  style='color: $color;' onClick=\"toggle('$key')\">&lt;$keyname&gt;</a></span> 
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
				var username = '$username';
			</script>
			<script src='$jsurl/xmllayout.js'></script>";
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

	function getattlist($nn, $remrec = null) {
		global $globalatts, $settings, $teilist;
		if ( $remrec ) foreach ( $remrec->attributes() as $key => $att ) {
			$an = ucfirst($globalatts[$key]);
			$maintext .= "<tr><td style='font-size: smaller; color: #dd9999;'>@$key<th>$an<td>$att";
			$attlist[$key] = array('display' => $an);
		};
		foreach ( $teilist[$nn]['atts'] as $key => $val ) {
			$attlist[$key] = $val;
		};
		foreach ( $settings['xmlfile']['sattributes'][$nn] as $key => $val ) {
			if ( !is_array($val) ) continue;
			$attlist[$key] = $val;
		};
		return $attlist;
	};
	
?>