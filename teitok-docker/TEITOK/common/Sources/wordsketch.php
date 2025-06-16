<?php

	// Use tt-cqp to calculate a dependency-concordancer for corpora with dependency relations (head, deprel)
	// (c) Maarten Janssen, 2018
	
	# Check that there are deprels
	if ( !file_exists("cqp/deprel.lexicon") ) fatal("Dependency sketches depends on dependency relations, which are not present in this corpus (yet)");
	
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
	
		$maintext .= "<h1>Dependency Sketch</h1>";

		$fld = $_POST['fld'] or $fld = "word";
		$fldname = pattname($fld);

		$maintext .= "<table>
			<tr><th>CQL Query:<td>$cql
			<tr><th>Concordance field:<td>$fldname
			</table>
			<hr>";
		
		$cqp = "Matches = $cql; stats Matches $fld :: context:head measure:all show:deprel;";	
			
		$cmd = "echo '$cqp' | $bindir/tt-cqp";
		$results = shell_exec($cmd);

		if ( $debug ) { $maintext .= "<pre>$cmd</pre>"; };
		
		foreach ( explode("\n", $results ) as $res ) {
			list ( $word, $deprel, $cnt, $tot, $exp, $chi2, $mutinf ) = explode("\t", $res );
			$exp = sprintf("%.4f", $exp);
			$sketchlist[$deprel][$word] = array ( $cnt, $tot, $exp, $chi2, $mutinf );
		};

		foreach ( $sketchlist as $deprel => $wordlist ) {
			if ( !$deprel || count($wordlist) == 0 ) continue;
			$deprelname = $edgelabels[$deprel.""]['display'] or $deprelname = $deprel;
			$maintext .= "<table style='float: left;'>
				<tr><th colspan=6><b onClick=\"visualize('$deprel', '$deprelname');\">$deprelname</b></tr>
				<tr><th>{%$fldname}<th>{%Observed}<th>{%Total}<th>{%Expected}<th title='{%Chi Square}'>{%Chi Square}<th title='{%Mutual Information}'>{%Mutual Information}";

			$sort_col = array();
			$sortidx = $_GET['sort'] or $sortidx = 3;
			foreach ($wordlist as $key => $row) {
				$sort_col[] = $row[$sortidx];
			};
			array_multisort($sort_col, SORT_NUMERIC, $wordlist, SORT_ASC );
			
			$i = 0; $maxshow = $_GET['max'] or $maxshow = 10;
			$json .= "\n\t'$deprel':[[{'key':'$fld','label':'$fldname'},\n{'key':'obs','label':'Observed'},\n{'key':'tot','label':'Total'},\n{'key':'exp','label':'Expected'},\n{'key':'chi2','label':'Chi Square'},\n{'key':'mi','label':'Mutual Information'}],\n";
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
				document.postform.title.value = 'Dependency Sketch: ' + tit;
				document.postform.submit();
			}
			</script>
			<form style='display: none;' action='index.php?action=visualize' method=post name=postform id=postform>
				<input name='json'>
				<input name='title'>
			</form>";
	} else {

		$nowsfld = array("head", "deps", "deprel"); // Fields to ignore for context
		$patta = getset('cqp/pattributes', array());
		foreach ( $patta as $key => $kva ) { 
			if ( in_array($key, $nowsfld) ) continue;
			$options .= "<option value='$key'>{%{$kva['display']}}</option>";
		};
		
		$qbnosearch = true;	
		require_once ("$ttroot/common/Sources/querybuilder.php");
				
		$maintext .= "<h1>{%Dependency Sketches}</h1>
				<script language=Javascript>
					$prescript
					function checksearch (frm) {
						if ( frm.cqlfld.value == '' ) {
							updatequery(true); 
							if ( frm.cqlfld.value == '[] within text' ) frm.cqlfld.value = '';
							if ( frm.cqlfld.value == '' ) return false;
						};
					}; 
				</script>
		
			<form action='index.php?action=$action' method=post>
					<p>{%CQL Query}: &nbsp;  $cqlbox
					<input type=submit value=\"{%Search}\"> 
						<a onClick=\"showqb('cqlfld');\" title=\"{%define a CQL query}\">{%query builder}</a>
						| <a onClick=\"showcql();\" title=\"{%visualize your CQL query}\">{%visualize}</a>
					</p>
			<p>{%Sketch field}: <select name=fld><option value=''>[{%select}]</option>$options</select>
			<p><input type=submit value=Create>
			</form>
				<div style='display: none;' class='helpbox' id='cqlview'></div>
				<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-right: -5px; float: right;' onClick=\"this.parentNode.style.display = 'none';\">&times;</span>$querytext</div>
				<script language='Javascript' src=\"$jsurl/cqlparser.js\"></script>
				<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>
			";
		
	};

?>