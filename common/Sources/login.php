<?php
	// Script to login to TEITOK
	// (c) Maarten Janssen, 2015

	# Read the userlist 
	$ufile = file_get_contents ("Resources/userlist.xml");
	$uxml = simplexml_load_string($ufile);
	// $sessionvar = "teitok-$foldername";

	if (!function_exists('password_hash')) {
		function password_hash($pwd, $salt) {
			return crypt($password);
		}
		function password_verify($pwd1, $pwd2) )  { 
			if ( $pwd1 == crypt($pwd2) ) return true;
			return false;
		};		
	}	

	if ( $_POST["login"] ) {
		# Lookup the data for this user in the STAFF database
		$result = $uxml->xpath("//user[@email='{$_POST['login']}']"); 
		$xrec = $result[0]; 
		$record['email'] = $xrec['email'].''; 
		$record['short'] = $xrec['short'].''; 
		$record['permissions'] = $xrec['permissions'].''; 
		$record['group'] = $xrec['group'].''; 
		$record['fullname'] = $xrec.''; 

		// This is for a smooth transition to a more secure encryption method
		if ( !$xrec['enc'] && !$xrec['tochange'] ) {
			if ( $_POST['password'] == $xrec['password']  || $xrec['password'] == crypt("teiteitokencryptor", $_POST['password'] ) ) {
				# Password correct, but now save it as a more secure password
				 $pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
				 $xrec['password'] = $pwd;
				 $xrec['enc'] = "1";
				file_put_contents("Resources/userlist.xml", $uxml->asXML());
			} else {
				fatal ("wrong password");
			};
		};
	
		## Check whether the password is correct
		if ( password_verify($_POST['password'], $xrec['password']) )  { 
			if ( $record['permissions'] != "none"  ){
				$_SESSION[$sessionvar] = $record; 
				
				$user = $_SESSION[$sessionvar]; 
				$username = $user['email']; 
				
				actionlog ( "user {$_POST['login']}" );
				
				// Check if this is not a admin-provided password that needs to be modified
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
						
				// Now - reload 
				if ( $_GET['goon'] ) $newurl = "top.location='{$_GET['goon']}';";
				else if ( $_GET['action'] == "login" ) $newurl = "top.location='?action=admin';";
				else $newurl = "top.location='{$_SERVER['HTTP_REFERER']}';";
				print "<script language=javascript>$newurl</script>You have been logged in. This page will now reload. If it does not, please click 
					<a href='$newurl'>here</a>.";
				exit();
				
			} else {
				$maintext .= "You do not have permission to edit this corpus."; exit();
				actionlog ( "permissions error: {$_POST['login']}" );
			};
		} else {
			$maintext .= "<h1>Login Failed</h1><p>The username and password you provided do not match."; 
			messagelog ( "password error: {$_POST['login']} /  {$_POST['password']}" );

		};
		
		$action = $_POST['goon'];

	} else if ( $action == "logout"  || $act == "exit" ) { 
		$username = ""; 
		$_SESSION[$sessionvar] = ""; 
		unset($_SESSION);
		session_destroy(); 
			print "<script language=javascript>top.location='?action=home';</script>You have been logged out. This page will now reload. If it does not, please click 
				<a href='$newurl'>here</a>.";
			exit();
	};
		
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
		
		print_r($browser);
		
		if ( !$_SESSION['extid'] ) {
			if ( $settings['permissions']['orcid'] && $settings['permissions']['orcid']['public'] && $settings['permissions']['orcid']['private'] ) {
				$maintext .= "<p>{%Visitor login}:  <a href='index.php?action=orcid'><img src=\"https://orcid.org/sites/default/files/images/orcid_16x16.png\" width=\"16\" height=\"16\" hspace=\"4\" /> ORCID</a>";
			};
		};		
		
		if ( $debug ) $maintext .= "<p style='color: #999999'>You are using: {$_SERVER['HTTP_USER_AGENT']}";
				
	} # else { $maintext .= "logged in as $username"; };

?>
