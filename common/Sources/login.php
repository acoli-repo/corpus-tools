<?php
	// Script to login to TEITOK
	// (c) Maarten Janssen, 2015

	# Read the userlist 
	$ufile = file_get_contents ("Resources/userlist.xml");
	$uxml = simplexml_load_string($ufile);
	$sessionvar = "teitok-$foldername";
	
	if ( $_POST["login"] ) {
		# Lookup the data for this user in the STAFF database
		$result = $uxml->xpath("//user[@email='{$_POST['login']}']"); 
		$xrec = $result[0]; 
		$record['email'] = $xrec['email'].''; 
		$record['short'] = $xrec['short'].''; 
		$record['permissions'] = $xrec['permissions'].''; 
		
		## Check whether the password is correct
		if ( ( $_POST['password'] == $xrec['password'] 
				|| crypt("teiteitokencryptor", $_POST['password']) == $xrec['password'] ) 
			&& $_POST['password'] )  { 
			if ( $record['permissions'] != "none"  ){
				$_SESSION[$sessionvar] = $record; 
				
				$user = $_SESSION[$sessionvar]; 
				$username = $user['email']; 
				
				actionlog ( "user {$_POST['login']}" );
				
				// Check if this is not a default password that needs to be modified
				if ( $xrec['password'] == $settings['defaults']['password'] ) {
					header("location:index.php?action=user&act=pwdchange&forced=1");
					exit;
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
	
	if ( $user['recid'] == "" ) { // && $action != "login" 
		$action = "login";
		$maintext .= "<h1>{%Login}</h1>";
		$maintext .= "<form action=\"?action=login\" method=post>
				<table><tr><td>{%Email}:<td><input name=login>
				<tr><td>{%Password}:<td><input type=password name=password>
				</table>
				<input type=hidden name=goon value={$_GET['action']}>
				<input type=submit value={%Login}></form>";
	} # else { $maintext .= "logged in as $username"; };

?>
