<?php
	// Script to XPath queries on XML files
	// DEV script, avoid using
	// (c) Maarten Janssen, 2015

	$maintext .= "<h1>Xpath File Finder</h1>";

	$xquery = $_POST['xquery'] or $xquery = $_GET['xquery'];
	$xp2 = $_POST['xp2'] or $xp2 = $_GET['xp2'];
	
	$xp = $xquery or $xp = "//date[@value=\"2007\"]";

	$xp = preg_replace ("/\"/", "&quot;", $xp);
	$xp2 = preg_replace ("/\"/", "&quot;", $xp2);
	$maintext .= "<p><form action=\"index.php?action=$action\" method=post>Search query: 
		<input type=hidden name=action value=\"$action\">
		<input name=xquery size=80 value=\"$xp\">
		<input type=submit value=Search>
		<p>Search query 2: <input name=xquery2 size=80 value=\"$xp2\">
		
		<p><input type=radio name=res value='file' checked> List files 
			<br><input type=radio name=res value='elm'> List nodes 
		</form>";
	
	if ( $xquery ) {
		
		if ( $_POST['res'] == "elm" ) {
			if ( $xp2 ) {
				$xpath2 = "-m '{$_GET['xquery2']}'";
				$xtxt = " and $xp2";
			};
	
			$maintext .= "<hr style='margin-top: 15px;'>XQuery: $xquery $xtxt";
			$cmd = "xmlstarlet sel -t -m \"$xquery\" $xpath2 -f -o \"::\"  -c . -n $xmlfolder/*.xml $xmlfolder/*/*.xml";
			if ( $debug ) $maintext .= "<p>Unix command: $cmd";
		
			$maintext .= "<hr><p>Matching elements:<table>";		
			$output = shell_exec($cmd);
			foreach ( explode (  "\n", $output ) as $line ) {
				list ( $filename, $node ) = explode ( "::", $line );
				$maintext .= "<tr><td>$filename<td>".htmlentities($node);
			};
			$maintext .= "</table>";
			
		} else {
			
			if ( $xp2 ) {
				$xpath2 = "-m '{$_GET['xquery2']}'";
				$xtxt = " and $xp2";
			};
	
			$maintext .= "<hr style='margin-top: 15px;'>XQuery: $xquery $xtxt";
			$cmd = "xmlstarlet sel -t -m \"$xquery\" $xpath2 -f $xmlfolder/*.xml $xmlfolder/*/*.xml";
			if ( $debug ) $maintext .= "<p>Unix command: $cmd";
		
			$maintext .= "<hr><p>Matching XML files:";		
			$output = shell_exec($cmd);
			foreach ( explode (  "\n", $output ) as $filename ) {
				$filename = preg_replace ( "/^$xmlfolder\//", "", $filename );
				$maintext .= "<p><a href=\"index.php?action=edit&id=$filename\">$filename</a>";
			};
		};
	};
			
?>