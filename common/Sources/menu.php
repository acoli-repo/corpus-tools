<?php
	// Script to display the menubar
	// Maarten Janssen, 2015
	// Default items: {%Home}, {%Search}, {%About}, {%!help}

	if ( is_array($settings['languages']['options']) && count($settings['languages']['options']) > 1 ) {
		$sep = "";
		foreach ( $settings['languages']['options'] as $key => $item ) {
			if ( $item['menu'] == "" ) continue;
			if ( $key == $lang ) $langstxt .= "<span class=langon>$sep{$item['menu']}</span>";
			else {
				if ( $settings['languages']['prefixed'] ) {
					$langurl = "$baseurl$key/index.php?".$_SERVER['QUERY_STRING'];
				} else {
					if ( preg_match("/\?/", $_SERVER['REQUEST_URI']) ) $ss = "&"; else $ss = "?";
					$langurl = $_SERVER['REQUEST_URI']."{$ss}lang=$key";
				};
				$langstxt .= "$sep<span class=langoff><a href='$langurl'>{$item['menu']}</a></span>";
			};
			$sep = " | ";
		};
		if ( $settings['languages']['position'] ) {
			$smarty->assign($settings['languages']['position'], $langstxt);
		} else $menu .= "<div stle='margin-top: 0px; margin-bottom: 20px;'>$langstxt</div>";	
	};

	if ( $settings['menu']['name'] ) $menu .= "<p class='title'>{%{$settings['menu']['name']}}</p>";

	if ( $settings['menu']['title'] == "none" ) $nomentit = 1; # Do nothing
	else if ( $settings['menu']['title'] ) {
		$menutitle = $settings['menu']['title'];
		if ( $menutitle == "[title]" ) $menutitle = $settings['defaults']['title']['display'];
		$menutitle = "{%$menutitle}";
		if ( $settings['menu']['link'] ) $menutitle = "<a href='{$settings['menu']['link']}'>{%$menutitle}</a>";
		$menu .= "<p class='header'>$menutitle</p>";
	} else $menu .= "<p>{%Main Menu}</p>";
	
    $menu .= "<ul style='text-align: left'>";
        	
    foreach ( $settings['menu']['itemlist'] as $key => $item ) { 
    	if ( !is_array($item) ) continue; # Skip attributes
    	if ( $item['link'] ) $link = $item['link'];
    	else if ( substr($key,0,4) == "http" ) $link = $key;
    	else $link = "{$tlpr}index.php?action=$key";
    	$trgt = ""; if ( $link['target'] ) $trgt = " target=\"{$link['target']}\"";
    	if ( preg_replace("/&.*/", "", $key) == $action ) {
    		$scl = " class='selected'"; 
    		$scli = " class='active'"; 
    	} else {
    		$scl = "";
    		$scli = "";
    	};
		$itemtxt = $item['display'] or $itemtxt = $key;
		if ( $item['title'] ) $scl .= " title=\"{$item['title']}\"";
    	if ( $item['type'] == "separator" ) {
    		$menu .= "</ul><hr><ul style='text-align: left'>";
    	} else if ( $item['type'] == "header" ) {
    		$menu .= "</ul><h3>{%$itemtxt}</h3><ul style='text-align: left'>";
		} else if ( $item['admin'] ) {
    		if ( $item['admin'] == 1 || $user['permissions'] == "admin" ) {
	    		$adminitems .= "<ul style='text-align: left'><li $scli><a$trgt href='$link'$scl>{%$itemtxt}</a></ul>";
	    	};
    	} else {
    		$menu .= "<li><a$trgt href='$link'$scl>{%$itemtxt}</a>";
    	};
	};
   $menu .= "</ul>";
	
  	if ( $username ) {
  	
		$shortuserid = $user['short'];
	 	$usertype = "user"; if ( $user['projects'] == "all" ) $usertype = "<span title='server-wide user'>guser</span>";
  		$menu .= "<hr>$usertype: <a href='index.php?action=user'>$shortuserid</a><hr>";
  		$tmp = ""; if ( $action == "admin" ) $tmp = "class=\"selected\""; 
  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=admin' $tmp>Admin</a></ul>";
  		$menu .= "<ul style='text-align: left'><li><a target=help href='http://www.teitok.org/index.php?action=help'>Help</a></ul>";
		if ( ( file_exists("/usr/local/bin/tt-cqp") && $settings["cqp"] ) || $settings["defaults"]["tt-cqp"] ) $menu .= "<ul style='text-align: left'><li><a href='index.php?action=classify'>Custom annotation</a></ul>"; 
  		if ( count(scandir("pagetrans")) > 2 && !$settings['menu']['itemlist']['pagetrans'] ) $menu .= "<ul style='text-align: left'><li><a href='index.php?action=pagetrans'>Page-by-Page</a></ul>"; 
  		$tmp = ""; if ( $action == "files" ) $tmp = "class=\"selected\""; 
  		if ( file_exists("xmlfiles") ) $menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=files' $tmp>XML Files</a></ul>";

  		$menu .= $adminitems;
	} else if ( $_SESSION['extid'] ) {
		if ( ! $settings['permissions']['shibboleth'] ) $settings['permissions']['shibboleth'] = array ( "display" => "visitor" ); # Always allow Shibboleth login
		foreach ( $_SESSION['extid'] as $key => $val ) { 
		if ( $tmp = $settings['permissions'][$key] ) { 
			$idtype = $key;
			$idname = $tmp['display'] or $idname = strtoupper($idtype); 
			$idaction = $tmp['login'] or $idaction = "extuser"; 
			$shortuserid = $_SESSION['extid'][$idtype];
			$menu .= "<hr>$idname: <a href='index.php?action=$idaction'>$shortuserid</a><hr>";
			foreach ( $tmp['functions'] as $key => $func ) {
				$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action={$func['key']}' $tmp>{%{$func['display']}}</a></ul>";
			};
		}; }; 
		if ( !$idtype && !$settings['menu']['nologin'] ) {
	  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=login'><i>Login</i></a></ul>";
		};
	} else if ( !$settings['menu']['nologin']  ){
		$shortuserid = "guest";
  		$tmp = ""; if ( $action == "login" ) $tmp = "class=\"selected\""; 
  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=login' $tmp>Login</a></ul>";
	};
        	
        
?>