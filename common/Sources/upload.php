<?php
	// Script to upload different files
	// Also helps to track missing and unused images
	// (c) Maarten Janssen, 2015

	check_login();
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 

	# If not defined in settings, predefine which files go where
	if ( !$settings['files'] ) {
		$settings['files'] = array (
			"facsimile" => array ( "display" => "Facsimile Images", "folder" => "Facsimile", "extension" => "*.jpg", "description" => "Facsimile images of XML pages (best done <a href='index.php?action=images'>here</a>)" ),
			"image" => array ( "display" => "Site Images", "folder" => "Images", "extension" => "*.jpg,*.png,*.gif,*.jpeg", "description" => "Image files used for the site design" ),
			"audio" => array ( "display" => "Audio files", "folder" => "Audio", "extension" => "*.wav,*.mp3", "description" => "Sound files for XML files" ),
			"xml" => array ( "display" => "TEI XML Files", "folder" => "xmlfiles", "extension" => "*.xml", "description" => "Externally generated TEI files" ),
			"psdx" => array ( "display" => "PSDX Annotation files", "folder" => "Annotations", "extension" => "*.psdx,*.psd", "description" => "PSD(X) Syntactic annotations" ),
		    "pdf" => array ( "display" => "PDF files", "folder" => "pdf", "extension" => "*.pdf", "description" => "PDF file linked within the site" ),
			// "html" => array ( "display" => "HTML Pages", "folder" => "Pages", "extension" => "*.html,*.htm", "description" => "HTML Pages created externally (best done <a href='index.php?action=pageedit'>here</a>)" ),
		);
		$nodef = 1;
	};
	
	if ( $act == "save" ) {

		$type = $_POST['type']; if ( !$type ) fatal ("POST data incorrectly set");

		$target_folder = $settings['files'][$type]['folder']; if ( !$type ) fatal ("Data folder not found");
		if ( !is_dir($target_folder) ) mkdir($target_folder); # Create the folder if needed

		$target_file = $target_folder."/".basename($_FILES["upfile"]["name"]);
		$uploadOk = 1;
		$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);

		// print_r($_POST); exit;
		if ( isset($_POST["submit"]) && $_POST['submit'] != "Save" ) {
			$dropzone = true;
			header('Content-type: application/json');
		} else {
			print "<h1>Uploading File</h1><p>$target_file";
		};
		if( isset($_POST["type"]) ) {
			if ( move_uploaded_file($_FILES["upfile"]["tmp_name"], $target_file) ) {
				if ( !$dropzone ) {
					echo "<p>The file ". basename( $_FILES["upfile"]["name"]). " has been uploaded.";
					header("location:index.php?action=$action&act=list&type=$type");
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

	} else if ( $act == "download" ) {
	
		# Check if this is not in Resources
		$filename = $_GET['file'];
		if ( strstr($filename, "Resources") ) fatal ("Not allowed to download files from Resources");
		if ( strstr($filename,"..") ) fatal ("Not allowed");
		if ( !file_exists($filename) ) fatal ("No such file: $filename");
	
		$finfo = new finfo(FILEINFO_MIME);
		header('Content-disposition: attachment; filename="'.basename($filename).'"');
        header('Content-Length: '.filesize($filename));
        header('Content-type: '.$finfo->file($filename));
		$cmd = "cat $filename";
		passthru($cmd);
		exit;
	
	} else if ( $act == "list" ) {
	
		$type = $_GET['type'];
		$typedef = $settings['files'][$type];
		$accept = str_replace('*', '', $typedef['extension']);
		$maxsize = min(intval(ini_get("upload_max_filesize")), intval(ini_get("post_max_size")), intval(ini_get("memory_limit")));

		$maintext .= "<h1>File Upload</h1>
				<h2>{$typedef['display']}</h2>";

		if ( !$settings['files']['nodropzone'] ) {
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
  				  maxsize: $maxsize,
  				  $capture
  				  init: function() {
				  	// Maybe reload the list of uploaded files?
				  }
				};
				</script>
				<div id=\"dropzone\">
				<form action=\"index.php?action=$action&act=save\" class=\"dropzone needsclick\" id=\"upload-zone\"  method=post enctype=\"multipart/form-data\">
				<input type=hidden name=type value='$type'>
				<div class=\"dz-message needsclick\">
					Drop files here or click to upload.
					<br/>Accepted files: $accept
					<br/>Maximum file size (accepted by server): $maxside Mb
				</div>				
				<div class=\"fallback\">
					<input type=file name=upfile accept=\"$accept\"> 
					<input type=submit value=Save name=submit> 
				</div>
  				</form>
				</div>
			";
			
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
					<p>Add new file: 
						<input type=file name=upfile accept=\"$accept\"> 
						<input type=submit value=Save name=submit> 
					</form> ";
			};
		};	
			
		
		$maintext .= "<hr>
				<h2>Stored Files</h2>
				<table cellpadding='5px'>
				";

		# First - read all the files
		$glob = "{$typedef['folder']}/{{$typedef['extension']}}";
		$files = glob($glob, GLOB_BRACE);
		foreach ( $files as $line ) {
			$maintext .= "<tr><td><a href='$baseurl$line' target=file>view</a> 
				<td> <a href='index.php?action=$action&act=download&file=$line' target=file>download</a> 
				<td> {$line}
				<td align=right>".human_filesize(filesize($line));
			$totsize += filesize($line); $cnt++;
		};
		$maintext .= "
			<tr><td><td><td style='border-top: 1px solid #999999; color: #666666'>$cnt files<td align=right style='border-top: 1px solid #999999; color: #666666'>".human_filesize($totsize)."
			</table>
			<hr><p><a href='index.php?action=$action'>select a different file type</a>";
		
	} else {
	
		$maintext .= "<h1>File Upload</h1>
		
			<h3>Select a type of file to upload</h3>
		
			<table>";
			
		foreach ( $settings['files'] as $key => $val ) {
			if ( !$nodef || is_dir($val['folder']) ) {
				$maintext .= "<tr><td><a href='index.php?action=$action&act=list&type=$key'>{$val['display']}</a><td>{$val['description']}";
			};
		};
	
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