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
		mkdir($projectfolder); 
		if ( !is_dir($projectfolder) ) {
			fatal("Failed to create $projectfolder");
		};
		
		# Copy the index.php into the folder
		copy("$ttroot/projects/default-shared/index.php", $projectfolder);
		if ( !file_exists("$projectfolder/index.php") ) shell_exec("cp $ttroot/projects/default-shared/index.php $projectfolder");
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

	
	} else if ( $act == "list" ) {
	
		$maintext .= "<h1>Local Project List</h1><table>";
		$guessroot = $settings['defaults']['apacheroot'] or $guessroot = $settings['defaults']['base']['apacheroot'] or $guessroot = preg_replace("/\/[^\/]+\/index\.php.*/", "", $_SERVER['SCRIPT_FILENAME']);
		
		$tmp = scandir($guessroot);
		$rootbase = $settings['defaults']['base']['httproot'] or $rootbase = str_replace($_SERVER['DOCUMENT_ROOT'], "", $guessroot);
		// $maintext .= "<p>$guessroot - $rootbase</p>";
		foreach ( $tmp as $fl ) {
			if ( is_dir("$guessroot/$fl") && substr($fl, 0, 1) != "." && !is_link("$guessroot/$fl") && !is_link("$guessroot/$fl/Resources") && file_exists("$guessroot/$fl/Resources/settings.xml") ) {
				$xtmp = simplexml_load_file("$guessroot/$fl/Resources/settings.xml");
				$prtit = current($xtmp->xpath("//defaults/title"));
				if ( $prtit['display'] ) $maintext .= "<tr><td><a href='$rootbase/$fl/index.php'>$fl</a><td>{$prtit['display']}";
			};
		};
		
		$maintext .= "</table>";
	
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
		$cqpcheck = shell_exec("$bindir/cqp -v");
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
			if ( file_exists("$bindir/$cpp") ) {
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

	} else if ( $act == "usercopy" ) {

		$guessroot = $settings['defaults']['apacheroot'] or $guessroot = preg_replace("/\/[^\/]+\/index\.php.*/", "", $_SERVER['SCRIPT_FILENAME']);
		if ( $_POST['from'] ) {
			list ( $ff, $email ) = explode(":", $_POST['from']);
			$tf = $_POST['to'];
			$ffile = "$guessroot/$ff/Resources/userlist.xml";
			$tfile = "$guessroot/$tf/Resources/userlist.xml";
			if ( $tf == "" || !is_dir("$guessroot/$tf") ) fatal("Not a proper project folder: $tf");
			$flist = simplexml_load_file($ffile);
			if ( !$flist ) fatal("Failed to read userlist of $ff");
			if ( file_exists($tfile) )
				$tlist = simplexml_load_file($tfile);
			else 
				$tlist = simplexml_load_string("<userlist/>");
			$fromrec = current($flist->xpath("//user[@email=\"$email\"]"));
			print showxml($fromrec);
			if ( !$fromrec ) fatal("No such user to copy: $email ($ff)");
			$torec = current($tlist->xpath("//user[@email=\"$email\"]"));
			if ( $torec ) fatal("User already exists: $email ($tf)");
			$toroot = current($tlist->xpath("/userlist"));
			if ( $torec ) fatal("Incorrect userlist ($tf)");
			$tmp = dom_import_simplexml($toroot);
			$tmp2 = dom_import_simplexml($fromrec);
			$tmp2  = $tmp->ownerDocument->importNode($tmp2, TRUE);
			$tmp->appendchild($tmp2);
			# Make a backup
			$date = date("Ymd"); 
			$buname = preg_replace ( "/\.xml/", "-$date.xml", $filename );
			$buname = preg_replace ( "/.*\//", "", $buname );
			if ( !file_exists("backups") ) { mkdir("backups"); };
			if ( !file_exists("backups/$buname") ) {
				copy ( "$tofile", "backups/$buname");
			};
			file_put_contents($tfile, $tlist->asXML());
 			print "<p>Record copied for $email to $tfile - reloading";
 			print "<script>top.location='index.php?action=$action'</script>";
			exit;
		} else {
			$maintext .= "<h1>Copy User</h1>
				<p>Copy user priviledges from one project to another</p>
				";
		
			$tmp = scandir($guessroot);
			$rootbase = str_replace($_SERVER['DOCUMENT_ROOT'], "", $guessroot);
			foreach ( $tmp as $fl ) {
				if ( is_dir("$guessroot/$fl") && substr($fl, 0, 1) != "." && file_exists("$guessroot/$fl/Resources/settings.xml") ) {
					$xtmp = simplexml_load_file("$guessroot/$fl/Resources/settings.xml");
					if ( !$xtmp ) continue;
					$tmp3 = current($xtmp->xpath("//defaults/title"));
					$prtit = $tmp3['display'];
					if ( $prtit != "" ) {	
						$propts .= "<option value='$fl'>$prtit</option>";
						$xtmp2 = simplexml_load_file("$guessroot/$fl/Resources/userlist.xml");
						if ( $xtmp2 )
						foreach ( $xtmp2->xpath("//user") as $urec ) {
							if ( !$done[$urec['email'].""] || $_GET['all'] )
								$userlist["$fl:".$urec['email']] = $urec." ({$prtit})";
							$done[$urec['email'].""] = 1;
						};
					};
				};
			};		
			asort($userlist);
			foreach ( $userlist as $key => $val ) $uopts .= "<option value='$key'>$val</a>";
			
			$maintext .= "
				<form action='index.php?action=$action&act=$act' method=post>
				<table>
				<tr><th>Choose user:<td><select name=from>$uopts</select>
				<tr><th>Copy to: <td><select name=to>$propts</select>
				</table>
				<p><input type=submit value=Copy>
				</form>";
		};

				
	} else {
		$maintext .= "<h1>Server-Wide Administration</h1>
		
			<ul>";
			
		$maintext .= "<li><a href='index.php?action=$action&act=configcheck'>Check server configuration</a>";
		$maintext .= "<li><a href='index.php?action=$action&act=list'>List local projects</a>";
		$maintext .= "<li><a href='index.php?action=$action&act=usercopy'>Copy user data</a>";
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