<?php
	// Script to allow searching a CQP version of the corpus
	// Requires a running version of CWB/CQP
	// Settings for the corpus are read from settings.xml
	// (c) Maarten Janssen, 2015

	// Do not allow searches while the corpus is being rebuilt...
	if ( file_exists("tmp/recqp.pid") ) {
		fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
	};

	include ("$ttroot/common/Sources/cwcqp.php");


	$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";

		if ( $_GET['version'] ) {
			$versionxml = simplexml_load_file("Resources/static.xml");
			$version = current($versionxml->xpath("//version[@id='{$_GET['version']}']"));
			if ( $version ) {
				$registryfolder = "static/{$version['folder']}";
				$versionheader = "<h2>Static version {$version['display']}</h2>";
			};
		};

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['searchfolder'];
	$cqpcols = array();
	foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
		if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
	}; 
	
	# Check whether the registry file exists
	if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
		# For backward compatibility, always check the central registry
		if ( file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) $registryfolder = "/usr/local/share/cwb/registry/";
		else {
			$tmp = str_replace("CQP-", "", $cqpcorpus);
			if ( file_exists("/usr/local/share/cwb/registry/".strtolower($tmp)) ) {
				$registryfolder = "/usr/local/share/cwb/registry/";
				$cqpcorpus = $tmp;
			};
		};
	};
	if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
		fatal ( "Corpus $cqpcorpus has no registry file" );
	};
	
		
	$cql = stripslashes($_POST['cql']);
	if ( !$cql ) $cql = stripslashes($_GET['cql']);

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
				console.log(fld.value);
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

		$cqp = new CQP($registryfolder);
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
			
		foreach ( explode ( "\n", $results ) as $line ) {	
			$fields = explode ( "\t", $line ); $newcql = "";
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
		
	} else if ( $act == "seq" ) {
	
		check_login();
	
		if ( !$_POST ) $_POST = $_GET;
		$maintext .= "<h1>Raw CQP Query</h1>
			<p>Below you can type in a sequence of CQP queries, and the interface will display the final result.
		
			<form action='index.php?action=$action&act=$act' method=post>
				<textarea style='width: 100%; height: 200px;' name=query>{$_POST['query']}</textarea>
				<p><input type=submit value='Execute'>
			</form>";

		if ( $_POST['query'] ) {
			$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
			$cqp = new CQP();
			$cqp->exec($cqpcorpus); // Select the corpus
			$cqp->exec("set PrettyPrint off");
		
			$result = $cqp->exec($_POST['query']);
			if ( $result == "\n" ) $result = "No result";
		
			$maintext .= "<hr><h2>Result</h2><pre>".htmlentities($result)."</pre>";
		};
	
	} else if ( $act == "download" ) {
		# Download results of a Match as TXT

		header("content-type: text/txt; charset=utf-8");
		header('Content-Disposition: attachment; filename="CQPQuery.txt"');
		
		$cqp = new CQP($registryfolder);
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

		$cqp = new CQP($registryfolder);
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
				else if ( $val['type'] == "kselect" ) $cvlt = "{%$key-$cvl}"; 
				else $cvlt = $cvl;
				if ( $cvl ) $maintext .= "<tr><th>$cvlt<td style='text-align: right; padding-left: 10px;'>".number_format($cnt);
			};
			$maintext .= "</table>";
		};		

	} else if ( $cql ) {
	
		# Display the results for a given CQP search
		# Can have a pre-query (to search within a selection)
		# Consists either of a direct CQL query or of attribute-value pairs that have to be turned into one
		# Text-level restrictions (or other XML-levels) are provided separately (by default)

		# This is a simple search - turn it into a CQP search
		if ( !preg_match("/[\"\[\]]/", $cql) ) {
			$simple = $cql; $cql = "";
			foreach ( explode ( " ", $simple ) as $swrd ) {
				$swrd = preg_replace("/(?!\.\])\*/", ".*", $swrd);
				$cql .= "[word=\"$swrd\"] ";
			};
		};
		

		$cqp = new CQP($registryfolder);
		$cqp->exec($cqpcorpus); // Select the corpus

		if ( !$fileonly || $user['permissions'] == "admin" ) $cqltxt = $cql; # Best not show the query for doc-only searches...

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>
		
			$versionheader

			<form action='' method=post id=cqp name=cqp><p>{%CQL Query}: &nbsp; <input name=cql size=80 value='{$cqltxt}'  $chareqfn> <input type=submit value=\"Search\"> <a href='index.php?action=$action&act=advanced'>{%advanced}</a></form>
			$chareqjs
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			</script>
			";
		
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
			
		$showform = "word"; $context = 7; # This used to be definable
		if ( file_exists("cqp/text_id.avs") ) $idfld = "text_id"; else $idfld = "word";

		if ( $showform != "word" && !$fileonly ) { $subtit .= "<p>{%Showing form}: <i>".pattname($showform)."</i>"; };
		$maintext .= $subtit;
		$cqp->exec("Matches = ".$cql);
		$cnt = $cqp->exec("size Matches");
		if ( $debug ) $maintext .= "<p>Matches = $cql";
		if ( $cnt == 0 ) { 
		
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<p><i>No matches</i> for $cql
				"; 
			$nomatch = 1;
			
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

			if ( $debug ) $maintext .= "<p>$cqpquery<PRE>$results</PRE>";
			
			$cqpquery = "tabulate Matches $start $end match $idfld$moreatts";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>TABULATE COMMAND:<br>$cqpquery";
		
		
			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			$maintext .= "<p>$cnt {%results}";
			if ( $scnt < $cnt ) { 
				$maintext .= " &bull; {%!showing} $start - $end *
				(<a onclick=\"document.getElementById('rsstart').value ='$end'; document.resubmit.submit();\">{%next}</a>)";
			};
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<table><tr><th>ID$moreth";
			foreach ( $resarr as $line ) {
				$fatts = explode ( "\t", $line ); $fid = array_shift($fatts);
				if ( $admin ) {
					$fidtxt = preg_replace("/^\//", "", $fid ); 
				} else if ( $idfld == "word" ) {
					$fidtxt = ""; # No text_id available
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

			$matchwords = "match .. matchend";
			$leftc = "match[-$context]..match[-1]";
			$rightc = "matchend[1]..matchend[$context]";
			$matches = "Matches";

			if ( $_POST['style'] == "attlist" ) {
				$sfn = pattname($pform); 
				$matchh = "<tr><td><th>$sfn";
				$morematch = ", $matchwords word";
				if ( $withproject ) $morematch .= ", $matchwords text_project";
				else $morematch .= ", $matchwords $idfld";
				foreach ( $cqpcols as $i => $key ) {	
					$morematch .= ", $matchwords $key";
					$matchh .= "<th>". pattname($key);
				};
			} else {
				$morematch = ", $matchwords $showform";
				if ( $withproject ) $morematch .= ", $matchwords text_project";
			};
			
			$cqpquery = "tabulate $matches $start $end match $idfld, $leftc $showform, $rightc $showform, $matchwords id $morematch";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>From inital $cnt results: $cqpquery<PRE>$results</PRE>";

			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			$maintext .= "<p>$scnt {%results}";
			if ( $scnt < $cnt ) { 
				$last = min($end,$cnt);
				$maintext .= " &bull; {%!showing} $start - $last";
				if ($end<$cnt) $maintext .= " (<a onclick=\"document.getElementById('rsstart').value ='$end';  document.resubmit.submit();\">{%next}</a>)";
			};
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
				<table>$matchh";
			
			$maxmatchlength = 0;
			$minmatchlength = 1000;
			
			foreach ( $resarr as $line ) {
				if ( $line == "" ) continue;
				$tmp = explode ( "\t", $line );
				list ( $fileid, $lcontext, $rcontext, $tid, $word, $projectid  ) = $tmp;
				$tmp2 = array_slice ($tmp,4);
				foreach ( $tmp2 as $tmp3 => $tmp4 ) { $featres[$tmp3] = explode(" ", $tmp4); };

				$tidarray = explode (" ", $tid );
				if ( count($tidarray) > $maxmatchlength )  $maxmatchlength = count($tidarray);
				if ( count($tidarray) < $minmatchlength )  $minmatchlength = count($tidarray);
				$tid1 = $tidarray[0]; $match = $word;

				if ( $withproject ) {
					# Centralized index
					$refname = $settings['projects'][$projectid]['name']; # or $refname = $projectid;
					$purl = $settings['projects'][$projectid]['baseurl'];
					$target = " target=external";
				} else if ( $idfld == "word" ) {
					$refname = "";
				} else {
					$fileid = preg_replace("/xmlfiles\//", "", $fileid );
					if ( $setting['cqp']['resatts'] ) {
						$refname = "attlist";
					} else {
						$refname = "$fileid" ; #:$tid1";
					};
					$purl = "";
					$target = "";
				};
									
				if ( $_POST['style'] == "attlist" ) {
					$rowcnt = count($tidarray) + 1;	$colcnt = count($settings['xmlfile']['pattributes']['forms'])+count($settings['xmlfile']['pattributes']['tags']);
					$maintext .= "<tr>
							<th><a style='font-size: small; margin-right: 10px;' href='{$purl}index.php?action=file&cid=$fileid&jmp=$tid'$target>$refname</a>
							<th colspan='$colcnt'>$lcontext <b>$match</b> $rcontext";
					foreach ( $tidarray as $key => $val ) {
						if ( $username ) $val = "<a href='{$purl}index.php?action=tokedit&cid=$fileid&tid=$val'$target>$val</a>";
						$maintext .= "<tr><td style='color: #bbbbbb;'>$val";
						for ( $i = 0; $i <= count($tidarray)+1; $i++ ) {
							if ( $i != 1 ) $maintext .= "<td>{$featres[$i][$key]}";
						};
					};
				} else if ( $_POST['style'] == "sent" ) {
					if ( $match != "" && substr($line,0,1) != "#" ) $maintext .= "<tr><td><a style='font-size: small; margin-right: 10px;' href='{$purl}index.php?action=file&cid=$fileid&jmp=$tid'$target>$refname</a>
						<td>$lcontext <span class=match>$match</span> $rcontext";
				} else {
					if ( $match != "" && substr($line,0,1) != "#" ) $maintext .= "<tr><td><a style='font-size: small; margin-right: 10px;' href='{$purl}index.php?action=file&cid=$fileid&jmp=$tid'$target>$refname</a>
						<td align=right>$lcontext<td align=center><b>$match</b><td>$rcontext";
					#else $maintext .= "<p>?? $match ".join( " ; ", explode ("\t", $line));
				};
			};
			$maintext .= "</table>";
			$maintext = str_replace('__UNDEF__', '', $maintext);
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
				
	} else {
		# Display the search screen with a CQP box only and a search-help

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>

			$versionheader

			<form action='' method=post id=cqp name=cqp><p>CQP Query: &nbsp; <input name=cql size=80 value='{$cql}' $chareqfn> <input type=submit value=\"Search\"> <a href='index.php?action=$action&act=advanced'>{%advanced}</a></form>
			$chareqjs
			<script language=Javascript>
			function cqpdo(elm) { document.cqp.cql.value = elm.innerHTML; };
			</script>
			";

		$explanation = getlangfile("cqptext", true);
		
		$maintext .= $explanation;
		
		if ( $username ) { 
			$maintext .= " &bull; <a href='index.php?action=$action&act=seq' class=adminpart>Run sequence of queries</a>";
		};
	
	}; 
	$maintext .= "<style>.adminpart { background-color: #eeeedd; padding: 5px; }</style>";

?>