<?php

	# Show Google Visualization for data
	if ( !$_POST ) $_POST = $_GET;

	$cntcols = 1; $headrow = 'false';
	$ttcqp = findapp("tt-cqp");

	if ( $_GET['cwb'] || getset('cqp/ttcqp') == "0" ) $usecwb = 1; # tt-cqp is no longer supported

	if ( $act == "cql" ) {

			$maintext .= "<h1>{%Statistics}</h1>

				<form action='index.php?action=$action' method=post>
					<p>{%Search Query}: <input size=70 name=cql value=\"{$_GET['cql']}\">
					<p>{%Grouping Query}: <input size=70 name=query value=\"{$_GET['query']}\">
					<hr>
					<input type=submit value=\"{%Show}\">
				</form>";


	} else {

		if ( $_GET['json'] or $_POST['json'] ) {


			$title = $_POST['title'] or $title = "Data Visualization";
			$maintext .= "<h1>$title</h1>";

			if ( $_POST['description'] ) {
				$maintext .= "<p>{$_POST['description']}</p><hr>";
			};

			$json = $_GET['json'] or $json = $_POST['json'];

		} else if ( $act == "compare" ) {

			$maintext .= "<h1>{%Query Comparison}</h1>";
			foreach ( $_POST['myqueries'] as $cql => $val ) {
				$sq = $_SESSION['myqueries'][$cql] or $sq = $val;
				if ( $sq['cql'] == "" ) continue;
				$display = $sq['name'] or $display = $sq['display'] or $display = $cql;
				if ( $ttcqp && !$usecwb ) {
					$cmd = "echo 'Matches = {$sq['cql']}; size Matches $fld;'| $ttcqp";
					$num = shell_exec($cmd); $num = chop($num) + 0;
				} else {
					# Fallback without tt-cqp
					require_once ("$ttroot/common/Sources/cwcqp.php");
					$registryfolder = getset('cqp/defaults/registry', "cqp");
					$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
					$cqpfolder = getset('cqp/searchfolder', 'xmlfiles');
					if  ( !$corpusfolder ) $corpusfolder = "cqp";
					# Check whether the registry file exists
					if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
						# For backward compatibility, always check the central registry
						$registryfolder = "/usr/local/share/cwb/registry/";
					};
					if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
						fatal ( "Corpus $cqpcorpus has no registry file" );
					};
				
					$cqp = new CQP();
					$cqp->exec($cqpcorpus); // Select the corpus
					$cqp->exec("set PrettyPrint off");
					$cqpquery = "Matches = {$sq['cql']}";
					$cqp->exec($cqpquery);
					$num = $cqp->exec("size Matches"); $num = $num + 0;
				};
				$data .= "\n\t['$display', $num], ";
			};
			$json = "[[{'id':'form', 'label':'{%Search Query}'},  {'id':'count', 'label':'{%Count}', 'type':'number'} ], $data]";
			$nodirect = 1; // This relies on POST

		} else if ( $_GET['cql'] or $_POST['cql'] ) {

			if ( getset('cqp/defaults/registry') != '' ){
				$registryfolder = getset('cqp/defaults/registry', "cqp");
				$reg = " --cqlfolder={$registryfolder}";
			};

			$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";
			$cql = preg_replace("/[\n\r]/", " ", $cql);

			$cql = preg_replace("/(;.*)>.*/", "\\1", $cql);

			if ( preg_match("/ *\[([^\]]+)\](?: *within .*)?$/", $cql, $matches) || preg_match("/ *\[([^\]]+)\] *:: *(.*?)(?: *within .*)?$/", $cql, $matches) ) {
				$pmatch = $matches[1]; $smatch = $matches[2];
				if ( $smatch ) $srest = " :: $smatch"; # TODO : Check if we want to keep everything
				foreach ( explode ( ' & ', $pmatch ) as $pmp ) {
					if ( preg_match("/ *([^ ]+) *= *\"([^\"]*)\" */", $pmp, $matches) ) $cqlname .= "<p>{%".pattname($matches[1], false)."} = <b>".$matches[2]."</b>";
					else $cqlname .= "<p>$pmp";
				};
				foreach ( explode ( ' & ', $smatch ) as $smp ) {
					if ( preg_match("/match\.([^ ]+) *= *\"([^\"]*)\"/", $smp, $matches) ) $cqlname .= "<p>{%".pattname($matches[1])."} = <b>".$matches[2]."</b>";
					else $cqlname .= "<p>$smp";
				};
			};

			if ( $cqlname ) {
				$cqltxt = "<span title='".htmlentities($cql)."'>$cqlname</span>";
			} else {
				$cqltxt = htmlentities($cql);
			};

			if ( $act == "keywords" || $_POST['mode'] == "keyness" ) {
				$maintext .= "<h1>{%Keywords}</h1>";


				$fld = $_POST['fld'] or $fld = "word";
				$fldname = pattname($fld);

				if ( getset('cqp/frequency/refcorpus') != "" ) {
					$refcorpus = getset('cqp/frequency/refcorpus');
					$refcorpustxt = getset('cqp/frequency/refcorpustext', $refcorpus);
				} else {
					$refcorpus = $cqpcorpus;
					$refcorpustxt = "(internal)";
				};

				$tmpfile = time();

				$maintext .= "<table>
								<tr><th>{%Search Query}:<td>$cqltxt</tr>
								<tr><th>{%Keyness}:<td><span title='$cmd'>{%Field}: $fld, {%Reference corpus}: $refcorpustxt</span></tr>
							</table>";

				$cmd = "echo 'Matches = $cql; stats Matches $fld :: type:keywords context:$dir$context' | $ttcqp --output=json";
				$json = shell_exec($cmd);

				$fldname = pattname($fld);
				$cmd = "join tmp/$tmpfile.2.txt tmp/$tmpfile.3.txt | perl $ttroot/common/Scripts/collocate.pl --selsize=$size --corpussize=$corpussize --fldname='$fldname' --span=1";
				$json = shell_exec($cmd);

			} else if ( $act == "collocations" || $_POST['mode'] == "collocations" ) {

				$maintext .= "<h1>{%Collocations}</h1>";
				$tmpfile = time();

				$moredirect .= "&context=".urlencode($_POST['context']);
				$moredirect .= "&dir=".urlencode($_POST['dir']);
				$moredirect .= "&fld=".urlencode($_POST['fld']);

				$context = $_POST['context'] or $context = 5;
				$dirdir = array ( "" => "Left/Right", "-" => "Left", "+" => "Right"  );
				$dir = $_POST['dir'] or $dir = ""; $dirtxt = "{%{$dirdir[$dir]}}";
				$fld = $_POST['fld'] or $fld = "word";
				$fldname = pattname($fld);

				$maintext .= "<table>
								<tr><th>{%Search Query}:<td>$cqltxt</tr>
								<tr><th>{%Collocates}:<td><span title='$cmd'>{%Direction}: $dirtxt; {%!context}: $context; {%Field}: {%$fldname}</span></tr>
							</table>";

				if ( $ttcqp && !$usecwb && 1==2  ) {
					# Use tt-cqp by default
					$cmd = "echo 'Matches = $cql; stats Matches $fld :: context:$dir$context' | $ttcqp --output=json";
					if ( $debug ) $maintext .= "<!-- $cmd -->";
					$json = shell_exec($cmd);
				} else {
					require_once ("$ttroot/common/Sources/cwcqp.php");
					$registryfolder = getset('cqp/defaults/registry',  "cqp");
					$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
					$cqpfolder = getset('cqp/searchfolder');
					if  ( !$corpusfolder ) $corpusfolder = "cqp";
					# Check whether the registry file exists
					if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
						# For backward compatibility, always check the central registry
						$registryfolder = "/usr/local/share/cwb/registry/";
					};
					if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
						fatal ( "Corpus $cqpcorpus has no registry file" );
					};
				
					$cqp = new CQP();
					$cqp->exec($cqpcorpus); // Select the corpus
					$cqp->exec("set PrettyPrint off");
					$cqpquery = "Matches = $cql";
					$cqp->exec($cqpquery);
					$num = $cqp->exec("size Matches"); $num = $num + 0;
					$sep = "";
					$fld = $_POST["fld"] or $fld = "form";
					if ( $_POST['dir'] == "" || $_POST['dir'] == 'left'  ) {
						if ( $_POST['context'] == 1 ) $what .= $sep."match[-1] $fld";
						else if ( $_POST['context'] > 1 ) $what .= $sep."match[-{$_POST['context']}]..match[-1] $fld";
						$sep = ", ";
					};
					if ( $_POST['dir'] == "" || $_POST['dir'] == 'right'  ) {
						if ( $_POST['context'] == 1 ) $what .= $sep."match[1] $fld";
						else if ( $_POST['context'] > 1 ) $what .= $sep."matchend[1]..matchend[{$_POST['context']}] $fld";
						$sep = ", ";
					};
					$tabql = "tabulate Matches $what;";
					$res = $cqp->exec($tabql);
					$resarr = preg_split("/\s+/", $res);
					$colls = array_count_values($resarr);
					$json = json_encode($colls);
					# This is not the right json format {"a": 2, "b": 1}
				};
							
				$headrow = "false";

				$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Observed frequency}'>Observed</option><option value=4 title='{%Chi Square}'>{%Chi Square}</option><option value=5 title='{%Mutual Information}'>{%Mutual Information}</option></select>";
				$cntcols = 5;

			} else {
				$maintext .= "<h1>{%Corpus Distribution}</h1>";

				$moredirect = "&query=".urlencode($_POST['query']);

				$grquery = $_POST['query'] or $grquery = $_GET['query'] or $grquery = "group Matches match.word";
				if ( $ttcqp && !$usecwb && 1==2   ) { # Not using TT-CQP anymore
				
					// Use tt-cqp by default

					$cmd = "echo 'Matches = $cql; $grquery;' | $ttcqp --output=json";
					if ( $debug ) $maintext .= "<!-- $cmd -->";
					$json = shell_exec($cmd);

					// Bug in tt-cqp: remove lines with no category
					$json = preg_replace("/\n\[[^'][^\]]+\],\s*/", "", $json);
					
				} else {

					// Fallback in case tt-cqp is not installed
					// TODO: resolve/improve this fallback

					include ("$ttroot/common/Sources/cwcqp.php");
					$registryfolder = getset('cqp/defaults/registry', "cqp");
					$cqpcorpus = getset('cqp/corpus', "tt-".$foldername);
					if ( getset('cqp/subcorpora') != "" ) {
						$subcorpus = $_GET['subc'] or $subcorpus = $_SESSION['subc-'.$foldername];
						if ( !$subcorpus ) {
							fatal("No subcorpus selected");
						};
						$_SESSION['subc-'.$foldername] = $subcorpus;
						# We might need to check if the corpus name does (not) start with the full corpus
						$cqpcorpus = strtoupper("$subcorpus"); # a CQP corpus name ALWAYS is in all-caps
						$cqpfolder = "cqp/$subcorpus";
						$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
						$subcorpustit = "<h2>$corpusname</h2>";
						$maintext .= $subcorpustit;
					} else {
						$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
						$cqpfolder = getset('cqp/cqpfolder', "cqp");
					};

					# Check whether the registry file exists
					if ( !file_exists($registryfolder.strtolower($cqpcorpus)) && file_exists("/usr/local/share/cwb/registry/".strtolower($cqpcorpus)) ) {
						# For backward compatibility, always check the central registry
						$registryfolder = "/usr/local/share/cwb/registry/";
					};
					if ( !file_exists($registryfolder.'/'.strtolower($cqpcorpus)) ) {
						fatal ( "Corpus $cqpcorpus has no registry file" );
					};

					// Turn the grquery back into normal CQL
					$grquery = preg_replace("/([^ ]+)\.([^ ]+)/", "\\1 \\2", $grquery);

					$cqp = new CQP();
					$cqp->exec($cqpcorpus); // Select the corpus
					$cqp->exec("set PrettyPrint off");
					$cqpquery = "Matches = $cql";
					$cqp->exec($cqpquery);
					$totcnt = $cqp->exec("size Matches");
					$cqpresults = $cqp->exec($grquery);
					
					if ( getset('cqp/nowpm') == "" ) {
						# Determine what to use as reference query
						if ( preg_match("/(.+?) (:: .+)/", $cql, $matches) ) {
							$qp['local'] = $matches[1]; $qp['global'] = $matches[2];
						} else {
							$qp['local'] = $cql;
						};
											
						if ( preg_match("/group Matches ([^ ]+)[. ](.+)/", $grquery, $matches) ) {
							$qp['pos'] = $matches[1]; $qp['att'] = $matches[2];
							if ( preg_match("/(.+?)_+(.+)/", $qp['att'], $matches) ) {
								if ( $qp['global'] ) {
									$refquery = "[] {$qp['global']}";
									$qp['satt'] = $matches[1]; $qp['attname'] = $matches[2];
									$refgr = "group All {$qp['pos']} {$qp['att']}";
									$cqp->exec("All = $refquery");
									$tmp = "size All";
									$refcnt = $cqp->exec($tmp);
									$resall = $cqp->exec($refgr);
									foreach ( explode("\n", $resall) as $line ) {
										list ( $grp, $cnt ) = explode ( "\t", $line );
										$allcnt[$grp] = $cnt;
									};
									$refrow .= "<tr><th>{%Reference size} ({%total})<td title='$refquery'>".$refcnt;
								} else {
									# Nothing to compare to
								};
							} else {
								$refquery = "[] {$qp['global']}";
								$cqp->exec("All = $refquery");
								$tmp = "size All";
								$allcnt['All'] = $cqp->exec($tmp);
								$refrow .= "<tr><th>{%Reference size}<td title='$refquery'>".$allcnt['All'];
							};
						};
					};
					
					if ( $refquery ) {
						$label = "Group"; # {%Group}
						$json = "[[{'id':'grp', 'label':'{%$label}'}, {'id':'count', 'label':'{%Count}', 'type':'number'}, {'id':'wpm', 'label':'{%WPM}', 'type':'number'}, {'id':'perc', 'label':'{%Percent}', 'type':'number'}], ";
						foreach ( explode("\n", $cqpresults) as $line ) {
							list ( $grp, $cnt ) = explode ( "\t", $line );
							$wpm = 0; 
							if ( $allcnt[$grp] ) $relcnt = $cnt/$allcnt[$grp];
								else if ( $allcnt['All'] ) $relcnt = $cnt/$allcnt['All'];
							if ( $relcnt ) $wpm = sprintf("%0.2f", ($relcnt)*1000000);
							$grp = str_replace("'", "\\'", $grp); # Protect '
							$perc = sprintf("%0.2f", ($cnt/$totcnt)*100);
							if ( $grp && $cnt ) $json .= "['$grp', $cnt, $wpm, $perc], ";
						};
						$json .= "]";
					} else {
						$label = "Group"; # {%Group}
						$json = "[[{'id':'grp', 'label':'{%$label}'}, {'id':'count', 'label':'{%Count}', 'type':'number'}, {'id':'perc', 'label':'{%Percent}', 'type':'number'}], ";
						foreach ( explode("\n", $cqpresults) as $line ) {
							list ( $grp, $cnt ) = explode ( "\t", $line );
							if ( $grfld && $istranslatable ) { $grp = "$grfld-$grp"; }; # TODO: check whether the field should be translated
							$grp = str_replace("'", "\\'", $grp); # Protect '
							$perc = sprintf("%0.2f", (intval($cnt)/intval($totcnt))*100);
							if ( $grp && $cnt ) $json .= "['$grp', $cnt, $perc], ";
						};
						$json .= "]";
					}
										

					if ( preg_match("/group Matches match (.*)/", $grquery, $matches ) ) {
						$grfld = $matches[1];
						$pn = pattname($grfld);
						$qp['display'] = $pn;
						$grtxt = "<span title='".htmlentities($grquery)."'>{%$pn}</span>";
					} else $grtxt = $grquery;

				};

				if ( preg_match("/Error: (.*)/", $json, $matches) ) {
					$tterror = $matches[1];
					if ( preg_match("/failed to open: cqp\/(.*)\.corpus/", $json, $matches) ) {
						$errortxt = "Incorrect request: This corpus has no attribute <i>{$matches[1]}</i>";
					} else if ( preg_match("/failed to open: cqp\/(.*)\.rng/", $json, $matches) ) {
						$errortxt = "Incorrect request: This corpus has no attribute <i>{$matches[1]}</i>";
					} else $errortxt = $tterror;
					fatal("$errortxt");
				};

				# See if we can find a name for this query
				if ( !$grtxt && preg_match("/group Matches (.*)/", $grquery, $matches ) ) {
					$groupflds = explode(" ", $matches[1]); $sep = "";
					foreach ( $groupflds as $grfld ) {
						$grfld = preg_replace("/.*\./", "", $grfld);
						if ( !$mainfld ) $mainfld = $grfld;
						$pn = pattname($grfld);
						$qp['display'] = $pn; 
						$grnames .= $sep."<b>{%$pn}</b>"; $sep = " + ";
					};
					$grtxt = $grnames;
				} else if ( !$grtxt ) $grtxt = $grquery;
	  					
				$maintext .= "<script>var qp = ".array2json($qp)."</script>";
  		
				$cqllink = urlencode($cql);
				$maintext .= "<table>
								<tr><th>{%Search Query}<td><a style='color: black;' href='index.php?action=cqp&cql=$cqllink'>$cqltxt</a></tr>
								<tr><th>{%Group query}<td>$grtxt</tr>
								<tr><th>{%Total}<td>$totcnt</tr>
								$refrow
							</table>";

				$wpmdesc = "{%Words per million}"; $wpmtxt = "WPM";
				if ( strpos($json, "%Tot") != false ) {
					$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Corpus occurrences}'>{%Count}</option><option value=2 title='{%Total occurrences}'>{%Total}</option><option value=3 title='$wpmdesc'>$wpmtxt</option></select>";
					$cntcols = 3;
				} else {
					$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Corpus occurrences}'>{%Count}</option><option value=3 title='$wpmdesc'>$wpmtxt</option></select>";
					$cntcols = 2;
				};

				$headrow = "false";
				$cqltxt = str_replace("'", "&#039;", $cql);

			};
			$maintext .= "<hr>";

			if ( $debug ) $maintext .= $debugtxt;

		} else {

			# TODO: Should we provide some default JSON?

		};

	if ( $json ) {

		if ( is_array(getset('geomap')) ) $apikey = getset('geomap/apikey'); # Use our key when no other key is defined

		if ( ( $mainfld == "text_geo" || $fldi[0]['var'] == "geo" ) && $apikey  ) { $moregs .= "<option value='geomap'>{%Map Chart}</option><option value='geochart'>{%Geo Chart}</option>"; $morel = ", 'map', 'geochart'";  $moreo = ", 'mapsApiKey': '$apikey'"; };

		if ( $_GET['charttype'] ) $inittype = "'{$_GET['charttype']}'";
				$maintext .= "
					<div id='linkfield' style='float: right; z-index: 100; cursor: pointer;'></div>
					<p>
					{%Graph}:
					<select name=graph id=graphselect onChange=\"drawGraph(this.value);\">
					<option value='table'>{%Table}</option>
					<option value='pie'>{%Pie}</option>
					<option value='piehole'>{%Donut}</option>
					<option value='bars'>{%Bar Chart}</option>
					<option value='lines'>{%Line Chart}</option>
					<option value='scatter'>{%Scatter Chart}</option>
					<option value='histogram'>{%Histogram}</option>
					<option value='trendline'>{%Trendline}</option>
					<option value='totals'>{%Statistics}</option>
					$moregs
					</select>
					$wpmsel
					|
					{%Download}:
					<select name=download onClick=\"downloadData(this.value);\">
					<option value=''>[{%select}]</option>
					<option value='svg' class='imgbut' title='Download image as Scalable Vector Graphics'>SVG</option>
					<option value='png' class='imgbut' title='Download image as Portable Network Graphics'>PNG</option>
					<option value='csv' title='Download data as Comma-Separated Values'>CSV</option>
					<option value='json' title='Download data in Javascript Object Notation'>JSON</option>
					</select>
					</p>
					<div style='width: 100%;' id=googlechart></div>
					";

		$maintext .= "<hr><p><a target=help href='http://teitok.corpuswiki.org/site/index.php?action=help&id=visualize'>{%!help}</a>";

		if ( $cql && !$_GET['cql'] && !$nodirect ) {
			$maintext .= " &bull; <a href='index.php?action=$action&cql=".urlencode($cql)."&mode={$_POST['mode']}".$moredirect."'>{%Direct URL}</a>";
		};

			# Create the actual visualization
			$maintext .= "
				<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>
				<script type=\"text/javascript\" src=\"https://cdn.jsdelivr.net/npm/jstat@latest/dist/jstat.min.js\"></script>
				<script type=\"text/javascript\" src=\"$jsurl/visualize.js\"></script>
				<div id='notloaded'><i>The visualization in TEITOK relies on the Google visualization library, which does not seem to have been loaded correclty.</i></div>
				<script type=\"text/javascript\">

			google.charts.load('current', {'packages':['corechart', 'table', 'bar', 'line', 'scatter' $morel ] $moreo });
			document.getElementById('notloaded').style.display = 'none';

			var json = $json;
			var cql = '".str_replace("'", "&#039;", $cql)."';
			cnttype = 1;
			headrow = $headrow;
			cntcols = $cntcols;
			var viewport = document.getElementById('googlechart');
			google.charts.setOnLoadCallback(function() { drawGraph($inittype); });

			</script>
			";

		} else {

			$maintext .= "<h1>Data Visualization</h1>";
			$maintext .= "<p>No data to visualize";

		};
	};


?>
