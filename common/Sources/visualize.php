<?php

		# Show Google Visualization for data
		
		$maintext .= "<h2>Data Visualization</h2>";
		$cntcols = 1;

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

				$size =$cqp->exec("size Matches");

				if ( preg_match("/\[([^\]]+)\](?: within .*)?$/", $cql, $matches) || preg_match("/\[([^\]]+)\] :: (.*?)(?: within .*)?$/", $cql, $matches) ) {
					$pmatch = $matches[1]; $smatch = $matches[2];
					if ( $smatch ) $srest = " :: $smatch"; # TODO : Check if we want to keep everything
					foreach ( explode ( ' & ', $pmatch ) as $pmp ) {
						if ( preg_match("/([^ ]+) *= *\"([^\"]*)\"/", $pmp, $matches) ) $cqlname .= "<p>{%".pattname($matches[1], false)."} = <b>".$matches[2]."</b>";
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

				$grquery = $_POST['query'] or $grquery = $_GET['query'] or $grquery = "group Matches match word";
				$results = $cqp->exec($grquery);


				$headrow = "true"; $fldnum = 1;
				if ( preg_match ( "/group Matches match ([^ ]+) by match ([^ ]+)/", $grquery, $matches )  ) {
					$fld2 = $matches[1]; $fld = $matches[2];
					$fldname = '{%'.pattname($fld).'}' or $fldname = $fld;
					$fldname2 = '{%'.pattname($fld2).'}' or $fldname2 = $fld2;
					$json = "[{label: '$fldname', id:'$fld'}, {label: '$fldname2', id:'$fld2'}, {label:'{%Count}', id:'count', type:'number'}],\n";
					$headrow = "false"; 
					$grname = "{%Frequency by}: $fldname {%and} $fldname2";
					$fldids = array ( $fld, $fld2 );
				} else if ( preg_match ( "/group Matches match ([^ ]+)/", $grquery, $matches )  ) {
					$fld = $matches[1];
					$fldname = '{%'.pattname($fld).'}' or $fldname = $fld;
					if ( $fldname == "{%text_id}" ) $fldname = "{%Text}";
					$fldids = array ( $fld );
					$json = "[{label: '$fldname', id:'$fld'}, {label:'{%Count}', id:'count', type:'number'}],\n";
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
						$wpmdesc = "Percentage (occurrences per 100 tokens)";
						$wpmtxt = "Perc.";						
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

			
			} else {
			
			};

		$apikey = $settings['geomap']['apikey'] or $apikey = "AIzaSyBOJdkaWfyEpmdmCsLP0B6JSu5Ne7WkNSE"; # Use our key when no other key is defined  
			
		$cqltxt = str_replace("'", "&#039;", $cql);
		if ( $mainfld == "text_geo" ) { $maps = "<option value='geomap'>{%Map Chart}</option><option value='geochart'>{%Geo Chart}</option>"; $morel = ", 'map', 'geochart'";  $moreo = ", 'mapsApiKey': '$apikey'"; };
		if ( $withwpm ) $wpmsel = " | {%Base}: <select name='cntcol' onChange='setcnt(this.value);'><option value='count' title='{%Raw frequency}'>Count</option><option value='wpm' title='{%$wpmdesc}'>$wpmtxt</option></select>";
		if ( $json ) {
	
			if ( $_GET['charttype'] ) $inittype = "'{$_GET['charttype']}'";
					$maintext .= " 
						<hr>
						<div id='linkfield' style='float: right; z-index: 100; cursor: pointer;'></div>
						<p>
						{%Graph}:
						<select name=graph onChange=\"drawGraph(this.value);\">
						<option value='table'>{%Table}</option>
						<option value='pie'>{%Pie}</option>
						<option value='piehole'>{%Donut}</option>
						<option value='bars'>{%Bar Chart}</option>
						<option value='lines'>{%Line Chart}</option>
						<option value='scatter'>{%Scatter Chart}</option>
						<option value='histogram'>{%Histogram}</option>
						$maps
						</select>
						$wpmsel
						|
						{%Download}:
						<select name=download onClick=\"downloadData(this.value);\">
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
						<script type=\"text/javascript\">google.charts.load('current', {'packages':['corechart', 'table', 'bar', 'line', 'scatter' $morel ] $moreo });

			var json = [$json];
			var cql = '$cqltxt'; var data; var options;
			var chart; var charttype;
			var cnttype = 'count';
			var headrow = $headrow;
			var cntcols = $cntcols;
			var viewport = document.getElementById('googlechart');
			google.charts.setOnLoadCallback(function() { drawGraph($inittype); });

			</script>
				";
			

		} else {
	
			$maintext .= "<p>No data to visualize";
	
		};
	};

	function pattname ( $key, $dolang = true ) {
		global $settings, $wordfld;
		if ( $key == "word" && $wordfld ) $key = $wordfld;
		$val = $settings['xmlfile']['pattributes']['forms'][$key]['display'];
		if ( $val != "" ) return $val;
		$val = $settings['xmlfile']['pattributes']['tags'][$key]['display'];
		if ( $val != "" ) return $val;
		
		# Now try without the text_ or such
		if ( preg_match ("/^(.*)_(.*?)$/", $key, $matches ) ) {
			$key2 = $matches[2]; $keytype = $matches[1];
			$val = $settings['cqp']['sattributes'][$key2]['display'];
			if ( $val != "" ) return $val;
			$val = $settings['cqp']['sattributes'][$keytype][$key2]['display'];
			if ( $val != "" ) return $val;
		};
		
		if ( $dolang ) return $key;
		return "<i>$key</i>";
	};

		
?>