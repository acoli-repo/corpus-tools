<?php
	// Script to handle fatal errors that are reloaded
	// to avoid problems due to resubmitting
	// (c) Maarten Janssen, 2015

	$filename = "tmp/error_{$_GET['msg']}.txt";
	$message = file_get_contents($filename);
	
	if ( !$message ) {
		if ( $username ) $tmp = $_GET['msg'];
		$message = "(error message $filename no longer available)";
	} else {
		unlink($filename);
	};
	
	$maintext .= "<h1>Fatal Error</h1>
		<p>A fatal error has occurred: <blockquote><b>{$message}</b></blockquote>";

	if ( $username ) {
		$maintext .= "<hr><p class='adminpart'>Click <a href='{$_SERVER['HTTP_REFERER']}'>here</a> to go back after correcting the error";
	};

?>