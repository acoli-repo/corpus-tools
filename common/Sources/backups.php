<?php

	// Script to allow restoring backup copies of a given file
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fileid = $_GET['cid'] or $fileid = $_GET['id'];
	$temp = explode ( '/', $fileid );
	$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );

	if ( $act == "view" ) {
		if ( preg_match ( "/(.*)-(.*)\.(.*)/", $_GET['bid'], $matches ) ) { $date = $matches[2]; };

		$maintext .= "<h1>Version Retrieval - $xmlid / $date</h1>";
		$file = file_get_contents("{$_GET['bid']}"); 
		$xml = simplexml_load_string($file);
		if ( !$xml ) { print "Failing to read/parse $fileid<hr>"; print $file; exit; };

		$result = $xml->xpath("//title"); 
		$title = $result[0];

		$maintext .= "<h2>$title</h2><hr><p>Backup date: ".strftime("%d %h %Y", strtotime($date) );
		
		if ( $_GET['view'] == "full" ) {
			$viewxml = $xml->asXML();
		} else {
			$result = $xml->xpath($mtxtelement); 
			$txtxml = $result[0]; 
			$viewxml = $txtxml->asXML();
		};
		
		$maintext .= "<hr><div id=mtxt>".$viewxml."</div><hr>";
		
		if ( !file_exists("$xmlfolder/$fileid") ) { 
			$maintext .= "<p>Unable to recover since current file does not exist: $fileid"; 
		} else {
			$maintext .= "<p>- <a href='index.php?action=$action&act=recover&bid={$_GET['bid']}&cid=$fileid'>Recover this version</a>
				&bull; <a href='index.php?action=$action&act=diff&fid2={$_GET['bid']}&fid1=$fileid'>View version comparison</a>";
		};

	} else if ( $act == "diff" ) {

		$fid2 = $_GET['fid2'];
		$fid1 = $_GET['fid1']; 
	
			if ( preg_match ( "/backups\/(.*)-(.*)\.(.*)/", $fid2, $matches ) ) {
				$date = $matches[2];
				$date2 = strftime("%d %h %Y", strtotime($date))." (backup)";
			};
			
			if ( preg_match ( "/backups\/(.*)-(.*)\.(.*)/", $fid1, $matches ) ) {
				$date = $matches[2];
				$date1 = strftime("%d %h %Y", strtotime($date))." (backup)";
				$fileid = $matches[1];
			} else { 
				$fileid = $fid1; $current = 1;
				$fid1 = "xmlfiles/$fid1";
				
				# Determine the file date
				$tmp = filemtime($fid1);
				$fdate = strftime("%d %h %Y", $tmp);
				
				$date1 = "$fdate (current)";
			};
			
		$maintext .= "<h1>Version Comparison</h1>
		
			<p>Below are the (token-based) differences between two versions of <a href='index.php?action=file&cid=$fileid'>$fileid</a>
				<br>This comparison excludes differences in token ID's
				<br>Versions: <b>$date1</b> vs. <b>$date2</b>
			
			<style>.s2 { background-color: #ffffdd; padding-left: 15px; padding-right: 15px; }</style>
			<hr><table>
				<tr><td><td><th>$date1<th>$date2";
	
		$file2 = file_get_contents($fid2); 
		$xml2 = simplexml_load_string($file2);
		if ( !$xml2 ) { fatal("Not a valid XML file: ".$fid2); };
		
		$file1 = file_get_contents($fid1); 
		$xml1 = simplexml_load_string($file1);
		if ( !$xml1 ) { fatal("Not a valid XML file: ".$fid1); };		
		
		$toks2 = $xml2->xpath("//tok"); 
		$toks1 = $xml1->xpath("//tok"); 

		$id1 = 0; $id2 = 0;
		$nofeat['id'] = 1;


		# If there is a different number of tokens, find the first matching one
		if ( count($toks1) > count($toks2) ) {
		
			while ( $toks1[$id1].'' !=  $toks2[$id2].'' ) { $id1++; };
		
		} else if ( count($toks1) < count($toks2) ) {

			while ( $toks1[$id1].'' !=  $toks2[$id2].'' ) { $id2++; };
			
		};

		$nochange = 1;
		while ( $id1 < count($toks1) &&  $id2 < count($toks2) ) {
			$tid1 = $toks1[$id1]['id'];
			if ( $current ) $tid1 = "<a href='index.php?action=tokedit&cid=$fileid&tid=$tid1' target=edit>$tid1</a>";
		
			if ( $toks1[$id1]."" != $toks2[$id2]."" ) {
				$nochange = 0;
			
				if ( $toks1[($id1+1)]."" == $toks2[($id2+1)]."" ) {
					$maintext .= "<tr><td>$tid1<td>".$toks1[$id1]."<td>".htmlentities($toks1[$id1]->asXML())."<td class=s2>".htmlentities($toks2[$id2]->asXML());
					$id2++; $id1++;
				} else if ( $toks1[($id1+1)]."" == $toks2[$id2]."" ) {
					$maintext .= "<tr><td>$tid1<td>".$toks1[$id1]."<td>".htmlentities($toks1[$id1]->asXML())."<td style='color: #aaaaaa;' class=s2>(missing)";
					$id1++;
				} else if ( $toks1[$id1].$toks1[($id1+1)] == $toks2[$id2]."" ) {
					$maintext .= "<tr><td>$tid1<br>{$toks1[($id1+1)]['id']}<td>".$toks1[$id1]."<br>".$toks1[($id1+1)]."<td>".htmlentities($toks1[$id1]->asXML())."<br>".htmlentities($toks1[($id1+1)]->asXML())."<td class=s2>".htmlentities($toks2[$id2]->asXML());
					$id2++; $id1+=2;
				} else if ( $toks1[$id1]."" == $toks2[$id2].$toks2[($id2+1)] ) {
					$maintext .= "<tr><td>$tid1<td>".$toks1[$id1]."<td>".htmlentities($toks1[$id1]->asXML())."<td class=s2>".htmlentities($toks2[$id2]->asXML())."<br>".htmlentities($toks2[($id2+1)]->asXML());
					$id2+=2; $id1++;
				} else if ( $toks1[$id1]."" == $toks2[($id2+1)]."" ) {
					$maintext .= "<tr><td>-<td>".$toks2[$id2]."<td style='color: #aaaaaa;'>(missing)<td class=s2>".htmlentities($toks2[$id2]->asXML());
					$id2++;
				} else {
					$maintext .= "<tr><td><td><td colspan=2>Fatal mismatch - stopping comparison (words not matching)";
					$maintext .= "<tr><td>{$toks1[$id1]['id']}<td>".$toks1[$id1]."<td>".htmlentities($toks1[$id1]->asXML())."<td class=s2>".htmlentities($toks2[$id2]->asXML());
					$maintext .= "<tr><td>{$toks1[($id1+1)]['id']}<td>".$toks1[($id1+1)]."<td>".htmlentities($toks1[($id1+1)]->asXML())."<td class=s2>".htmlentities($toks2[($id2+1)]->asXML());
					$maintext .= "<tr><td><td><td colspan=2>...";
					$id1 = count($toks1);
				};
			
			} else { 
				if ( $toks1[$id1]->asXML() != $toks2[$id2]->asXML() ) {
				
					$feats1 = ""; $feats2 = ""; $sep = "";
				
					foreach ( $toks1[$id1]->attributes() as $ak => $av ) {
						if ( $av."" != $toks2[$id2][$ak]."" && !$nofeat[$ak] ) {
							if ( $av ) $feats1 .= "$sep$ak=\"$av\"";
							if ( $toks2[$id2][$ak] )  $feats2 .= "$sep$ak=\"{$toks2[$id2][$ak]}\"";
							$sep = "; ";
						};
					}; 
				
					if ( $feats1 != "" ) {
						$nochange = 0;
						$maintext .= "<tr><td>$tid1<td>".$toks1[$id1]."<td>".$feats1."<td class=s2>".$feats2;
					};
						
				};
			
				$id1++; $id2++;
			};
		};
		
		if ( $nochange ) {
			if ( $file1  == $file2 ) {
				$maintext .= "<tr><td><td><td colspan=6><i>These two versions are fully identical</i>"; 
			} else {
				$maintext .= "<tr><td><td><td colspan=6><i>No token based differences between these two versions (but there are XML differences)</i>"; 
			};
		};
		
		$maintext .= "</table>";

		if ( $current ) { 
			$maintext .= "<hr><p><a href='index.php?action=$action&act=recover&bid=$fid2&cid=$fileid'>Recover the version of $date2</a>";
		} else {
			$maintext .= "<hr><p><a href='index.php?action=$action&act=view&bid=$fid1&cid=$fileid'>View the version of $date1</a>";
		};
		$maintext .= " &bull; <a href='index.php?action=$action&act=view&bid=$fid2&cid={$_GET['fid1']}'>View the version of $date2</a>";
		
		
	} else if ( $act == "recover" ) {

		$newfile = file_get_contents("{$_GET['bid']}"); 
		saveMyXML($newfile, $fileid);

		$maintext .= "<h1>Version Retrieval - $xmlid / $date</h1>
		
			<p>Your previous version has been restored
			<script language=Javascript>top.location='index.php?action=edit&cid=$fileid';</script>";

	} else {	
	
		$maintext .= "<h1>Version History - $xmlid</h1>
	
			<p>Although not a full roll-back system, TEITOK allows you to revert to earlier version of an XML file (if changes have been made to it via the interface). 
				To do so, select the desired version and click 'recover this version'.
				<br>Backups are limited to one backup per day to limit the disk load and the backup saved is always the version prior to the first change made on that day.
				<br>You can also see a token-based comparison of the differences between two
				versions (diff), where you can revert potentially unwanted changes.
				<hr>
				<ul>";

		$matches = glob("backups/$xmlid-*");
		
		if ( !$matches ) $maintext .= "<p><i>There are no backup versions of this file yet</i>";
		foreach ( $matches as $fn ) {
			if ( preg_match ( "/(.*)-(.*)\.(.*)/", $fn, $matches ) ) {
				$date = $matches[2];
				$date = strftime("%d %h %Y", strtotime($date));
				$maintext .= "<li>$date - <a href='index.php?action=$action&act=view&bid=$fn&cid=$fileid'>view</a> &bull; <a href='index.php?action=$action&act=diff&fid2=$fn&fid1=$fileid'>diff</a>";
			} else {
				$maintext .= "<li>(unrecognized file) $fn";
			};
		};
		$maintext .= "</ul>";
	};
	
?>