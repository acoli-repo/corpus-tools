<?php

	$maintext .= "<h1>Visitor Session</h1>";

	if ( $user['email']) {
		$maintext .= "<p>You are currently logged in as corpus administrator {$user['realname']}";
		$logintype = "admin";
	} else if ( $_SESSION['extid']['realname'] ) {
		$maintext .= "<p>You are currently logged in as visitor {$_SESSION['extid']['realname']}";
		$logintype = "guest";
	} else {
		$maintext .= "<p>You are not currently logged in";
	};
	
	if ( $logintype ) {
		$maintext .= "<p><a href='index.php?action=login&act=exit'>logout</a>";
	};

?>

