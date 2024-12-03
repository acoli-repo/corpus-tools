<?php

	# TEITOK API main - query, download, reindex, nlp

	# see if we are calling this from a command line interface
	if ( strpos($_SERVER['HTTP_USER_AGENT'], "curl/") !== false ) $cmdln = true;
	if ( strpos($_SERVER['HTTP_USER_AGENT'], "wget/") !== false ) $cmdln = true;

	if ( !$toolroot ) $toolroot = getset('defaults/base/tools');
	if ( !$toolroot ||  !file_exists("$toolroot/Scripts/conllu2teitok.pl") ) {
		$toolroot = str_replace("Scripts/conllu2teitok.pl", "", shell_exec("locate conllu2teitok.pl"));
	};
	if ( !$toolroot || !file_exists("$toolroot/Scripts/conllu2teitok.pl") ) $toolroot = "/home/git/teitok-tools/";
	if ( !$username && is_array($_SESSION[$sessionvar.'-api']) ) $username = $_SESSION[$sessionvar.'-api']['username'];

	if ( !file_exists("$toolroot/Scripts/conllu2teitok.pl") ) {
		if ( $username ) {
			fatal("teitok-tools repository not found (checked $toolroot)");
		} else {
			fatal("teitok-tools repository not found");
		};
	};
	
	header('Content-Type: application/json; charset=utf-8');

	if ( $_GET['cid'] ) {
	
		$cid = $_GET['cid'];
		if ( !file_exists($cid) ) {
			if ( substr($cid,0,9) != "xmlfiles/" )
			$cid = "xmlfiles/$cid";
			if ( substr($cid,-4) != ".xml" )
			$cid = "$cid.xml";
		};
		if ( !file_exists($cid) ) {
    		require ("$ttroot/common/Sources/ttxml.php");
		    $ttxml = new TTXML($cid, false);
		    $cid = $ttxml->filename;
		};
		if ( !file_exists($cid) ) {	
			print '{"error": "no such file: '.$_GET['cid'].'"}'; exit;
		};
	};
	
	if ( $act == "login" ) {
	
		$token = $_POST['token'] or $token = $_GET['token'];
		
		if ( $token ) {
			$username = check_token();
			$_SESSION[$sessionvar.'-api']['username'] = $username;
		} else {
			$_POST['login'] = $_POST['user'];
			$_POST['password'] = $_POST['pw'];
			include("$ttroot/common/Sources/login.php");
		};
		$accesstoken = session_id();
		if ( !$username ) 
			print "{\"error\": \"invalid login/password\"}";
		else 
			print "{\"sessionId\": \"$accesstoken\", \"userName\": \"$username\"}";
		exit;
	
	} else if ( $act == "query" ) {
	
		if ( file_exists("Sources/apiquery.php") )
			include("Sources/apiquery.php");
		if ( file_exists("$sharedfolder/Sources/apiquery.php") )
			include("$sharedfolder/Sources/apiquery.php");
		else
			include("$ttroot/common/Sources/apiquery.php");
		exit;
	
	} else if ( $act == "download" ) {
	
		# Check whether download is barred
		if (  getset('download/admin') == "1" ) 
			$username = check_token();
		if ( getset('download/disabled') == "1" ) 
			{ fatal ("Download of files not permitted"); };

		$format = $_GET['format'];
		$form = $_GET['form'];
			
		$baseid = preg_replace("/.*\//", "", $cid);
		$baseid = str_replace(".xml", "", $baseid);
		if ( !$cid ) { 
			print '{"error": "no filename provided"}';
			exit;
		}
		check_folder("tmp");
		if ( $format == "conllu" ) {
			$tmp = "tmp/".time().".conllu";
			shell_exec("perl $toolroot/Scripts/teitok2conllu.pl --file=$cid --output=$tmp --form=$form");
			header('Content-Type: text/plain; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.conllu\"");
			passthru("cat $tmp");
			unlink($tmp);
		} else if ( $format == "vrt" ) {
			$tmp = "tmp/".time().".vrt";
			shell_exec("perl $toolroot/Scripts/teitok2vrt.pl --file=$cid --output=$tmp");
			header('Content-Type: text/plain; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.vrt\"");
			passthru("cat $tmp");
			unlink($tmp);
		} else if ( $format == "cas" ) {
			$tmp = "tmp/".time().".conllu";
			shell_exec("python $toolroot/Scripts/teitok2cas.py --file=$cid --output=$tmp");
			header('Content-Type: text/plain; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.conllu\"");
			passthru("cat $tmp");
			unlink($tmp);
		} else if ( $format == "tcf" ) {
			$tmp = "tmp/".time().".tcf";
			$lang = $_GET['lang'] or $lang = getset('defaults/lang');
			shell_exec("perl $toolroot/Scripts/teitok2tcf.pl --lang=$lang --file=$cid --output=$tmp");
			header('Content-Type: text/plain; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.tcf\"");
			passthru("cat $tmp");
			unlink($tmp);
		} else if ( $format == "tei" ) {
			$tmp = "tmp/".time()."-p5.xml";
			shell_exec("perl $toolroot/Scripts/teitok2p5.pl --file=$cid --output=$tmp");
			header('Content-Type: text/plain; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.xml\"");
			passthru("cat $tmp");
			unlink($tmp);
		} else {
			# Default to TEITOK/XML
			header('Content-Type: application/xml; charset=utf-8');
			header("Content-Disposition: attachment; filename=\"$baseid.xml\"");
			passthru("cat $cid");
		};
		exit;
	
	} else if ( $act == "token" ) {
	
		header('Content-Type: text/html; charset=utf-8');
		check_login();
		$token = hash('sha256', $thisfolder.time().$username);
		if ( !file_exists("Resources/litoks.xml") ) { file_put_contents("Resources/litoks.xml", "<tokens/>"); };
		$litoks = simplexml_load_file("Resources/litoks.xml");
		$newtok = $litoks->addChild('token','');
		$newtok->addAttribute('id',$token);
		$newtok->addAttribute('user',$username);
		$timestamp = time();
		$newtok->addAttribute('time',$timestamp);
		file_put_contents("Resources/litoks.xml", $litoks->asXML());
		$maintext .= "<h1>Login Token Created</h1><p>Your token has been succesfully created  - you will now be redirected to the main page
			<p>Token: $token
			<script>top.location='index.php?action=$action'</script>";
		#$maintext .= showxml($newtok);
		
	} else if ( $act == "list" ) {

		if ( !$username ) $username = check_token(false);
		if ( ( $_GET['token'] || $_COOKIE['PHPSESSID']  || $_GET['PHPSESSID'] ) && !$username ) {
			print '{"error": "invalid token or session"}';
			exit;
		}; 
		
		if ( $username ) {
			if ( $_GET['status'] ) {
				$tmp = $_GET['status'];
				if ( $tmp == "notok" ) $chk = "</tok>";
				if ( $tmp == "notag" ) $chk = " upos=";
				if ( $tmp == "noparse" ) $chk = " head=";
			};
			if ( $_GET['since'] ) {
				$since = preg_replace("/[^0-9: -]/", "", $_GET['since']);
				$datechk = "-newermt \"$since\"";
				$moredata = "\"since\": \"$since\", ";
			};
		};
		if ( $username ) {
			print "{".$moredata."\"files\": ["; $sep  = "";
			$cmd = "find xmlfiles -name '*.xml' $datechk -print";
			$filelist = shell_exec($cmd);
			foreach ( explode("\n", $filelist) as $filename ) {
				if ( $chk && $filename ) {
					$tmp = $filename;
					$cmd = "grep -L '$chk' $filename";
					$filename = trim(shell_exec($cmd));
				};
				if ( $filename ) {
					print $sep."\"$filename\"";
					$sep = ", ";
				};
			};
			print "]}";
		} else {
			print "{\"files\": ["; $sep  = "";
			$filelist = file_get_contents("cqp/text_id.avs");
			foreach ( explode("\0", $filelist) as $file ) {
				$filename = preg_replace("/.*\//", "", $file);
				if ( $filename ) {
					print $sep."\"$filename\"";
					$sep = ", ";
				};
			};
			print "]}";
		};
		exit;
			
	} else if ( $act == "getmeta" ) {
	
		$username = check_token();
		$type = $_GET['type'] or $type = "CQL";
		$query = $_GET['query'];
		if ( !$_GET['query'] ) {
			print '{"error": "missing query"}';
			exit;
		}; 

		if ( $type == "XPath" ) {
			$cmd = "tt-xpath --xpquery='$query' --max=5000 --folder=xmlfiles --header=1";
			$resxml = shell_exec($cmd);
			if ( $_GET['output'] == "xml" ) {
				header('Content-Type: application/xml; charset=utf-8');
				print $resxml; 
				exit;
			};
			$obj = simplexml_load_string($resxml);
			$data = [];
			foreach ( $obj->xpath("//results/*") as $result ) {
				$fileid = $result['fileid'].""; 
				$data[$fileid] = $result."";
			};
			print json_encode($data);
			exit;
		} else if ( $type == "CQL" ) {
			if ( !$cqpcorpus ) {
				$cqpcorpus = getset('cqp/corpus', "tt-".$foldername);
				if ( getset('cqp/subcorpora') ) {
					if ( !$subcorpus ) {
						fatal("No subcorpus selected");
					};
					# $_SESSION['subc'] = $subcorpus;
					$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
					$subcorpustit = "<h2>$corpusname</h2>";
				} else {
					$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
					$cqpfolder = getset('cqp/cqpfolder', "cqp");
				};
			};
			$elm = "text"; if ( preg_match("/^([a-z0-9]+)_([a-z0-9]+)$/", $query, $matches ) ) {
				$elm = $matches[1];
				$tmp = getset("cqp/sattributes/$elm/id"); 
				if ( $elm != "text" && $tmp ) { $elmid = ", match ".$elm."_id"; };
			};
			$cmd = "echo 'Matches = <$elm> []; tabulate Matches match text_id, match $query $elmid' | cqp -r cqp -c -D $cqpcorpus";
			$reslist = shell_exec($cmd);
			$data = [];
			foreach ( explode("\n", $reslist) as $resline ) {
				if ( substr($resline, 0, 11)  == "CQP version" || trim($resline) == "" ) continue;
				list ( $resfile, $resfld, $elmid ) = explode("\t", $resline);
				if ( $elmid ) $resfile .= ":$elmid";
				$data[$resfile] = $resfld."";
			};
			print json_encode($data);
			exit;
		} else {
			print '{"error": "unknown query type"}';
			exit;
		};

		print '{"error": "unexpected error"}'; exit;

	} else if ( $act == "metadata" ) {
	
		$username = check_token();
		
		require("$ttroot/common/Sources/ttxml.php");
		if ( !$_GET['cid'] ) {
			print '{"error": "missing filename"}';
			exit;
		}; 
		$ttxml = new TTXML($_GET['cid'], false);
		if ( !$ttxml ) {
			print '{"error": "invalid XML filename"}';
			exit;
		}; 
		
		$data = $_POST['values']; if ( !$data ) {
			$raw = $_GET['data'] or $raw = $_POST['data'];
			$data = json_decode($raw);
			if ( $raw && !$data ) {
				print '{"error": "invalid JSON data provided"}';
				exit;
			}; 
		};

		if ( !$data ) {
			print '{"error": "missing or invalid metadata"}';
			exit;
		}; 
					
		foreach ( $data as $xp => $val ) {
			$node = xpathnode($ttxml->xml, $xp);
			if ( !is_object($node) ) {
				print '{"error": "unable to create node: ' . $xp . '"}';
			} else {
				$node[0] = $val; # This only works for elements, not for attributes
			};
		};

		$ttxml->save();
		print "{'success': 'metadata successfullly modified for $ttxml->filename'}";
		exit;

	} else if ( $act == "delete" ) {
	
		$username = check_token();
		
		$xmlfile = str_replace("../", "", $_GET['cid']);
		if ( !$xmlfile ) $xmlfile = str_replace("../", "", $_GET['file']);
		if ( !$xmlfile ) $xmlfile = str_replace("../", "", $_GET['folder']);
		if ( !$xmlfile ) {
			print '{"error": "no file specified to delete" }';
			exit;
		};
		if ( substr($xmlfile, 0,9) != "xmlfiles/" ) $xmlfile = "xmlfiles/$xmlfile";
		if ( !file_exists($xmlfile) ) {
			print '{"error": "no such file or folder: '.$xmlfile.'" }';
			exit;
		}; 
		if ( is_dir($xmlfile) ) rmdir($xmlfile);
		else unlink($xmlfile);
		if ( is_dir($xmlfile) ) {
			print '{"error": "failed to delete '.$xmlfile.' (not empty or not allowed)"}';
		} else if ( file_exists($xmlfile) ) {
			print '{"error": "failed to delete '.$xmlfile.' (check permissions)"}';
		} else {
			print '{"success": "'.$xmlfile.' has been successfully removed"}';
		};
		exit;
		
	} else if ( $act == "annotate" ) {
	
		$username = check_token();
		
		$xmlfile = str_replace("../", "", $_GET['cid']);
		if ( substr($xmlfile, 0, 9) != "xmlfiles/" ) $xmlfile = "xmlfiles/$xmlfile";
		if ( substr($xmlfile, -4) != ".xml/" ) $xmlfile = "$xmlfile.xml";
		if ( !file_exists($xmlfile) ) {
			require("$ttroot/common/Sources/ttxml.php");
			$ttxml = new TTXML();
			$xmlfile = "xmlfiles/".$ttxml->fileid;
		};
		if ( !file_exists($xmlfile) ) {
			print '{"error": "invalid XML filename"}';
		}; 
		
		check_folder("tmp/infiles");
		if ( $_POST['infile'] ) {
			$infile = "tmp/".time();
			file_put_contents($infile, $_POST['infile']);
		} else if ( $_FILES['infile'] ) {
			$infile = "tmp/infiles/".preg_replace("/.*\//", "", $_FILES['infile']['name']);
			move_uploaded_file($_FILES['infile']['tmp_name'], $infile);
		} else {
			print '{"error": "no infile provided or upload failed"}'; exit;
		};
		if ( !file_exists($infile) ) {
			print '{"error": "uploading file failed"}'; exit;
		};
		
		if ( $_GET['format'] == "conllu" ) {
			# putenv("PYTHONPATH=/home/janssen/.local/lib/python3.10/site-packages");
			$cmd = "/usr/bin/perl $toolroot/Scripts/readback_conllu.pl --nobu --cid='$xmlfile' --input='$infile' 2>&1";
			$reslist = shell_exec($cmd); 
			print $reslist;
			exit;
		} else if ( $_GET['format'] == "json" ) {
			# putenv("PYTHONPATH=/home/janssen/.local/lib/python3.10/site-packages");
			$cmd = "/usr/bin/perl $toolroot/Scripts/readback_json.pl --cid='$xmlfile' --input='$infile' 2>&1";
			$reslist = shell_exec($cmd); 
			print $reslist;
			exit;
		} else if ( $_GET['format'] == "cas" ) {
			# putenv("PYTHONPATH=/home/janssen/.local/lib/python3.10/site-packages");
			$cmd = "/usr/bin/python $toolroot/Scripts/readback_cas.py --file='$xmlfile' --infile='$infile'";
			$reslist = shell_exec($cmd);
			# TODO - have the readback script report problems, and add those to the JSON output
			print "{'success': 'CAS file successfully read back'}";
			exit;
		} else {
			print '{"error": "unsupported file format"}';
		};
		exit;
		
	} else if ( $act == "upload" ) {
	
		$username = check_token();
		$format = $_GET['format'];
		if ( !$format ) {
			print '{"error": "no file format indicated"}'; exit;
		};
		if ( !$_FILES || !$_FILES['infile'] ) {
			print '{"error": "no infile provided or upload failed"}'; exit;
		};
		$infile = $_FILES['infile']['tmp_name'];
		$pandoc = array ("rtf", "docx", "html", "odt", "md");
		$inname = $_FILES['infile']['name'];
		$fileid = preg_replace("/\..*/", "", $inname);
		check_folder("tmp/infiles");
		check_folder("xmlfiles");
		$tmpfile = "tmp/infiles/$fileid.$format";
		$pdfile = "tmp/infiles/$fileid.xml";
		rename($infile, $tmpfile);
		$toname = $_GET['name'] or $toname = $inname;
		$toname = str_replace("..", "", $toname); # No ..
		$toname = str_replace(" ", "_", $toname); # No spaces
		$toname = preg_replace("['\"*+]", "", $toname); # No special chars (only [a-ZA-Z0-9_] ?)
		if ( $format == "audio" ) $outfile = "Audio/$toname";
		else if ( $format == "video" ) $outfile = "Video/$toname";
		else if ( $format == "facsimile" ) $outfile = "Facsimile/$toname";
		else if ( $format == "pagexml" ) $outfile = "Originals/$toname";
		else $outfile = "xmlfiles/$fileid.xml";
		$folder = preg_replace("/\/[^\/]*$/", "", $outfile);
		check_folder($folder);
		$created = "created";
		if ( file_exists($outfile) ) {
			$mode = $_GET['mode'];
			if ( $mode == "replace" ) {
				$created = "replaced";
			} else if ( $mode == "rename" ) {
				$n = 1;
				while ( file_exists("xmlfiles/$fileid"."_$n.xml") ) $n++;
				$outfile = "xmlfiles/$fileid"."_$n.xml";
			} else {
				print '{"error": "file '.$outfile.' already exists - provide mode to replace/rename"}'; exit;
			};
		}; 
		if ( in_array($format, $pandoc) ) {
			$cmd = "pandoc -t TEI -o $pdfile $tmpfile";
			shell_exec($cmd);
			$today = date("Y-m-d");
			$header = "<TEI>\n<teiHeader>\n<revisionDesc><change when=\"$today\">XML file created</change></revisionDesc>\n<notesStmt><note n=\"orgfile\">{$_FILES['infile']['name']}</note></notesStmt>\n</teiHeader>\n<text>";
			$cmd = "echo '$header' > $outfile ; cat $pdfile >> $outfile ; echo '</text></TEI>' >> $outfile";
			shell_exec($cmd);
		} else if ( $format == "eaf" ) {
			$cmd = "/usr/bin/perl $toolroot/Scripts/eaf2teitok.pl --file='$tmpfile' --output='$outfile'";
			$cmdres = shell_exec($cmd);
		} else if ( $format == "tei" ) {
			$cmd = "/usr/bin/perl $toolroot/Scripts/teip52teitok.pl --file='$tmpfile' --output='$outfile'";
			$cmdres = shell_exec($cmd);
		} else if ( $format == "pagexml" ) {
			rename($tmpfile, $outfile);
			$xmlfile = str_replace("Originals/", "xmlfiles/", $outfile);
			$cmd = "/usr/bin/perl $toolroot/Scripts/page2teitok.pl --nofolders --file='$outfile' --output='$xmlfile'";
			$cmdres = shell_exec($cmd);
		} else if ( $format == "audio" || $format == "video" || $format == "facsimile" ) {
			if ( $mode == "rename" ) {
				print '{"error": "renaming not supported for media files" }'; exit;
			};
			$mmime = $format; if ( $mmime == "facsimile" ) $mmime = "image";
			$cmd = "/usr/bin/file --mime -b $tmpfile";
			$mime = shell_exec($cmd);
			if ( substr($mime, 0, strlen($mmime)) != $mmime ) { 
				print '{"error": "not an '.$mmime.' file: '.$inname.'"}'; exit;
			};
			$folder = preg_replace("/\/[^\/]*$/", "", $outfile);
			check_folder($folder);
			rename($tmpfile, $outfile);

			if (file_exists($outfile)) {
				print '{"success": "file successfully '.$created.'", "file": "'.$inname.'" }'; exit;
			};
			print '{"error": "uploading '.$inname.' failed (check permissions)" }';
			exit;
		} else {
			print '{"error": "not (yet) possible to upload '.$format.' files"}'; exit;
		};
		
		if ( file_exists($outfile) ) {
		
			if ( $folder == "xmlfiles" ) {
				# Check whether there are missing media files
				$cmd = "tt-xpath --filename='$outfile' --header=1 '//media | //pb[@facs]'";
				$tmp = shell_exec($cmd);
				$medias = simplexml_load_string($tmp);
				$mismed = array();
				if ( $medias )
				foreach ( $medias->xpath("//results/*") as $mnode ) {
					$nn = $mnode->getName(); $mf = ""; 
					if ( $nn == "pb" ) { $mr = $mnode['facs'].""; $mf = "Facsimile/$mr"; };
					if ( $nn == "media" ) { 
						$mr = $mnode['url'].""; 
						if ( substr($mr, 0, 4) != "http" ) { 
							$mf = $mr; 
							if ( substr($mr, 0, 6 ) == "Audio/" )  $mr = substr($mr, 6);
							$mf = "Audio/$mr"; 
						}; 
					};
					if ( $mf && !file_exists($mf) )  {
						array_push($mismed, $mr);
					};
				}; if ( $mismed ) $mistxt = ", missing_media: [\"".join('", "', $mismed).'"]'; else $mistxt = "";
			};
					
			print '{"success": "file '.$outfile.' successfully '.$created.'"'.$mistxt.' }'; exit;
		
		} else {
			print '{"error": "something went wrong with the conversion"}'; exit;
		};
		
		print '{"error": "unexpected error"}'; exit;
		
	} else if ( $act == "reindex" ) {

		$username = check_token();
		if ( file_exists("Scripts/recqp.pl") ) $scriptname = "Scripts/recqp.pl"; 
		else if ( file_exists("$sharedfolder/Scripts/recqp.pl") ) $scriptname = "$sharedfolder/Scripts/recqp.pl"; 
		else $scriptname = "$ttroot/common/Scripts/recqp.pl";	
		$cmd = "perl $scriptname $setfile > /dev/null &";
		exec($cmd);
		print '{"success": "corpus regeneration started"}'; exit;
	
	} else if ( $act == "nlp" ) {

		$username = check_token();
		$perlapp = findapp("perl") or $perlapp = "/usr/bin/perl";
		
		if ( !$_GET['tokenize'] || $_GET['tokenize'] == "yes" ) {
			$lbcmd .= " --sent=1"; ## always segment
			$mtxtelm .= "'//text'"; 
			$cmd = "$perlapp $ttroot/common/Scripts/xmltokenize.pl --filename='$cid' $lbcmd ";
			$res = shell_exec($cmd);
			if ( strpos($res, "messed up") !== false ) {
				print '{"error": "tokenization failed - please revise manually"}'; exit;
			};
		}; 

		# Set-up for udpipe
		$task = "";
		if ( !$_GET['tag'] || $_GET['tag'] == "yes" ) $task .= " --tag";
		if ( !$_GET['parse'] || $_GET['parse'] == "yes" ) $task .= " --parse";
		if ( $task != "" ) {
			$nlplang = $_GET['lang'];
			if ( !$nlplang ) { 
				try { $nlplang = getset('defaults/lang'); } catch (Error $e) { };
			};
			if ( !$nlplang ) {
				print '{"error": "no language provided NLP processing - aborted after tokenisation"}'; exit;
			};
			$cmd = "$perlapp $toolroot/Scripts/parseudpipe.pl --writeback --mode=server --lang=$nlplang --file='$cid' ";
			$res = shell_exec($cmd);
			if ( strpos($res, "No UDPIPE models") !== false ) {
				print '{"error": "no UDPIPE models for '.$nlplang.'  - aborted after tokenisation"}'; exit;
			};
		};
		
		print '{"success": "NLP pipeline applied"}'; exit;
	
	} else if ( $cmdln ) {

		print '{"error": "no (valid) act provided"}'; exit;
	
	} else {
	
		header('Content-Type: text/html; charset=utf-8');
		$maintext .= "<h1>TEITOK API</h1>";
		
		if ( $username ) {
			if ( !file_exists("Resources/litoks.xml") ) { file_put_contents("Resources/litoks.xml", "<tokens/>"); };
			if ( !file_exists("Resources/litoks.xml") ) { fatal("failed to read or create token file"); };
			$litoks = simplexml_load_file("Resources/litoks.xml");
			$mine = $litoks->xpath("//token[@user=\"$username\"]");
			if ($mine) {
				$maintext .= "<p>Your (active) token(s):</p><ul>";
				foreach ( $mine as $chk ) {
					$chkt = $chk['time'] * 1; $chktt = "";
					if ( $chkt ) $chktt = strftime("%d %h %Y %H:%m:%S", $chkt);
					$maintext .= "<li><span style='color: #999999'>$chktt</span> {$chk['id']} ";
				};
				$maintext .= "</ul>";
			} else {
				$maintext .= "<p>To use the API to modify the corpus or access restricted data, you will need a login token. You can create a new one <a href='index.php?action=$action&act=token'>here</a>";	
			};
			$maintext .= "<hr>";
		};
		
		$filelist = file_get_contents("cqp/text_id.avs");
		$exampleid = current(explode("\0", $filelist));
		if ( !$username ) $exampleid = preg_replace("/.*\//", "", $exampleid);
		
		$exquery = "[upos=\"NOUN\"]";
		$exquery = urlencode($exquery);

		$maintext .= "<h2>API Reference</h2>
		
			<p>The TEITOK API can be accessed directly or via any other web programming tools that support standard HTTP request methods and JSON for output handling.
				Login tokens should be generated via this GUI interface. Each TEITOK corpus has its own API instance.
				
			<table id=rollovertable style='width: 100%'>
			<tr><th>Service<th>Description<th>HTTP Method</tr>
			<tr><td><a onclick=\"jumpto('list');\">list</a><td>List the corpus files<td>GET/POST
			<tr><td><a onclick=\"jumpto('download');\">download</a><td>Download a file from the corpus<td>GET/POST
			<tr><td><a onclick=\"jumpto('upload');\">upload</a><td>Upload a file to the corpus<td>POST
			<tr><td><a onclick=\"jumpto('annotate');\">annotate</a><td>Upload an annotation file to the corpus<td>POST
			<tr><td><a onclick=\"jumpto('metadata');\">metadata</a><td>Add or modify metadata for a file<td>GET/POST
			<tr><td><a onclick=\"jumpto('getmeta');\">getmeta</a><td>Get metadata for all files<td>GET/POST
			<tr><td><a onclick=\"jumpto('nlp');\">nlp</a><td>Run the default NLP pipeline on a file<td>GET/POST
			<tr><td><a onclick=\"jumpto('delete');\">delete</a><td>Delete a file from the corpus<td>GET/POST
			<tr><td><a onclick=\"jumpto('reindex');\">reindex</a><td>Regenerate the index corpora<td>GET/POST
			<tr><td><a onclick=\"jumpto('query');\">query</a><td>Send a query request to the indexed corpus<td>GET/POST
			</table><p>
			
			
			<script>
				function dodl(id) {
					var url = document.getElementById(id).innerText;
					if ( url ) {
						window.open(url, '_new');
					}; 
				};
				function jumpto(anchor){
					const element = document.querySelector('#'+anchor)
					const topPos = element.getBoundingClientRect().top + window.pageYOffset

					window.scrollTo({
					  top: topPos, // scroll so that the element is at the top of the view
					  behavior: 'smooth' // smooth scroll
					})
				}		
			</script>
			
			<h2 id='list'>Method LIST</h2>
			
			<p>Returns a list of all the files in the corpus. If a login token is provided, the full list of XML files in the corpus is rendered, otherwise
				only the files in the indexed corpus are given.</p>

			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>status<td>no<td>string ( <code>notok</code> / <code>notag</code> / <code>noparse</code> )<td>list only file that are not (tokenized/tagged/parsed)
			<tr><td>since<td>no<td>date ( <code>YYYY-MM-DD</code> )<td>list only file that were modified after a specific date 
			</table><p>
			
			<style>pre { background-color: #f2f2f2; padding:5px; border: 1px solid #aaaaaa; }</style>	
				
			<h3>Browser Example</h3>
			<table style='width: 100%;'><tr>
			<td valign=top><pre id='url-list'>$baseurl/index.php?action=api&act=list</pre>
			<td valign=top><button onclick=\"dodl('url-list')\">this this</button>
			</tr></table>
			<h3>Example JSON result</h3>
			<pre>{
  \"files\": [
    \"aktuality0001.xml\",
    \"aktuality0002.xml\",
    \"aktuality0005.xml\",
    \"new.xml\",
    \"test.xml\",
    \"multilang.xml\",
    \"aktuality0004.xml\",
    \"aktuality0003.xml\"
  ]
}</pre>

			<h2 id='download'>Method DOWNLOAD</h2>
			
			<p>Retrieves a file from the corpus for offline use. Depending on the settings of the corpus, this might require a login token.
			The output is not JSON, but rather the raw requested file.</p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>ID of the file to be downloaded
			<tr><td>format<td>no<td>string ( <code>teitok</code> / <code>conllu</code>  / <code>vrt</code> / <code>cas</code> / <code>tcf</code> )<td>format to download in - default <code>teitok</code>
			<tr><td>form<td>no<td>string<td>orthographic form to use - default <code>form</code>
			</table><p>

			<h3>Browser Example</h3>
			<table style='width: 100%;'><tr>
			<td valign=top><pre id='url-download'>$baseurl/index.php?action=api&act=download&format=conllu&cid=$exampleid</pre>
			<td valign=top><button onclick=\"dodl('url-download')\">try this</button>
			</tr></table>

			<h2 id='annotate'>Method ANNOTATE</h2>
			
			<p>Upload an annotation file for a TEI/XML file, meant for use in combination with DOWNLOAD. The uploaded file should retain the 
				token IDs provided in the download file. Requires a login token. </p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>ID for the TEITOK/XML file to be annotated
			<tr><td>format<td>yes<td>string ( <code>conllu</code> / <code>cas</code> / <code>json</code> )<td>format of the uploaded file
			<tr><td>force<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>whether the uploaded file should overwrite existing annotations - default <code>no</code>
			<tr><td>infile<td>yes<td>file / string<td>the uploaded file 
			</table><p>

			<h2 id='upload'>Method UPLOAD</h2>
			
			<p>Upload a new file to the corpus. When not already in TEITOK format, the file will be converted to the TEITOK format</p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>ID the TEITOK/XML file should get in the corpus
			<tr><td>format<td>yes<td>string<td>format of the uploaded file (see below)
			<tr><td>mode<td>yes<td>string ( <code>reject</code> / <code>rename</code> / <code>replace</code> )<td>what to do if the file already exists, rename will add a sequential number to the end of the filename - default <code>reject</code>
			<tr><td>infile<td>yes<td>file<td>the uploaded file 
			</table><p/>

			<p>All uploaded files (exept media files) will be converted to TEITOK/XML, which deviates from pure TEI/XML in small points. below is 
			a list of accepted input formats, with the process that will be used for the conversion</p>

			<table id=rollovertable style='width: 100%'>
			<tr><th>Format<th>Document type<th>Conversion</tr>
			<tr><td>txt<td>Plain text file<td>conversion with the teitok-tools txt2teitok.pl script
			<tr><td>rtf<br>docx<br>html<br>md<br>odt<td>Rich Text File<br>Microsoft Word Document<br>HTML file<br>MarkDown file<br>OpenOffice Document
				<td>conversion with pandoc with an added teiHeader
			<tr><td>eaf<td>ELAN Annotation Format<td>conversion with the teitok-tools eaf2teitok.pl script
			<tr><td>audio<br>video<br>facsimile<td>Audio files<br>Video files<br>Facsimile Images<td>will be checked on MIME format and moved to the corresponding folder
			</table><p/>
			
			<p>If the converted TEI file contains media references (video, audio, facsimile), the result will contain a list of all the files that should but upload.</p>

			<h3>Example JSON result</h3>
			<pre>{
   \"success\":\"file xmlfiles/KR50_02.xml successfully created\",
   \"missing_media\":[
	  \"KR50_02.wav\"
   ]
}</pre>

			<h2 id='metadata'>Method METADATA</h2>
			
			<p>Provide an array or JSON object that goes provides new values for XPath statement, which will create or modify the content
			of the XML nodes indicated. Requires a login token. </p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>ID for the TEITOK/XML file to be modified
			<tr><td>values<td>yes<td>array<td>an array of XPath statements with their new value(s)
			<tr><td>data<td>yes<td>JSON string<td>alternative to the POST/data
			</table><p>

			<h2 id='getmeta'>Method GETMETA</h2>
			
			<p>Get metadata for all files in the corpus, either with an XPath query, or using a CQL field. Requires a login token. </p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>type<td>yes<td>string ( <code>CQL</code> / <code>XPath</code> )<td>the type of query - default <code>CQL</code>
			<tr><td>output<td>yes<td>string ( <code>json</code> / <code>xml</code> )<td>the format of the output ( <code>xml</code> only supported in XPath mode) - default <code>json</code>
			<tr><td>query<td>yes<td>string<td>the CQL sattribute field, or an XPath query
			</table><p>

			<h2 id='nlp'>Method NLP</h2>
			
			<p>Run the default NLP pipeline on a file, where steps can be turned on or off. The default pipeline is TEITOK tokenization + UDPIPE</p> <!--  + NameTag2 -->
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>ID of the file to be processed
			<tr><td>language<td>yes<td>string<td>the ISO code for the language of the file - not needed when a default corpus language is defined
			<tr><td>tokenize<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>toggle the tokenization - default <code>yes</code>
			<tr><td>tag<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>toggle the POS tagging - default <code>yes</code>
			<tr><td>parse<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>toggle the dependency parsing - default <code>yes</code>
			<!-- <tr><td>ner<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>toggle the Named Entity recognition - default <code>yes</code> -->
			</table><p>

			<h2 id='reindex'>Method REINDEX</h2>
			
			<p>Regenerate the CWB corpus + all secondary corpus format. While being regenerated, the corpus will not be searchable. Requires a login token. Can be used to 
			periodically reindex a corpus under development, say every day at midnight using a local timed command.</p>
			
			<h2 id='query'>Method DELETE</h2>
		
			<p>Delete a file or folder from the corpus. Contrary to deleting via the interface, the will not keep the file in the trash.
				Folders can only be deleted if they are empty.</p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>cid<td>yes<td>string<td>the ID of the TEI/XML file to be deleted
			</table><p>
			
			<h2 id='query'>Method QUERY</h2>
		
			<p>Send a query to the corpus. The API is intended for tree query languages, which always related to sentences, and as such, the results
				always contains full sentences.</p>
			
			<table id=rollovertable style='width: 100%'>
			<tr><th>Parameter<th>Mandatory<th>Data type<th>Description</tr>
			<tr><td>query<td>no<td>string<td>the query to be processed
			<tr><td>type<td>no<td>string ( <code>CQL</code> / <code>...</code> )<td>the query language - default <code>CQP</code>
			<tr><td>output<td>no<td>string ( <code>xml</code> / <code>text</code> )<td>the format of the output data - default <code>xml</code>
			<tr><td>qid<td>no<td>string<td>use the query ID to continue with an already processed query to get quicker results 
			<tr><td>start<td>no<td>integer<td>from which results to start - default <code>0</code>
			<tr><td>perpage<td>no<td>integer<td>how many results to render - default <code>100</code>
			<!-- <tr><td>format<td>no<td>string ( <code>yes</code> / <code>no</code> )<td>toggle the Named Entity recognition - default <code>yes</code> -->
			<tr><td>subcorus<td>no<td>string<td>if the corpus is divided into subcorpora, provide a subcorpus ID
			<!-- atts -->
			</table><p>

			<h3>Browser Example</h3>
			<table style='width: 100%;'><tr>
			<td valign=top><pre id='url-query'>$baseurl/index.php?action=api&act=query&output=text&type=CQL&query=$exquery</pre>
			<td valign=top><button onclick=\"dodl('url-query')\">try this</button>
			</tr></table>


			";
		
	};


	function check_token($fail = true) {
		global $username;
		
		if ( $username ) return $username;
		
		$litok = $_GET['token'] or $litok = $_POST['token'];
		
		if ( !$litok ) {
			if ( $fail ) {
				print '{"error": "action requires login token"}'; exit;
			};
		};
		
		$litoks = simplexml_load_file("Resources/litoks.xml");
		if ( !is_object($litoks) ) {
			if ( $fail ) {
				print '{"error": "no active login tokens"}'; exit;
			}
		} else {		
			$chk = current($litoks->xpath("//token[@id=\"$litok\"]"));
			if ( !$chk ) {
				if ( $fail ) {
					print '{"error": "invalid login token"}'; exit;
				} else return "";
			};
		};
		
		return $chk['user']."";
		
	};

?>