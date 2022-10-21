<?php
	
	$fileid = $_GET['cid'] or $fileid = $_GET['id'];
	$filename = preg_replace("/.*\//", "", $fileid);
	if ( preg_match("/([^\/]+)\.xml/", $filename, $matches ) ) $id = $matches[1];

	if ( $settings['download']['admin'] == "1" && !$username ) 
		{ fatal ("Download of XML files not permitted"); };

	if ( $settings['download']['disabled'] == "1" ) 
		{ fatal ("Download of XML files not permitted"); };

	if ( !$settings['download']['options'] ) {
		$downloadoptions = array ( "raw" => array ( "cmd" => "cat [fn]", "display" => "raw XML" ) ); 	
	} else {
		$downloadoptions = $settings['download']['options'];
	};
	
	if ( $_GET['type'] ) $type = $_GET['type'];
	else if ( count($downloadoptions) == 1 ) $type = array_shift(array_keys($downloadoptions));

	if ( $type && $downloadoptions[$type] ) {
	
		$mime = $downloadoptions[$type]['mime'] or $mime = "text/xml";
		if ( $downloadoptions[$type]['outfile'] ) {
			$outfile = $downloadoptions[$type]['outfile'];
			$outfile = preg_replace ( "/\[fn\]/", "$fileid", $outfile );
			$outfile = preg_replace ( "/\[id\]/", "$id", $outfile );
		} else $outfile = $filename;
	
		$cmd = $downloadoptions[$type]['cmd'];
		$cmd = preg_replace ( "/\[fn\]/", "$xmlfolder/$fileid", $cmd );
		$cmd = preg_replace ( "/\[id\]/", "$id", $cmd );

		header("Content-type: text/xml"); 
		header('Content-disposition: attachment; filename="'.$outfile.'"');
		
		passthru($cmd);
		exit;
		
	} else {
		
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		
		$dltit = $settings['download']['title'] or $dltit = "Download XML";
		
		$maintext .= "<h2>{%$dltit}</h2>
			<h1>".$ttxml->title()."</h1>
			".$ttxml->tableheader()."
			<p>{%Select download format}
			<table>";
			
		foreach ( $downloadoptions as $key => $val ) {
			if ( $val['xprest'] || $val['xpcond'] ) {
				if ( $val['xprest'] && $ttxml->xml->xpath($val['xprest']) ) continue;
				if ( $val['xpcond'] && !$ttxml->xml->xpath($val['xpcond']) ) continue;
			};
			$maintext .= "<tr><td><a href='index.php?action=$action&type=$key&cid=$fileid'>{%{$val['display']}}</a>
				<td>{$val['description']}";
		};
		$maintext .= "</table>";
	};

?>