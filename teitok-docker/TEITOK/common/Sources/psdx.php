<?php

	$cid = $_GET['cid'];
	$cid = preg_replace("/.*\//", "", $cid);
	$cid = preg_replace("/\.xml/", "", $cid);
	$treeid = $_GET['treeid'];
	$sid = $_GET['sentence'] or $sid = $_GET['sid'];
	$xpath = $_POST['xpath'] or $xpath = $_GET['xpath'] or $xpath = $_POST['query'] or $xpath = $_GET['query'];
	if ( !$xpath && $_GET['qid'] && ( $userid || $username ) ) {
		require("$ttroot/common/Sources/querymng.php");
		$qid = $_GET['qid'];
		$xpath = getq($qid);
		$act = "xpath";
	};
	$xpath = stripslashes($xpath);
	if ( $cid ) {
		$psdxfile = "Annotations/$cid.psdx";
		if ( !file_exists($psdxfile) ) {
			fatal("No such PSDX annotation: $cid"); 
		};
	};
		
	if ( $act == 'query' && ( file_exists("cqp/s_id.avs") || getset("qlis/nosent") )  ) {
	
		print "Reloading to BTQL
			<script language=Javascript>window.location='index.php?action=btql&type=PSDX&query={$_GET['query']}'</script>
			";
	
	} else if ( $sid && $psdxfile ) {
	 
		require_once("$ttroot/common/Sources/ttxml.php");
	
		$psdx = simplexml_load_file($psdxfile);
		if ( !$psdx ) {
			fatal("Unable to load PSDX file: $cid");
		};
	
		$maintext .= "<h2>Tree Viewer</h2>";
	
		$ttxml = new TTXML();

		$forest = current($psdx->xpath("//forest[@sentid=\"$sid\"]"));
	
		$maintext .= "<h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();
		$tmp = $forest->xpath("./preceding::forest[1]");
		if ( $tmp ) {
			$prid = current($tmp)['sentid'];
			$plink ="index.php?action=$action&cid=$ttxml->fileid&sid=$prid";
			$prev = "<a href='$plink'>&lt;</a>";
		};
		$tmp = $forest->xpath("./following::forest[1]");
		if ( $tmp ) {
			$flid = current($tmp)['sentid'];
			$nlink = "index.php?action=$action&cid=$ttxml->fileid&sid=$flid";
			$next = "<a href='$nlink'>&gt;</a>";
		};
		$maintext .= "<table style='width: 100%'><tr><td>$prev<td style='text-align: center'><b>Tree {$forest['id']} = Sentence $sid</b></td><td>$next</tr></table>
		<hr>";
		$tmp = current($ttxml->xpath("//s[@id=\"$sid\"]"));
		if ( !$tmp ) { fatal("no such sentence: $sid"); };
		$editxml = $tmp->asXML();
		$maintext .= "<div id=mtxt>$editxml</div> <hr>";

		$settingsdefs .= "\n\t\tvar formdef = ".array2json(getset('xmlfile/pattributes/forms', array())).";";
		$settingsdefs .= "\n\t\tvar tagdef = ".array2json(getset('xmlfile/pattributes/tags', array())).";";
	

		if ( $_GET['jmp'] ) {
			$jmp = $_GET['jmp'];
			$maintext .= "<script>
				function posttree () {
					var hlnode = document.evaluate('//*[@nodeid=\"$jmp\"]', document, null, XPathResult.ANY_TYPE, null).iterateNext();
					if ( hlnode ) {
						hlnode.setAttribute('fill', '#992200');
						hlnode.setAttribute('font-weight', 'bold');
					};
				};
			</script>";
		};

		$setopts = array2json($_SESSION['options']);
		
		$treexml = $forest->asXML();
		if ( $act == "treeedit" ) {
			check_login();
			if ( !is_writable($psdxfile)  ) {
				fatal ("File Annotations/$cid.psdx is not writable - please contact admin"); 
			};
			
			$file = file_get_contents($psdxfile);
			$forestxml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$forestxml ) { fatal ("Failed to load PSDX file: Annotations/$cid.psdx"); }; # print "Node not found: <hr>//forest[@id=\"$treeid\"]//*[@id=\"$nid\"]<hr>".htmlentities($forestxml->asXML()); exit; };
	
			$result = $forestxml->xpath("//forest[@id=\"$treeid\"]"); 
			$forest = $result[0]; 
	
			$maintext .= "
				<h2>Edit Tree</h2>
				<p>Select an eTree in the tree, and select the buttons to move it around.</p><hr>
				<hr>
				<div style='display: block' id=editbuttons>
					<p>Actions on the selected node:
					<p>
						<button id='moveup' disabled onClick='moveup()'>Move out of parent</button>
						<button id='moveleft' disabled onClick='moveleft()'>Move to previous tree</button>
						<button id='moveright' disabled onClick='moveright()'>Move to next tree</button>
						<button id='movedown' disabled onClick='movedown()'>Insert parent node</button>
						<button id='insert' disabled onClick='insertempty()'>Insert empty child node</button>
					 - <span id='changetag' style='display: none;'>Change nodename: <input id=tagtxt> <button id='tagchange' onClick='tagchange()'>Change</button></span>
					<p>
						<button id='source' onClick='togglesource()'>Show XML</button>
						<button id='update' style='display: none;' onClick='updatefromraw()'>Update</button>
						<button id='undo' disabled onClick='undo()'>Undo</button>
						<button id='save' disabled onClick='savetree()'>Save</button>
				</div>
				<form style='display: none;' action='index.php?action=$action&act=treesave' id=submitxml name=submitxml method=post>
					<h2>Raw XML Edit</h2>
					<p>Below you can edit the raw XML when needed. Click the <i>Update</i> button to apply the changes.
					<input type=hidden name='cid' value='$cid'>
					<input type=hidden name='treeid' value='$treeid'>
				</form>
				<script language=Javascript src=\"$jsurl/psdx.js\"></script>
				<script language=Javascript>
				var username = '';
				var cid = '$cid';
				function treeclick(elm) { 
					var id = elm.getAttribute('nodeid');
					// handle tree edit		
					nodeClick(id);				
					console.log(id);
				};
				</script>
				
				<script language=Javascript src=\"$jsurl/psdxedit.js\"></script>
			";
		};	
		$maintext .= "<div id=svgdiv></div>

			<textarea id=treexml style='display: none'>$treexml</textarea>
			<script language=\"Javascript\" src=\"$jsurl/tokedit.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/tokview.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/deptree.js\"></script>
			<script language=\"Javascript\" src=\"$jsurl/treeview.js\"></script>
			<script>
				parser = new DOMParser();
			    const ispunct = new RegExp(`^[!?(){}.,:;]+$`);
				var treetxt = document.getElementById('treexml').value;
				var selnode = '';
				var psdxTree = parser.parseFromString(treetxt,\"text/xml\");
				var treexml = psdxTree;
				var jsonTree = psdx2tree(psdxTree.firstChild);
				
				NodeList.prototype.forEach = Array.prototype.forEach;
				
				function psdx2tree(node) {
					if ( !node ) return;
					if ( node.nodeType != 1 ) return;
					var label = '';
					if ( node.hasAttribute('Label') ) label = node.getAttribute('Label') 
					else 
						if ( node.hasAttribute('Text') ) label = node.getAttribute('Text') 
					else 
						if ( node.hasAttribute('Notext') ) label = node.getAttribute('Notext') 
					else 
						if ( node.hasAttribute('NoText') ) label = node.getAttribute('NoText') 
					;
										
					tokid = node.getAttribute('tokid'); if ( !tokid ) tokid = '';
					nodeid = node.getAttribute('id'); if ( !nodeid ) nodeid = '';
					var json = { 'label': label, 'nodeid': nodeid, 'tokid': tokid };

					if ( node.hasChildNodes() ) {
						json['children'] = [];
						var children = node.childNodes;
						children.forEach(function(item){
							itemjson = psdx2tree(item);
							if ( itemjson ) json['children'].push(itemjson);
						});	

					};
					if ( ispunct.test(label) ) json['ispunct'] = 1;

				
					return json;
				};
				
				function renewtree() {
					treetxt = document.getElementById('treexml').value;
					var psdxTree = parser.parseFromString(treetxt,\"text/xml\");
					var tree = psdx2tree(psdxTree.firstChild);
					
					tree['options'] = {};
					tree['options']['type'] = 'constituency';
					document.getElementById('tokinfo').style['z-index'] = 3000;
					drawsvg(tree, 'svgdiv');
				};

				$settingsdefs
				var tree = {};
				var treeid = '$ttxml->xmlid-$sid';
				var options = $setopts;
				renewtree();

				document.onkeydown = function(evt) {
					evt = evt || window.event;
				   if ( evt.keyCode == 37 ) {
						var plink = '$plink';
						if ( plink ) { window.open(plink, '_self'); };
				   } else if ( evt.keyCode == 39 ) {
						var nlink = '$nlink';
						if ( nlink ) { window.open(nlink, '_self'); };
				   };
				};
				
				
			</script>";
	
		$maintext .= "<hr><p><a href='index.php?action=$action&cid=$ttxml->fileid&'>{%Sentence list}</a> &bull; ".$ttxml->viewswitch();

	} else if ( $psdxfile ) {

		require_once("$ttroot/common/Sources/ttxml.php");
	
		$psdx = simplexml_load_file($psdxfile);
		if ( !$psdx ) {
			fatal("Unable to load PSDX file: $cid");
		};
	
		$maintext .= "<h2>Tree Viewer</h2>
			<p>Select a sentence from the tree below</p>
			<table>";
	
		$ttxml = new TTXML();

		foreach ( $psdx->xpath("//forest") as $forest ) {
			$sentid = $forest['sentid'];
			$fileid = $ttxml->fileid;
			$sent = sentbyid($fileid, $sentid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&sid=$sentid'>tree</a><td>".$sent;
		};
		$maintext .= "</table>";

	} else {
		
		$maintext .= "
			<h1>Tree Viewer</h1>
			<p>{%Select a file from the list below or} <a href='index.php?action=btql&type=PSDX'>{%Search}</a><hr>
			<table>";
	
		# Show all available PSDX files
		foreach (glob("Annotations/*.psdx") as $filename) {
			$some = 1; $anid = preg_replace( "/.*\/(.*?)\.psdx/", "\\1", $filename );
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$anid'>".ucfirst($anid)."</a><td>";
		};
		$maintext .= "</table>";
		if ( !$some ) $maintext .= "<p><i>{%No results found}</i>";

	};
	

?>