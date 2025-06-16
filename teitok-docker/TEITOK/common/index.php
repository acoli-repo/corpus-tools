<?php
	header('HTTP/1.0 200 OK'); ## Hard code this as NOT an error page!
	define('SMARTY_DIR', '/usr/local/share/smarty/');
	include('/usr/local/share/smarty/Smarty.class.php');
	// include('Resources/settings.php');
	if ( $_GET['debug'] == "1" ) $debug = true;
	session_start();

	error_reporting( error_reporting() & ~E_NOTICE );
	
	$thisdir = preg_replace("/\/[^\/]+$/", "", $_SERVER['SCRIPT_FILENAME'] );
	
	$action = $_GET['action'] or $action = $_GET['page'] or $action = "home";
	$act = $_GET['act'];

	## Treat 404 redirect errors
	if ( $_SERVER['REDIRECT_STATUS'] == "404" ) {
		if ( preg_match( "/\/(.+)\./", $_SERVER['REDIRECT_URL'], $matches ) ) $action=  $matches[1];
	};

	$template =  $_GET['template'];
	if ( $template == "print" ) $printable = true;
	include ( "Sources/functions.php" ); # Global functions


	// create object
	$smarty = new Smarty;

	// define the folders 
	$smarty->setTemplateDir($thisdir.'/project');
	$smarty->compile_dir = $thisdir.'/templates_c';

	// call the main php script to add the content to the Smarty object
	include ( "Sources/main.php" );

	if ( $template == "" ) $template = "htmltemplate";

	// display it
	$smarty->display("$template.tpl");

?>
