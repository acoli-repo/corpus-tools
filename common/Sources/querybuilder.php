<?php

	$cqpcols = array();
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
	}; 

	# Determine which form to search on by default 
	$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";
	if ( !in_array($wordfld, $cqpcols) )  array_unshift($cqpcols, $wordfld ); # We need the wordfld as a search option
				
		$querytext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Query Builder}</h1>

			<form action='' method=post id=querybuilder name=querybuilder onsubmit=\"updatequery(); return false;\">";
			
		if ( $settings['cqp']['sattributes'] ) { $querytext .= "<table cellpadding=5><tr><td valign=top style='border-right: 1px solid #cccccc;'>
			
			<style>
				.tokdiv { border: 1px solid #aaaaaa; padding: 5px; background-color: #eeeeee; display: inline-block; margin-bottom: 10px; margin-right: 5px; vertical-align: middle;  }
				.globdiv { border-left: 1px solid #aaaaaa; padding: 5px; background-color: #eeffee; display: inline-block; margin-bottom: 10px; margin-right: 5px; vertical-align: middle;  }
				.tokdiv p { font-size: smaller; margin: 2px; }
				.globdiv p { font-size: smaller; }
				.helpbox { border: 1px solid #444444; background-color: #fefeee; padding: 5px; }
				.caption { margin-top: -4px; margin-bottom: 3px; font-size: smaller; color: #888888; }
				.wrong { font-weight: bold; color: #ff0000; }
			</style>
			<input id='toklist' style='display: none;'>
			<div id='cqlview'></div>
			<h3>{%Token Search}</h3>"; };	

		if ( $settings['cqp']['searchmethod'] == "word" && $act == "advanced" ) {
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
			$jsnames .= "pattname['$col'] = '{%$colname}'; ";
			if ( !$colname ) $colname = "[$col]";
			$tstyle = ""; 
			$coldef = $settings['cqp']['pattributes'][$col];
			if ( $coldef['admin'] == "1" ) {
				$tstyle = " class=adminpart";
				if ( !$username ) { continue; };
			};
			if ( $coldef['type'] == "mainpos" ) {
				if ( !$tagset ) {
					require ( "$ttroot/common/Sources/tttags.php" );
					$tagset = new TTTAGS("", false);
				}; $optlist = "";
				foreach ( $tagset->taglist() as $letters => $name ) {
					$optlist .= "<option value=\"$letters.*\">$name</option>";
				};
				$wordsearchtxt .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col]><option value=''>{%[select]}</option>$optlist</select>";
			} else if ( substr($coldef['type'], -6) == "select" ) {
				$tmp = file_get_contents("$corpusfolder/$col.lexicon"); unset($optarr); $optarr = array();
				foreach ( explode ( "\0", $tmp ) as $kva ) { 
					if ( $kva ) {
						if ( $coldef['values'] == "multi" ) {
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
						if ( $kval ) {
							if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
							if ( $coldef['type'] == "kselect" || $coldef['translate'] ) $kvaltxt = "{%$col-$kval}"; else $kvaltxt = $kval;
							if ( ( $coldef['type'] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) 
								$optarr[$kval] = "<option value='$kval' $seltxt>$kvaltxt</option>"; 
						};
					};
				};
				sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				if ( $coldef['select'] == "multi" ) $multiselect = "multiple";
				
				$wordsearchtxt .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col] $multiselect><option value=''>{%[select]}</option>$optlist</select>";

			} else 
				$wordsearchtxt .= "<tr><td$tstyle>{%$colname}
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
		$fieldlisttxt .= "</table><div style='display: none' id='tt-cqp-search'><table>";
		foreach ( $extannfields as $key => $display ) {
			$fieldlisttxt .= "<tr><th span='row'>$key<td>{%$display}</tr>";
		};

		// name the sattributes
		foreach ( $settings['cqp']['sattributes'] as $lvl ) {
			foreach ( $lvl as $xatt ) {
				if ( !$xatt['display'] || !$xatt['key'] || !is_array($xatt) ) continue;
				$jsnames .= "pattname['{$lvl['key']}_{$xatt['key']}'] = '{%{$xatt['display']}}'; ";
			};
		};
		$prescript .= "var pattname = []; $jsnames";

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
					</script>";
					
		if ( $act == "advanced" ) 
			$querytext .= "
					<p>{%Search method}:  &nbsp;
						<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'cqp');\" $cdef> CQP &nbsp; &nbsp;
						<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'word');\" $wdef> {%Word Search}
						<div name='wordsearch' id='wordsearch' style='display: none;'>
						<table>$wordsearchtxt</table>
						$chareqtxt
					</div>
				
					<div name='cqpsearch' id='cqpsearch'>
					<p>{%CQP Query}: &nbsp;  <input name=cql value='$cql' style='width: 600px;'/>
					$chareqjs 
					$subheader
				$stmp
				<p><b>{%Searchable fields}</b>
			
				<table>
				$fieldlisttxt
				</table>
				</div></div>
				<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>";
		else 
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
						<table>$wordsearchtxt</table>
						<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
						<button type='button' onClick='addtoken();'>{%Add token}</button>
						$chareqjs 
					
					
					$subheader
				$stmp
				</div></div>
				";
				
		
		// Preselect styles
		if ( $settings['cqp']['defaults']['searchtype'] == "context" ) { 
			$moreactions .= "switchtype('style', '{$settings['cqp']['defaults']['searchtype']}');"; 
			$chcont = "checked";
		} else { 
			$moreactions .= "switchtype('style', 'kwic');"; 
			$chkwic = "checked";
		};
		$optiontext .= "
				<p>{%Display method}: 
				<input type=radio name=style value='kwic' onClick=\"switchtype('style', 'kwic');\" $chkwic> KWIC
				<input type=radio name=style value='context' onClick=\"switchtype('style', 'context');\" $chcont> Context
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
				<p id='cqp-search'>{%Matching stategy}: <select name=strategy>
					<option value='longest' selected>{%Longest match}</option>
					<option value='shortest'>{%Shortest match}</option>
				</select> 
				</p>
				
				
				
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			$moreactions
			</script>";
			
		$querytext .= "\n\t<td valign=top>";  $hr = "";

		# Deal with any additional level attributes (sentence, utterance)
		if ( is_array ( $settings['cqp']['sattributes']))
		foreach ( $settings['cqp']['sattributes'] as $xatts ) {
			if ( !$xatts['display'] ) continue;
			$querytext .= "$hr<h3>{%{$xatts['display']}}</h3><table>"; $hr = "<hr>";
			foreach ( $xatts as $key => $item ) {
				$xkey = "{$xatts['key']}_$key";
				$val = $item['long']."" or $val = $item['display']."";
				if ( $item['type'] == "group" ) { 
					$querytext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
				} else {
					if ( $item['nosearch'] || $val == "" ) $a = 1; # Ignore this in search 
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
						$querytext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>{%[$mstext]}</option>$optlist</select>";
					} else 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$querytext .= "</table>"; 
		};	

		# Deal with any stand-off annotation attributes (errors, etc.)
		if ( is_array ( $settings['cqp']['annotations']))
		foreach ( $settings['cqp']['annotations'] as $xatts ) {
			if ( !$xatts['display'] || ( $xatts['admin'] && !$username ) ) continue;
			if ( $xatts['admin'] ) $adms = " class=adminpart";
			$querytext .= "$hr<div$adms><h3>{%{$xatts['display']}}</h3><table>"; $hr = "<hr>";
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
						$querytext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>{%[$mstext]}</option>$optlist</select>";
					} else 
						$querytext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$querytext .= "</table></div>"; 
		};	

		$querytext .= "</table>"; 
		$querytext .= "<p><input type=submit value=\"{%Create query}\"> <span onClick=\"document.getElementById('qbframe').style.display = 'none';\">{%cancel}</span></form>";
	
		if ( $settings['cqp']['longbox'] or $_GET['longbox'] ) 
			$cqlbox = "<textarea id='cqlfld' name=cql style='width: 600px;  height: 25px;' $chareqfn>$cql</textarea> ";
		else 
			$cqlbox = "<input id='cqlfld' name=cql value='$cql' style='width: 600px;'/> ";

		if ( $action == "cqp" ) $optionoption = "|
					<span onClick=\"document.getElementById('optionbox').style.display = 'block';\">{%options}</span> 
					<div style='display: none;' class='helpbox' id='optionbox'><span style='margin-top: -6px; float: right;' onClick=\"document.getElementById('optionbox').style.display = 'none';\">x</span>$optiontext</div>";

		$cqlfld = "
			<script language=Javascript>$prescript</script>
			<form action='$postaction' method=post id=cqp name=cqp><p>CQP Query: &nbsp; 
				$cqlbox
				<input type=submit value=\"{%Search}\"> 
					<span onClick=\"showqb('cqlfld');\">{%query builder}</span>
					| <span onClick=\"showcql();\">{%visualize}</span>
				$optionoption
			</form>
			$chareqjs
			<div style='display: none;' class='helpbox' id='cqlview'><span style='margin-top: -6px; float: right;' onClick=\"this.parentNode.style.display = 'none';\">x</span>$querytext</div>
			<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-top: -6px; float: right;' onClick=\"this.parentNode.style.display = 'none';\">x</span>$querytext</div>
			<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>";

	
?>