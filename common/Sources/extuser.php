<?php

	$maintext .= "<h1>Visitor Session</h1>";

	if ( $user['email']) {
		$maintext .= "<p>You are currently logged in as corpus administrator {$user['realname']}";
		$logintype = "admin";
	} else if ( $_SESSION['extid']['realname'] ) {
		$maintext .= "<p>You are currently logged in as visitor {$_SESSION['extid']['realname']}";
		
		if ( $_SESSION['extid']['shibboleth'] )	$maintext .= "<p><table>
				<tr><th>Name<td>{$_SESSION['extid']['name']}
				<tr><th>Identifier<td>{$_SESSION['extid']['shibboleth']}
				<tr><th>Organization<td>{$_SESSION['extid']['organization']}
				<tr><th>Mail<td>{$_SESSION['extid']['mail']}
				</table>";
		$logintype = "guest";
	} else {
		$maintext .= "<p>You are not currently logged in";
	};
	
	if ( $logintype ) {
		$maintext .= "<p><a href='index.php?action=login&act=exit'>logout</a>";
	};

?>

