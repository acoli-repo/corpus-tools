<?php
	// Script to handle fatal errors that are reloaded
	// to avoid problems due to resubmitting
	// (c) Maarten Janssen, 2015

	$maintext .= "<h1>Fatal Error</h1>
		<p>A fatal error has occurred: <blockquote><b>{$_GET['msg']}</b></blockquote>";

	if ( $username ) {
		$maintext .= "<hr><p class='adminpart'>Click <a href='{$_SERVER['HTTP_REFERER']}'>here</a> to go back after correcting the error";
	};

?>