<?php

		# Show Google Visualization for data
		
		$maintext .= "<h2>Data Visualization</h2>";

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

			$maintext .= "<p>Search query: ".htmlentities($cql);

			$query = $_POST['query'] or $query = $_GET['query'] or $query = "group Matches match word";
			$results = $cqp->exec($query);

			$maintext .= "<p>Group query: <b>$query</b>";

			$headrow = "true"; $fldnum = 1;
			if ( preg_match ( "/group Matches match ([^ ]+) by match ([^ ]+)/", $query, $matches )  ) {
				$fld2 = $matches[1]; $fld = $matches[2];
				$fldname = pattname($fld) or $fldname = $fld;
				$fldname2 = pattname($fld2) or $fldname2 = $fld2;
				$json = "[{label: '{%$fldname}', id:'$fld'}, {label: '{%$fldname2}', id:'$fld2'}, {label:'{%Count}', id:'{%count}', type:'number'}],\n";
				$headrow = "false"; 
			} else if ( preg_match ( "/group Matches match ([^ ]+)/", $query, $matches )  ) {
				$fld = $matches[1];
				$fldname = pattname($fld) or $fldname = $fld;
				$json = "[{label: '{%$fldname}', id:'$fld'}, {label:'{%Count}', id:'{%count}', type:'number'}],\n";
				$headrow = "false";
			};	
	
			
			foreach ( explode ( "\n", $results ) as $line ) {	
				$line = str_replace("'", "&#039;", $line);
				$flds = explode("\t", $line); $flda = "";
				if ( $line != "" && ( $flds[0] != ''  || $showempties) ) {
					foreach ( $flds as $i => $fld ) {
						if ( $i + 1 == count($flds) ) $flda .= "$fld"; else $flda .= "'$fld', ";
					};
					$json .= "[$flda],\n";
				};
			};		
			
		} else {
			
		};
		
	if ( $json ) {
	
				$maintext .= "
					<hr>
					<div id='linkfield' style='float: right; z-index: 100; cursor: pointer;'></div>
					<p>
					<button onClick=\"drawChart('table');\">{%Table}</button>
					<button onClick=\"drawChart('pie');\">{%Pie}</button>
					<button onClick=\"drawChart('piehole');\">{%Donut}</button>
					<button onClick=\"drawChart('bars');\">{%Bar Chart}</button>
					<button onClick=\"downloadSVG();\" id='svgbut'>{%Download SVG}</button>
					<button onClick=\"downloadCSV();\">{%Download CSV}</button>
					</p>
					<div style='width: 100%;' id=googlechart></div>
					";
	
	
				# Create a pie-chart option
				$maintext .= " 
					<script type=\"text/javascript\" src=\"https://www.gstatic.com/charts/loader.js\"></script>
					<script type=\"text/javascript\" src=\"$jsurl/visualize.js\"></script>
					<script type=\"text/javascript\">google.charts.load('current', {'packages':['corechart', 'table', 'bar']});

		var json = [$json];
		var cql = '$cql';
		var chart; var charttype;
		var headrow = $headrow;
		google.charts.setOnLoadCallback(drawChart);

		var viewport = document.getElementById('googlechart');
		</script>
			";
	} else {
	
		$maintext .= "<p>No data to visualize";
	
	};

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