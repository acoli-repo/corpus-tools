<?php
	// Script to change {%$xxx} in HTML 
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

	} else if ( $act == "savephp" && $_POST['lid'] ) {
		
		$lid = $_POST['lid'];
		// Save the local i18n file
		$outfile = "Sources/i18n_$lid.php";
		$outtxt = '<?php';
		$date = date("Y-m-d"); 
		$fullname = trim($user['fullname']);
		$outtxt .= "\n\t// Localization file created $date by $fullname\n";
		$outtxt .= '	$i18n = array (
';
		foreach ( $_POST['totxt'] as $from => $to ) {
			$cnt2++;
			if ( $to != "" ) {
				$cnt1++;
				$outtxt .= "\t\t\"$from\" => \"$to\",\n";
			};
		};
		$outtxt .= '	);
?>
';

		file_put_contents($outfile, $outtxt);
		$maintext .= "<h1>Data saved</h1>
			<p>Your localization file for <b>$lid</b> has been saved locally.";
			
		if ( $cnt1 < $cnt2 ) $maintext .= " Once (sufficiently) completed (currently translated $cnt1 of $cnt2 terms), please consider sending us the complete
				localization file, so that we can include it with the TEITOK repository, and other researchers can
				profit from the localization files.
				<hr><p><a href='index.php?action=$action&act=makephp&lid={$_POST['lid']}'>Click here to edit again</a>";
		else  $maintext .= "Please consider sending us the complete
				localization file, so that we can include it with the TEITOK repository, and other researchers can
				profit from the localization files.
				<hr><p><a href='index.php?action=$action&act=makephp&lid={$_POST['lid']}'>Click here to edit again</a>";
		
	} else if ( $act == "makephp" ) {

		// Save the local i18n file
		$tolang = $_GET['lid'] or $tolang = $lang;
		$maintext .= "<h1>Defining (new/local) Localization for language: $tolang</h1>
			<p>Provide the translations for the terms below in order to get a localized version of TEITOK in $tolang
			<hr>";
		
		// Load the auto file
		require("$ttroot/common/Sources/i18n/i18n_auto.php"); 
		$i18nauto = $i18n; 
		if ( file_exists("Sources/i18n_$tolang.php") ) { // Local defs overrule global defs
			include("Sources/i18n_$tolang.php");
			$i18nlocal = $i18n;
		};
		if ( file_exists("$ttroot/common/Sources/i18n/i18n_$tolang.php") ) {
			include("$ttroot/common/Sources/i18n/i18n_$tolang.php");
			$i18nglobal = $i18n;
		};
		
		if ( file_exists("Resources/i18n_$tolang.txt") ) { // Local defs overrule global defs
			foreach ( explode("\n", file_get_contents("Resources/i18n_$tolang.txt")) as $line ) {
				list ( $from, $to ) = explode ( "\t", $line );
				$i18ntxt[$from] = $to;
			};
		};

		$tablerows = array();
		foreach ( $i18nauto as $from => $to ) {
			$cnt2++;
			
			$totxt = $i18nlocal[$to] or $totxt = $i18nglobal[$to] or $totxt = $i18ntxt[$to];
			if ( $totxt != "" ) $cnt1++;

			array_push ( $tablerows, "<tr><td>$from<td><input size=60 name=\"totxt[$from]\" value=\"$totxt\">");	
		};
		natcasesort($tablerows);
		$table = join("\n", $tablerows);

		$maintext .= "
			<form action='index.php?action=$action&act=savephp' method=post>
			<input type=hidden name=lid value='$tolang'>
			<table class=rollovertable>
			$table
			</table>
			<p><input type=Submit value=Save> 
			</form>
			<hr><p>Translated $cnt1 of $cnt2 terms
			";	

	} else if ( $act == "savetxt" && $_POST['lid'] ) {

		$lid = $_POST['lid'];
		// Save the local i18n file
		$outfile = "Resources/i18n_$lid.txt";
		$outtxt = '';

		foreach ( $_POST['totxt'] as $from => $to ) {
			$cnt2++;
			if ( $to != "" ) {
				$cnt1++;
				$outtxt .= "$from\t$to\n";
			};
		};
		if ( $_POST['newfrom'] && $_POST['newto'] ) $outtxt .= "{$_POST['newfrom']}\t{$_POST['newto']}\n";

		file_put_contents($outfile, $outtxt);
		$maintext .= "<h1>Data saved</h1>
			<p>Your project-specific localization file for <b>$lid</b> has been saved.
			<hr><p><a href='index.php?action=$action'>Back</a>";
	
	} else if ( $act == "view" && $_GET['lid'] ) {
	
		$langcode = $_GET['lid'];
		if ( !file_exists("Resources/i18n_$langcode.txt") ) fatal ("No such i18n file: $langcode");
		
		$maintext .= "<h1>Internationalization: $langcode</h1>
			<p>These are the project-specific internationalization definitions for: $langcode
			<table class=rollovertable>
			<form action='index.php?action=$action&act=savetxt' method=post>
			<input type=hidden name=lid value='$langcode'>
			<tr><th>English<th>$langcode";

		// Load the auto file
		require("$ttroot/common/Sources/i18n/i18n_auto.php"); 
		$i18nauto = $i18n; 
		if ( file_exists("Sources/i18n_$tolang.php") ) { // Local defs overrule global defs
			include("Sources/i18n_$tolang.php");
			$i18nlocal = $i18n;
		};
		if ( file_exists("$ttroot/common/Sources/i18n/i18n_$tolang.php") ) {
			include("$ttroot/common/Sources/i18n/i18n_$tolang.php");
			$i18nglobal = $i18n;
		};
		
		foreach ( explode ( "\n", file_get_contents("Resources/i18n_$langcode.txt") ) as $line ) {
			
			list ( $from, $to ) = explode ( "\t", $line );
			if ( !$from ) continue;
			$totxt = $i18nlocal[$to] or $totxt = $i18nglobal[$to];
			if ( $totxt != $to ) {
				$maintext .= "<tr><td>$from<td><input name=\"totxt[$from]\" value=\"$to\"><td>";
			} else {
				$maintext .= "<tr><td>$from<td>$to<td><i>Redundant: identical to standard localization</i>";
			};
		};
		$maintext .= "
			<tr><td><input name=\"newfrom\" value=\"\"><td><input name=\"newto\" value=\"\"><td><i>Add line for a default localization you want to override locally</i>
			</table>
			<p><input type=submit value=Save>
			</form>
		
		<hr><p><a href='index.php?action=$action&act=view'>other languages</a>
		&bull; <a href='index.php?action=$action'>edit missing translations</a>
		";
		
	} else if ( $act == "test" ) {
	
		print_r($_SESSION['mistrans']); exit;
		
	} else if ( $act == "view" ) {
	
		$files = glob("Resources/i18n*");
		
		$maintext .= "<h1>Internationalization Files</h1>
			<p>These are the project-specific internationalization definition files
			<ul>";
		
		foreach ( $files as $line ) {
			if ( preg_match("/i18n\_(.*)\.txt/", $line, $matches ) ) {
				$langcode = $matches[1];
				$langopt = $settings['languages']['options'][$langcode];
				if ( $langopt ) {
					$langtxt = $langopt['name'] or $langtxt = $langopt['menu'];
					$langtxt = " = $langtxt"; 
				} else $langtxt = " - <i>Not currently an interface language</i>";
				$maintext .= "<li><a href='index.php?action=$action&act=view&lid=$langcode'>$langcode</a> $langtxt";
			};
		};
		$maintext .= "</ul>";

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

			<p>To switch to a different language to allow you to localize your site before offering 
				the new and yet incomplete language to your site, type in the ISO code of the language below:
				<form action='index.php'><input type=hidden name=action value='$action'><input name=lang size=5> <input type=submit value='Start Browsing'></form>";

		$files = glob("Resources/i18n*");

		if ( $files ) $maintext .= "<p><a href='index.php?action=$action&act=view'>view/edit existing language files</a>";
		
		foreach ( $settings['languages']['options'] as $key => $val ) {		
			if ( !file_exists("$ttroot/common/Sources/i18n/i18n_$key.php") && !file_exists("Sources/i18n_$key.php") ) {
				$maintext .= "<p>Missing localization file for $key (<a href='index.php?action=$action&act=makephp&lid=$key'>create</a>)";
			};		
		};	 
		if ( !$settings['languages']['options'][$lang] && !file_exists("$ttroot/common/Sources/i18n/i18n_$lang.php") && !file_exists("Sources/i18n_$lang.php") ) {
			$maintext .= "<p>Missing localization file for $lang (<a href='index.php?action=$action&act=makephp&lid=$lang'>create</a>)";
		};		

		$maintext .= "<h2>Missing Translation</h2>
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

		$maintext .= "<style>
				.private { color: #999999; }
				.rollovertable tr:nth-child(even) { background-color: #fafafa; }
				.rollovertable tr:hover { background-color: #ffffeb; }
				.rollovertable td { padding: 5px; }
				a.black { color: black; }
				a.black:hover { text-decoration: underline; }
			</style>";
	

?>