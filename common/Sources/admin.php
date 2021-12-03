<?php

	check_login();
	
	# Determine the Git root for TEITOK
	$gitfldr = str_replace("/common", "", realpath("$ttroot/common"));

	if ( $act == "shorthand" ) {

		$shorthand = $_POST['shorthand'];
		$maintext .= "<h1>Shorthand Test</h1>
			<p>Here you can test the shorthand you defined. Type in the text in shorthand in the box below and
				click Process to see the resulting XML after the TEI conversion.
				
				<form action='index.php?action=$action&act=$act' method=post>
				<textarea name=shorthand style='width: 100%; height: 100px;'>$shorthand</textarea>
				<p><input type=submit value=Process>
				</form>";
		
		$maintext .= "<hr><p>"; $sep = "";
		foreach ( explode("\n", file_get_contents("Resources/shorthand.tab")) as $line ) {
			list ( $from, $desc, $to ) = explode ( "\t", $line ); 
			if ( $from ) $maintext .= "$sep <span style='color: #66aa66'>".htmlentities($from)."</span>: $desc"; $sep = " &bull; ";
		};
		
		if ( $shorthand ) {
			$maintext .= "<hr>".htmlentities(unshorthand($shorthand));
		};

		
	} else if ( $act == "configcheck" ) {
	
		$maintext .= "<h1>Configuration Check</h1>
			<p>Below are some additional checks to see whether your project is set-up properly</p>
			<style>
	.wrong { color: #aa2000; } .wrong::before { content:'✘ ' }
	.warn { color: #aa8800; } .warn::before { content:'✣ ' }
	.right { color: #209900; } .right::before { content:'✔ ' }
	</style>
	";

		$cfs = array ( 
			"Resources" => array ( "w", "contains all the settings", "TEITOK will not be able to save changes to the settings" ), 
			"xmlfiles" => array ( "w", "contains all the XML files", "TEITOK will not be able to save or modify corpus documents" ), 
			"Trash" => array ( "w", "contains deleted files", "TEITOK will not be able to store deleted file, which will hence disappear" ), 
			"tmp" => array ( "w", "contains temporary files", "TEITOK will not be to store temporary files used in various computational processes and things like error messages" ), 
			"Pages" => array ( "w", "contains HTML files", "TEITOK will not be to save or modify static HTML pages for the project" ), 
		);
		
		foreach ( $cfs as $key => $value ) {
			list ( $rw, $explanation, $failure ) = $value;
			
			if ( $rw == "w" && !is_writable($key) ) {
				$maintext .= "<p class=wrong> The folder $key (which $explanation) should be writable for PHP or $failure";
				$foldererrors = 1;
			};
		};
		if ( !$foldererrors ) {
			$maintext .= "<p class=right> All crucial files/folders are writable";
		};
		
		# Check if the Javascript files are accessible
		$maintext .= "<p id=js class=wrong> Javascript files are not accessible from $jsurl - please change
			<script language=Javascript>
				var img = new Image();
				img.onload = function () {
				   document.getElementById('js').style.display = 'none';
				}
				img.src = '$jsurl/load_img.gif';
			</script>";
	
		# Check if all form inherit properly
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $val ) {
			if ( $key == "pform" || $key == "form" ) continue; # <pform> and @form inherit automiatically
			if ( !$val['inherit'] ) $maintext .= "<p class=wrong>$key ({$val['display']}) is not inheriting";
			else if ( !$settings['xmlfile']['pattributes']['forms'][$val['inherit']] ) $maintext .= "<p class=wrong>$key ({$val['display']}) inherits from @{$val['inherit']}, which does not exist";
		};
		
		# Check that there is an i18n file for each interface language
		foreach ( $settings['languages']['options'] as $key => $val ) {
			if ( $key == "en" ) continue;
			if ( !file_exists("$ttroot/common/Sources/i18n/i18n_$key.php") && !file_exists("Sources/i18n_$key.php") ) 
				$maintext .= "<p>There is no localization file for {$val['display']} ($key) - <a href='index.php?action=i18n&act=makephp&lid=$key'>you should create one</a>";
			else {
				# Check that the i18n file is (more or less) up-to-date
				if ( file_exists("Sources/i18n_$key.php") ) $langfl = "Sources/i18n_$key.php"; else $langfl = "$ttroot/common/Sources/i18n/i18n_$key.php";
				$autocnt = count(explode("\n", file_get_contents("$ttroot/common/Sources/i18n/i18n_auto.php") )) - 5;
				$langcnt = count(explode("\n", file_get_contents($langfl) )) - 5;
				
				if ( $langcnt < $autocnt/1.2 ) {
					$maintext .= "<p class=warn>The localization file for {$val['name']} ($key) appears to be outdated, defining only $langcnt of the $autocnt localizable terms. <a href='index.php?action=i18n&act=makephp&lid=$key'>You should consider expanding it</a>";
				};
			}; 
		};
		
		# Check that the new teiHeader settings are used
		if ( !$settings['teiheader'] ) {
			$maintext .= "<p class=warn>Since version 2.5 TEITOK keeps the metadata in the settings file instead of a metadata template. You should go to the <a href='index.php?action=metadata'>settings</a> section to convert to the new format";
		};
		
		# Check that there are no duplicate filenames
		$results = shell_exec("find xmlfiles/ -name *.xml -print | perl -pe 's/.*\///' | sort | uniq -cd");
		if ( $results ) {
			$maintext .= "<p class=wrong>There are files with the same name in the xmlfiles folder, which is not allowed in TEITOK - each XML files needs a unique filename. Below is the list of conflicting files
				<pre>$results</pre>";
		};
		
		# Check some inconsistent setings files
		if ( $tmp && !$settings['cqp']['sattributes']['text'] ) {
			# <text> needs to exist
			$maintext .= "<p class=wrong>Your CQP settings does not contain a section for the text; almost all metadata are text-level so you should add a text level</p>";
		};
		$tmp = $settings['cqp']['defaults']['subtype'];
		if ( $tmp && !$settings['cqp']['sattributes'][$tmp] ) {
			# Default Context view needs to exist
			$maintext .= "<p class=wrong>Your default CQP context is set to $tmp, but there is no such level defined in the CQP settings</p>";
		};
		$tmp = $settings['cqp']['defaults']['context'];
		if ( $tmp && !$settings['cqp']['sattributes'][$tmp] ) {
			# Default Context view needs to exist
			if ( !preg_match("/^\d+$/", $tmp) ) $maintext .= "<p class=wrong>Your default CQP context is set to $tmp, but there is no such level defined in the CQP settings</p>";
		};
		
		$maintext .= "<p class='csscheck wrong'>If this paragraph is visible, the teitok.css style file is not linked properly in the template</p>";
			
	} else if ( $act == "checksettings" ) {
	
		check_login("admin");
	
		if ( $foldername != $settings['defaults']['base']['foldername'] ) {
			$projtit = $settings['defaults']['title']['display'] or $projtit = "<i>Not always used</i>";
			$burl = $settings['defaults']['base']['url'] or $burl = "<i>Not always used</i>";
			$maintext .= "<h1>Check Settings</h1>
				<p>The folder where this project is located ($foldername) does not correspond to the
					folder specified in the settings ({$settings['defaults']['base']['foldername']}). This is typically due to the fact that
					you moved your project, copied an existing project to create a new one,
					 or that you recently updated TEITOK from before version 1.8. 
					 In all those cases, you are asked to verify the settings below 
					make sure they are accurate, and click 'confirm settings' when all necessary changes have
					been made. If these settings do not belong to the current 
					project, make sure to carefully revise the <a href='index.php?action=adminsettings'>settings</a>. Click a 
					value to change it. Current action: $action";
			$confirm = "<input type=submit value='Confirm settings'>";
		} else {
			$maintext .= "<h1>Check Settings</h1>
				<p>Below is a listing of some crucial project settings - please verify that these are correct.
				If these settings do not belong to the current 
					project, make sure to carefully revise the <a href='index.php?action=adminsettings'>settings</a>. Click a 
					value to change it.
				";
		};
				
		$maintext .= "<table>
			<tr><th>Project name<td><a target=edit href='index.php?action=adminsettings&act=edit&node=/ttsettings/defaults/title/@display'>$projtit</a>
			<tr><th>Corpus name<td><a target=edit href='index.php?action=adminsettings&act=edit&node=/ttsettings/cqp/@corpus'>{$settings['cqp']['corpus']}</a>
			<tr><th>Base URL<td><a target=edit href='index.php?action=adminsettings&act=edit&node=/ttsettings/defaults/base/@url'>{$burl}</a>
			</table>
			<form action='index.php?action=adminsettings&act=save' method=post>
			<textarea style='display: none;' type=hidden name=xpath>/ttsettings/defaults/base/@foldername</textarea>
			<p><input name=newval  type=hidden value='$foldername'>
			$confirm</form>";
				
		if ( file_exists("Scripts/recqp.pl") ) {
			$recqp = file_get_contents("Scripts/recqp.pl") ;
			$maintext .= "<hr>
				<p>You furthermore have a customized script to generate the CQP corpus. Check
				this script, and modify it if necessary, or remove it completely if you do not
				in fact have any customized features in your corpus
				<pre>$recqp</pre>";
				
		};
	
	} else {
	
		if ( $settings['permissions']['groups'] )  $grouprec = $settings['permissions']['groups'][$user['group'].""];
		$adminmenulist = array (
				"upload" => "upload files",
				"pageedit" => "edit HTML files",
				"i18n" => "edit internationalization",
				"csv" => "batch-edit XML using CSV",
			);
			
		if ( $grouprec['actions'] ) $grouptxt = "of your group ({$user['group']})";
		$maintext .= "<h1>Admin Functions</h1>
			
			<p>These are the options available for editors of the corpus $grouptxt

			<ul>";

		if ( allowedforme("create") ) {
			#if ( $settings['xmltemplates'] || file_exists("Resources/xmltemplate.xml" ) ) $maintext .= "<li><a href='index.php?action=create'>create new XML from template</a>"; else 
			$maintext .= "<li><a href='index.php?action=create'>create new XML file</a>";
		};

		if ( $settings['cqp']['corpus'] && allowedforme("recqp") ) {
			$maintext .= "<li><a href='index.php?action=recqp'>(re)generate the CQP corpus</a> (or only 
				<a href='index.php?action=recqp&check=1'>check</a> the status)";
			# if ( $user['permissions'] == "admin" ) { 
			#	$maintext .= "<ul><li>  <a href='index.php?action=recqp&force=1'>regenerate</a> the script to regenerate the CQP corpus (after changing CQP settings)</ul>";
			# };
		};
		
		foreach ( $adminmenulist as $key => $val ) {
			if ( allowedforme($key) ) {
				$maintext .= "			<li><a href='index.php?action=$key'>$val</a>";
			};
		};
			
				
		if ( $settings['neotag'] && allowedforme("neotag") ) {
				$maintext .= "<li>  <a href='index.php?action=neotag'>check or update</a> the NeoTag parameter set(s)";
		};
		if ( ( file_exists("Resources/tagset.xml") ) && allowedforme("tagset") ) {
				$maintext .= "<li>  <a href='index.php?action=tagset'>check</a> the tagset";
		};
				
		if ( $user['permissions'] == "admin" ) {
			$maintext .= "<li><a href='index.php?action=useredit'>edit users</a>";
			$maintext .= "<li><a href='index.php?action=adminsettings'>edit settings</a>";
			$maintext .= "<li><a href='index.php?action=admin&act=configcheck'>check configuration settings</a>";
			$maintext .= "<li><a href='index.php?action=adminedit'>edit resource files</a>";
		};
		
		if ( $settings['teiheader'] ) {
						$maintext .= "<li><a href='index.php?action=headersettings'>teiHeader (metadata) definitions</a>";
		};		
		
		if ( ( $filelist || file_exists("Resources/filelist.xml" ) ) && allowedforme("filelist") )
			$maintext .= "<li><a href='index.php?action=filelist'>view file repository</a>";
				
// 		if ( file_exists("Facsimile" ) && allowedforme("images") )
// 			$maintext .= "<li><a href='index.php?action=images&act=check'>check facsimile images</a>";

		if ( is_array($settings['menu']['admin']) )
		foreach ( $settings['menu']['admin'] as $key => $item ) { 	
			$link = "{$tlpr}index.php?action=$key";
			if ( allowedforme($key) && $item['display'] )
				$maintext .= "<li><a href='$link'>".$item['display']."</a>";
		};


		$checkshared = preg_replace("/.*\/([^\/]+)\/?/", "\\1", getenv('TT_SHARED'));
		if ( $checkshared == $foldername && $user['projects'] == "all" ) {
				$maintext .= "<li><a href='index.php?action=sharedadmin'>server-wide settings</a>";
				$issharedproject = 1;
		};
		
		$maintext .= "</ul>
		
			<hr>
			
			<p>For help on admin functions see the <a href='http://teitok.corpuswiki.org/site/index.php?action=help&project={$_SERVER['HTTP_HOST']}$baseurl'>Help</a> section online.
			";
		
		if ( $sharedfolder ) {
			if ( $sharedsettings ) $maintext .= "<p style='font-size: small; color: #999999;'>These settings are supplemented by shared settings";
			else $maintext .= "<p style='font-size: small; color: #999999;'>These settings can be supplemented by shared settings";
		};
	
		# Display the TEITOK version
		if ( file_exists("$ttroot/common/Resources/version.xml") ) {
			$tmp = simplexml_load_file("$ttroot/common/Resources/version.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			$version = $tmp[0];
			$maintext .= "<p style='font-size: small; color: #999999;'>TEITOK version: {$version['version']}, {$version['date']}";	

			$scopts['http']['timeout'] = 3; // Set short timeout here to avoid hanging
			if ( $settings['defaults']['base']['proxy'] ) $scopts['http']['proxy'] = $settings['defaults']['base']['proxy'];
			$ctx = stream_context_create($scopts);	
			$latesturl = "http://www.teitok.org/latest.php?url={$_SERVER['HTTP_HOST']}".preg_replace("/\/index\.php.*/", "", $_SERVER['REQUEST_URI'])."&version={$version['version']}";
			$tmpf = file_get_contents($latesturl, false, $ctx);
			$tmp = simplexml_load_string($tmpf, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			if ( $tmp ) {
				$tmp2 = $tmp->xpath("//info");
				$latest = $tmp2[0];
				if ( $latest['version']."" != $version['version']."" ) $maintext .= " - Latest version: {$latest['version']}, {$latest['date']}" ;
				else  {
					$maintext .= " (up-to-date)";
					$uptodate = 1;
				};
			};
			
		};

	};
		
	function allowedforme ( $checkaction ) {
		global $user, $settings, $publicactions;
		if ( $settings['permissions']['groups'] ) $grouprec = $settings['permissions']['groups'][$user['group']];
		if ( $user['permissions'] == "admin" 
				|| !$grouprec['actions'] 
				|| in_array($checkaction, explode(",",$grouprec['actions']) ) 
				|| in_array($checkaction, explode(",",$publicactions) ) 
			) {
			return 1;
		};
		return 0;
	};
		
?>