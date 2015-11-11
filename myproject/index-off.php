<?php


	// define where the Smarty.class can be found
	// define('SMARTY_DIR', '/usr/local/share/smarty/');

	// define which time-zone to use (obligatory for date in PHP)
	date_default_timezone_set('UTC');
	
	// define which errors to report
	ini_set('display_errors', '0');
	error_reporting(E_ERROR|E_WARNING);

	// call the main php script and only use resources from here
	include ( "../common/Sources/main.php" );

?>
