<?php
	// Script to allow users to see and modify their settings
	// (c) Maarten Janssen, 2015

	check_login();
	
	if ( $act == "changepwd" ) {
		if ( $user['shared']) fatal("Shared record");
	
		$newpwd = password_hash($_POST['newpwd'], PASSWORD_DEFAULT);
		
		$xml = simplexml_load_file("Resources/userlist.xml");
	
		$result = $xml->xpath("//user[@email='{$user['email']}']"); 
		$usernode = $result[0];

		if ( $_POST['newemail'] ) {
			$usernode['email'] = $_POST['newemail'];
			$usernode['short'] = $_POST['short'];
			$usernode[0][0] = $_POST['fullname'];
		} else if ( $usernode['password'] != $_POST['oldpwd'] 
					&& crypt("teiteitokencryptor", $_POST['oldpwd']) != $usernode['password'] 
					&& !password_verify($_POST['oldpwd'], $usernode['password']) 
					) fatal("Old password is not correct");
		if ( $_POST['newpwd'] != $_POST['newpwd2'] ) fatal("Passwords do not match");
		
		$usernode['password'] = $newpwd;
		unset($usernode['tochange']);
		$usernode['enc'] = "1";

		$record['email'] = $usernode['email'].''; 
		$record['short'] = $usernode['short'].''; 
		$record['permissions'] = $usernode['permissions'].''; 
		$record['group'] = $usernode['group'].''; 
		$record['fullname'] = $usernode.''; 
		$_SESSION[$sessionvar] = $record; 
		
		$newxml = $xml->asXML(); 
		file_put_contents("Resources/userlist.xml", $newxml);
		
		print "<p>Password changed - reloading";
		header("location:index.php?action=user");
		print "<script language=Javascript>top.location='index.php?action=user';</script>";
		exit;
		
	} else if ( $act == "pwdchange" ) {	
		
		if ( $_GET['forcec'] ) $txt = "<p>For security reasons you are asked to choose a new password";
		
		if ( $user['email'] == "nobody@nowhere.com" ) $dorow = "<tr><td colspan=2><i>Replace the default admin user by your personal account</i><tr><td>Email address: <td><input name=newemail size=60>
				<tr><td>Full name: <td><input name=fullname size=60>
				<tr><td>Short name (initials): <td><input name=short size=6>
				";
		else $dorow = "<tr><td>Old password: <td><input name=oldpwd type=password>";
		
		$maintext .= "
			<h1>Change your Password</h1>
			$txt
			<form action='index.php?action=$action&act=changepwd' method=post>
				<table>
				$dorow
				<tr><td>New password: <td><input name=newpwd type=password>
				<tr><td>Repeat: <td><input name=newpwd2 type=password>
				</table>
				<p><input type=submit value=Change>
			</form>
			";
			
	} else {
	
		if ( $user['group'] ) $grouptxt = "<tr><th>Group:<td>{$user['group']}";
		$useridtxt = $shortuserid;
		
		if ( $_SESSION['myqueries'] || file_exists("Users/cql_$useridtxt.xml") ) $more .= "<p><a href='index.php?action=multisearch&act=stored'>{%Stored CQL queries}</a>";
		if ( file_exists("Users/ann_$useridtxt.xml") ) $more .= "<p><a href='index.php?action=classify'>{%Custom annotation}</a>";

		$qfldr = preg_replace("/[^a-z0-9]/", "", strtolower($userid));
		if ( file_exists("Users/$qfldr/queries.xml") ) $more .= "<p><a href='index.php?action=querymng'>{%Stored queries}</a>";

		
		if ( $user['shared'] ) $more .= "<p><i>Shared user - changes should be made in the shared folder</i>"; 
		else $more .= "<p><a href='index.php?action=$action&act=pwdchange'>Change password</a>";



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
			
			$more
			
			<p><a href='index.php?action=login&act=exit'>Logout</a>
			";
		
	};

?>