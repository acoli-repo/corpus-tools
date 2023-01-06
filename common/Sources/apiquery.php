<?php

	check_folder("cache");

	if ( !$_POST ) $_POST = $_GET;

	$qid = $_POST['qid'];
	$query = $_POST['query'];
	$type = $_POST['type'];
	$format = $_POST['format'] or $format = "json";
	$output = $_POST['output'] or $output = "xml";
	$lvl = $_POST['lvl'] or $lvl = $settings['cqp']['sent'] or $lvl = "s";
	
	if ( !$qid ) {
		$rnd = rand();
		if ( $rnd < 100 ) $rnd = $nd * 10000;
		$rnd = floor($rnd);
		$qid = time() . '-' . $rnd;
	};
	if ( !$query && !file_exists("cache/$qid") ) {
		print "{\"error\": \"No query provided\"}";
		exit;
	}; 
	
	$start = $_POST['start'] or $start = 0;
	$max = $_POST['perpage'] or $max = 100;
	$end = $start + $max;

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps and cannot be provided by API
	$subcorpus = $_POST['subcorpus'] or $subcorpus = $_SESSION['subc'] or $subcorpus = $_GET['subc']; 
	if ( $subcorpus ) {
		$cqpcorpus = strtoupper("$cqpcorpus-$subcorpus"); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = "cqp/$subcorpus";
	};
	
	# When we have metadata, compile the list of matching files
	if ( $_POST['atts'] ) {
		$sep = "";
		foreach ( $_POST['atts'] as $key => $val ) {
			if ( $val ) {
				$cql .= $sep."match.$key=\"$val\"";
				$sep = " & ";
			};
		};
	
		if ( $cql ) {
			$metaquery = "Matches = <text> [] :: $cql;\ntabulate Matches match text_id;";
			file_put_contents("cache/$qid.dcql", $metaquery);
			$cmd = "/usr/local/bin/cqp -r cqp -D $cqpcorpus -f 'cache/$qid.dcql' | perl -e 'while(<>) { s/ /,/g; print; };' > cache/$qid.docs"; 
			shell_exec($cmd);
			if ( !$debug ) unlink("cache/$qid.dcql");
			$docfile = "cache/$qid.docs";
		};
	};

	if ( $username && $_POST['debug'] ) $debug = 1;

	if ( !file_exists("cache/$qid") || $debug ) {
		# Run the query
		if ( file_exists("Sources/query-$type.php") ) {
			$qsrc = "Sources/query-$type.php";
		} else if ( $sharedfolder && file_exists("$sharedfolder/Sources/query-$type.php") ) {
			$qsrc = "$sharedfolder/Sources/query-$type.php";
		} else if ( file_exists("$ttroot/common/Sources/query-$type.php") ) {
			$qsrc = "$ttroot/common/Sources/query-$type.php";
		};

		if ( $qsrc ) {
			include($qsrc);	
			$time1 = microtime(true);
			$fname = preg_replace("/[^a-zA-Z0-9]/", "", "query$type");
			$fname($query, $qid);
			$time2 = microtime(true);
			$extime = sprintf("%0.3f", $time2-$time1);
			$date = (new DateTime('NOW'))->format("y/m/d h:i:s");
			$tmp = explode(" ", trim(shell_exec("wc cache/$qid")));
			$totcnt = $tmp[0];
			$last = min($totcnt+0, $end+0);
			$lquery = preg_replace("/[\n\r]+/", " ## ", $query);
			$logline = "$date\t$type\t$extime\t$totcnt\t$lquery\n";
			file_put_contents("tmp/query.log", $logline, FILE_APPEND);
		} else {
			print "{\"error\": \"No search engine for $type\"}";
			exit;
		};
	} else {
		$tmp = explode(" ", trim(shell_exec("wc cache/$qid")));
		$totcnt = $tmp[0];
		$last = min($totcnt+0, $end+0);
	};
	
	$tail = min($max, $totcnt-$start); 
	$results = shell_exec("head -n $end cache/$qid | tail -n $tail");

	if ( $format == 'json' ) {
		header('Content-Type: application/json; charset=utf-8');
		if ( !filesize("cache/$qid") ) {
			print "{\"total\": 0, \"results\": []}"; exit;
		};
		print "{\"total\": $totcnt, \"start\":  $start, \"results\": [\n"; 
		$sep = "";
		foreach ( explode("\n", $results) as $i => $line ) {
			list ( $textid, $sentid, $toks, $text ) = explode("\t", $line);
			if ( !$textid ) continue;
			if ( !$sentid ) list ($textid, $sentid) = explode("_", $textid);
			if ( substr($textid,-4) != ".xml" ) $textid .= ".xml";
			$toklist = '"'.join('", "', explode(",",  $toks)).'"';
			if ( $output == "xml" ) {
				$content = sentbyid($textid, $sentid, $lvl);
			} else if ( $output == "text" ) {
				if ( !$text ) {
					$tmp = sentbyid($textid, $sentid, $lvl);
					$text = totext($tmp);
				};
				$content = $text;
			};
			$content = str_replace('"', '\\"', $content);
			$content = str_replace("\n", ' ', $content);
			if ( trim($content) ) {
				print $sep."\n{\"cid\": \"$textid\", \"sentid\": \"$sentid\", \"toks\": [$toklist], \"content\": \"$content\"}";
				$sep = ",";
			};
		};
		print "\n]}";
		
	} elseif ( $format == 'text' ) {
				
	} elseif ( $format == 'conllu' ) {

		header('Content-Type: text/plain; charset=utf-8');
		if ( !filesize("cache/$qid") ) {
			print "{\"total\": 0, \"results\": []}"; exit;
		};
		$startt = $start+1;
		print "# TEITOK API output
# Query ($type): $query
# Results: $startt-$last of $totcnt
\n";

		foreach ( explode("\n", $results) as $i => $line ) {
			list ( $textid, $sentid, $toks, $text ) = explode("\t", $line);
			if ( !$textid ) continue;
			
			if ( !is_array($cqpr[$textid]) ) $cqpr[$textid] = array();
			$cqpr[$textid][$sentid] .= $toks.";";

			$lastdoc = $textid;
		};
		$sep = "";
		foreach ( $cqpr as $textid => $slist ) {
			print "# newdoc = $textid\n";
			foreach ( $slist as $sentid => $toks ) {
				$restok = array();
				foreach( explode(";", $toks) as $gc => $tmp ) {
					$grlab = $gc+1;
					foreach ( explode(",", $tmp) as $tokid ) {
						$grid = "";
						if ( preg_match("/(.+):(.*)/", $tokid, $matches ) ) {
							$grid = ":".$matches[1];
							$tokid = $matches[2];
						};
						if ( $tokid ) $restok[$tokid] = $grlab.$grid;
					}; 
				};
				if ( substr($textid,-4) != ".xml" ) $textid .= ".xml";
				$sxml = sentbyid($textid, $sentid);
				$sxml = "<s>$sxml</s>";
				$tmp = simplexml_load_string($sxml); $tc = 0;
				$toklist = array ();
				if ( $tmp ) {
				$content = html_entity_decode(strip_tags($tmp->asXML())); $content = trim(preg_replace("/\s+/", " ", $content ));
				print "# raw = $content\n"; $text = "";
				foreach ( $tmp->xpath(".//tok[not(dtok)] | .//dtok") as $tok ) {
					$tc++; $tok['ord'] = $tc;
					array_push($toklist, $tok);
					$id2ord[$tok['id'].""] = $tc;
					$form = $tok['form'] or $form = $tok."";
					$text .= "$form ";
				};
				if ( !$toklist ) continue;
				print "# sent_id = $sentid\n";
				print "# text = ".trim($text)."\n";
				foreach ( $toklist as $tok ) {
					$tc++;
					$tokid = $tok['id']."";
					$ord = $tok['ord'] or $ord = $tc;
					$form = $tok['form'] or $form = $tok."";
					$lemma = $tok['lemma']; if ( !$lemma ) $lemma = "_";
					$upos = $tok['upos']; if ( !$upos ) $upos = "_";
					$xpos = $tok['xpos']; if ( !$xpos ) $xpos = "_";
					$feats = $tok['feats']; if ( !$feats ) $feats = "_";
					$head = $id2ord[$tok['head'].""]; if ( !$head ) $head = "0";
					$deprel = $tok['deprel']; if ( !$deprel ) $deprel = "_";
					$deps = $tok['deps']; if ( !$deps ) $deps = "_";
					$misc = $tok['misc']; if ( $misc ) $misc .= "|"; $misc .= "tokId=$tokid";
					if ( $restok[$tokid] ) $misc .= "|mrk=".$restok[$tokid];
					print "$ord\t$form\t$lemma\t$upos\t$xpos\t$feats\t$head\t$deprel\t$deps\t$misc\n";
				}; };
				print "\n\n";
			};
		};

	} elseif ( $format == 'conllu2' ) {

		# Do CoNLL-U via CQP (seems slower)
		header('Content-Type: text/plain; charset=utf-8');

		if ( !$cqpcorpus ) {
			$cqpcorpus = $settings['cqp']['corpus'] or $cqpcorpus = "tt-".$foldername;
			if ( $settings['cqp']['subcorpora'] ) {
				if ( !$subcorpus ) {
					fatal("No subcorpus selected");
				};
				# $_SESSION['subc'] = $subcorpus;
				$corpusname = $_SESSION['corpusname'] or $corpusname = "Subcorpus $subcorpus";
				$subcorpustit = "<h2>$corpusname</h2>";
			} else {
				$cqpcorpus = strtoupper($cqpcorpus); # a CQP corpus name ALWAYS is in all-caps
				$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";
			};
		};
		$cqpr = array();
		foreach ( explode("\n", $results) as $i => $line ) {
			list ( $textid, $sentid, $toks, $text ) = explode("\t", $line);
			if ( !$textid ) continue;
			
			if ( !is_array($cqpr[$textid]) ) $cqpr[$textid] = array();
			$cqpr[$textid][$sentid] .= $toks.";";
			
// 			$cmd = "perl $sharedfolder/Scripts/grewplus.pl --corpus=$cqpcorpus --cql='match.text_id=\".*/$textid.xml\" & match.s_id=\"$sentid\"'";
// 			print shell_exec($cmd);			
// 			print "\n\n";

			$lastdoc = $textid;
		};
		$sep = "";
		foreach ( $cqpr as $textid => $slist ) {
			chop($slist); $sids = ""; $sep2 = "";
			if (substr($textid,-4) != ".xml") $textid = ".*/$textid.xml";
			foreach ( $slist as $sentid => $toks ) { if ( $sentid ) $sids .= " $sep2 match.{$lvl}_id=\"$sentid\""; $sep2 = "|"; }; 
			$fullcql .= " $sep ( match.text_id=\"$textid\" & ( $sids ) )";
			$sep = " | ";
		};

		$startt = $start+1;
		print "# TEITOK API output
# Query ($type): $query
# Results: $startt-$last of $totcnt
\n";

 		$cmd = "perl $sharedfolder/Scripts/idlist2conllu.pl --corpus=$cqpcorpus --list='$qid'";
		print shell_exec($cmd);			

	} else {
		print "{\"error\": \"No output defined for $format\"}";
		exit;
	};

	if ( !$debug ) unlink("cache/$qid.docs");
	
	function totext($xml, $form="pform") {
		if ( $form != "pform" ) {
			
		};
		$text = preg_replace("/<[^<>]+>/", "", $xml);
		$text = preg_replace("/\s+/", " ", $text);
		return $text;
	};
	
	exit;
	
?>