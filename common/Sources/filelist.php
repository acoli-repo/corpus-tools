<?php
	// Script to allow viewing and editing the file repository
	// which is a centralized metadata file keeping track of changes in files
	// that should not be stored in the XML
	// Also helps to locate missing or duplicated XML files
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fid = $_POST['id'] or $fid = $_GET['id'];
	libxml_use_internal_errors(true);

	$file = file_get_contents("Resources/filelist.xml"); 
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) { 
		print "Failing to read/parse filelist<hr>"; 
		foreach(libxml_get_errors() as $error) {
			print "<p>&nbsp; &nbsp; ". $error->message;
		}
		print $file; exit; 
	};

	$flfields = $settings['filelist']['fields'];

	if ( $act == "edit" ) {
			
		$result = $xml->xpath("//file[@id='$fid']"); 
		$frec = $result[0]; # print_r($token); exit;
		if ( !$frec && $fid != "new" ) { print "<p>Error: no such file ($fid)"; exit; };
		
		$maintext .= "<h1>XML File Repository</h1>";
		
		if ( $fid == "new" ) $maintext .= "<h2>New record</h2>";
		else $maintext .= "<h2>Edit {$frec['id']}</h2>";
		
		$maintext .= "
			<form action='index.php?action=$action&act=save' method=post>
				<input type=hidden name=id value='$fid' >
				<table>";
		if ( $fid == "new" ) $maintext .= "<p>File ID: <input name='newid' size=20 value='{$_GET['newid']}'>";
		foreach ( $flfields as $key => $item ) {
			$fval = $frec->$key;
			if ( $key == "notes" ) 
				$maintext .= "<tr><th>{$item['display']}<td><textarea name='vals[$key]' cols=58 rows=5>$fval</textarea>";
			else 
				$maintext .= "<tr><th>{$item['display']}<td><input name='vals[$key]' size=80 value='$fval'>";
		};
		foreach ( $frec as $key => $val ) {
			if ( !$flfields[$key] ) $maintext .= "<tr><th>$key<td><input size=80 name='vals[$key]' value='$val'>";
		};
		$maintext .= "</table><p><input type=submit value=Save></form>";
		
	} else if ( $act == "multisave" ) {

		foreach ( explode(',', $_POST['showfields']) as $edited ) {
			foreach ( $_POST[$edited] as $key => $val ) {
				print "<p>$key => $val";
				$result = $xml->xpath("//file[@id='$key']"); 
				$frec = $result[0]; 
				if ( $frec ) { 
					$frec->$edited = $val;
					$tmp = $frec->asXML(); 
					print htmlentities($frec); 
				} else {
					print "-- not found";
				};
			};
		};

		$fxml = $xml->asXML();

		// remove empty nodes
		$fxml = preg_replace( "/<[a-z]+><\/[a-z]+>/", "", $fxml );
		$fxml = preg_replace( "/></", ">\r\t<", $fxml );
		$fxml = preg_replace( "/\t(<\/?(file[> ]|filelist))/", "\\1", $fxml );
		file_put_contents("Resources/filelist.xml", $fxml);
		$maintext .= "<hr>Saved - reloading <script language=Javascript>top.location='index.php?action=$action&show={$_GET['showfields']}';</script>";
		
	} else if ( $act == "save" ) {
		
		if ( $fid == "new" ) {
			if ( !$_POST['newid'] ) { print "No File ID given"; exit; };
			$frec = $xml->addChild("file");
			$frec->addAttribute('id', $_POST['newid']);
			$fid = $_POST['newid']; # Set the new id so that we continue correctly
		} else {
			$result = $xml->xpath("//file[@id='$fid']"); 
			$frec = $result[0]; # print_r($token); exit;
			if ( !$frec ) { print "<p>Error: no such file ($fid)"; exit; };
		};
				
		$maintext .= "<h2>Saving {$frec['id']}</h2>";
	
			
		foreach ( $_POST['vals'] as $key => $val ) {
			$maintext .= "<p>$key => $val";
			$frec->$key = $val;
		};

	
		$fxml = $xml->asXML();
		$nxml = $frec->asXML(); 
		$nxml = preg_replace( "/<[a-z]+><\/[a-z]+>/", "", $nxml );
		$nxml = htmlentities($nxml);

		// remove empty nodes
		$fxml = preg_replace( "/<[a-z]+><\/[a-z]+>/", "", $fxml );
		$fxml = preg_replace( "/></", ">\r\t<", $fxml );
		$fxml = preg_replace( "/\t(<\/?(file[> ]|filelist))/", "\\1", $fxml );
		file_put_contents("Resources/filelist.xml", $fxml);
	
		$maintext .= "<hr><pre>$nxml</pre>";
		$maintext .= "<hr>Saved - reloading <script language=Javascript>top.location='index.php?action=$action&id=$fid';</script>";
			
	} else if ( $fid ) {
	
		
		$result = $xml->xpath("//file[@id='$fid']"); 
		$frec = $result[0]; # print_r($token); exit;
		if ( !$frec ) { print "<p>Error: no such file ($fid)"; exit; };
			$fxml = $frec->asXML();
		$fxmltxt = htmlentities($fxml);
		
		$maintext .= "<h1>XML File Repository</h1><h2>{$frec['id']}</h2>
		<table>";
		foreach ( $frec as $showf => $val ) {
			$showh = $flfields[$showf]['display'] or $showh = $showf;

			$maintext .= "<tr><th>$showh<td>$val";
		};
		$maintext .= "</table>";
		$maintext .= "<p><a href='index.php?action=$action&act=edit&id={$fid}'>edit file data</a>";
	
		// Now look for the actual file
		$fnd = array_merge(glob("xmlfiles/$fid.xml"), glob("xmlfiles/*/$fid.xml"), glob("xmlfiles/*/*/$fid.xml"), glob("xmlfiles/*/*/*/$fid.xml"));

		if (count($fnd) == 0) {
			$maintext .= "<hr><p style='color: #992000'>WARNING: file not found";
		} else if (count($fnd) > 1) {
			$maintext .= "<hr><p style='color: #992000'>WARNING: duplicated file";
			foreach ( $fnd as $ff ) {
				$ff = preg_replace ( "/^Resources\/xmlfiles\//", "", $ff );
				$maintext .= "<p><a href='index.php?action=file&id=$ff'>$ff</a>";
			};
		
		} else {
			$maintext .= "<hr><p>Current location: ";
			$fff = $fnd[0];
			$ff = preg_replace ( "/^Resources\/xmlfiles\//", "", $fff );
			$maintext .= "<p><a href='index.php?action=file&id=$ff'>$ff</a>";

			// Now that we have one file, go check for tokenization, etc.
			$file = file_get_contents("$fff"); 
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };
			
			$maintext .= "<h2>Status check</h2><table>";

			$maintext .= "<tr><th>Tokenization";
			if ( preg_match ( "/<tok/", $file ) ) { 
				$maintext .= "<td>Yes";
			} else {
				$maintext .= "<td>No";
			};

			$maintext .= "<tr><th>Modernization";
			if ( preg_match ( "/ nform=/", $file ) ) { 
				$maintext .= "<td>Yes";
			} else {
				$maintext .= "<td>No";
			};

			$maintext .= "<tr><th>POS Tagging";
			if ( preg_match ( "/ (msd|pos)=/", $file ) ) { 
				$maintext .= "<td>Yes";
			} else {
				$maintext .= "<td>No";
			};

			$maintext .= "<tr><th>Lemmatization";
			if ( preg_match ( "/ (lemma)=/", $file ) ) { 
				$maintext .= "<td>Yes";
			} else {
				$maintext .= "<td>No";
			};

			$maintext .= "</table>";

		};
		
		$maintext .= "<hr><p><a href='index.php?action=$action'>Back to list</a>";

		
	} else {
	
		$tablearray = array (); 
		$query = $_GET['query']; 
		$showfields = explode( ',', $_GET['show']); $editmode = $_GET['edit'];
		$max = $_GET['max']; if (!$max) if ( $editmode ) $max = 100; else $max = 1000;
		$order = $_GET['order'] or $order = $showfields[0] or $order = "@id";
		if ( $_POST['editfield'] ) { $editmode = 1; $editfld = $_POST['editfield']; $showfields = array ( $editfld ); } else $editfld = $showfields[0];
		
		$cnt = $dcnt = $mcnt = 0;
		foreach ( $xml->file as $frec ) {
			$morefields = "";
			$fid = $frec['id']; 

			if ( $query && !preg_match("/$query/", $frec->$order) ) { 
				continue; // Non-matching record
			};

			if ( $cnt++ > $max ) { $maxed = 1; continue; };
			$fnd = array_merge(glob("xmlfiles/$fid.xml"), glob("xmlfiles/*/$fid.xml"), glob("xmlfiles/*/*/$fid.xml"), glob("xmlfiles/*/*/*/$fid.xml"));

			if (count($fnd) == 0) {
				$fname = "<span style='color: #992200; font-weight: bold;'>file not found</a>";
				$mcnt++;
			} else if (count($fnd) > 1) {
				$fname = "<span style='color: #995500'>".join("<br>", $fnd);
				$dcnt++;
			} else {
				$fname = $fnd[0];
			}; $fname = preg_replace ("#xmlfiles/#", "", $fname);
			
			foreach ( $showfields as $showf ) {
				if ( $showf ) {
					$showr = $frec->$showf;
					if ( $editmode ) {
						if ( !preg_match("/[^A-Za-z0-9]/", $fid) ) $morefields .= "<td><input name='{$showf}[$fid]' size=100 value=\"{$showr[0]}\">";
						else $morefields .= "<td><i>Illegal record ID</i>";
					} else if ( $showf ) $morefields .= "<td>".$showr[0];
				};
			};
			
			if ( substr($order,0,1) == "@" ) $orderf = $frec[substr($order, 1)];
			else $orderf = $frec->$order; 
			$orderf = trim($orderf);
			if ( $orderf == "" ) $orderf = "ªª";
			
			array_push( $tablearray, "<tr label='$orderf'><td><a href='index.php?action=$action&id=$fid'>$fid</a><td>$fname$morefields" ); # .;
		};
		sort($tablearray);
		$tablerows = join ( "\n", $tablearray );
		foreach ( $showfields as $showf ) {
			$showh = $flfields[$showf]['display'] or $showh = $showf;
			if ( $showf ) $morefields = "<th>".$showh;
		};

		foreach ( $flfields as $key => $item ) $qfields .= "<option value='$key'>{$item['display']}</option>";
		if ( $query ) $qtxt = "<p>Search query: <b>{$flfields[$order]['display']}</b> contains <b>$query</b> (<a href='index.php?action=$action'>new query</a>)";
		else $qtxt = "<p><form action=''><input name=action type=hidden value='$action'><select name=show>$qfields</select> contains <input name=query size=40> <input type=submit value='Search'></form></p>";
		
		if ( $editmode )
			$maintext .= "<h1>XML File Repository - Edit <i>{$flfields[$showfields[0]]['display']}</i></h1>
				$qtxt
				<form action='index.php?action=$action&act=multisave' method=post>
				<input type=hidden name=showfields value='{$_GET['show']}'>
				<table>
				<tr><th>FileID<th>Location$morefields
				$tablerows
				</table>
				<p><input type=submit value=Save>
				</form>
				";					
		else 
			$maintext .= "<h1>XML File Repository</h1>
				$qtxt
				<table>
				<tr><th>FileID<th>Location$morefields
				$tablerows
				</table>";

		if ( $maxed ) $maintext .= "<p style='color: red'>Maximum number of rows reached ($max) - please restrict listing";
			
		$maintext .= "<p>$cnt files - $mcnt missing - $dcnt duplicates &bull; <a href='index.php?action=$action&act=edit&id=new'>add new file record</a>";
		if ( $query && !$editmode ) $maintext .= "<p><form action='{$_SERVER['REQUEST_URI']}' method=post>Edit this selection for: <select name=editfield>$qfields</select> <input type=submit value='Go'></form>";
		else {
			if ( $flfields ) $maintext .= "<hr>Show field in list: ";
			foreach ( $flfields as $key => $item ) $maintext .= " &bull; <a href='index.php?action=$action&show=$key'>{$item['display']}</a>";
		};
	};
	
?>