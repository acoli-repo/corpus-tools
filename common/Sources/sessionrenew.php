<?php

	// PHP script to keep the session alive when doing potentially long edits
	if ( $_GET['type'] == "text" ) {
		header('Content-Type: text/plain');
		if ( $username != "" ) {
			print "logged in";
		} else if ( $userid != "" ) {
			print "sso logged in";
		} else {
			print "logged out";
		};
	} else if ( $_GET['type'] == "json" ) {
		header('Content-Type: text/json');
		if ( $username != "" ) {
			print "{'message': 'logged in'}";
		} else {
			print "{'error': 'logged out'}";
		};
	} else {
		if ( $username != "" ) {
			// logged in - provide an image
			header('Content-Type: image/png');
			echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
		} else {
			// logged out - throw an error
			header("HTTP/1.0 403 Forbidden");
		};
	};
	exit;

?>