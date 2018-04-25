<?php

	// Apparatus machine for collations between witnesses
	// (c) Maarten Janssen, 2018
		
	if ( !$_POST ) $_POST = $_GET;
			
	$maintext .= "<h1>{%Witness Collation}</h1>
			<div name='cqpsearch' id='cqpsearch'>
			$chareqjs 
			$subheader
			";
	
	if ( !$_POST['div_n'] ) { $_POST["div_n"] = "GP"; $_POST["l_n"] = "480"; };
	
	if ( $act == "cqp" ) {
	
		// CQP based collation

		include ("$ttroot/common/Sources/cwcqp.php");

		$baselevel = $_POST['level'] or $baselevel = $settings['collaction']['baselevel'] or $baselevel = "l";
		
		$cql = "<$baselevel> [] :: "; $sep = ""; $sep2 = "";
		
		foreach ( $settings['collation'] as $key => $val ) {
			if ( !$_POST[$key] ) continue;
			$cv = $_POST[$key];
			$cql .= $sep."match.$key=\"$cv\""; $sep = " & ";
			$data .= $sep2."match $key"; $sep2 = ", ";
			$keytxt = $settings['collation'][$key]['display'] or $keytxt = $key;
			$subtit .= "<p>{%$keytxt} = <b>$cv</b>";
		}; 
		$maintext .= "<h2>{%Collation on}:</h2>$subtit<hr>";
		$outfolder = $settings['cqp']['folder'] or $outfolder = "cqp";

		// This version of CQP relies on XIDX - check whether program and file exist
		$xidxcmd = findapp('tt-cwb-xidx');
		if ( !$xidxcmd || !file_exists("$outfolder/xidx.rng") ) {
			print "<p>This CQP version works only with XIDX
				<script language=Javascript>top.location='index.php?action=cqpraw';</script>
			";
		};

		# print htmlentities($cql); exit;

		# Determine which form to search on by default 
		$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";

		$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";

		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['searchfolder'];
		$cqpcols = array();

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cqpquery = "Matches = $cql";
		$cqp->exec($cqpquery);

		$size = $cqp->exec("size Matches");

		$mtch = "match"; 
			
		$perpage = $_GET['perpage'] or $perpage = 50;
		$start = $_GET['start'] or $start = 0;
		$end = $start+$perpage;
		$cqpquery = "tabulate Matches $start $end $mtch id, $mtch text_id, $mtch word, $mtch, $data;";	# match page_facs
		$results = $cqp->exec($cqpquery);

		if ( $size > $perpage ) {
			$showing = " - {%showing} ".($start+1)." - $end";
		};
		# $maintext .= "<p>$size {%results} $showing";
		
		if ( $debug ) $maintext .= "<p>$cqpquery";
		$xidxcmd = findapp('tt-cwb-xidx');
		foreach ( explode("\n", $results ) as $res ) {
			list ( $id, $cid, $word, $ids, $fld1, $fld2 ) = explode("\t", $res );
			$tmp = explode(" ", $ids); $leftpos = array_shift($tmp); $rightpos = array_pop($tmp);
			if ( !$leftpos ) continue;

			$fileid = "xmlfiles/$cid"; $outfolder = "cqp";
			$expand = "--expand=$baselevel";
			$cmd = "$xidxcmd --filename=$fileid --cqp='$outfolder' $expand $leftpos $leftpos";
			$cid2 = preg_replace("/.*?\/([^\/]+)\.xml/", "\\1", $cid);
			$resxml = shell_exec($cmd);
			$cnt++;
		
			if ( $cid2 != "" && $cid2 == $_GET['from'] ) {
				$baserow = "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$id'><b>$cid2</b></a><td class=wits wit=$cid2 id=bf>$resxml
					<tr><td colspan=2><hr>";
			} else 
				$witrows .= "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$id'>$cid2</a><td wit=$cid2 class=wits>$resxml";
		};
		$maintext .= "<table id=mtxt>$baserow$witrows</table><hr><p>$cnt witnesses";
		$maintext .= "
			<script language=Javascript src='$jsurl/collate.js'></script>
		<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
<style>
	tok[apps] { text-decoration: underline; };
</style>
";

	} else {
	
		$maintext .= "
			<form action='index.php?action=$action&act=cqp' method=post>
			<table>";
		foreach ( $settings['collation'] as $key => $val ) {
			if ( $val['display'] == "" || !is_array($val) ) continue;
			$keytxt = $val['display'];
			if ( $val['type'] == "select" ) {
				$sellist = ""; $corpusfolder = "cqp";
				$tmp = file_get_contents("$corpusfolder/$key.avs"); unset($optarr); $optarr = array();
				$sortarray = array();
				foreach ( explode ( "\0", $tmp ) as $kva ) { 
					array_push($sortarray, "<option value='$kva'>$kva</option>");
				};
				natsort($sortarray); $sellist = join("\n", $sortarray);
				$maintext .= "<tr><td>{%$keytxt}<td><select name='$key'>$sellist</select></tr>";
			} else { 
				$maintext .= "<tr><td>{%$keytxt}<td><input name='$key' size=10></tr>";
			};
		}; 
		
		$maintext .= "</table>
			<p><input type=submit value='{%Search}'>
			</form>";
	
	};

?>