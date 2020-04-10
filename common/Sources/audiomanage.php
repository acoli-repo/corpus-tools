<?php
	// Script to manage audio files
	// It still contains an alignment function, but that has been superceded by wavesurfer.php
	// (c) Maarten Janssen, 2015

	check_login();
	
	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;

	$sox = findapp("sox");
	if ( !$sox ) {
		fatal("Audio management relies on SoX, which is not installed");
	};

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


		$cmd = "$sox backups/$filename Audio/$filename trim $start $length";
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
		};
		
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

			
	} else if ( $act == "blank" )  {
		
		// TODO: replace aligned <anon> elements with beep sounds	
	
	} else if ( $act == "convert" )  {
		
		$soundfile = execsafe($_GET['file']);	
		if ( preg_match("/(.*?)([^\/]+)\.([^.]+)$/", $soundfile, $matches ) ) {
			$ext = $matches[3];	
			$basename = $matches[2];
			$folder = $matches[1];
			$soundinfo['Extension'] = $ext;	
		};
		if ( $_POST ) {

			$outfile = $folder.$basename.".".$_POST['format'];	
			
			if ( $outfile == $soundfile ) {
				$cmd = "/bin/cp '$soundfile' 'tmp/$basename.$ext'";
				print "<p>$cmd";
				shell_exec($cmd);
			};
			
			
			// TODO: replace aligned <anon> elements with beep sounds	
			$rate = execsafe($_POST['rate']);
			$format = execsafe($_POST['format']);
			$cmd = "$sox '$soundfile' -r '$rate' -t '$format' $outfile";
			print "<p>$cmd";
			shell_exec($cmd);
			//print "<p>File converted. Reloading.
			//	<script language=Javascript>top.location='index.php?action=$action&cid=$ttxml->fileid';</script>";
			exit;
			
		} else {	

			// sound info	
			$soxinfo = shell_exec("$sox --i '$soundfile'");
			foreach ( explode("\n", $soxinfo) as $line ) {
				if ( preg_match ( "/^\s*(.*?)\s*:\s*(.*)$/", $line, $matches ) )
					$soundinfo[trim($matches[1])] = trim($matches[2]);
			};
			$infotable .= "<table>";
			foreach ( $soundinfo as $key => $val ) {
				if ( preg_match("/^([0-9.]+)M$/", $val, $matches) ) {
					$soundinfo[$key] = $matches[1]*1000000;
				};
				$infotable .= "<tr><th>$key<td>$val</pre>";
			};
			$infotable .= "</table><p></p>";
		
			$maintext .= "
				<h2>$fileid</h2>
				<h1>Sound conversion: $soundfile</h1>
				
				<h2>Current format</h2>
				$infotable
			
				<h2>Output option</h2>";

			$maintext .= "<form action='index.php?action=$action&act=$act&file=$soundfile&cid=$ttxml->fileid' method=post>";

			$bitrates = array (
				"8000" => "Telephone ",
				"11025" => "1/4 audio CDs",
				"16000" => "Wideband telephone ",
				"22050" => "1/2 audio CDs",
				"32000" => "miniDV",
				"37800" => "CD-XA audio",
				"44100" => "Audio CD",
				"48000" => "Digital video equipment",
				"96000" => "DVD-Audio",
				"1410000" => "PCM-Audio",
				);
			$list = "";
			foreach ( $bitrates as $key => $val  ) {
				$sel = ""; if ( $key == $soundinfo['Sample Rate'] ) $sel = "selected";
				$list .= "<option value='$key' $sel>$key ($val)</option>";
			};
			$maintext .= "<p>Sample rate: <select name=rate>$list</select>";
		
			// 8svx aif aifc aiff aiffc al amb au avr cdda cdr cvs cvsd cvu dat dvms f32 f4 f64 f8 fssd gsm gsrt hcom htk ima ircam la lpc lpc10 lu maud mp2 mp3 nist prc raw s1 s16 s2 s24 s3 s32 s4 s8 sb sf sl sln smp snd sndr sndt sou sox sph sw txw u1 u16 u2 u24 u3 u32 u4 u8 ub ul uw vms voc vox wav wavpcm wve xa
			$formats = array (
				"aac" => "Advanced Audio Coding",
				"aiff" => "Apple audio format",
				"gsm" => "Telephony format",
				"m4a" => "Audio-only MPEG-4",
				"mp3" => "MPEG Layer III Audio",
				"mpc" => "Musepack",
				"ogg" => "OGG/Vorbis",
				"ra" => "RealAudio",
				"raw" => "PCM audio data",
				"tta" => "The True Audio",
				"vox" => "Vox format",
				"wav" => "Windows file container",
				"wma" => "Windows Media Audio",
				"wv" => "Wavpack",
				"webm" => "HTML5 video",
				);
			$list = "";
			foreach ( $formats as $key => $val ) {
				$sel = ""; if ( $key == $soundinfo['Extension'] ) $sel = "selected";
				$list .= "<option value='$key' $sel>$key ($val)</option>";
			};
			$maintext .= "<p>File format: <select name=format>$list</select>";

			$maintext .= "
				<p><input type=submit value=Convert>
				</form>";

		};	
		
	} else if ( $act == "align" )  {
		// made redundant by wavesurfer
		
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
							<p><i><a href='{$medianode['url']}'>{%Audio}</a></i> - {%Consider using Chrome or Firefox for better audio support}</p>
						"; 
				} else {
					$audiobit .= "<audio id=\"track\" src=\"{$medianode['url']}\" controls ontimeupdate=\"checkaudio();\">
							<source  src=\"{$medianode['url']}\">
							<p><i><a href='{$medianode['url']}'>{%Audio}</a></i></p>
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
			<input type=button value=Cancel onClick=\"window.open('index.php?action=file&cid=$fileid', '_top');\">
			</form>
			";

	} else if ( $ttxml )  {
	
		$maintext .= "
			<h2>$fileid</h2>
			<h1>Audio management</h1>";
			
		$soundfile = execsafe(current($ttxml->xml->xpath("//media[contains(@mimeType, \"audio\")]/@url")));
		if ( !$soundfile ) $soundfile = current($ttxml->xml->xpath("//media/@url")); // maybe there is no mimeType
		if ( !$soundfile ) fatal ("XML file has no media element providing a URL to the sound file");

		if ( !strstr($audiourl, 'http') ) {
			if ( file_exists($audiourl) ) $audiourl =  "$baseurl/$audiourl"; 
			else $audiourl = $baseurl."Audio/$audiourl"; 
		}
			
		// sound info	
		$soxinfo = shell_exec("$sox --i $soundfile");
		foreach ( explode("\n", $soxinfo) as $line ) {
			if ( preg_match ( "/^(.*?)\s*:\s*(.*)$/", $line, $matches ) )
				$soundinfo[$matches[1]] = $matches[2];
		};
		$maintext .= "<h2>File info</h2>
		<table>";
		foreach ( $soundinfo as $key => $val ) {
			$maintext .= "<tr><th>$key<td>$val</pre>";
		};
		$maintext .= "</table><p></p>";
		
		$maintext .= "<audio id=\"track\" src=\"{$medianode['url']}\" controls ontimeupdate=\"checkaudio();\">
				<source  src=\"{$medianode['url']}\">
				<p><i><a href='{$medianode['url']}'>{%Audio}</a></i></p>
			</audio>

			<h2>Audio options</h2>
			<ul>
			<li><a href='index.php?action=$action&cid=$ttxml->fileid&act=cut'>Cut audio</a>
			</ul>
			"; 
		
	} else {

		$maintext .= "<h1>Audio files</h1>";
	
	};
	
?>