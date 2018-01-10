<?php

		# Show Google Visualization for data
		if ( !$_POST ) $_POST = $_GET;
		
		$cntcols = 1; $headrow = 'false';

		if ( $act == "cql" ) {
		
				$maintext .= "<h1>CQP Statistics</h1>
		
					<p>Below you can define a base query, and a grouping query
			
					<form action='index.php?action=$action' method=post>
						<p>Base query: <input size=70 name=cql value=\"{$_GET['cql']}\">
						<p>Grouping query: <input size=70 name=query value=\"{$_GET['query']}\">
						<hr>
						<input type=submit value=Show>
					</form>";
		
				
		} else {
		
			if ( $_GET['json'] or $_POST['json'] ) {
	
	
				$title = $_POST['title'] or $title = "Data Visualization";
				$maintext .= "<h1>$title</h1>";
		
				if ( $_POST['description'] ) {
					$maintext .= "<p>{$_POST['description']}</p><hr>";
				};
		
				$json = $_GET['json'] or $json = $_POST['json'];
		
			
			} else if ( $_GET['cql'] or $_POST['cql'] ) {

				include ("$ttroot/common/Sources/cwcqp.php");
				$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = $settings['cqp']['searchfolder'];
				$cqpcols = array();
		
				$cqp = new CQP();
				$cqp->exec($cqpcorpus); // Select the corpus
				$cqp->exec("set PrettyPrint off");
				$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

				if ( substr($cql,0,6) == "<text>" ) $fileonly = 1;

				$cqpquery = "Matches = $cql";
				$cqp->exec($cqpquery);

				$size = chop($cqp->exec("size Matches"));

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

				if ( $act == "collocations" || $_POST['coll'] ) {
					$maintext .= "<h1>Collocations</h1>";
				
					$cmd = "info $cqpcorpus;";
					$tmp = $cqp->exec($cmd);
					if ( preg_match("/Size:\s+(\d+)/", $tmp, $matches ) ) $corpussize = $matches[1];
					else $corpussize = 0;

					$tmpfile = time();
	
					$context = $_POST['context'] or $context = 5;
					$dir = $_POST['dir'] or $dir = "both";
					$fld = $_POST['fld'] or $fld = "word";
					$cmd = "tabulate Matches ";
					if ( $dir == "left" ) { $cmd .= "match[-$context] .. match[-1] $fld"; $span = $context; };
					if ( $dir == "right" ) { $cmd .= "match[1] .. match[$context] $fld"; $span = $context; };
					if ( $dir == "both" ) { $cmd .= "match[-$context] .. match[-1] $fld, match[1] .. match[$context] $fld";	$span = 2*$context; };
	
								$maintext .= "<table>
												<tr><th>{%Search query}:<td>$cqltxt</tr>
												<tr><th>{%Search data}:<td>Occurrences: $size, Corpus size: $corpussize</tr>
												<tr><th>{%Collocates}:<td><span title='$cmd'>{%Direction}: $dir; {%Context}: $context; {%Field}: $fld</span></tr>
											</table>";
		
					$cmd = "$cmd   > \"tmp/$tmpfile.1.txt\"";
					$cqp->exec($cmd); $debugtxt .= "<p>Make context : $cmd"; 

					$cmd = 'cat tmp/'.$tmpfile.'.1.txt | perl -e \'while (<>) { if ($_ eq "\n") { next; }; s/\s+/\n/g; print; };\' | sort | uniq -c | perl -pe \'s/^\s*(\d+)\s+(.*)/\2\t\1/g;\' | sort > tmp/'.$tmpfile.'.2.txt';
					shell_exec($cmd); $debugtxt .= "<p>Sort context: $cmd"; 
					$cmd = '/usr/local/bin/cwb-lexdecode -f -s -r cqp -P '.$fld.' '.$cqpcorpus.' | perl -pe \'s/^\s*(\d+)\s+(.*)/\2\t\1/g;\' | perl -pe \'s/ /_/g;\' | sort > tmp/'.$tmpfile.'.3.txt';
					shell_exec($cmd); $debugtxt .= "<p>Get lexicon: $cmd"; 

					$fldname = pattname($fld);
					$cmd = "join tmp/$tmpfile.2.txt tmp/$tmpfile.3.txt | perl $ttroot/common/Scripts/collocate.pl --selsize=$size --corpussize=$corpussize --fldname='$fldname' --span=$span";
					$json = shell_exec($cmd); $debugtxt .= "<p>Create collocation JSON: $cmd"; 
					
					$headrow = "false"; 

					$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Observed frequency}'>Observed</option><option value=4 title='{%Chi-square}'>{%Chi-square}</option><option value=5 title='{%Mutual information}'>{%MI}</option></select>";
					$cntcols = 5;
					
					$maintext .= $debugtxt;
	
					# shell_exec("rm tmp/$tmpfile.*");
				
				} else {
				
					$maintext .= "<h1>Corpus Distribution</h1>";
				

					$grquery = $_POST['query'] or $grquery = $_GET['query'] or $grquery = "group Matches match word";
					$results = $cqp->exec($grquery);


					$headrow = "true"; $fldnum = 1;
					if ( preg_match ( "/group Matches match ([^ ]+) by match ([^ ]+)/", $grquery, $matches )  ) {
						$fld2 = $matches[1]; $fld = $matches[2];
						$tmp = pattsett($fld); if ( $tmp ) {
							$tmpt = $tmp['long'] or $tmpt = $tmp['display'];
							if ( $tmp['var'] ) $type1 = ", 'type': '{$tmp['var']}'";
							$fldname = '{%'.$tmpt.'}';
							$fldi[0] = $tmp;
						} else $fldname = $fld;
						$tmp = pattsett($fld2); if ( $tmp ) {
							$tmpt = $tmp['long'] or $tmpt = $tmp['display'];
							if ( $tmp['var'] ) $type2 = ", type:'{$tmp['var']}'";
							$fldname2 = '{%'.$tmpt.'}';
							$fldi[1] = $tmp;
						} else $fldname2 = $fld2;
						$fldname2 = '{%'.pattname($fld2).'}' or $fldname2 = $fld2;
						$json = "[{label: '$fldname', id:'$fld' $type1}, {label: '$fldname2', id:'$fld2' $type2}, {label:'{%Frequency}', id:'freq', type:'number'}],\n";
						$headrow = "false"; 
						$grname = "{%Frequency by}: $fldname {%and} $fldname2";
						$fldids = array ( $fld, $fld2 );
					} else if ( preg_match ( "/group Matches match ([^ ]+)/", $grquery, $matches )  ) {
						$fld = $matches[1];
						$tmp = pattsett($fld); if ( $tmp ) {
							$tmpt = $tmp['long'] or $tmpt = $tmp['display'];
							if ( $tmp['var'] ) $type1 = ", type:'{$tmp['var']}'";
							$fldname = '{%'.$tmpt.'}';
							$fldi[0] = $tmp;
						} else $fldname = $fld;
						if ( $fldname == "text_id" ) $fldname = "{%Text}";
						$fldids = array ( $fld );
						$json = "[{label: '$fldname', id:'$fld' $type1}, {label:'{%Frequency}', id:'freq', type:'number'}],\n";
						$headrow = "false";
						$grname = "{%Frequency by}: $fldname";
					};	$mainfld = $fld;

					if ( $grname ) {
						$grtxt = "<span title='$grquery'>$grname</span>";
					} else { 
						$grtxt = $grquery;
					};
				
				
					if ( preg_match("/_/", $mainfld) ) {
						# For a relative query, pick up the total counts to calculate proportional measures
						$query1 = "Tots = [] $srest";
						$cqp->exec($query1);
						$query = "group Tots match $mainfld";
						$results2 = $cqp->exec($query);
						foreach ( explode ( "\n", $results2 ) as $line ) {	
							list ( $a, $b ) = explode ( "\t", $line );
							$tots[$a] = $b;
						};
						if ( $settings['cqp']['frequency']['relcnt'] == "perc" ) {
							$withwpm = 100;
							$wpmdesc = "Percentage (within the total of the type)";
							$wpmtxt = "Percentage";						
						} else {
							$withwpm = 1000000;
							$wpmdesc = "Words per million";
							$wpmtxt = "WPM";
						};
						$json = preg_replace("/\],\n$/", ", {id:'totcnt', label:'{%Total}'}, {id:'relcnt', label:'{%$wpmtxt}', title:'{%$wpmdesc}', format:'###,###.#', type:'number'}],\n", $json);
						$cntcols = 3;
					} else $cntcols = 1;

					$maintext .= "<table>
									<tr><th>{%Search query}:<td>$cqltxt</tr>
									<tr><th>{%Group query}:<td>$grtxt</tr>
								</table>";
			
					foreach ( explode ( "\n", $results ) as $line ) {	
						$line = str_replace("'", "&#039;", $line);
						$flds = explode("\t", $line); $flda = "";
						if ( $line != "" && ( ( $flds[0] != '' && $flds[0] != '_' ) || $showempties) ) {
							foreach ( $flds as $i => $fld ) {	
								$rowval[$i] = $fld;
								if ( $i + 1 == count($flds) ) {
									$flda .= "$fld"; 
									$rowcnt = $fld;
								} else if ( $fldi[$i]['var'] == "number" ) {
									$flda .= intval($fld).', '; 
								} else {
									if ( $fldids[$i] == 'text_id' ) $fld = preg_replace("/.*\//", "", $fld); # For text_id fields
									$flda .= "'$fld', ";
								};
							};
							if ( $tots ) {
								$valtot = $tots[$rowval[0]];
								$relcnt = ($rowcnt/$valtot) * $withwpm;
								$flda .= ", $valtot, $relcnt";
							};
							$json .= "[$flda],\n";
						};

					};		
					$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Corpus occurrences}'>Frequency</option><option value=3 title='{%$wpmdesc}'>$wpmtxt</option></select>";

					$json = "[$json]";
					$cqltxt = str_replace("'", "&#039;", $cql);

				};
				$maintext .= "<hr>";
				# End of CQP section

			} else {
			
			# TODO: Should we provide some default JSON?
			
			};

		if ( $json ) {

			$apikey = $settings['geomap']['apikey'] or $apikey = "AIzaSyBOJdkaWfyEpmdmCsLP0B6JSu5Ne7WkNSE"; # Use our key when no other key is defined  
			
			if ( $mainfld == "text_geo" || $fldi[0]['var'] == "geo"  ) { $moregs .= "<option value='geomap'>{%Map Chart}</option><option value='geochart'>{%Geo Chart}</option>"; $morel = ", 'map', 'geochart'";  $moreo = ", 'mapsApiKey': '$apikey'"; };
	
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
						$moregs
						</select>
						$wpmsel
						|
						{%Download}:
						<select name=download onClick=\"downloadData(this.value);\">
						<option value=''>{%[select]}</option>
						<option value='svg' class='imgbut' title='Download image as Scalable Vector Graphics'>{%SVG}</option>
						<option value='png' class='imgbut' title='Download image as Portable Network Graphics'>{%PNG}</option>
						<option value='csv' title='Download data as Comma-Separated Values'>{%CSV}</option>
						<option value='json' title='Download data in Javascript Object Notation'>{%JSON}</option>
						</select>
						</p>
						<div style='width: 100%;' id=googlechart></div>
						";

			$maintext .= "<hr><p><a target=help href='http://teitok.corpuswiki.org/site/index.php?action=help&id=visualize'>{%Help}</a>";
			if ( $cql && !$_GET['cql'] ) {
				$maintext .= " &bull; <a href='index.php?action=$action&cql=".urlencode($cql)."&query=".urlencode($grquery)."'>{%Direct URL}</a>";
			};
	
	
					# Create a pie-chart option
					$maintext .= " 
						<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>
						<script type=\"text/javascript\" src=\"$jsurl/visualize.js\"></script>
						<script type=\"text/javascript\">
						
			google.charts.load('current', {'packages':['corechart', 'table', 'bar', 'line', 'scatter' $morel ] $moreo });

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