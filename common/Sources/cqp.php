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

	if ( $act == "advanced" || $settings['defaults']['qb'] == "direct" ) $showdirect = true;

	include ("$ttroot/common/Sources/cwcqp.php");

	$outfolder = $settings['cqp']['cqpfolder'] or $outfolder = "cqp";

	// This version of CQP relies on XIDX - check whether program and file exist
	$xidxcmd = findapp('tt-cwb-xidx');
	if ( !$xidxcmd || !file_exists("$outfolder/xidx.rng") ) {
		print "<p>This CQP version works only with XIDX
			<script language=Javascript>top.location='index.php?action=cqpraw';</script>
		";
	};

	# Determine which form to search on by default
	$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";

	$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['searchfolder'];

	if  ( !$corpusfolder ) $corpusfolder = "cqp";

	# Check whether the registry file exists
	if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
		# For backward compatibility, always check the central registry
		$registryfolder = "/usr/local/share/cwb/registry/";
	};
	if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) && !file_exists("cqp/".strtolower($cqpcorpus)) ) {
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
				$moresel = ", match {$key}_audio";
				$offset = 1;
			};
		};
	};
	
	if ( is_array($settings['cqp']['kwicdata']))
	 foreach ( $settings['cqp']['kwicdata'] as $key => $val ) {
		$moresel .= ", match $key";
	};

	$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = $_GET['query'];
	if ( !$cql && $_GET['qid'] && ( $userid || $username ) ) {
		require_once("$ttroot/common/Sources/querymng.php");
		$qid = $_GET['qid'];
		$tmp = getq($qid);
	};
	$cql = stripslashes($cql);


	if ( $act == "download" ) {
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

	} else if ( $act == "seq" ) {
	
		# TODO: check whether we can allow this for visitors
	
		check_login();
	
		if ( !$_POST ) $_POST = $_GET;
		$maintext .= "<h1>Raw CQL Query</h1>
			<p>Below you can type in a sequence of CQL queries for the CQP processor, and the interface will display the final result.
		
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
			$maintext .= "<table class=restable>";
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
		
			# Read the contraction list if it exists (either local or in sharedfolder)
			if ( file_exists("Resources/contractions.txt") ) $contrfile = "Resources/contractions.txt";
			else if ( $sharedfolder && file_exists("$sharedfolder/Resources/contractions.txt") ) $contrfile = "$sharedfolder/Resources/contractions.txt";

			if ( $contrfile ) {
				$contrs = array ( );
				$tmp = file_get_contents($contrfile);
				foreach ( explode("\n", $tmp) as $tmp1 ) {
					$tmp2 = explode("\t", $tmp1);
					$tmp3 = array_shift($tmp2);
					$contrs[$tmp3] = $tmp2;
				};
				
			};
					
			$simple = $cql; $cql = "";
			foreach ( explode ( " ", $simple ) as $swrd ) {
				$swrd = preg_replace("/(?!\.\])\*/", ".*", $swrd);
				if ( $contrs[$swrd] ) {
					if ( $settings['cqp']['dtoks'] == "contr" ) {
						# Use the dtok region, which can be extended to more options easily
						$cql .= "( [$wordfld=\"$swrd\"] | <contr_$wordfld=\"$swrd\"> []+ </contr_$wordfld> ) ";
					} else {
						# Make an option list explicitly
						$sep = ""; $sparts = "";
						foreach ( $contrs[$swrd] as $spart ) {
							$spps = ""; $plst = explode(",", $spart);
							foreach ( $plst as $i => $spp ) {
								if ( $settings['cqp']['dtoks'] == "strict" && count($plst) > 1 ) $drest = " & id=\"d-.*-".($i+1)."\" "; else $drest = "";
								$spps .= "[$wordfld=\"$spp\" $drest ] ";
							};					
							$sparts .= $sep." $spps ";
							$sep = " | ";
						};
						$cql .= "( $sparts ) ";
					};
				} else {
					$cql .= "[$wordfld=\"$swrd\"] ";
				};
			};
						
		};

		# Allow word searches to be defined via URL
		if ( !$cql && $_GET['atts'] ) {
			foreach ( explode ( ";", $_GET['atts'] ) as $att ) {
				list ( $feat, $val ) = explode ( ":", $att );
				$_POST['atts'][$feat] = $val;
			};
		};

		if ( $_GET['preset'] && !$_POST['fromqb'] ) {
			if ( preg_match("/::/", $cql ) ) $sep = "&"; else $sep = "::";
			if ( preg_match("/(.*)( within .*)/", $cql, $matches ) ) { $cql = $matches[1]; $withincond = $matches[2]; };
			foreach ( explode(";", $_GET['preset']) as $tmp ) {
				if ( preg_match("/(.*?):(.*)/", $tmp, $matches )) { 
					if ( !preg_match("/{$matches[1]}/", $cql) ) $cql .= " $sep match.{$matches[1]}=\"{$matches[2]}\"";
				};
				$sep = " & ";
			};
			$cql .= $withincond;
		};

		# This is a document search - turn it into CCQP
		if ( !$cql ) {	// This used to also do [] but that is now done by QB
			$cql = "<text> []";  $sep = "::"; $fileonly = true;
		} else if ( strstr($cql, '<text> [] ::') ) {
			$sep = "&"; $fileonly = true;
		} else if ( strstr($cql, '::') ) {
			$sep = "&";
		} else if ( $_POST['atts'] ) {
			 $sep = "::";
		};

		# Check whether we are asked to only list files
		if ( $_GET['fileonly'] || $_POST['fileonly'] || substr($cql,0,6) == "<text>" ) {
			$fileonly = true;
		};

		$cqpapp = $_POST['cqpapp'] or $cqpapp = $_GET['cqpapp'] or $cqpapp = "$bindir/cqp";
		$cqp = new CQP("", $cqpapp);

		if ( strstr($cqpapp, "tt-cqp") !== false  ) {
			// tt-cqp specific options
			$usettcqp = 1;
			$extannfile = $_POST['extann'] or $extannfile = "Users/ann_{$user['short']}.xml";
			if ( file_exists($extannfile) ) {
				$cqp->exec("load $extannfile my"); // Load the external annotation file
			};
		} else {
			$usettcqp = 0;
			$cqp->exec($cqpcorpus); // Select the corpus
			if ( $_POST['strategy'] && !$fileonly ) {
				$cmd = "set MatchingStrategy {$_POST['strategy']}";
				$cqp->exec($cmd); // Select the corpus
			};
		};


		if ( !$fileonly || $user['permissions'] == "admin" ) $cqltxt = str_replace("'", "&#039;", $cql); # Best not show the query for doc-only searches...

		require_once ("$ttroot/common/Sources/querybuilder.php");

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>{%Corpus Search}</h1>

			$subcorpustit
			$subtit
			$cqlfld

			<script language=Javascript>
			function cqpdo(elm, autorun=false) {
				var newcql;
				if ( typeof(elm) == 'string' ) newcql = elm;
				else newcql = elm.innerHTML;
				document.cqp.cql.value = newcql;
				if ( typeof(code) == 'object') { 
					code.innerText = newcql; 
					dohighlight(code);
				};
				if ( autorun ) document.cqp.submit();
			};
			</script>

			";

		if ( $showdirect ) $maintext .= "\n<!-- auto visualize -->\n<script language=Javascript>showcql('cqlfld'); var direct = 1;</script>";


		if ( $audioelm ) {
			$maintext .= "<script language='Javascript'>var playimg1 = '$playimg';</script>";
			$maintext .= "<script language='Javascript' src=\"$jsurl/audiocontrol.js\"></script>";
			$maintext .= "<div style='display: none;'><audio id=\"track\" src=\"http://alfclul.clul.ul.pt/teitok/site/Audio/mini.wav\" controls ontimeupdate=\"checkstop();\"></audio></div>";
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

		# To make sure that we can modify our query, create a hidden post
		$maintext .= "\n<form action='' method=post id=resubmit name=resubmit>";
		foreach ( $_POST as $key => $val ) {
			$val = str_replace("'", "&#039;", $val);
			$maintext .= "<input type=hidden name=$key value='$val'>";
			if ( is_array($val) ) {
				foreach ( $val as $key2 => $val2 )
					$val2 = str_replace("'", "&#039;", $val2);
					$maintext .= "\n    <input type=hidden id='rs$key$key2' name={$key}[$key2] value='$val2'>";
			} else {
				$val = str_replace("'", "&#039;", $val);
				$maintext .= "\n  <input type=hidden name=$key id='rs$key' value='$val'>";
			};
		};
		$maintext .= "\n</form>";

		# If we have a target in our query, we can do multi-edit
		if ( preg_match("/\@\[/", $cql) ) {
			$targetmatch = 1;
		};

		$qsid = time(); $query = $cql;
		if ( $query && !$qid ) $_SESSION['queries'][$qsid] = array("query" => $query, "ql" => $action);

		$maintext .= $subtit;
		$cqp->exec("Matches = ".$cql);
		$cnt = $cqp->exec("size Matches");
		
		$end = min($cnt, $start + $max); $before = max(0, $start - $max);

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
				$maintext .= " &bull; {%!showing} $start - $end";
			};
			if ( $start > 0 ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$before'; document.resubmit.submit();\">{%previous}</a>";
			if ( $end < $cnt ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$end'; document.resubmit.submit();\">{%next}</a>";
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
						if ( $settings['cqp']['sattributes']['text'][$attit]['values'] == "multi" ) {
							$fatts[$key] = ""; $sep = "";
							foreach ( explode(",", $fatt) as $fattp ) { $fatts[$key] .= "$sep{%$attit-$fattp}"; $sep = ", "; };
						} else $fatts[$key] = "{%$attit-$fatt}";
					};
				};
				$maintext .= "<tr><td><a href='index.php?action=$fileview&cid={$fid}'>{$fidtxt}</a><td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>".join ( "<td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>", $fatts );
			};
			$maintext .= "</table>";

		} else {

			# Text searches
				
			if ( !$settings['cqp']['noipm'] ) {
				$corpsize = $cqp->exec("All = []; size All;");			
				if ( preg_match("/^[^:;]+ :: ([^;:]+)$/", $cql, $matches) ) {
					$globals = $matches[1];
					$relsize = $cqp->exec("Rel = [] :: $globals; size Rel;");		
				};	
			};

			if ( $sort ) {
				# $maintext .= "Sorted by $sort"; - this is not
				$cqp->exec("sort Matches by $sort");
			};

			// $cqpquery = "tabulate Matches $start $end match text_id, match id, matchend id, match[-$context], matchend[$context]";
			$cqpquery = "tabulate Matches $start $end match text_id, match ... matchend id, match, matchend $moresel";
			$results = $cqp->exec($cqpquery);

			if ( $debug ) $maintext .= "<p>From inital $cnt results: $cqpquery<PRE>$results</PRE>";

			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			$maintext .= "<p>$cnt {%results}";
			if ( $corpsize ) $ipm = sprintf("%0.2f", ($cnt/$corpsize)*1000000);
			if ( $ipm ) $maintext .= " &bull; <span title='{%instances per million tokens}'>ipm</span>: <span title='{%over whole corpus}'>$ipm</span> "; 
			if ( $relsize && $relsize != $corpsize ) $ipr = sprintf("%0.2f", ($cnt/$relsize)*1000000);
			if ( $ipr ) $maintext .= " / <span title='{%over subcorpus}: $globals'>$ipr</span> "; 
			if ( $scnt < $cnt ) {
				$maintext .= " &bull; {%!showing} $start - $end";
			};
			if ( $start > 0 ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$before'; document.resubmit.submit();\">{%previous}</a>";
			if ( $end < $cnt ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$end'; document.resubmit.submit();\">{%next}</a>";
			$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>";

			$maxmatchlength = 0;
			$minmatchlength = 1000;

			$showstyle = $_POST['style'] or $showstyle = $settings['cqp']['defaults']['searchtype'] or $showstyle = "kwic";
			$showsubstyle = $_POST['substyle'] or $showsubstyle = $settings['cqp']['defaults']['subtype'];
			if ( $showsubstyle && !$settings['cqp']['sattributes'][$showsubstyle] ) {
				if ( $user['permissions'] == "admin" ) {
					print "Incoherent settings - asked to display context on non-existing &lt;$showsubstyle&gt; - please go to admin > check configuration settings
						<script language=Javascript>top.location='index.php?action=admin&act=configcheck';</script>";
					exit;					
				} else {
					if ( $settings['cqp']['sattributes']['s'] ) $showsubstyle = "s";
					else if ( $settings['cqp']['sattributes']['p'] ) $showsubstyle = "p";
					else $showstyle = "kwic";
				};
			};

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
				$resultarray = explode ( "\t", $line );
				list ( $fileid, $match, $leftpos, $rightpos, $audiofile ) = $resultarray;
				$idlist = explode ( " ", $match );
				if ( count($idlist) > $maxmatchlength )  $maxmatchlength = count($idlist);
				if ( count($idlist) < $minmatchlength )  $minmatchlength = count($idlist);
				$m1 = $idlist[0];  $m1x = $m1;
				$m2 = end($idlist);   $m2x = $m2;

				$cmd = "$xidxcmd --filename='$fileid' --cqp='$outfolder' $expand $leftpos $rightpos";
				$resxml = shell_exec($cmd);
				if ( $debug ) $maintext .= "<pre>$cmd\n".htmlentities($xidxres)."</pre>";

				$fileid = preg_replace("/xmlfiles\//", "", $fileid );

				$m1 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m1 );
				$m2 = preg_replace("/d-(\d+)-\d+/", "w-\\1", $m2 );

				if ( $audioelm && $audiofile ) {
					if ( preg_match("/start=\"([^\"]*)\"/", $resxml, $matches ) ) $strt = $matches[1]; else $strt = 0;
					if ( preg_match("/end=\"([^\"]*)\"/", $resxml, $matches ) ) $stp = $matches[1]; else $stp = 0;
					if ( $settings['defaults']['playbutton'] ) $playimg = $settings['defaults']['playbutton'];
					else  if ( file_exists("Images/playbutton.gif") ) $playimg = "Images/playbutton.gif";
					else $playimg = "http://alfclul.clul.ul.pt/teitok/site/Images/playbutton.gif";
					$audiobut = "<td><img src=\"$playimg\" width=\"14\" height=\"14\" style=\"margin-right: 5px;\" onClick=\"playpart('$audiofile', $strt, $stp, this );\"></img></td>";
				};

				# Now, clean the resulting XML in various ways to make it display better

				# XMLIDX does not work perfectly, so if we just missed the <tok>, repair it
				if ( substr($resxml, 0,3) == "tok" || substr($resxml, 0,4) == "dtok" ) { $resxml = "<".$resxml; }; # Missing the beginning of <tok>
				if ( substr($resxml, -4) == "</tok" ) { $resxml = $resxml.">"; }; # Missing the end of </tok>
				$resxml = preg_replace("/<[^<>]+$/", "", $resxml); # A bit the end of a tag
				$resxml = preg_replace("/^[^<>]+>/", "", $resxml); # A bit the beginnin of a tag
				$resxml = preg_replace("/<tok [^<>]+>[^<>]+\n?$/", "", $resxml); # Half-words at the end (to prevent broken HTML tags - alternatively: &#[^;]+$)

				# Replace block-type elements by vertical bars
				$resxml = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='\\1'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(doc)>\s*|<(doc) [^>]*>\s*)+/", " <span style='color: #995555; font-weight: bold;' title='\\1'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<(lb|br|cb|sb)[^>]*>)\s*/", " <span style='color: #aaffaa' title='\\1'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<pb[^>]*>\s*)+/", " <span style='color: #ffaaaa' title='\\1'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(table|cell|row)(?=[ >])[^>]*>\s*)+/", " ", $resxml);

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
					$resxml = preg_replace ( "/(<tok[^>]*id=\"$m1\")/", "</td><td style='text-align: center; font-weight: bold;' m1=\"$m1x\" m2=\"$m2x\">\\1", $resxml);
					$resxml = preg_replace ( "/(id=\"$m2\".*?<\/tok>)/smi", "\\1</td><td style='text-align: $rca;'>", $resxml);
					$resstyle = "style='text-align: $lca;'";
					// TODO: This colors dtoks pink in KWIC only when they are partially part of the result - but for the final token does not work if more than 2 dtoks
					$tmp = explode(" ", $match); $m1t = array_shift($tmp); $m2t = array_pop($tmp); if ( !$m2t ) $m2t = $m1t;
					if ( preg_match("/d-.*-[^1]/", $m1t ) ) $moreactions .= "\nhllist('$m1t', 'r-$i', '#ffffff'); ";
					if ( preg_match("/d-.*-1/", $m2t ) ) $moreactions .= "\nhllist('$m2t', 'r-$i', '#ffffff'); ";
				};

				if ( $settings['cqp']['kwicdata'] ) {
					$metainfo = ""; $idx = $offset+4;
					foreach ( $settings['cqp']['kwicdata'] as $key => $val ) {
						$attit = pattname($key); 
						$attval = $resultarray[$idx]; $idx++;
						if ( $attval == "_" ) $attval = "";
						list ( $kds, $kda ) = explode("_", $key); 
						if ( $settings['cqp']['sattributes'][$kds][$kda]['translate'] ) $attval = "{%$kda-$attval}";
						$style = ""; if ( $val['color'] ) $style = " style=\"color: {$val['color']}\"";
						$metainfo .= "<td title='{%$attit}' class='kwic_$key' $style>$attval</a>";
					};
				};
				
				if ( !$noprint ) $editxml .= "\n<tr id=\"r-$i\" tid=\"$fileid\"><td><a href='index.php?action=$fileview&amp;cid=$fileid&amp;jmp=$match' id=\"$fileid:$match\" style='font-size: 10pt; padding-right: 5px;' cid='$fileid' onmouseover=\"showdocinfo(this)\" target=view>{%context}</a></td>
					$audiobut
					<td $resstyle>$resxml</td>$metainfo</tr>";


			};

			# empty tags are working horribly in browsers - change
			$editxml = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $editxml );

			#Build the view options
			$attnamelist = "var attributenames = [];";
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

	// Load the tagset 
	$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
	$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	require_once ( "$ttroot/common/Sources/tttags.php" );
	$tttags = new TTTAGS($tagsetfile, false);
	if ( $tttags->tagset['positions'] ) {
		$tmp = $tttags->xml->asXML();
		$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
		$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
	};

			$maintext .= "
					$viewoptions $showoptions
					<hr>
				<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
				$countrow
				<div id='mtxt' mod='$action' $textdir><text><table class='kwictable' $direc>$editxml</table></text></div>

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
						$settingsdefs;
						var lang = '$lang';
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
		$cqlu = str_replace("'", "&#039;", $cqlu);
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
			<a onclick=\"document.cqlform.action = 'index.php?action=$action&act=download'; document.cqlform.submit();\">{%Download results}</a>
			";
		$cqll = str_replace("'", "&#039;", $cql);

		if ( $subtit ) $cqptit = "&cqltit=".urlencode($subtit);
		#$maintext .= " - <a href='index.php?action=querymng&act=save&cql=".urlencode($cqll)."$cqptit'>{%store query}</a>";
		$qsid = time(); $query = $pattern;
		if ( $query && !$qid ) $_SESSION['queries'][$qsid] = array("query" => $query, "ql" => $action);
		$maintext .= " &bull; <a onclick='submitq();'>{%store this query}</a>
			<script>
				function submitq() {
					var qf = document.getElementById('cqp');
					qf.setAttribute('action', 'index.php?action=querymng&type=$action&act=save');
					qf.submit();
				};
			</script>";
		# $maintext .= " - <a href='index.php?action=cqp&cql=".urlencode($cqll)."$cqptit'>{%Direct URL}</a>";

		$useridtxt = $shortuserid;
		$qfldr = preg_replace("/[^a-z0-9]/", "", strtolower($userid));
		$qfn = "Users/$qfldr/queries.xml";
		if ( file_exists($qfn) || file_exists("Resources/queries.xml") ) $maintext .= " &bull; <a href='index.php?action=querymng&type=$action'>{%stored queries}</a>";
