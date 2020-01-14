<?php
	print "<div style='color: black;'>";

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

	$thisdir = dirname(__FILE__);

	// Set session var before printing anything
	$_SESSION['check']['two'] = "also";

	print "<div style='color: black;'>";
	// Check whether Smarty exist

	# Look for Smarty in some standard locations
	if ( getenv("SMARTY_DIR") ) {
		$smarty =  getenv("SMARTY_DIR");
		if ( !file_exists($smarty.'/Smarty.class.php') ) {
			print "<p class=wrong> You have defined your SMARTY_DIR ($smarty), but it does not contains the Smarty.class.php file, please revise";
			$critical = 1;
		};
	} else if ( file_exists('/usr/local/share/smarty/Smarty.class.php') )
		$smarty = '/usr/local/share/smarty/';
	else if ( file_exists('/usr/local/lib/smarty/Smarty.class.php') )
		$smarty = '/usr/local/lib/smarty/';
	else if ( file_exists('/usr/local/share/smarty/libs/Smarty.class.php') )
		$smarty = '/usr/local/share/smarty/libs/';
	else if ( file_exists('/usr/local/lib/smarty/libs/Smarty.class.php') )
		$smarty = '/usr/local/lib/smarty/libs/';
	if ( !$smarty ) {
		$smartypath = str_replace("Smarty.class.php", "", file_locate('Smarty.class.php'));
		if ( $smartypath && $smartypath != "." ) {
			# TODO: Write this to index-off.php
			print "<p class=warn>Smarty seems to be installed, but not in a location where TEITOK expects it.
				Please set the SMARTY_DIR variable to $smartypath - in either httpd.conf, .htaccess, or index.php.";
				$htaccess .= "SetEnv SMARTY_DIR $smarty\n";
		} else {
			print "<p class=wrong> Smarty engine not installed or not found, which is required by TEITOK.
				Please install <a href='http://www.smarty.net/'>Smarty</a>, which can also be done using <a href='https://github.com/smarty-php/smarty'>GitHub</a>.
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
	$ttroot = getenv("TT_ROOT") or $ttroot = "..";
		$teitokpath = str_replace("/common/Sources/tttags.php", "", file_locate("common/Sources/tttags.php"));
	if ( !file_exists("$ttroot/common/Sources/tttags.php") ) {
		$teitokpath = str_replace("/common/Sources/tttags.php", "", file_locate("common/Sources/tttags.php"));
		if ( $teitokpath ) {
			# TODO: Write this to index-off.php
			print "<p class=warn>TEITOK seems to be installed, but not in a location where it can be found by default.
				Please change the \$ttroot definition in index-off.php to <b>$teitokpath</b>, or set the environment
				variable TT_ROOT to that value, for instance in the .htaccess file in the root of the TEITOK project(s)";
			$htaccess .= "SetEnv TT_ROOT $teitokpath\n";
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
	
	if ( getenv("TT_SHARED") ) {
		$sharedfolder = getenv("TT_SHARED");
		if ( !is_dir($sharedfolder) ) {
			print "<p class=wrong>The shared folder you have defined ($sharedfolder) does not exist</p>";
			$phperror = 1;
		} else {
			print "<p class=right>Your shared folder: $sharedfolder</p>";
			$sharedsettings = xmlflatten(simplexml_load_string(file_get_contents("$sharedfolder/Resources/settings.xml")));
			$jsurl = $sharedsettings['defaults']['base']['javascript'];
			if ( $jsurl )  {
				print "
					<script language=Javascript src='$jsurl/simtoks.js'></script>
					<p class=wrong id=nojs>This warning should disappear - if it does not your, your shared Javascript folder ($jsurl) does not exist, or is not accessible</div>
					<script language=Javascript>
						console.log('If JS is not loaded, highlight will not exist, and the following JS call will throw an error');
						highlight('nojs', '#ffffee');
						document.getElementById('nojs').style.display = 'none';
					</script>
					";
			};
		};
	} else if ( is_dir("../shared") ) {
		print "<p>You have a shared folder (../shared), but have not defined this system-wide as your folder for shared settings. You can opt
			to define it folder-based, or you can add an environment variable TT_SHARED pointing to your shared folder. The easiest way to
			do that is to add this to the .htaccess file in enclosing Apache folder: <code>SetEnv TT_SHARED ../shared</code>";
			$htaccess .= "SetEnv TT_SHARED $thisdir/shared\n";
	} else {
		print "<p>You have not defined a folder for shared settings; if you wish to define some server-wide settings for TEITOK, for instance
			to indicate the location of the Javascript files, you can for instance do that by creating a folder <i>shared</i> to the
			enclosing folder, and point to it in the .htaccess file in enclosing Apache folder: <code>SetEnv TT_SHARED ../shared</code>";
	};


	// Check whether SESSION variables work
	if ( $_SESSION['check']['two'] != "also" ) {
		print "<p class=wrong> If this message remains after reload, SESSION variables are not stored, and you will not be able to log in.";
		$critical = 1; 
	} else {
		print "<p class=right> Session variables working properly<p>";
	};

	if ( $htaccess ) {
		print "<p class=warn>Recommended additions to <tt>".str_replace('/check', '', $thisdir)."/.htaccess</tt>: 
<pre>
$htaccess
</pre></p>";
	};

	if ( !$critical ) {
		print "<hr>Your configuration is workable. To continue with the project, choose one of the default projects from the 
			Git (Learner Corpus, Historic, or Oral corpus) and copy it (with the name of your project) next to this check folder.
			Make sure the templates_c folder in that project is writable for Apache, otherwise Smarty will fail. 
			After that, delete this folder, and login to your new project asap to change the default password.";
	};
	print "</div></div>";

	function xmlflatten ( $xml, $int = 0 ) {
		global $maintext; 
		if ( !$xml ) return "";
	
		if ( $xml->attributes() ) 
		foreach ( $xml->attributes() as $atn => $atv ) {
			$flatxml[$atn] = $atv."";
		};

		if ( $int && $xml.""  != "" ) { $flatxml['(text)'] = $xml.""; };

		foreach ( $xml->children() as $node ) {
			$chn = "".$node->getName();
			if ( $node['id'] ) $key = $node['id']."";
			else if ( $chn == "item" ) {
				if ( $node['key'] ) $key = $node['key']."";
				else { $icnt++; $key = $icnt; };
			} else $key = $chn;
			
			$flatxml[$key] = xmlflatten($node);
		};
	
		return $flatxml;
	};

?>
