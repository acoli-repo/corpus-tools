<?php

	// PHP script to keep the session alive when doing potentially long edits
	if ( $username != "" ) {
		// logged in - provide an image
		header('Content-Type: image/png');
		echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
	} else {
		// logged out - throw an error
		header("HTTP/1.0 404 Not Found");
	};
	exit;

?>