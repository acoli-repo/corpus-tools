<?php
	// Main php script of TEITOK
	// called directly from index.php
	// (c) Maarten Janssen, 2015

	# Lower error reporting
	error_reporting(E_ERROR | E_PARSE);

	if ( $ttroot == "" ) $ttroot = "..";

	header('HTTP/1.0 200 OK'); ## Hard code this as NOT an error page!
	include ( "$ttroot/common/Sources/functions.php" ); # Global functions

	// Determine the location of the Smarty scripts
	if ( !defined(SMARTY_DIR) ) {
		# Look for Smarty in some standard locations if not defined in a non-standard location
		if ( file_exists('/usr/local/share/smarty/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/share/smarty/');
		else if ( file_exists('/usr/local/lib/smarty/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/lib/smarty/');
		else if ( file_exists('/usr/local/share/smarty/libs/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/share/smarty/libs/');
		else if ( file_exists('/usr/local/lib/smarty/libs/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/lib/smarty/libs/');
	};
	if ( !file_exists(SMARTY_DIR . 'Smarty.class.php') ) {
		// $smartypath = str_replace("Smarty.class.php", "", file_locate('Smarty.class.php')); //too slow so throw an error anyway
		print "Smarty engine not installed or not found. Please install Smarty or indicate where it can be found."; exit; // fatal() puts this into a loop
	};
	include(SMARTY_DIR . 'Smarty.class.php');
	
	// Deal with sessions and cookies
	ini_set("session.cookie_secure", 0); // TEITOK typically does not work on HTTPS, so SESSION vars have to be allow on HTTP
	session_start();

	// Have a uniform treatment of magic quotes
	// set_magic_quotes_runtime(false); // turn magic quotes off (this throws an error in newer PHP versions)
	if (get_magic_quotes_gpc()) {
		function strip_array($var) {
			return is_array($var)? array_map("strip_array", $var):stripslashes($var);
		}

		$_POST = strip_array($_POST);
		$_SESSION = strip_array($_SESSION);
		$_GET = strip_array($_GET);
	}

	// Load the settings.xml file (via PHP)
	include("$ttroot/common/Sources/settings.php");
	
	# Determine the folder to set a folder-specific user cookie
	if ( preg_match("/.*\/teitok\/([^\/]*?)\//", $_SERVER['SCRIPT_FILENAME'], $matches ) ) {
		$foldername = $matches[1];
	} else {
		$foldername = $_SERVER['SCRIPT_FILENAME'];
		$foldername = preg_replace("/.*\/www\/(html\/)?/", "", $foldername); # For /var/www/html
		$foldername = preg_replace("/.*\/WebServer\/Documents\//", "", $foldername); # For MacOS
		$foldername = preg_replace("/\/index\.php.*/", "", $foldername);
	}; 
	$sessionvar = "teitok-".preg_replace("/[^a-z0-9]/", "", $foldername); # Make the session relative to this project

	# Determine which language to use
	$deflang = $settings['languages']['default'] or $deflang = "en";
	if ( $_GET['lang'] ) $lang = $_GET['lang'];
	else if ( preg_match ( "/\/(...?)\/index\.php/", $_SERVER['REQUEST_URI'], $matches ) ) {
		if ( $matches[1] != $foldername ) $lang = $matches[1];
		else $lang = $deflang;	
	} else if ( $_COOKIE['lang'] ) $lang = $_COOKIE['lang'];
	else $lang = $deflang;
	if ( !$settings['languages']['prefixed'] ) setcookie("lang", $lang); # Store the language use in a session if not using prefixes
	else  setcookie("lang", "");
	
	# Determine the base URL and the root folder
	if ( $settings['defaults']['base']['url'] ) $baseurl = $settings['defaults']['base']['url'];
	else {
		$baseurl = str_replace('index.php', '', $_SERVER['SCRIPT_NAME'] );
		$baseurl = str_replace("/$lang/", '/', $baseurl );
	};
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 


	// Determine where to get the Javascript files from
	if ( $settings['defaults']['base']['javascript'] ) {
		$jsurl = $settings['defaults']['base']['javascript'];
	} else {
		$jsurl = "http://www.teitok.org/Scripts";
	};
	
	// Determine the main XML content 
	$mtxtelement = $settings['xmlfile']['xpath'];
	
	// Determine the locale
	$langloc = $settings['languages']['option'][$lang]['locale'];
	if ( $langloc ) setlocale(LC_ALL, $langloc);	

	// Deal with GET variables	
	$action = $_GET['action'] or $action = $_GET['page'];
	if ( $action == "" ) {
		$miniuri = preg_replace("/^.*?{$settings['defaults']['base']['foldername']}\//", "", $_SERVER['REQUEST_URI']);
		if ( preg_match("/^([^\/]+)\.(html|php)/", $miniuri, $matches ) ) $action = $matches[1];
		else if ( preg_match("/\//", $miniuri, $matches ) ) {
			$parts = explode("/", $miniuri );
			$action = array_shift($parts);
			$partdesc = explode(",", $settings['shorturl'][$action.'']['parts'] ); 
			for ( $i=0; $i<count($partdesc); $i++ ) {
				$_GET[$partdesc[$i]] = $parts[$i];
			};
		};
	};
	if ( $action == "index" || $action == "" || $action == "main" ) $action = "home";
	$act = $_GET['act'];
	if ( $_GET['debug'] ) $debug = 1;

	## Treat 404 redirect errors
	if ( $_SERVER['REDIRECT_STATUS'] == "404" ) {
		if ( preg_match( "/\/(.+)\./", $_SERVER['REDIRECT_URL'], $matches ) ) $action=  $matches[1];
	};

	if ( $_GET['template'] && file_exists("templates/{$_GET['template']}.tpl") ) $template =  $_GET['template'];
	if ( $template == "print" ) $printable = true;

	// create smarty object
	$smarty = new Smarty;

	// load user data 
	$user = $_SESSION[$sessionvar]; 
	$username = $user['email'];
	
	# Some settings that used to be flexible, but now fixed
	$xmlfolder = "xmlfiles";
	$imagefolder = "Facsimile";
	
	if ( file_exists("Sources/menu.php") ) include("Sources/menu.php");
	else include("$ttroot/common/Sources/menu.php");

	# Check whether the settings actually belong to this project
	if ( $user['permissions'] == "admin" && $foldername != $settings['defaults']['base']['foldername'] && $action != "admin" && $action != "adminsettings" && $action != "error"  && !$debug ) {
		print "<script langauge=Javasript>top.location='index.php?action=admin&act=checksettings';</script>";
		exit;
	};

	# Create an edit HTML button	
	if ( $username ) {
		$edithtml = "<div class='adminpart' style='float: right;'><a href='index.php?action=pageedit&id={$action}-$lang'>edit page</a></div>";
	};
	
	$sharedfolder = $settings['defaults']['shared']['folder'];
	
	## Determine which action to perform
	if ( file_exists( "Pages/$action-$lang.html" ) ) {
		# Local page - language depedent
		$maintext = $edithtml.file_get_contents ( "Pages/$action-$lang.html" );
	} else if ( file_exists( "Pages/$action.html" ) ) {
		# Local page - no language
		$maintext = $edithtml.file_get_contents ( "Pages/$action.html" );
	} else if ( file_exists( "Pages/$action-$deflang.html" ) ) {
		# Local page - default language
		$maintext = $edithtml.file_get_contents ( "Pages/$action-$deflang.html" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Sources/$action.php" ) ) {
		# Locally shared script
		include ( "$sharedfolder/Sources/$action.php" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Pages/$action-$lang.html" ) ) {
		# Locally shared page
		$maintext = $edithtml.file_get_contents (  "$sharedfolder/Pages/$action-$lang.html" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Pages/$action.html" ) ) {
		# Locally shared page
		$maintext = $edithtml.file_get_contents (  "$sharedfolder/Pages/$action.html" );
	} else if ( file_exists( "Sources/$action.php" ) ) {
		# Local script
		include ( "Sources/$action.php" );
	} else if ( file_exists( "$ttroot/common/Pages/$action-$lang.html" ) ) {
		# Common page
		$maintext = $edithtml.file_get_contents ( "$ttroot/common/Pages/$action-$lang.html" );
	} else if ( file_exists( "$ttroot/common/Pages/$action.html" ) ) {
		# Common page
		$maintext = $edithtml.file_get_contents ( "$ttroot/common/Pages/$action.html" );
	} else if ( file_exists( "$ttroot/common/Sources/$action.php" ) ) {
		# Common script
		include ( "$ttroot/common/Sources/$action.php" );
	} else if ( $settings['xmlreader'][$action] ) {
		# XML Reader file
		$xmlid = $action;
		include ( "$ttroot/common/Sources/xmlreader.php" );
	} else {
		# Nothing appropriate
		$maintext = getlangfile ( "notfound", true );
		header('HTTP/1.0 404 Not Found'); ## Hard code this as NOT an error page!
		if ( $username ) {
			$maintext .= "<hr><span class=adminpart><a href='index.php?action=pageedit&id=new&name=$action-$lang.html'>create</a> this as an HTML page</span>";
		};		
	};

	# Treat internationalisation CW-style
	$maintext = i18n($maintext);
	$menu = i18n($menu);

	# Add the TEITOK footer
	if ( !$noteitokmessage ) {
		$menu .=  "<hr style='opacity: 0.5; margin-top: 40px;'><p style='opacity: 0.5; font-size: smaller;' onClick=\"window.open('http://www.teitok.org/site/index.php', 'teitok');\">Powered by TEITOK<br>&copy; Maarten Janssen, 2014-</p>";
	};
	
	// Load smarty content
	if ( !isset($pagetitle) ) $pagetitle = $pagetitles[$action] or $pagetitle = $settings['defaults']['title']['display'];
	$smarty->assign("title", $pagetitle);
	$smarty->assign("header", $pagetitle);
	$smarty->assign("menu", $menu);
	$smarty->assign("maintext", $maintext);

	// if more smarty variables were defined, load them
	foreach ( $moresmarty as $key => $val ) {
		if ( $seti18n{$key} ) $val = i18n($val); 
		$smarty->assign($key, $val);	
	};
	
	// load the template
	if ( !isset($template) ) {
 		if ( $username && file_exists("templates/admin.tpl" ) ) $template = "admin";
 		else $template = "main";
	};
	if ( file_exists("templates/{$template}_$lang.tpl" ) ) $template = "{$template}$lang";
	
	// display the Smarty page
	$smarty->display("$template.tpl");

?>
