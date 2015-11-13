<?php
	// Check the configuration of the server, the project, and TEITOK
	// This file should be deleted when finished
	session_start();
	
	print "<html>
		<head>
			<title>TEITOK Configuration Check</title>
			<meta charset='utf-8'>
			<link href='Resources/htmlstyles.css' media='all' rel='stylesheet' />
		</head>
		<body>
		<style>
			.wrong { color: #aa2000; } .wrong::before { content:'✘ ' }
			.warn { color: #aa8800; } .warn::before { content:'✣ ' }
			.right { color: #209900; } .right::before { content:'✔ ' }
		</style>
		<h2>TEITOK Configuration Check</h2>
		
		<p>Welcome to your new TEITOK project. Below is a checklist to make sure all really required things are in place; the rest can be done from within TEITOK.
		<hr>";
		
	
	// Check whether Smarty exist
	if ( !$smarty ) {
		# Look for Smarty in some standard locations if not defined in a non-standard location
		if ( file_exists('/usr/local/share/smarty/Smarty.class.php') ) 
			$smarty = '/usr/local/share/smarty/';
		else if ( file_exists('/usr/local/lib/smarty/Smarty.class.php') ) 
			$smarty = '/usr/local/lib/smarty/';
		else if ( file_exists('/usr/local/share/smarty/libs/Smarty.class.php') ) 
			$smarty = '/usr/local/share/smarty/libs/';
		else if ( file_exists('/usr/local/lib/smarty/libs/Smarty.class.php') ) 
			$smarty = '/usr/local/lib/smarty/libs/';
	};
	if ( !$smarty ) {
		print "<p class=wrong> Smarty engine not installed or not found. Install Smarty, or indicate 
			the location of the Smarty directory in index.php. It also ";
		$critical = 1;
	} else {
		print "<p class=right> Smarty engine found: $smarty"; 
	};

	// Check for CQP
	$cqpcheck = shell_exec("/usr/local/bin/cqp -v");
	if ( !$cqpcheck ) $cqpcheck = shell_exec("cqp -v"); // if not in /usr/local/bin - try just running it if server allows
	if ( !$cqpcheck ) {
		print "<p class=warn> CQP not installed or not found. Install CQP, or search will not be available";
	} else {
		preg_match ("/version:\s*(.*)/i", $cqpcheck, $matches);
		print "<p class=right> CQP found, version: {$matches[1]}"; 
	};

	// Check whether main.php in common exists in the expected location
	if ( !file_exists("../common/Sources/main.php") ) {
		print "<p class=wrong> The common TEITOK files are missing or not in the folder directly above the project";
		$critical = 1;
	} else {
		print "<p class=right> Common TEITOK files found"; 
	};

	// Check whether relevant folders is writable
	if ( !is_writable("Resources") ) {
		print "<p class=wrong> The folder Resources/ should be writable for Apache or TEITOK will not be able to modify preferences";
		$foldererror = 1;
	};
	if ( !is_writable("Resources/userlist.xml") ) {
		print "<p class=wrong> The userlist.xml should be writable for Apache or TEITOK will not be able to change users";
		$foldererror = 1;
	};
	if ( !$foldererrors ) {
		print "<p class=right> All crucial files/folders are writable"; 
	};
	
	// Check whether SESSION variables work (forget COOKIE - SESSION works with cookies, so that should be implied)
	if ( $_SESSION['check']["2"] != "also" ) {
		print "<p class=wrong> If this message remains after reload, SESSION variables are not stored, and you will not be able to log in.";
		$critical = 1;
	} else {
		print "<p class=right> Session variables working properly"; 
	};
	$_SESSION['check'] = array ( "1" => "check", "2" => "also" );

	if ( !$critical ) {
		print "<hr>Your configuration is workable. To continue with the project, move index-off.php to index.php (effectively deleting this check).
		<br>Your login will temporarily be nobody@nowhere.com / defaultpassword - which you should change asap";
	};

?>