a<?php
	// Script to login to TEITOK
	// (c) Maarten Janssen, 2015

	# Read the userlist 
	$ufile = file_get_contents ("Resources/userlist.xml");
	$uxml = simplexml_load_string($ufile);
	
	if ( $sharedfolder && file_exists("$sharedfolder/Resources/userlist.xml") ) {
		$sufile = file_get_contents ("$sharedfolder/Resources/userlist.xml");
		$suxml = simplexml_load_string($sufile);
	};

	$shareduser = false;
	if ( $_POST["login"] ) {
	
		# Lookup the data for this user in the STAFF database - local or shared when defined
		if ( $uxml ) $result = $uxml->xpath("//user[@email='{$_POST['login']}']"); 
		if ( $result ) {
			$xrec = $result[0]; 
			$record['email'] = $xrec['email'].''; 
			$record['short'] = $xrec['short'].''; 
			$record['permissions'] = $xrec['permissions'].''; 
			$record['group'] = $xrec['group'].''; 
			$record['fullname'] = $xrec.''; 
			if ( $sufile == $ufile ) {
				$record['projects'] = $xrec['projects'].''; 
			};
		} else if ( $suxml ) {
			$result = $suxml->xpath("//user[@email='{$_POST['login']}']"); 
			$xrec = $result[0];
			$shareduser = true;
			$record['shared'] = 1;
			$record['email'] = $xrec['email'].''; 
			$record['short'] = $xrec['short'].''; 
			$record['fullname'] = $xrec.''; 
			if ( $xrec['projects'] == "all" ) {
				$record['permissions'] = $xrec['permissions'].''; 
				$record['group'] = $xrec['group'].''; 
				$record['projects'] = $xrec['projects'].''; 
			} else if ( $xrec ) { 
				$pxrec = current($xrec->xpath("./project[@key='$foldername']"));
				if ( $pxrec ) {
					$record['permissions'] = $pxrec['permissions'].''; 
					$record['group'] = $pxrec['group'].''; 
				} else {
					$record['permissions'] = "none";
				};
			};
		};
			
		## Check whether the password is correct
		if ( password_verify($_POST['password'], $xrec['password']) )  { 
			if ( $record['permissions'] != "none"  ){
				if ( $shareduser && $record['projects'] == "all" ) {
					$_SESSION[$gsessionvar] = $record; 
				};
				$_SESSION[$sessionvar] = $record; 
				
				$user = $_SESSION[$sessionvar]; 
				$username = $user['email']; 
				$userid = $username;
				
				actionlog ( "user {$_POST['login']}" );
				
				// Check if this is an admin-provided password that needs to be modified
				if ( $xrec['tochange'] ) {
					header("location:index.php?action=user&act=pwdchange&forced=1");
					exit;
				};
				
				// See if we have any stored queries to load
				$useridtxt = $record['short'];
				if ( file_exists("Users/cql_$useridtxt.xml") ) {
					$xmlq = simplexml_load_file("Users/cql_$useridtxt.xml");
					foreach ( $xmlq->xpath("//query") as $sq ) {
						$sqarray['cql'] = $sq['cql']."";
						$sqarray['name'] = $sq['name']."";
						$sqarray['display'] = $sq['display']."";
						$_SESSION['myqueries'][urlencode($sq['cql'])] = $sqarray;
					};
				};
						
				// If we login as admin and have not done set-up properly, go to checksettings		
				if ( $user['permissions'] == "admin" && $settings['defaults']['checkfolder'] && $foldername != $settings['defaults']['base']['foldername'] && $action != "admin" && $action != "adminsettings" && $action != "error"  && !$debug ) {
					print "<script language=Javasript>top.location='index.php?action=admin&act=checksettings';</script>";
					exit;
				};
						
				// Now - reload 
				if ( $_GET['goon'] ) $newurl = "top.location='{$_GET['goon']}';";
				else if ( $_GET['action'] == "login" ) $newurl = "top.location='?action=admin';";
				else $newurl = "top.location='{$_SERVER['HTTP_REFERER']}';";
				if ( $action != "api" ) {
					print "<script language=javascript>$newurl</script>You have been logged in. This page will now reload. If it does not, please click 
						<a href='$newurl'>here</a>.";
					exit();
				};
			} else {
				fatal("Your login to this corpus has been deactivated. If you need to work on this corpus, please contact the corpus administrator to reactivate your account.");
				actionlog ( "permissions error: {$_POST['login']}" );
			};
		} else if ( $xrec && !$xrec['enc'] ) {
			
			fatal("This is an old style, insecure password; for security, this type of login has been disabled");
			messagelog ( "non-encoded password: {$_POST['login']} /  {$_POST['password']}" );

		} else {
		
			$maintext .= "<h1>Login Failed</h1><p>The username and password you provided do not match."; 
			messagelog ( "password error: {$_POST['login']} /  {$_POST['password']}" );

		};
		
		$action = $_POST['goon'];

	} else if ( $action == "logout"  || $act == "exit" ) { 
		$username = ""; $userid = "";
		$_SESSION[$sessionvar] = ""; 
		unset($_SESSION);
		session_destroy(); 
			print "<script language=javascript>top.location='?action=home';</script>You have been logged out. This page will now reload. If it does not, please click 
				<a href='$newurl'>here</a>.";
			exit();
	};
	if ( $_SESSION['extid'] ) {
		$userid = $username;
	};

	## When we are not logged in at this point, show the login
	if ( $user['email'] == "" ) { // && $action != "login" 
		$action = "login";
		$maintext .= "<h1>{%Login}</h1>";
		$maintext .= "<form action=\"?action=login\" method=post>
				<table><tr><td>{%Email}:<td><input name=login>
				<tr><td>{%Password}:<td><input type=password name=password>
				</table>
				<input type=hidden name=goon value={$_GET['action']}>
				<input type=submit value={%Login}></form>";
		
		$browser = get_browser(null, true);
		if ( strpos($_SERVER["HTTP_USER_AGENT"], "Explorer") !== false ) {
			$maintext  .= "<p style='color: red'>Corpus administration in Explorer is not supported; 
				please use Chrome, Firefox, or Edge</p>";
		};
				
		if ( !$_SESSION['extid'] ) {
			if ( $settings['permissions']['orcid'] && $settings['permissions']['orcid']['public'] && $settings['permissions']['orcid']['private'] ) {
				$maintext .= "<p>{%Visitor login}:  <a href='index.php?action=orcid'><img src=\"https://orcid.org/sites/default/files/images/orcid_16x16.png\" width=\"16\" height=\"16\" hspace=\"4\" /> ORCID</a>";
			};
		};		
		
		if ( $debug ) $maintext .= "<p style='color: #999999'>You are using: {$_SERVER['HTTP_USER_AGENT']}";
				
	} else if ( $_GET['action'] == "login" ) {
			print "<script language=javascript>top.location='?action=user';</script>Already logged in. This page will now reload. If it does not, please click 
				<a href='index.php?action=user'>here</a>.";
			exit();

	};

?>
