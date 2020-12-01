<?php 

	if ( $user['permissions'] != "admin" ) { fatal("Only for superusers"); };
	if ( $user['projects'] != "all" ) { fatal("Only for global admin users"); };

	$checkshared = preg_replace("/.*\/([^\/]+)\/?/", "\\1", getenv('TT_SHARED'));
	if ( $checkshared != $foldername ) { 
		fatal("This can only be run from the shared project folder");
	};
	
	if ( $act == "createnew" ) {
	
		# create a new project
		
		$rootfolder = $_POST['root'];
		$projectname = $_POST['project'];
		$projecttitle = $_POST['title'];
		$projectfolder = "$rootfolder/$projectname";
		
		# Create the folder
		if ( is_dir($projectfolder) ) {
			fatal("Folder $projectfolder exists - refusing to advance");
		};
		if ( !is_writable($rootfolder) ) {
			fatal("Apache cannot write to $rootfolder - cannot create from within here");
		};
		shell_exec("mkdir $projectfolder"); 
		if ( !is_dir($projectfolder) ) {
			fatal("Failed to create $projectfolder");
		};
		
		# Copy the index.php into the folder
		shell_exec("cp $ttroot/projects/default-shared/index.php $projectfolder");
		if ( !file_exists("$projectfolder/index.php") ) {
			fatal("Failed to copy index from $ttroot/projects/default-shared/ to $projectfolder");
		};

		if ( $settings['xmlreader']['corplist'] && $_POST['corplist'] ) {
			$tmp = file_get_contents("Resources/corplist.xml");
			if ( !$tmp ) $tmp = "<corplist></corplist>";
			$corpxml = simplexml_load_string($tmp);

			$new = $corpxml->addChild("corpus");
			$new['id'] = $projectfolder;
			foreach ( $_POST['corplist'] as $key => $val ) {
				print "<p>$key: $val";
				$new->addChild($key."", $val."");
			};
			$corpxml->addChild($new);
			# Write back
			file_put_contents("Resources/corplist.xml", $corpxml->asXML());
		}; 
		
		# Create the settings.xml
		shell_exec("mkdir $projectfolder/Resources"); 
		$setfile = $_POST['settings'];
		if ( $setfile == "." || !$setfile ) if ( file_exists("Resources/defaultsettings.xml") ) $setfile = "Resources/defaultsettings.xml"; else $setfile = "Resources/settings.xml";
		else $setfile = $setfile."Resources/settings.xml";
		$setxml = simplexml_load_file($setfile);
		if ( !$setxml ) fatal("Failed to load settings $setfile");
		
		$node = xpathnode($setxml, "/ttsettings/defaults/base");
		$node['foldername'] = $projectname;

		$node = xpathnode($setxml, "/ttsettings/defaults/title");
		$node['display'] = $projecttitle;
		
		$node = xpathnode($setxml, "/ttsettings/cqp");
		$corpname = strtoupper($projectname); $corpname = preg_replace("/[^A-Za-z0-9_]/g", "", $corpname);
		$node['corpus'] = "TT-$corpname";
		
		file_put_contents("$projectfolder/Resources/settings.xml", $setxml->asXML());

		# Create a home page
		shell_exec("mkdir $projectfolder/Pages"); 
		$homepage = "<h1>$projecttitle</h1>\n\n<p>New TEITOK project created automatically, please check in back later.";
		file_put_contents("$projectfolder/Pages/home.html", $homepage);
		
		$newurl = "../$projectname/index.php?action=login";
		print "<p>Project created, redirecting to probable project.
			<script language=Javascript>top.location='$newurl'</script>";
		exit;
	
	} else if ( $act == "newproject" ) {
	
		# create a new project
		if ( file_exists("Resources/corplist.xml") )  {
		} else {
			foreach ( glob("../*/Resources/settings.xml") as $val ) {
				$valkey = preg_replace("/\/Resources.*/", "/", $val);
				$valtxt = preg_replace("/.*\/([^\/]+)\/Resources.*/", "\\1", $val);
				$optlist .= "<option value='$valkey'>$valtxt</option>";
			};
		};
		$guessroot = $settings['defaults']['apacheroot'] or $guessroot = preg_replace("/\/[^\/]+\/index\.php.*/", "", $_SERVER['SCRIPT_FILENAME']);

		if ( $settings['xmlreader']['corplist'] && file_exists("Resources/corplist-entry.xml")) {
			$tmp = file_get_contents("Resources/corplist-entry.xml");
			$corpexml = simplexml_load_string($tmp);
			$corpusentry = "<p><table><tr><th colspan=2>Corpus List Entry";
			foreach ( $corpexml->children() as $child ) {
				$tn = $child->getName();
				$td = $child["display"] or $td = $child."";
				$corpusentry .= "<tr><th>$td<td><input name='corplist[$tn]' value='' size=70>";
			};
			$corpusentry .= "</table>";
		};


		$maintext .= "<h1>Create new project</h1>
			<form action='index.php?action=$action&act=createnew' method=post>
			<table>
			<tr><th>TEITOK root folder<td><input size=60 name='root' value='$guessroot'>
			<tr><th>Project folder name<td><input size=60 name='project'>
			<tr><th>Project title<td><input size=60 name='title'>
			<tr><th>Copy settings from<td><select name='settings'><option value='.'>Shared project</option><option value='$ttroot/projects/default-min/'>Minimal project</option>$optlist</select>
			</table>
			
			$corpusentry
			
			<p><input type=submit value='Create Project'>
			</form>
			
			<h2>Existing folders</h2>";
			
		if ($handle = opendir('..')) {
			$upfolder = preg_replace("/[^\/]+\/[^\/]+$/", "", $_SERVER['REQUEST_URI']);
			while (false !== ($entry = readdir($handle))) {
				if ( substr($entry,0,1) != "." ) {
					if ( file_exists("../$entry/index.php") ) $maintext .= "<p><a href='$upfolder$entry/index.php'>$entry</a>\n";
				}
			}
			closedir($handle);
		}

	} else if ( $act == "update" ) {
		
		// Self-update
		if ( $user['permissions'] != "admin" ) { fatal("Not allowed"); };
		if ( !is_writable($ttroot) ) { fatal("TEITOK cannot be updated from within the browser $gitfldr"); };
		
		$date = date("Ymd"); 
		check_folder("log");
		$cmd = "cd $ttroot; /usr/bin/git pull >  $sharedfolder/log/gitpull-$date  2>&1 ";
		shell_exec($cmd);
		$output = file_get_contents("log/gitpull-$date");
		
		$maintext .= "<h1>Updating the TEITOK system</h1>
			<p>TEITOK Git folder: $ttroot</p>
			<p>Update response:
			<pre>$output</pre>";
		
	} else if ( $act == "configcheck" ) {
	
		$maintext .= "<h1>Server Configuration Check</h1>
			<p>Below are some additional checks to see whether your server is set-up properly</p>
			<style>
	.wrong { color: #aa2000; } .wrong::before { content:'✘ ' }
	.warn { color: #aa8800; } .warn::before { content:'✣ ' }
	.right { color: #209900; } .right::before { content:'✔ ' }
	</style>
	";

		// Check for CQP
		$cqpcheck = shell_exec("/usr/local/bin/cqp -v");
		if ( !$cqpcheck ) $cqpcheck = shell_exec("cqp -v"); // if not in /usr/local/bin - try just running it if server allows
		if ( !$cqpcheck ) {
			$maintext .= "<p class=warn> CQP not installed or not found. Please install <a href='http://cwb.sourceforge.net/'>CQP</a>,
					unless you do not require any search functions on your corpus.";
		} else {
			preg_match ("/version:\s*(.*)/i", $cqpcheck, $matches);
			$maintext .= "<p class=right> CQP found, version: {$matches[1]}";
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
			$maintext .= "<p class=right> C++ modules compiled.";
		} else {
			$maintext .= "<p class=warn> The following c++ programs were not found, they are recommended for use with CQP : $cpperrors .";
			$newc = 1; # This should check whether the c++ version is new enough... which voids the need for boost
			if ( $newc || file_exists('/usr/local/include/boost/version.hpp')  ) {
				# Proper installation
			} else {
				# Check whether boost is (properly) installed
				$boostpath = str_replace("Smarty.class.php", "", file_locate('boost/version.hpp'));
				if ( $boostpath ) {
					$maintext .= "<p class=warn> These C++ programs rely on boost, which seems not to be linked properly, but installed under $boostpath";
				} else {
					$maintext .= "<p class=warn> These C++ programs rely on boost, which does not seem to be installed";
				};
			};
		};

		// Check whether XML::LibXML is installed
		$cmd = "perl -e 'use XML::LibXML; use HTML::Entities; print \"works\";'";
		$test = shell_exec($cmd);
		if ( $test != "works" ) {
			$maintext .= "<p class=warn> For most external scripts, TEITOK requires the Perl modules XML::LibXML and HTML::Entities to be installed, which it is not.";
			$perlerror = 1;
		};
		if ( !$perlerror ) {
			$maintext .= "<p class=right> Required Perl modules working.";
		};

		if ( !function_exists('simplexml_load_string') ) {
			$maintext .= "<p class=wrong>XML is not installed in PHP, please install php-xml";
			$critical = 1; $phperror = 1;
		};
		
		
		# Check if the Javascript files are accessible
		$maintext .= "<p id=js class=wrong> Javascript files are not accessible from $jsurl - please change
			<script language=Javascript>
				var img = new Image();
				img.onload = function () {
				   document.getElementById('js').style.display = 'none';
				}
				img.src = '$jsurl/load_img.gif';
			</script>";

				
	} else {
		$maintext .= "<h1>Server-Wide Administration</h1>
		
			<ul>";
			
		$maintext .= "<li><a href='index.php?action=$action&act=configcheck'>Check server configuration</a>";
		$maintext .= "<li><a href='index.php?action=$action&act=newproject'>Create new project</a>";

		# Display the TEITOK version
		if ( file_exists("$ttroot/common/Resources/version.xml") ) {
			$tmp = simplexml_load_file("$ttroot/common/Resources/version.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			$version = $tmp[0];
			$footer .= "<p style='font-size: small; color: #999999;'>TEITOK version: {$version['version']}, {$version['date']}";	

			$scopts['http']['timeout'] = 3; // Set short timeout here to avoid hanging
			if ( $settings['defaults']['base']['proxy'] ) $scopts['http']['proxy'] = $settings['defaults']['base']['proxy'];
			$ctx = stream_context_create($scopts);	
			$latesturl = "http://www.teitok.org/latest.php?url={$_SERVER['HTTP_HOST']}".preg_replace("/\/index\.php.*/", "", $_SERVER['REQUEST_URI'])."&version={$version['version']}";
			$tmpf = file_get_contents($latesturl, false, $ctx);
			$tmp = simplexml_load_string($tmpf, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			if ( $tmp ) {
				$tmp2 = $tmp->xpath("//info");
				$latest = $tmp2[0];
				if ( $latest['version']."" != $version['version']."" ) $footer .= " - Latest version: {$latest['version']}, {$latest['date']}" ;
				else  {
					$footer .= " (up-to-date)";
					$uptodate = 1;
				};
			};
			
			// TODO: Can we update via the GUI?
			if ( $user['permissions'] == "admin" && is_writable($ttroot) && !$uptodate ) {
				$maintext .= "<li> <a href='index.php?action=$action&act=update'>update TEITOK version to {$latest['version']}</a>";
			};
		};
				
		$maintext .= "</ul>$footer";
	};
	
?>