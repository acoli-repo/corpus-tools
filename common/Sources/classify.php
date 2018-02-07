<?php
	// Allow users to create their own annotations, and make them searchable in TT-CQP
	// (c) Maarten Janssen, 2018
	check_login();
	if ( !file_exists("/usr/local/bin/tt-cqp") && !$settings["defaults"]["tt-cqp"] ) {
		fatal ("This function relies on TT-CQP, which does not seem to be installed");
	};
	
	// Use/build an external annotation file to classify 
	$file = $_GET['file'];
	if ( $file ) {
		if ( !file_exists($file) && file_exists("Users/$file.xml") ) $file = "Users/$file.xml";
		if ( !file_exists($file) && $username && file_exists("Annotations/$file.xml") ) $file = "Annotations/$file.xml";
		
		if ( !file_exists($file) ) fatal("Non-existing annotation file: $file");
		else $extann = simplexml_load_file($file);

		// TODO: Check if you are allowed to write to this annotation
	};
	
	if ( $file != "" && substr($file,0,6) != "Users/" ) check_login(); // Allow less to visitors

	$useridtxt = $shortuserid;
		
	if ( gettype($extann) == "object" ) {
		foreach ( $extann->xpath("//def/field") as $i => $deffld) { 
			$values = "";
			foreach ( explode(",", $deffld['values']) as $fldopt ) {
				$values .= "<option value='$fldopt'>$fldopt</option>";
			};
			$optname = $deffld['key'].'';
			$optdef[$optname] = $deffld; 
			$optlist[$optname] = $values; 
			$opttit[$optname] = $deffld['short'] or $opttit[$optname] = $deffld['display'] or $opttit[$optname] = $deffld['key']; 
		};

		$classname = $extann['name'];

		$desc = current($extann->xpath("//def/desc"));
		if ( $desc) $desc = $desc->asXML();
		$desc = preg_replace("/<\/?desc[^>]*>/", "", $desc);
		$defcql = current($extann->xpath("//def/cql/@value"));

			
		if ( $_GET['fields'] ) $opts = explode(",", $_GET['fields']);
		else if ( $_POST['flds'] ) $opts = array_keys($_POST['flds']);
		else $opts = array_keys($optlist);
		$optx = join(",", $opts);

		if ( count($opts) == 1 ) {
			$fld = $opts[0];
			$classname = $optdef[$fld]['display'];
			$desc = "<p>".$optdef[$fld]['desc']."</p>";
			$itmrest = "[@$fld and @$fld != \"\"]";
		};

		$maintext .= "<h1>{%Custom annotation}: $classname</h1> $desc";

		if ( $extann['id'] == $useridtxt ) $myfile = true; // Check whether this file is mine (and I can edit it)

		if ( $act == "" && !$_GET['fields'] && !$_POST['flds'] && count($opts) > 1 ) $act = "choose";
	};

	if ( $act == "create" ) {
		
		$file = "Users/ann_$useridtxt.xml"; // TODO: allow superusers to create other files as well?
		file_put_contents($file, "<annotation author=\"$realname\" id=\"$useridtxt\"></annotation>");
		print "Annotation file has been created
			<script language=Javascript>top.location='index.php?action=$action&file=$file&act=fields';</script>"; exit;
	
	} else if ( gettype($extann) != "object" ) {
	
		$maintext .= "<h1>{%Choose an annotation file}</h1>";
		if ( file_exists("Users/ann_$useridtxt.xml") ) {
			$anfs["Users/ann_$useridtxt.xml"] = 1;
			$maintext .= "<p><a href=\"index.php?action=$action&file=ann_$useridtxt\">{%Your annotation file}</a>";
		} else {
			$anfs["Users/ann_$useridtxt.xml"] = 1;
			$maintext .= "<p><a href=\"index.php?action=$action&act=create\">{%Create your own annotation file}</a>";
		};
		
		if ( $user['email'] ) {
			$maintext .= "<h2>Annotations made by other users</h2>
			<table>
			<tr><th>Field<th>File<th>Description";
			foreach ( explode("\n", shell_exec("/usr/bin/grep -H '<field' Users/*.xml")) as $line )  {
				$ufld = $ufile = $udesc = $ustat = "";
				if ( preg_match("/key=\"([^\"]+)\"/", $line, $matches ) ) { $ukey = $matches[1]; };
				if ( preg_match("/display=\"([^\"]+)\"/", $line, $matches ) ) { $ufld = $matches[1]; };
				if ( preg_match("/^([^:]+):/", $line, $matches ) ) { $ufile = $matches[1]; };
				if ( preg_match("/desc=\"([^\"]+)\"/", $line, $matches ) ) { $udesc = $matches[1]; };
				if ( preg_match("/status=\"([^\"]+)\"/", $line, $matches ) ) { $ustat = $matches[1]; };
				$ufiletxt = str_replace("Users/", "", $ufile);
				$ufiletxt = str_replace(".xml", "", $ufiletxt);
				if ( $ustat != "private" && $ufile != "Users/ann_$useridtxt.xml" )
				$maintext .= "<tr><td><a href='index.php?action=$action&file=$ufile&fields=$ukey'>$ufld</a>
					<td>$ufiletxt
					<td>$udesc";
				$anfs[$ufile] = 1;
			};
			$maintext .= "</table>";
   		};
		
		if ( count($anfs) == 1 && 1==2) {
			$autofile = array_keys($anfs)[0];
			print "Auto-selecting: $autofile
					<script language=Javascript>top.location='index.php?action=$action&file=$autofile';</script>"; exit;
		};
	
	} else if ( $act == "save" ) {
	
		foreach($_POST['vals'] as $key => $tmp){
			$someset = false;
			foreach( $tmp as $att => $val) {
				if ( $val != "" ) {
					$someset = 1;
				};
			};
			if ( $someset ) {
				print "<p>Setting: $key => ".print_r($tmp, 1); 
				$newnode = xpathnode($extann, "/annotation/item[@c_pos=\"$key\"]");
				# Lookup the text_id, word, and id 
				$cmd = "/bin/echo '$key' | /usr/local/bin/tt-cqp --mode=pos2tab --cql='tabulate match.word match.id match.text_id match._'";
				$result = shell_exec($cmd); 
				list ( $word, $id, $textid ) = explode ( "\t", $result );
				$newnode['c_idx'] = "$textid:$id";
				$newnode['c_wrd'] = "$word";
				print htmlentities($newnode->asXML());
				foreach( $tmp as $att => $val) {
					if ( $val != "" ) {
						$newnode[$att] = $val;
					};
				};
			};
		}; 
		file_put_contents($file, $extann->asXML());
		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&file=$file&act=list';</script>"; exit;

	} else if ( $act == "choose" ) {
	
		$maintext .= "<h2>{%Choose field(s) to display/use}</h2>
		
		<form action='index.php?action=$action&file=$file&act=list' method=post>";
	
		foreach ( $extann->xpath("//field") as $fld ) {
			$maintext .= "<p><input type=checkbox name=flds[{$fld['key']}] value=\"1\"> {$fld['display']}";
		};
		if ( $myfile )
		$maintext .= "<p><input type=submit value={%Choose}></form>
			<hr><p><a href='index.php?action=$action&file=$file&act=fields'>{%Define fields}</a>";

	} else if ( $act == "fields" && $myfile ) {

		$maintext .= "<h2>{%Define fields}</h2>
		
		<form action='index.php?action=$action&file=$file&act=define' method=post>
		<p>{%Annotation name}: <input name=name value='{$extann['name']}' size=70>
		<p>{%Description}: <br><textarea name=desc style='width: 100%; height: 100px;'>$desc</textarea>

		<h2>Fields</h2>
		<table>
		<tr><th>{%Key}<th>{%Settings}";

		foreach ( $extann->xpath("//field") as $fld ) {
			$maintext .= "<tr><td>{$fld['key']}				
				<td>
				<table>
				<tr><th>{%Display name}<td><input name=\"flds[{$fld['key']}][display]\" value=\"{$fld['display']}\" size=30>
				<tr><th>{%Description}<td><textarea name=\"flds[{$fld['key']}][desc]\" style='width: 600px; height: 25px;'>{$fld['desc']}</textarea>
				<tr><th>{%Values}<td><input name=\"flds[{$fld['key']}][values]\" value=\"{$fld['values']}\" size=70>
				<tr><th>{%Availability}<td><input name=\"flds[{$fld['key']}][status]\" value=\"{$fld['status']}\" size=20> (public/private)
				<tr><th>{%CQL Query}<td><input name=\"flds[{$fld['key']}][cql]\" value=\"{$fld['cql']}\" size=70>
				</table>
				<tr><td colspan=2><hr>
				";
		};
		$maintext .= "<tr><td>{%new}:<br><input name=\"flds[new][key]\" value=\"\" size=10>
				<td>
				<table>
				<tr><th>{%Display name}<td><input name=\"flds[new][display]\" value=\"\" size=30>
				<tr><th>{%Description}<td><textarea name=\"flds[new][desc]\" style='width: 600px; height: 25px;'></textarea>
				<tr><th>{%Values}<td><input name=\"flds[new][values]\" value=\"\" size=70>
				<tr><th>{%Availability}<td>
					<input type=radio name=\"flds[new][status]\" value=\"public\" checked> public
					<input type=radio name=\"flds[new][status]\" value=\"private\"> private
				<tr><th>{%Query}<td><input name=\"flds[new][cql]\" value=\"\" size=70>
				</table>
				<tr><td colspan=2><hr>
			";

		$maintext .= "</table><p><input type=submit value='{%Save}'> <a href='index.php?action=$action&file=$file'>{%Cancel}</a>
		</form>
		";

	} else if ( $act == "define" && $myfile ) {
	
		print_r($_POST);
		
		$extann['name'] = $_POST['name'];
		
		if ( $_POST['flds']['new']['key'] ) {
			$newkey = $_POST['flds']['new']['key'];
			print "<p>Adding node: $newkey";
			$newnode = xpathnode($extann, "/annotation/def/field[@key=\"$newkey\"]");
			foreach ( $_POST['flds']['new'] as $att => $val ) {
				$newnode[$att] = $val;
			};
		};
		
		file_put_contents($file, $extann->asXML());
		print "Changes have been saved
			<script language=Javascript>top.location='index.php?action=$action&file=$file&act=list';</script>"; exit;
	
	} else if ( $act == "cql" && $myfile ) { // Allow other users to search?
	
		foreach ( $extann->xpath("//def/field") as $i => $deffld) { 
			$values = "";
			foreach ( explode(",", $deffld['values']) as $fldopt ) {
				$values .= "<option value='$fldopt'>$fldopt</option>";
			};
			$optname = $deffld['key'].'';
			$optlist[$optname] = $values; 
			$opttit[$optname] = $deffld['short'] or $opttit[$optname] = $deffld['display'] or $opttit[$optname] = $deffld['key']; 
		};
	
		if ( $_POST['onlynew'] ) { $ons .= "checked"; };
		$cql = $_GET['cql'] or $cql = $_POST['cql'];
		
		if ( !$cql && $defcql ) {
			$cql = $defcql;
			$ons = "checked";
		};
				
		$maintext .= "			<form action='index.php?action=$action&file=$file&fields={$_GET['fields']}&act=cql' method=post>
			<p>CQL: <input name=cql size=70 value=\"".preg_replace("/\"/", "&quot;", $cql)."\">";
		if ($fld) $maintext .= "<input type=checkbox value=1 name=onlynew $ons> {%Only unclassified results}";
		$maintext .= "<input type=submit value={%Search}></form>";
		
		if ( $cql ) {

			$maintext .= "<form action='index.php?action=$action&act=save&file=$file' method=post>";
		
			if ( $ons && $fld ) { $cql .= " :: match.extann_$fld = \"\""; };
			
			$max = $_GET['max'] or $max = 50;
		
			$cmd = "/bin/echo '$cql; xidx 0 $max context:5' | /usr/local/bin/tt-cqp --extann=$file";
			$results = shell_exec($cmd); // print $cmd;
		
			$maintext .= "<div id=\"mtxt\"><table>
				<tr><td><th>Result";
			foreach ( $opts as $key ) {
				$maintext .= "<th>{%{$opttit[$key]}}";
			};
			foreach ( explode("\n", $results) as $i => $line ) {
				if ( $line == "" ) continue;
				// Correct xidx errors
				$line = str_replace("<</match>", "</match><", $line );
				$line = str_replace("<</resblk>", "</resblk>", $line );
				# Replace block-type elements by vertical bars
				$line = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $line);
				$line = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $line);
				$line = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $line); # non-standard section break
				$line = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $line);
				
				if ( preg_match("/resblk c_pos='(\d+)'/", $line, $matches ) ) $cpos = $matches[1];
				$maintext .= "<tr id=\"$i\"><td style='text-align: right; color: #cccccc;'>$cpos<td>$line";
				foreach ( $opts as $key ) {
					$maintext .= "<td><select name=vals[$cpos][$key]><option value=''>{%[select]}</option>$val</select>";
				};
			};
			$maintext .= "</table></div>
			<hr>
			<p><input type=submit value={%Save}> <a href='index.php?action=$action&file=$file&act=list&fields=$optx'>{%Cancel}</a>
			</form>";

			$withxml = true;
					
		};


	} else if ( $act == "list" || $act == "" ) {
		
		foreach ( $extann->xpath("//item") as $item ) {
			$poslist .= "{$item['c_pos']};";
		}
		
		if ( !$myfile ) $maintext .= "<p>Author: <b>{$extann['author']}</b></p>";

		$cmd = "/bin/echo '$poslist' | /usr/local/bin/tt-cqp --mode=pos2tab --cql='xidx context:5' --linesep=';'";
		$results = shell_exec($cmd);
	
		foreach ( explode("\n", $results) as $i => $line ) {
			if ( $line == "" ) continue;
				// Correct xidx errors
				$line = str_replace("<</match>", "</match><", $line );
				$line = str_replace("<</resblk>", "</resblk>", $line );
				# Replace block-type elements by vertical bars
				$line = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $line);
				$line = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $line);
				$line = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $line); # non-standard section break
				$line = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $line);

			$cpos = 0;
			if ( preg_match("/resblk c_pos='(\d+)'/", $line, $matches ) ) $cpos = $matches[1] + 0;
			$reslist[$cpos] = $line;
		};
		$maintext .= "<div id=\"mtxt\"><table>
			<tr><td><th>{%Result}";
		
		$json = "[[";
		foreach ( $opts as $key ) {
			$maintext .= "<th>{%{$opttit[$key]}}";
			$json .= "{'id':'$key', 'label':'{$opttit[$key]}'},";
		};
		$json .= " {'id':'count', 'label':'Count', 'type':'number'}],";
		foreach ( $extann->xpath("//item$itmrest") as $item ) {
			$cpos = $item['c_pos']."";
			$maintext .= "<tr id=\"$i\"><td style='text-align: right; color: #cccccc;'>$cpos<td>".$reslist[$cpos];
			foreach ( $opts as $key ) {
				$valstr = $item[$key]."";
				$maintext .= "<td>$valstr";
				$counts[$valstr]++;
			};
		};
		$maintext .= "</table></div><hr> <p>";
		if ( $myfile ) 
			$maintext .= "<a href='index.php?action=$action&file=$file&fields=$optx&act=cql'>{%Classify more}</a>
				&bull; 
				<a href='index.php?action=$action&file=$file&act=fields'>{%Define fields}</a>			
			";
		
		if ( count($optlist) > 1 ) {
		$maintext .= "
			&bull; 
			<a href='index.php?action=$action&file=$file&act=choose'>{%Choose fields}</a>			
			";
		};

		if ( count($opts) == 1 ) {
			foreach ( $counts as $key => $val ) {
				$json .= "['$key', $val],";
			};
			$json .= "]";
			$json = str_replace("'", "&#039;", $json);
			$maintext .= " &bull; <a onclick='document.visualize.submit();'>{%Visualize}</a>";
			$maintext .= "<form style='display: none;' action='index.php?action=visualize' method=post id=visualize name=visualize>
				<input type=hidden name=json value='$json'>
				</form>";
		};

		$withxml = true;
			
	};

	$maintext .= "<style>match tok { color: #aa2200; font-weight: bold; };</style>";

	if ( $withxml ) {
		// Make the XML in the results rollover
	
		$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
		$jsontrans = array2json($settings['transliteration']);

		// Build the view options	
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$formcol = $item['color'];
			# Only show forms that are not admin-only
			if ( $username || !$item['admin'] ) {	
				if ( $item['admin'] ) { $bgcol = " border: 2px dotted #992000; "; } else { $bgcol = ""; };
				$ikey = $item['inherit'];
				if ( preg_match("/ $key=/", $editxml) || $item['transliterate'] || ( $item['subtract'] && preg_match("/ $ikey=/", $editxml) ) || $key == "pform" ) { #  || $item['subtract'] 
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
			if ( preg_match("/ $key=/", $editxml) || 1==1 ) { // TODO: should this see if the tag occurs? 
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

		$maintext .= "	
			<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
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
	
			<script language=Javascript>$moreactions</script>";
	};

?>