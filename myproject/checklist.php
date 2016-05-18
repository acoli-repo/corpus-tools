<?php

	print "<div style='color: black;'>";
	// Check whether Smarty exist

	# Look for Smarty in some standard locations 
	if ( file_exists('/usr/local/share/smarty/Smarty.class.php') ) 
		$smarty = '/usr/local/share/smarty/';
	else if ( file_exists('/usr/local/lib/smarty/Smarty.class.php') ) 
		$smarty = '/usr/local/lib/smarty/';
	else if ( file_exists('/usr/local/share/smarty/libs/Smarty.class.php') ) 
		$smarty = '/usr/local/share/smarty/libs/';
	else if ( file_exists('/usr/local/lib/smarty/libs/Smarty.class.php') ) 
		$smarty = '/usr/local/lib/smarty/libs/';
	if ( !$smarty ) {
		$smartypath = str_replace("Smarty.class.php", "", file_locate('Smarty.class.php'));
		if ( $smartypath ) print "Smarty seems to be installed, but not in a location where TEITOK expects it. Please change the SMARTY_DIR definition in index.php to <b>$smartypath</b> and remove the slashes in front of the line.";
		print "<p class=wrong> Smarty engine not installed or not found, which is required by TEITOK.
			Please install <a href='http://www.smarty.net/'>Smarty</a>.
			 ";
		$critical = 1;
	} else {
		print "<p class=right> Smarty engine found: $smarty"; 
	};

	// Check for CQP
	$cqpcheck = shell_exec("/usr/local/bin/cqp -v");
	if ( !$cqpcheck ) $cqpcheck = shell_exec("cqp -v"); // if not in /usr/local/bin - try just running it if server allows
	if ( !$cqpcheck ) {
		print "<p class=warn> CQP not installed or not found. Please install <a href='http://cwb.sourceforge.net/'>CQP</a>, 
				unless you do not require any search functions on your corpus.";
	} else {
		preg_match ("/version:\s*(.*)/i", $cqpcheck, $matches);
		print "<p class=right> CQP found, version: {$matches[1]}"; 
	};

	// Check whether main.php in common exists in the expected location
	if ( !file_exists("../common/Sources/main.php") ) {
		print "<p class=wrong> The common TEITOK files are missing or not in the folder directly above the project.
			Please make sure that your project folder is next to the common folder of the TEITOK system.";
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
	
	// Check whether XML::LibXML is installed
	$cmd = "perl -e 'use XML::LibXML; print \"works\";'";
	$test = shell_exec($cmd);
	if ( $test != "works" ) {
		print "<p class=warn> For most external scripts, TEITOK requires the Perl module XML::LibXML to be installed, which it is not.";
		$perlerror = 1;
	};
	if ( !$perlerror ) {
		print "<p class=right> Required Perl modules working.";
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
		Also, remove the file checklist.php.
		<br>Your login will temporarily be nobody@nowhere.com / defaultpassword - which you should change asap";
	};
	print "</div>";
	
?>