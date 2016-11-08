<?php
	// Script to allow editing files in Resources
	// Restricted to staff
	// and for certain files to admin only
	// (c) Maarten Janssen, 2015

	check_login();
	$id = $_GET['id'];

	$filedescs = array (
		"htmlstyles.css" => "CSS definitions for the overall site layout",
		"xmlstyles.css" => "CSS definitions for the actual text in the XML files",
		"teiHeader.tpl" => "Header info - short style",
		"teiHeader-long.tpl" => "Header info - long style",
		"settings.xml" => "Overall TEITOK settings",
		"userlist.xml" => "File with user permissions",
		"tagset.xml" => "Definition of the tagset",
		"filelist.xml" => "Metadata file (can be too large to edit)",
		// deprecated
		// "verticalize.xslt" => "XSLT for creating the CQP corpus",
		// "neotag.xslt" => "XSLT for creating verticalized input for the NeoTag POS tagger",
		// "recqp.pl" => "(Automatically created) perl script to regenerate the CQP corpus",
		);
		
	$reserved['filelist.xml'] = 1; # There is an editor for the file list
	if ( $user['permissions'] != "admin" ) {
		$reserved['userlist.xml'] = 1;
		$reserved['settings.xml'] = 1;
	};
		
	if ( $act == "save" ) {
		
		$id = $_POST['id'];
		if ( $reserved[$id] && $user['permissions'] != "admin" ) { fatal("Not allow - superuser only"); };
		if ( !$id ) {
			$id = $_POST['newid'];
			if ( !$id ) fatal ( "No filename given" );
			$pagename = substr($id, 0,-5);
			if ( file_exists("Pages/$id") )  fatal ( "File $id already exists" );
		};
		
		# If this is an XML file, check first whether it is valid XML
		if ( preg_match("/\.xml/", $id) ) {
			$xml = simplexml_load_string($_POST['rawxml'], NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { 
				fatal ( "File seems no longer to be valid XML - cowardly refusing to save" ); 
				$badfile = 1;
			};		
		};
		
		if ( !$badfile ) { 	
		
			# Save a backup copy
			$date = date("Ymd"); 
			$buname = preg_replace ( "/\.xml/", "-$date.xml", $id );
			$buname = preg_replace ( "/.*\//", "", $buname );
			if ( !file_exists("backups/$buname") ) {
				copy ( "Resources/$id", "backups/$buname");
			};
		
			file_put_contents("Resources/$id", $_POST['rawxml']);
			
			# Now save the actual file
			print "<p>File saved. Reloading.
				<script language=Javascript>top.location='index.php?action=$action';</script>
				";

		};
		
	} else if ( $id ) {
		
		if ( $id == "new" ) {
			$content = "";
			$maintext .= "<h1>Create Resource File</h1>";
			$id = "new.txt";
			$idfield = "<p>Filename: <input name=newid value='' size=40>";
		} else {
			if ( file_exists("Resources/$id") ) {
				if ( !is_writable("Resources/$id") ) {
					fatal ("Due to file permissions, the file $id cannot be edited, please contact the server administrator");
				};
				$content = file_get_contents("Resources/$id");
			} else {
				$warning .= "<p>This file does not yet exist, using a default version of the file...";
				$content = file_get_contents("../common/Resources/$id"); # Read the dummy variant of this file (if any)
			};
			$maintext .= "<h1>Edit Resource file</h1>
			<h2>Filename: $id</h2>$warning";
			
			$idfield = "<input type=hidden name=id value='$id'>";
		};
		
		if ( $reserved[$id] && $user['permissions'] != "admin" ) { fatal("Not allow - superuser only"); };

		$protcontent = htmlentities($content, ENT_QUOTES, 'UTF-8');
			
		if ( preg_match("/.*\.(.*?)$/", $id, $matches ) ) {
			$filetype = $matches[1]; 
		}; 
		# XSLT is a type of XML
		if ( $filetype == "xslt" ) $filetype = "xml";
		# TPL (here) is a type of XML
		if ( $filetype == "tpl" ) $filetype = "html";
		
		if ( $filetype == "txt" ) $filetype = "plain_text";
		if ( $filetype == "tab" ) { $filetype = "plain_text"; $settabs = "editor.getSession().setTabSize(25); editor.getSession().setUseSoftTabs(false); ";  };
		if ( $filetype == "pl" ) $filetype = "perl";
			
		if ( $filetype == "html" ) $protcontent = preg_replace( "/%/", "&#37;", $protcontent );
			
		$maintext .= "
			<div id=\"editor\" style='width: 100%; height: 400px; color: white;'>".$protcontent."</div>

			<form action=\"index.php?action=$action&act=save\" id=frm name=frm method=post>
			$idfield
			<textarea style='display:none' name=rawxml></textarea>
			<p><input type=button value=Save onClick=\"runsubmit();\"> $switch
			</form>

			<script src=\"$jsurl/ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
			<script>
				var editor = ace.edit(\"editor\");
				editor.setTheme(\"ace/theme/chrome\");
				editor.getSession().setMode(\"ace/mode/$filetype\");
				$settabs
			
				function runsubmit ( ) {
					document.frm.rawxml.value = editor.getSession().getValue();
					document.frm.submit();
				};
				document.getElementById('editor').style['color'] = '#000000';
			</script>

			";
		
		
	} else {
		$maintext .= "<h1>Settings Files</h1>
			<p>Here you can edit many of the \"system\" files of TEITOK, that make the system tick.</p>

			<hr><table>
			<tr><th>Filename<th>File Description
			";
			
		$files = scandir("Resources");
		sort($files);
		
		foreach ( $files as $entry ) {
			if ( preg_match("/i18n_(.*?)\.txt/", $entry, $matches) ) { $filedescs[$entry] = "Internationalization file for {$matches[1]}"; };
			if ( preg_match("/xmltemplate-(.*?)\.xml/", $entry, $matches) ) { $filedescs[$entry] = "XML template for new XML files of type <i>{$matches[1]}</i>"; };
			$desc = $filedescs[$entry] or $desc = "<i>Custom file</i>";
			if (substr($entry, 0,1) != "." && !$reserved[$entry] ) $maintext .= "<tr><td><a href='index.php?action=$action&id=$entry'>$entry</a><td>$desc";
		
			$done[$entry] = 1;
		};
		$maintext .= "</table>";
		
		$maintext .= "<h2>Create new file</h2>
			<p>Below is a list of all the core TEITOK settings files that are not currently used. 
			Not all of them will be relevant for the current project.
			<hr><table>
			<tr><th>Filename<th>File Description
			";
		# Now list the non-used files to be created
		foreach ( $filedescs as $key => $val ) {
			if ( !$done[$key]  && !$reserved[$key] ) {
				$maintext .= "<tr><td><a href='index.php?action=$action&id=$key'>$key</a><td>$val";
			};
		};
		$maintext .= "</table>";
		
	};
	
?>