<?php
	// File browser - browse files by category
	// (c) Maarten Janssen, 2018

	$class = $_GET['class'];
	$val = $_GET['val'];
	
	$maintext .= "<h1>{%Document Browser}</h1>";

	$titlefld = $settings['cqp']['titlefld'];
	if ( !$titlefld ) 
		if ( $settings['cqp']['sattributes']['text']['title'] ) $titlefld = "text_title"; else $titlefld = "text_id";

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

	if ( $settings['defaults']['locale'] ) $localebit = ", '{$settings['defaults']['locale']}'";

	$maintext .= "
		<script language=Javascript>
			function sortList(ul){
				var new_ul = ul.cloneNode(false);

				// Add all lis to an array
				var lis = [];
				for(var i = ul.childNodes.length; i--;){
					if(ul.childNodes[i].nodeName === 'LI') {
						lis.push(ul.childNodes[i]);
					};
				}

				// Sort the lis in descending order - locale dependent
				lis.sort((a, b) => a.getAttribute('key').localeCompare(b.getAttribute('key')$localebit));
				console.log(lis);

				// Add them into the ul in order
				for(var i = 0; i < lis.length; i++)
					new_ul.appendChild(lis[i]);
				ul.parentNode.replaceChild(new_ul, ul);
			};
		</script>
		";
	
	if ( $class && $val ) {

		// Do not allow searches while the corpus is being rebuilt...
		if ( file_exists("tmp/recqp.pid") ) {
			fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
		};

		include ("$ttroot/common/Sources/cwcqp.php");
		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$val = htmlentities($val);
		if ( $item['values'] == "multi" ) $cqpquery = "Matches = <text> [] :: match.text_$class = '.*$val.*'";
		else $cqpquery = "Matches = <text> [] :: match.text_$class = '$val'";
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
			<hr><ul id=sortlist>";
	
		if ( $size > 0 ) {
			$catq = "tabulate Matches $start $stop match text_id, match $titlefld";
			$results = $cqp->exec($catq); 
		
			foreach ( explode("\n", $results) as $result ) {
				list ( $cid, $title ) = explode("\t", $result); 
				if ( $titlefld == "text_id" ) {
					$title = preg_replace("/.*\/(.*?)\.xml/", "$1", $cid);
				};
				if ( $cid && $title ) $maintext .= "<li key='$title'><a href='index.php?action=file&cid=$cid'>$title</a></li>";
			};
			$maintext .= "</ul>";
		} else if ( $username ) $maintext .= "<p class=adminpart>Failed query: ".htmlentities($cqpquery);

	} else if ( $class ) {
	
		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];
		
		$maintext .= "<p><a href='index.php?action=$action'>{%Documents}</a> > {%$cat}
			<hr><ul id=sortlist>";
		
		$list = file_get_contents("$cqpfolder/text_$class.avs");

		foreach ( explode("\0", $list) as $val ) {
			if ( $item['values'] == "multi" ) {
				foreach ( explode(",", $val) as $pval ) $vals[trim($pval)]++;
			} else $vals[$val]++;
		};
		
		foreach ( $vals as $val => $cnt ) {
			$oval = $val;
			if ( $val == "" || $val == "_" ) {
				if ( !$settings['cqp']['listnone'] ) continue;
				$val = "({%none})";
			} else if ( $item['type'] == "kselect" || $item['translate'] ) $val = "{%$class-$val}";
			$maintext .= "<li key='$val'><a href='index.php?action=$action&class=$class&val=$oval'>$val</a></li>";
		}; 
		$maintext .= "</ul><script language=Javascript>sortList(document.getElementById('sortlist'));</script>";
	
	} else {
	
		$doctitle = getlangfile("browsertext", true);
	
		$maintext .= "$doctitle
			<hr><ul id=sortlist>";
		foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {

			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			$cat = $item['display']; # $val = $item['long'] or 
	
			if ( ( $item['type'] == "select" || $item['type'] == "kselect" ) 
					&& ( !$item['admin'] || $username ) ) {	
				$maintext .= "<li key='$cat'><a href='index.php?action=$action&class=$key'>$cat</a></li>";
			};	
		};
		$maintext .= "</ul>"; //<script language=Javascript>sortlist(document.getElementById('sortlist'));</script>";
	};

	

?>