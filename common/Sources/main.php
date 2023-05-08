<?php
	// Main php script of TEITOK
	// called directly from index.php
	// Maarten Janssen, 2015

	# Lower error reporting
	error_reporting(E_ERROR | E_PARSE);
	session_start();

	if ( $ttroot == "" ) $ttroot = "..";

	include ( "$ttroot/common/Sources/functions.php" ); # Global functions

	// Load the settings.xml file (via PHP)
	include("$ttroot/common/Sources/settings.php");
	
	if ( !$hprot )  if ( isSecure() || ( is_array($settings['defaults']['base']) &&  $settings['defaults']['base']['protocol'] == "https" ) ) {
		# TODO : should this not specify HTTPS?
		header('HTTP/1.0 200 OK'); ## Hard code this as NOT an error page! 
		$hprot = "https";
	} else {
		header('HTTP/1.0 200 OK'); ## Hard code this as NOT an error page!
		$hprot = "http";
		ini_set("session.cookie_secure", 0); // TEITOK typically does not work on HTTPS, so SESSION vars have to be allow on HTTP
	};

	// Parse the URL if it is not just index.php
	$phpself = $_SERVER['SCRIPT_NAME'];
	$urireq = $_SERVER['REQUEST_URI'];
	if ( $settings['defaults']['base']['ttroot'] && $settings['defaults']['base']['foldername'] ) $basefldr = $settings['defaults']['base']['ttroot'].$settings['defaults']['base']['foldername']."/";
	else $basefldr = str_replace("index.php", "", $phpself);
	if ( substr($urireq, 0, strlen($basefldr)) == $basefldr) {
		$basereq = substr($urireq, strlen($basefldr));
		if ( preg_match("/^(...?)\/(.*)$/", $basereq, $matches) 
			&& is_array($settings['languages']) && $settings['languages']['options'][$matches[1]] # Only allow defined langs via the URL
		) { 
			$basereq = $matches[2]; $urllang = $matches[1];
		};
	};
	
	# Determine the folder to set a folder-specific user cookie
	$scriptfolder = realpath($_SERVER['SCRIPT_FILENAME']);
	if ( !$foldername )
	if ( preg_match("/([^\/]+)\/$/", $basefldr, $matches ) ) {
		$foldername = $matches[1];
	} else if ( preg_match("/\/([^\/]*)\/\.\.\/index\.php\//", $scriptfolder, $matches ) ) {
		$foldername = $matches[1];
	} else if ( preg_match("/\/([^\/]*)\/index\.php/", $scriptfolder, $matches ) ) {
		$foldername = $matches[1];
	} else if ( preg_match("/.*\/teitok\/([^\/]*?)\//", $scriptfolder, $matches ) ) {
		$foldername = $matches[1];
	} else {
		$foldername = $scriptfolder;
		$foldername = preg_replace("/.*\/www\/(html\/)?/", "", $foldername); # For /var/www/html
		$foldername = preg_replace("/.*\/WebServer\/Documents\//", "", $foldername); # For MacOS
		$foldername = preg_replace("/\/index\.php.*/", "", $foldername);
	}; 

	// Deal with sessions and cookies
	$gsessionvar = "teitok-".preg_replace("/[^a-z0-9]/", "", $_SERVER['SERVER_NAME'] ); # Allow server-wide login
	$sessionvar = "teitok-".preg_replace("/[^a-z0-9]/", "", $foldername); # Make the session relative to this project
	
	// load user data 
	$user = $_SESSION[$sessionvar] or $user = $_SESSION[$gsessionvar]; 
	$username = $user['email'];

	// Determine the location of the Smarty scripts
	if ( getenv('SMARTY_DIR') != "" && !defined('SMARTY_DIR') && file_exists(getenv('SMARTY_DIR').'Smarty.class.php') ) define('SMARTY_DIR', getenv('SMARTY_DIR'));
	if ( !defined('SMARTY_DIR') ) {
		# Look for Smarty in some standard locations if not defined in a non-standard location
		if ( file_exists('/usr/local/share/smarty/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/share/smarty/');
		else if ( file_exists('/usr/local/lib/smarty/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/lib/smarty/');
		else if ( file_exists('/usr/local/share/smarty/libs/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/share/smarty/libs/');
		else if ( file_exists('/usr/local/lib/smarty/libs/Smarty.class.php') ) 
			define('SMARTY_DIR', '/usr/local/lib/smarty/libs/');
		else 
			define('SMARTY_DIR', '/home/git/smarty/libs/');
	};
	if ( !file_exists(SMARTY_DIR . 'Smarty.class.php') ) {
		if ( $username ) {
			print "Smarty engine not installed or not found. Please install Smarty or indicate where it can be found - assuming ".SMARTY_DIR; exit; // fatal() puts this into a loop
		} else print "This site is currently down due to technical problems";
	};
	include(SMARTY_DIR . 'Smarty.class.php');
	

	// Have a uniform treatment of magic quotes
	// set_magic_quotes_runtime(false); // turn magic quotes off (this throws an error in newer PHP versions)
	// if ( get_magic_quotes_gpc() ) { // deprecated
	if ( !getenv('oldquotes') ) {
		function strip_array($var) {
			return is_array($var)? array_map("strip_array", $var):stripslashes($var);
		}

		$_POST = strip_array($_POST);
		$_SESSION = strip_array($_SESSION);
		$_GET = strip_array($_GET);
	}


	# Determine which language to use
	$deflang = $settings['languages']['default'] or $deflang = "en";
	if ( $_GET['lang'] ) $lang = $_GET['lang'];
	else if ( $urllang ) {
		$lang = $urllang;
	} else if ( $_SESSION['lang'] ) $lang = $_SESSION['lang'];
	else $lang = $deflang;
	if ( is_array($settings['languages']) && !$settings['languages']['prefixed'] ) $_SESSION['lang'] = $lang;
	
	# Determine the base URL and the root folder
	if ( !$baseurl )
	if ( $settings['defaults']['base']['url'] ) {
		$baseurl = str_replace('{$corpusfolder}', $foldername, $settings['defaults']['base']['url']);
	} else if ( $basefldr ) {
		$baseurl = $basefldr;
	} else {
		$baseurl = preg_replace('/index.php.*/', '', $_SERVER['SCRIPT_NAME'] );
		$baseurl = str_replace("/$lang/", '/', $baseurl );
	};
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 
	
	# Set the base META tag when asked
	$rooturl = str_replace("{project}", $foldername, $baseurl);
	$rooturl = str_replace("{lang}", $lang, $rooturl);
	if ( $settings['defaults']['base']['meta'] ) {
		$moresmarty['baseurl'] = $rooturl;
	}; 

	// Determine where to get the Javascript files from
	if ( $settings['defaults']['base']['javascript'] ) {
		$jsurl = $settings['defaults']['base']['javascript'];
	} else {
		$jsurl = "$hprot://www.teitok.org/Scripts";
	};
	
	// Determine the main XML content 
	$mtxtelement = "//text";
	if ( is_array($settings['xmlfile']) && $settings['xmlfile']['xpath'] ) $mtxtelement = $settings['xmlfile']['xpath'];
	
	// Determine the locale
	$langloc = current($settingsxml->xpath("//languages/options/item[@key=\"$lang\"]/@locale"));
	if ( $langloc ) setlocale(LC_ALL, $langloc);	

	// Deal with GET variables	
	$action = $_GET['action'] or $action = $_GET['page'];
	if ( $action == "" ) {
		$tmp = str_replace("/", "\\/", preg_quote($baseurl));
		$miniuri = preg_replace("/^.*$tmp/", "", $_SERVER['REQUEST_URI']);
		if ( preg_match("/([^\/]+)\.(html|php)/", $basereq, $matches ) ) $action = $matches[1];
		else if ( preg_match("/^([^\/]+)\/([^\/]+\.xml)$/", $basereq, $matches ) ) {
			$action = $matches[1]; $_GET['cid'] = $matches[2];
		} else if ( preg_match("/\//", $miniuri, $matches ) && !preg_match("/index\.php/", $miniuri) ) {
			$parts = explode("/", $miniuri );
			$action = array_shift($parts);
			$partdesc = explode(",", $settings['shorturl'][$action.'']['parts'] ); 
			for ( $i=0; $i<count($partdesc); $i++ ) {
				$_GET[$partdesc[$i]] = $parts[$i];
			};
		};
	};
	if ( $action == "index" || $action == "" || $action == "main" ) $action = $settings['defaults']['home'] or $action = "home";
	$act = $_GET['act'];
	if ( $_GET['debug'] && $user['permissions'] == "admin" ) $debug = 1;
	
	if ( preg_match("\.\.\/", $action) ) $action = "notfound"; # Prevent people from going below the ttroot

	## Treat 404 redirect errors
	if ( $_SERVER['REDIRECT_STATUS'] == "404" ) {
		if ( preg_match( "/\/(.+)\./", $_SERVER['REDIRECT_URL'], $matches ) ) $action=  $matches[1];
	};

	$template = "main";
	if ( $_GET['template'] && file_exists("templates/{$_GET['template']}.tpl") ) $template =  $_GET['template'];
	if ( $template == "print" ) $printable = true;

	// create smarty object
	$smarty = new Smarty;

	
	# Some settings that used to be flexible, but now fixed
	$xmlfolder = "xmlfiles";
	$imagefolder = "Facsimile";
	
	if ( file_exists("Sources/menu.php") ) include("Sources/menu.php");
	else include("$ttroot/common/Sources/menu.php");

	# Create an edit HTML button	
	if ( $username ) {
		$baseaction = $_GET['action'] or $baseaction = "home";
		$editaction = preg_replace("/-[a-z]{2,3}$/", "", $baseaction);
		$edithtml = "<div class='adminpart' style='float: right;'><a href='index.php?action=pageedit&id={$editaction}&pagelang=$lang'>edit page</a></div>";
	};
			
	# Use the shared template if no local one exists
	if (  $_GET['template'] == "none" ) {
		# Use the shared-default template as a failsafe if your template does not load
		$templatedir = realpath("$ttroot/common/../projects/default-shared/templates");
		$smarty->setTemplateDir($templatedir);
		$template = "main";
		if ( !is_writable("templates_c") ) $smarty->setCompileDir("$sharedfolder/templates_c");
	} else if (  !file_exists("templates/$template.tpl") && file_exists ("$sharedfolder/templates/main.tpl") ) {
		$smarty->setTemplateDir("$sharedfolder/templates");
		if ( !file_exists ("$sharedfolder/templates/$template.tpl") ) $template = "main";
		if ( !is_writable("templates_c") ) $smarty->setCompileDir("$sharedfolder/templates_c");
	};
	
	## Determine which action to perform
	if ( file_exists( "Pages/$action-$lang.html" ) ) {
		# Local page - language depedent
		$maintext = $edithtml.file_get_contents ( "Pages/$action-$lang.html" );
	} else if ( file_exists( "Pages/$action-$lang.md" ) ) {
		# Local page - no language
		$maintext = $edithtml.md2html(file_get_contents ( "Pages/$action-$lang.md" ));
	} else if ( file_exists( "Pages/$action.html" ) ) {
		# Local page - no language
		$maintext = $edithtml.file_get_contents ( "Pages/$action.html" );
	} else if ( file_exists( "Pages/$action.md" ) ) {
		# Local page - no language
		$maintext = $edithtml.md2html(file_get_contents ( "Pages/$action.md" ));
	} else if ( file_exists( "Pages/$action-$deflang.html" ) ) {
		# Local page - default language
		$maintext = $edithtml.file_get_contents ( "Pages/$action-$deflang.html" );
	} else if ( file_exists( "Sources/$action.php" ) ) {
		# Local script
		include ( "Sources/$action.php" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Sources/$action.php" ) ) {
		# Locally shared script
		include ( "$sharedfolder/Sources/$action.php" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Pages/$action-$lang.html" ) ) {
		# Locally shared page
		$maintext = $edithtml.file_get_contents (  "$sharedfolder/Pages/$action-$lang.html" );
	} else if ( $sharedfolder && file_exists( "$sharedfolder/Pages/$action.html" ) ) {
		# Locally shared page
		$maintext = $edithtml.file_get_contents (  "$sharedfolder/Pages/$action.html" );
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
		header('HTTP/1.0 404 Not Found'); ## Hard code this as not found
		if ( $username ) {
			$maintext .= "<hr><span class=adminpart><a href='index.php?action=pageedit&id=new&name=$action-$lang.html'>create</a> this as an HTML page</span>";
		};		
	};

	# Treat internationalisation CW-style
	$maintext = i18n($maintext);
	$menu = i18n($menu);

	# Add the TEITOK footer
	if ( !isset($noteitokmessage) || !$noteitokmessage ) {
		$menu .=  "<hr style='opacity: 0.5; margin-top: 40px;'><p id=powby style='opacity: 0.5; font-size: smaller;'><span onClick=\"window.open('http://www.teitok.org/index.php', 'teitok');\">Powered by <span style='font-family: Courier;'>&lt;TEI:TOK&gt;</span></span><br><span onClick=\"window.open('http://www.teitok.org/index.php?action=credits', 'teitok');\">Maarten Janssen, 2014-</a></p>";
	};
	
	// Load smarty content
	if ( !isset($pagetitle) ) $pagetitle = @$pagetitles[$action] or $pagetitle = $settings['defaults']['title']['display'];
	$smarty->assign("title", $pagetitle);
	$smarty->assign("header", $pagetitle);
	$smarty->assign("menu", $menu);
	$smarty->assign("maintext", $maintext);

	// if more smarty variables were defined, load them
	if ( isset($moresmarty) )
	foreach ( $moresmarty as $key => $val ) {
		if ( $seti18n[$key] ) $val = i18n($val); 
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