<?php

	// Use tt-cqp to calculate a word-sketch for corpora with dependency relations (head, deps)
	// (c) Maarten Janssen, 2018
	
	if ( !$_POST ) $_POST = $_GET;
	
	$cql = $_POST['cql'];
	
	if ( $cql ) {
	
		# Read edge labels from the tagset
		if ( file_exists("Resources/tagset.xml") ) {
			require ( "$ttroot/common/Sources/tttags.php" );
			$tttags = new TTTAGS("", false);
			$edgelabels = $tttags->tagset['edges'];
		};
		// print_r($edgelabels); exit;
	
		$maintext .= "<h1>Word Sketch</h1>";

		$fld = $_POST['fld'] or $fld = "word";
		$fldname = pattname($fld);

		$maintext .= "<table>
			<tr><th>CQL Query:<td>$cql
			<tr><th>Concordance field:<td>$fldname
			</table>
			<hr>";
		
		$cqp = "Matches = $cql; stats Matches $fld :: context:head measure:all show:deps;";	
			
		$cmd = "echo '$cqp' | /usr/local/bin/tt-cqp";
		$results = shell_exec($cmd);

		// if ( $debug ) { $maintext .= "<pre>$cmd</pre>"; };
		
		foreach ( explode("\n", $results ) as $res ) {
			list ( $word, $deps, $cnt, $tot, $exp, $chi2, $mutinf ) = explode("\t", $res );
			$sketchlist[$deps][$word] = array ( $cnt, $tot, $exp, $chi2, $mutinf );
		};

		foreach ( $sketchlist as $deps => $wordlist ) {
			if ( !$deps || count($wordlist) == 0 ) continue;
			$depsname = $edgelabels[$deps.""]['display'] or $depsname = $deps;
			$maintext .= "<table style='float: left;'>
				<tr><th colspan=6><b onClick=\"visualize('$deps', '$depsname');\">$depsname</b></tr>
				<tr><th>{%$fldname}<th>{%Observed}<th>{%Total}<th>{%Expected}<th title='{%Chi Square}'>{%Chi2}<th title='{%Mutual Information}'>{%MI}";

			$sort_col = array();
			$sortidx = $_GET['sort'] or $sortidx = 3;
			foreach ($wordlist as $key => $row) {
				$sort_col[] = $row[$sortidx];
			};
			array_multisort($sort_col, SORT_NUMERIC, $wordlist, SORT_ASC );
			
			$i = 0; $maxshow = $_GET['max'] or $maxshow = 10;
			$json .= "\n\t'$deps':[[{'key':'$fld','label':'$fldname'},\n{'key':'obs','label':'Observed'},\n{'key':'tot','label':'Total'},\n{'key':'exp','label':'Expected'},\n{'key':'chi2','label':'Chi Square'},\n{'key':'mi','label':'Mutual Information'}],\n";
			foreach ( array_reverse($wordlist) as $collocate => $data ) {
				if ( $i < $maxshow ) $maintext .= "<tr><td>$collocate<td>".join("<td>", $data);
				$i++;
				$json .= "['$collocate', ".join(",", $data)."], ";
			};
			$maintext .= "</table>";
			$json .= "],";

		};
	
		$maintext .= "\n\n<script language=Javascript>
			\njson = { $json \n};
			function visualize(fld, tit) {
				console.log(tit);
				document.postform.json.value = JSON.stringify(json[fld]);
				document.postform.title.value = 'Word Sketch: ' + tit;
				document.postform.submit();
			}
			</script>
			<form style='display: none;' action='index.php?action=visualize' method=post name=postform id=postform>
				<input name='json'>
				<input name='title'>
			</form>";
		
	};

?>