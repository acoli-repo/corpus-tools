<?php
	// Script to upload different files (and delete them)
	// (c) Maarten Janssen, 2015

	check_login();
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']);


	# If not defined in settings, predefine which files go where
	if ( !$settings['files'] ) {
		$settings['files'] = array (
			"facsimile" => array ( "display" => "Facsimile Images", "folder" => "Facsimile", "extension" => "*.jpg", "description" => "Facsimile images of XML pages (best done <a href='index.php?action=images'>here</a>)" ),
			"image" => array ( "display" => "Site Images", "folder" => "Images", "extension" => "*.jpg,*.png,*.gif,*.jpeg", "description" => "Image files used for the site design" ),
			"audio" => array ( "display" => "Audio files", "folder" => "Audio", "extension" => "*.wav,*.mp3", "description" => "Sound files for XML files" ),
			"xml" => array ( "display" => "TEI XML Files", "folder" => "xmlfiles", "extension" => "*.xml", "description" => "Externally generated TEI files", "subfolders" => 1 ),
			"psdx" => array ( "display" => "PSDX Annotation files", "folder" => "Annotations", "extension" => "*.psdx,*.psd", "description" => "PSD(X) Syntactic annotations" ),
		    "pdf" => array ( "display" => "PDF files", "folder" => "pdf", "extension" => "*.pdf", "description" => "PDF file linked within the site" ),
			// "html" => array ( "display" => "HTML Pages", "folder" => "Pages", "extension" => "*.html,*.htm", "description" => "HTML Pages created externally (best done <a href='index.php?action=pageedit'>here</a>)" ),
		);
		$nodef = 1;
	};

	$type = $_POST['type'] or $type = $_GET['type']; 
	$typedef = $settings['files'][$type];

	if ( $act == "save" ) {

		if ( !$type ) fatal ("POST data incorrectly set");

		$target_folder = $settings['files'][$type]['folder']; if ( !$target_folder ) fatal ("Filetype not allowed for upload");
		
		if ( $typedef['subfolders'] && $_POST['subfolder'] )  {
			$target_folder .= "/".str_replace(".", "", $_POST['subfolder']);
		};
		check_folder($target_folder); # Create the folder if needed

		if ( $_POST["filename"] ) {
			$target_file = $target_folder."/".$_POST["filename"];
		} else  $target_file = $target_folder."/".basename($_FILES["upfile"]["name"]);
		$uploadOk = 1;
		$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
		$target_file = preg_replace("/^[.\/]+/", "", $target_file); # Protect against going outside dedicated folder

		# For subfolders, make the folder
		if ( strpos('/', $_POST["filename"]) !== null ) {
			$fullfolder = preg_replace("/\/[^\/]+$/", '/', $target_file);
			if ( !is_dir($fullfolder) ) {
				mkdir($fullfolder, 0777, true);
			};
		};

		if ( isset($_POST["submit"]) && $_POST['submit'] != "Save" ) {
			$dropzone = true;
			header('Content-type: application/json');
		} else {
			print "<h1>Uploading File</h1><p>$target_file";
		};
		if( isset($_POST["type"]) ) {
			if ( file_exists($target_file) ) {
				// Do not allow overwrite files
				if ( !$dropzone ) {
					echo "<p>The file $targe_file already exists.";
				} else {
					header("HTTP/1.0 422 File already exists"); ## Throw an error to let Dropzone know it went wrong
					print '{"error": "file already exists"}';
				};
			} if ( move_uploaded_file($_FILES["upfile"]["tmp_name"], $target_file) ) {
				if ( !$dropzone ) {
					echo "<p>The file ". basename( $_FILES["upfile"]["name"]). " has been uploaded.";
					if ( $_POST['goon'] ) {
						$newurl = $_POST['goon'];
					} else $newurl = "index.php?action=$action&act=list&type=$type";
					header("location:$newurl");
					print "<script language=Javascript>top.location='$newurl';</script>"; exit;
				} else {
					print "{\"ok\": \"file has been uploaded to $target_file\"}";
				};
			} else {
				if ( !$dropzone ) {
					echo "<p>Sorry, there was an error uploading your file.";
					if ( !is_uploaded_file($_FILES["upfile"]["tmp_name"]) ) print "<p>Error: file did not get uploaded";
					else print "<p>Error: file could not get moved to $target_file";
				} else {
					if ( !is_uploaded_file($_FILES["upfile"]["tmp_name"]) ) {
						header("HTTP/1.0 422 File move error"); ## Throw an error to let Dropzone know it went wrong
						print "{\"error\": \"file could not get moved to $target_file\"}";
					} else {
						header("HTTP/1.0 422 File upload error"); ## Throw an error to let Dropzone know it went wrong
						print '{"error": "file did not get uploaded"}';
					};
				};
			}
		} else {
			if ( !$dropzone )
				print "<p>Nothing received to upload";
			else
				header("HTTP/1.0 422 No file"); ## Throw an error to let Dropzone know it went wrong
				print '{"error": "no file received"}';
		};
		exit;

	} else if ( $act == "newfolder" ) {
		
		$name = $_POST['name'];
		$name = preg_replace("/[^A-Z0-9a-z]/", "_", $name); # Remove problematic characters from the name
		$basefolder = $typedef['folder'];
		$sf = $_GET['subfolder'];
		if ( $sf ) {
			$basefolder .= "/".$sf;
		};
		if ( $name ) {		
			if ( is_dir("$basefolder/$name") ) { fatal("Folder $basefolder/$name already exists"); };
			mkdir ("$basefolder/$name");
			print "<h1>Folder Created</h1>
				<p>Click <a href='index.php?action=$action&type=$type'>here</a> to return to list
				<script>top.location='index.php?action=$action&act=list&type=$type&subfolder=$sf'</script>";
		} else 
			$maintext .= "<h1>Create new folder</h1>
			<form class=adminpart action='index.php?action=$action&type=$type&act=$act' method=post>
			<p>Type in the path of the folder you want to create: <input name=name> <input type=submit value=Create>
			</form>		
			";
		

	} else if ( $act == "delete" ) {

		$type = $_GET['type'];
		if ( !$type ) fatal ("Filetype incorrectly set");
		$target_folder = $settings['files'][$type]['folder']; if ( !$target_folder ) fatal ("Filetype not allowed to delete");
		$deltype = $settings['files'][$type]['delete'] or $deltype = $settings['files']['delete'];
		if ( $deltype == "none" || ( $deltype == "sudo" && $user['permissions'] != "admin" ) )  fatal ("Filetype not allowed to delete");

		# Check if this is not in Resources
		$filename = $_GET['file'];
		if ( substr($filename, 0, strlen($target_folder) ) != $target_folder ) fatal( "File does not belong to the selected filetype" );
		if ( strstr($filename, "Resources") ) fatal ("Not allowed to delete file");
		if ( strstr($filename,"..") ) fatal ("Not allowed");
		if ( !file_exists($filename) ) fatal ("No such file: $filename");
		if ( !is_writable($filename) ) fatal ("Not allowed to delete file: $filename");

		if ( $_GET['confirm'] ) {
			# Now really remove the file
			$basename = preg_replace("/.*\//", "", $filename);
			check_folder("Trash");
			if ( is_writable("Trash") ) {
				rename($filename, "Trash/$basename");
				echo "<p>The file $filename has been deleted (moved to the Trash folder).";
			} else {
				unlink($filename);
				echo "<p>The file $filename has been deleted.";
			};

			header("location:index.php?action=$action&act=list&type=$type");
		} else {
			# Check for confirmation
			$confirm = "index.php?action=$action&act=$act&type=$type&confirm=1&file=$filename";
			$maintext .= "<h1>Delete File</h1>
				<p>You have selected to delete the following file: <b>$filename</b>
				<p>If this file is used in the system, this might disrupt TEITOK, are you sure you want to continue?
				<p><button onClick=\"window.open('$confirm', '_self')\">Confirm</button>";

		};

	} else if ( $act == "download" ) {

		$type = $_GET['type'];
		if ( !$type ) fatal ("Filetype incorrectly set");
		$target_folder = $settings['files'][$type]['folder']; if ( !$target_folder ) fatal ("Filetype not allowed for download");

		# Check if this is not in Resources
		$filename = $_GET['file'];
		if ( substr($filename, 0, strlen($target_folder) ) != $target_folder ) fatal( "File does not belong to the selected filetype" );
		if ( strstr($filename, "Resources") ) fatal ("Not allowed to download files from Resources");
		if ( strstr($filename, "Resources") ) fatal ("Not allowed to download file");
		if ( strstr($filename,"..") ) fatal ("Not allowed");
		if ( !file_exists($filename) ) fatal ("No such file: $filename");

		$finfo = new finfo(FILEINFO_MIME);
		header('Content-disposition: attachment; filename="'.basename($filename).'"');
        header('Content-Length: '.filesize($filename));
        header('Content-type: '.$finfo->file($filename));
		$cmd = "cat $filename";
		passthru($cmd);
		exit;

	} else if ( $act == "fill" && $typedef['display'] && $_GET['name'] ) {
	
		$filename = $_GET['name'];
	
		$maintext .= "<h1>Add Named File</h1>
			<p>File type: {$typedef['display']}
			<p>Filename: $filename
			";
		if ( $_GET['cid'] ) {
			$cid = $_GET['cid'];
			$maintext .= "
				<p>For XML: <a href='index.php?action=file&cid=$cid' target=xml>$cid</a>";
		}

		$goon = $_GET['goon'] or
			$goon = "index.php?action=$action&type=$type&act=list";

		$accept = preg_replace("/.*\./", '.', $filename);
		$maintext .= "
			<p><form action='index.php?action=upload&act=save' method=post enctype=\"multipart/form-data\">
			<input type=hidden name=type value='$type'>
			<p>Upload file:
				<input type=file name=upfile accept=\"$accept\">
				<input name=filename type=hidden value=\"$filename\">
				<input name=type type=hidden value=\"$type\">
				<input name=goon type=hidden value=\"$goon\">
				<input type=submit value=Save name=submit>
			</form> ";				
		
	} else if ( $act == "browse" ) {
	
		$maintext .= "<h1>Stored Images</h1>
				<h2>{$typedef['display']}</h2>
				<style>
					.imgbox { float: left; margin: 5px; }
				</style>
				";

		# First - read all the files
		if ( $typedef['subfolders'] ) {
			$sf = str_replace(".", "", $_GET['subfolder']);
			$sf = preg_replace("/^\//", "", $sf);
			$glob = "{$typedef['folder']}/$sf/*";
			$files = glob($glob);
			if ( $sf ) $maintext .= "<h3>Subfolder: $sf</h3>";
		} else {
			$glob = "{$typedef['folder']}/*";
			$files = glob($glob, GLOB_BRACE);
		};

		$perpage = $_GET['perpage'] or $perpage = $typedef['perpage'] or $perpage = 50;
		$height = $typedef['height'] or $height = 100;
		$start = $_GET['start'] or $start = 0;
		$toshow = array_slice($files, $start, $perpage);
		$total = count($files);
		
		if ( $total > $perpage) {
			$end = min($total, $start + $perpage);
			$maintext .= "<p>Showing ".($start+1)." - $end of $total &bull; ";
			if ( $start > 0 ) {
				$prev = max(0,$start-$perpage); 
				$maintext .= " <a href='index.php?action=$action&act=$act&type=$type&perpage=$perpage&start=$prev'>previous</a>";
			};
			if ( $end < $total ) {
				$maintext .= " <a href='index.php?action=$action&act=$act&type=$type&perpage=$perpage&start=$end'>next</a>";
			};
		};
		
		foreach ( $toshow as $fn ) {
			if ( is_dir($fn) ) {
				$foldername = preg_replace("/^{$typedef['folder']}\/*/", '', $fn);
				$folderlist .= "<p><a href='index.php?action=$action&subfolder=$foldername&act=$act&type=$type'>$foldername</a>";
			};
			if ( !is_file($fn) ) continue;
			$size = "height=\"{$height}px\"";
			$maintext .= "<div class='imgbox' title='$fn'><a href='$fn' target=preview><img src='$fn' $size></a></div>";
		};
		if ( $folderlist ) {
			$maintext .= "<hr style='clear:both;'><h3>Subfolders</h3>$folderlist";
		};
		$maintext .= "<hr style='clear:both;'><p><a href='index.php?action=$action&act=list&type=$type'>back to upload</a>";
		if ( $sf ) {
			$upfolder = preg_replace("/\/?[^\/]+$/", "", $sf);
			$maintext .= " &bull; <a href='index.php?action=$action&act=$act&type=$type&folder=$upfolder'>to parent folder</a>";
		};

	} else if ( $act == "list" && $typedef['display'] ) {

		if ( $typedef['admin'] ) check_login("admin"); 
		$accept = str_replace('*', '', $typedef['extension']);
		$acar = explode ( " ", $accept );
		$maxsize = min(intval(ini_get("upload_max_filesize")), intval(ini_get("post_max_size")), intval(ini_get("memory_limit")));

		$maintext .= "<h1>File Upload</h1>
				<h2>{$typedef['display']}</h2>";

		# First - read all the files
		if ( $typedef['subfolders'] ) {
			$sf = str_replace(".", "", $_GET['subfolder']);
			$sf = preg_replace("/^\//", "", $sf);
			$glob = "{$typedef['folder']}/$sf/*";
			$files = glob($glob);
			if ( $sf ) $maintext .= "<h3>Subfolder: $sf</h3>";
		} else {
			$glob = "{$typedef['folder']}/*";
			$files = glob($glob, GLOB_BRACE);
		};

		if ( !$settings['files']['nodropzone'] && !$_GET['nodropzone'] ) {
			// Dropzone.js

			if ( $type == "facsimile" ) {
				$capture = "capture: \"camera\",";
			} else if ( $type == "audio" ) {
				$capture = "capture: \"microphone\",";
			} else if ( $type == "video" ) {
				$capture = "capture: \"camcorder\",";
			};
			if ( $debug || $_GET['simple'] || $settings['files']['fallback'] ) { $capture .= " forceFallback: true,"; };
			$maintext .= "
				<script src=\"https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.2.0/dropzone.js\"></script>
				<style type=\"text/css\"> @import url(\"https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.2.0/dropzone.css\");</style>
				<script language=Javascript>
				Dropzone.options.uploadZone = {
  				  paramName: \"upfile\", // The name that will be used to transfer the file
  				  acceptedFiles: \"$accept\",
  				  maxFilesize: $maxsize,
 				  timeout: 0,
   				  $capture
  				  init: function() {
				  	// Throw an error - TODO: Reload file list?
					this.on(\"error\", function(file, message) { 
								alert(message);
								this.removeFile(file); 
					});
				  }
				};
				</script>
				<div id=\"dropzone\">
				<form action=\"index.php?action=$action&act=save\" class=\"dropzone needsclick\" id=\"upload-zone\"  method=post enctype=\"multipart/form-data\">
				<input type=hidden name=type value='$type'>
				<input type=hidden name=subfolder value='$sf'>
				<div class=\"dz-message needsclick\">
					Drop files here or click to upload.
					<br/>Accepted files: $accept
					<br/>Maximum file size (accepted by server): $maxsize Mb
				</div>
				<div class=\"fallback\">
					<input type=file name=upfile accept=\"$accept\">
					<input type=submit value=Save name=submit>
				</div>
  				</form>
				</div>
			";
			
			// You need to be logged in to upload
			// Add a session logout tester
			$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";

		} else {
			// Simple style


			$maxsize = min(intval(ini_get("upload_max_filesize")), intval(ini_get("post_max_size")), intval(ini_get("memory_limit")));
			if ( $maxsize < 20 ) $warning = "<i style='color: #888888;'> - ask system admistrator to increase upload_max_filesize, post_max_size, and memory_limit to upload larger files</i>";
			$maxsize .= "Mb";

			if ( !is_dir($typedef['folder']) ) {
				$maintext .= "<p style='font-weight: bold; color: #992000;'>Folder {$typedef['folder']} does not exist, please contact admin</p>";
			} else if ( !is_writable($typedef['folder']) ) {
				$maintext .= "<p style='font-weight: bold; color: #992000;'>Folder {$typedef['folder']} is not writable, please contact admin</p>";
			} else {
				$maintext .= "<p><form action='index.php?action=$action&act=save' method=post enctype=\"multipart/form-data\">
					<p>Accepted extensions: <i>{$typedef['extension']}</i>
					<p>Maximum file size: <i>$maxsize</i> $warning
					<input type=hidden name=type value='$type'>
					<input type=hidden name=subfolder value='$sf'>
					<p>Add new file:
						<input type=file name=upfile accept=\"$accept\">
						<input type=submit value=Save name=submit>
					</form> ";
			};
		};


		$maintext .= "<hr>
				<h2>Stored Files</h2>";
		if ( $typedef['type'] == "image" ) { $maintext .= "<p><a href='index.php?action=$action&act=browse&type=$type'>browse images</a>"; };
		$maintext .= "<table cellpadding='5px'>
				";

		foreach ( $files as $line ) {
			$fn = preg_replace("/.*\//", "", $line);
			$ffn = str_replace("xmlfiles/", "", $line);
			$ffn = preg_replace("/^\//", "", $ffn);
			list ( $bn, $ext ) = explode(".", $fn);
			if ( is_dir($line) ) {
				if ( $typedef['subfolders'] ) {
					if ( !$dirlist ) $dirlist .= "<h3>Subfolders</h3>";
					$dirlist .= "<p><a href='index.php?action=$action&act=list&type=$type&subfolder=$sf/$fn'>$fn</a>";
				} else {
					$maintext .= "<tr><td><td style='color: grey'>$ffn";
					$warnings = "<p class=warning>There are subfolders, while the settings do not allow for those. You should flatten the folder, or change the settings.";
				};
			} else if ( in_array($acar, ".$ext") ) {
				$maintext .= "<tr><td><td style='color: grey'>$ffn (no allowed: $ext - ".join(",", $acar).")";
			} else {
				$maintext .= "<tr><td><a href='$baseurl$line' target=file>view</a>
				<td> <a href='index.php?action=$action&act=download&type={$typedef['folder']}&file=$ffn' target=file>download</a>
				<td> {$ffn} <td align=right>".human_filesize(filesize($line));
				$deltype = $settings['files'][$type]['delete'] or $deltype = $settings['files']['delete'];
				if ( $deltype != "none" && ( $deltype != "sudo" || $user['permissions'] == "admin" ) )  $maintext .= "<td><a href='index.php?action=$action&act=delete&type=$type&file=$line'>delete</a>";
				$totsize += filesize($line); $cnt++;
			};
		};
		if ( $totsize ) 
			$maintext .= "
			<tr><td><td><td style='border-top: 1px solid #999999; color: #666666'>$cnt files<td align=right style='border-top: 1px solid #999999; color: #666666'>".human_filesize($totsize);	
		else $maintext .= "<p><i>No files</i>";
		$maintext .= "</table>$dirlist";
		$maintext .= "<hr><p><a href='index.php?action=$action'>select a different file type</a>$warnings";
		if ( $typedef['subfolders'] ) {
			$maintext .= " &bull; <a href='index.php?action=$action&act=newfolder&type=$type&subfolder=$sf'>create folder</a>";
			if ( $sf ) { 
				$nsf = preg_replace("/[^\/]+$/", "", $sf);
				$maintext .= " &bull; <a href='index.php?action=$action&act=list&type=$type&subfolder=$nsf'>leave subfolder</a>";
			};
		};
		

	} else {

		$maintext .= "<h1>File Upload</h1>

			<h3>Select a type of file to upload</h3>

			<table>";

		foreach ( $settings['files'] as $key => $val ) {
			if ( !$nodef || is_dir($val['folder']) ) {
				if ( !$val['admin'] || $user['permissions'] == "admin" ) $maintext .= "<tr><td><a href='index.php?action=$action&act=list&type=$key'>{$val['display']}</a><td>{$val['description']}";
			};
		};

		$maintext .= "</table>";
	};

	function human_filesize($bytes, $decimals = 2) {
	  $sz = 'BKMGTP';
	  $factor = floor((strlen($bytes) - 1) / 3);
	  return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
	}

	function parseint ($string) {
	  $sz = 'BKMGTP';
	  $exp = strpos($sz,substr($string,-1));
	  if ( $exp != -1 ) { return intval($string)*pow(1024, $exp); }
	  else return $string;
	};
?>
