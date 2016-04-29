<?php

	$pid = $_GET['pid'];
	
	if ( !file_exists("tmp/pid$pid.log") ) { fatal ("No such process: $pid"); };

	$log = file_get_contents("tmp/pid$pid.log");

	$maintext .= "<h1>Process report: $pid</h1>
	
		<div>$log</div>";



	if ( !strstr($log, "Process finished") ) {
		$maintext .= "<hr><p>Process still running. This page will reload every 10s</p>
		<script type=\"text/javascript\">
					setTimeout(function () { 
					  location.reload();
					}, 10 * 1000);
				</script>";
	};

?>