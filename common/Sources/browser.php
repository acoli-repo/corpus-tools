<?php
	// File browser - browse files by category
	// (c) Maarten Janssen, 2018

	$class = $_GET['class'];
	$val = $_GET['val'];
	
	$maintext .= "<h1>{%Document Browser}</h1>";

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
	
	if ( $class && $val ) {

		include ("$ttroot/common/Sources/cwcqp.php");
		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cqpquery = "Matches = <text> [] :: match.text_$class = '$val'";
		$cqp->exec($cqpquery);

		$oval = $val;
		if ( $val == "" || $val == "_" ) $val = "({%none})";
		else if ( $item['type'] == "kselect" || $item['translate'] ) $val = "{%$class-$val}";

		$size = $cqp->exec("size Matches");

		$max = $_GET['max'] or $max = 100;
		$start = $_GET['start'] or $start = 0;
		$stop = $start + $max;
		if ( $size > $max || $start > 0 ) {
			$next = $stop; $beg = $start + 1; $prev = max(0, $start - $max);
			if ( $start > 0 ) $bnav .= " <a href='index.php?action=$action&class=$class&val=$oval&start=$prev'>{%previous}</a> ";
			if ( $size > $max ) $bnav .= " <a href='index.php?action=$action&class=$class&val=$oval&start=$next'>{%next}</a> ";
			$nav = " - {%showing} $beg - $stop - $bnav";
		};

		$maintext .= "<p><a href='index.php?action=$action'>{%Documents}</a> > <a href='index.php?action=$action&class=$class'>{%$cat}</a> > $val
			<p>$size {%documents} $nav
			<hr>";
	
		if ( $size > 0 ) {
			$catq = "tabulate Matches $start $stop match text_id, match text_title";
			// $maintext .= "<p>$cqpquery; $catq;";
			$results = $cqp->exec($catq); 
		
			foreach ( explode("\n", $results) as $result ) {
				list ( $cid, $title ) = explode("\t", $result);
				$maintext .= "<p><a href='index.php?action=file&cid=$cid'>$title</a>";
			};
		};

	} else if ( $class ) {
	
		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];
		
		$maintext .= "<p><a href='index.php?action=$action'>{%Documents}</a> > {%$cat}
			<hr>";
		
		$list = file_get_contents("$cqpfolder/text_$class.avs");

		foreach ( explode("\0", $list) as $val ) {
			if ( $item['values'] == "multi" ) {
				foreach ( explode(",", $val) as $pval ) $vals[trim($pval)]++;
			} else $vals[$val]++;
		};
		
		foreach ( $vals as $val => $cnt ) {
			$oval = $val;
			if ( $val == "" || $val == "_" ) $val = "({%none})";
			else if ( $item['type'] == "kselect" || $item['translate'] ) $val = "{%$class-$val}";
			$maintext .= "<p><a href='index.php?action=$action&class=$class&val=$oval'>$val</a>";
		}; 
	
	} else {
	
		$doctitle = getlangfile("browsertext", true);
	
		$maintext .= "$doctitle
			<hr>";
		foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {

			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			$cat = $item['display']; # $val = $item['long'] or 
	
			if ( ( $item['type'] == "select" || $item['type'] == "kselect" ) 
					&& !$item['admin'] || $username ) {	
				$maintext .= "<p><a href='index.php?action=$action&class=$key'>$cat</a>";
			};
	
	
		};
	};
	

?>