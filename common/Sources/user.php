<?php
	// Script to allow users to see and modify their settings
	// (c) Maarten Janssen, 2015

	check_login();
	
	if ( $act == "changepwd" ) {
	
		$newpwd = crypt("teiteitokencryptor", $_POST['newpwd']);
		
		$xml = simplexml_load_file("Resources/userlist.xml");
	
		$result = $xml->xpath("//user[@email='{$user['email']}']"); 
		$usernode = $result[0];
		
		$usernode['password'] = $newpwd;
		
		$newxml = $xml->asXML(); 
		file_put_contents("Resources/userlist.xml", $newxml);
		
		print "<p>Password changed - reloading";
		header("location:index.php?action=user");
		exit;
		
	} else if ( $act == "pwdchange" ) {	
		
		if ( $_GET['forcec'] ) $txt = "<p>For security reasons you are asked to choose a new password";
		
		$maintext .= "
			<h1>Change your Password</h1>
			$txt
			<form action='index.php?action=$action&act=changepwd' method=post>
				<p>Change password: <input name=newpwd>
				<input type=submit value=Change>
			</form>
			";
			
	} else {
	
		if ( $user['group'] ) $grouptxt = "<tr><th>Group:<td>{$user['group']}";
		$maintext .= "
		
			<h1>User Profile</h1>
			
			<table>
			<tr><th>Name:<td>{$user['fullname']}
			<tr><th>Email:<td>{$user['email']}
			<tr><th>Short name:<td>{$user['short']}
			<tr><th>Permissions:<td>{$user['permissions']}
			$grouptxt
			</table>
			
			<hr>
			<p><a href='index.php?action=$action&act=pwdchange'>Change password</a>
			<p><a href='index.php?action=login&act=exit'>Logout</a>
			";
		
	};

?>