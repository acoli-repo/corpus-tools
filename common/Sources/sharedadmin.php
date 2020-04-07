<?php 

	if ( $user['permissions'] != "admin" ) { fatal("Only for superusers"); };
	if ( $user['projects'] != "all" ) { print_r($user); exit; fatal("Only for global admin users"); };

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
			# fatal("Folder $rootfolder exists - refusing to advance");
		};
		if ( !is_writable($rootfolder) ) {
			fatal("Apache cannot write to $rootfolder - cannot create from within here");
		};
		shell_exec("mkdir $projectfolder"); 
		if ( !is_dir($projectfolder) ) {
			fatal("Failed to create $projectfolder");
		};
		
		# Copy the index.php into the folder
		shell_exec("cp $ttroot/projects/default-min/index.php $projectfolder");
		if ( !file_exists("$projectfolder/index.php") ) {
			fatal("Failed to copy index from $ttroot/projects/default-min/ to $projectfolder");
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

		$maintext .= "<h1>Create new project</h1>
			<form action='index.php?action=$action&act=createnew' method=post>
			<table>
			<tr><th>TEITOK root folder<td><input size=60 name='root' value='$guessroot'>
			<tr><th>Project folder name<td><input size=60 name='project'>
			<tr><th>Project title<td><input size=60 name='title'>
			<tr><th>Copy settings from<td><select name='settings'><option value='.'>Shared project</option><option value='$ttroot/projects/default-min/'>Minimal project</option>$optlist</select>
			</table>
			<p><input type=submit value='Create Project'>
			</form>";
	
	} else {
		$maintext .= "<h1>Server-Wide Administration</h1>
		
			<ul>";
			
		$maintext .= "<li><a href='index.php?action=$action&act=newproject'>Create new project</a>";
				
		$maintext .= "</ul>";
	};
	
?>