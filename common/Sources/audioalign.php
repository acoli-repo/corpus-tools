<?php
	// Script to help put audio alignment tags (start, stop)
	// in all elements of type <p> <s> <u> (choose)
	// use keyboard to indicate (start) end of each fragment
	// (c) Maarten Janssen, 2015

	check_login();
	
	$fileid = $_POST['cid'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'];
	$oid = $fileid;
	
	if ( !strstr( $fileid, '.xml') ) { $fileid .= ".xml"; };	
	if ( !$fileid ) fatal ("No XML file indicated"); 


	if ( !file_exists("$xmlfolder/$fileid") ) { 

		$fileid = preg_replace("/^.*\//", "", $fileid);
		$test = array_merge(glob("$xmlfolder/**/$fileid")); 
		if ( !$test ) 
			$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
		$temp = array_pop($test); 
		$fileid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);

		if ( $fileid == "" ) {
			fatal("No such XML File: {$oid}"); 
		};
	};
	
	$file = file_get_contents("$xmlfolder/$fileid"); 
	$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$xml ) fatal ("No valid XML in $xmlfolder/$fileid");

	if ( $act == "save" ) {
	
		$tagname = $_POST['tagname'];
		foreach ( $_POST['nodeid'] as $key => $val) {	
			$nodestart = $_POST['start'][$key];
			$nodeend = $_POST['end'][$key];
			
			print "//{$tagname}[@id='$val']";
			$result = $xml->xpath("//{$tagname}[@id='$val']"); 
			$node = $result[0]; 
			$node['start'] = $nodestart;
			$node['end'] = $nodeend;
			
		};
		# print $xml->asXML(); exit;
		saveMyXML($xml->asXML(), $fileid);

		$maintext .= "<hr><p>The time indexes have been saved - reloading";
		header("location:index.php?action=file&cid=$fileid");
		
		exit;
	
	
	} else if ( $act == "cutoff" ) {
	
		# Cut off parts (of the beginning and end) of a sound file
		$audiofile = $_GET['audio'];
		$filename = preg_replace("/^Audio\//", "", $audiofile);

		if ( !file_exists("Audio/$filename") ) fatal ("$audiofile does not exist or is not a local file");

		copy("Audio/$filename", "backups/$filename");
		$start = $_POST['start']; $end = $_POST['end'];
		$length = $end-$start;
		if ( $length <= 0 ) fatal ("negative length");
		

		# Now adjust the @start and @end by $start in the XML
		if ( $_POST['correct'] && $start != 0 )  { 
			foreach ( $xml->xpath("//text//*[@start]") as $node ) {
				$id = $node['id'];
				$t1 = $node['start']."";
				# if ( $t1 == "" ) { $t1 = $node['end']; };
				$newt1 = max(0,$t1 - $start);
				$node['start'] = $newt1;
				$t2 = $node['end']."";
				# if ( $t2 == "" ) { $t2 = $node['start']; };
				$newt2 = max(0,$t2 - $end);
				$node['end'] = $newt2;
				
				saveMyXML($xml->asXML(), $fileid);
			};
		};


		$cmd = "sox backups/$filename Audio/$filename trim $start $length";
		exec($cmd);

		$maintext .= "<hr><p>The sound file has been trimmed (there is a backup of the old file); reloading";
		header("location:index.php?action=file&cid=$fileid");
		exit;
	
	} else if ( $act == "cut" ) {
	
		$audiofile = $_GET['audio'];
		if ( !$audio ) $audiofile = current($xml->xpath("//media[1]/@url")).""; 

		$filename = $audiofile;
		if ( !strstr($filename, "/") ) $filename = "Audio/$filename";

		if ( !file_exists($filename) ) fatal ("$audiofile does not exist or is not a local file");
		
		$tmp = current($xml->xpath("//text//*[@start]"));
		$first = $tmp['start'];
		if ( $first ) {
			$firsttxt = "- first aligned element: $first";
		}:
		
		$maintext .= "<script language=Javascript src=\"$jsurl/audiocontrol.js\"></script>";
		$maintext  .= "<h1>Crop Audio File</h1>
		
			<p>Below you can crop the audio file $audiofile in case there is sound at the beginning or
			end of the file that should not be made available online.  Cutting off is done by sox, which
			hence has to be installed on your server.
			It is best to do this before any
			alignment with the text since although the system will make an attempt to correct the time
			indices if they exist, this process is not fully reliable.</p>
			
			<div><audio id=\"track\" src=\"$audiofile\" controls ontimeupdate=\"checkaudio();\" onload=\"document.audiofrm.end.value = 4\">
					<source  src=\"$audiofile\">
				</audio>
			</div>
			
			<p><form id=audiofrm name=audiofrm action='index.php?action=$action&act=cutoff&cid=$fileid&audio=$audiofile' method=post>
				<table>
				<tr><td>Start index: <td><input name=start value='0' onChange='mins(this);'> $firsttxt
				<tr><td>End index: <td><input name=end value='' onChange='mins(this);' onFocus=\"if (this.value=='') { this.value=audio.duration;}; \">
				</table>
			<script>
				var audio = document.getElementById('track');
				var firstidx = parseFloat('$first');
				function mins ( e ) {
					var time = e.value;
					if ( time.indexOf(':') != -1 ) {
						res = time.split(':');
						time = 60*parseInt(res[0]) + parseInt(res[1]);
						e.value = time;
					};
					if ( time > firstidx ) {
						alert('new start of sound fragment seems to be before the first aligned segment (' + firstidx + ') - consider revising; all starts before ' + time + ' will be rounded up to 0;');
					};
				};
			</script>";
			
			if (  $xml->xpath("//text//*[@start]") ) {			
				$maintext .= "<p><input type=checkbox name=correct checked> Modify aligned segments to (hopefully) match new audio file";	
			};
			
			$maintext .= "<p><input type='Submit' value='Trim'></p>
			</form>
			
			<p onClick=\"endtime = document.audiofrm.end.value; playpart('$audiofile', document.audiofrm.start.value, document.audiofrm.end.value, '')\">listen to cropped sound</p>
			<p><a href='index.php?action=file&cid=$fileid'>cancel</a></p>
				"; 

			
	
	} else {
	
		# Determine the alignment level
		if ( $_GET['tag'] ) $tagname = $_GET['tag'];
		else {
			$result = $xml->xpath("//*[@start]"); 
			if ( $result ) {
				# Take whichever level has already been annotated
				$audionode = $result[0];
				$tagname = $audionode->getName();
			} else {
				$result = $xml->xpath("//s"); 
				if ( $result ) $tagname = "s";
				else $tagname = "p";
			};
		};
	
		
		# Get the audio file
		$result = $xml->xpath("//media"); 
		if ( !$result ) fatal ("No audio file in XML");
		foreach ( $result as $medianode ) {
			list ( $mtype, $mform ) = explode ( '/', $medianode['mimeType'] );
			if ( $mtype == "audio" ) {
				if ( preg_match ( "/MSIE|Trident/i", $_SERVER['HTTP_USER_AGENT']) ) {	
					// IE does not do sound - so just put up a warning
					$audiobit .= "
							<p><i><a href='{$medianode['url']}'>{%Audio fragment for this text}</a></i> - {%Consider using Chrome or Firefox for better audio support}</p>
						"; 
				} else {
					$audiobit .= "<audio id=\"track\" src=\"{$medianode['url']}\" controls ontimeupdate=\"checkaudio();\">
							<source  src=\"{$medianode['url']}\">
							<p><i><a href='{$medianode['url']}'>{%Audio fragment for this text}</a></i></p>
						</audio>
						"; 
					$result = $medianode ->xpath("desc"); 
					$desc = $result[0].'';
					if ( $desc ) {
						$audiobit .= "<br><span style='font-size: small;'>$desc</span>";
					};
				};
			};
		};
		if ( $username ) $txtid = $fileid; else $txtid = $xmlid;
		$result = $xml->xpath("//title"); 
		$title = $result[0];
		if ( $title == "" ) $title = "<i>{%Without Title}</i>";

		$maintext .= "<script language=Javascript src=\"$jsurl/audioalign.js\"></script>";
		$maintext .= "<h2>$txtid</h2><h1>$title </h1>$audiobit
				<style>.adminpart { background-color: #eeeedd; padding: 5px; }</style>";


		# Check whether the tagname exists
		$result = $xml->xpath("//$tagname"); 
		if ( !$result ) fatal ( "No existing nodes for tagname $tagname" );

		$maintext .= "<p>Edit sound alignment at the level of: &lt;$tagname&gt;";
	
		$maintext .= "<p>Click <a href='index.php?action=$action&cid=$fileid&act=cut'>here</a> if you need to trim the sound file (best done before aligning)";
		
	
		$maintext .= "
			<form action='index.php?action=$action&act=save' method=post id=audioform name=audioform>
			<input type=hidden name=cid value='$fileid'>
			<input type=hidden name=tagname value='$tagname'>
			<table>
			<tr><th>ID<th>Text<th>Start<th>End";
		$audioidx = 0;
		foreach ( $result as $audionode ) {
			$nodeid = $audionode['id'];
			if ( $tagname == "lb" ) {
				$nodetxt = "(line)";
			} else {
				$nodetxt = $audionode->asXML();
			};
			$nodestart = $audionode['start'];
			$nodeend = $audionode['end'];
			$maintext .= "<tr id='row$audioidx' onClick=\"gotoRow($audioidx)\">
				<td>$nodeid<td>$nodetxt<td><input name='start[$audioidx]' value='$nodestart'>
				<td><input name='end[$audioidx]' value='$nodeend'>
				<input type=hidden name='nodeid[$audioidx]' value='$nodeid'>";
			$audioidx++;
		};
		$maintext .= "</table>
			<input type=submit value=Save>
			<input type=button value=Cancel onClick=\"window.open('index.php?action=edit&cid=$fileid', '_top');\">
			</form>
			";

	};
	
?>