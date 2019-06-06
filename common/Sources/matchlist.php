<?php

	# A Script to display a list of matches, with lists of features behind it
	# (c) Maarten Janssen, 2019

	$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "";

	require_once ("$ttroot/common/Sources/querybuilder.php");
	$showlist = "<p>";
	foreach ( $cqpcols as $col ) {
		if ( in_array($col, array_keys($_POST['attlist']) ) ) $chk = "checked"; else $chk = "";
		$showlist .= " <input type=checkbox name=attlist[$col] value='1' $chk> ".pattname($col);
	};
	foreach ( $settings['cqp']['sattributes'] as $lvl ) {
		$showlist .= "<p>";
		foreach ( $lvl as $xatt ) {
			if ( !$xatt['display'] || !$xatt['key'] || !is_array($xatt) ) continue;
			$display = $xatt['display'] or $display =  $xatt['key'];
			$col = "{$lvl['key']}_{$xatt['key']}";
			if ( in_array($col, array_keys($_POST['attlist']) ) ) $chk = "checked"; else $chk = "";
			$showlist .= " <input type=checkbox name=attlist[$col] value='1' $chk> {%$display}";
		};
	};


	$maintext .= "<h1>CQP Match List</h1>
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
			<form action='$postaction' onsubmit=\"return checksearch(this);\" method=post id=cqp name=cqp><p>CQP Query: &nbsp; 
				$cqlbox
				<input type=submit value=\"{%Search}\"> 
					<a onClick=\"showqb('cqlfld');\" title=\"{%define a CQL query}\">{%query builder}</a>
					| <a onClick=\"showcql();\" title=\"{%visualize your CQL query}\">{%visualize}</a>
				$optionoption

			<h2>Features to show</h2>
			$showlist

			</form>
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

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['searchfolder'];

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
	
?>