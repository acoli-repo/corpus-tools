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

		if ( file_exists("Users/cql_$shortuserid.xml") ) {
			$xmlq = simplexml_load_file("Users/cql_$useridtxt.xml");
			$maintext .= "<tr><th colspan=4>{%Permanently stored queries}";
			foreach ( $xmlq->xpath("//query") as $sq ) {
				$done[$sq['cql'].""] = 1; 
				$display = $sq['name'] or $display = $sq['display'] or $display = $sq['cql'];
				if ( $sq['display'] && $sq['name'] ) $desc = "<span title='".urldecode($sq['cql'])."'>{$sq['display']}</span>"; else $desc = $sq['display'] or $desc = $cql; if ( $desc == $display ) $desc = "";
				$cqltxt = urlencode($sq['cql']);
				$maintext .= "<tr><td><input type=checkbox name='myqueries[$cqltxt]' value='1'><td>$display<td><a href='index.php?action=$action&act=storedit&cql=$cqltxt'>{%edit}</a><td><a href='index.php?action=cqp&cql=$cqltxt'>{%view}</a><td>$desc";
			};
		};

		$maintext .= "<p><a href='index.php?action=login&act=exit'>logout</a>";
	};

?>

