<?php
	
	$fileid = $_GET['cid'] or $fileid = $id;
	$filename = preg_replace("/.*\//", "", $fileid);
	if ( preg_match("/([^\/]+)\.xml/", $filename, $matches ) ) $id = $matches[1];

	if ( $settings['download']['admin'] == "1" && !$username ) 
		{ fatal ("Download of XML files not permitted"); };

	if ( !$settings['download']['options'] ) {
		$downloadoptions = array ( "raw" => array ( cmd => "cat [fn]", "display" => "raw XML" ) ); 	
	} else {
		$downloadoptions = $settings['download']['options'];
	};
	
	if ( $_GET['type'] ) $type = $_GET['type'];
	else if ( count($downloadoptions) == 1 ) $type = array_shift(array_keys($downloadoptions));

	if ( $type && $downloadoptions[$type] ) {
	
		$cmd = $downloadoptions[$type]['cmd'];
		header("Content-type: text/xml"); 
		header('Content-disposition: attachment; filename="'.$filename.'"');
		
		$cmd = preg_replace ( "/\[fn\]/", "$xmlfolder/$fileid", $cmd );
		$cmd = preg_replace ( "/\[id\]/", "$id", $cmd );
		passthru($cmd);
		exit;
		
	} else {
		$maintext .= "<h1>{%Download XML}</h1>
			<p>{%Select the way in which you want to download the XML for this file}";
			
		foreach ( $downloadoptions as $key => $val ) {
			$maintext .= "<p><a href='index.php?action=$action&type=$key&cid=$fileid'>{%{$val['display']}}</a>";
		};
		
	};

?>