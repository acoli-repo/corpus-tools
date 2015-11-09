<?php
	// Script to change {%xxx} in HTML 
	// to its internationalized counterpart  
	// Also allows staff to add missing i18n translations
	// (c) Maarten Janssen, 2015

	check_login();
	
	if ( $act == "save" ) {
	
		foreach ( $_POST['org'] as $langcode => $translist ) {
			$newlines = ""; 
			foreach ( $translist as $id => $org ) {
				$trans = $_POST['trans'][$langcode][$id];
				if ( $trans ) {
					print "<p>$org = $trans";
					$newlines .= "$org\t$trans\n";
					unset($_SESSION['mistrans'][$langcode][$org]);
				};
			};
			if ( $newlines ) {
				file_put_contents("Resources/i18n_$langcode.txt", $newlines, FILE_APPEND);
				$newlang = $langcode;
			};
		};
		print "File saved. Reloading
			<script language=Javascript>top.location='index.php?action=$action&act=view&lid=$newlang';</script>";
		exit;
		
	} else if ( $act == "view" && $_GET['lid'] ) {
	
		$langcode = $_GET['lid'];
		if ( !file_exists("Resources/i18n_$langcode.txt") ) fatal ("No such i18n file: $langcode");
		
		$maintext .= "<h1>Internationalization: $langcode</h1>
			<p>These are the project-specific internationalization definitions for: $langcode
			<table>
			<tr><th>English<th>$langcode";
		
		foreach ( explode ( "\n", file_get_contents("Resources/i18n_$langcode.txt") ) as $line ) {
			list ( $key, $val ) = explode ( "\t", $line );
			$maintext .= "<tr><td>$key<td>$val";
		};
		$maintext .= "</table>
		
		<hr><p><a href='index.php?action=$action&act=view'>other languages</a>
		&bull; <a href='index.php?action=$action'>edit missing translations</a>
		";
	} else if ( $act == "test" ) {
		print_r($_SESSION['mistrans']); exit;
		
	} else if ( $act == "view" ) {
	
		$files = glob("Resources/i18n*");
		
		$maintext .= "<h1>Internationalization Files</h1>
			<p>These are the project-specific internationalization definition files
			";
		
		foreach ( $files as $line ) {
			if ( preg_match("/i18n\_(.*)\.txt/", $line, $matches ) ) {
				$langcode = $matches[1];
				$maintext .= "<p><a href='index.php?action=$action&act=view&lid=$langcode'>$langcode</a>";
			};
		};

	} else if ( $act == "reset" ) {
		
		unset($_SESSION['mistrans']);
		header('location: index.php?action=i18n');
		
	} else if ( $_SESSION['mistrans'] ) {
		$maintext .= "<h1>Translation Editor</h1>
	
			<p>Below are the missing translations encountered during your recent crawl through the site.
				This list is not necessarily complete: any parts of your site you did not visit (during the 
				current session) are not on this list. To verify the rest of you site, keep using it and
				periodically come back to this page. Only text elements for which you provide a translation will
				be stored (to you project-specific i18n), so leave anything you do not want to be stored empty
				(there might be things on the list that are non-translatable in your project, such as folder 
				names).

			<p>To swith to a different language to allow you to localize your site before offering 
				the new and yet incomplete language to your site, type in the ISO code of the language below:
				<form action='index.php'><input type=hidden name=action value='home'><input name=lang size=5> <input type=submit value='Start Browsing'></form>

			<p><a href='index.php?action=$action&act=view'>view existing language files</a>
			 
			<h2>Missing Translation</h2>
				<form action='index.php?action=$action&act=save' method=post>";
		
			foreach ( $_SESSION['mistrans'] as $langcode => $txtrec ) {
				if ( !$langcode ) continue;
				$maintext .= "<h3>Language: $langcode</h3>";
				if (!$settings['languages']['options'][$langcode]) $maintext .= "<p style='color: #992000;'>$langcode is not currently a selectable language</p>";
				$maintext .= "<table>
					<tr><th>'English' term<th>Translation ($langcode)<th>First encountered on";
				foreach ( $txtrec as $textel => $furl ) {
					$cnt++;
					if ($textel) $maintext .= "\n<tr><td>$textel<td><input size=60 name=trans[$langcode][$cnt] value=''><input size=60 name=org[$langcode][$cnt] type=hidden value='$textel'><td><a href='$furl' target=test>$furl</a>";
				}
				$maintext .= "</table>";
			};

				$maintext .= "
					<input type=submit value=save>
					</form>
					
					<hr><p>Click <a href='index.php?action=$action&act=reset'>here</a> if you want to reset the list of remembered missing translations";
	
	} else {
		$maintext .= "<h1>Translation Editor</h1>
	
			<p>TEITOK offers the option to localize your project site: make it available in
				other languages besides English. To do so, there are so-called i18n (internationalization) 
				files that tell what the correct translation is in a specific language of a part of the
				interface. For each language there are two types of i18n files: translation files that come
				with the system and translate the core elements of TEITOK, and project-specific files that
				provide the translation of the project-specific elements of the interface. 
			
			<p>To facilitate in completing the localization process, TEITOK keeps track of all missing 
				translations, and you can visualize them here. To do so, browse through your own site to 
				test as many different pages of your site as possible and in all the languages you 
				want to check, and then come back here to verify if
				there were any missing translations. After that, add the missing translations to the project-specific
				i18n file.
				
			<p>To swith to a different language to allow you to localize your site before offering 
				the new and yet incomplete language to your site, type in the ISO code of the language below:
				
				<form action='index.php'><input type=hidden name=action value='home'><input name=lang size=5> <input type=submit value='Start Browsing'></form>
			
		<hr><p><a href='index.php?action=$action&act=view'>view existing languages</a>
		";
			
	};		

?>