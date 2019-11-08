<?php

	$type = $_GET['type'];

	$grouprec = $settings['permissions']['groups'][$type];
		
	if ( !$username ) {
		# Not logged in
		$maintext .= getlangfile("notli-default", 1);
	} else if ( file_exists("Pages/notli-$type.html") || file_exists("Pages/notli-$type-$lang.html") || file_exists("$ttroot/common/Pages/notli-$type.html") ) {
		$maintext .= getlangfile("notli-$type", 1);		
	} else if ( $grouprec['message'] ) {
		$maintext .= "<h1>{%Not Allowed}</h1><p>".$grouprec['message'];		
	} else {
		$maintext .= getlangfile("notli-nogroup", 1);
	};
	
?>