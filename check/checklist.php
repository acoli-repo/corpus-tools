<?php

	function file_locate( $file ) {
		if ( file_exists($file) ) return $file;
		else {
			# Use glob
			$tmp = glob($file);
			if ( $tmp ) return current($tmp);

			# Use locate
			$cmd = "ls -td \$(locate '$file')";
			$tmp = explode("\n", shell_exec ( $cmd ));
			if ( $tmp ) return current($tmp);
		};
		return false;
	};

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
		if ( $smartypath ) {
			# TODO: Write this to index-off.php
			print "<p class=warn>Smarty seems to be installed, but not in a location where TEITOK expects it.
			Please change the SMARTY_DIR definition in index.php to <b>$smartypath</b> and remove the slashes in front of the line.";
		} else {
			print "<p class=wrong> Smarty engine not installed or not found, which is required by TEITOK.
				Please install <a href='http://www.smarty.net/'>Smarty</a>.
				 ";
			$critical = 1;
		};
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

	// Check whether TEITOK (main.php) in common exists in the expected location
	if ( !file_exists("../common/Sources/tttags.php") ) {
		$teitokpath = str_replace("/common/Sources/tttags.php", "", file_locate("common/Sources/tttags.php"));
		if ( $teitokpath ) {
			# TODO: Write this to index-off.php
			print "<p class=warn>TEITOK seems to be installed, but not in a location where it can be found by default.
				Please change the \$ttroot definition in index-off.php to <b>$teitokpath</b>";
		} else {
			print "<p class=wrong> The common TEITOK files seem not to be installed or are not readable for Apache.
				Please make the TEITOK common files available.";
			$critical = 1;
		};
	} else {
		print "<p class=right> Common TEITOK files found";
	};


	if ( $_GET['project'] ) {
		# If asked to do so, check folder settings on a project
		$projectroot = "../{$_GET['project']}";
		
		// Check whether relevant folders is writable
		if ( !is_dir($projectroot) ) {
			print "<p class=wrong> There is no project $projectroot";
			$foldererrors = 1;
		};
		
		if ( !is_writable("$projectroot/Resources") ) {
			print "<p class=wrong> The folder Resources/ should be writable for Apache or TEITOK will not be able to modify preferences";
			$foldererrors = 1;
		};
		if ( !is_writable("$projectroot/Resources/userlist.xml") ) {
			print "<p class=wrong> The userlist.xml should be writable for Apache or TEITOK will not be able to change users";
			$foldererrors = 1;
		};
		if ( !is_writable("$projectroot/templates_c") ) {
			print "<p class=wrong> The folder templates_c should be writable for Apache or Smarty will not work";
			$foldererrors = 1;
			$critical = 1;
		};
		if ( !$foldererrors ) {
			print "<p class=right> All crucial files/folders for project {$_GET['project']} are writable";
		};
	};
	
	// Check whether C++ modules are installed
	$sep = "";
	$cpps = array ('tt-cwb-encode', 'tt-cwb-xidx');
	foreach ( $cpps as $cpp ) {
		$cmd = "which $cpp";
		if ( file_exists("/usr/local/bin/$cpp") ) {
		} else {
			$cpperrors .= $sep."$cpp.cpp"; $sep = ", ";
		};
	};
	if ( !$cpperrors ) {
		print "<p class=right> C++ modules compiled.";
	} else {
		print "<p class=warn> The following c++ programs were not found, they are recommended for use with CQP : $cpperrors .";
		$newc = 1; # This should check whether the c++ version is new enough... which voids the need for boost
		if ( $newc || file_exists('/usr/local/include/boost/version.hpp')  ) {
			# Proper installation
		} else {
			# Check whether boost is (properly) installed
			$boostpath = str_replace("Smarty.class.php", "", file_locate('boost/version.hpp'));
			if ( $boostpath ) {
				print "<p class=warn> These C++ programs rely on boost, which seems not to be linked properly, but installed under $boostpath";
			} else {
				print "<p class=warn> These C++ programs rely on boost, which does not seem to be installed";
			};
		};
	};

	// Check whether XML::LibXML is installed
	$cmd = "perl -e 'use XML::LibXML; use HTML::Entities; print \"works\";'";
	$test = shell_exec($cmd);
	if ( $test != "works" ) {
		print "<p class=warn> For most external scripts, TEITOK requires the Perl modules XML::LibXML and HTML::Entities to be installed, which it is not.";
		$perlerror = 1;
	};
	if ( !$perlerror ) {
		print "<p class=right> Required Perl modules working.";
	};

	if ( !function_exists('simplexml_load_string') ) {
		print "<p class=wrong>XML is not installed in PHP, please install php-xml";
		$critical = 1; $phperror = 1;
	};

	// Check whether SESSION variables work
	if ( $_SESSION['check']["2"] != "also" ) {
		print "<p class=wrong> If this message remains after reload, SESSION variables are not stored, and you will not be able to log in.";
		$critical = 1; 
	} else {
		print "<p class=right> Session variables working properly";
	};

	if ( !$critical ) {
		print "<hr>Your configuration is workable. To continue with the project, choose one of the default projects from the 
			Git (Learner Corpus, Historic, or Oral corpus) and copy it (with the name of your project) next to this check folder.
			Make sure the templates_c folder in that project is writable for Apache, otherwise Smarty will fail. 
			After that, delete this folder, and login to your new project asap to change the default password.";
	};
	print "</div>";

?>
