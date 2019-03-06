<?php
	# Script to edit users
	# (c) Maarten Janssen 2015
	
	$userfile = file_get_contents("Resources/userlist.xml");
	$userlist = simplexml_load_string($userfile);

	if ( !$user['permissions'] == "admin" ) { fatal ("Function for admin users only"); };
	$id = $_GET['id'];

		if ( !is_writable("Resources/userlist.xml") ) {
			fatal ("Userlist is not writable - please contact admin");
		};
	

	if ( $act == "save" ) {
		$id = $_POST['id'];
	
		if ( !$id ) { fatal ("Nothing to save or no (short) ID"); };
	
		if ( $id == "new" ) {
			$usr = $userlist->addChild("user");
			$usr["short"] = $_POST['short'];
			if ( $usr["short"] == "" ) { # Make sure we always have a short ID
				$i = 1; $tmpid = "usr$i";
				while ( $userlist->xpath("//user[@short=\"$tmpid\"]") ) {
					$i++; $tmpid = "usr$i";
				};
				$usr["short"] = $tmpid;
			};
		} else {
			$result = $userlist->xpath("//user[@short=\"$id\"]");
			$usr = $result[0];
			# $usr["name"] = $_POST['name'];
			$usr["short"] = $_POST['short'];
		};
		$usr[0][0] = $_POST['name'];
		$usr["permissions"] = $_POST['permissions'];
		$usr["email"] = $_POST['email'];
		$usr["group"] = $_POST['group'];
		if ( !$_POST['keep'] ) $usr['tochange'] = "1";
		else $usr['enc'] = "1";
		if ($_POST['password']) { 
			$pwd = password_hash($_POST['password'], PASSWORD_DEFAULT);
			$usr["password"] = $pwd;
		};
	
		# Save the modified XML file
		$xml = $userlist->asXML();
		
		file_put_contents("Resources/userlist.xml", $xml);
		print $xml;
		
		print "Saved - reloading to index
			<script language=Javascript>top.location='index.php?action=$action';</script>";
		exit;
			
	} else if ( $id || $_GET['email'] ) {
	
		if ( $id ) {
			$result = $userlist->xpath("//user[@short=\"$id\"]");
		} else {
			$result = $userlist->xpath("//user[@email=\"{$_GET['email']}\"]");		
		};
		$usr = $result[0];
		$name = preg_replace("/^\s+|\s+$/", "", $usr."" );
		
		if ( $id == "new" ) {
			$idfld = "<input type='hidden' name='id' value='new'>
				<tr><th>Initials<td><input name='short' value='' size=6> (used as user ID in TEI)"; 
		} else { 
			$idfld = "<input type='hidden' name='id' value='{$usr['short']}'>"; 
			$shortfld = "<tr><th>Short Name<td><input name='short' value='{$usr['short']}' size=10> (used in TEI/XML)";
			 $chpwd = "(unchanged when left empty)";
		};
		
		$maintext .= "<h1>User Edit</h1>
			<form action='index.php?action=$action&act=save' method=post>
			<table>
			$idfld
			<tr><th>Real Name<td><input name='name' value='{$name}' size=70>
			<tr><th>Email<td><input name='email' value='{$usr['email']}' size=50> (used as login)
			$shortfld
			<tr><th>Password<td><input name='password' size=20> $chpwd
			<tr><th>Permissions<td><input name='permissions' value='{$usr['permissions']}'> (user, admin, none)
			<tr><th>Group<td><input name='group' value='{$usr['group']}'> (defined in settings)
			</table>
			<input type=checkbox name=keep value=1> User provided this password himself
			<p><input type=submit value=Save> <a href='index.php?action=$action'>cancel</a>
			</form>";
	} else {
		# Display the list of users
		$result = $userlist->xpath("//user");
		$maintext .= "<h1>User Administration</h1>
			<table><tr><th>ID<th>Email<th>Name<th>Status";
		foreach ( $result as $usr ) {

			$userid = $usr['short'];		
			if ( $userid == "" ) {  
				$userlink = "&email=".$usr['email'];
			} else {
				$userlink = "&id=$userid";
			};
			
			$usrtxt = $usr."";
			if ( $usrtxt == "" ) { 
				$usralt = $usr['short'];
				if ( $usralt == "" ) { $usralt = $usr['email']; };
				$usrtxt = "<i>$usralt</i>"; 
			};

			$tochange = ""; if ( $usr['tochange'] ) $tochange = "<i>User needs to change his/her password</i>";
		
			# Hide the MJXXSU user - which is the default user to allow the author of TEITOK access to help out
			if ($usr['email'] != "maarten@clul.ul.pt") $maintext .= "<tr><td>{$usr['short']}<td>{$usr['email']}<td><a href='index.php?action=$action$userlink'>$usrtxt</a><td>{$usr['permissions']}<td>$tochange";
		
		};  
		$maintext .= "</table>
		<p><a href='index.php?action=$action&id=new'>create new user</a>";
	};


?>