<?php

	// Apparatus machine for collations between documents/witnesses
	// (c) Maarten Janssen, 2018
		
	if ( !$_POST ) $_POST = $_GET;
			
	$maintext .= "<h1>{%Witness Collation}</h1>
			<div name='cqpsearch' id='cqpsearch'>
			$chareqjs 
			$subheader
			";
		
	if ( $act == "cqp" ) {
	
		// CQP based collation

		if ( file_exists("tmp/recqp.pid") ) {
			fatal ("The corpus is currently being regenerated, please try again later.");
		};
		
		include ("$ttroot/common/Sources/cwcqp.php");

		$baselevel = $_POST['level'] or $baselevel = $settings['collation']['baselevel'] or $baselevel = "l";
		
		$appid = $_POST['appid'];
		
		# See if we can find a TOC index for this collation
		if ( $_GET['from'] && $settings['xmlfile']['toc'] ) {
			
			# Read the XML of the from file
			require ("$ttroot/common/Sources/ttxml.php");
			$ttxml = new TTXML($_GET['from']);
			if ( $ttxml->xml ) {
		
				# Get the name of the item from the TOC
				$tocdef = $settings['xmlfile']['toc'];
				if ( $tocdef['xp'] ) $tocxp = $tocdef['xp']; else $tocxp = "//teiHeader/toc";
				if ( $ttxml->xpath($tocxp) ) {
					$tocdef = xmlflatten(current($ttxml->xpath($tocxp)));
				}; $tocidx = array_keys($tocdef);
				
				$levxp = "//*[@appid='$appid']";
				$appnode = current($ttxml->xpath($levxp));
				# Overrule the base level if we know what the node is (and we have that level in CQP)		
				$tmp = $appnode->getName();
				if ( $settings['cqp']['sattributes'][$tmp] ) $baselevel = $tmp;

				# Check if the appid is in the TOC itself
				$leaf = array_values(array_slice($tocdef, -1))[0];
				if ( preg_match("/\]$/", $leaf['xp']) ) $leaftest = preg_replace("/\]/", " and @appid='$appid']", $leaf['xp']);
				else $leaftest = "//".$leaf['xp']."[@appid='$appid']";
				$leafnode = current($ttxml->xpath($leaftest));
				if ( !$leafnode['appid'] ) {
					$appidtxt = " > $appid";
					$chnode = "[.//*[@appid='$appid']]";
				} else {
					$chnode = "[@appid='$appid']";
					$appidtxt = " ($appid)";
				};
				
				for ( $i = count($tocidx); $i>0; $i-- ) {
					$levdef = $tocdef[$tocidx[$i-1]];
					$levxp = "//".$levdef['xp'].$chnode;
					$chnode = "[.//*[@appid='$appid']]";
					$level = current($ttxml->xpath($levxp));
					$levatt = $levdef['att']."" or $levatt = "n";
					$levtxt = $level[$levatt];
					if ( $levdef['prefix'] ) $levtxt = "{%{$levdef['display']}}: $levtxt";
					$appidtxt = " > $levtxt $appidtxt";
				}; $appidtxt = preg_replace("/^ > /", "", $appidtxt );
				
			};
						
		};
		if ( !$appidtxt ) $appidtxt = $appid;

		$cql = "<".$baselevel."_appid=\"$appid\"> [];";
		
		$maintext .= "<h2>{%Collation on}: $appidtxt</h2>$subtit<hr>";
		$outfolder = $settings['cqp']['cqpfolder'] or $outfolder = "cqp";

		// This version of CQP relies on XIDX - check whether program and file exist
		$xidxcmd = findapp('tt-cwb-xidx');
		if ( !$xidxcmd || !file_exists("$outfolder/xidx.rng") ) {
			print "<p>This CQP version works only with XIDX
				<script language=Javascript>top.location='index.php?action=cqpraw';</script>
			";
		};

		# print htmlentities($cql); exit;

		# Determine which form to search on by default 
		$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";

		$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";

		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['searchfolder'];
		$cqpcols = array();

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		if ( $debug ) $maintext .= "<!-- CQL: $cql -->";
		$cqpquery = "Matches = $cql";
		$cqp->exec($cqpquery);

		$size = $cqp->exec("size Matches");

		$mtch = "match"; 
		
		if ( file_exists("cqp/{$baselevel}_facs.avs") && file_exists("cqp/{$baselevel}_bbox.avs") ) { 
			$bbs = ", $mtch {$baselevel}_bbox, $mtch {$baselevel}_facs"; 
		}; # else print "cqp/{$baselevel}_facs.avs";
			
		$perpage = $_GET['perpage'] or $perpage = 50;
		$start = $_GET['start'] or $start = 0;
		$end = $start+$perpage;
		$cqpquery = "tabulate Matches $start $end $mtch id, $mtch text_id, $mtch word, $mtch, $mtch {$baselevel}_id $bbs;";	# match page_facs
		$results = $cqp->exec($cqpquery);
		$results = $cqp->exec($cqpquery); // TODO: Why do we need this a second time?
		if ( $size > $perpage ) {
			$showing = " - {%showing} ".($start+1)." - $end";
		};
		# $maintext .= "<p>$size {%results} $showing";
		
		if ( $_GET['witlist'] ) $witlist = explode(",", $_GET['witlist']);
		if ( is_array($witlist) && $_GET['from'] && !in_array($_GET['from'], $witlist) ) array_push($witlist, $_GET['from'] );
		
		if ( $debug ) $maintext .= "<p>$cqpquery";
		$xidxcmd = findapp('tt-cwb-xidx');
		foreach ( explode("\n", $results ) as $res ) {
			list ( $id, $cid, $word, $ids, $blid, $bbox, $facs ) = explode("\t", $res );
			$tmp = explode(" ", $ids); $leftpos = array_shift($tmp); $rightpos = array_pop($tmp);
			if ( !$leftpos ) continue;

			$fileid = $cid; $outfolder = "cqp";
			if ( preg_match("/([^\/.]+)\.xml/", $cid, $matches) ) { $xmlid = $matches[1]; };
			
			if ( is_array($witlist) && !in_array($xmlid, $witlist) ) continue;
			
			$expand = "--expand=$baselevel";
			$cmd = "$xidxcmd --filename=$fileid --cqp='$outfolder' $expand $leftpos $leftpos";
			$cid2 = preg_replace("/.*?\/([^\/]+)\.xml/", "\\1", $cid);
			$resxml = shell_exec($cmd);
			$cnt++;
		
			if ( $bbox != "" && $bbox != "_" ) {
				$divheight = 40; if ( $facs ) $glfacs = $facs;
				$facsdiv = "<div bbox='$bbox' class='linediv' id='$cid:$id' tid='$id' style='display: inline-block; width: 300px; height: {$divheight}px; background-image: url(\"Facsimile/$facs\"); background-size: cover;' facs='Facsimile/$facs'></div><br>";
			} else $facsdiv = "";
			$jmpid = $id; if ( $jmpid ) { $jmpid = $blid."&jmpname=$appidtxt"; }; 
			if ( $cid2 != "" && $cid2 == $_GET['from'] ) {
				$baserow = "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$jmpid'><b>$cid2</b></a></td><td class=wits wit=$cid2 id=bf>$facsdiv$resxml</td></tr>
					<tr><td colspan=2><hr>";
			} else 
				$witrows .= "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$jmpid'>$cid2</a></td><td wit=$cid2 class=wits style='border-bottom: 1px solid #ffddaa;'>$facsdiv$resxml</td></tr>";
		};
		$maintext .= "<table id=mtxt>$baserow$witrows</table><hr><p>$cnt witnesses &bull; <a href='' id='dltei' download='app.xml' target='_blank'>download TEI app</a>";
		$maintext .= "
			<script language=Javascript src='$jsurl/collate.js'></script>
		<div id='tokinfo' style='display: block; position: absolute; right: 5px; top: 5px; width: 300px; background-color: #ffffee; border: 1px solid #ffddaa;'></div>
<style>
	tok[apps] { text-decoration: underline; };
</style>
";

		# Show the linediv images
		$mask = $_GET['mask'] or $mask = 5;
		$maintext .= "<img src='Facsimile/$glfacs' id='facsimg' style='display: none;'/>"; // Keep the last facs image as the facsimg for the width - assume all images to have the same size...
		$maintext .= "\n\n<script language=Javascript>
				var facsimg = document.getElementById('facsimg');
				var linedivs = document.getElementsByClassName('linediv');
				for ( var i=0; i<linedivs.length; i++ ) {
					var linediv = linedivs[i]; 

					// TODO: wait untill loaded
					facsimg.src = linediv.getAttribute('facs'); // load the current image into the facsimg
					var bbox = linediv.getAttribute('bbox').split(' ');
					
					// allow showing a mask - ie some space around the bbox
					bbox[0] = bbox[0] - $mask;
					bbox[1] = bbox[1] - $mask;
					bbox[2] = bbox[2] - (0-$mask);
					bbox[3] = bbox[3] - (0-$mask);
					
					// Never scale more than 50% up
					var imgscale  = Math.min(1.2, linediv.offsetHeight/(bbox[3]-bbox[1]));

					var bih = facsimg.naturalHeight*imgscale;
					var biw = bih*(facsimg.naturalWidth/facsimg.naturalHeight);
					var bix = bbox[0]*imgscale;
					var biy = bbox[1]*imgscale;

					linediv.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
					linediv.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
					linediv.style['background-size'] = biw+'px '+bih+'px';
					linediv.style['background-position'] = '-'+bix+'px -'+biy+'px';

				};
			</script>";

	} else {
	
		if ( !$settings['collation'] ) fatal("No definitions for collation");
	
		$maintext .= "
			<form action='index.php?action=$action&act=cqp' method=post>
			<table>";
		foreach ( $settings['collation'] as $key => $val ) {
			if ( $val['display'] == "" || !is_array($val) ) continue;
			$keytxt = $val['display'];
			if ( $val['type'] == "select" ) {
				$sellist = ""; $corpusfolder = "cqp";
				$tmp = file_get_contents("$corpusfolder/$key.avs"); unset($optarr); $optarr = array();
				$sortarray = array();
				foreach ( explode ( "\0", $tmp ) as $kva ) { 
					array_push($sortarray, "<option value='$kva'>$kva</option>");
				};
				natsort($sortarray); $sellist = join("\n", $sortarray);
				$maintext .= "<tr><td>{%$keytxt}<td><select name='$key'>$sellist</select></tr>";
			} else { 
				$maintext .= "<tr><td>{%$keytxt}<td><input name='$key' size=10></tr>";
			};
		}; 
		
		$maintext .= "</table>
			<p><input type=submit value='{%Search}'>
			</form>";
	
	};

?>