<?php
	// Script to display the menubar
	// (c) Maarten Janssen, 2015

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

	if ( $settings['menu']['name'] ) $menu .= "<p style='title'>{%{$settings['menu']['name']}}</p>";

	if ( $settings['menu']['title'] ) $menu .= "<p style='header'>{%{$settings['menu']['title']}}</p>";
	else $menu .= "<p>{%Main Menu}</p>";
	
    $menu .= "<ul style='text-align: left'>";
        	
    foreach ( $settings['menu']['itemlist'] as $key => $item ) { 	
    	$link = "{$tlpr}index.php?action=$key";
    	if ( $key == $action ) {
    		$scl = " class='selected'"; 
    		$scli = " class='active'"; 
    	} else {
    		$scl = "";
    		$scli = "";
    	};
		$itemtxt = $item['display'] or $itemtxt = $key;
    	if ( $item['admin'] ) {
    		if ( $item['admin'] == 1 || $user['permissions'] == "admin" ) {
	    		$adminitems .= "<ul style='text-align: left'><li $scli><a href='$link'>{%$itemtxt}</a></ul>";
	    	};
    	} else {
    		$menu .= "<li><a href='$link'$scl>{%$itemtxt}</a>";
    	};
	};
   $menu .= "</ul>";
	
  	if ( $username ) {
  		$menu .= "<hr>user: <a href='index.php?action=user'>{$user['short']}</a><hr>";
  		$tmp = ""; if ( $action == "admin" ) $tmp = "class=\"selected\""; 
  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=admin' $tmp>Admin</a></ul>";
  		$tmp = ""; if ( $action == "files" ) $tmp = "class=\"selected\""; 
  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=files' $tmp>XML Files</a></ul>";
  		if (file_exists("Resources/filelist.xml")) $menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=filelist'>File repository</a></ul>";
  		$menu .= $adminitems;
	} else {
  		$menu .= "<ul style='text-align: left'><li><a href='{$tlpr}index.php?action=login'>Login</a></ul>";
	};
        	
        
?>