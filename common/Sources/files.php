<?php
	// Script to allow viewing the XML file tree
	// Not typically very useful for visitors (superceded by browse)
	// (c) Maarten Janssen, 2015

	if ( $settings['defaults']['nofiles'] ) check_login();

	check_folder("xmlfiles");	
	
	if ( $act == "mkdir" && ( $_POST['name'] )  && $username ) {
		
		$path = $_POST['name'];
		$path = preg_replace("/[+ '\"]+/", "_", $path); # Remove problematic characters from the name
		
		if ( is_dir("xmlfiles/$path") ) { fatal("Folder already exists"); };
		if ( preg_match("/^(.*)\/([^\/]+)$/", $path, $matches ) ) { 
			$where = $matches[1]; 
			if ( !is_dir("xmlfiles/$where") ) { fatal ("Folder $where does not exist"); };
			$foldername = $matches[2]; 
		} else $foldername = $path;
		
		
		mkdir ("xmlfiles/$path");
		$maintext .= "<h1>Folder Created</h1>
			<p>Click <a href='index.php?action=files'>here</a> to return to file list";
		
	} else if ( $act == "mkdir" && $username ) {
		
		$maintext .= "<h1>Create new folder</h1>
			<form class=adminpart action='index.php?action=$action&act=$act' method=post>
			<p>Type in the path of the folder you want to create: <input name=name> <input type=submit value=Create>
			</form>		
			";
	
	} else if ( $act == "mv" && $_POST['target'] && $username ) {
		
		$id = $_POST['id'];
		$path = $_POST['target'];
		if ( !$id ) { fatal("No file selected"); };
		if ( !$path ) { fatal("No destination selected"); };
		if ( $id == $path ) { fatal("Filename identical"); };

		if ( !file_exists("xmlfiles/$id") ) { fatal("File $id does not exists"); };
		if ( preg_match("/^(.*)\/([^\/]+\.xml)$/", $path, $matches ) ) { 
			$where = $matches[1]; 
			if ( !is_dir("xmlfiles/$where") && $where != "[Trash]" ) { fatal ("Folder $where does not exist"); };
			$oldname = $matches[2]; 
		} else $oldname = $id;
		
		if ( file_exists("xmlfiles/$path") && substr($path,-4) == ".xml" && $where != "[Trash]" ) { fatal("File already exists"); };
		
		if ( preg_match("/^\[Trash\]\/([^\/]+\.xml)$/", $path, $matches )  ) {
			$path = $matches[1];
			if ( !is_dir("Trash") ) { mkdir("Trash"); };
			if ( !is_dir("Trash") ) fatal("Trash folder could not be created, please contact admin"); 
			if ( !is_writable("Trash") ) fatal("Trash folder not writable, please contact admin"); 
			rename ("xmlfiles/$id", "Trash/$path"); 
		} else if ( preg_match("/^(.*)\/([^\/]+\.xml)$/", $path, $matches ) ) { 
			$where = $matches[1]; 
			if ( !is_dir("xmlfiles/$where") ) { fatal ("Folder $where does not exist"); };
			$newname = $matches[2]; 
			rename ("xmlfiles/$id", "xmlfiles/$path"); 
		} else if ( preg_match("/^([^\/]+\.xml)$/", $path, $matches ) ) { 
			$newname = $path; 
			rename ("xmlfiles/$id", "xmlfiles/$path"); 
		} else if ( preg_match("/^(.*)\/([^\/]+)$/", $path, $matches ) ) { 
			$where = $path; 
			if ( !is_dir("xmlfiles/$where") ) { fatal ("Folder $where does not exist"); };
			rename ("xmlfiles/$id", "xmlfiles/$path/$oldname");
		} else { 
			# uhm - what to to?
		};
		
		if ( $newname && $newname != $oldname ) {
			# The name of the file changed - make changes to backups and text/@id accordingly...
		};
		
		$maintext .= "<h1>File renamed</h1>
			<p>Click <a href='index.php?action=files'>here</a> to return to file list";
		
		
	} else if ( $act == "mv" && $_GET['id'] && $username ) {
		
		$id = $_GET['id'];
		$maintext .= "<h1>Move file</h1>
			<p>Indicate the full path of the folder where you want to move this file, or type in the
				full path of the new name for this file
			<form action='index.php?action=$action&act=$act' method=post id=pst name=pst>
			<table>
				<tr><td>Old filename:<td>$id <input type=hidden name=id value='$id'>
				<tr><td>New filename:<td><input name=target value='$id' size=60> 
			</table>
				<input type=submit value=Rename>
			</form>		
			<script language=Javascript>
				function addfolder (name) {
					var trg = document.pst.target.value;
					document.pst.target.value = name + '/' + trg.replace(/.*\//, '');
				};
			</script>
			";
			
			if ( preg_match("/^(.*)\/([^\/]+)$/", $id, $matches ) ) {
				$dir = $matches[1];
			} else {
				$dir = "xmlfiles";
			};
			
			# List any existing folders
			$fldr = scandir($dir); 
			$folderlist .= "<p><span onClick=\"addfolder('[Trash]');\">[Trash]</span>";
			foreach($fldr as $value)  { 
 				if ( is_dir("$dir/$value") && substr($value,0,1) != "." ) {
 					$tomove = preg_replace("/^(xmlfiles)?\/?/", "", "$dir/$value");
					$folderlist .= "<p><span onClick=\"addfolder('$tomove');\">$value</span>";
				};
			};
			if ( $folderlist ) {
				$maintext .= "<h2>Select folder to move to</h2>
					$folderlist";
			};
			
	} else {
		// If nothing else, just list the XML files (in a given subfolder)
		$maintext .= "<h1>{%List of XML files}</h1>"; $cnt = 0;
	
		if ( $username ) 
		$maintext .= "
			<div class='adminpart'>
			<form class=adminpart style='padding: 4px;' action='index.php?action=file' method=post>
			<p>Give the ID of the XML File to open: <input name=id> <input type=submit value=Open>
			</form>
			<p>Or select a file from the list below:
			</div>
			";
	
		$recf = 1;
	
		if ( $_GET['folder'] ) {
			$subf = $_GET['folder'];
			$subftxt = preg_replace ( "/^".preg_quote($xmlfolder, '/')."\//", "", $subf );
			if ( strstr($subftxt,'/') ) {
				$upftxt = preg_replace("/\/[^\/]+$/", "", $subf);
				$upftxt = "&folder=$upftxt";
			};
			$subf2 = $subf; $subf .= '/';
			 $subftxt2 = $subftxt.'/';
			$maintext .= "<h2>{%$subftxt}</h2><p><a href='index.php?action=files$upftxt'>..</a>";
			$fld = "&fld=$subftxt";
		} else {
			$subf = "$xmlfolder/";
			$subf2 = $xmlfolder;
		};
			
		$dirfiles = scandir( $subf2 );
		foreach ( $dirfiles as $file ) {
			if( substr($file,0,1) === '.' ) {continue;} 
			$file = preg_replace ( "/^".preg_quote($xmlfolder, '/')."\//", "", $file );
			$filelink = urlencode($file);
			if ( substr($file, -4) == ".xml" ) { 
				if ( $username ) {
					$editlink = "<div style='display: inline-block; padding: 4px;' class=adminpart><a href='index.php?action=$action&act=mv&id=$subftxt2$file'>rename</a></div> ";
				};
				$filelist .= "<p>$editlink<a href='index.php?action=file&id=$subftxt2$filelink'>$file</a>"; 
				$cnt++;
			} else if ( is_dir($subf.$file) ) {
				$dirlist .= "<p><a href='index.php?action=files&folder=$subf$filelink'><b>{%$file}</b></a>";
			} else {
				// What to do with non-XML files?
			};
		};
		$maintext .= "$dirlist";
		if ( $dirlist && $filelist ) $maintext .= "<hr>";
		$maintext .= "$filelist<hr>$cnt files$showing";
		if ( !$filelist && !$dirlist ) $maintext .= "<p><i>There are no XML files in this folder (yet)</i>";
		if ($username) $maintext .= " &bull; <a href='index.php?action=$action&act=mkdir'>create new folder</a>";
		if ($username) $maintext .= " &bull; <a href='index.php?action=create'>create new XML file</a>";
	};
		
	function find_all_files($dir, $type, $opt) 
	{  
		$root = scandir($dir, SCANDIR_SORT_ASCENDING); 
		foreach($root as $value) 
		{ 
			if($value === '.' || $value === '..') {continue;} 
			if(is_file("$dir/$value")) {
				if(substr($value, -strlen($type)-1) != ".$type") { continue; } 
				$result[]="$dir/$value";continue;
			} 
			if ($opt != "R") 
			foreach(find_all_files("$dir/$value", "$type", $opt) as $value) 
			{ 
				$result[]=$value; 
			} 
		} 
		return $result; 
	} 

?>