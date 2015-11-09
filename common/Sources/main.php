<?php
	// Main php script of TEITOK
	// called directly from index.php
	// (c) Maarten Janssen, 2015

	header('HTTP/1.0 200 OK'); ## Hard code this as NOT an error page!
	if ( !defined(SMARTY_DIR) ) {
		# Look for Smarty if not defined in a non-standard location
		define('SMARTY_DIR', '/usr/local/share/smarty/');
	};
	if ( !file_exists(SMARTY_DIR . 'Smarty.class.php') ) {
		print "Smarty engine not installed or not found. Install Smarty, or indicate 
			the location of the Smarty directory in index.php";
		exit;
	};
	include(SMARTY_DIR . 'Smarty.class.php');
	
	ini_set("session.cookie_secure", 0); // TEITOK typically does not work on HTTPS, so SESSION vars have to be allow on HTTP
	session_start();

	set_magic_quotes_runtime(false); // turn magic quotes off
	if (get_magic_quotes_gpc()) {
		function strip_array($var) {
			return is_array($var)? array_map("strip_array", $var):stripslashes($var);
		}

		$_POST = strip_array($_POST);
		$_SESSION = strip_array($_SESSION);
		$_GET = strip_array($_GET);
	}

	if ( file_exists('Resources/settings.php') ) include('Resources/settings.php');
	include('../common/Sources/settings.php');
	
	# Determine the folder to set a folder-specific user cookie
	if ( preg_match("/\/teitok\/(.*?)\//", $_SERVER['SCRIPT_FILENAME'], $matches ) ) {
		$foldername = $matches[1];
	} else $foldername = "teitok";
	$sessionvar = "teitok-$foldername";

	
	# Determine which language to use
	$deflang = $settings['languages']['default'] or $deflang = "en";
	if ( $_GET['lang'] ) $lang = $_GET['lang'];
	else if ( preg_match ( "/\/(..)\/index\.php/", $_SERVER['REQUEST_URI'], $matches ) ) {
		$lang = $matches[1];
	} else if ( $_COOKIE['lang'] ) $lang = $_COOKIE['lang'];
	else $lang = $deflang;
	if ( !$settings['languages']['prefixed'] ) setcookie("lang", $lang); # Store the language use in a session if not using prefixes
	else  setcookie("lang", "");
	
	# Determine the base URL
	if ( $settings['defaults']['base']['url'] ) $baseurl = $settings['defaults']['base']['url'];
	else {
		$baseurl = str_replace('index.php', '', $_SERVER['SCRIPT_NAME'] );
		$baseurl = str_replace("/$lang/", '/', $baseurl );
	};
	
	$mtxtelement = $settings['xmlfile']['xpath'];
	
	$langloc = $settings['languages']['option'][$lang]['locale'];
	if ( $langloc ) setlocale(LC_ALL, $langloc);	
	
	# Which of these works better?
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 
	# $thisdir = preg_replace("/\/[^\/]+$/", "", $_SERVER['SCRIPT_FILENAME'] );
	
	$action = $_GET['action'] or $action = $_GET['page'] or $action = "home";
	$act = $_GET['act'];
	if ( $_GET['debug'] ) $debug = 1;

	## Treat 404 redirect errors
	if ( $_SERVER['REDIRECT_STATUS'] == "404" ) {
		if ( preg_match( "/\/(.+)\./", $_SERVER['REDIRECT_URL'], $matches ) ) $action=  $matches[1];
	};

	if ( $_GET['template'] && file_exists("templates/{$_GET['template']}.tpl") ) $template =  $_GET['template'];
	if ( $template == "print" ) $printable = true;
	include ( "../common/Sources/functions.php" ); # Global functions

	// create object
	$smarty = new Smarty;

	// define the folders 
	#$smarty->setTemplateDir($thisdir.'/templates');
	#$smarty->compile_dir = $thisdir.'/templates_c';

	if ( $anonymous && !$_SESSION[$sessionvar] ) {
		$username = "Anonymous";
	} else {
		$user = $_SESSION[$sessionvar]; 
		$username = $user['email'];
	};
	
	$xmlfolder = "xmlfiles";
	$imagefolder = "Facsimile";
	include('../common/Sources/menu.php');
			
	## Main actions
	if ( file_exists( "Pages/$action-$lang.html" ) ) {
		
		$maintext = file_get_contents ( "Pages/$action-$lang.html" );

	} else if ( file_exists( "Pages/$action.html" ) ) {
		
		$maintext = file_get_contents ( "Pages/$action.html" );

	} else if ( file_exists( "Pages/$action-$deflang.html" ) ) {
		
		$maintext = file_get_contents ( "Pages/$action-$deflang.html" );

	} else if ( file_exists( "Sources/$action.php" ) ) {

		include ( "Sources/$action.php" );
	
	} else if ( file_exists( "../common/Pages/$action.html" ) ) {
		
		$maintext = file_get_contents ( "../common/Pages/$action.html" );
		
		
	} else if ( file_exists( "../common/Sources/$action.php" ) ) {
		
		include ( "../common/Sources/$action.php" );
		
	} else {
		
		$maintext = getlangfile ( "notfound", true );
		
		if ( $username ) {
			$maintext .= "<hr><span class=adminpart><a href='index.php?action=pageedit&id=new&name=$action-$lang.html'>create</a> this as an HTML page</span>";
		};
		
	};

	# Treat internationalisation CW-style
	$maintext = i18n($maintext);
	$menu = i18n($menu);


	# Add the TeiTOK footer
	$menu .=  "<hr style='background-color: #aaaaaa; margin-top: 40px;'><p style='opacity: 0.5; font-size: 9pt;' onClick=\"window.open('http://teitok.corpuswiki.org/site/index.php', 'teitok');\">Powered by TEITOK<br>&copy; Maarten Janssen, 2014</p>";

	// take care of the title and header
	if ( !$pagetitle ) $pagetitle = $pagetitles[$action] or $pagetitle = $settings['defaults']['title']['display'];
	$smarty->assign(title, $pagetitle);
	$smarty->assign(header, $pagetitle);
	$smarty->assign(menu, $menu);
	
	

	// overrule page by error
	// if ( $criticalerror ) $maintext = $criticalerror;
	$smarty->assign(maintext, $maintext);
	
	// display the debug info if any
	// if ( $debug) $smarty->assign(debug, "<!--\nDebugging\n $debug \n-->");

	if ( $template == "" ) {
 		if ( $username && file_exists("templates/admin.tpl" ) ) $template = "admin";
 		else $template = "main";
	};
	if ( file_exists("templates/{$template}_$lang.tpl" ) ) $template = "{$template}$lang";
	
	// display it
	$smarty->display("$template.tpl");

?>
