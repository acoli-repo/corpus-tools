<?php
	// Script to allow searching a CQP version of the corpus
	// Requires a running version of CWB/CQP
	// This version reads in the XML and requires tt-cwb-xidx
	// Settings for the corpus are read from settings.xml
	// (c) Maarten Janssen, 2015

	// Do not allow searches while the corpus is being rebuilt...
	if ( file_exists("tmp/recqp.pid") ) {
		fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
	};

	include ("../common/Sources/cwcqp.php");

	// This version of CQP relies on XIDX - check whether program and file exist
	$xidxcmd = $settings['bin']['tt-cwb-xidx'] or $xidxcmd = "/usr/local/bin/tt-cwb-xidx";
	if ( !file_exists($xidxcmd) || !file_exists("cqp/xidx.rng") ) {
		print "<p>This CQP version works only with XIDX
			<script language=Javascript>top.location='index.php?action=cqpraw';</script>
		";
	};

	$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "/usr/local/share/cwb/registry/";

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['searchfolder'];
	$cqpcols = array();
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
	}; 
	
	
	# Check whether the registry file exists
	if ( !file_exists($registryfolder.strtolower($cqpcorpus)) ) {
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
				// console.log(fld.value);
				for(i in ces) {
					console.log(i + ' = ' + ces[i]);
					fld.value = fld.value.replace(i, ces[i]);
					console.log(fld.value);
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
					$chareqjs
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
			$maintext .= "<tr><th>$fldname<th>{%$freqnum}<th>{%Percentage}";
		};	
			
		foreach ( split ( "\n", $results ) as $line ) {	
			$fields = split ( "\t", $line ); $newcql = "";
			if ( $fld ) {
				$newcql = "[$fld = \"{$fields[0]}\"]";
			};
			if ( $frf ) { 
				$perc = sprintf("%0.2f", ($fields[$frf]/$size)*100);
				$ptd = "<td align=right>$perc</td>";
			};
			if ( $fields[1] ) {
				$maintext .= "<tr>";
				foreach ( $fields as $key => $field ) {
					if ( $newcql && $key == 0 ) $maintext .= "<td><a onclick=\"document.newform.cql.value='".preg_replace("/\"/", "&quot;", $newcql)."'; document.newform.submit();\">".preg_replace ( "/__UNDEF__/", "(none)", $fields[0])."</a></td>";
					else if ( $key == $frf ) $maintext .= "<td align=right>$field</td>";
					else $maintext .= "<td>".preg_replace ( "/__UNDEF__/", "(none)", $field )."</td>";
				};
				$maintext .= "$ptd</tr>";
			};
		};
			$maintext .= "</table>";
		

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
		# Old style distribution - made redundant by freq?
		
		$maintext .= "<h1>{%Word Distribution}</h1>";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

		$cqpquery = "Matches = $cql";
		$results = $cqp->exec($cqpquery);

		foreach ( $settings['cqp']['sattributes']['text'] as $key => $val ) {	
			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			if ( $val['type'] != "select" && $val['type'] != "kselect" ) continue;
			$cqpquery = "group Matches matchend $xkey";
			$results = $cqp->exec($cqpquery);

			$maintext .= "<h2>{%Words by} {%".$val['display']."}</h2><table>";
			foreach ( explode("\n", $results) as $line ) {
				list ( $cvl, $cnt ) = explode ( "\t", $line );
			
				if ( $key == "project" ) $cvlt = $settings['projects'][$cvl]['name'];
				else if ( $val['type'] == "kselect" || $val['translate'] ) $cvlt = "{%$key-$cvl}"; 
				else $cvlt = $cvl;
				if ( $cvl ) $maintext .= "<tr><th>$cvlt<td style='text-align: right; padding-left: 10px;'>".number_format($cnt);
			};
			$maintext .= "</table>";
		};		

	} else if ( $cql || $_POST['atts'] || $_POST['vals'] ) {
		# Display the results for a given CQP search
		# Can have a pre-query (to search within a selection)
		# Consists either of a direct CQL query or of attribute-value pairs that have to be turned into one
		# Text-level restrictions (or other XML-levels) are provided separately (by default)

		$sort = $_POST['sort'] or $sort = $_GET['sort'] or $sort = '';

		# This is a simple search - turn it into a CQP search
		if ( $cql && !preg_match("/[\"\[\]]/", $cql) ) {
			$simple = $cql; $cql = "";
			foreach ( explode ( " ", $simple ) as $swrd ) {
				$swrd = preg_replace("/(?!\.\])\*/", ".*", $swrd);
				$cql .= "[word=\"$swrd\"] ";
			};
		};
		
		# This is a word search - turn it into a CQP search
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
		} else if ( strstr($cql, '::') ) { 
			$sep = "&"; 
		} else if ( $_POST['atts'] ) { 
			 $sep = "::"; 
		};
		if ( is_array($_POST['atts']) )
		foreach ( $_POST['atts'] as $tmp => $val ) {
			if ( !$val ) continue;
			list ( $key, $type ) = explode ( ":", $tmp );
			if ( strstr($key, '_' ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			list ( $keytype, $keyname ) = explode ( "_", $xkey );
			$attitem = $settings['cqp']['sattributes'][$keytype][$keyname]; 
				$attname = $attitem['display']; $atttype = $attitem['type'];

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
					$val = "(|$mvsep)$val(|$mvsep)";
				};
				$cql .= " $sep match.$xkey = \"$val\""; $sep = "&";
				$subtit .= "<p>$attname = <i>$val</i>";
			};
		}; # if ( strstr($cql, "a.text") && !strstr($cql, "a:") ) { $cql = "a:$cql"; }

		// Now that the we have the full CQL - make sure matches are always within a <text>
		if ( !preg_match("/ within /", $cql) ) $cql .= " within text";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus

		if ( !$fileonly || $user['permissions'] == "admin" ) $cqltxt = $cql; # Best not show the query for doc-only searches...

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

			$acnt = 0;
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
					$moreth .= "<th>$val";
				};
				$acnt++;
				$atttit[$acnt] = $val;
			};
			$cqp->exec("sort Matches by match on text_year");

			if ( $debug ) $maintext .= "<p>$cqpquery<PRE>$results</PRE>";
			
			$cqpquery = "tabulate Matches $start $end match text_id$moreatts";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>TABULATE COMMAND:<br>$cqpquery";
		
		
			$resarr = split ( "\n", $results ); $scnt = count($resarr);
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
				$maintext .= "<tr><td><a href='index.php?action=file&cid={$fid}'>{$fidtxt}</a><td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>".join ( "<td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>", $fatts );
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

			$resarr = split ( "\n", $results ); $scnt = count($resarr);
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
					$expand = "--context=$context";
				};
			
			foreach ( $resarr as $line ) {
				$i++;
				if ( $line == "" ) continue;
				$tmp = explode ( "\t", $line );
				list ( $fileid, $match, $leftpos, $rightpos, $audiofile ) = $tmp;
				$idlist = split ( " ", $match );
				if ( count($idlist) > $maxmatchlength )  $maxmatchlength = count($idlist);
				if ( count($idlist) < $minmatchlength )  $minmatchlength = count($idlist);
				$m1 = $idlist[0]; 
				$m2 = end($idlist); 
				

				$cmd = "$xidxcmd --filename=$fileid $expand $leftpos $rightpos";
				$resxml = shell_exec($cmd);
				if ( $debug ) $maintext .= "<pre>$cmd\n".htmlentities($xidxres)."</pre>";
				
				$fileid = preg_replace("/xmlfiles\//", "", $fileid );
								
				$m1 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m1 );
				$m2 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m2 );

				# Replace block-type elements
				$resxml = preg_replace ( "/(<\/?(p|seg)>\s*|<(p|seg) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<lb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml);
				
				
				if ( $audiofile ) {
					if ( preg_match("/start=\"([^\"]*)\"/", $resxml, $matches ) ) $strt = $matches[1]; else $strt = 0;
					if ( preg_match("/end=\"([^\"]*)\"/", $resxml, $matches ) ) $stp = $matches[1]; else $stp = 0;
					if ( $settings['default']['playbutton'] ) $playimg = $settings['default']['playbutton'];
					else  if ( file_exists("Images/playbutton.gif") ) $playimg = "Images/playbutton.gif";
					else $playimg = "http://alfclul.clul.ul.pt/teitok/site/Images/playbutton.gif";
					$audiobut = "<td><img src=\"$playimg\" width=\"14\" height=\"14\" style=\"margin-right: 5px;\" onClick=\"playpart('$audiofile', $strt, $stp, this );\"></img></td>"; 
				};
				
				# Somehow, the XML fragment is too long sometimes, repaired that here for now
				$resxml = preg_replace ( "/<$/", "", $resxml);
				
				$resstyle = "";
				if ( $showstyle == "context" ) {
					// Show as context
					$moreactions .= "\nhllist('$match', 'r-$i', '#ffff55'); ";
					if ($i/2 == floor($i/2)) $resstyle = "style='background-color: #f5f5f2;'"; 
				} else {
					// Show as KWIC
					$rescol = "#ffffaa";
					$resxml = preg_replace ( "/(<tok[^>]*id=\"$m1\")/", "</td><td style='text-align: center; font-weight: bold;'>\\1", $resxml);
					$resxml = preg_replace ( "/(id=\"$m2\".*?<\/tok>)/smi", "\\1</td><td>", $resxml);
					$resstyle = "style='text-align: right;'";
					$moreactions .= "\nhllist('$match', 'r-$i', '#ffffff'); ";
				};
				
				if ( !$noprint ) $editxml .= "\n<tr id=\"r-$i\"><td><a href='index.php?action=file&amp;cid=$fileid&amp;jmp=$m1' style='font-size: 10pt; padding-right: 5px;' title='$fileid' target=view>{%context}</a></td>
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
			if ( $showform == "word" ) $showform = "form";

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
				<div id='mtxt'><text><table>$editxml</table></text></div>

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
		
		$cqlu = $cql;

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
			<span onclick=\"document.cqlform.action = 'index.php?action=$action&act=download'; document.cqlform.submit();\">Download results as TXT</span></p>
			";
		$maintext .= "<!-- CQL: $cql -->";
		# $maintext .= "<span onclick=\"this.style.display = 'none'; document.getElementById('freqopts').style.display='block';\">show frequency options</span>";
		
		# Do not allow frequency counts if we already have a pre-select CQL
		if ( !$precql && !$nomatch && !$fileonly ) { # We actually do want text-based searches
			$maintext .= "<hr>";
 			if ( $minmatchlength == 1 || $fileonly ) {
				$maintext .= "<div style='display: block;' id='freqopts' name='freqopts'>
					<h2>Frequency Options</h2>
					<p>Use the query above to calculate:";
			
				$freqopts = $settings['cqp']['frequency'] or 
					$freqopts = array ( 
						array ( 'key' => 'group Matches match pos', 'display' => 'Frequency by POS' ),
						array ( 'key' => 'group Matches match lemma', 'display' => 'Frequency by lemma'),
						array ( 'key' => 'group Matches match pos by match lemma', 'display' => 'Frequency by POS+lemma'),
					);
				
				foreach ( $freqopts as $key => $val ) {
					if ( !$fileonly || preg_match("/text_/", $val['key']) ) $maintext .= "<p><a onclick=\"document.freqform.query.value = '{$val['key']}'; document.freqform.submit();\">{%{$val['display']}}</a>";
				};
					#<p><a onclick=\"document.freqform.query.value = 'group Matches match pos'; document.freqform.submit();\">Frequency by POS</a>
					#<p><a onclick=\"document.freqform.query.value = 'group Matches match lemma'; document.freqform.submit();\">Frequency by lemma</a>
					#<p><a onclick=\"document.freqform.query.value = 'group Matches match pos by match lemma'; document.freqform.submit();\">Frequency by POS+lemma</a>
			
			
				$maintext .= "<p>Or run an additional custom CQP command on the results above (Matches):
			
					<form action='index.php?action=$action&act=freq' id=freqform name=freqform method=post>
						CQP Query:
						<input type=hidden name=cql value='$cqlu' $chareqfn><input name='query' value='group Matches match lemma' size=70>
						<input type=submit value='{%Apply}'>
					</form>
					<br></div>
					";
			} else {
				$maintext .= "<div style='display: block;' id='freqopts' name='freqopts'>
					<h2>Additional queries</h2>
					<p>Use the query above to run an additional CQP command on the result (Matches):
			
					<form action='index.php?action=$action&act=freq' id=freqform name=freqform method=post>
						CQP Query:
						<input type=hidden name=cql value='$cqlu' $chareqfn><input name='query' value='group Matches matchend lemma' size=70>
						<input type=submit value='{%Search}'>
					</form>
					<br></div>
					";
			};
		};		
		

	} else if ( $act == "advanced" ) {
		
		# Display the search screen (advanced search)
	
		if ( file_exists("Pages/searchhelp.html") ) {
			$maintext .= "<div style='position: absolute; top: 70px; right: 20px'><a href='index.php?action=searchhelp'>{%help}</a></div>";
		};

		# $tokatts['word'] = $tokatts['form'] or $tokatts['word'] = "Written form"; --> how to do this?
		array_unshift($cqpcols, 'word' ); // Add word as a search option
				
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
			if ( substr($coldef['type'], -6) == "select" ) {
				$tmp = file_get_contents("cqp/$col.lexicon"); unset($optarr); $optarr = array();
				foreach ( explode ( "\0", $tmp ) as $kval ) { 
					if ( $kval ) {
						if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
						if ( $coldef['type'] == "kselect" || $coldef['translate'] ) $kvaltxt = "{%$col-$kval}"; else $kvaltxt = $kval;
						if ( ( $coldef['type'] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) 
							$optarr[$kval] = "<option value='$kval' $seltxt>$kvaltxt</option>"; 
					};
				};
				sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				if ( $coldef['select'] == "multi" ) $multiselect = "multiple";
				
				$maintext .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col] $multiselect><option value=''>{%[select]}</option>$optlist</select>";

			} else 
				$maintext .= "<tr><td$tstyle>{%$colname}
						      <td><select name=\"matches[$col]\"><option value='matches'>{%matches}</option><option value='startswith'>{%starts with}</option><option value='endsin'>{%ends in}</option><option value='contains'>{%contains}</option></select>
						      <td><input name=vals[$col] size=50 $chareqfn>";
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
				$maintext .= "<tr><th>$col<td class=adminpart>{%$colname}</tr>";				
			} else {
				$maintext .= "<tr><th>$col<td>{%$colname}</tr>";
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
						$maintext .= "<tr><th>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
					else if ( $item['type'] == "select" || $item['type'] == "kselect" ) {
						# Read this index file
						$tmp = file_get_contents("cqp/$xkey.avs"); unset($optarr); $optarr = array();
						foreach ( explode ( "\0", $tmp ) as $kval ) { 
							if ( $kval && $kval != "_" ) {
								if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
									else $ktxt = $kval;
								$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
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
						$maintext .= "<tr><th>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>{%[$mstext]}</option>$optlist</select>";
					} else 
						$maintext .= "<tr><th>{%$val}<td><input name=atts[$xkey] value='' size=40>";
				};
			};
			$maintext .= "</table>"; 
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
		if ( $key == "word" ) $key = "form";
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