<?php
	// Script to search through the XML files
	// on raw text strings 
	// useful to find information not accessible via CQP
	// (c) Maarten Janssen, 2015

	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 
	$query = $_GET['query'];
	$cqpfolder = $settings['cqp']['searchfolder'];
	
	if ( $_GET['text'] || $_GET['prefix'] ){
		
		$maintext .= "<h1>{%Related Documents}</h1>";
		if ( $_GET['prefix'] ) $maintext .= "<p>{$_GET['prefix']}: $query";
		else $maintext .= "<p>{$_GET['text']}";
		
	} else {
		$fileheader = getlangfile("rawsearchtext");
	
		$maintext .= "<h1>{%Raw corpus search}</h1>
	
			$fileheader
	
			<form action='index.php'>
				<input type=hidden name=action value='$action'>
				{%Search Query}: 
				<input name=query size=60 value='$query'> <input type=submit value='{%Search}'>
			</form>
			";
	};
		
	if ( $query ) { 
		$maintext .= "<hr><table>";

		# $query = quotemeta $query;
		$query = str_replace('[', '\[', $query);
		$query = str_replace(']', '\]', $query);

		$query = execsafe($query);

		if ( $settings['bin']['grep'] ) $grepcmd = $settings['bin']['grep'];
		else {
			$cmd = "/usr/bin/which grep"; $grepcmd = chop(shell_exec($cmd));
			if ( !$grepcmd ) { $grepcmd = "grep"; };
		};

		if ( $username || !$cqpfolder ) { $folderlist = "xmlfiles"; } else { $folderlist = $cqpfolder; };
		$cmd = "$grepcmd -lR '$query'  $folderlist";
		print "<!-- QUERY: $cmd -->";

		$reslist = shell_exec($cmd);

		foreach ( explode ( "\n", $reslist ) as $resline ) {
			preg_match("/.*xmlfiles\/(.*)\./", $resline, $matches );
			$xmlid = $matches[1]; $folder = ""; 
			if ( preg_match("/(.*)\/([^\/]+)/", $xmlid, $matches ) ) {
				$folder = $matches[1]; $xmlid = $matches[2];
			};
			if ( !$xmlid ) continue;
			
			if ( $username ) $foldertxt = "<td><i class=adminpart>$folder</i>";
			$tmp = ""; #if ( !$folders[$folder] ) $tmp = "class=adminpart"; # TODO: why?
			$maintext .= "<tr>$foldertxt<td><a href='index.php?action=file&id=$xmlid' $tmp>$xmlid</a>";
		};
		$maintext .= "</table>";
	};
	
?>