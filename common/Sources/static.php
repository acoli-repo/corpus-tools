<?php

	# List the static dumps of the corpus
	
	$versionxml = simplexml_load_file("Resources/static.xml");
	if ( !$versionxml ) fatal("Could not read the static version XML file");
	
	$maintext .= "<h1>Static Versions</h1>";
	
	if ( $_GET['version'] ) {
		$version = current($versionxml->xpath("//version[@id='{$_GET['version']}']"));
	} else {
	
		$maintext .= getlangfile("static-text");
		
		$maintext .= "<table><tr><td><th>ID<th>Name<th>Date";
		foreach ( $versionxml->xpath("//version") as $version ) {
			$maintext .= "<tr><td><a href='index.php?action=cqpraw&version={$version['id']}'>{%Search}</a><td>{$version['id']}<td>{$version['display']}";
			$maintext .= "<td>".current($version->xpath("./date"));
		};
		$maintext .= "<table>";
	
	};


?>