// 		if ( $_SESSION['myqueries'] || file_exists("Users/cql_$useridtxt.xml") )
// 			$maintext .= " - <a href='index.php?action=multisearch&act=stored&cql=".urlencode($cqll)."'>{%Stored CQL queries}</a>";


		# Do not allow frequency counts if we already have a pre-select CQL
		if ( !findapp("tt-cqp") && 1==2 ) { // We now created a backdoor - TODO: make this better
			if ( $username )
			$maintext .= "<hr><div class=adminpart>
				<p>The corpus frequency options in TEITOK rely on tt-cqp, which does not
				seem to be installed on the server. In order to provide statistical data,
				please ask you administrator to install it.
				</div>";
		} else if ( !$precql && !$nomatch && !$fileonly ) { # We actually do want text-based searches
			$maintext .= "<hr>";

			$maintext .= "<div style='display: block;' id='freqopts' name='freqopts'>
				<h2>{%Frequency Options}</h2>
				<form action='index.php?action=visualize' id=freqform name=freqform method=post>
				";


			# Frequency distribution
			foreach ( $settings['cqp']['frequency'] as $key => $val ) {
				if ( !is_array($val) || $val['type'] == "group" ) continue; # Skip attributes and separator TODO: keep separators in pulldown?
				if ( ( !$fileonly || preg_match("/text_/", $val['key']) ) ) {
					$display = $val['long'] or $display = $val['display'];
					if ( $val['type'] == "freq" ) {
						$freqlist[$val['key']] = 1;
						$freqopts .= "<option value=\"{$val['key']}\">{%$display}</option>";
					} else $nofreqopts .= "<p><a onclick=\"document.freqform.query.value = '{$val['key']}'; document.freqform.submit();\">{%$display}</a>";
				};
			};
			if ( !$fileonly && $minmatchlength == 1 )
			 foreach ( $settings['cqp']['pattributes'] as $key => $att ) {
				if ( ( $att['nosearch'] || $att['freq'] == "no" || ( $att['admin'] && !$username ) ) && $att['freq'] != "yes" ) continue; # Skip non-searchable fields (unless explicitly freqable)
				if ( $freqlist[$key] ) continue; # Skip attributes already listed explicitly
				$pattname = pattname($key);
				$freqlist[$key] = 1;
				$collopts .= "<option value=\"$key\">{%$pattname}</option>";
				$freqopts .= "<option value=\"$key\">{%$pattname}</option>";
			};
			foreach ( $settings['cqp']['sattributes'] as $lvl => $tmp ) {
				if ( !$tmp['display'] && $val['freq'] != "yes" ) continue; # Skip non-searchable levels (unless explicitly freqable)
				foreach ( $tmp as $key => $val ) {
					if ( !is_array($val) ) continue;
					if ( ( $val['nosearch'] || $val['freq'] == "no"  || ( $att['admin'] && !$username ) ) && $val['freq'] != "yes" ) continue; # Skip non-searchable fields (unless explicitly freqable)
					$fkey = $lvl."_".$key;
					if ( $freqlist[$fkey] ) continue; # Skip attributes already listed explicitly
					$pattname = pattname($fkey);
					$freqlist[$fkey] = 1;
					$freqopts .= "<option value=\"$fkey\">{%$pattname}</option>";
				};
			};
			if ( !$freqlist['text_id'] ) $freqopts .= "<option value=\"text_id\">{%Text}</option>";
			$freqopts .= "<option value=\"custom\">Custom distribution</option>";
			
			if ( $usettcqp ) $maintext .= "<p>{%Collocation by}:
				<input type=hidden name=mode value=''>
				<select name='fld'>
				<option value=''>[{%select}]</option>
				$collopts
			</select>
				| {%Context size}: <select name='context'><option value=1>1</option><option value=2>2</option><option value=3>3</option><option value=4>4</option><option value=5>5</option></select>
				| {%Direction}: <select name='dir'><option value='-'>{%Left}</option><option value='+'>{%Right}</option><option value='' selected>{%Left and Right}</option></select>
				<input type=submit onClick=\"return collchoose();\"/>
				";

			// TODO: what happened to the context by sattribute?

			$maintext .= "<p>{%Frequency by}: <select onchange=\"freqchoose(this.value);\">
				<option value=''>[{%select}]</option>
				$freqopts
			</select>
			<p>$nofreqopts</p>
			<script language=Javascript>
			function collchoose () {
				if ( document.freqform.fld.value != '' ) {
					document.freqform.mode.value = 'collocations';
					document.freqform.submit();
				} else {
					return false;
				};
			};
			function freqchoose (val) {
				if ( val == 'custom') {
					document.getElementById('customfreq').style.display = 'block';
				} else {
					document.freqform.query.value = 'group Matches match.' + val; document.freqform.submit();
				};
			};
			</script>";


			$maintext .= "<div id='customfreq' style='display: none;'><p>Specifiy additional custom CQP command on the results above (Matches):


					{%CQL Query}:
					<input type=hidden name=cql value='$cqlu' $chareqfn>
					<input name='query' value='group Matches match lemma' size=70>
					<input type=submit value='{%Apply}'>
				</form>
				</div>
				<br></div>
				";
		};

	} else {

		# Display the search screen with a CQP box only and a search-help

		if ( $_GET['cid'] ) {
			$pagetit = "{%Search in document}";
			require ("$ttroot/common/Sources/ttxml.php");
			$ttxml = new TTXML($cid, false);
			$subtit .= "<h2>".$ttxml->title()."</h2>";
			$subtit .= $ttxml->tableheader();

			$postaction = "index.php?action=file&cid=".$ttxml->fileid;
		} else $pagetit = "{%Corpus Search}";

		require_once ("$ttroot/common/Sources/querybuilder.php");

		$maintext .= "<h1 style='text-align: left; margin-bottom: 20px;'>$pagetit</h1>

			$subcorpustit
			$subtit
			$cqlfld

			<script language=Javascript>
			function cqpdo(elm, autorun = false) {
				var newcql;
				if ( typeof(elm) == 'string' ) newcql = elm;
				else newcql = elm.innerHTML;
				document.cqp.cql.value = newcql;
				if ( typeof(code) == 'object') { 
					code.innerText = newcql; 
					dohighlight(code);
				};
				if ( autorun ) document.cqp.submit();
			};
			</script>
			<link rel=\"stylesheet\" href=\"http://maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css\">
			<style>
			@font-face { ... }
			div[onclick] { 
				cursor: pointer;
			}
			div[onclick]:before { 
				font-family: \"FontAwesome\"; font-weight: 100; content: \"\\f021\" ' ';
				color: #aaaaaa;
			}
			</style>
			";

		$explanation = getlangfile("cqptext", true);

		$maintext .= $explanation;

		if ( $username ) { 
			$maintext .= " &bull; <a href='index.php?action=$action&act=seq' class=adminpart>Run sequence of queries</a>";
		};

		if ( $showdirect ) $maintext .= "\n<!-- auto open -->\n<script language=Javascript>showqb('cqlfld'); var direct = 1;</script>";

	};

?>
