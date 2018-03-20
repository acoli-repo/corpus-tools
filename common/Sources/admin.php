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

		
	} else if ( $act == "update" ) {
		
		if ( $user['permissions'] != "admin" ) { fatal("Not allowed"); };
		if ( !is_writable($gitfldr) ) { fatal("TEITOK cannot be updated from within the browser $gitfldr"); };
		
		$cmd = "cd $gitfldr; /usr/bin/git pull 2>&1";
		$output = shell_exec($cmd);
		
		$maintext .= "<h1>Updating the TEITOK system</h1>
			<p>TEITOK Git folder: $gitfldr</p>
			<pre>$output</pre>";
		
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
					value to change it. ";
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
		if ( ( file_exists("Resources/tagset.xml") || $settings['tagset'] ) && allowedforme("tagset") ) {
				$maintext .= "<li>  <a href='index.php?action=tagset'>check</a> the tagset";
		};
				
		if ( $user['permissions'] == "admin" ) {
			$maintext .= "<li><a href='index.php?action=useredit'>edit users</a>";
			$maintext .= "<li><a href='index.php?action=adminsettings'>edit settings</a>";
			$maintext .= "<li><a href='index.php?action=adminedit'>edit resource files</a>";
			$maintext .= "<li><a href='index.php?action=headermake'>edit teiHeader files</a>";
		};
				
		if ( ( $filelist || file_exists("Resources/filelist.xml" ) ) && allowedforme("filelist") )
			$maintext .= "<li><a href='index.php?action=filelist'>view file repository</a>";
				
// 		if ( file_exists("Facsimile" ) && allowedforme("images") )
// 			$maintext .= "<li><a href='index.php?action=images&act=check'>check facsimile images</a>";

		if ( is_array($settings['menu']['admin']) )
		foreach ( $settings['menu']['admin'] as $key => $item ) { 	
			$link = "{$tlpr}index.php?action=$key";
			if ( allowedforme($key) && $item['display'] )
				$maintext .= "<li><a href='$link'>{%".$item['display']."}</a>";
		};
		
		$maintext .= "</ul>
		
			<hr>
			
			<p>For help on admin functions see the <a href='http://teitok.corpuswiki.org/site/index.php?action=help&project={$_SERVER['HTTP_HOST']}$baseurl'>Help</a> section online.
			";
	
		# Display the TEITOK version
		if ( file_exists("$ttroot/common/Resources/version.xml") ) {
			$tmp = simplexml_load_file("$ttroot/common/Resources/version.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			$version = $tmp[0];
			$maintext .= "<p style='font-size: small; color: #999999;'>TEITOK version: {$version['version']}, {$version['date']}";	

			if ( $user['permissions'] == "admin" && is_writable($gitfldr) ) {
				$maintext .= " (<a href='index.php?action=admin&act=update'>update</a>)";
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