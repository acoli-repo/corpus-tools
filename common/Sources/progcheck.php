<?php
	// Script to automatically check the status of XML files
	// See whether they are POS tagged, lemmatized, and tokenized
	// (c) Maarten Janssen, 2015

	check_login();

	$checks = array ( 
		"tok" => array ( "Tokenization", "//tok", "index.php?action=tokenize&id=" ),
		"pos" => array ( "POS tagging", "//tok[@msd] | //tok[@pos]", "" ),
		"lemma" => array ( "Lemmatization", "//tok[@lemma]", "" ),
		);
	$tocheck = $_GET['tocheck'] or $tocheck = "tok";
	
	$maintext .= "<h1>Progress check for: {$checks[$tocheck][0]}</h1>
		<p>Below is a list of the files in '$xmlfolder' with an indication whether or not they have been processed for {$checks[$tocheck][0]}.";
		

	if ( $_GET['fld'] ) { 
		$subf = $_GET['fld'].'/';
		$subf2 = '/'.$_GET['fld'];
		$folder = $xmlfolder.'/'.$_GET['fld'].'/';
		$maintext .= "<h2>{$_GET['fld']}</h2>";
	} else $folder =  $xmlfolder.'/';

	# Get all the folders 
	$dirfiles = scandir( $xmlfolder.$subf2 );
	foreach ( $dirfiles as $file ) {
		if( substr($file,0,1) === '.' ) {continue;} 
		$purefile = preg_replace ( "/^".preg_quote($xmlfolder, '/')."\//", "", $file );
		if ( is_dir($folder.$file) ) $dirlist .= "<p><a href='index.php?action=$action&fld=$subf$purefile$recf&tocheck=$tocheck'>$file</a>";
	}; if ( $dirlist ) $maintext .= "$dirlist<hr>";

	$maintext .= "<table>
		<tr><th>XML File<th>Done?";
		
	// $cmd = "grep -Hc '<tok' $xmlfolder/$subf*.xml";
	$xpath = $checks[$tocheck][1];
	$cmd = "xmlstarlet sel -t -f -o '::' -v 'count($xpath)' $xmlfolder/$subf*.xml";
	foreach ( explode("\n", shell_exec($cmd)) as $fileline ) {
		list ( $tmp, $tokcnt ) = explode ( "::", $fileline );
		$pathfile = preg_replace ( "/^".preg_quote($xmlfolder, '/')."\//", "", $tmp );
		$filename = preg_replace ( "/.*\//", "", $tmp );
		
		$tokcheck = "yes"; if ( $tokcnt == 0 ) {
			$tokcheck = "no<td><a target=new href='index.php?action=rawxml&id=$pathfile'>view XML</a>";
			if ( $checks[$tocheck][2] ) 
			$tokcheck .= "<td>&bull; <a target=new href='{$checks[$tocheck][2]}{$pathfile}'>process now</a>";
		};
		
		if ( $filename != "" ) $maintext .= "<tr><td><a href='index.php?action=edit&id=$pathfile' target=new>$filename</a>
			<td>$tokcheck"; #<td>$tokcnt";
	};
	$maintext .= "</table>";

	$maintext .= "<hr>Check for: &nbsp; &nbsp; ";
	$sep = "";
	foreach ( $checks as $key => $val ) {
		$maintext .= "$sep<a href='index.php?action=$action&tocheck=$key'>{$val[0]}</a>";
		$sep = " &bull; ";
	};

?>