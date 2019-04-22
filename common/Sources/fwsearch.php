<?php

	// Use cqp to show bbox cut-outs corresponding to a CQP query
	// (c) Maarten Janssen, 2018
	
	// Check if we have pattributes for bbox and facs
	if ( !file_exists("cqp/facs.lexicon") || !file_exists("cqp/bbox.lexicon") ) {
		fatal("The word-search in facsimile images relies on the bbox and the facs being exported to CQP");
	};
	
	if ( !$_POST ) $_POST = $_GET;
	
	$cql = $_POST['cql'];
	
	if ( $settings['cqp']['longbox'] or $_GET['longbox'] ) 
		$cqlbox = "<textarea name=cql style='width: 600px;  height: 25px;' $chareqfn>$cql</textarea> ";
	else 
		$cqlbox = "<input name=cql value='$cql' style='width: 600px;'/> ";
	
	include ("$ttroot/common/Sources/querybuilder.php");
	
	$maintext .= "<h1>{%Facsimile Search}</h1>
			<div name='cqpsearch' id='cqpsearch'>
			$cqlfld
			$chareqjs 
			$subheader
			";
	
	if ( $cql ) {

		include ("$ttroot/common/Sources/cwcqp.php");

		$outfolder = $settings['cqp']['folder'] or $outfolder = "cqp";

		// This version of CQP relies on XIDX - check whether program and file exist
		$xidxcmd = findapp('tt-cwb-xidx');
		if ( !$xidxcmd || !file_exists("$outfolder/xidx.rng") ) {
			print "<p>This CQP version works only with XIDX
				<script language=Javascript>top.location='index.php?action=cqpraw';</script>
			";
		};

		# Determine which form to search on by default 
		$wordfld = $settings['cqp']['wordfld'] or $wordfld = "word";

		$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "cqp";

		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['searchfolder'];
		$cqpcols = array();

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cql = $_POST['cql'] or $cql = $_GET['cql'] or $cql = "[]";

		if ( substr($cql,0,6) == "<text>" ) $fileonly = 1;

		$cqpquery = "Matches = $cql";
		$cqp->exec($cqpquery);

		$size = $cqp->exec("size Matches");

		# $maintext .= "<p>{%Search Query}: ".htmlentities($cql);
			
		if ( strpos($cql, "@") === false ) $mtch = "match"; else $mtch = "target";
			
		$perpage = $_GET['perpage'] or $perpage = 50;
		$start = $_GET['start'] or $start = 0;
		$end = $start+$perpage;
		$cqpquery = "tabulate Matches $start $end  $mtch id, $mtch text_id, $mtch form, $mtch bbox, $mtch facs, $mtch;";	# match page_facs
		$results = $cqp->exec($cqpquery);

		if ( $size > $perpage ) {
			$showing = " - {%showing} ".($start+1)." - $end";
		};
		$maintext .= "<p>$size {%results} $showing";
		
		if ( $debug ) $maintext .= "<p>$cqpquery";
		$maintext .= "<table id=mtxt>";
		$xidxcmd = findapp('tt-cwb-xidx');
		foreach ( explode("\n", $results ) as $res ) {
			list ( $id, $cid, $word, $bbox, $facs, $ids ) = explode("\t", $res );

			$fileid = "xmlfiles/$cid"; $outfolder = "cqp";
			$tmp = explode(" ", $ids); $leftpos = array_shift($tmp); $rightpos = array_pop($tmp);
			if ( !$rightpos ) $rightpos = $leftpos;
			$cmd = "$xidxcmd --filename=$fileid --cqp='$outfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);

			if ( $bbox == "" || $facs == "" || $cid == "" ) continue;
			$divheight = 40; if ( $facs ) $glfacs = $facs;
			$facsdiv = "<div bbox='$bbox' class='linediv' id='$cid:$id' tid='$id' bgimg='Facsimile/$facs' style='display: inline-block; width: 300px; height: {$divheight}px; background-image: url(\"$jsurl/load_img.gif\"); background-size: cover;'></div>";
			$cid2 = preg_replace("/.*?\/([^\/]+)\.xml/", "\\1", $cid);
			$maintext .= "<tr><td><a href='index.php?action=file&cid=$cid&jmp=$id'>$cid2</a><td>$resxml<td>$facsdiv";
		};
		$maintext .= "</table>
		<img src='Facsimile/$glfacs' id='facsimg' style='display: none;'/>"; // Keep the last facs image as the facsimg for the width - assume all images to have the same size...

		$mask = $_GET['mask'] or $mask = 5;
	
		$maintext .= "\n\n<script language=Javascript>
				var linedivs = document.getElementsByClassName('linediv');
				var facslist = [];
				for ( var i=0; i<linedivs.length; i++ ) {
					var linediv = linedivs[i]; 

					facslist[i] = new Image ();
					facslist[i].setAttribute('divid',  linediv.getAttribute('id'));
					var src = linediv.getAttribute('bgimg');
					facslist[i].onload = function () { scalefacs(this, $mask) };
					facslist[i].src = src;
				}				

				function scalefacs ( facsimg, mask = 5 ) {
					var divid = facsimg.getAttribute('divid');
					var linediv = document.getElementById(divid);
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

					linediv.style['background-image'] = 'url('+facsimg.src+')';

					linediv.style.width = (bbox[2]-bbox[0])*imgscale + 'px'; // We might have made the div too wide
					linediv.style.height = (bbox[3]-bbox[1])*imgscale + 'px';
					linediv.style['background-size'] = biw+'px '+bih+'px';
					linediv.style['background-position'] = '-'+bix+'px -'+biy+'px';
				};
			</script>";
		
	} else {
	
			$pagetit = "Facsimile search"; 

			$explanation = getlangfile("fwsearchtext", true);
	
			$maintext .= $explanation;
	
	};
	

?>