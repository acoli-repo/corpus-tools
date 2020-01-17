<?php
	
	# Lookup alternatives @att for @key = @val
	
	$att = $_GET['att'];
	$akey = $_GET['key'];
	$aval = $_GET['val'];
	
	$vals = array ();
	
	# Lookup which other tags have been used for this form
	if ( $settings['neotag']['lexicon'] ) {
		# Get the data from where they are said to be
		print "Lexicon...";
	} else if ( $settings['cqp'] ) {
		include ("$ttroot/common/Sources/cwcqp.php");
		if ( $akey == "form" ) $akey = "word";
		# If we don't know where to find a lexicon, ask CQP
		$cqp = new CQP();
		$cqp->exec($settings['cqp']['corpus']); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cqp->exec("Matches = [$akey=\"$aval\"]");
		$res = $cqp->exec("group Matches match $att");
		foreach ( explode ( "\n", $res ) as $line ) {
			list ( $val, $cnt ) = explode ( "\t", $line );
			if ( $val != "__UNDEF__" && $val != "" ) $vals[$val] = $cnt;
		};
	} else {
		# We have no idea where to get alternative forms...
	};
	
	
	if ( $_GET['format'] == "json" ) {
		header ("Content-Type:application/json");
		print "{
			\"att\":\"$att\", 
			\"lookup\":\"$akey\", 
			\"val\":\"$aval\", 
			\"options\":[";
		$valopts = $settings['xmlfile']['pattributes']['tags'][$att]['options'];
		if ( $settings['xmlfile']['pattributes']['tags'][$att]['type'] == "pos") {
			require("$ttroot/common/Sources/tttags.php");
			$tttags = new TTTAGS("", false); $tagfld = 1;
		};
		foreach ( $vals as $val => $cnt ) {	
			if ( is_array($valopts[$val]) ) $display = $valopts[$val]['display']; 
			else if ( $tagfld ) {
				$tmp = $tttags->analyse($val); $display = "";
				foreach ( $tmp as $key2 => $val2 ) {
					$fval = $val2['display'];
					if ( !$fval ) {
						if ( $val2['value'] == $tttags->tagset['noval'] ) $fval =  "<i style='color: #aaaaaa;'>none</i>";
						else $fval = "<i class=wrong>".$val2['value']."</i>";
					};
					$display .= "$fval; ";
				};
				$display = preg_replace("/; *$/", "", $display);
			} else $display = $val;
			print "\n{\"cnt\": $cnt, \"val\": \"$val\", \"display\": \"$display\"},";
		};
		print "{}\n]}";
		exit;
	} else {
		header ("Content-Type:text/xml");
		print "<options att=\"$att\" lookup=\"$akey\" val=\"$aval\">";
		foreach ( $vals as $val => $cnt ) {	
			print "\n\t<option n=\"$cnt\">$val</option>";
		};
		print "\n</options>";
		exit;
	};
	
?>