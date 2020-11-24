<?php

	$maintext .= "<h1>Visitor Session</h1>";

	if ( $user['email']) {
		$maintext .= "<p>You are currently logged in as corpus administrator {$user['realname']}";
	} else if ( $_SESSION['extid']['realname'] ) {
		$maintext .= "<p>You are currently logged in as visitor {$_SESSION['extid']['realname']}";
	} else {
		$maintext .= "<p>You are not currently logged in";
	};

?>

