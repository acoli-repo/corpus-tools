<?php
	$annotation = $_GET['annotation'] or $annotation = $_SESSION['annotation'];
	$colorlist = array ( "#ff9999", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#ff9999", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#ff9999", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66", "#99ff99", "#9999ff", "#66ffff", "#ff66ff", "#ffff66");
	
	if ( !$annotation ) {
		if ( count(getset('annotations', array())) == 1 ) {
			$annotation = join(";", array_keys(getset('annotations', array())));
		} else if ( !$act ) {
			// Redirect to annotation selection
			$act = "select";
		};
	};
	check_folder("Annotations");

	if ( $_GET['cid'] ) {
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = preg_replace("/.*\//", "", $ttxml->fileid);

		# Make a clean version of the text
		$cleaned = $ttxml->rawtext;
		$cleaned = preg_replace("/.*<text[^>]*>/smi", "", $cleaned);
		$cleaned = preg_replace("/<\/text>.*/smi", "", $cleaned);
		$cleaned = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $cleaned );
		if ( !$keepxml ) {
			$cleaned = preg_replace("/<(?!\/?(d?tok|p))[^>]+>/", "", $cleaned);
			$cleaned = preg_replace("/<\/tok>(\s+)/", " </tok>", $cleaned);
		};

		$maintext .= "<h2 title=\"$filename\">".$ttxml->title()."</h2>"; 
		if ($annotation) $maintext .= "<h1>{%".getset("annotations/$annotation/display")."}</h1>";

	};
	
	if ( $annotation && $user['permissions'] == "admin" && !file_exists("Annotations/{$annotation}_def.xml") ) { 
		if ( $act == "savedef" ) {
		} else {
			print "Shifting from : $act ".$_GET['act'].$_POST['act'];
			$act = "define"; 
		};
	};

	# Read the annotation definition
	if ( $annotation && file_exists("Annotations/{$annotation}_def.xml") ) {
	
		$andef = simplexml_load_file("Annotations/{$annotation}_def.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( ( getset("annotations/$annotation") == '' || getset("annotations/$annotation/admin") != '' ) && !$username )  {
			fatal ( "Annotation data for <i>{$andef['name']}</i> are not publicly accessible" );
		};
		if ( !$andef ) {
			if ( $username ) 
				fatal ( "Error reading annotation definition file Annotations/{$annotation}_def.xml" );
			else
				fatal ( "Annotation data for <i>{$andef['name']}</i> cannot be read" );
		};
		$headertxt = current($andef->xpath("desc")); 
		if ( $headertxt ) $headertxt .= "<hr>";

		# Read the actual annotation for this file (if any)
		$filename = "Annotations/{$annotation}_".$fileid;
		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		
		$result = $andef->xpath("//interp"); 
		if ( $andef['keepxml'] || getset('annotation/keepxml') != '' ) { $keepxml = 1; } else { $keepxml = 0; };
		$moreactions .= "var keepxml = $keepxml; var interp = []; var codetrans = [];\n"; 
		foreach ( $result as $tmp ) { 
			$tagset[$tmp['key'].''] = $tmp;
			if ( $tmp['type'] != "long" ) {
				if ( $tmp['key'] == $dist ) $sel = "selected"; else $sel = "";
				$distlist .= "<option value='{$tmp['key']}' $sel>{$tmp['long']}</option>"; 
			};
			 $i = 0;
			if ( $tmp->xpath("option") ) {
				$optionlist = "";
				foreach ( $tmp->xpath("option") as $child ) {
					$optionlist .= "<option value=\"{$child['value']}\">{$child}</option>";
					if ( $child['display'] ) $tagnames[$tmp['key'].'-'.$child['value']] = $child['display']."";
				};
				$pulldowns[$tmp['key'].''] = $optionlist; 
				$annotationrows .= "<tr><th>{$tmp['display']}<td><select name=news[$i][{$tmp['key']}]><option value=''>[{%select}]</option>$optionlist</select>";
			} else {
				$annotationrows .= "<tr><th>{$tmp['display']}<td><input name=news[$i][{$tmp['key']}] style='width: 100%'/>";
			};
			if ( $tmp['colored'] > 0) {
				if ( $tmp['display'] || $tmp['long'] ) {
					$marktit = $tmp['long'] or $marktit = $tmp['display'];
					$marktit = "<h3>$marktit</h3>";
				};
				if ( $tmp->xpath("./option") ) {
					foreach ( $tmp->children() as $tmp2 ) {
						$color = $tmp2['color']."";
						# Check if this one exists
						if ( !$anxml->xpath("//span[@{$tmp['key']}=\"{$tmp2['value']}\"]") ) { continue; };
						if ( $color == "" ) $color = array_shift($colorlist);
						$markfeat = $tmp['key'].""; 
						$markcolor[$tmp2["value"].""] = $color; 
						$spantit = ""; 
						if ( $tmp2['display'] ) {
							$spantit = "title=\"{%{$tmp2['display']}}\"";
							$moreactions .= "codetrans['{$tmp2['value']}'] = '{%{$tmp2['display']}}'; ";
						};
						$markbuttons .= "<span $spantit style=\"border: 1px solid black; padding: 2px; line-height: 35px; background-color: $color;\" onmouseover=\"markall('$markfeat', '{$tmp2['value']}');\" onmouseout=\"unmarkall('$markfeat', '{$tmp2['value']}');\">{$tmp2['value']}</span> ";
					};
				} else {
					# Take the existing values if no values were pre-defined
					foreach ( $anxml->xpath("//span") as $span ) {
						$markfeat = $tmp['key']."";
						$markval = $span[$markfeat].""; 
						if ( !$markcolor[$markval] ) {
							$color = array_shift($colorlist);
							$markcolor[$markval] = $color;
							$markbuttons .= "<span $spantit style=\"border: 1px solid black; padding: 2px; line-height: 35px; background-color: $color;\" onmouseover=\"markall('$markfeat', '{$tmp2['value']}');\" onmouseout=\"unmarkall('$markfeat', '$markval');\">$markval</span> ";
						};
					};
				};
				if ( $markbuttons ) $annotations = "$marktit$markbuttons<hr>";
			};
			$intdisp = $tmp['long'] or $intdisp = $tmp['display'];
			$moreactions .= "interp['{$tmp['key']}'] = '{%$intdisp}'; ";
		};
		$moreactions .= "var markfeat = '$markfeat'; ";
	};
	

	if ( $act == "save" && $fileid ) {
		
		check_login();
		
		foreach ( $_POST['toks'] as $key => $val ) {
			$sep = ""; $tt = "";
			foreach ( $val as $key2 => $val2 ) {
				$tt .= $sep."#".$key2; 
				$tmp = $ttxml->xpath("//tok[@id='$key2']"); $tw = $tmp[0]['form'] or $tw = $tmp[0]."";
				$tws .= $sep.$tw; 
				$sep = " ";
			};
			$result = $anxml->xpath("//span[@id=\"$key\"]"); $segnode = $result[0];
			if ( $tt != $segnode['corresp'] ) {
				$_POST['sval'][$key]['corresp'] = $tt;
				$segnode[0] = $tws;
			};
		};
		
		foreach ( $_POST['sval'] as $key => $val ) {
			$result = $anxml->xpath("//span[@id=\"$key\"]"); $segnode = $result[0];
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = $fv;
			};
		};

		foreach ( $_POST['news'] as $key => $val ) {
			if ( $val['corresp'] == "" ) { continue; }
			$segnode = $anxml->addChild('span', trim($val['text'])); unset($val['text']);
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = trim($fv);
			};
		};
		
		# Renumber the segments
		$result2 = $anxml->xpath("//span"); $sc = 1;
		foreach ( $result2 as $segnode ) {
			$segnode['id'] = "an-".$sc++;
		};
		file_put_contents($filename, $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 
	
	} else if ( $act == "tagset" ) {


		$maintext .= "<h1>{%{$andef['name']}}</h1>";
		$description = $andef->desc;
		$maintext .= "<p>$description</p>";

		foreach ( $andef->interp as $interp ) {
			
			if ( $interp['long'] ) $long = " ({$interp['long']})";
			$maintext .= "<h2>{$interp['display']}$long</h2>";
			$maintext .= "<p>".$interp->desc."</p>";
		
			if ( $interp->option ) {
				$maintext .= "<table>";
				foreach ( $interp->option as $option ) {
					$maintext .= "<tr><th>{$option['value']}<td>$option";
				};
				$maintext .= "</table>";
			} else {
				$maintext .= "<p><i>Free text field</i>";
			};
		
		};
		$maintext .= "<hr><p><a href='index.php?action=$action&annotation=$annotation'>To annotation list</a>";

	} else if ( $fileid && $act == "delete" ) {

		# TODO: This only works with spanGrp, should we make that customizable?
		check_login();
		
		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		
		$sid = $_GET['sid'];
		$result = $anxml->xpath("//span[@id=\"$sid\"]"); 
		$segnode = $result[0];	
		if ( !$segnode ) fatal("No such segment: $sid");
		unset($segnode[0][0]);

		# Renumber the segments
		$result = $anxml->xpath("//file");  $fc = 0;
		foreach ( $result as $fnode ) {
			$fc++; $sc = 1;
			$result2 = $fnode->xpath("./segment");
			foreach ( $result2 as $segnode ) {
				$segnode['id'] = $fc."-".$sc++;
			};
		};
		
		file_put_contents($filename, $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 


	} else if ( $fileid && $act == "vert" ) {

		check_login();

		$maintext .= "<h2>Verticalized view</h2>";

		$typeatt = "type";
		foreach ( $andef->xpath("//interp") as $grp ) {
			if ( $grp['colored'] ) $typeatt = $grp['key']."";
		};
		
		$edges = array(); $spans = array();
		foreach ( $anxml->xpath("//span") as $span ) {
			$aid = $span['id']."";
			$spans[$aid] = $span;
			$toklist = explode ( " ", str_replace("#", "", $span['corresp'] ));
			$t1 = $toklist[0]."";
			$t2 = end($toklist)."";
			$type = $span[$typeatt] or $type = $annotation or $type = "ann";
			$type = preg_replace("/[^a-z0-9]/", "", strtolower($type)); 
			if ( $type == "" ) $type = "ann";
			if ( preg_match("/^[0-9]/", $type) ) $type = "ann$type";
			if ( !$edges[$t1] ) {
				$edges[$t1] = array();
				$edges[$t1]['start'] = array();
				$edges[$t1]['end']  = array();
			};
			if ( !$edges[$t2] ) {
				$edges[$t2] = array();
				$edges[$t2]['start'] = array();
				$edges[$t2]['end']  = array();
			};
			array_push($edges[$t1]['start'], array($type, $aid, $t1) );
			array_push($edges[$t2]['end'], array($type, $aid, $t2) );
		}; 

		$toklist = $ttxml->toklist($ttxml->xml);

		$attlist = getset("cqp/pattributes", array());
		$sattlist = array();
		foreach ( $attlist as $key => $val ) {
			if ( $key == "id" || $key == "form" ) continue;
			$sattlist[$key] = "form";
			if ( getset("xmlfile/pattributes/tags/$key") ) $sattlist[$key] = "tag";
			else if ( getset("xmlfile/pattributes/forms/$key/inherit") ) $sattlist[$key] = "inherit";
		};
		
		$nl = "\n";
		if ( $_GET['style'] == "inline" ) $nl = "";
		$maintext .= "
			<p><button id='boton2' onclick=\"download('#div-text')\">Download</button> - click on a line to edit the content
			<hr>
			<style>
			pre .token { color: #888888; }
			pre .tag { font-weight: bold; }
			</style>";
		if ( $_GET['style'] == "inline" ) 
			$maintext .= "<div id='mtxt'>&lt;text id=\"$ttxml->xmlid\" nodeatt=\"$typeatt\"&gt;";
		else
			$maintext .= "<pre id='div-text'>&lt;text id=\"$ttxml->xmlid\" nodeatt=\"$typeatt\"&gt;\n";
		foreach ( $toklist as $tok ) {
			$form = $tok['form'] or $form = $tok."";
			$tid = $tok['id']."";
			foreach ( $edges[$tid]['start'] as $tmp ) {	
				$atts = ""; $aid = $tmp[1]."";
				foreach ( $spans[$aid]->attributes() as $key => $val ) {
					if ( $key == "corresp" ) continue;
					$atts .= " $key=\"$val\"";
				};
				$maintext .= "<span class='tag' onclick=\"window.open('index.php?action=$action&act=redit&cid=$ttxml->fileid&sid=$aid', 'edit');\">&lt;{$tmp[0]}$atts&gt;</span>$nl";
			};
			if ( $_GET['style'] == "inline" ) { 
				$maintext .= "<span class='token' onclick=\"window.open('index.php?action=tokedit&cid=$ttxml->fileid&tid=$tid', 'edit');\">$form</span> ";
			} else {
				$maintext .= "<span class='token' onclick=\"window.open('index.php?action=tokedit&cid=$ttxml->fileid&tid=$tid', 'edit');\">$form\t$tid";
				foreach ( $sattlist as $key => $val ) {
					if ( $val == "inherit" ) $aval = forminherit($tok, $key);
					else $aval = $tok[$key];
					$maintext .= "\t$aval";
				}
				$maintext .= "</span>$nl";
			};
			foreach ( $edges[$tid]['end'] as $tmp ) {
				$maintext .= "<span  class='tag'>&lt;/{$tmp[0]}&gt;&lt;--id=\"{$tmp[1]}\"--&gt;</span>$nl";
			};
		};
		$maintext .= "&lt;/text&gt;</pre>
		<hr><button id='boton1' onclick=\"download('#div-text')\">Download</button> <a href='index.php?action=$action&cid=$ttxml->fileid&annotation=$annotation'>back</a>
			<script>
			function copiar(filename, text) {
			  var element = document.createElement('a');
			  element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(text));
			  element.setAttribute('download', filename);

			  element.style.display = 'none';
			  document.body.appendChild(element);

			  element.click();

			  document.body.removeChild(element);
			}

			// Start file download.
			function download(id) {
			  // Generate download of .txt file with some content
			  var text = document.querySelector(id);
			  var filename = '$ttxml->xmlid.$annotation.vrt';

			  copiar(filename, text.textContent);
			};
		 </script>";
		if ( $_GET['style'] == "inline" ) {
			$newstyle = ""; $newtxt = "VRT";
		} else { $newstyle = "inline"; $newtxt = "inline"; };
		$maintext .= " &bull; <a href='index.php?action=$action&act=$act&cid=$ttxml->fileid&annotation=$annotation&style=$newstyle'>switch to $newtxt view</a>";


	} else if ( $fileid && $act == "redit" ) {

		check_login();

		$sid = $_GET['sid'];

		if ( !$sid ) fatal("No span selected");	
		$result = $anxml->xpath("//span[@id=\"$sid\"]"); 
		$segnode = $result[0];	
		if ( !$segnode ) fatal("No such span: $sid");	
		$result = $segnode->xpath(".."); 
		$filenode = $result[0];		
		$toklist = explode ( " ", str_replace("#", "", $segnode['corresp'] ));
		$tks = max ( str_replace("w-", "", $toklist[0]) - 5, 1 );
		$tkf = str_replace("w-", "", $toklist[count($toklist)-1]) + 5;
		$toklisttxt = "<table>";
		for ( $i = $tks; $i <= $tkf; $i++ ) { 
			$tokid = "w-$i";
			if ( in_array($tokid, $toklist) ) $checked = "checked"; else $checked = "";
			$tmp = $ttxml->xpath("//tok[@id=\"$tokid\"]"); $ttok = $tmp[0];
			if ($ttok) $toklisttxt .= "<tr><td><input type=checkbox name=toks[$sid][$tokid] $checked value=1><td>$tokid<td>$ttok<td style='color: #888888;'>".htmlentities($ttok->asXML(), ENT_QUOTES, 'UTF-8');
		};
		$toklisttxt .= "</table>";
		
			$maintext .= "<h1>{%{$andef['name']}}</h1>
				<h2>Edit $sid</h2>
				
				<form action='index.php?action=$action&act=save&annotation=$annotation&cid=$fileid' method=post>
				<table>
				<tr><td><td><th>File<td>{$filenode['id']}
				<tr><td><td><th>Token list<td>$toklisttxt
				<tr><td><td><th>Word value<td>{$segnode}";
			foreach ( $tagset as $key => $val ) {
				$maintext .= "<tr><th>$key
					<th>{$tagset[$key]['display']}
					<th>{$tagset[$key]['long']}";
				$tmp = $andef->xpath("//interp[@key='$key']/option");
				$sval = $segnode[$key]; $odone = 0;
				if ( $tmp ) {	
					$optionlist = ""; $odone = 0;
					foreach ( $tmp as $option ) {
						$oval = $option['value'].""; 
						if ( $sval == $oval ) { $selected = "selected"; $odone = 1; } else $selected = ""; 
						if ( $oval == $option || !$option) $otxt = $oval; 
						else $otxt = "$oval: $option";
						$optionlist .= "<option value=\"$oval\" $selected>$otxt</option>";
					};
					if ( $sval && !$odone ) $optionlist = "<option value=\"$sval\" selected><i>$sval</i>: (invalid value)</option>$optionlist";
					$maintext .= "<td><select name='sval[$sid][$key]'><option value=\"\">[select]</option> $optionlist</select>$val</td>";
				} else {
					$maintext .= "<td><input name='sval[$sid][$key]' value='{$segnode[$key]}'></td>";
				};
			};
			$maintext .= "</table>
				<p><input type=submit value=Save> 
					<a href='index.php?action=$action&act=&annotation=$annotation&cid=$fileid'>cancel</a>
				</form>
				<p><a href='index.php?action=$action&act=delete&annotation=$annotation&sid=$sid&cid=$fileid'>delete segment</a>
				";
			
			$ctxt = $ttxml->context($toklist[0]);
			if ( $ctxt ) {	
				foreach ( $toklist as $wk ) $hllist .= "highlight('$wk', '#ffcccc');";
				$maintext .= "<hr><h2>Context</h2>$ctxt
					<script language=Javascript src=\"$jsurl/tokedit.js\"></script>
					<script language=Javascript>$hllist</script>
					";
			}
		
	} else if ( $act == "distribution" ) {
		
			$query = $_GET['query'] or $query = "//segment";
			$dist = $_GET['dist'];

			
			$maintext .= "<h1>{%{$andef['name']}}</h1>";
			$maintext .= "<h2>{%Distribution}</h2>
			
				<form action='index.php'>
					<input type=hidden name=action value='$action'>
					<input type=hidden name=act value='$act'>
					<input type=hidden name=annotation value='$annotation'>
					<p>{%Query}: <input name=query value='$query' size=80>
					<p>{%Distribute by}: <select name=dist>$distlist</select>
					<p><input type=submit value=Calculate>
				</form>
				";
			
			if ( $dist ) {
				$disttit = $tagset[$dist]['display']; # $disttit = $tagset[$dist]['long'] or 
				$maintext .= "<hr><table><tr><th>$disttit<th>{%Count}<th>{%Percentage}";
				$result = $anxml->xpath($query);  $tot = count($result);
				foreach ( $result as $tmp ) { 
					$dval = $tmp[$dist].'' or $dval = "(none)";
					$dd[$dval] = $dd[$dval] + 1;
				};
				arsort($dd);
				foreach ( $dd as $key => $val ) {
					$maintext .= "<tr><td>$key<td align=right>$val<td align=right>".sprintf("%0.2f", ($val/$tot)*100)."%";
				}; 
				$maintext .= "</table>";
			};
		$maintext .= "<hr>
			<p><a href='index.php?action=$action&annotation=$annotation'>{%to annotation list}</a>";
	
	} else if ( $act == "list"  ) { #  || $act == "edit"

		$xpath = "//span$squery";
		$result = $anxml->xpath($xpath); $sortrows = array();
		foreach ( $result as $segment ) {
			$sid = $segment['id'];
			$tokenlist = str_replace("#", "", $segment['corresp']);
			$newrow = "<tr>";
			if ($username) 
				$newrow .= "<td><a href='index.php?action=$action&annotation=$annotation&act=redit&sid=$sid&cid=$fileid'>".$segment."</a>";
			else 
				$newrow .= "<td>".$segment;
			foreach ( $tagset as $key => $val ) {
				$named = $tagnames[$key.'-'.$segment[$key]];
				if ( $act == "edit" ) {
					if ( $pulldowns[$key] ) 
						$newrow .= "<td><select name='sval[$sid][$key]'>{$pulldowns[$key]}</select></td>";
					else
						$newrow .= "<td><input name='sval[$sid][$key]' value='{$segment[$key]}'></td>";
				} else if ( $named ) {
					$newrow .= "<td>{%$named} ({$segment[$key]})</td>";
				} else {
					$newrow .= "<td>{$segment[$key]}</td>";
				};
			};
			$newrow .= "</tr>"; array_push($sortrows, $newrow);
		};
		natsort($sortrows); $maintext .= "<table>".join("\n", $sortrows)."</table>";
		
		if ( $act == "edit" ) {
			check_login();
			$maintext .= "<hr><p><a href='index.php?action=$action&annotation=$annotation&act=list&cid=$fileid'>{%back to list}</a>";
		} else if ( !$username ) {
			$maintext .= "<hr><p>
				<a href='index.php?action=$action&annotation=$annotation&act=&&cid=$fileid'>{%text view}</a>
				";
		} else {
			$maintext .= "<hr><p>
				<a href='index.php?action=$action&annotation=$annotation&act=edit&&cid=$fileid'>edit list</a>
				&bull;
				<a href='index.php?action=$action&annotation=$annotation&act=&&cid=$fileid'>{%text view}</a>
				";
			if ( $user['permissions'] == "admin" ) $maintext .= "
							&bull;
				<a href='index.php?action=$action&act=define&annotation=$annotation' class=adminpart>{%edit definitions}</a>
				";

		};
		
	} else if ( $act == "savedef" ) {

		check_login();
		
		# Save the definition file
		$annotation = $_GET['annotation']; # Hard code in case we switched in the meantime

		if ( $andef ) 
			$newandef = $andef;
		else
			$newandef = simplexml_load_string("<interpGrp id=\"{$annotation}\" name=\"{$_POST['name']}\" keepxml=\"1\">
				<desc>{$_POST['desc']}</desc>
				</interpGrp>");
		foreach ( $_POST['grp'] as $key => $grpdef ) {
			if ( $key."" == "new" || !$grpdef['key'] ) continue; # If we are generating a new def file, we do not handle fields yet
			$grpfld = $newandef->addChild("interp");
			foreach ( $grpdef as $key2 => $val ) {
				$grpfld[$key2] = $val;
			};
			foreach ( $grpdef['values'] as $key2 => $val2 ) {
				if ( $key2."" == "new" || !$val2['value'] ) continue;
				$valuesfld = $grpfld->addChild("option");
				foreach ( $val2 as $key3 => $val3 ) {
					$valuesfld[$key3] = $val3;
				};
			};
		};

		$maintext .= showxml($newandef);
		$dom = new DOMDocument("1.0");
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($newandef->asXML());
		file_put_contents("Annotations/{$annotation}_def.xml", $dom->saveXML());
		$maintext .= "<hr><p>New definitions have been saved - reloading
			<script>top.location = 'index.php?action=$action&annotation=$annotation';</script>";
		
	
	} else if ( $act == "define" ) {
	
		check_login();
		$sodispl = getset("annotations/$annotation/display");
		$maintext .= "<h2>Define a stand-off annotation</h2>
			<h1>$sodispl</h1>";

		if ( !$andef ) {
			$andef = simplexml_load_string("<interpGrp id=\"$annotation\" name=\"$sodispl\" keepxml=\"1\">
	<desc/>
	</interpGrp>");
			$newxml = 1;
		};

		# Edit the definitions
		$maintext .= "<form action='index.php?action=$action&act=savedef&annotation=$annotation' method=post>
			<table>
			<tr><th>ID<td>$annotation = $sodispl
			<tr><th>Name<td><input size=80 name=name value=\"{$andef['name']}\">
			<tr><th>Description<td><textarea style='width:100%; height: 40px;' name=desc>".htmlentities($andef->desc)."</textarea>
			</table>
			<h2>Fields</h2>
			<table id='fieldtable'>
			<tr><th>Field ID<th>Display name<th>Long name<th>Col<th>Fixed values (Value/Display)";
		
		$tmp = $andef->xpath("./interp");
		foreach ( $tmp as $key => $item ) {
			$maintext .= "<tr id='antable[{$key}]'>
							<td><input name='grp[{$key}][key]' size=20 value=\"{$item['key']}\">
							<td><input name='grp[{$key}][display]' size=30 value=\"{$item['display']}\">
							<td><input name='grp[{$key}][long]' size=30 value=\"{$item['long']}\">
							<td><input name='grp[{$key}][colored]' size=2 value=\"{$item['colored']}\">
							<td><table id='valtable[{$key}]'>";
				$tmp2 = $item->xpath("./option");
				foreach ( $tmp2 as $key2 => $val2 ) {
					$maintext .= "<tr>
						<td><input name='grp[{$key}][values][$key2][value]' size=30 value=\"{$val2['value']}\">
						<td><input name='grp[{$key}][values][$key2][display]' size=20 value=\"{$val2['display']}\">
						";
				};
				$maintext .= "<tr style='display: none;'  id='valrow[{$key}][new]'>
					<td><input name='grp[{$key}][values][new2][value]' size=30 value=\"\">
					<td><input name='grp[{$key}][values][new2][display]' size=20 value=\"\">
					";
			$maintext .= "</table>";
			if ( count($tmp2) ) { 
				$maintext .= "<span onclick='addvalue(this)';' class='button'>add value</span>";
			} else { 
				$maintext .= "<span onclick='makevalues(this)';' class='button'>make fixed values</span>";
			};
		};
		$key = count($tmp);
			$maintext .= "<tr style='display: none;'  id='antable[new]'>
							<td><input name='grp[new][key]' size=20 value=\"\">
							<td><input name='grp[new][display]' size=30 value=\"\">
							<td><input name='grp[new][long]' size=30 value=\"\">
							<td><input name='grp[new][colored]' size=2 value=\"\">
							<td><table id='valtable[new]'>
								<tr style='display: none;'  id='valrow[{$key}][new]'>
									<td><input name='grp[new][values][new2][value]' size=30 value=\"\">
									<td><input name='grp[new][values][new2][display]' size=20 value=\"\">							</table><span onclick='makevalues(this)';' class='button'>make fixed values</span>";			
		$maintext .= "</table>
			<span onclick='addfield()' class='button'>add field</span>
			<script>
				var fieldtable = document.getElementById('fieldtable');
				function addfield() { 
					var lastnum = fieldtable.rows.length - 1;
					var lastrow = fieldtable.rows[lastnum];
					var newrow = fieldtable.insertRow(fieldtable.rows.length-1);
					var newhtml = lastrow.innerHTML.replaceAll('[new]', '['+(lastnum-1)+']');
					newrow.innerHTML = newhtml;
				};
				function addvalue(elm) { 
					var rownum = elm.parentNode.parentNode.getAttribute('id').replace(/antable\[(.*)\]/, '$1');
					console.log(rownum);
					var valtable = document.getElementById('valtable['+rownum+']');
					var lastnum = valtable.rows.length - 1;
					var lastrow = valtable.rows[lastnum];
					var newrow = valtable.insertRow(valtable.rows.length-1);
					var newhtml = lastrow.innerHTML.replaceAll('[new2]', '['+(lastnum)+']');
					newrow.innerHTML = newhtml;
				};
				function makevalues(elm) { 
					elm.innerHTML = 'add value';
					addvalue(elm);
				};
			</script>
			<style>.button { color: #aaaaaa; }</style>
			<p><input type=submit value=\"Save\">
			</form>";
		
		
		if ( $newxml ) {
			$maintext .= "<hr><p>Here you can create the definitions for a new stand-off annotation.
				Here you define which data you want to record each annotation so that they can be easily 
				edited and displayed correctly. Fields are stored as XML attributes, in which the key corresponds
				to the Field ID. The value is an open text field, unless you define a list of fixed values, in 
				which case you will be asked to select a value from a pull-down list. Since annotations
				can be of any type, there are no pre-defined fields at all.
				<p>A detailed explanation about how stand-off annotations work can be
				found in the <a target=help href='http://www.teitok.org/index.php?action=help&id=standoff'>Help pages</a>.
				";
		} else {
			$maintext .= "<hr><h2>Raw XML</h2>$newxml".showxml($andef)."<hr><p><a href='index.php?action=adminedit&folder=Annotations&id={$annotation}_def.xml'>edit raw XML</a>";
		};
		
	} else if ( $act == "select" ) {
		
		$maintext .= "<h1>Select a stand-off annotation</h1>
			<table>";
		
		foreach ( getset('annotations', array()) as $key => $ann ) {
			$display = $ann['display'] or $display = $key;
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$ttxml->xmlid&annotation=$key'>select</a><td>$display";
			$somedone = 1;
		};
		
		$maintext .= "</table>";
		if ( !$somedone ) $maintext .= "<i>No stand-off annotations defined yet";

		if ( $username ) {
			# $maintext .= "<hr><p><a href='index.php?action=$action&act=new'>define a new stand-off annotation</a>";
			$maintext .= "<hr><p><a href='index.php?action=adminsettings&act=additem&force=1&xpath=/ttsettings/annotations&goto=index.php?action=$action&act=define&annotation=[newid]'>define a new stand-off annotation</a>";
		};
			
	} else if ( $ttxml ) {
	
		if ( $anxml ) {
			$xpath = "//span$squery";
			$result = $anxml->xpath($xpath); $sortrows = array();
			foreach ( $result as $segment ) {
				$sid = $segment['id'];
				$tokenlist = str_replace("#", "", $segment['corresp']);
				if ( $tokenlist ) {
					$rotitle = ""; $rodata = "";
					foreach ( $tagset as $key => $val ) {
						$rodata .= " $key=\"{$segment[$key]}\"";
					};
					$segmenttxt = $segment."";
					if ( $segment['idx'] ) {
						# A substring - mark it up
						list ( $pa, $pb ) = explode("-", $segment['idx']);
						$pa = intval($pa);
						$pb = intval($pb);
						if ( !$pb ) $pb = $pa;
						$pre = mb_substr($segment, 0, $pa-1);
						$middle = mb_substr($segment, $pa-1, $pb-$pa+1);
						$post = mb_substr($segment, $pb);
						$segmenttxt = "<span style='opacity: 0.2; font-weight: normal;'>".$pre."</span>".$middle."<span style='opacity: 0.2; font-weight: normal'>".$post."</span>";
					};
					$markupcolor = $markcolor[$segment[$markfeat].""]; 
					$rodata .= " markupcolor=\"$markupcolor\"";
					$newrow = "<tr onMouseOver=\"markout(this, 1)\" onMouseOut=\"unmarkout();\" $rodata class=\"segment\" annid='$sid' toklist='$tokenlist'>";
					$newrow .= "<td><a href='index.php?action=$action&annotation=$annotation&act=redit&sid=$sid&cid=$fileid'\" style=\"text-decoration: none;\"><span style=\"color: $markupcolor; font-size: large;\">&blacksquare;</span> <ann>".$segmenttxt."</ann></a>";
					$newrow .= "</tr>"; array_push($sortrows, $newrow);
				} else if ( $segment['source'] ) {
					# This is a relation - 
					# $reldefs .= " { 'source': '{$segment['source']}', 'target': '{$segment['target']}' } ";
					$src = substr($segment['source'], 1);
					$trg = substr($segment['target'], 1);
					if ( !$rellist[$src] ) $rellist[$src] = array();
					array_push($rellist[$src], $segment);
				};
			};
			natsort($sortrows); $annotations .= "<table >".join("\n", $sortrows)."</table>";
			if ( $rellist ) {
				foreach ( $rellist as $src => $trgs ) {
					$trgdefs = "";
					foreach ( $trgs as $trg ) {
						$trgdef = "";
						foreach ( $trg->attributes() as $att ) {
							$key = $att->getName();
							$trgdef .= " '$key': '$att', ";
						};
						$trgdefs .= " { $trgdef }, ";
					};
					$reldefs .= "\n\t'$src': [ $trgdefs ]";
				};
				$annotations .= "\n<script>var reldefs = { $reldefs }</script>";
			};
		};
		if ( $username ) {
			$annotations .= "<hr><p><a href='index.php?action=$action&annotation=$annotation&cid={$_GET['cid']}&act=list'>{%show as list}</a>
			&bull; 
			<a href='index.php?action=adminedit&folder=Annotations&id={$annotation}_$fileid' target=edit>{%edit raw XML file}</a>
			";
		};


		if ( $username ) {
			$helptext = "<h2 style='margin-top: 0px;'>Help</h2><p>To edit the mark-up, select tokens in the text and fill in 
					the annotation data in the popup. To select more words, hold down the Alt key. Any selection will automatically
					snap to tokens.</p>";
		};

			# TODO: there should be a GUI for making the definitions...
			if ( $user['permissions'] == "admin" ) $rawedit = "				&bull;
				<a href='index.php?action=$action&act=define&annotation=$annotation' class=adminpart>{%edit definitions}</a>
							&bull;
				<a href='index.php?action=$action&act=vert&annotation=$annotation&cid={$_GET['cid']}' class=adminpart>{%verticalized view}</a>
				";
	
		# Allow for form switch buttons when so desired
		if ( getset("annotations/$annotation/formswitch") != '' ) {	
			$editxml = $cleaned;
			foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
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
			if ( $fbc > 1 ) $formbutsdiv = "<div>{%Text}: $formbuts</div><hr>
				<script language=Javascript src='$jsurl/tokedit.js'></script>";
				$jsonforms = array2json(getset('xmlfile/pattributes/forms', array()));
				foreach ( getset('xmlfile/pattributes/forms', array()) as $key => $item ) {
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
				$moreactions .= "
					var attributenames = [];
					$attnamelist
					var formdef = $jsonforms;
					var orgtoks = new Object();
					formify(); 
					var orgXML = document.getElementById('mtxt').innerHTML;
					setForm('pform');
				";
		};

		$maintext .= "
		
			<div style=\"z-index: 200; text-align: right; position: fixed; top: 10px; right: 30px;\">
				<span style='cursor: help;' onmouseover=\"show('help');\" onmouseout=\"hide('help');\">?</span>
				<div id=help name=help style='width: 400px; display: none; text-align: left; border: 1px solid #992000; background-color: #ffffaa; margin-top: 30px; padding: 15px; padding-bottom: 5px;'>$helptext</div>
			</div>
			

			<div id=editform name=editform style=\"z-index: 100; display: none; width: 22%; position: fixed; top: 80px; right: 20px; border: 1px solid #992000; background-color: white; padding: 15px; \">
				<h2 style='margin-top: 0px;'>Edit Annotation</h2>
				<form action='index.php?action=$action&cid=$fileid&annotation=$annotation&act=save' method=post>
				<p>Selection:<br>
				<span id='selection' style='font-weight: bold; color: #200099;'></span>				
				<input type=hidden name='news[0][text]' id='selectionf' style='font-weight: bold; color: #200099; border: none;'>
				<p style='display: none;'>IDs: <input name=news[0][corresp] id=idlist style='border: none;'>
				<table>
				$annotationrows
				</table>
				<p><input type=submit value='Save'> <input type=button value='Cancel' onClick='killselection();'>
				<div id='tokinfos' style='display: block;'></div>
				</form>
			</div>
					
			<div id='tokinfo' style='z-index: 300; display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
			<div style='vertical-align: top; width: 22%; float: right; overflow: scroll; position: fixed; top: 0px; right: 30px; height: 100%; ' id=annotations><h2 style='margin-top: 75px'>{%Annotations}</h2>$annotations</div>
			<div style='vertical-align: top; width: 70%; height: 80%; overflow: scroll;' onmouseup='makespan(event);'>$headertxt
			$formbutsdiv
			<div id='mtxt' mod='$action' $textdir>$cleaned</div>
				<hr><p><a href='index.php?action=file&cid=$fileid'>{%text view}</a>
				$rawedit
				</div>

			<script language=Javascript>$moreactions</script>
					<script language=Javascript src=\"$jsurl/tokedit.js\"></script>
			<script src=\"$jsurl/annotation.js\"></script>
			";
	} else {
	
		$maintext .= "<h1>{%{$andef['name']}}</h1>";
		$maintext .= "<p>{%Select a file}</p>";
		
		foreach ( glob("Annotations/{$annotation}_*.xml") as $file ) {
		
			if ( preg_match("/Annotations\/{$annotation}_(.*)\.xml/", $file, $matches) ) { $fileid = $matches[1]; };
			if ( $fileid == "def" ) continue;
		
			$maintext .= "<p><a href='index.php?action=$action&annotation=$annotation&cid=$fileid.xml'>$fileid</a>";
		};

		if ( $user['permissions'] == "admin" ) $maintext .= "<hr>
			<a href='index.php?action=$action&act=define&id=annotation=$annotation' class=adminpart>{%edit definitions}</a>
			";
		
	};
	
?>