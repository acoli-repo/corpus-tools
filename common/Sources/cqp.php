<?php
	// Script to allow searching a CQP version of the corpus
	// Requires a running version of CWB/CQP
	// This version reads in the XML and requires tt-cwb-xidx
	// Settings for the corpus are read from settings.xml
	// (c) Maarten Janssen, 2015-2016

	// Do not allow searches while the corpus is being rebuilt...
	if ( file_exists("tmp/recqp.pid") ) {
		fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
	};

	$fileview = $settings['defaults']['fileview'] or $fileview = "file";

	include ("$ttroot/common/Sources/cwcqp.php");

	$outfolder = $settings['cqp']['folder'] or $outfolder = "cqp";

	// This version of CQP relies on XIDX - check whether program and file exist
	$xidxcmd = $settings['bin']['tt-cwb-xidx'] or $xidxcmd = "/usr/local/bin/tt-cwb-xidx";
	if ( !file_exists($xidxcmd) || !file_exists("$outfolder/xidx.rng") ) {
		print "<p>This CQP version works only with XIDX
			<script language=Javascript>top.location='index.php?action=cqpraw';</script>
		";
	};

	# Determine which form to search on by default 
	$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";

	$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";


	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['searchfolder'];
	$cqpcols = array();
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
	}; 

	if  ( !$corpusfolder ) $corpusfolder = "cqp";
		
	# Check whether the registry file exists
	if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
		# For backward compatibility, always check the central registry
		$registryfolder = "/usr/local/share/cwb/registry/";
	};
	if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
		fatal ( "Corpus $cqpcorpus has no registry file" );
	};
	
	# Old sattributes did not have <text> inside
	if ( !is_array($settings['cqp']['sattributes']['text']) ) { 
		$settings['cqp']['sattributes']['text'] = $settings['cqp']['sattributes'];
		$settings['cqp']['sattributes']['text']['display'] = "Document search";
		$settings['cqp']['sattributes']['text']['key'] = "text";
		$settings['cqp']['sattributes']['text']['level'] = "text";
	};	

	if ( is_array($settings['cqp']['sattributes'] ) ) {
		foreach ( $settings['cqp']['sattributes'] as $key => $satt ) {
			if ( $satt['audio'] ) {
				$audioelm = $key;
				$audiosel = ", match {$key}_audio";
			};
		};
	};
	
	$cql = stripslashes($_POST['cql']);
	if ( !$cql ) $cql = stripslashes($_GET['cql']);
	
	# print_r($_POST); exit;

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


	if ( $act == "freq" ) {
		# Show frequency distribution (group) for a given Match
		
		$maintext .= "<h2>Frequency Information</h2>";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

		if ( substr($cql,0,6) == "<text>" ) $fileonly = 1;

		$cqpquery = "Matches = $cql";
		$cqp->exec($cqpquery);

		$size =$cqp->exec("size Matches");

		$maintext .= "<p>Search query: ".htmlentities($cql);

		$query = $_POST['query'] or $query = $_GET['query'] or $query = "[]";
		$results = $cqp->exec($query);

			$maintext .= "<form style=\"display: none;\" action=\"index.php?action=$action\" method=post name=newform id=newform>
					List files: <input name=fileonly value=\"0\">
					Pre-select: <input name=precql value=\"".preg_replace("/\"/", "&quot;", $cql)."\">
					Select: <input name=cql value=\"\"  $chareqfn>
				</form>";

		if ( $settings['cqp']['frequency'][$query] ) $queryname = $settings['cqp']['frequency'][$query]['display'];
		else $queryname = $query;

			$maintext .= "<p>Group query: <b>$queryname</b>";
		
			$maintext .= "<table>";
		
		if ( $fileonly ) {
			$freqnum = "# Texts";
		} else {
			$freqnum = "Frequency";
		};
		
		if ( preg_match ( "/group Matches match ([^ ]+) by match ([^ ]+)/", $query, $matches )  ) {
			$fld2 = $matches[1]; $fld = $matches[2];
			$fldname = pattname($fld) or $fldname = $fld;
			$fldname2 = pattname($fld2) or $fldname2 = $fld2;
			$maintext .= "<tr><th>$fldname<th>$fldname2<th>{%$freqnum}<th>{%Percentage}";
			$frf = 2;
		} else if ( preg_match ( "/group Matches match ([^ ]+)/", $query, $matches )  ) {
			$fld = $matches[1];
			$fldname = pattname($fld) or $fldname = $fld;
			$frf = 1;
			if ( strstr($fld, '_') && 1 == 2 ) { # Sometimes we want to see the documents insteads of the tokens, but rethink
				$sattfld = 1; $td = "<td>";
				$maintext .= "<tr><td><th>$fldname<th>{%$freqnum}<th>{%Percentage}";
			} else {
				$maintext .= "<tr><th>$fldname<th>{%$freqnum}<th>{%Percentage}";
			};
		};	
			
		foreach ( explode ( "\n", $results ) as $line ) {	
			$fields = explode ( "\t", $line ); $newcql = "";
			if ( $fld ) {
				if ( $sattfld ) {	
					$newscql = "<text> [] :: match.$fld = \"{$fields[0]}\"";
					$newcql = "[] :: match.$fld = \"{$fields[0]}\"";
				} else $newcql = "[$fld = \"{$fields[0]}\"]";
			};
			if ( $frf ) { 
				$perc = sprintf("%0.2f", ($fields[$frf]/$size)*100);
				$ptd = "<td align=right>$perc</td>";
			};
			if ( $fields[1] ) {
				$maintext .= "<tr>";
				$typecnt++;
				if ( $fld == "text_id" ) $maintext .= "<td><a href=\"index.php?action=$fileview&cid={$fields[0]}\">doc</a></td>";
				else if ( $sattfld ) $maintext .= "<td><a onclick=\"document.newform.cql.value='".preg_replace("/\"/", "&quot;", $newcql)."'; document.newform.fileonly.value='1'; document.newform.submit();\">docs</a></td>";
				foreach ( $fields as $key => $field ) {
					if ( $newcql && $key == 0 && strstr($fld, '_') ) {
						// TODO: this does only work if the original CQL was "simple"
						$recql = urlencode(str_replace(" within text", "", $cql)." :: match.$fld = \"{$fields[0]}\" within text");
						$recql = str_replace("'", "&#039;", $recql);
						$maintext .= "<td><a href=\"index.php?action=$action&cql=$recql\">".preg_replace ( "/__UNDEF__/", "(none)", $fields[0])."</a></td>";
					} else if ( $newcql && $key == 0 ) $maintext .= "<td><a onclick=\"document.newform.cql.value='".preg_replace("/\"/", "&quot;", $newcql)."'; document.newform.submit();\">".preg_replace ( "/__UNDEF__/", "(none)", $fields[0])."</a></td>";
					else if ( $key == $frf ) $maintext .= "<td align=right>$field</td>";
					else $maintext .= "<td>".preg_replace ( "/__UNDEF__/", "(none)", $field )."</td>";
				};
				$maintext .= "$ptd</tr>";
			};
		};
			$maintext .= "
				<tr>$td<td style='border-top: 1px solid #999999; color: #999999;' colspan=".count($fields).">$typecnt {%types}<td style='border-top: 1px solid #999999; text-align: right; color: #999999;'>".$size."</table>";
		
		
		

	} else if ( $act == "download" ) {
		# Download results of a Match as TXT

		header("content-type: text/txt; charset=utf-8");
		header('Content-Disposition: attachment; filename="CQPQuery.txt"');
		
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

		// $cqp->exec("set PrettyPrint off");
		
		$cqpquery = "Matches = $cql";
		$cqp->exec($cqpquery);

		$cqp->exec("show -cpos");
		$cqp->exec("set Context 10 words");
		
		$cqpquery = "cat Matches";
		$results = $cqp->exec($cqpquery);
		
		print $results;
		exit;

	} else if ( $act == "distribute" ) {
		# Distribute over a bunch of different sattributes
		
		$maintext .= "<h1>{%Word Distribution}</h1>";
		if ( file_exists("Page/distributetext.html") ) $maintext .= getlangfile("distributetext");

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

		$cqpquery = "Matches = $cql";
		$results = $cqp->exec($cqpquery);

		$showlist = $settings['cqp']['distribute']
			or $showlist = $settings['cqp']['sattributes']['text'];
		foreach ( $showlist as $key => $val1 ) {	
			$val = $settings['cqp']['sattributes']['text'][$key];
			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			if ( $val['type'] != "select" && $val['type'] != "kselect" ) continue;
			$cqpquery = "group Matches matchend $xkey";
			$results = $cqp->exec($cqpquery);

			$displaytxt = $val1['display'] or $displaytxt = "{%Words by} {%".$val['display']."}";
			$maintext .= "<h2>$displaytxt</h2>";
			if ( $val1['header'] ) {
				$tmp = $val1['header'][$lang] or $tmp = $val1['header']["en"];
				$maintext .= "<p>{$tmp}</p>";
			};
			$maintext .= "<table>";
			foreach ( explode("\n", $results) as $line ) {
				list ( $cvl, $cnt ) = explode ( "\t", $line );
			
				if ( $key == "project" ) $cvlt = $settings['projects'][$cvl]['name'];
				else if ( $val['type'] == "kselect" || $val['translate'] ) $cvlt = "{%$key-$cvl}"; 
				else $cvlt = $cvl;
				if ( $cvl && !$val['noshow'] ) $maintext .= "<tr><th span='row'>$cvlt<td style='text-align: right; padding-left: 10px;'>".number_format($cnt);
			};
			$maintext .= "</table>";
		};		

	} else if ( $cql || $_POST['atts'] || $_GET['atts'] || $_POST['vals'] ) {
	
		# Display the results for a given CQP search
		# Can have a pre-query (to search within a selection)
		# Consists either of a direct CQL query or of attribute-value pairs that have to be turned into one
		# Text-level restrictions (or other XML-levels) are provided separately (by default)

		$sort = $_POST['sort'] or $sort = $_GET['sort'] or $sort = '';

		# If this is a simple search - turn it into a CQP search
		if ( $cql && !preg_match("/[\"\[\]]/", $cql) ) {
			$simple = $cql; $cql = "";
			foreach ( explode ( " ", $simple ) as $swrd ) {
				$swrd = preg_replace("/(?!\.\])\*/", ".*", $swrd);
				$cql .= "[$wordfld=\"$swrd\"] ";
			};
		};
		
		# Allow word searches to be defined via URL
		if ( !$cql && $_GET['atts'] ) {	
			foreach ( explode ( ";", $_GET['atts'] ) as $att ) {
				list ( $feat, $val ) = explode ( ":", $att );
				$_POST['atts'][$feat] = $val;
			};
		}; 
		
		# If this is a word search - turn it into a CQP search
		if ( !$cql && $_POST['vals'] ) {	
			$cql = "["; $sep = "";
			foreach ( $_POST['vals'] as $key => $val ) {
				$type = $_POST['matches'][$key];
				$attname = pattname($key) or $attname =  pattname('form');
				if ( $val ) {
					if ( $type == "startswith" ) {
						$cql .= " $sep $key = \"$val.*\""; $sep = "&";
						$subtit .= "<p>$attname = $val-";
					} else if ( $type == "contains" ) {
						$cql .= "$sep$key=\".*$val.*\""; $sep = " & ";
						$subtit .= "<p>$attname <i>{%contains}</i> $val";
					} else if ( $type == "endsin" ) {
						$cql .= "$sep$key=\".*$val\""; $sep = " & ";
						$subtit .= "<p>$attname -$val";
					} else {
						$cql .= "$sep$key=\"$val\""; $sep = " & ";
						$subtit .= "<p>$attname = $val";
					};
				};
			};
			$cql .= "]";
		};

		# This is a document search - turn it into CCQP
		if ( !$cql || $cql == "[]" ) {	
			$cql = "<text> []";  $sep = "::"; $fileonly = true;
		} else if ( strstr($cql, '<text> [] ::') ) { 
			$sep = "&"; $fileonly = true;
		} else if ( strstr($cql, '::') ) { 
			$sep = "&"; 
		} else if ( $_POST['atts'] ) { 
			 $sep = "::"; 
		};
		
		# Check whether we are asked to only list files
		if ( $_GET['fileonly'] || $_POST['fileonly'] ) { 
			$fileonly = true; 
		};
		
		if ( is_array($_POST['atts']) )
		foreach ( $_POST['atts'] as $tmp => $val ) {
			if ( !$val ) continue;
			list ( $key, $type ) = explode ( ":", $tmp );
			if ( strstr($key, '_' ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			list ( $keytype, $keyname ) = explode ( "_", $xkey );
			$attitem = $settings['cqp']['sattributes'][$keytype][$keyname]; 
				$attname = $attitem['display']; $atttype = $attitem['type'];
				$attname = "{%$attname}";

			# Account for multiple select
			if ( is_array($val) ) {
				$val = "#(".join("|", $val)."#)";
			};

			if ( $type == "start" ) {
				$cql .= " $sep int(match.$xkey) >= $val"; $sep = "&";
				if (!$_POST['atts']["$key:end"]) $subtit .= "<p>$attname > $val";
			} else if ( $type == "end" ) {
				$cql .= " $sep int(match.$xkey) <= $val"; $sep = "&";
				if ( $start = $_POST['atts']["$key:start"] ) 
					$subtit .= "<p>$attname: $start - $val";
				else 
					$subtit .= "<p>$attname < $val";
			} else if ( $atttype == "long" ) {
				$cql .= " $sep match.$xkey = \".*$val.*\" %cd"; $sep = "&";
				$subtit .= "<p>$attname {%contains} <i>$val</i>";
			} else {
				$val = quotemeta($val);
				$val = str_replace("#\\", "", $val);
				if ( $attitem['values'] == "multi" ) {
					$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
					$subtit .= "<p>$attname = <i>$val</i>";
					$val = "(.*$mvsep)?$val($mvsep.*)?"; # Brackets not supported in CQP
				} else {
					$subtit .= "<p>$attname = <i>$val</i>";
				};
				$cql .= " $sep match.$xkey = \"$val\""; $sep = "&";
				if ( $attitem['type'] == "kselect" || $attitem['translate'] ) $val = "{%$key-$val}";
				$val = stripslashes($val);
			};
		}; # if ( strstr($cql, "a.text") && !strstr($cql, "a:") ) { $cql = "a:$cql"; }

		// Now that the we have the full CQL - make sure matches are always within a <text>
		if ( !preg_match("/ within /", $cql) && !$fileonly ) $cql .= " within text";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus

		if ( !$fileonly || $user['permissions'] == "admin" ) $cqltxt = str_replace("'", "&#039;", $cql); # Best not show the query for doc-only searches...

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>

			<form action='' method=post id=cqp name=cqp><p>{%CQP Query}: &nbsp; <input name=cql size=80 value='{$cqltxt}'  $chareqfn> <input type=submit value=\"Search\"> <a href='index.php?action=$action&act=advanced'>{%advanced}</a></form>
			$chareqjs
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			</script>
			";

		if ( $audioelm ) {
			$maintext .= "<script language='Javascript' src=\"$jsurl/audiocontrol.js\"></script>";
			$maintext .= "<div style='display: none;'><audio id=\"track\" src=\"http://alfclul.clul.ul.pt/teitok/site/Audio/mini.wav\" controls ontimeupdate=\"checkstop();\"></audio></div>";
		};
		
		if ( $_POST['strategy'] && !$fileonly ) {
			$cmd = "set MatchingStrategy {$_POST['strategy']}";
			$cqp->exec($cmd); // Select the corpus
			$maintext .= "<p>{%Matching strategy}: {%{$_POST['strategy']}}";
		};

		$precql = stripslashes($_POST['precql']);
		if ( !$precql ) $precql = stripslashes($_GET['precql']);
		if ( $precql ) {
			# Execute a pre-selection query when specified
			$cqp->exec("Preselect = ".$precql);
			# And switch to that sub-corpus
			$cqp->exec("Preselect");
			$maintext .= "<p>Preselect query: $precql";
		};		
		
		$max = $_POST['max'] or $max = $_GET['max'] or $max = $cqpmax or $max = 100; $_POST['max'] = $max;
		$start = $_POST['start'] or $start = $_GET['start'] or $start = 0; $_POST['start'] = $start;
		$end = $start + $max;

		# To make sure that we can modify our query, create a hidden post 
		$maintext .= "\n<form action='' method=post id=resubmit name=resubmit>";
		foreach ( $_POST as $key => $val ) {
			$maintext .= "<input type=hidden name=$key value='$val'>";
			if ( is_array($val) ) {
				foreach ( $val as $key2 => $val2 )
					$maintext .= "\n    <input type=hidden id='rs$key$key2' name={$key}[$key2] value='$val2'>";
			} else {
				$maintext .= "\n  <input type=hidden name=$key id='rs$key' value='$val'>";
			};
		};
		$maintext .= "\n</form>";
		
		# If we have a target in our query, we can do multi-edit
		if ( preg_match("/\@\[/", $cql) ) { 
			$targetmatch = 1;
		};
			
		$maintext .= $subtit;
		$cqp->exec("Matches = ".$cql);
		$cnt = $cqp->exec("size Matches");
			if ( $debug ) $maintext .= "<p>Matches = $cql";
		if ( $cnt == 0 ) { 
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<p><i>No matches</i> for $cql
				"; 
			$nomatch = 1;
			if ( $debug ) $maintext .= "<p>Matches = $cql";
		} else if ( $fileonly )  { 
			
			# Document searches	

			$acnt = $bcnt = 0;
			foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {
				if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
				$val = $item['display']; # $val = $item['long'] or 
				if ( $item['type'] == "group" ) {	
					$fldval = $val; # substr($key,4);
					if ( $fldval != "" ) $fldtxt = " ($fldval)";
					else $fldtxt = "";
				} else if ( $item['noshow'] ) {
					# Ignore year if there also is a date
				} else if ( $key != "id" ) {
					$moreatts .= ", match $xkey";
					$moreth .= "<th>{%$val}";
					$atttik[$bcnt] = $key; $bcnt++;
				};
				$acnt++;
				$atttit[$acnt] = $val;
			};
			
			# TODO: Sorting on structural attributes does not work in CQP - solution?
// 			if ( !$sort ) $sort = "text_year";
// 			$sortquery = "sort Matches by match on match $sort";
// 			$cqp->exec($sortquery);
// 			if ( $debug ) $maintext .= "<p>SORT COMMAND:<br>$sortquery";

			if ( $debug ) $maintext .= "<p>$cqpquery<PRE>$results</PRE>";
			
			$cqpquery = "tabulate Matches $start $end match text_id$moreatts";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>TABULATE COMMAND:<br>$cqpquery";
		
		
			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			$maintext .= "<p>$cnt {%results}";
			if ( $scnt < $cnt ) { 
				$maintext .= " &bull; {%Showing} $start - $end *
				(<a onclick=\"document.getElementById('rsstart').value ='$end'; document.resubmit.submit();\">{%next}</a>)";
			};
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<table><tr><th>ID$moreth";
			foreach ( $resarr as $line ) {
				$fatts = explode ( "\t", $line ); $fid = array_shift($fatts);
				if ( $admin ) {
					$fidtxt = preg_replace("/^\//", "", $fid ); 
				} else {
					$fidtxt = preg_replace("/.*\//", "", $fid ); 
				};
				# Translate the columns where needed
				foreach ( $fatts as $key => $fatt ) {
					$attit = $atttik[$key];
					$tmp = $settings['cqp']['sattributes']['text'][$attit]['type'];
					if ( $settings['cqp']['sattributes']['text'][$attit]['type'] == "kselect" ) {
						$fatts[$key] = "{%$attit-$fatt}";
					};
				};
				$maintext .= "<tr><td><a href='index.php?action=$fileview&cid={$fid}'>{$fidtxt}</a><td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>".join ( "<td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>", $fatts );
			};
			$maintext .= "</table>";

		} else {

			# Text searches
			
			if ( $sort ) {
				# $maintext .= "Sorted by $sort"; - this is not 
				$cqp->exec("sort Matches by $sort");
			};
						
			// $cqpquery = "tabulate Matches $start $end match text_id, match id, matchend id, match[-$context], matchend[$context]";
			$cqpquery = "tabulate Matches $start $end match text_id, match ... matchend id, match, matchend $audiosel";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>From inital $cnt results: $cqpquery<PRE>$results</PRE>";

			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			$maintext .= "<p>$cnt {%results}";
			if ( $scnt < $cnt ) { 
				$last = min($end,$cnt);
				$maintext .= " &bull; {%Showing} $start - $last";
				if ($end<$cnt) $maintext .= " (<a onclick=\"document.getElementById('rsstart').value ='$end';  document.resubmit.submit();\">{%next}</a>)";
			};
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>";
			
			$maxmatchlength = 0;
			$minmatchlength = 1000;
			
			$showstyle = $_POST['style'] or $showstyle = $settings['cqp']['defaults']['searchtype'];
			$showsubstyle = $_POST['substyle'] or $showsubstyle = $settings['cqp']['defaults']['subtype'];
			
				if ( $showstyle == "context" && $showsubstyle != "tok"  ) {
					$expand = "--expand=$showsubstyle";					
				} else if ( $showstyle == "context" ) {
					$context = $_POST['tokcnt'] or $context = $_GET['tokcnt'] or $context = $settings['cqp']['defaults']['context'] or $context = '30';
					$expand = "--context=$context";
				} else {
					$context = $_POST['context'] or $context = $_GET['context'] or $context = $settings['cqp']['defaults']['kwic'] or $context = '5';
					$expand = "--context=$context ";
				};
			
			foreach ( $resarr as $line ) {
				$i++;
				if ( $line == "" ) continue;
				$tmp = explode ( "\t", $line );
				list ( $fileid, $match, $leftpos, $rightpos, $audiofile ) = $tmp;
				$idlist = explode ( " ", $match );
				if ( count($idlist) > $maxmatchlength )  $maxmatchlength = count($idlist);
				if ( count($idlist) < $minmatchlength )  $minmatchlength = count($idlist);
				$m1 = $idlist[0]; 
				$m2 = end($idlist); 
				

				$cmd = "$xidxcmd --filename=$fileid --cqp='$outfolder' $expand $leftpos $rightpos";
				$resxml = shell_exec($cmd);
				if ( $debug ) $maintext .= "<pre>$cmd\n".htmlentities($xidxres)."</pre>";
				
				$fileid = preg_replace("/xmlfiles\//", "", $fileid );
								
				$m1 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m1 );
				$m2 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m2 );

				if ( $audiofile ) {
					if ( preg_match("/start=\"([^\"]*)\"/", $resxml, $matches ) ) $strt = $matches[1]; else $strt = 0;
					if ( preg_match("/end=\"([^\"]*)\"/", $resxml, $matches ) ) $stp = $matches[1]; else $stp = 0;
					if ( $settings['default']['playbutton'] ) $playimg = $settings['default']['playbutton'];
					else  if ( file_exists("Images/playbutton.gif") ) $playimg = "Images/playbutton.gif";
					else $playimg = "http://alfclul.clul.ul.pt/teitok/site/Images/playbutton.gif";
					$audiobut = "<td><img src=\"$playimg\" width=\"14\" height=\"14\" style=\"margin-right: 5px;\" onClick=\"playpart('$audiofile', $strt, $stp, this );\"></img></td>"; 
				};

				# Now, clean the resulting XML in various ways to make it display better
				
				# XMLIDX does not work perfectly, so if we just missed the <tok>, repair it
				if ( substr($resxml, 0,3) == "tok" || substr($resxml, 0,4) == "dtok" ) { $resxml = "<".$resxml; }; # Missing the beginning of <tok>
				if ( substr($resxml, -4) == "</tok" ) { $resxml = $resxml.">"; }; # Missing the end of </tok>
				$resxml = preg_replace("/<[^<>]+$/", "", $resxml); # A bit the beginning of a tag
				$resxml = preg_replace("/^[^<>]+>/", "", $resxml); # A bit the end of a tag

				# Replace block-type elements by vertical bars
				$resxml = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml); # non-standard section break
				$resxml = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $resxml);

				# Remove notes and app
				$resxml = preg_replace ( "/<(note|app)[^>]*>.*?<\/\\1>/smi", "", $resxml);

				# Remove block-type markings
				$resxml = preg_replace ( "/(<\/?(stage|speaker)[^>]*\/?>\s*)/", "", $resxml);
				
				# Remove HTML like code
				$resxml = preg_replace ( "/<\/?(table|tr|td|div|font)[^>]*\/>/", "", $resxml);
				
				
				
				# Somehow, the XML fragment is too long sometimes, repaired that here for now
				$resxml = preg_replace ( "/<$/", "", $resxml);

				if ( $settings['xmlfile']['basedirection'] == "rtl" ) {
					$direc = " style='direction: rtl;'";
					$tba = " align=right";
					$lca = "left"; $rca = "right";
				} else {
					$lca = "right"; $rca = "left";
				};
				
				$resstyle = "";
				if ( $showstyle == "context" ) {
					// Show as context
					$moreactions .= "\nhllist('$match', 'r-$i', '#ffff55'); ";
					if ($i/2 == floor($i/2)) $resstyle = "style='background-color: #f5f5f2;'"; 
				} else {
					// Show as KWIC
					$rescol = "#ffffaa";
					$resxml = preg_replace ( "/(<tok[^>]*id=\"$m1\")/", "</td><td style='text-align: center; font-weight: bold;'>\\1", $resxml);
					$resxml = preg_replace ( "/(id=\"$m2\".*?<\/tok>)/smi", "\\1</td><td style='text-align: $rca;'>", $resxml);
					$resstyle = "style='text-align: $lca;'";
					$moreactions .= "\nhllist('$match', 'r-$i', '#ffffff'); ";
				};
				
				if ( !$noprint ) $editxml .= "\n<tr id=\"r-$i\" tid=\"$fileid\"><td><a href='index.php?action=$fileview&amp;cid=$fileid&amp;jmp=$match' style='font-size: 10pt; padding-right: 5px;' title='$fileid' target=view>{%context}</a></td>
					$audiobut
					<td $resstyle>$resxml</td></tr>";


			};

			# empty tags are working horribly in browsers - change
			$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

			#Build the view options	
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
				if ( preg_match("/ $key=/", $editxml) ) {
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

			$showform = $_POST['showform'] or $showform = $_GET['showform'] or $showform = 'form';
			if ( $showform == "word" ) $showform = $wordfld;

			# Only show text options if there is more than one form to show
			if ( $fbc > 1 ) $viewoptions .= "<p>{%Text}: $formbuts"; // <button id='but-all' onClick=\"setbut(this['id']); setALL()\">{%Combined}</button>

				$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
				$jsontrans = array2json($settings['transliteration']);

				if ( $tagstxt ) $showoptions .= "<p>{%Tags}: $tagstxt ";
						
			$maintext .= "
					$viewoptions $showoptions
					<hr>
				<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				$countrow
				<div id='mtxt'$tba><text><table $direc>$editxml</table></text></div>

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
		
				<script language=Javascript>$moreactions</script>
				";
		};
		
		$cqlu = $cqltxt;

		$cqp->close();
		$maintext .= "\n<hr>\n\n<p><form action='index.php?action=download' id=cqlform name=cqlform method=post>
			<input type=hidden name=cql value='$cqlu' $chareqfn></form>";
		if ( $username && !$fileonly && ( $user['permissions'] == "admin" || $settings['defaults']['cqpedit'] == 1 ) ) {
   
 			if ( $minmatchlength > 1 && !$targetmatch ) {
	 			$maintext .= "(Query cannot be used for multi-token edit since all results span more than one word)";
 			} else if ( !$nomatch ) {
				
				$maintext .= "					
					<p  class=adminpart onclick=\"document.cqlform.action = 'index.php?action=cqpedit'; document.cqlform.submit();\">Use this query for multi-token edit</p>
					
					";
			};
		};
		
		if ( !$fileonly && !$nomatch ) $maintext .= "					
			<span onclick=\"document.cqlform.action = 'index.php?action=$action&act=download'; document.cqlform.submit();\">{%Download results as TXT}</span>
			";
		$cqll = str_replace("'", "&#039;", $cql);
		$maintext .= " - <a href='index.php?action=cqp&cql=".urlencode($cqll)."'>{%Direct query URL}</a>";
		$maintext .= "<!-- CQL: $cql -->";
		# $maintext .= "<span onclick=\"this.style.display = 'none'; document.getElementById('freqopts').style.display='block';\">show frequency options</span>";
		
		# Do not allow frequency counts if we already have a pre-select CQL
		if ( !$precql && !$nomatch && !$fileonly ) { # We actually do want text-based searches
			$maintext .= "<hr>";
			if ( $settings['cqp']['visualize'] == "cqp" ) {
				$visaction = "cqp&act=freq";
			} else {
				$visaction = "visualize";
			};

			$maintext .= "<div style='display: block;' id='freqopts' name='freqopts'>
				<h2>Frequency Options</h2>
				<p>Use the query above to calculate:";
		
			if ( !$fileonly && $minmatchlength == 1 ) 
			 foreach ( $settings['cqp']['pattributes'] as $key => $att ) {
				$pattname = pattname($key);
				$freqlist[$key] = 1;
				$freqopts .= "<option value=\"$key\">{%$pattname}</option>";
			};
			foreach ( $settings['cqp']['sattributes'] as $lvl => $tmp ) {
				foreach ( $tmp as $key => $val ) {
					if ( !is_array($val) ) continue;
					$fkey = $lvl."_".$key;
					$pattname = pattname($fkey);
					$freqlist[$fkey] = 1;
					$freqopts .= "<option value=\"$fkey\">{%$pattname}</option>";
				};
			}; 
			foreach ( $settings['cqp']['frequency'] as $key => $val ) {
				if ( !is_array($val) || $val['type'] == "group" ) continue; # Skip attributes and separator TODO: keep separators in pulldown?
				if ( ( $val['nosearch'] || $val['freq'] == "no" ) && !$val['freq'] == "yes" ) continue; # Skip non-searchable fields (unless explicitly freqable) 
				if ( ( !$fileonly || preg_match("/text_/", $val['key']) ) ) {
					$display = $val['long'] or $display = $val['display'];
					if ( $val['type'] == "freq" ) $freqopts .= "<option value=\"{$val['key']}\">{%$display}</option>";
					else $nofreqopts .= "<p><a onclick=\"document.freqform.query.value = '{$val['key']}'; document.freqform.submit();\">{%$display}</a>";
				};
			};
			if ( !$freqlist['text_id'] ) $freqopts .= "<option value=\"text_id\">{%Text}</option>";
			$freqopts .= "<option value=\"custom\">Custom distribution</option>";
			$maintext .= "<p>Frequency by: <select onchange=\"freqchoose(this.value);\">
				$freqopts
			</select>
			<p>$nofreqopts</p>
			<script language=Javascript>
			function freqchoose (val) {
				if ( val == 'custom') {
					document.getElementById('customfreq').style.display = 'block';
				} else {
					document.freqform.query.value = 'group Matches match ' + val; document.freqform.submit();
				};
			};
			</script>";
			
					
			$maintext .= "<div id='customfreq' style='display: none;'><p>Specifiy additional custom CQP command on the results above (Matches):
		
				<form action='index.php?action=$visaction' id=freqform name=freqform method=post>
					CQP Query:
					<input type=hidden name=cql value='$cqlu' $chareqfn><input name='query' value='group Matches match lemma' size=70>
					<input type=submit value='{%Apply}'>
				</form>
				</div>
				<br></div>
				";
		};		
		

	} else if ( $act == "advanced" ) {
		
		# Display the search screen (advanced search)
			
		if ( file_exists("Pages/searchhelp.html") ) {
			$maintext .= "<div id='helpbox' style='position: absolute; top: 70px; right: 20px'><a href='index.php?action=searchhelp'>{%help}</a></div>";
		};

		# $tokatts['word'] = $tokatts['form'] or $tokatts['word'] = "Written form"; --> how to do this?
		if ( !in_array($wordfld, $cqpcols) )  array_unshift($cqpcols, $wordfld ); # We need the wordfld as a search option
				
		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>

			<!-- <h2>{%Advanced Search}</h2> -->
			<form action='' method=post id=cqp name=cqp>";
			
		if ( $settings['cqp']['sattributes'] ) { $maintext .= "<table cellpadding=5><tr><td valign=top style='border-right: 1px solid #cccccc;'>
			<h3>{%Text Search}</h3>"; };	

		if ( $settings['cqp']['searchmethod'] == "word" ) {
			$wdef = "checked";
			$stmp = "<script language=Javascript>switchtype('st', 'word');</script>";
		} else { $cdef = "checked"; };
		
		$maintext .= "
				<p>{%Search method}:  &nbsp;
					<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'cqp');\" $cdef> CQP &nbsp; &nbsp;
					<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'word');\" $wdef> {%Word Search}
				<script language=Javascript>
				function switchtype ( tg, type ) { 
					var types = [];
					types['st'] = ['cqp', 'word'];
					types['style'] = ['kwic', 'context'];
					for ( var i in types[tg] ) {
						stype = types[tg][i]; 
						document.getElementById(stype+'search').style.display = 'none';
					};
					document.getElementById(type+'search').style.display = 'block';
				};
				</script>
				<div name='wordsearch' id='wordsearch' style='display: none;'><table>";
				
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col);
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
				$maintext .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col]><option value=''>{%[select]}</option>$optlist</select>";
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
				
				$maintext .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col] $multiselect><option value=''>{%[select]}</option>$optlist</select>";

			} else 
				$maintext .= "<tr><td$tstyle>{%$colname}
						      <td><select name=\"matches[$col]\"><option value='matches'>{%matches}</option><option value='startswith'>{%starts with}</option><option value='endsin'>{%ends in}</option><option value='contains'>{%contains}</option></select>
						      <td><input name=vals[$col] size=40 $chareqfn>";
		};
		
		$maintext .= "</table>$chareqtxt</div>
				<div name='cqpsearch' id='cqpsearch'>
				<p>{%CQP Query}: &nbsp; <input name=cql size=70 value='{$cql}' $chareqfn>
				$chareqjs 
				$subheader
				";

			

				
		$maintext .= "
							$stmp

			<p><b>{%Searchable fields}</b>
			
			<table>
			";
			
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col);
			if ( $settings['cqp']['pattributes'][$col]['admin'] == "1" ) {
				$maintext .= "<tr><th span='row'>$col<td class=adminpart>{%$colname}</tr>";				
			} else {
				$maintext .= "<tr><th span='row'>$col<td>{%$colname}</tr>";
			};
		};
		$maintext .= "</table></div>
			<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>";
		
		
		// Preselect styles
		if ( $settings['cqp']['defaults']['searchtype'] == "context" ) { 
			$moreactions .= "switchtype('style', '{$settings['cqp']['defaults']['searchtype']}');"; 
			$chcont = "checked";
		} else { 
			$moreactions .= "switchtype('style', 'kwic');"; 
			$chkwic = "checked";
		};
		$maintext .= "
				<p>{%Display method}: 
				<input type=radio name=style value='kwic' onClick=\"switchtype('style', 'kwic');\" $chkwic> KWIC
				<input type=radio name=style value='context' onClick=\"switchtype('style', 'context');\" $chcont> Context
				";			
		
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
		
		$maintext .= "
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
				<p>{%Matching stategy}: <select name=strategy>
					<option value='longest' selected>{%Longest match}</option>
					<option value='shortest'>{%Shortest match}</option>
				</select> 
				
				
				
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			$moreactions
			</script>";
			
		$maintext .= "\n\t<td valign=top>";  $hr = "";

		# Deal with any additional level attributes (sentence, utterance)
		if ( is_array ( $settings['cqp']['sattributes']))
		foreach ( $settings['cqp']['sattributes'] as $xatts ) {
			if ( !$xatts['display'] ) continue;
			$maintext .= "$hr<h3>{%{$xatts['display']}}</h3><table>"; $hr = "<hr>";
			foreach ( $xatts as $key => $item ) {
				$xkey = "{$xatts['key']}_$key";
				$val = $item['long']."" or $val = $item['display']."";
				if ( $item['type'] == "group" ) { 
					$maintext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
				} else {
					if ( $item['nosearch'] ) $a = 1; # Ignore this in search 
					else if ( $item['type'] == "range" ) 
						$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
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
						$maintext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>{%[$mstext]}</option>$optlist</select>";
					} else 
						$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$maintext .= "</table>"; 
		};	

		# Deal with any stand-off annotation attributes (errors, etc.)
		if ( is_array ( $settings['cqp']['annotations']))
		foreach ( $settings['cqp']['annotations'] as $xatts ) {
			if ( !$xatts['display'] || ( $xatts['admin'] && !$username ) ) continue;
			if ( $xatts['admin'] ) $adms = " class=adminpart";
			$maintext .= "$hr<div$adms><h3>{%{$xatts['display']}}</h3><table>"; $hr = "<hr>";
			foreach ( $xatts as $key => $item ) {
				$xkey = "{$xatts['key']}_$key";
				$val = $item['long']."" or $val = $item['display']."";
				if ( $item['type'] == "group" ) { 
					$maintext .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
				} else {
					if ( $item['nosearch'] ) $a = 1; # Ignore this in search 
					else if ( $item['type'] == "range" ) 
						$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
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
						$maintext .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>{%[$mstext]}</option>$optlist</select>";
					} else 
						$maintext .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$maintext .= "</table></div>"; 
		};	

		$maintext .= "</table>"; 
		$maintext .= "<p><input type=submit value=\"{%Search}\"></form>";
	
			
		
	} else {
		# Display the search screen with a CQP box only and a search-help

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>

			<form action='' method=post id=cqp name=cqp><p>CQP Query: &nbsp; <input name=cql size=80 value='{$cql}' $chareqfn> <input type=submit value=\"Search\"> <a href='index.php?action=$action&act=advanced'>{%advanced}</a></form>
			$chareqjs
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			</script>
			";

		$explanation = getlangfile("cqptext", true);
		
		$maintext .= $explanation;
	
	}; $maintext .= "<style>.adminpart { background-color: #eeeedd; padding: 5px; }</style>";

	function pattname ( $key ) {
		global $settings;
		if ( $key == "word" ) $key = $wordfld;
		$val = $settings['xmlfile']['pattributes']['forms'][$key]['display'];
		if ( $val ) return $val;
		$val = $settings['xmlfile']['pattributes']['tags'][$key]['display'];
		if ( $val ) return $val;
		
		# Now try without the text_ or such
		if ( preg_match ("/^(.*)_(.*?)$/", $key, $matches ) ) {
			$key2 = $matches[2]; $keytype = $matches[1];
			$val = $settings['cqp']['sattributes'][$key2]['display'];
			if ( $val ) return $val;
			$val = $settings['cqp']['sattributes'][$keytype][$key2]['display'];
			if ( $val ) return $val;
		};
		
		return "<i>$key</i>";
	};

?>