<?php

	check_login();

	if ( $act == "recqp" ) {
	
		# No longer here - now just under recqp.php 

	} else if ( $act == "configcheck" ) {

		// Check whether TEITOK configuration is safe and working
		$maintext .= "<h1>Configuration Check</h1>
		
		<p>Below is a list of checks to see whether your TEITOK project is installed properly, and whether
		the set-up is secure.
		<hr>
			
		<style>
			.wrong { color: #aa2000; } .wrong::before { content:'✘ ' }
			.warn { color: #aa8800; } .warn::before { content:'✣ ' }
			.right { color: #209900; } .right::before { content:'✔ ' }
		</style>";
		
		// Check project folder permissions
		$writefolders = array ( "xmlfiles" => "Modify XML files", 
			"Resources" => "Change settings", 
			"backups" => "Make XML backups"
			);	
		foreach ( $writefolders as $fldr => $reason ) {
			if ( !is_writable($fldr) ) {
				$maintext .= "<p class=wrong> The folder $fldr/ should be writable for Apache, reason: $reason";
				$foldererrors = 1;
			};
		}; 
		if ( !$foldererrors ) $maintext .= "<p class=right> All folders TEITOK needs to write to are writable";

		// Check project folder permissions of common 
		if ( is_writable("$ttroot/common") ) {
			$maintext .= "<p class=warn> The common folder of TEITOK had best not be writable";
		};
		

	} else if ( $act == "serverdata" ) {

		$maintext .= "<h1>_SERVER</h1>";
		$maintext .= "<pre>".print_r($_SERVER, 1)."</pre>";

	} else if ( $act == "shorthand" ) {

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
		
	} else {
	
		$grouprec = $settings['permissions']['groups'][$user['group']];
		$adminmenulist = array (
				"pageedit" => "edit HTML files",
				"i18n" => "edit internationalization",
				"upload" => "upload files",
				"csv" => "batch-edit XML using CSV",
			);
			
		if ( $grouprec['actions'] ) $grouptxt = "of your group ({$user['group']})";
		$maintext .= "<h1>Admin Functions</h1>
			
			<p>These are the options available for editors of the corpus $grouptxt

			<ul>";
		
		foreach ( $adminmenulist as $key => $val ) {
			if ( allowedforme($key) ) {
				$maintext .= "			<li><a href='index.php?action=$key'>$val</a>";
			};
		};
			
		if ( allowedforme("create") ) {
			if ( $settings['xmltemplates'] || file_exists("Resources/xmltemplate.xml" ) )
				$maintext .= "<li><a href='index.php?action=create'>create new XML from template</a>";
			else 
				$maintext .= "<li><a href='index.php?action=create'>create new XML file</a>";
		};
				
		if ( $settings['cqp']['corpus'] && allowedforme("recqp") ) {
			$maintext .= "<li><a href='index.php?action=recqp'>(re)generate the CQP corpus</a> (or only 
				<a href='index.php?action=recqp&check=1'>check</a> the status)";
			# if ( $user['permissions'] == "admin" ) { 
			#	$maintext .= "<ul><li>  <a href='index.php?action=recqp&force=1'>regenerate</a> the script to regenerate the CQP corpus (after changing CQP settings)</ul>";
			# };
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
				
		if ( file_exists("Facsimile" ) && allowedforme("images") )
			$maintext .= "<li><a href='index.php?action=images&act=check'>check facsimile images</a>";

		if ( is_array($settings['menu']['admin']) )
		foreach ( $settings['menu']['admin'] as $key => $item ) { 	
			$link = "{$tlpr}index.php?action=$key";
			if ( allowedforme($key) )
				$maintext .= "<li><a href='$link'>{%".$item['display']."}</a>";
		};
				
		$maintext .= "</ul>
		
			<hr>
			
			<p>For help on admin functions see the <a href='http://teitok.corpuswiki.org/site/index.php?action=help'>Help</a> section online.
			";
	
		if ( file_exists("$ttroot/common/Resources/version.xml") ) {
			$tmp = simplexml_load_file("$ttroot/common/Resources/version.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);	
			$version = $tmp[0];
			$maintext .= "<p style='font-size: small; color: #999999;'>TEITOK version: {$version['version']}, {$version['date']}";	
		};
	
		# $maintext .= "<p>Screen type detected: ".screentype();

	};
		
	function allowedforme ( $checkaction ) {
		global $user, $settings, $publicactions;
		$grouprec = $settings['permissions']['groups'][$user['group']];
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