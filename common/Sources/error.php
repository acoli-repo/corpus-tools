<?php
	// Script to handle fatal errors that are reloaded
	// to avoid problems due to resubmitting
	// (c) Maarten Janssen, 2015

	$maintext .= "<h1>Fatal Error</h1>
		<p>A fatal error has occurred: <blockquote><b>{$_GET['msg']}</b></blockquote>";

?>