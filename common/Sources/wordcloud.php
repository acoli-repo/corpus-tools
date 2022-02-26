<?php
	// Script to create a word cloud from a TEITOK/XML file
	// (c) Maarten Janssen, 2015

	if ( !$_POST ) $_POST = $_GET;

	$ttcqp = findapp("tt-cqp");

	$showform = $_POST['show'] or $showform = $_POST['defaults']['wordcloud']['show'] or $showform = 'word';
	$rest = $_POST['rest'] or $rest = $settings['defaults']['wordcloud']['rest'];
	$font = $_POST['font'] or $font = $settings['defaults']['wordcloud']['font'] or $font = "Impact";
	$max = $_POST['max'] or $max = 250;
	$titfld = $settings['defaults']['wordcloud']['title'] or $titfld = $settings['cqp']['title'] or $titfld = "text_id";

	if ( $settings['cqp']['subcorpora'] ) {
		$subcorpus = $_SESSION['subc'] or $subcorpus = $_GET['subc'];
		if ( !$subcorpus ) {
			fatal("No subcorpus selected");
		};
		$_SESSION['subc'] = $subcorpus;
		$cqpcorpus = strtoupper("$cqpcorpus-$subcorpus"); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = "cqp/$subcorpus";
		$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
		$subcorprow = "<tr><th>Corpus<td>$corpusname";
		$corpusfolder = $cqpfolder;
	} else {
		$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'];
	};
	// Do not allow searches while the corpus is being rebuilt...
	if ( file_exists("tmp/recqp.pid") ) {
		fatal ( "Wordcloud is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
	};	
	if  ( !$cqpfolder ) $cqpfolder = "cqp";
	if  ( !$corpusfolder ) $corpusfolder = "cqp";

	# Calculate the word counts
	$textid = $_POST['id'] or $textid = $_POST['cid'];
	if ( file_exists("$cqpfolder/text_id.idx") ) {
		# Default: CQP for this text ID
		include ("$ttroot/common/Sources/cwcqp.php");
	
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cql = $_GET['cql'];
		if ( !$cql && $textid ) {
			$cql = "[$rest] :: match.text_id=\"xmlfiles/$textid\"";
			$textrest = " :: match.text_id=\"xmlfiles/$textid\"";
		};
		$cql = "Matches = $cql";
		$cqp->exec($cql); // Select the corpus
		
		if ( strstr($pos, "@") !== false ) $match = "target";
		else $match = "match";
		
		$cql3 = "group Matches $match $titfld";
		$result = $cqp->exec($cql3); $sep = ""; // Select the corpus
		foreach ( explode("\n", $result) as $line ) {
			list ( $doc, $size ) = explode ( "\t", $line);
			$doc = preg_replace("/.*\//", "", $doc); 
			if ( $doc ) { $doclist .= "$sep<a href='index.php?action=file&cid=$doc'>$doc</a>"; $sep = "<br>"; };
		};
		$cql2 = $_GET['cql2'] or $cql2 = "group Matches match $showform";
		$result = $cqp->exec($cql2); // Select the corpus
		$cnt = 0;
		foreach ( explode("\n", $result) as $line ) {
			list ( $word, $size ) = explode ( "\t", $line);
			$word = str_replace("\\", "\\\\", $word);
			$word = str_replace("'", "\'", $word);
			if ( $size) $wordlist .= " { text:'$word', size: $size},\n";
			if ( $cnt++ > $max ) break;
		};
	};	
	if ( !$doclist ) { $doclist = "<i>$textid</i>"; };
	
	foreach ( $settings['cqp']['pattributes'] as $key => $pat ) {
		$pattname = pattname($key);
		if ( $key == $showform ) $sel = "selected"; else $sel = "";
		if ( $pattname && ( !$pat['admin'] || $username ) ) $textoptions .= "<option value='$key' $sel>{%$pattname}</option>";
	};
	
	if ( $username ) $rawcql = "<tr><th>{%CQL Query}:<td>$cql<tr><th>{%Grouping Query}:<td>$cql2";
	
	$fwquery = "&rest={$_GET['rest']}";
	
	$maintext .= "
	<h2>$title</h2>
	<h1>{%Word Cloud}</h1>
	<link href=\"https://fonts.googleapis.com/icon?family=Material+Icons\" rel=\"stylesheet\">
	
	<div id='settingsbut' onClick=\"this.style.display='none'; document.getElementById('settings').style.display='block'\"><i class=\"material-icons md-48\" style='float: right;'>menu</i></div>
	<div id='settings' style='display: none; float: right;'>
		<span onClick=\" document.getElementById('settingsbut').style.display='block'; document.getElementById('settings').style.display='none'\"><i class=\"material-icons md-48\">menu</i></span> <b style='font-size: larger; padding-bottom: 15px; margin-bottom: 15px; vertical-align: top;'>{%Settings}</b>
		<form action='index.php?action=$action$fwquery'>
		<input type=hidden name=id value=\"$textid\">
		<input type=hidden name=action value=\"$action\">
		<table>
		<tr><td valign=top>
		<table>
		<tr><td>{%Text}:<td><select name='show'>$textoptions</select>
		<tr><td>{%Count}:<td><select name='max'><option value=50>50</option><option value=100>100</option><option value=250 selected>250</option><option value=500>500</option></select>
		<tr><td>{%Restriction}:<td><input size=40 value=\"{$_GET['rest']}\"></select>		
		</table>
		<p><input type=submit value=\"{%Redraw}\"> <a href='https://github.com/wvengen/d3-wordcloud' target='_new' style='color: #aaaaaa;'>https://github.com/wvengen/d3-wordcloud</a>
		<td valign=top>
		<table>
		$rawcql
		$subcorprow
		<tr><th>{%Document(s)}:<td>$doclist
		</table>
		</table>
				
		</form>
		</div>
	
	<script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/lib/d3/d3.js\"></script>
	<script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/lib/d3/d3.layout.cloud.js\"></script>
	<script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/d3.wordcloud.js\"></script>
	<script src=\"$jsurl/tokedit.js\"></script>

	<div id='wordcloud' style='width: 100%; height: 600px;'></div>

	<script>

	var list = [];

	$settingsdefs

	function go() {
		list = [$wordlist];
	
	  d3.wordcloud()
		.size([document.getElementById('wordcloud').clientWidth, document.getElementById('wordcloud').clientHeight])
		.selector('#wordcloud')
		.font('$font')
		.words(list)
		.onwordclick(function(d, i) {
		  window.location = 'index.php?action=cqp&cql=[$showform=\"' + d.text+ '\"] $textrest\"';
		})    
		.start();
	};

	go();
  

	</script>
	";
	
?>