<?php

	# A Script to display a list of matches, with lists of features behind it
	# (c) Maarten Janssen, 2019

	$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "";

	require_once ("$ttroot/common/Sources/querybuilder.php");
	$showlist = "<table><tr><th>Token<td>";
	foreach ( $cqpcols as $col ) {
		if ( is_array($_POST['attlist']) && in_array($col, array_keys($_POST['attlist']) ) ) $chk = "checked"; else $chk = "";
		$showlist .= " <input type=checkbox name=attlist[$col] value='1' $chk> ".pattname($col);
	};
	foreach ( getset('cqp/sattributes', array()) as $lvl ) {
		$levdisp = $lvl['display'] or $levdisp = $lvl['key'];
		$row = "";
		foreach ( $lvl as $xatt ) {
			if ( !is_array($xatt) || !$xatt['display'] || !$xatt['key'] ) continue;
			$display = $xatt['display'] or $display =  $xatt['key'];
			$col = "{$lvl['key']}_{$xatt['key']}";
			if ( is_array($_POST['attlist']) && in_array($col, array_keys($_POST['attlist']) ) ) $chk = "checked"; else $chk = "";
			$row .= " <input type=checkbox name=attlist[$col] value='1' $chk> {%$display}";
		};
		if ( $row ) $showlist .= "<tr><th>$levdisp<td>$row";
	};
	$showlist .= "</table>";

	$myform = str_replace("</form>", "", $cqlfld)."
			<h2>Features to show</h2>
			$showlist

			</form>";
	

	$maintext .= "<h1>CQP Match List</h1>
			$myform

			$chareqjs
			$tagbuilder
			<div style='display: none;' class='helpbox' id='cqlview'></div>
			<div style='display: none;' class='helpbox' id='qbframe'><span style='margin-right: -5px; float: right; cursor: pointer;' onClick=\"this.parentNode.style.display = 'none';\" title=\"{%close}\">&times;</span>$querytext</div>
			<script language='Javascript' src=\"$jsurl/cqlparser.js\"></script>
			<script language='Javascript' src=\"$jsurl/querybuilder.js\"></script>

			<script language=Javascript>
			function cqpdo(elm) {
				console.log(typeof(elm));
				if ( typeof(elm) == 'string ')  document.cqp.cql.value = elm;
				else document.cqp.cql.value = elm.innerHTML;
			};
			</script>
		<div>
		";

	if ( $cql ) {

		$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = getset('cqp/searchfolder');

		include ("$ttroot/common/Sources/cwcqp.php");

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus

		if ( $cql ) {
			$cqpquery = "Matches = $cql";
			$cqp->exec($cqpquery);
	
			$sep = ""; foreach ( array_keys($_POST['attlist']) as $att ) {
				$attlist .= "$sep match $att"; $sep = ", ";
				$headerrow .= "<th>".pattname($att);
			};
			if ( $attlist == "" ) {
				$attlist = "match form, match id, match text_id";
				$headerrow = "<tr><th>Word<th>ID<th>Text ID";
			} else $headerrow = "<tr>$headerrow";

			$perpage = $_GET['perpage'] or $perpage = 50; 
			$start = $_GET['start'] or $start = 0; 
			$end = $_GET['end'] or $end = $perpage; 

			$cqpquery = "tabulate Matches $start $end $attlist";
			$results = $cqp->exec($cqpquery);
		
			$maintext .= "<p><hr><table>$headerrow";
			foreach ( explode("\n", $results) as $result ) {
				$maintext .= "<tr><td>".join("<td>", explode("\t", $result));
			};
			$maintext .= "</table>";
		};
	
	};
	
?>