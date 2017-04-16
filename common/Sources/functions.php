<?php
	// Centralized TEITOK functions
	// (c) Maarten Janssen, 2015
	
	// Check if we are logged in
	function check_login ( $type = "user" ) {
		global $user, $username;
		if ( !$username ) { 
			print "<p>This function is for editing users only
				<script language=Javascript>top.location = 'index.php?action=notli&username=$username';</script>
			"; exit;
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
			if ( $to == "" && !$showempties ) { 
				$text = preg_replace("/<tr><t[dh][^>]*>[^>]+(<[^>]+>)+$from(<[^>]+>)+/", "$to", $text); # For rows
			};
			
			$text = preg_replace("/$from/", "$to", $text);
		};
		
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

	function i18n ( $text ) {
		global $lang; global $i18n; global $langprefix; global $deflang; global $debug; global $ttroot;
		
		if ( strpos("{%", $text) == -1 ) return $text; # If there is nothing to translate - return to save time
		
		if ( !$i18n ) { # Read the translation defs - but do so only once
			if ( file_exists("$ttroot/common/Sources/i18n/i18n_$lang.php") ) {
				include("$ttroot/common/Sources/i18n/i18n_$lang.php");
			}

			# Now read the local defs - which can override all global settings
			if ( file_exists("Resources/i18n_$lang.txt") ) {
				foreach ( explode ( "\n", file_get_contents("Resources/i18n_$lang.txt") ) as $line ) {
					list ( $org, $trans ) = explode ( "\t", $line );
					$i18n[$org] = $trans;
				};
			};
		};
				
		preg_match_all ( "/\{%([^\}]+)\}/", $text, $matches );		

		foreach ( $matches[0] as $key => $match ) {
			$txtel = $matches[1][$key];
			$from = preg_quote($match, '/'); 
			if ( $i18n[$txtel] != "" ) {
				$to = $i18n[$txtel];
			} else {
				$to = $txtel; # If we have no translation, just remove the brackets
				$furl = $_SERVER['REQUEST_URI'] or $furl = 1;
				if ($lang != "en" || strstr($txtel,'-') ) $_SESSION['mistrans'][$lang][$txtel] = $furl; # Store the missing translation in a cookie
			}
			$to = str_replace('"', '&quot;', $to);
			$to = preg_replace("/\r/", '', $to); # Hidden \r make Javascript stop working
			$text = preg_replace("/$from/", "$to", $text);
		};
	
		# Finally, also prefix all uses of "index.php" with the language 
		if ( $langprefix && $lang != $deflang ) {
			$text = preg_replace ( "/([\"'])index\.php/", "\\1$lang/index.php", $text );
		};
		
		return $text;
	};
	
	function getlangfile ( $ffid, $common = false, $flang = null ) {
		global $lang; global $settings; global $getlangfile_lastfile; 
		if ( $flang === null ) $flang = $lang;
		$deflang = $settings['languages']['default'] or $deflang = "en";
		# print "Pages/{$ffid}-$flang.html"; exit;
		
		if ( file_exists("Pages/{$ffid}-$flang.html") ) {
			$getlangfile_lastfile = "Pages/{$ffid}-$flang.html";
			return file_get_contents($getlangfile_lastfile);
		} else if ( file_exists("Pages/{$ffid}.html") ) {
			$getlangfile_lastfile = "Pages/{$ffid}.html";
			return file_get_contents($getlangfile_lastfile);
		} else if ( file_exists("Pages/{$ffid}-$deflang.html") ) {
			$getlangfile_lastfile = "Pages/{$ffid}-$deflang.html";
			return file_get_contents($getlangfile_lastfile);
		} else if ( $common && file_exists("$ttroot/common/Pages/{$ffid}-$flang.html") ) {
			$getlangfile_lastfile = "$ttroot/common/Pages/{$ffid}-$fflang.html";
			return file_get_contents($getlangfile_lastfile);
		} else if ( $common && file_exists("$ttroot/common/Pages/{$ffid}.html") ) {
			$getlangfile_lastfile = "$ttroot/common/Pages/{$ffid}.html";
			return file_get_contents($getlangfile_lastfile);
		};
		return "";	
	};

	function saveMyXML ( $xmltxt, $filename, $noempties = true ) {
		// Safe store XML to file, and keep a backup
		global $xmlfolder;
		libxml_use_internal_errors(true);

		if ( $noempties ) {
			$xmltxt = preg_replace( "/[a-z]+=\"\"/", "", $xmltxt );
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
	
			print "<p>Click <a href='index.php?action=edit&cid=$filename'>here</a> to go (back) to view mode";
	
			print "
				<div id=\"editor\" style='width: 100%; height: 300px;'>".$xmltxt."</div>
			
				<script src=\"ace/ace.js\" type=\"text/javascript\" charset=\"utf-8\"></script>
				<style>.warning
				{
					background: rgba(255, 255, 50, 0.2);
					position: absolute;
					width: 100% !important;
					left: 0 !important;
				}</style>
				<script>
					var editor = ace.edit(\"editor\");
					editor.setTheme(\"ace/theme/chrome\");
					editor.getSession().setMode(\"ace/mode/xml\");
					editor.setReadOnly(true);
					var Range = ace.require(\"ace/range\").Range;
					$markers
				</script>
			";

			exit;
		};
		
		# First - make a once-a-day backup
		$date = date("Ymd"); 
		$buname = preg_replace ( "/\.xml/", "-$date.xml", $filename );
		$buname = preg_replace ( "/.*\//", "", $buname );
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
		
		# prefix HTML element in XML with xml namespace
		foreach ( array ( "head", "opener", "address", "div", "option" ) as $tagname ) {
			$text = preg_replace( "/<$tagname([ >])/", "<tei:$tagname$1", $text );
			$text = preg_replace( "/<\/$tagname>/", "</tei:$tagname>", $text );
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
		
		$logfile = $settings['log']['errorlog']['filename'];
		
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
		print "<h1>Fatal Error</h1><p>A fatal error has occurred: $txt
			<script language=Javascript>top.location='index.php?action=error&msg=$txt';</script>";
		exit;
	};

	function forminherit ( $node, $form ) {
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
				if ( $node[$try] != "" ) return "<span class='p-$try'>".$node[$try]."</span>";
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

	function getxmlfile ( $fileid ) {
		global $xmlfolder;
		
		$oid = $fileid;
		if ( !preg_match("/\./", $fileid) && $fileid ) $fileid .= ".xml";
		$temp = explode ( '/', $fileid );
		$xmlid = array_pop($temp); $xmlid = preg_replace ( "/\.xml/", "", $xmlid );
	
		if ( !$fileid ) { 
			fatal ( "No XML file selected." );  
		};

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
		return $xml;
		
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
?>