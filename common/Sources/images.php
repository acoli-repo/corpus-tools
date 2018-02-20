<?php
	// Script to view and upload Facimile images
	// Also helps to track missing and unused images
	// (c) Maarten Janssen, 2015

	check_login();
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 
	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'];
	
	if ( $act == "save" ) {

		$target_dir = "Facsimile/";
		$target_file = $target_dir . basename($_FILES["facs"]["name"]);
		print "<h1>Uploading File</h1><p>$target_file";
		$uploadOk = 1;
		$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
		// Check if image file is a actual image or fake image
		if(isset($_POST["submit"])) {
			$check = getimagesize($_FILES["facs"]["tmp_name"]);
			if($check !== false) {
				echo "<p>File is an image - " . $check["mime"] . ".";
				$uploadOk = 1;
			} else {
				echo "<p>File is not an image but ". $check["mime"];
				$uploadOk = 0;
			}
			// Check if $uploadOk is set to 0 by an error
			if ($uploadOk == 0) {
				echo "<p>Sorry, your file was not uploaded.";
			// if everything is ok, try to upload file
			} else {
				if (move_uploaded_file($_FILES["facs"]["tmp_name"], $target_file)) {
					echo "<p>The file ". basename( $_FILES["facs"]["name"]). " has been uploaded.";
					header("location:index.php?action=images&act=list");
				} else {
					echo "<p>Sorry, there was an error uploading your file.";
				}
			}
		} else { print "<p>Nothing received to upload";};
		exit;
	} else if ( $act == "list" ) {

		$maintext .= "<h1>Stored Facsimile images</h1>
		
			<p><form action='index.php?action=$action&act=save' method=post enctype=\"multipart/form-data\">
				Add new image: <input type=file name=facs> <input type=submit value=Save name=submit> 
				</form> 
				<br>
				<span style='font-size: small; color: #006600;'>suggested image filename: Filename-10v.jpg - where Filename is the name of Filename.xml and 10v is the page number
				<br>
				To insert an image into a &lt;pb&gt; just copy the desired filename from the list below
				</span>
				<hr>
				";

		# First - read all the images
		$cmd = "/bin/grep -H '<pb' $thisdir/xmlfiles/*.xml $thisdir/xmlfiles/*/*.xml $thisdir/xmlfiles/*/*/*.xml ";
		$results = shell_exec($cmd);
		foreach ( explode ( "\n", $results ) as $line ) {
			if ( preg_match ( "/^(.*?):.*?<pb[^>]+facs=\"(.*?)\"/", $line, $matches ) ) {
				$img = $matches[2]; $xmlfile = $matches[1]; $xmlfile = preg_replace("/.*xmlfiles\//", "", $xmlfile);
				if ( $usedin[$img] ) $usedin[$img] .= ";".$xmlfile;
				else $usedin[$img] = $xmlfile;
			};
		};
		
		$maintext .= "<table>
			<tr><th>Image file<th>Used in XML file";
		foreach ( scandir("Facsimile") as $imgfile ) {
			if (substr($imgfile,0,1) != '.') 
				$maintext .= "<tr><td><a href='Facsimile/$imgfile' target=img>$imgfile</a><td>{$usedin[$imgfile]}";
		};
		$maintext .= "</table>";
		

	} else if ( $act == "check" ) {

		$maintext .= "<h1>Facsimile Consistency check</h1>
			<p><a href='index.php?action=$action&act=list'>List all facsimile images</a>";
	
	
		# Check if all the images used in pb are in the Facsimile
		$maintext .= "<h2>Facsimile images that are not in the Facsimile folder</h2>"; $cnt = 0;
		$cmd = "/bin/grep -H '<pb' $thisdir/xmlfiles/*.xml $thisdir/xmlfiles/*/*.xml $thisdir/xmlfiles/*/*/*.xml ";
		$results = shell_exec($cmd);
		foreach ( explode ( "\n", $results ) as $line ) {
			if ( preg_match ( "/^(.*?):.*?<pb[^>]+facs=\"(.*?)\"/", $line, $matches ) ) {
				$img = $matches[2]; $xmlfile = $matches[1];
				$xmlfile = preg_replace ( "/.*\/xmlfiles\//", "", $xmlfile);
				$xmlid = preg_replace ( "/.*\//", "", $xmlfile);
				$imgfls[$img] = 1;
				if ( !file_exists("Facsimile/$img") ) {
					$maintext .= "<p><b>$img</b> in <a href='index.php?action=file&cid=$xmlfile'>$xmlfile</a>";
					$cnt++;
				} else if ( $debug ) {
					$maintext .= "<p><a href='Facsimile/$img'>$img</a> in <a href='index.php?action=file&cid=$xmlfile'>$xmlfile</a>";
				};
			};
		};
		$maintext .= "<p>$cnt missing image files";		

		$maintext .= "<h2>Facsimile images that are not in a &lt;pb&gt; in any XML file</h2>"; $cnt = 0;
		foreach ( scandir("Facsimile") as $imgfile ) {
			if ( exif_imagetype("Facsimile/$imgfile") && !$imgfls[$imgfile] ) {
				$maintext .= "<p><a href='Facsimile/$imgfile'>$imgfile</a>";
				$cnt++;
			};
		};
		$maintext .= "<p>$cnt unused image files";		

	} else if ( $fileid ) {
	
		$xml = getxmlfile($fileid);
		if ( !$xml ) fatal("Error loading file");
				
		$maintext .= "<h2>Pages and Facsimile</h2>
			<table><tr><th>Page number<th>Image";
		foreach ( $xml->xpath("//pb") as $n => $pbel ) { 
			$num = $pbel['n'] or $num = "<i>$n</i>";
			$facs = $pbel['facs'];
			if ( $facs ) {	
				if ( file_exists("Facsimile/$facs") ) 
					$facs = "<a href='Facsimile/$facs'>$facs</a>";
				else if ( file_exists("Facsimile/$facs") ) 
					$facs = "<a href='Facsimile/$facs'>$facs</a>";
				else 
					$facs = "<b>$facs</b><td><a href='index.php?action=$action&act=add&fn=$facs&cid=$fileid'>add</a>";
			} else $facs = "(none)";
			$maintext .= "<tr><td>$num<td>$facs";
		};
		$maintext .= "</table>";

	
	} else {
	
	
	};

?>