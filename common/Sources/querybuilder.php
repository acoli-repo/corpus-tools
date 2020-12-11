<?php

	$cqpcols = array();
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
	}; 

	# See if we have any subcorpus (pre-set values)
	foreach ( explode(",", $_GET['preset']) as $tmp ) {
		if ( preg_match("/(.*?):(.*)/", $tmp, $matches )) { 
			$presets[$matches[1]] = $matches[2]; 
			$subcorpustit .= "<h2>".pattname($matches[1]).": ".$matches[2]."</h2>";
		};
	};

	# Determine which form to search on by default 
	$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";
	if ( !in_array($wordfld, $cqpcols) )  array_unshift($cqpcols, $wordfld ); # We need the wordfld as a search option
				
		$querytext .= "<h2 style='text-align: left; margin-bottom: 20px;'>{%Query Builder}</h2>

			<form action='' method=post id=querybuilder name=querybuilder onsubmit=\"updatequery(); return false;\">";
			
		if ( $settings['cqp']['sattributes'] ) { $querytext .= "<table class=qbt cellpadding=5><tr><td valign=top style='border-right: 1px solid #cccccc;'>
			
			<input id='toklist' style='display: none;'>
			<div id='cqltoks'></div>
			<h3>{%Text Search}</h3>"; };	

		if ( $settings['cqp']['searchmethod'] == "word" && $act == "direct" ) {
			$wdef = "checked";
			$stmp = "<script language=Javascript>switchtype('st', 'word');</script>";
		} else { $cdef = "checked"; };

		if ( findapp("tt-cqp") ) {
			// tt-cqp specific options
			$extannfile = $_POST['extann'] or $extannfile = "Users/ann_{$user['short']}.xml";
			if ( file_exists($extannfile) ) {
				$extann = simplexml_load_file($extannfile);	
				foreach ( $extann->xpath("//def/field") as $i => $deffld) { 
					$tkey = $deffld['key'].'';
					$tval = $deffld['short'] or $tval = $deffld['display'] or $tval = $deffld['key']; 
					$extannfields["my_$tkey"] = "$tval"; 
				};
			};
		};

	if (  $settings['input']['replace'] ) {
		$chareqjs .= "<p>{%Special characters}: "; $sep = "";
		foreach ( $settings['input']['replace'] as $key => $item ) {
			$val = $item['value'];
			$chareqjs .= "$sep $key = $val"; 
			$charlist .= "ces['$key'] = '$val';";
			$sep = ",";
		};
		$chareqtxt = $chareqjs; 
		$chareqjs .= "
			<script language=\"Javascript\">
			var ces = {};
			$charlist
			function chareq (fld) {
				for(i in ces) {
					fld.value = fld.value.replace(i, ces[i]);
				}
			};
			</script>
		";
		$chareqfn = "onkeyup=\"chareq(this);\"";
	};			
		
		
		// define the word search		
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col); 
			$coldef = $settings['cqp']['pattributes'][$col];
			$jsnames .= "pattname['$col'] = { 'values': '{$coldef['values']}', 'display': '{%$colname}'}; ";
			if ( !$colname ) $colname = "[$col]";
			$tstyle = ""; 
			if ( $coldef['admin'] == "1" ) {
				$tstyle = " class=adminpart";
				if ( !$username ) { continue; };
			};
			if ( $coldef['nosearch'] == "1" ) continue;
			if ( $coldef['type'] == "mainpos" ) {
				if ( !$tagset ) {
					require ( "$ttroot/common/Sources/tttags.php" );
					$tagset = new TTTAGS("", false);
				}; $optlist = "";
				foreach ( $tagset->taglist() as $letters => $name ) {
					$optlist .= "<option value=\"$letters.*\">$name</option>";
				};
				$wordsearchtxt .= "<tr><th span=\"row\"$tstyle>{%$colname}<td colspan=2><select name=vals[$col]><option value=''>[{%select}]</option>$optlist</select>";
			} else if ( $coldef['type'] == "pos" ) {
				if( !$tagbuilder && file_exists("Resources/tagset.xml") ) {
					$tagbuilder = "
					<div id='tbframe' class='helpbox' style='display: none; width: 50%;'>
					<span style='margin-right: -5px; float: right; cursor: pointer;' onClick=\"this.parentNode.style.display = 'none';\">&times;</span>
					<h3>{%Tag Builder}: {%$colname}</h3>
					<form>
					";
					if ( !$tagset ) {
						require ( "$ttroot/common/Sources/tttags.php" );
						$tagset = new TTTAGS("", false);
					}; $optlist = "";
					foreach ( $tagset->taglist() as $letters => $name ) {
						$mainlist .= "<option value=\"$letters\">$name</option>";
						$letter = substr($letters,0,1);
						$inneropts = "<table>"; $taglen = 0;
						foreach ( $tagset->tagset['positions'][$letter] as $pos => $opt ) {
							if ( !is_array($opt) || $pos == "multi" ) continue;
							$innerlist = ""; $taglen++;
							foreach ( $opt as $key => $val ) {
								if ( !is_array($val) ) continue;
								$display = $val['display-'.$lang] or $display = $val['display'] or $display = $key;
								$innerlist .= "<option value='{$val['key']}'>$display</option>";
							};
							$display = $opt['display-'.$lang] or $display = $opt['display'] or $display = $pos;
							if ( $pos > $tagset->tagset['positions'][$letter]['maintag']) $inneropts .= "<tr><th>$display<td><select id='posopt-$letters-$pos'><option value='.' selected>[{%any}]</option>$innerlist</select>";
						};
						$taglens .= " taglen['$letter'] = $taglen;";
						$inneropts .= "</table>";
						
						$posopts .= "<div id='posopt-$letters' style='display: none;'>$inneropts</div>";
					};
					$tagbuilder .= "
						<p>{%Main POS}: <select id='mainpos' onChange='changepos(this);'><option disabled selected>[{%select}]</option>$mainlist</select>
						$posopts
						<p><input type='button' value=\"{%Insert}\" onClick=\"filltag();\"> 
						<input type='button' value=\"{%Append}\" onClick=\"filltag(1);\">
						</form></div>
						<script language='Javascript'>
							var tagfld; var tagprev;
							var taglen = []; $taglens
						</script>";
				};
				$wordsearchtxt .= "<tr><th span=\"row\"$tstyle>{%$colname}
					<td style='text-align: center;'><a onClick=\"tagbuilder('val-$col');\">{%tag builder}</a>
					<td><input name=vals[$col] id='val-$col' size=40>";
			} else if ( substr($coldef['type'], -6) == "select" ) {
				$tmp = file_get_contents("$corpusfolder/$col.lexicon"); unset($optarr); $optarr = array();
				foreach ( explode ( "\0", $tmp ) as $kva ) { 
					if ( $kva ) {
						if ( $coldef['values'] == "multi" ) {
							$mvsep = $coldef['mvsep'] or $mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
							$kvl = explode ( $mvsep, $kva );
						} else {
							$kvl = array ( $kva );
						}
						
						$colopts = $settings['xmlfile']['pattributes']['tags'][$col];
						foreach ( $kvl as $kval ) {
							$kval = trim($kval);
							if ( $colopts['options'][$kval] ) {
								$ktxt = $colopts['options'][$kval]['display'];
								if ( $colopts['i18n'] ) $ktxt = "{%$ktxt}";
							} else if ( $item['type'] == "kselect" ||  $coldef['translate'] ) $ktxt = "{%$key-$kval}"; 
							else $ktxt = $kval;
							
							if ( $kval && $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
							$optarr[$kval] = "<option value='$kval' $seltxt>$ktxt</option>"; 
						};
					};
				};
				natcasesort( $optarr ); $optlist = join ( "", $optarr ); $colm = '';
				if ( $coldef['select'] == "multi" ) { $multiselect = "multiple"; $colm = '[]'; };
				
				$wordsearchtxt .= "<tr><th span=\"row\"$tstyle>{%$colname}<td colspan=2><select name='vals[$col]$colm' $multiselect><option value=''>[{%select}]</option>$optlist</select>";

			} else 
				$wordsearchtxt .= "<tr><th span=\"row\"$tstyle>{%$colname}
						      <td><select name=\"matches[$col]\"><option value='matches'>{%matches}</option><option value='startswith'>{%starts with}</option><option value='endsin'>{%ends in}</option><option value='contains'>{%contains}</option></select>
						      <td><input name=vals[$col] size=40 $chareqfn>";
		};

		
			
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col);
			if ( $settings['cqp']['pattributes'][$col]['admin'] == "1" ) {
				$fieldlisttxt .= "<tr><th span='row'>$col<td class=adminpart>{%$colname}</tr>";				
			} else {
				$fieldlisttxt .= "<tr><th span='row'>$col<td>{%$colname}</tr>";
			};
		};
		$fieldlisttxt .= "</table><div style='display: none' id='tt-cqp-search'><table  class=qbt >";
		foreach ( $extannfields as $key => $display ) {
			$fieldlisttxt .= "<tr><th span='row'>$key<td>{%$display}</tr>";
		};

		// name the sattributes
		foreach ( $settings['cqp']['sattributes'] as $lvl ) {
			foreach ( $lvl as $xid => $xatt ) {
				if ( !$xatt['display'] || !$xatt['key'] || !is_array($xatt) ) continue;
				$jsnames .= "pattname['{$lvl['key']}_{$xatt['key']}'] = {'values': '{$xatt['values']}', 'display': '{%{$xatt['display']}}'}; ";
			};
		};
		foreach ( $settings['cqp']['annotations'] as $lvl ) {
			foreach ( $lvl as $xatt ) {
				if ( !$xatt['display'] || !$xatt['key'] || !is_array($xatt) ) continue;
				$jsnames .= "pattname['{$lvl['key']}_{$xatt['key']}'] = {'values': '{$xatt['values']}', 'display': '{%{$xatt['display']}}'}; ";
			};
		};
		if (  $settings['cqp']['multiseperator'] ) $prescript .= "var mvsep = '{$settings['cqp']['multiseperator']}'; ";

		// Pass i18n to Javascript
		$prescript .= "var pattname = [];\n var jstrans = []; var fldtypes= []; fldtypes['multi'] = [];\n$jsnames";
		$tojstrans = array ("CQL Query Visualization", "Document Search", "any token", "and", "or", "globals", "group", "name", "or more", "optional" );
		foreach ( $tojstrans as $tmp ) $prescript .= " jstrans['$tmp'] = '{%$tmp}';";

		$optiontext .= "<script language=Javascript>	
					function switchtype ( tg, type ) { 
						var types = [];
						types['st'] = ['cqp', 'word'];
						types['style'] = ['kwic', 'context'];
						types['app'] = ['cqp-', 'tt-cqp-'];
						for ( var i in types[tg] ) {
							stype = types[tg][i]; 
							document.getElementById(stype+'search').style.display = 'none';
						};
						document.getElementById(type+'search').style.display = 'block';
					};
					</script>
					<h2>{%Search Options}</h2>";
					

		$querytext .= "
				<script language=Javascript>
				function switchtype ( tg, type ) { 
					var types = [];
					types['st'] = ['cqp', 'word'];
					types['style'] = ['kwic', 'context'];
					types['app'] = ['cqp-', 'tt-cqp-'];
					for ( var i in types[tg] ) {
						stype = types[tg][i]; 
						document.getElementById(stype+'search').style.display = 'none';
					};
					document.getElementById(type+'search').style.display = 'block';
				};
				</script>
					<table  class=qbt >$wordsearchtxt</table>
					<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
					<button type='button' onClick='addtoken();'>{%Add token}</button>
					$chareqjs 
				
				
				$subheader
			$stmp
			</div></div>
			";
				
		
		// Preselect styles
		$searchtype = $settings['cqp']['defaults']['searchtype'] or $searchtype = "kwic";
		$moreactions .= "switchtype('style', '$searchtype');";  
		$optiontext .= "
				<p>{%Display method}: 
				<input type=radio name=style value='kwic' onClick=\"switchtype('style', 'kwic');\"> KWIC
				<input type=radio name=style value='context' onClick=\"switchtype('style', 'context');\"> Context
				";			
		
		// TODO: choose the CQP app (for now only for admin users)
		$cqpopts = array();
		if ( $cqpapp = findapp("cqp") ) array_push($cqpopts, "<input type=radio name=cqpapp value=\"$cqpapp\" checked onClick=\"switchtype('app', 'cqp-');\"> CQP");
		if ( $cqpapp = findapp("tt-cqp") ) array_push($cqpopts, "<input type=radio name=cqpapp value=\"$cqpapp\" onClick=\"switchtype('app', 'tt-cqp-');\"> TT-CQP");
		if ( $user['permissions'] == "admin" && count($cqpopts) > 1 ) $optiontext .= "
				<p>{%CQP application}: ".join("\n", $cqpopts);
		
		foreach ( $settings['cqp']['sattributes'] as $key => $val ) {
			if ( $settings['cqp']['defaults']['subtype'] == $key ) $sel = "checked"; else $sel = "";
			if ( $key != "text" && $val['display'] ) $morecontext .= "<input type=radio name=substyle value='{$val['key']}' $sel> {$val['display']}";
		};		
		
		
		$cntlist1 = array ( 3,4,5,6,7 );
		$defcnt = $settings['cqp']['defaults']['kwic'] or $defcnt = '5';
		foreach ( $cntlist1 as $key ) { 
			if ( $key == $defcnt ) $sel = "selected"; else $sel = "";
			$cntopts1 .= "<option value='$key' $sel>$key</option>"; 
		};
		$cntlist2 = array ( 5, 15, 30, 50, 100 );
		$defcnt = $settings['cqp']['defaults']['context'] or $defcnt = '30';
		foreach ( $cntlist2 as $key ) { 			
			if ( $key == $defcnt ) $sel = "selected"; else $sel = "";
			$cntopts2 .= "<option value='$key' $sel>$key</option>"; 
		};
		
		$optiontext .= "
				<div name='contextsearch' id='contextsearch' style='display: none;'>
					<p>{%Display context}: 
						<input type=radio name=substyle value='tok'>
						{%Tokens}: <select name=tokcnt>$cntopts2</select>
						$morecontext
				</div>
				<div name='kwicsearch' id='kwicsearch' $nokwic>
					$formsel
					<p>{%Context size}: <select name=context>$cntopts1</select>  {%words}
				</div>
				<p>{%Sort on}: <select name=sort>
					<option value='word'>{%Word}</option>
					<option value='word on matchend[1]..matchend[5]'>{%Right context}</option>
					<option value='word on match[-1]..match[-5]'>{%Left context}</option>
					<option value=''>{%Corpus order}</option>
				</select> 
				<p id='cqp-search'>{%Matching strategy}: <select name=strategy>
					<option value='longest' selected>{%Longest match}</option>
					<option value='shortest'>{%Shortest match}</option>
				</select> 
				</p>";
			
		$querytext .= "\n\t<td valign=top>";  $hr = "";

		# Deal with any additional level attributes (sentence, utterance)
		if ( is_array ( $settings['cqp']['sattributes']))
		foreach ( $settings['cqp']['sattributes'] as $xatts ) {
			if ( $xatts['partial'] ) {
				# A "partial" region we want to offer as within option
				$rname = $xatts['regionname'] or $rname = $xatts['level'];
				$regwith[$xatts['level']] = $rname;
			};
			if ( !$xatts['display'] ) continue;
			$querytext .= "$hr<h3>{%{$xatts['display']}}</h3><table  class=qbt >"; $hr = "<hr>";
			foreach ( $xatts as $key => $item ) {
				$xkey = "{$xatts['key']}_$key";
				$val = $item['long']."" or $val = $item['display']."";
				if ( $item['type'] == "group" ) { 
					$querytext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
				} else {
					if ( $item['nosearch'] || $val == "" ) $a = 1; # Ignore this in search 
					else if ( $item['type'] == "range" ) 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
					else if ( $item['type'] == "date" ) 
						## TODO: this is not really a nice solution
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' placeholder='YYYY-MM-DD' size=40>";
					else if ( $item['type'] == "select" || $item['type'] == "kselect" ) {
						# Read this index file
						$tmp = file_get_contents("$corpusfolder/$xkey.avs"); unset($optarr); $optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kva ) { 
							if ( $kva ) {
								if ( $item['values'] == "multi" ) {
									$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
									$kvl = explode ( $mvsep, $kva );
								} else {
									$kvl = array ( $kva );
								}
								
								foreach ( $kvl as $kval ) {
									if ( $item['type'] == "kselect" || $item['translate']  ) $ktxt = "{%$key-$kval}"; else $ktxt = $kval;
									if ( $presets[$xkey] == $kval ) $sld = "selected"; else $sld = "";
									$optarr[$kval] = "<option value='$kval' $sld>$ktxt</option>"; 
								};
							};
							foreach ( $kvl as $kval ) {
								if ( $kval && $kval != "_" ) {
									if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
										else $ktxt = $kval;
									if ( $presets[$xkey] == $kval ) $sld = "selected"; else $sld = "";
									$optarr[$kval] = "<option value='$kval' $sld>$ktxt</option>"; 
								};
							};
						};
						if ( $item['sort'] == "numeric" ) sort( $optarr, SORT_NUMERIC ); 
						else sort( $optarr, SORT_LOCALE_STRING ); 
						$optlist = join ( "", $optarr );
						if ( $item['select'] == "multi" ) {
							$multiselect = "multiple style='max-height: 50px; overflow:auto;'";  $msarr = "[]";
							$mstext = "select choices";
							$prescript .= "fldtypes['multi']['$key'] = true;";
						} else {
							$multiselect = ""; $msarr = "";
							$mstext = "select";
						};
						$querytext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>[{%$mstext}]</option>$optlist</select>";
					} else 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='{$presets[$xkey]}' size=40>";
						if ( $item['type'] == "long" ) $querytext .= "<input type='hidden' name=matches[$xkey] value='contains'>";
				};
			};
			$querytext .= "</table>"; 
		};	
		
		# Now do "within" regions
		foreach ( $regwith as $key => $val ) {
			$regwithtext .= "<option value='$key'>$val</option>";
		};
		if ( $regwithtext ) $querytext .= "<hr><p>Search within: <select name='within' id='within'><option value='text'>Text</option>$regwithtext</select>";

		# Deal with any stand-off annotation attributes (errors, etc.)
		if ( is_array ( $settings['cqp']['annotations']))
		foreach ( $settings['cqp']['annotations'] as $xatts ) {
			if ( !$xatts['display'] || ( $xatts['admin'] && !$username ) ) continue;
			if ( $xatts['admin'] ) $adms = " class=adminpart";
			$querytext .= "$hr<div$adms><h3>{%{$xatts['display']}}</h3><table  class=qbt >"; $hr = "<hr>";
			foreach ( $xatts as $key => $item ) {
				$xkey = "{$xatts['key']}_$key";
				$val = $item['long']."" or $val = $item['display']."";
				if ( $item['type'] == "group" ) { 
					$querytext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
				} else {
					if ( $item['nosearch'] ) $a = 1; # Ignore this in search 
					else if ( $item['type'] == "range" ) 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
					else if ( $item['type'] == "select" || $item['type'] == "kselect" ) {
						# Read this index file
						$tmp = file_get_contents("$corpusfolder/$xkey.avs"); unset($optarr); $optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kva ) { 
							if ( $kva ) {
								if ( $item['values'] == "multi" ) {
									$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
									$kvl = explode ( $mvsep, $kva );
								} else {
									$kvl = array ( $kva );
								}
								
								foreach ( $kvl as $kval ) {
									if ( $item['type'] == "kselect" ) $ktxt = "{%$key-$kval}"; else $ktxt = $kval;
									$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
								};
							};
							foreach ( $kvl as $kval ) {
								if ( $kval && $kval != "_" ) {
									if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
										else $ktxt = $kval;
									$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
								};
							};
						};
						if ( $item['sort'] == "numeric" ) sort( $optarr, SORT_NUMERIC ); 
						else sort( $optarr, SORT_LOCALE_STRING ); 
						$optlist = join ( "", $optarr );
						if ( $item['select'] == "multi" ) {
							$multiselect = "multiple";  $msarr = "[]";
							$mstext = "select choices";
						} else {
							$multiselect = ""; $msarr = "";
							$mstext = "select";
						};
						$querytext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>[{%$mstext}]</option>$optlist</select>";
					} else 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$querytext .= "</table></div>"; 
		};	

		$querytext .= "</table>"; 
		if ( $showdirect ) {
			$searchmake = "{%Search}"; 
			$prescript .= "var direct = 1;";
		} else $searchmake = "{%Create query}"; 
		$querytext .= "<p><input type=submit value=\"$searchmake\"> <a onClick=\"document.getElementById('qbframe').style.display = 'none';\">{%cancel}</a> |  <a href=\"index.php?action=querybuilderhelp\" target=help>{%help}</a></form>";
	
		if ( $settings['cqp']['longbox'] or $_GET['longbox'] ) 
			$cqlbox = "<textarea id='cqlfld' name=cql value='$cql' style='width: 600px;  height: 25px;' $chareqfn>$cql</textarea> ";
		else 
			$cqlbox = "<input id='cqlfld' name=cql value='$cql' style='width: 600px;'/> ";

		$cqlbox .= "<input type=hidden id='fromqb' name=fromqb value=''/> ";

		if ( $action == "cqp" ) $optionoption = "|
					<a title=\"{%define search options}\" onClick=\"document.getElementById('optionbox').style.display = 'block';\">{%options}</a> 
					<div style='display: none;' class='helpbox' id='optionbox'><span style='margin-right: -5px; float: right;' onClick=\"document.getElementById('optionbox').style.display = 'none';\" title=\"{%close}\">&times;</span>$optiontext</div>";

		$cqlfld = "
			<script language=Javascript>
				$prescript
				function checksearch (frm) {
					if ( frm.cqlfld.value == '' ) {
						updatequery(true); 
						if ( frm.cqlfld.value == '[] within text' ) frm.cqlfld.value = '';
						if ( frm.cqlfld.value == '' ) return false;
					};
				}; 
			</script>
			<form action='$postaction' onsubmit=\"return checksearch(this);\" method=post id=cqp name=cqp><p>{%CQP Query}: &nbsp; 
				$cqlbox
				<input type=submit value=\"{%Search}\"> 
					<a onClick=\"showqb('cqlfld');\" title=\"{%define a CQL query}\">{%query builder}</a>
					| <a onClick=\"showcql();\" title=\"{%visualize your CQL query}\">{%visualize}</a>
				$optionoption
			</form>
			$chareqjs
			$tagbuilder
			<div style='display: none;' class='helpbox' id='cqlview'></div>
			<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-right: -5px; float: right; cursor: pointer;' onClick=\"this.parentNode.style.display = 'none';\" title=\"{%close}\">&times;</span>$querytext</div>
			<script language='Javascript' src=\"$jsurl/cqlparser.js\"></script>
			<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>";

	
?>