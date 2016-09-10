<?php
	// Script to XPath queries on XML files
	// DEV script, avoid using
	// (c) Maarten Janssen, 2015

	$maintext .= "<h1>Xpath File Finder</h1>";

	$xquery = $_POST['xquery'] or $xquery = $_GET['xquery'];
	$xp2 = $_POST['xp2'] or $xp2 = $_GET['xp2'];
	
	$xp = $xquery or $xp = "//date[@value=\"2007\"]";

	$xp = preg_replace ("/\"/", "&quot;", $xp);
	$xp2 = preg_replace ("/\"/", "&quot;", $xp2);
	$maintext .= "<p><form action=\"index.php?action=$action\" method=post>Search query: 
		<input type=hidden name=action value=\"$action\">
		<input name=xquery size=80 value=\"$xp\">
		<input type=submit value=Search>
		<p>Search query 2: <input name=xquery2 size=80 value=\"$xp2\">
		
		<p><input type=radio name=res value='file' checked> List files 
			<br><input type=radio name=res value='tok'> List tokens 
			<br><input type=radio name=res value='elm'> List nodes (XML)
		</form>";
	
	$debug = 1;
	
	if ( $xquery ) {
		
		if ( $_POST['res'] == "elm" || $_POST['res'] == "tok" ) {
			if ( $xp2 ) {
				$xpath2 = "-m '{$_GET['xquery2']}'";
				$xtxt = " and $xp2";
			};
	
			$maintext .= "<hr style='margin-top: 15px;'>XQuery: $xquery $xtxt";
			$cmd = "xmlstarlet sel -t -m \"$xquery\" $xpath2 -f -o \"::\"  -c . -n $xmlfolder/*.xml $xmlfolder/*/*.xml";
			if ( $debug ) $maintext .= "<p>Unix command: $cmd";
		
			if ( $_POST['res'] == "tok" ) {
				#Build the view options	
				foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
					$formcol = $item['color'];
					# Only show forms that are not admin-only
					if ( $username || !$item['admin'] ) {	
						if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
						$ikey = $item['inherit'];
						if ( 1==1 || preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
							$formbuts .= " <button id='but-$key' onClick=\"setbut(this['id']); setForm('$key')\" style='color: $formcol;$bgcol'>{%".$item['display']."}</button>";
							$fbc++;
						};
						if ( $key != "pform" ) { 
							if ( !$item['admin'] || $username ) $attlisttxt .= $alsep."\"$key\""; $alsep = ",";
							$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
						};
					};
				};
				foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
					$val = $item['display'];
					if ( preg_match("/ $key=/", $editxml) || 1==1 ) {
						if ( is_array($labarray) && in_array($key, $labarray) ) $bc = "eeeecc"; else $bc = "ffffff";
						if ( !$item['admin'] || $username ) {
							if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
							$attlisttxt .= $alsep."\"$key\""; $alsep = ",";		
							$attnamelist .= "\nattributenames['$key'] = \"{%".$item['display']."}\"; ";
							$pcolor = $item['color'];
							$tagstxt .= " <button id='tbt-$key' style='background-color: #$bc; color: $pcolor;$bgcol' onClick=\"toggletag('$key')\">{%$val}</button>";
						};
					} else if ( is_array($labarray) && ($akey = array_search($key, $labarray)) !== false) {
						unset($labarray[$akey]);
					};
				};

				$viewoptions .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

				$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
				$jsontrans = array2json($settings['transliteration']);
				$maintext .= "
					$viewoptions $showoptions
					<hr>
					<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
					<div id=mtxt>
		
				<script language=Javascript>$moreactions</script>
					<hr><p>Matching tokens:
					
					<table>";	
			} else {
				$maintext .= "<hr><p>Matching elements:<table>";		
			};
			$output = shell_exec($cmd);
			foreach ( explode (  "\n", $output ) as $line ) {
				list ( $filename, $node ) = explode ( "::", $line );
				if ( $node == "" ) continue;
				if ( preg_match("/id=\"(w-\d+)\"/", $node, $matches) ) {
					$tid = $matches[1];
					$tokid = "&tid=$tid";
				};
				$filename = preg_replace ( "/^$xmlfolder\//", "", $filename );
				if ( $_POST['res'] == "tok" ) {
					$maintext .= "<tr>
						<td>$filename
						<td><a href='index.php?action=tokedit&cid=$filename$tokid'>edit</a>
						<td><a href='index.php?action=file&cid=$filename$tokid'>view</a>
						<td>".$node;
				} else {
					$maintext .= "<tr><td><a href='index.php?action=file&cid=$filename$tokid'>$filename</a><td>".htmlentities($node);
				};
			};
			$maintext .= "</table></div>";

			if ( $_POST['res'] == "tok" ) {
				$maintext .= "
					<script language=Javascript src='$jsurl/tokedit.js'></script>
					<script language=Javascript src='$jsurl/tokview.js'></script>
					<script language=Javascript>

						function makeunique () {
							var mtxt = document.getElementById('mtxt');
							var ress = mtxt.getElementsByTagName('tr');
							for ( var a = 0; a<ress.length; a++ ) {
								var res = ress[a];
								// console.log(res);
								var resid = res.getAttribute('id');
								var toks = res.getElementsByTagName(\"tok\");
								for ( var b = 0; b<toks.length; b++ ) {
									var tok = toks[b];
									var tokid = tok.getAttribute('id');
									tok.setAttribute('id', resid+'_'+tokid);
								};					
								var toks = res.getElementsByTagName(\"dtok\");
								for ( var b = 0; b<toks.length; b++ ) {
									var tok = toks[b];
									var tokid = tok.getAttribute('id');
									tok.setAttribute('id', resid+'_'+tokid);
								};					
							};
						};

						makeunique();
						var username = '$username';
						var formdef = $jsonforms;
						var orgtoks = new Object();
						var attributelist = Array($attlisttxt);
						$attnamelist
						formify(); 
						var orgXML = document.getElementById('mtxt').innerHTML;
						setForm('$showform');
			
						function hllist ( ids, container, color ) {
							idlist = ids.split(' ');
							for ( var i=0; i<idlist.length; i++ ) {
								var id = idlist[i];
								// node = getElementByXpath('//*[@id=\"'+container+'\"]//*[@id=\"'+id+'\"]');
								node = document.getElementById(container+'_'+id);
								if ( node ) {
									if ( node.nodeName == 'DTOK' ) { 
										node = node.parentNode; 
										// console.log(node);
										if ( color == '#ffffaa' ) {
											node.style['background-color'] = '#ffeeaa';
											node.style.backgroundColor= '#ffeeaa'; 
										} else {
											node.style['background-color'] = '#ffcccc';
											node.style.backgroundColor= '#ffcccc'; 
										};
									} else {
										node.style['background-color'] = color;
										node.style.backgroundColor= color; 
									};
								};
							};
						};
						function getElementByXpath(path) {
							return document.evaluate(path, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
						}
					</script>
					";		
			};
			
		} else {
			
			if ( $xp2 ) {
				$xpath2 = "-m '{$_GET['xquery2']}'";
				$xtxt = " and $xp2";
			};
	
			$maintext .= "<hr style='margin-top: 15px;'>XQuery: $xquery $xtxt";
			$cmd = "xmlstarlet sel -t -m \"$xquery\" $xpath2 -f $xmlfolder/*.xml $xmlfolder/*/*.xml";
			if ( $debug ) $maintext .= "<p>Unix command: $cmd";
		
			$maintext .= "<hr><p>Matching XML files:";		
			$output = shell_exec($cmd);
			foreach ( explode (  "\n", $output ) as $filename ) {
				$filename = preg_replace ( "/^$xmlfolder\//", "", $filename );
				$maintext .= "<p><a href=\"index.php?action=edit&id=$filename\">$filename</a>";
			};
		};
	};
			
?>