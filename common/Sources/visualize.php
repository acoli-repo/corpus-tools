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
			
				if ( $settings['cqp']['defaults']['registry'] ){
					$reg = " --cqlfolder={$settings['cqp']['defaults']['registry']}";
				};

				$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

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
					$maintext .= "<h1>Keywords</h1>";


					$fld = $_POST['fld'] or $fld = "word";
					$fldname = pattname($fld);

					if ( $settings['cqp']['frequency']['refcorpus'] ) {
						$refcorpus = $settings['cqp']['frequency']['refcorpus'];
						$refcorpustxt = $settings['cqp']['frequency']['refcorpus'] or $refcorpustxt = $refcorpus;
					} else {
						$refcorpus = $cqpcorpus;
						$refcorpustxt = "(internal)";
					};

					$tmpfile = time();
	
					$maintext .= "<table>
									<tr><th>{%Search query}:<td>$cqltxt</tr>
									<tr><th>{%Keyness}:<td><span title='$cmd'>{%Field}: $fld, {%Reference corpus}: $refcorpustxt</span></tr>
								</table>";

					$cmd = "echo 'Matches = $cql; stats Matches $fld :: type:keywords context:$dir$context' | /usr/local/bin/tt-cqp --output=json";
					$json = shell_exec($cmd);

					$fldname = pattname($fld);
					$cmd = "join tmp/$tmpfile.2.txt tmp/$tmpfile.3.txt | perl $ttroot/common/Scripts/collocate.pl --selsize=$size --corpussize=$corpussize --fldname='$fldname' --span=1";
					$json = shell_exec($cmd); 
				
				} else if ( $act == "collocations" || $_POST['mode'] == "collocations" ) {
				
					$maintext .= "<h1>Collocations</h1>";
				
					$tmpfile = time();
	
					$context = $_POST['context'] or $context = 5;
					$dirdir = array ( "" => "Left/Right", "-" => "Left", "+" => "Right"  );
					$dir = $_POST['dir'] or $dir = ""; $dirtxt = "{%{$dirdir[$dir]}}";
					$fld = $_POST['fld'] or $fld = "word";
					$fldname = pattname($fld);
	
					$maintext .= "<table>
									<tr><th>{%Search query}:<td>$cqltxt</tr>
									<tr><th>{%Collocates}:<td><span title='$cmd'>{%Direction}: $dirtxt; {%Context}: $context; {%Field}: {%$fldname}</span></tr>
								</table>";
		
					$cmd = "echo 'Matches = $cql; stats Matches $fld :: context:$dir$context' | /usr/local/bin/tt-cqp --output=json";
					$json = shell_exec($cmd);
					
					$headrow = "false"; 

					$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Observed frequency}'>Observed</option><option value=4 title='{%Chi-square}'>{%Chi-square}</option><option value=5 title='{%Mutual information}'>{%MI}</option></select>";
					$cntcols = 5;
				
				} else {
				
					$maintext .= "<h1>Corpus Distribution</h1>";
				
					$grquery = $_POST['query'] or $grquery = $_GET['query'] or $grquery = "group Matches match.word";
					$cmd = "echo 'Matches = $cql; $grquery;' | /usr/local/bin/tt-cqp --output=json";
					if ( $debug ) $maintext .= "<!-- $cmd -->";
					$json = shell_exec($cmd);
				
					$maintext .= "<table>
									<tr><th>{%Search query}:<td>$cqltxt</tr>
									<tr><th>{%Group query}:<td>$grquery</tr>
								</table>";

					$wpmdesc = "Words per million"; $wpmtxt = "WPM";
					if ( strpos($json, "%Tot") != false ) {
						$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Corpus occurrences}'>{%Count}</option><option value=2 title='{%Total occurrences}'>{%Total}</option><option value=3 title='{%$wpmdesc}'>$wpmtxt</option></select>";
						$cntcols = 3;
					} else {
						$wpmsel = " | {%Count}: <select name='cntcol' onChange='setcnt(this.value);'><option value=1 title='{%Corpus occurrences}'>{%Count}</option><option value=3 title='{%$wpmdesc}'>$wpmtxt</option></select>";
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
						<option value='totals'>{%Statistics}</option>
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
						<script type=\"text/javascript\" src=\"https://cdn.jsdelivr.net/npm/jstat@latest/dist/jstat.min.js\"></script>
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
	
			# $maintext .= "<h1>Data Visualization</h1>";
			$maintext .= "<p>No data to visualize";
	
		};
	};

		
?>