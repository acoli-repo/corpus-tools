<?php

	# Get corpus/file data via AJAX
	header('Content-type: application/json');
	
	if ( $_GET['cid'] ) {
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
	};

	if ( $_GET['cqp'] || $cqptype[$_GET['data']] ) {
		# Lookup all occurrences
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
	};
	
	if ( $_GET['data'] == "facs" ) {

		# Get the list of facsimile images from an XML file
		if ( !$ttxml->xml ) { print "{\"error\": \"unable to load XML file\"]}"; exit; }

		$facslist = array();
		foreach ( $ttxml->xml->xpath("//pb[@facs]") as $pb  ) {
			$facs = $pb['facs'];
			if ( file_exists("Thumbnails/$facs") ) $ffolder = "Thumbnails"; else   $ffolder = "Facsimile";
			$facs = $ffolder."/".$facs;
			array_push($facslist, $facs);
		};
			$maintext .= "<hr><a href='index.php?action=$action'>{%back to list}</a> &bull; ".$ttxml->viewswitch();
		
		print "{\"cid\": \"{$_GET['cid']}\", \"facs\": [\"".join("\", \"", $facslist)."\"]}"; 

	} else if ( $_GET['data'] == "docinfo" ) {
		
		$rawtable = $ttxml->tableheader("", false);
		print i18n($rawtable);
		exit;
		
	} else {
		print "{\"error\": \"no data selected\"]}"; 
	};
	exit;
	
?>