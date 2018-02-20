<?php
	// Script to call external commands 
	// to allow project-specific (Perl) scripts for easy editing
	// Scripts are defined in settings.xml (for security)
	// only scripts defined there can be run here
	// (c) Maarten Janssen, 2015

	check_login();
	
	if ( $_GET['script'] ) {
		$item = $settings['scripts'][$_GET['script']];
		
		$filename = $_GET['file'];
		if ( preg_match("/([^\/]+)\.xml/", $filename, $matches ) ) $id = $matches[1];
		
		
		if ( !$item ) { print "Error. No such script found: {$_GET['script']}"; exit; };
		$cmd = $item['action'];
		$desc = $item['display'];
		$outfile = $item['outfile'];
		
		$cmd = preg_replace ( "/\[fn\]/", "$xmlfolder/{$_GET['file']}", $cmd );
		$cmd = preg_replace ( "/\[id\]/", "$id", $cmd );
		
		# print $cmd; exit;
		$res = htmlentities(shell_exec($cmd));
		$maintext .= "<h1>Script Done</h1><p>Script successfully executed. Result:
		 <hr><PRE>$res</PRE><hr>
		 
		 <p>&bull; Click <a href='index.php?action=file&cid={$_GET['file']}'>here</a> to return to the XML file";
		 
		 if ( $outfile ) {
			$outfile = preg_replace ( "/\[fn\]/", "$xmlfolder/{$_GET['file']}", $outfile );
			$outfile = preg_replace ( "/\[id\]/", "$id", $outfile );
			$maintext .= "<p>&bull; Click <a href='index.php?action=file&cid=$outfile'>here</a> to go to the modified XML file";

		 };
	};

?>