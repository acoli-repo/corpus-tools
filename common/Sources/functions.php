<?php
	// Centralized TEITOK functions
	// (c) Maarten Janssen, 2015
	
	// Check if we are logged in
	$publicactions = "user,admin";
	function check_login ( $checktype = "user" ) {
		global $user, $username, $userid, $settings, $action, $publicactions;

		# See if we are allowed special permissions on this file
		if ( !$username && $userid && file_exists("Sources/useredit.php") ) {
			require("Sources/useredit.php");
			if ( $allowme ) return;
		};

		if ( $settings['permissions']['groups'] ) $grouprec = $settings['permissions']['groups'][$user['group'].""];
				
		if ( $user['permissions'] == "admin" ) return; # Always allow admin
		if ( $action == "user" && $username ) return; # Always allow people to see their user profile (and logout)
		
		if ( $checktype != "" && in_array($user['permissions'], explode(",", $checktype)) ) return; // explicitly allowed for this type
		
		if ( is_array($_SESSION['extid']) ) { // Check whether we are logged in with an appropriate external ID
			foreach ( $_SESSION['extid'] as $idtype => $val ) {
				$extfunc = $settings['permissions'][$idtype]['functions'];
				if ( is_array($extfunc) && in_array($action, array_keys($extfunc)) ) return; // allowed for extid users
			};
		};

		if ( $grouprec['actions'] && in_array($action, explode(",", $grouprec['actions'])) ) return; // An allowed action for this group		
		if ( $grouprec['restrictions'] && !in_array($action, explode(",", $grouprec['restrictions'])) ) return; // An allowed action for this group		

		// We did not get permissions - figure out which error to display		
		if ( !in_array($user['permissions'], explode(",", $checktype)) ) { 
			if ( $usergroups[$checktype]['message'] )
				print "<p>".$usergroups[$checktype]['message'];
			else 
				print "<p>This function is for editing users only";
			
			print "<script language=Javascript>top.location = 'index.php?action=notli&type=$checktype';</script>"; 
			exit;
		} else if ( $grouprec['actions'] && !in_array($action, explode(",", $grouprec['actions'])) && !in_array($action, explode(",", $publicactions)) ) {
			print "<p>This function is not allowed for your group";
			
			print "<script language=Javascript>top.location = 'index.php?action=notli&type=nogroup';</script>"; 
			exit;
		};
	};

	// Get a specific token from an XML file
	function grab_token ( $fileid, $tid ) {
		// First, get the XML token
		if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot/common"; };
		$cmd = "xsltproc --novalid --stringparam id '$tid' $scrt/common/Scripts/tok.xslt xmlfiles/merged/*/$fileid.xml";
		$toktxt = shell_exec($cmd);
		
		// Now, parse this xml
		$tokxml = simplexml_load_string($toktxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		// set pform to innerHTML
		$tokxml['pform'] = $tokxml."";

		return $tokxml;
	};

	function file_locate ( $filename ) {
		$cmd = "locate $filename";
		$filepath = shell_exec($cmd);
		if ( strstr($filepath, "\n") ) $filepath = substr($filepath, 0, strpos($filepath, "\n"));

		return $filepath;
	};
	function rsearch($folder, $pattern) {
		// rsearch('myfldr', 'this**file')
		$dir = new RecursiveDirectoryIterator($folder);
		$ite = new RecursiveIteratorIterator($dir);
		if ( !$ite ) return array();
		$files = new RegexIterator($ite, $pattern, RegexIterator::GET_MATCH);
		$fileList = array();
		foreach($files as $file) {
			$fileList = array_merge($fileList, $file);
		}
		return $fileList;
	};
	function rglob($pattern, $flags = 0) {
		// rglob('myfldr/**/this**file')
		$files = glob($pattern, $flags); 
		foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
			$files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
		}
		return $files;
	};

	function getxmlrec($fn, $id, $tag, $opt="") {
		# Get a record from an XML file by ID on a given tag
		$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 

		# See if we can find an index for this file
		preg_match("/([^\/]+)\.xml/", $fn, $matches ); 
		$idname = $matches[1];
		$idxname = $idname."_$tag.idx";
		$idxname2 = $idname.".idx";
		
		if ( file_exists("Indexes/$idxname") ) {
			$idxfn = "Indexes/$idxname";
		} else if ( file_exists("Indexes/$idxname2") ) { 
			$idxfn = "Indexes/$idxname2";
		};
		
		if ( $idxfn ) {		

			$fp = fopen($fn, 'r');
			fseek($fp,0,SEEK_SET);

			# Look in the index for the file pointers
			$cmd = "grep -b1 '$id\t'  '$thisdir/$idxfn'";
			$result1 = shell_exec($cmd); 
			$tmp = explode ( "\n", $result1 );
			list ($id1, $pos1) = explode ( "\t", $tmp[1]); 
			list ($id2, $pos2) = explode ( "\t", $tmp[2]); 
	
			# Get the stretch between the file pointers from the file
			$toget = $pos2-$pos1-1;
			fseek($fp, $pos1,SEEK_CUR);
			$result = fread($fp, $toget);
			$toshow = $result;
			fclose ($fp);
		
			return $toshow;

		} else {
			# No index - do this the slow way
			$file = file_get_contents($fn);
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { return -1; };
			$result = $xml->xpath("//{$tag}[@id='$id']"); 
			$match = $result[0];
			if (!$match) return -1;
			return $match->asXML();
		};
		
	};

	function showxml ( $xml, $ident = 0 ) {
		$inds = str_repeat("&nbsp;", $ident*5);
		$atts = ""; $unit = "em";
		
		if ( !$xml ) return "";
		if ( !$xml->children() && count($xml->attributes()) < 2 && strlen($xml."") < 50 ) {
			$showxml .= "\n<div style='margin-left: {$ident}$unit;'><span style='color: #0000dd;'>&lt;".$xml->getName()."&gt;</span><span style='color: black;'>$xml</span><span style='color: #0000dd;'>&lt;/".$xml->getName()."&gt;</span></div>";					
		} else {
			foreach ( $xml->attributes() as $key => $val ) { $atts .= " <span style='color: #aa0000;'>$key=\"$val\"<span>"; }
			$showxml .= "\n<div style='margin-left: {$ident}$unit;'><span style='color: #0000dd;'>&lt;".$xml->getName().$atts."&gt;</span></div>";
			if ( preg_replace("/\s/", "", $xml."") != "" ) $showxml .= "\n<div style='margin-left: {$ident}$unit; padding-left: 1$unit;'><span style='color: black;'>".$xml."</span></div>";		
			foreach ( $xml->children() as $child ) {
				$showxml .= showxml($child, $ident+1);
			};
			$showxml .= "\n<div style='margin-left: {$ident}$unit;'><span style='color: #0000dd;'>&lt;/".$xml->getName()."&gt;</span></div>";
		};
				
		return $showxml;
	};

	function xpathrun ( $text, $xml, $filename = "" ) {
		# Fill in variable from XPath queries
		global $showempties; global $fileid; // When so desired, remove empty rows
		if ( !$xml ) { return $text; }; # No XML to compare to, so just return
		if ( !$filename ) $filename = $fileid;
		
		if ( strpos("{#", $text) == -1 ) return $text; # If there is nothing to translate - return to save time

		$text = preg_replace ( "/\{#fn\}/", $filename, $text );		
		
		preg_match_all ( "/\{#([^\}]+)\}/", $text, $matches );		

		foreach ( $matches[0] as $key => $match ) {

			$from = preg_quote($match, '/'); 

			$xquery = $matches[1][$key];
			$lnk = 0; if ( substr($xquery, 0,1) == "=" ) { $lnk = 1; $xquery =  substr($xquery, 1); }
			# We need to emulate SUBSTR here since PHP does not support it....
			if ( preg_match ( "/substring\((.*?), (\d+)\)/", $xquery, $subms ) ) { 
				$xquery = $subms[1]; $tmp  = $subms[2]-1;
				$result = $xml->xpath($xquery); 
				if ( !$result ) $to = "";
				else $to = $result[0];
				$to = substr($to, $tmp);				
			} else {			
				$result = $xml->xpath($xquery); 
				$tmp = $result[0];
				if ( !$result ) $to = "";
				else if ( $tmp->xpath('child::*') ) $to = $tmp->asXML();
				else $to = $tmp."";
			};
			$to = str_replace('"', '&quot;', $to);
			if ( $lnk ) { $to = "<a href='$to'>$to</a>"; };
			if ( $to == "" && !$showempties ) { 
				$text = preg_replace("/<tr><t[dh][^>]*>[^>]+(<[^>]+>)+$from(<[^>]+>)+/", "$to", $text); # For rows
			};
			
			$text = preg_replace("/$from/", "$to", $text);
		};
		$text = str_replace('&quot;', '"', $text);
		
		return $text;
	};

	function hrnum ( $num ) {
		# Make a human readable number
		$deg = array ( "", "k", "M", "G", "T",  "P", "E" );
		$i = 0;
		# Keep dividing by 1000
		while ( $num > 1000 ) {
			$i++; $num = $num/1000;
		};
		# Keep one digit after the comma for number below 10
		if ( $num < 10 && round($num*10)/10 != round($num) ) {
			$num = round($num*10)/10; 
		} else { $num = round($num); };
		return $num.$deg[$i];
	};

	function i18n ( $text, $tolang = "" ) {
		global $lang; global $i18n; global $langprefix; global $deflang; global $debug; global $ttroot; 
		global $i18nlang; global $sharedfolder;
		if ( !$tolang ) $tolang = $lang;
		
		if ( strpos($text, "{%") == -1 ) return $text; # If there is nothing to translate - return to save time
		
		if ( $i18nlang != $tolang ) { # Read the translation defs - but do so only once (or once per language)
			if ( file_exists("Sources/i18n_$tolang.php") ) { // Local defs overrule global defs
				include("Sources/i18n_$tolang.php");
			} else if ( file_exists("$ttroot/common/Sources/i18n/i18n_$tolang.php") ) {
				include("$ttroot/common/Sources/i18n/i18n_$tolang.php");
			}

			# Now read the local shared defs - which can override all global settings
			if ( $sharedfolder && file_exists("$sharedfolder/Resources/i18n_$tolang.txt") ) {
				foreach ( explode ( "\n", file_get_contents("$sharedfolder/Resources/i18n_$tolang.txt") ) as $line ) {
					list ( $org, $trans ) = explode ( "\t", $line );
					$i18n[$org] = $trans;
				};
			};

			# Now read the local defs - which can override all global settings
			if ( file_exists("Resources/i18n_$tolang.txt") ) {
				foreach ( explode ( "\n", file_get_contents("Resources/i18n_$tolang.txt") ) as $line ) {
					list ( $org, $trans ) = explode ( "\t", $line );
					$i18n[$org] = $trans;
				};
			};
			
			$i18nlang = $tolang;
			
		};
				
		preg_match_all ( "/\{%([^\}]+)\}/", $text, $matches );		

		foreach ( $matches[0] as $key => $match ) {
			$txtel = $matches[1][$key];
			$from = preg_quote($match, '/'); 
			$caps = 0; if ( substr($txtel,0,1) == "!" ) {
				$caps = 1;
				$txtel = substr($txtel,1);
			};
			if ( isset($i18n[$txtel]) ) {
				$to = $i18n[$txtel];
			} else {
				$to = $txtel; # If we have no translation, just remove the brackets
				$furl = $_SERVER['REQUEST_URI'] or $furl = 1;
				if ($lang != "en" || strstr($txtel,'-') ) $_SESSION['mistrans'][$lang][$txtel] = $furl; # Store the missing translation in a cookie
			};
			$to = str_replace('"', '&quot;', $to);
			$to = preg_replace("/\r/", '', $to); # Hidden \r make Javascript stop working
			if ( $caps ) $to = ucfirst($to);
			$text = preg_replace("/$from/", "$to", $text);
		};
	
		# Finally, also prefix all uses of "index.php" with the language 
		if ( $langprefix && $lang != $deflang ) {
			$text = preg_replace ( "/([\"'])index\.php/", "\\1$lang/index.php", $text );
		};
		
		return $text;
	};
	
	function md2html ( $html ) {
		return "<textarea id='mdtext' style='display: none;'>$html</textarea>
			<div id='tohtml'></div>
			<script language=Javascript src='https://cdnjs.cloudflare.com/ajax/libs/showdown/1.9.0/showdown.min.js'></script>
			<script language=Javascript>
				converter = new showdown.Converter(),
				text      = document.getElementById('mdtext').value,
				html      = converter.makeHtml(text);
				document.getElementById('tohtml').innerHTML = html;
			</script>
			";
	};
	
	function getlangfile ( $ffid, $common = false, $flang = null, $options = null ) {
		global $lang; global $settings; global $getlangfile_lastfile;  global $getlangfile_lastfolder;  global $ttroot; global $username, $action, $sharedfolder;
		if ( $flang === null ) $flang = $lang; $html = "";
		$deflang = $settings['languages']['default'] or $deflang = "en";
		
		$tryfolders = array ( "Pages", "$sharedfolder/Pages", "$ttroot/common/Pages");
		$trypages = array ( "{$ffid}-$flang", "{$ffid}", "{$ffid}-$deflang" );
		
		foreach ( $tryfolders as $tryfolder ) {
			$getlangfile_lastfolder = $tryfolder;
			foreach ( $trypages as $trypage ) {
				if ( substr($trypage,0,1) == "/" ) continue; # Skip shared if not defined
				if ( file_exists("$tryfolder/$trypage.html") ) {
					$getlangfile_lastfile = "$tryfolder/$trypage.html";
					$html = file_get_contents($getlangfile_lastfile);
					break 2;
				} else if ( file_exists("$tryfolder/$trypage.md") ) {
					$getlangfile_lastfile = "$tryfolder/$trypage.md";
					$md = file_get_contents($getlangfile_lastfile);
					if ( $options == 'nomd' ) {
						$html = $md;
					} else {
						$html = md2html($md);
					};
					break 2;
				};
			};
		}; if ( !$html ) $getlangfile_lastfolder = "";

		if ( $username && $action != "pageedit") {
			if ( $ffid == "notfound" ) $ffid = $_GET['action'] or $ffid = "home";
			$editaction = preg_replace("/-[a-z]{2,3}$/", "", $ffid);
			$html = "<div class='adminpart' style='float: right;'><a href='index.php?action=pageedit&id=$editaction&pagelang=$flang'>edit text</a></div>".$html;
		};
		
		return $html;
	};

	function usettcqp() {
		if ( !findapp("tt-cqp") ) return false;
		if ( $_GET['cwb'] || $settings['cqp']['ttcqp'] == "0" ) return false;

		return true; # This should prob. stop being the default
	};

	function findapp ( $appname ) {
		global $bindir; global $settings;
		
		if ( is_array($settings['bin']) && $settings['bin'][$appname] ) return $settings['bin'][$appname];
		
		if ( $bindir && file_exists("$bindir/$appname") ) return "$bindir/$appname";

		if ( file_exists("/usr/bin/$appname") ) return "/usr/bin/$appname"; // For Fedora
		if ( file_exists("/usr/local/bin/$appname") ) return "/usr/local/bin/$appname"; // For most everythibng else

		$which = shell_exec("which  $appname");
		if ( $which ) return trim($which);
	
		return $appname;
	};

	function check_folder($foldername, $filename = "") {
		// Try to create a folder we need
		if ( !is_dir($foldername) ) mkdir($foldername); 
		if ( !is_dir($foldername) ) {
			if ( $filename != "" ) $withfile = " (with a file <b>$filename</b> inside)";
			fatal("Cannot create the folder <b>$foldername</b> - please check permissions or create it by hand$withfile"); 		
		} else {
			if ( !file_exists("$foldername/$filename") ) touch("$foldername/$filename");
			if ( !file_exists("$foldername/$filename") ) 
				fatal("Cannot create the file <b>$filename</b> inside the folder <b>$foldername</b> - please check permissions or create it by hand"); 		
		};
	};
	
	function saveMyXML ( $xmltxt, $filename, $noempties = true ) {
		// Safe store XML to file, and keep a backup
		global $xmlfolder;
		libxml_use_internal_errors(true);

		if ( $noempties ) {
			$xmltxt = preg_replace( "/ [a-zA-Z0-9]+=\"\"/", "", $xmltxt );
		};

		$xml = simplexml_load_string($xmltxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( $xml === false ) {
			# The input is not XML (anymore) - throw an error and do not save
			print "<h1>Oops</h1> <p>There is an error in the XML and we will not save. 
					The error messages are shows below. 
					More info can be found by looking at the source code of this page.</p>";

			foreach(libxml_get_errors() as $error) {
				echo "<p>&nbsp; &nbsp; ", $error->message;
				if ( preg_match("/error line (\d+) /", $error->message, $matches) ) { 
					$linenr = $matches[1] - 1;
					if ( $linenr != 0 ) $markers .= "editor.resize(true); editor.scrollToLine($linenr, true, true, function () {}); editor.getSession().addMarker(new Range($linenr, 0, $linenr, 2000), 'warning', 'line', true);";
				}
			}

			$xmltxt = htmlentities($xmltxt);
			if ( $markers ) print "<p>The (first) conflicting line has been highlighted";
	
			print "<p>Click <a href='index.php?action=file&cid=$filename'>here</a> to go (back) to view mode";

			exit;
		};
		
		# First - make a once-a-day backup
		$date = date("Ymd"); 
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $filename );
		$buname = preg_replace ( "/.*\//", "", $buname );
		if ( !file_exists("backups") ) { mkdir("backups"); };
		if ( !file_exists("backups/$buname") ) {
			copy ( "$xmlfolder/$filename", "backups/$buname");
		};
		
		# Now, make a safe XML text out of this and save it to file
		file_put_contents("$xmlfolder/$filename", $xml->asXML());
	};

	function unshorthand ( $shorthand ) {
		global $user; global $username; global $xml;
		
		foreach ( explode("\n", file_get_contents("Resources/shorthand.tab")) as $line ) {
			list ( $from, $desc, $to ) = explode ( "\t", $line ); $of = $from;
			if ( $from ) {
				$from = str_replace("(", "\(", $from);
				$from = str_replace("[", "\[", $from);
				$from = str_replace(")", "\)", $from);
				$from = str_replace("]", "\]", $from);
				# $from = preg_replace("/#((\\?).)/", "([^\\2]*?)\\1", $from); 
				$from = str_replace("#", "(.*?)", $from); 
				# print "\n<p>$from ($of) => $to = $shorthand";
				$shorthand = preg_replace ( "/$from/", $to, $shorthand );
			};
		};
		
		# Now that we have this - we need to replace [editor] by the user currently logged in
		$edithand = $user['short']; 
		$shorthand = preg_replace ( "/\[editor\]/", $edithand, $shorthand );
	
		# And we need to do XPath lookups where necessary
		$shorthand = xpathrun ( $shorthand, $xml );
	
		$shorthand = preg_replace ( "/\n/", "<lb/>\n", $shorthand ); # By default, make linebreaks meaningful

		return $shorthand;
	};

	function namespacemake ( $text ) {
		global $settings;
		if ( $settings['xmlfile']['protect'] ) $protects = explode(",", $settings['xmlfile']['protect']);
		else $protects = array ( "head", "opener", "address", "div", "option", "image" );
		# prefix HTML element in XML with xml namespace
		foreach ( $protects as $tagname ) {
			if ( !$tagname ) continue;
			$text = preg_replace( "/<$tagname([ >])/i", "<tei_$tagname$1", $text );
			$text = preg_replace( "/<\/$tagname>/i", "</tei_$tagname>", $text );
		};
		# Kill the namespace in the XML since SimpleXML does not like it
		$text = preg_replace("/ xmlns=\"[^\"]+\"/", "", $text);
		return $text;
	};
	
	// log errors (optional)
	function errorlog ( $type, $txt, $action='' ) {
	 $logfile = "log/error.log";

			if ( !$action ) $action  = $_GET['action'];

			$ip = $_SERVER['REMOTE_ADDR'];
			$date = date ( "d/M/Y:h:i:s" );
			$line = "$ip\t$date\t$type\t$action\t$txt\n";

			if ( $fh = fopen($logfile, 'a') ) { 
					fwrite($fh, $line);
					fclose ( $fh );
			} else print "<!-- error opening log file -->";
	};

	## Log into logfile
	function messagelog ( $txt ) {
		global $settings;
		
		if ( is_array( $settings['log']['errorlog']) ) $logfile = $settings['log']['errorlog']['filename'];
		
		$ip = $_SERVER['REMOTE_ADDR'];
		$date = date ( "d/M/Y:h:i:s" );
		$line = "$ip\t$date\t$txt\n";
		
		if ( $fh = fopen($logfile, 'a') ) { 
			fwrite($fh, $line);
			fclose ( $fh );
		} else {
			 print "<!-- error opening log file: $logfile -->";
			 // exit;
		};
	};

	## Log into logfile
	function actionlog ( $txt, $action='' ) {
		global $username, $setting;
		
		$logfile = $settings['log']['errorlog'];
		if (!$logfile) { return -1; };
		
		if ( !$username ) $username  = "guest";
		if ( !$action ) $action  = $_GET['action'];
		
		$ip = $_SERVER['REMOTE_ADDR'];
		$date = date ( "d/M/Y:h:i:s" );
		$line = "$ip\t$username\t$date\t$action\t$txt\n";
		
		if ( $fh = fopen($logfile, 'a') ) { 
			fwrite($fh, $line);
			fclose ( $fh );
		} else print "<!-- error opening log file -->";
	};

	function sentShow ( $sentid, $text, $headpos, $args ) {

		$words = explode ( " ", $text ); 
		$words[$headpos] = "<span class='head'>".$words[$headpos]."</span>";

		$tmp = explode ( ";", $args );
		foreach ( $tmp as $key => $tmp2 ) {
			$an = $key+1;
			$tmp3 = explode ( '\+', $tmp2 );
			foreach ( $tmp3 as $key2 => $tmp4 ) {
				$words[$tmp4] = "<span class='filler$an'>".$words[$tmp4]."</span>";
			};
		}; 
		$text = join (" ", $words);
	
		return $text;
	};
	
	function wget ( $server, $request ) {

		global $debug; $htdoc = "";
		if ( $debug ) echo "<h2>TCP/IP Connection</h2><pre>\n";
		
		/* Get the port for the WWW service. */
		$service_port = getservbyname('www', 'tcp');
		
		/* Get the IP address for the target host. */
		$address = gethostbyname( $server );
		
		/* Create a TCP/IP socket. */
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		 if ( $socket < 0) {
		    if ( $debug ) echo "socket_create() failed: reason: " . socket_strerror($socket) . "\n";
		 } else {
		    if ( $debug ) echo "OK.\n";
		 }
		
		 if ( $debug ) echo "Attempting to connect to '$address' on port '$service_port'...";
		$result = socket_connect($socket, $address, $service_port);
		 if ($result < 0) {
		    if ( $debug ) echo "socket_connect() failed.\nReason: ($result) " . socket_strerror($result) . "\n";
		 } else {
		    if ( $debug ) echo "OK.\n";
		 }
		
		if ( substr( $request, 0, 1) != "/" ) $request = "/$request";
		$in = "GET $request HTTP/1.0\r\n";
		$in .= "Host: $server\r\n\r\n";
		$out = '';
		
		 if ( $debug ) echo "Sending HTTP request ($request) ...";
		 socket_write($socket, $in, strlen($in));
		 if ( $debug ) echo "OK.\n";
		
		 if ( $debug ) echo "Reading response:\n\n";
		 while ( $out = socket_read($socket, 2048) ) {
		    if ( $debug ) echo $out;
		 	$htdoc .= $out;
		 }
		
		 if ( $debug ) echo "Closing socket...";
		 socket_close($socket);
		 if ( $debug ) echo "OK.\n\n</pre>";
		 
		return $htdoc;
	};
	
	
	function asciify($input) {
		$output = strtolower($input); 
		$output = preg_replace("/^(de|van|het|of|le|la|les) /", "", $output);
		$output = preg_replace("/^l\\\\'/", "", $output);
		$output = preg_replace("/&([eauoi])(acute|grace|uml);/", "\\1", $output);
		$output = preg_replace("/&ccedil;/", "c", $output);
		$output = preg_replace("/[^a-z0-9]/", "", $output);
		return $output;
	}

	function debug ($array) {
		# print the key => value pairs from an array
	
		while ( list ( $key, $val ) = each ( $array ) ) {
			$debug .= $key." => ".$val."<BR>";
			if ( is_array ( $val ) ) { $debug .= "*: "; debug ( $val ); };
		};
	};

	
	function parseList ( $list ) {
		$temp = explode ( ", ", $list );
		while ( list ( $key, $val ) = each ( $temp ) ) {
			list ( $key2, $val2 ) = explode ( ":", $val );
			$array[$key2] = $val2;
		};
		return $array;
	};

	function screentype () {
		if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
			return "tablet";
		}
 
		if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
			return "mobile";
		}
 
		if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
			return "mobile";
		}
 
		$mobile_ua = strtolower(substr($_SERVER['HTTP_USER_AGENT'], 0, 4));
		$mobile_agents = array(
			'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
			'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
			'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
			'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
			'newt','noki','palm','pana','pant','phil','play','port','prox',
			'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
			'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
			'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
			'wapr','webc','winw','winw','xda ','xda-');
 
		if (in_array($mobile_ua,$mobile_agents)) {
			return "mobile";
		}
 
		if (strpos(strtolower($_SERVER['HTTP_USER_AGENT']),'opera mini') > 0) {
			return "mobile";
			//Check for tablets on opera mini alternative headers
			$stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA'])?$_SERVER['HTTP_X_OPERAMINI_PHONE_UA']:(isset($_SERVER['HTTP_DEVICE_STOCK_UA'])?$_SERVER['HTTP_DEVICE_STOCK_UA']:''));
			if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
			return "tablet";
			}
		};
		
		return "desktop";
		
	};

	function fatal ($txt) {
		global $username;
		$time = time();
		if (!is_dir("tmp")) mkdir("tmp"); 
		file_put_contents("tmp/error_$time.txt", $txt);
		print "<h1>Fatal Error</h1><p>A fatal error has occurred
			<script language=Javascript>top.location='index.php?action=error&msg=$time';</script>";
		exit;
	};

	function forminherit ( $node, $form, $rich = false ) {
		# Calculate inherited form
		global $settings;
		if ( $settings['xmlfile']['inherit'] == "default" ) {
			$inheritlist = array ('form', 'fform', 'nform', 'dform');
			array_reverse($inheritlist);
			foreach ( $inheritlist as $try ) {
				if ( $node[$try] != "" ) return "<span class='p-$try'>".$node[$try]."</span>";
			};
		} else {
			$try = $form;
			while ( $try != "" ) {
				if ( $node[$try] != "" ) {
					if ( $rich ) return "<span class='p-$try'>".$node[$try]."</span>";
					else return $node[$try];
				};
				$trfrom = $settings['xmlfile']['pattributes']['forms'][$try]['transliterate'];
				if ( $trfrom && $settings['transliteration'] ) {
					return transliterate(forminherit($node, $trfrom));
				};

				$try = $settings['xmlfile']['pattributes']['forms'][$try]['inherit'];
			};
		};
		return "$node";
	};

	function transliterate ( $text ) {
		global $settings;
		if ( !is_array($settings['transliteration']) ) return $text;
				
		foreach ( $settings['transliteration'] as $key => $item ) {
			$from = $item['from'];
			$translitstr[$from] = $item['to'];
		};
			
		if ( is_array($translitstr) ) $transtxt = strtr($text, $translitstr); 
		else $transtxt = $text;
		
		return $transtxt;
	};

	function array2json ( $array ) {
		$json = "{";
		if ( !is_array($array) ) return "''";
		$sep = "";
		foreach ( $array as $key => $val ) {
			$key = str_replace("'", "\'", $key);
			if ( is_array ($val) ) {
				$json .= "$sep\n'$key':".array2json($val);
			} else {
				$val = str_replace("'", "\'", $val);
				$json .= "$sep '$key':'$val'";
			};
			$sep = ", ";
		};
		$json .= "}";
	
		return $json;

	};

	if (!function_exists("rstrpos")) {
	   function rstrpos($haystack,$needle,$offset=0) {
		  $tot  = strlen($haystack);
		  $pos = strpos(strrev($haystack), strrev($needle), $tot-$offset );
		  
		  if ( !$pos || $pos == -1 )
		  	return ""; 
		  else {
		  	$pos = $tot - $pos - strlen($needle);
		  	return $pos;
		  };
	   }
	}	
	
	function innerXML($node) {
		$content="";
		foreach($node->children() as $child)
			$content .= $child->asXml();
		return $content;
	}
	
	function xpathnode ( $xml, $xquery) {
		$dom = dom_import_simplexml($xml)->ownerDocument; #->ownerDocument		
		$settingsdom = createnode($dom, $xquery);
		$resnode = current($xml->xpath($xquery));	
		return $resnode;
	};
	
	function createnode ($xml, $xquery) {
		# See if XML has a node matching the XPath, if not - create it
		global $verbose;
			if ( $verbose ) { print "\n<p>Creating node $xquery"; };
	
		$xpath = new DOMXpath($xml);

		$result = $xpath->query($xquery); 
		if ( $result->length ) {
			if ( $verbose ) { print "\n<p>Node exists ($xquery) - returning"; };
			return $xml;
		};
		
		if ( preg_match("/^(.*)\/@([^\/]*?)$/", $xquery, $matches) ) {
			$before = $matches[1];
			$new = $matches[2]; 
			$res = createnode($xml, $before); 
			$res = $xpath->query($before)->item(0); 
			$res->setAttribute($new, ""); 
		} else if ( preg_match("/^(.*)\/(.*?)$/", $xquery, $matches) ) {
		
			// create the node type after the last / inside the xpath before that
			// create the inner node again when needed
			$before = $matches[1];
			$new = $matches[2];
			if ( $before == "/" ) { print "\n<p>Non-rooted node $xquery does not exist - cannot create"; return -1; };
			# if ( $before == "" ) { print "\n<p>Reached root node - cannot create"; return -1; };
			$res = createnode($xml, $before);
			if ( $res == -1 ) { return -1; };

			$newatt = $newval = "";
			if ( preg_match("/^(.*)\[([^\]]+)\]$/", $new, $matches2) ) { 
				$new = $matches2[1]; $newrest = $matches2[2];
				if ( $verbose ) { print "\n<p>Node restriction: $newrest"; };
				if ( preg_match("/\@([a-z][a-z0=9_]*)=['\"](.*?)['\"]/", $newrest, $matches3) ) { 
					$newatt = $matches3[1]; $newval = $matches3[2]; 
				};
			};

			$result = $xpath->query($before); 
			if ( $result->length == 1 ) {
				foreach ( $result as $node ) {
					if ( substr($new, 0, 1) == '@' ) {
						# This should only happen if we find an attribute in our XPath, which should never happen
						if ( $verbose ) { print "\n<p>Setting value for node of $att to x"; };
						$att = substr($new, 1); 
						$node->setAttribute($att, 'x');
					} else {
						if ( $verbose ) { print "\n<p>Creating a node $new inside $before"; };
						$newelm = $xml->createElement($new, '');
						if ( $newatt ) {
							if ( $verbose ) { print "\n<p>Setting value for node of $newatt to $newval"; };
							$newelm->setAttribute($newatt, $newval);
						};
						if ( $verbose ) { print "\n<p>New node: ".htmlentities($newelm->ownerDocument->saveXML($newelm)); };
						$node->appendChild($newelm);
					};
				};
			};
		} else {
			if ( $verbose ) { print "\n<p>Failed to find a node to attach to $xquery - aborting"; };
			return -1;
		}; 
		return $xml;
	};


	function pattsett ( $key ) {
		global $settings, $wordfld;
		if ( $key == "word" && $wordfld ) $key = $wordfld;
		$val = $settings['xmlfile']['pattributes']['forms'][$key];
		if ( $val != "" ) return $val;
		$val = $settings['xmlfile']['pattributes']['tags'][$key];
		if ( $val != "" ) return $val;

		# Now try without the text_ or such
		if ( preg_match ("/^(.*)_(.*?)$/", $key, $matches ) ) {
			$key2 = $matches[2]; $keytype = $matches[1];
			$val = $settings['cqp']['sattributes'][$key2];
			if ( $val != "" ) return $val;
			$val = $settings['cqp']['sattributes'][$keytype][$key2];
			if ( $val != "" ) return $val;
		};
	};

	function pattname ( $key, $dolang = true ) {
		global $settings, $wordfld;
		$cqpattname = $settings['cqp']['pattributes'][$key]['display'];
		if ( $cqpattname ) return $cqpattname;
		$pattfld = pattsett($key);
		if ( $pattfld ) {
			$name = $pattfld['long'] or $name = $pattfld['display'];
			return $name;
		};
				
		if ( $dolang ) return $key;
		return "<i>$key</i>";
	};

	if (!function_exists('password_hash')) {
		# For older versions of PHP, use crypt for password_hash
		function password_hash($pwd, $salt) {
			return crypt($pwd, "teitokdefaultsalt");
		};
		function password_verify($pwd1, $pwd2) { 
			if ( $pwd2 == password_hash($pwd1, DEFAULT_PASSWORD) ) return true;
			return false;
		};		
	};

	if (!function_exists('mb_convert_encoding')) {
		# When not defined (why??) make a similar function
		function mb_convert_encoding ( $string, $to, $from = "UTF-8") {
			return iconv($from, $to, $string);
		};
	};

	if (!function_exists('mb_strlen')) {
		# When not defined (why??) make a similar function
		function mb_strlen ( $string ) {
			return strlen( utf8_decode( $string ) );
		};
	};

	function isSecure() {
	  return
		(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| $_SERVER['SERVER_PORT'] == 443;
	}		

	function is_attribute($node) {
		$tmp = $node->asXML();
		return !( $tmp[0] == "<");
	};

	function findnode ( $xpath ) {
		global $setdef;
		$defx = "/ttsettings"; $xpath = str_replace("/ttsettings/", "", $xpath);
		foreach ( explode ( "/", $xpath ) as $xpp ) {
			if ( preg_match("/item\[@key=\"(.*?)\"\]/", $xpp, $matches ) ) {
				$xppt = "list";
			} else if ( preg_match("/@(.*)/", $xpp, $matches ) ) {
				$xppt = "att[@key=\"{$matches[1]}\"]";
			} else {
				$xppt = "item[@key=\"$xpp\"]";
			};
			
			$defx .= "/".$xppt;
		};
		
		$tmp = $setdef->xpath($defx); $defnode = $tmp[0];
		
		return $defnode;
	};
		
	function makexpath ( $node ) {
		global $action;
		if ( $action == "adminsettings" ) $xmroot = "ttsettings"; else $xmroot = "TEI";
		$tn = $node; 
		if ( is_attribute($node) ) {
			$xpath = "/@".$node->getName();
			$tmp = $node->xpath(".."); $tn = $tmp[0];
		}; $c=0;
		while ( $tn->getName() != $xmroot && $c < 10 ) {
			$c++;
			$nn = $tn->getName();
			if ( $nn == "item" ) { 
				if ( $tn['key'] == "" ) $nn = "{$nn}[not(@key) or @key=\"\"]"; 
				else $nn = "{$nn}[@key=\"".$tn['key']."\"]"; 
			};
			$xpath = "/$nn".$xpath;
			$tmp = $tn->xpath(".."); $tn = $tmp[0];
		};
		return "/$xmroot$xpath";
	};


	function execsafe( $cmd ) {
		# Make sure commands passed to shell_exec in brackets are safe
		
		# Remove all brackets 
		$cmd = str_replace("'", '&#039;', $cmd);
		
		return $cmd;
	};

	function xmlflatten ( $xml, $int = 0 ) {
		global $maintext; 
		if ( !$xml ) return "";
	
		if ( $xml->attributes() ) 
		foreach ( $xml->attributes() as $atn => $atv ) {
			$flatxml[$atn] = $atv."";
		};

		if ( $int && $xml.""  != "" ) { $flatxml['(text)'] = $xml.""; };

		foreach ( $xml->children() as $node ) {
			$chn = "".$node->getName();
			if ( $node['id'] ) $key = $node['id']."";
			else if ( $chn == "item" ) {
				if ( $node['key'] ) $key = $node['key']."";
				else { $icnt++; $key = $icnt; };
			} else $key = $chn;
			
			$flatxml[$key] = xmlflatten($node);
		};
	
		return $flatxml;
	};

	function replacenode ( $oldrec, $newcode ) {
	
		# Check if this new person is valid XML
		$use_errors = libxml_use_internal_errors(true);
		$newxr = simplexml_load_string($newcode);
		if ( $newxr === false ) {
			foreach(libxml_get_errors() as $error) {
				$errors .= $error->message.";";
			}
			fatal ( "XML Error in record. Please go back and make sure to input valid XML<br>".htmlentities($newcode)." $errors");
		};
		
		if ( $oldrec === false ) fatal("No record to replace");

		$domToChange = dom_import_simplexml($oldrec);
		$domReplace  = dom_import_simplexml($newxr);
		$nodeImport  = $domToChange->ownerDocument->importNode($domReplace, TRUE);
		$domToChange->parentNode->replaceChild($nodeImport, $domToChange);
			
	};
	
	function array2xml($array, $root = 'root') {
		$xml = simplexml_load_string("<$root/>");
		$dom = dom_import_simplexml($xml);
		foreach ( $array as $key => $val ) {
			if ( is_array($val) ) {
				$child = array2xml($val, $key);
				$domc = dom_import_simplexml($child);
				$domi = $dom->ownerDocument->importNode($domc, TRUE);
				$dom->appendChild($domi);
			} else {
				$xml[$key] = $val;
			};
		};
		return $xml;
	};	
	
	function makexml ($node, $opts = array()) {
		global $nospace, $ttxml, $settings;
		$xmltxt = $node->asXML();
		
		# For implicit content nodes, add the content
		$nn = $node->getName();
		$corresp = $opts['corresp'] or $corresp = $settings['cqp']['sattributes'][$nn]['toklist'] or $corresp="sameAs";
		if ( $opts[$corresp] || ( !$node->children && $node[$corresp] ) ) {
			$toklist = explode(" ", $node[$corresp]);
			$tok1 = substr($toklist[0],1); 
			$tok2 = substr(end($toklist),1);
			$raw = $ttxml->raw; if ( $raw == "" ) { 
				$tmp = current($node->xpath("./ancestor::text"));
				$raw = $tmp->asXML(); 
			};
			$p1 = strpos($raw, " id=\"$tok1\""); $p1 = rstrpos($raw, "<tok", $p1);
			$p2 = strpos($raw, " id=\"$tok2\""); $p2 = strpos($raw, "</tok>", $p2)+6;
			$impl = substr($raw,$p1,$p2-$p1);
			$xmltxt .= $impl;
		};
		
		# Protect empty elements
		$xmltxt = preg_replace( "/<([^> ]+)([^>]*)\/>/", "<\\1\\2></\\1>", $xmltxt );
		# $xmltxt = str_replace( "&nbsp;", "&#xA0;", $xmltxt );
		
		# Deal with @join type spacing
		if  ( $ttxml->nospace == 2 || $nospace == 2 || $style == "nospace" ) {
			$xmltxt = str_replace( "</tok>", "</tok><njs> </njs>", $xmltxt );
			$xmltxt = preg_replace( "/(join=\"right\"((?!<tok).)+<\/tok>)<njs> <\/njs>/", "\\1", $xmltxt );
		} else if  ( $ttxml->nospace == 2 || $nospace == 2 || $style == "nospace" ) {
 			$xmltxt = str_replace( "<tok ", "<njs> </njs><tok ", $xmltxt );
 			$xmltxt = preg_replace( "/<njs> <\/njs>(<tok(.(?!<\/tok))+join=\"left\")/", "\\1", $xmltxt );
		};
		return $xmltxt;
	};

	function makesettings ($settings) {
		$merged = new SimpleXMLElement("<ttsettings/>");
		$cqp = $merged->addChild("cqp");
		$patts = $cqp->addChild("pattributes");
		foreach ( $settings['cqp'] as $key => $val ) {
			if ( !is_array($val) ) { $cqp[$key] = $val; };
		};
		foreach ( $settings['cqp']['pattributes'] as $key => $val ) {
			$item = $patts->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				$item[$key2] = $val2;
			};
		};
		$satts = $cqp->addChild("sattributes");
		foreach ( $settings['cqp']['sattributes'] as $key => $val ) {
			$item = $satts->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				if ( is_array($val2) ) {
					$item2 = $item->addChild("item");				
					foreach ( $val2 as $key3 => $val3 ) {
						$item2[$key3] = $val3;
					};
				} else {
					$item[$key2] = $val2;
				};
			};
		};
		$anns = $cqp->addChild("annotations");
		foreach ( $settings['cqp']['annotations'] as $key => $val ) {
			$item = $anns->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				if ( is_array($val2) ) {
					$item2 = $item->addChild("item");				
					foreach ( $val2 as $key3 => $val3 ) {
						$item2[$key3] = $val3;
					};
				} else {
					$item[$key2] = $val2;
				};
			};
		};
		# We need to also copy the xmlfile for the inheritance
		$xmlf = $merged->addChild("xmlfile");
		$patts = $xmlf->addChild("pattributes");
		foreach ( $settings['xmlfile'] as $key => $val ) {
			if ( !is_array($val) ) { $xmlf[$key] = $val; };
		};
		$forms = $patts->addChild("forms");
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $val ) {
			$item = $forms->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				$item[$key2] = $val2;
			};
		};
		$tags = $patts->addChild("tags");
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $val ) {
			$item = $tags->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				$item[$key2] = $val2;
			};
		};
		$satts = $xmlf->addChild("sattributes");
		foreach ( $settings['xmlfile']['sattributes'] as $key => $val ) {
			$item = $satts->addChild("item");
			foreach ( $val as $key2 => $val2 ) {
				if ( is_array($val2) ) {
					$item2 = $item->addChild("item");				
					foreach ( $val2 as $key3 => $val3 ) {
						$item2[$key3] = $val3;
					};
				} else {
					$item[$key2] = $val2;
				};
			};
		};
		# And we need to copy the base for the URL
		if ( $settings['defaults']['base']['url'] ) {
			$deff = $merged->addChild("defaults");
			$basef = $deff->addChild("base");
			$basef["url"] = $settings['defaults']['base']['url'];
		};
		
		return $merged;
	};
	
	function modurl ( $attlist, $vallist ) {
		# Change some values in the current URL
		$baseurl = $_SERVER['REQUEST_URI'];
		$newget = $_GET;
		$newurl = preg_replace("/.*\//", "", $_SERVER['SCRIPT_NAME']) or $newurl = "index.php";
		if ( is_array($attlist) ) {
			foreach ( $attlist as $key => $val ) {
				print "<p>Settings $key to $val";
				$newget[$key] = $val;
			};
		} else {
			$attar = explode(",", $attlist);
			$valar = explode(",", $vallist);
			foreach ( $attar as $key => $val ) {
				$newget[$val] = $valar[$key];			
			};
		};
		$sep = "?";
		foreach ( $newget as $key => $val ) {
			$newurl .= $sep.$key."=".urlencode($val);
			$sep = "&";
		};
		return $newurl;
	};

	function sentbyid($text, $eid) {
		# Use an explicit list of sentence IDs 
		global $settings;
		$cqpcorpus = $settings['cqp']['corpus'] or $cqpcorpus = "tt-".$foldername;
		$cqpcorpus = strtoupper($cqpcorpus);

		$subcorpus = $_SESSION['subc'] or $subcorpus = $_GET['subc'] or $subcorpus = "";
		if ( $subcorpus ) { $subf = "/$subcorpus"; };

		if ( !file_exists("cqp$subf/slist.csv") ) {
			$cql = "Matches = <s> []+ </s>; tabulate Matches match text_id, match s_id, match, matchend;";
			$cmd = "echo '$cql' | cqp -c -r cqp -D $cqpcorpus > cqp/slist.csv";
			shell_exec($cmd);
		};
		
		$cmd = "grep '$text\t$eid\t' cqp/slist.csv";
		$poss = shell_exec($cmd);
		
		list ( $fileid, $elementid, $leftpos, $rightpos ) = explode("\t", $poss);
		if ( !$rightpos ) {
			return "";
		};
		
		$xidxcmd = findapp('tt-cwb-xidx'); $cqpfolder = "cpq";
		$cmd = "$xidxcmd --filename='$fileid' $expand $leftpos $rightpos";
		$result = shell_exec($cmd);
	
		return $result;
	};

	if (!function_exists('str_contains')) {
		function str_contains($haystack, $needle) {
			return $needle !== '' && mb_strpos($haystack, $needle) !== false;
		}
	}
	
?>