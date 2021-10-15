<?php

	// A module to allow login using ORCID
	// requires the ORCID API enabled for some user, with the public and private key copied to settings.xml
	// (c) Maarten Janssen, 2018

	if ( !$settings['permissions']['orcid']['public'] || !$settings['permissions']['orcid']['private'] ) {
		print "ORCID identification not set-up yet for this server"; exit;
	};

	$maintext .= "<h1>ORCID Login</h1>";

	if ( $act == "logout" ) {
		
		session_destroy();
		unset($_SESSION);
		
		print "You have been logged out
			<script language=Javascript>top.location='index.php?action=$action';</script>"; exit;

	};

	$redirecturl = $settings['permissions']['orcid']['redirect'] 
		or $redirecturl = "https://{$_SERVER['SERVER_NAME']}{$baseurl}index.php?action=orcid";
	
	if ( $_SESSION['extid']['orcid'] ) {

		$maintext .= "<p><img alt=\"ORCID logo\" src=\"https://orcid.org/sites/default/files/images/orcid_16x16.png\" width=\"16\" height=\"16\" hspace=\"4\" /> You are logged in as: <a target=orgid href='https://orcid.org/{$_SESSION['extid']['orcid']}'>{$_SESSION['extid']['orcid']}</a> ({$_SESSION['extid']['realname']})
		
			<p><a href='index.php?action=$action&act=logout'>{%Logout}</a>";

	} else if ( $act == "userlist" ) {

		check_login("admin");
		
		$orcidlist = simplexml_load_file("Resources/orcidlist.xml");

		$maintext .= "<h2>List of users that logged in using ORCID</h2>
			<table><th>Name<th>ORCID<th>Last login";
		foreach ( $orcidlist->xpath("//user") as $tmp ) {
			$maintext.= "<tr><td>{$tmp['realname']}
				<td><a href='https://orcid.org/{$tmp['id']}'>{$tmp['id']}</a>
				<td>".date("d M Y", $tmp['lastlogin']."");
		};

	} else if ( $_GET['code'] ) {

		// curl -i -L -k -H 'Accept: application/json' --data 'client_id=APP-61RN73KGL0APH8RN&client_secret=51617c0e-79c7-449f-9b57-0e04ef889df5&grant_type=authorization_code&redirect_uri=http://teitok.iltec.pt/muneco-von/index.php?action=test2&code=REPLACE WITH OAUTH CODE' https://orcid.org/oauth/token

		// header("Accept: application/json");
		$url = 'https://orcid.org/oauth/token';
		$data = array(
			'client_id' => "{$settings['permissions']['orcid']['public']}", 
			'client_secret' => "{$settings['permissions']['orcid']['private']}",
			'grant_type' => 'authorization_code',
			"redirect_uri" => "$redirecturl",
			"code" => "{$_GET['code']}"
			);

		// use key 'http' even if you send the request to https://...
		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		if ($result === FALSE) { 
			print "Failed to login to ORCID<hr>"; print_r($result); 
			exit;
		}

		if ( preg_match("/\"access_token\":\"([^\"]+)\"/", $result, $matches ) ) {
			$_SESSION['extid']['orcid-bearer'] = $matches[1];
			if ( preg_match("/\"orcid\":\"([^\"]+)\"/", $result, $matches ) ) $_SESSION['extid']['orcid'] = $matches[1];
			if ( preg_match("/\"name\":\"([^\"]+)\"/", $result, $matches ) ) $_SESSION['extid']['realname'] = $matches[1];
			$orcid = $_SESSION['extid']['orcid'];
			$_SESSION['extid']['userid'] = $orcid;
		
			# Store/load this user in an XML file
			print "Logged in - going to store data in XML file as /userlist/user[@id=\"$orcid\"]";
			if ( !file_exists("Resources/orcidlist.xml") ) file_put_contents("Resources/orcidlist.xml", "<userlist></userlist>");
			$orcidlist = simplexml_load_file("Resources/orcidlist.xml");
			if ( !$orcidlist ) print "Failed to load orcidlist XML file";
			else {
				$orcidrec = xpathnode($orcidlist, "/userlist/user[@id=\"$orcid\"]");
				$orcidrec["realname"] = $_SESSION['extid']['realname'];
				$orcidrec["lastlogin"] = time();
				file_put_contents("Resources/orcidlist.xml", $orcidlist->asXML());			
			};
					
		} else {
			print "Failed to login to ORCID<hr>"; print_r($result); 
			exit;
		};

		print "
			You have been logged in. Reloading (and closing this window).
			<script language=Javascript>
			window.onunload = refreshParent;
			function refreshParent() {
				window.opener.location.reload();
			}
			window.close()
			</script>";
		exit;

	} else if ( $act == "biblist" ) {

		$userid = $_GET['orcid'] or $userid = $_SESSION['extid']['orcid'];

		$url = 'https://orcid.org/oauth/token';
		$data = array(
			'client_id' => "{$settings['permissions']['orcid']['public']}", 
			'client_secret' => "{$settings['permissions']['orcid']['private']}",
			'scope' => '/read-public',
			'grant_type' => 'client_credentials',
			);

		$options = array(
			'http' => array(
				'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
				'method'  => 'POST',
				'content' => http_build_query($data)
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		if ($result === FALSE) { 
			print "Failed to login to ORCID<hr>"; print_r($result); 
			exit;
		}

		if ( preg_match("/\"access_token\":\"([^\"]+)\"/", $result, $matches ) ) {
			$bearer = $matches[1];
		} else {
			print "Failed to login to ORCID<hr>"; print_r($result); 
			exit;
		};


		// Read the user data 
		$userdata = orcidurl("https://pub.orcid.org/v2.1/$userid/personal-details/"); 

		$realname = current($userdata->xpath("//given-names"))." ".current($userdata->xpath("//family-name"));
		$maintext .= "<h1>$realname</h1>
	
			<p>ORCID <a href='https://orcid.org/$userid'>$userid</a></p>";

		// Read the publication list
		$works = orcidurl("https://pub.orcid.org/v2.1/$userid/works/"); 

		foreach ( $works->xpath("//orcid-work") as $tmp ) {
			$title = current($tmp->xpath(".//title"));
			$year = current($tmp->xpath(".//year"))."";
			$booktitle = current($tmp->xpath(".//journal-title"));
			$type = current($tmp->xpath(".//work-type"));
			$doi = current($tmp->xpath(".//work-external-identifier[./work-external-identifier-type/text()='doi']/work-external-identifier-id"));
			if ( $doi ) $title = "<a href='https://doi.org/$doi' target=paper>$title</a>";
			$entry = "<div style='margin-bottom: 20px;'><b>$title</b>. <i>$booktitle</i> <span style='color: #aaaaaa;'>| $type</span></div>";
			$lst[$year] .= $entry;
			if ( $_GET['debug'] )
				$maintext .= "<hr>".htmlentities($tmp->asXML());	
		};

		krsort($lst, SORT_NUMERIC);
		foreach ( $lst as $key => $val ) {
			if ( $key == "" ) $key = "(without date)";
			$maintext .= "<h3>$key</h3><ul>$val</ul>";
		};
		
	} else {
		$maintext .= "
		<p>For advanced corpus access, you can login using your ORCID account.
		
		<p><button id=\"connect-orcid-button\" onclick=\"openORCID()\"><img id=\"orcid-id-logo\" src=\"https://orcid.org/sites/default/files/images/orcid_24x24.png\" width='24' height='24' alt=\"ORCID logo\"/>Create or Connect your ORCID iD</button>

		<p>\"<a href='orcid.org'>ORCID</a> provides a persistent digital identifier that distinguishes you from other researchers.\"</p>

		<script type=text/javascript>
		var oauthWindow;

		function openORCID() {
			var oauthWindow = window.open(\"https://orcid.org/oauth/authorize?client_id={$settings['permissions']['orcid']['public']}&response_type=code&scope=/authenticate&redirect_uri=$redirecturl\", \"_blank\", \"toolbar=no, scrollbars=yes, width=500, height=600, top=500, left=500\");
		}
		</script>

		<style>
		#connect-orcid-button{
			border: 1px solid #D3D3D3;
			padding: .3em;
			background-color: #fff;
			border-radius: 8px;
			box-shadow: 1px 1px 3px #999;
			cursor: pointer;
			color: #999;
			font-weight: bold;
			font-size: .8em;
			line-height: 24px;
			vertical-align: middle;
		}

		#connect-orcid-button:hover{
			border: 1px solid #338caf;
			color: #338caf;
		}

		#orcid-id-logo{
			display: block;
			margin: 0 .5em 0 0;
			padding: 0;
			float: left;
		}
		</style>";
	};

	function orcidurl ( $url ) {
		global $bearer;
	
		// Read from ORCID API 
		$data = array(
			);
		$options = array(
			'http' => array(
				'header'  => "Content-Type: application/orcid+xml\r\nAuthorization: Bearer $bearer\r\n",
				'method'  => 'GET',
			)
		);
		$context  = stream_context_create($options);
		$result = file_get_contents($url, false, $context);
		if ($result === FALSE) { 
			print "Request failed<hr>";
			  $error = error_get_last();
			  echo "HTTP request failed. Error was: " . $error['message'];		 
			exit;
		}
		return simplexml_load_string(str_replace("xmlns", "away", $result)); 
	};


?>