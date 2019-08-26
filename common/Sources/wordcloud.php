<?php
	// Script to create a word cloud from a TEITOK/XML file
	// (c) Maarten Janssen, 2015

	require ("$ttroot/common/Sources/ttxml.php");
	$ttcqp = findapp("tt-cqp");

	$ttxml = new TTXML();

	$title = $ttxml->title();
	
	$showform = $_GET['show'] or $showform = 'word';
	$rest = $_GET['rest'];
	$max = $_GET['max'] or $max = 250;
	
	# Calculate the word counts
	if ( 1==2 ) {
	} else if ( file_exists("cqp/text_id.idx") ) {
		# Default: CQP for this text ID
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  		
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$textid = $ttxml->fileid;
		$cql = "Matches = [$rest] :: match.text_id=\"xmlfiles/$textid\"";
		$cqp->exec($cql); // Select the corpus
		$cql = "group Matches match $showform";
		$result = $cqp->exec($cql); // Select the corpus
		$cnt = 0;
		foreach ( explode("\n", $result) as $line ) {
			list ( $word, $size ) = explode ( "\t", $line);
			$word = str_replace("'", "\'", $word);
			if ( $size) $wordlist .= " { text:'$word', size: $size},\n";
			if ( $cnt++ > $max ) break;
		};
	};	
		
	$maintext .= "
	<h2>$title</h2>
	<h1>{%Word Cloud}</h1>
    <script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/lib/d3/d3.js\"></script>
    <script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/lib/d3/d3.layout.cloud.js\"></script>
    <script src=\"https://cdn.rawgit.com/wvengen/d3-wordcloud/master/d3.wordcloud.js\"></script>
    <script src=\"$jsurl/tokedit.js\"></script>

    <div id='wordcloud' style='width: 100%; height: 600px;'></div>
    
    <script>
    
	var list = [];
	var showform = 'form';

	$settingsdefs
    
    function go() {
	    list = [$wordlist];
		
      d3.wordcloud()
        .size([document.getElementById('wordcloud').clientWidth, document.getElementById('wordcloud').clientHeight])
        .selector('#wordcloud')
        .font('Impact')
        .words(list)
		.onwordclick(function(d, i) {
		  window.location = 'index.php?action=cqp&cql=[$showform=\"' + d.text+ '\"] :: match.text_id=\"xmlfiles/$textid\"';
		})    
        .start();
    };
    
    go();
      

    </script>
    <button onClick=\"showform='form'; go();\">Form</button> 
    <button onClick=\"showform='lemma'; go();\">Lemma</button> 
    ";

?>