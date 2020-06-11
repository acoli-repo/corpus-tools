<?php
	// Script to allow visualizing and editing
	// a dictionary that is based on the corpus
	// (c) Maarten Janssen, 2015
		
	$did = $_GET['did'] OR $did = $_SESSION['did'];
	if ( $did == "exit" ) { unset($_SESSION['did']); $did = ""; };
	if ( $settings['xdxf'] ) {
		if ( $did ) { 
			$dict = $settings['xdxf'][$did];
		} else if ( count($settings['xdxf']) == 1 ) {	
			$dict = array_shift($settings['xdxf']);
			$did = $dict['key'];
		};
		$dictfile = $dict['filename'];
	} else {
		 $dictfile = "dict.xml";
	};
	foreach ( $settings['xdxf'] as $key => $val ) {
		if ( !$dict[$key] ) $dict[$key] = $val;
	};
	if ( !$username && $dict['admin'] ) fatal("No access to this dictionary");
	$_SESSION['did'] = $did; // Store the selected dictionary in a session variable
	$filename = "Resources/$dictfile";
	$id = $_GET['id'];

	$arxpath = $dict['entry'] or $arxpath = "//lexicon/ar";
	$hwxpath = $dict['headword'] or $hwxpath = "k";
	$posxpath = $dict['pos'] or $posxpath = "gr";

	if ( $filename && !file_exists($filename) ) { 
		if ($username) {
			if ( !is_writable("Resources") ) fatal("Cannot write to Resources");
			$xdxftmp = "<xdxf>\n<meta_info>\n\t<title>{$dict['title']}</title>\n</meta_info>\n<lexicon>\n</lexicon>\n</xdxf>";
			file_put_contents($filename, $xdxftmp); 
		} else {
			fatal("Dictionary file not found: $filename"); 
		};
	}; 

	if ( $act == "newdict" ) {
		
		$maintext .= "<h1>Create new dictionary</h1>
			
			<style>.obl { color: #992200; }</style>	
			<p>Here you can provide the data for the new dictionary, where <span class='obl'>items in red are obligatory</span>.
				There data for the dictionary are stored in the settings - more data about the dictionary can later be added in the meta_info section.</p>

			<p><form method=post action='index.php?action=adminsettings&act=makeitem'>
			<input name=xpath value='/ttsettings/xdxf' type=hidden>
			<input name=goto value='index.php?action=$action&did={#key}' type=hidden>
			<table>
			<tr><th class='obl'>ID of the dictionary<td><input name='flds[key]' size=40 required><tr>
			<th class='obl'>Filename of the dictionary XML file<td><input name='flds[filename]' size=40 required>
			<tr><th class='obl'>Name of the dictionary<td><input name='flds[title]' size=40 required>
			<tr><th class=''>CSS file to use in this XDXF<td><input name='flds[css]' size=40><tr><th class=''>Search options<td><input name='flds[search]' size=40><tr><th class=''>CQP options<td><input name='flds[cqp]' size=40></table>
		<p><input type=submit value='Create item'>
		</form>
		
		";
	} else if ( !$dictfile ) {
		// Ask to choose a dictionary if there is more than one in the settings
		
		if ( $settings['xdxf'] ) {
			$maintext .= "<h1>{%Dictionary Reader}</h1>
				<p>{%Select a dictionary}";
			foreach ( $settings['xdxf'] as $key => $dict ) {
				if ( is_array($dict) && ($username || !$dict['admin']) ) $maintext .= "<p><a href='index.php?action=$action&did=$key'>{$dict['title']}</a>";
			};
		} else {
			fatal("no dictionary selected");
		};	

		if ( $username  ) {
			$maintext .= "<hr><p>
				<a href='index.php?action=$action&act=newdict'>add new dictionary</a>
				";
		};
		
	} else if ( $act == "save" && $id ) {
		check_login();

		$newrec = $_POST['rawxml']; 
		$file = file_get_contents($filename); 
		
		# Check if this new person is valid XML
		$test = simplexml_load_string($newrec, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$test ) fatal ( "XML Error in AR record. Please go back and make sure to input valid XML" );
		
		if ( $id == "new" ) {
			# Add the new record after the last entry
			$newrec = preg_replace ( "/<!--.*?-->/", "", $newrec );
			$newxml = preg_replace ( "/<\/lexicon>/smi", "\n".$newrec."\n</lexicon>", $file, 1 );
			$newk = $test->k[0]."";
			if ( !$newk ) { fatal ("No headword given"); };
		} else if ( $id == "meta_info" ) {
			# Add the new record after the last entry
			$newxml = $file;
			if ( !preg_match("/<meta_info/", $newxml) ) {
				$newxml = preg_replace ( "/(<xdxf[^>]*>/smi", "\1\n<meta_info></meta_info>", $newxml, 1 );
			};
			$newxml = preg_replace ( "/<meta_info.*?<\/meta_info>/smi", $newrec, $newxml );
		} else {
			# Overwrite the existing record
			$newxml = preg_replace ( "/<ar id=\"$id\".*?<\/ar>/smi", $newrec, $file );
		};
		
		$test = simplexml_load_string($newxml, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$test ) fatal ( "XML Error in XDXF - something went wrong here (record XML was valid though)" );
		
		# print $newxml; exit;
		# Save new XML to file
		file_put_contents($filename, $newxml);
		
		print "Changes save. Reloading.";
		if ( $id == "new" ) {
			print "<script language=Javascript>top.location='index.php?action=$action&act=renumber&toword=new'</script>"; 
		} else if ( $id == "meta_info" ) {
			print "<script language=Javascript>top.location='index.php?action=$action'</script>"; 
		} else {
			print "<script language=Javascript>top.location='index.php?action=$action&id=$id'</script>"; 
		};
		exit;
		
	} else if ( $act == "edit" ) {
		check_login();
	
		$xml = simplexml_load_file($filename, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( !$xml ) fatal("Error reading dictionary");
		$maintext .= "\n<h1>{$dict['title']}</h1>";
		if ( $id == "new" ) {
			$result = $xml->xpath("//ar[@id=\"new\"]"); 
			if ( !$result ) {
				if ( $dict['defentry'] ) {
					$editxml =  $dict['defentry'];
				} else if ( $settings['xdxf']['defentry'] ) {
					$editxml = $settings['xdxf']['defentry'];
				} else {
					$editxml = "<ar id=\"new\">\n\t<k></k>\n\t<def n=\"1\"><deftext></deftext></def>\n</ar>";
				};
			} else { 
				$entry = current($result);
				$editxml = $entry->asXML();
			};
			$maintext .= "<h2>New dictionary item</h2>";
		} else if ( $id == "meta_info" ) {
			$result = $xml->xpath("//meta_info"); 
			if ( !$result ) {
				$editxml = "<meta_info>\n\t<title></title>\n\t<full_title></full_title>\n\t<authors>\n\t\t<author></author>\n\t</authors>\n\t<publisher></publisher>\n\t<publishing_date></publishing_date>\n</meta_info>";
			} else { 
				$entry = current($result);
				$editxml = $entry->asXML();
			};
			$maintext .= "<h2>Edit Dictionary metadata</h2>";
		} else if ( $id ) {
			$result = $xml->xpath($arxpath."[@id=\"$id\"]"); 
			if ( !$result ) fatal("No such dictionary entry: $id");
			$entry = current($result);
			$editxml = $entry->asXML();
			$id = $entry['id'];
			$maintext .= "<h2>Edit dictionary item $id</h2>";
		};
		
		# Close empty tags
		$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

		if ( $id == "meta_info") {
			if ( file_exists("Resources/xdxfmetatags.xml") )  $tmp = "Resources/xdxfmetatags.xml";
			else if ( file_exists("$sharedfolder/Resources/xdxfmetatags.xml") )  $tmp = "$sharedfolder/Resources/xdxfmetatags.xml";
			else $tmp = "$ttroot/common/Resources/xdxfmetatags.xml";
		} else {
			if ( file_exists("Resources/xdxftags.xml") )  $tmp = "Resources/xdxftags.xml";
			else if ( file_exists("$sharedfolder/Resources/xdxftags.xml") )  $tmp = "$sharedfolder/Resources/xdxftags.xml";
			else $tmp = "$ttroot/common/Resources/xdxftags.xml";
		};
		$teilist = array2json(xmlflatten(simplexml_load_string(file_get_contents($tmp))));
		$acelturl = str_replace("ace.js", "ext-language_tools.js", $aceurl);
										
		$maintext .= "
			<div id=\"editor\" style='width: 100%; height: 300px;'>".htmlentities($editxml, ENT_QUOTES, "UTF-8")."</div>

			<form action=\"index.php?action=$action&act=save&id=$id\" id=frm name=frm method=post>
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"runsubmit();\"> 
			<input type=button value=Cancel onClick=\"window.open('index.php?action=$action', '_self');\"> 
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
		
	} else if ( $act == "advanced" && $dict['search'] ) {
	
		$dicttitle = $dict['title'];
		$maintext .= "\n<h1>$dicttitle</h1>";
		$maintext .= "<h2>Advanced Search</h2>
			<form action='index.php?action=$action' method=post>
			<input type=hidden name=advanced value=1>
			<table>";

		foreach ( $dict['search'] as $key => $val ) {
			$maintext .= "<tr>
				<input type=hidden name=kn[$key] value=\"{$val['kn']}\">
				<td>{%{$val['display']}}</td>
				<td>
					<select name=match[$key]><option value=\"contains\">{%contains}</option><option value=\"match\" $msel>{%matches}</option><option value=\"starts\" $msel>{%starts with}</option><option value=\"regex\" $msel>{%patterns}</option></select>
				</td>
				<td>
					<input name=query[$key] value=\"$wordquery\">
				</td>
				</tr>";		
		};
		$maintext .= "
			</table><p><input type=submit value=\"{%Search}\"></form>";
	
	} else if ( $act == "renumber" ) {
	
		# Each <ar> needs a unique ID
		check_login();
		$id = 1; $toword = $_GET['toword'] or $toword = "xxxx";
		$xml = simplexml_load_file($filename, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$result = $xml->xpath($arxpath); 
		foreach ( $result as $entry ) {
			if ( $entry['id'] == $toword ) $toid = "&id=ar-".$id;
			$entry['id'] = "ar-".$id;
			$id++;
		};
		file_put_contents($filename, $xml->asXML()); 
		header("location:index.php?action=$action$toid");
		exit;
		
	} else {
		
		$cssfile = $dict['css'] or $cssfile = "dict.css";
		if ( file_exists("Resources/$cssfile") ) {
			$maintext .= "\n<style type=\"text/css\"> @import url(\"Resources/$cssfile\"); </style>\n";
		} else {
			$css = file_get_contents("$ttroot/common/Resources/dict.css");
			$maintext .= "\n<style>\n$css\n</style>\n";
		};
		
		$file = file_get_contents($filename);

		$max = $_GET['max'] or $max = 100;
		$query = $_GET['query'];	
		if ( !preg_match('//u', $query) ) $query = utf8_encode($query); // Repair URL variables coming in as Latin 1

		// Place the command line query in the POST
		if ($query) {
			$_POST['query']['url'] = $query;
			$_POST['match']['url'] = $_GET['match'];
			$_POST['kn']['url'] = $_GET['kn'] or $_POST['url']['kn'] = "./k";
		};
	
		if ( preg_match("/lang_from=\"(.*?)\"/", $file, $matches) ) $langfrom = "{%lang-{$matches[1]}}";
		if ( preg_match("/lang_to=\"(.*?)\"/", $file, $matches) ) $langto = " - {%lang-{$matches[1]}}";
		
		$dicttitle = $dict['title'];
		$maintext .= "\n<h1>$dicttitle</h1>";
		if ( $_GET['match'] == "match" ) $msel = "selected";
		
		# Display the search box
		if ( !$_GET['kn'] ) $wordquery = $query;
		if ( $dict['search'] ) $advanced = "<a href='index.php?action=$action&act=advanced'>{%advanced}</a>";
		$maintext .= "<form action='index.php'>{%Word} 
			<select name=match>
				<option value=\"contains\">{%contains}</option>
				<!-- <option value=\"starts\">{%starts with}</option> -->
				<option value=\"match\" $msel>{%matches}</option>
			</select>
			<input name=query value=\"$wordquery\">
			<input type=hidden name=action value=\"$action\">
			<input type=Submit value=\"{%Search}\"> $advanced
			</form>
			<hr>
			<div id=\"dict\">";
						
		if ( !$query && !$_POST['query'] && !$id && !$debug && $act != "list" ) {

			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Dict XML Error - unable to read $filename"; exit; };
			$metas = current($xml->xpath("//meta_info"));

			if ( file_exists("Resources/dictInfo-$did.tpl") ) { 
				$header = file_get_contents("Resources/dictInfo-$did.tpl");
			} else if ( file_exists("Resources/xdxf.tpl") ) { 
				$header = file_get_contents("Resources/xdxf.tpl");
			} else {
				$header = "<table>
					<tr><th>{%Full Title}</th><td>{#//meta_info/full_title}</td>
					<tr><th>{%Edition}</th><td>{#//meta_info/dict_edition}</td>
					<tr><th>{%Author}</th><td>{#//meta_info/authors}</td>
					<tr><th>{%Publication date}</th><td>{#//meta_info/publishing_date}</td>
					<tr><th>{%Original}</th><td>{#//meta_info/dict_src_url}</td>
					<tr><th>{%Description}</th><td>{#//meta_info/description}</td>	</tr>
					</table>
					";
			};
			$maintext .= xpathrun($header, $xml);
			
		
		} else {

			if ( ( $dict['index'] && $_GET['index'] != "0" ) || $_GET['index'] ) {
				$indexlist = 1;
				$ixp = $dict['index'];
				if ( $ixp == "" || $ixp == "1" ) $xip = "{#//k}";
			};
			
			$xml = new DOMDocument();
			$xml->load($filename);
			if ( !$xml ) { print "Dict XML Error"; exit; };
			$xpath = new DOMXPath($xml);

			if ( $id ) $restr = "@id=\"$id\"";
			else {
				$restr = ""; $sep = "";
				foreach ( $_POST['query'] as $key => $tq ) {
					if ( !$tq ) continue; 
					$restr .= $sep; 
					$match = $_POST['match'][$key] or $match = "contains";
					$kn = $_POST['kn'][$key] or $kn = $hwxpath;
					if ( $match == "match" ) {
						$restr .= $kn."[.='$tq']"; // Match
					} else if ( $match == "contains" ) {
						$restr .= ".//".$kn."[contains(.,'$tq')]"; // Contains
					} else if ( $match == "starts" ) {
						# TODO: this only shows the first item, make this actually work
						$restr .= $kn."[starts-with(.,'$tq')]"; // Starts with
					} else if ( $match == "regex" ) { // Regexp - does not work
						$xpath->registerNamespace("php", "http://php.net/xpath");
						$xpath->registerPHPFunctions();
						$restr .= $kn."[php:functionString(\"preg_match\",\"/$tq/\",.)]";
					};
					if ( $restr ) $sep = " and ";
				};
			}; 
						
			$xquery = $arxpath;
			if ( $restr ) $xquery .= "[$restr]";
		
			if ( $debug ) $maintext .= "<p>XPath query: $xquery";
			$result = $xpath->query($xquery); 
			if ( $result ) {
				$count = $result->length;
				$start = $_GET['start'] or $start = 0; 
				$starttxt = $start + 1;
				$end = min( $start+$max, $count );
				if ( $count > $max ) {
					$showing = " - {%showing} $starttxt - $end";
					$minurl = preg_replace("/&start=\d+/", "", $_SERVER['REQUEST_URI']);
					// We need to forward the post variables in order to be able to scroll advanced searches
					if ( !$_POST['advanced'] ) {
						if ( $start > 0 ) { 
							$url = $minurl."&start=".max($start-$max, 0);
							$showing .= " - <a href='$url'>{%previous}</a>"; 
						};
						if ( $end < $count ) { 
							$url = $minurl."&start=".($end);
							$showing .= " - <a href='$url'>{%next}</a>"; 
						};
					};
				};
				if ( !$id ) $maintext .= "<p><i>$count {%results}</i> $showing</p><hr>\n";
				if ( $dict['cqp'] && $count > 1 && $linkdict ) $maintext .= "<p>{%Click on an entry to see corpus examples}</p><hr>"; 
				$sortarray = array();
				for ( $i=$start; $i<$end; $i++ ) {
					$entry = $result->item($i);
					$entryxml = $entry->ownerDocument->saveXML($entry);
					$arid = $entry->getAttribute("id");
					$tmp = $xpath->query($hwxpath, $entry); $ark = $tmp->item(0)->textContent;
					if ( $dict['cqp'] && $arid && $linkdict ) $entryxml = "<div onClick=\"window.open('index.php?action=$action&act=view&id=$arid', '_self')\">".$entryxml."</div>";
					else if ( $username && $arid ) $entryxml = " <a onClick=\"window.open('index.php?action=$action&act=edit&id=$arid', '_self')\">edit</a> ".$entryxml;
					if ( $ixp && $entryxml && !$id && $count > 1 ) {
						$entrytext = xpathrun($ixp, simplexml_import_dom($entry));
						$entryxml = "<a href='index.php?action=$action&id=$arid'>$entrytext</a>";
					};
					array_push ( $sortarray, "<div k=\"$ark\">".$entryxml."</div>" );
					if ( $cnt++ > $max ) break;
					if ( $count == 1 ) $id = $arid; 
				};
				# TODO: sort seems to only distroy the right order
				if ( $settings['xdxf']['sort'] ) {
					natcasesort($sortarray);
				};
				$maintext .= join ( "\n", $sortarray );
				if ( !$id ) $maintext .= "<hr><p><i>$count {%results}</i> $showing</p>\n";
			} else $maintext .= "<i>{%No results found}</i>";
			
			$maintext .= "<script language=Javascript>
				function makekref () {
					var its = document.getElementById('dict').getElementsByTagName(\"kref\");
					for ( var a = 0; a<its.length; a++ ) {
						var it = its[a];
						if ( typeof(it) != 'object' ) { continue; };
						// Make this node clickable
						it.onclick = function() { 
							var linktarget;
							if ( this.getAttribute('ref') ) {
								linktarget = this.getAttribute('ref');
							} else{
								linktarget = this.textContent;
							};
							window.open('index.php?action=xdxf&match=match&query='+linktarget, '_top'); 
						};
					};
				};
				makekref();
				</script>";
		
			if ( $id ) {
				// if ( $username ) $maintext .= "<hr><p><a href='index.php?action=$action&act=edit&id=$id'>edit</a> this record";
				if ( $dict['cqp'] ) {
					# If so asked, get corpus results for this entry
				
					$tmp = $xpath->query($posxpath, $entry); if ($tmp) $arp = $tmp->item(0)->textContent;
				
					include ("$ttroot/common/Sources/cwcqp.php");
					$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
					$cqp = new CQP();
					$cqp->exec($cqpcorpus); // Select the corpus
					$cqp->exec("set PrettyPrint off");

					# View all the documents of a given location
					$location = $_GET['location'] or $location = $_GET['lat'].' '.$_GET['lng'];
					$place = $_GET['place'];

					$postag = $dict['cqp']['pos'];
					$lemtag = $dict['cqp']['lemma'] or $lemtag = "lemma";

					if ( $postag ) $posq = "& $postag=\"$arp.*\"";
					
					// HTML Decode if needed
					$ark = html_entity_decode($ark);

					$cqpquery = "Matches = [$lemtag=\"$ark\" $posq]";
					$cqp->exec($cqpquery); 
					if ($debug) $maintext .= "<p>CQL: $cqpquery";
					
					$size = $cqp->exec("size Matches");

					if ( $size > 0 ) {
						$maintext .= "<hr><h2>corpus examples</h2>
						
						<div id=mtxt2>
						<table>";
					
						$cqpquery = "tabulate Matches 0 100 match text_id, match id, match[-8]..matchend[8] word";
						$results = $cqp->exec($cqpquery); 
						if ($debug) $maintext .= "<p>CQL results: $results";
						
						foreach ( explode ( "\n", $results ) as $line ) {	
							list ( $fileid, $tid, $context ) = explode ( "\t", $line );
							if ( preg_match ( "/([^\/]+)\.xml/", $fileid, $matches ) ) {	
								$cid = $matches[1];
		
								$maintext .= "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$tid'>$cid</a><td>$context</td>";
							};
						};
						$maintext .= "</table></div>";
					};
				};
			};
		
		};
		
		if ( $username  ) {
			$maintext .= "<hr><div class='adminpart'><span title='{$dict['filename']}'>$did</span> 
					&bull; 
				<a href='index.php?action=$action&did=exit'>switch dictionary</a>
					&bull;
				<a href='index.php?action=$action&did=$did&act=edit&id=new'>add new entry</a>
					&bull; 
				<a href='index.php?action=$action&did=$did&act=edit&id=meta_info'>edit metadata</a>
					&bull; 
				<a href='index.php?action=adminedit&id={$dict['filename']}'>edit raw XML</a>
					&bull; 
				<a href='index.php?action=$action&act=list'>list entries</a>
				</div>
				";
		} else if ( count($settings['xdxf']) > 1 ) {
			$maintext .= "<hr>
				<p>
				<a href='index.php?action=$action&did=exit'>switch dictionary</a>
					&bull; 
				<a href='index.php?action=$action&act=list'>list entries</a>
				";
		};
			
	};

?>