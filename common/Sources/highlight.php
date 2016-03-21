<?php

	## A Script to highlight words in an XML document based on a CQP search
	# This script currently has to be called externally
	# And will reload to file.php

	$cid = $_POST['cid'] or $cid = $_GET['cid'] or $cid = $_GET['id'];

	if ( $act == "view" || $_GET['atts'] ) {
	
		if ( !file_exists("$xmlfolder/$cid") ) { 
			$oid = $cid;
		
			$cid = preg_replace("/^.*\//", "", $cid);
			if ( !preg_match("/\.xml$/", $cid) ) $cid .= ".xml";
			$test = array_merge(glob("$xmlfolder/**/$cid")); 
			if ( !$test ) 
				$test = array_merge(glob("$xmlfolder/$cid"), glob("$xmlfolder/*/$cid"), glob("$xmlfolder/*/*/$cid"), glob("$xmlfolder/*/*/*/$cid")); 
			$temp = array_pop($test); 
			$cid = preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
			if ( $cid == "" ) {
				fatal("No such XML File: {$oid}"); 
			};
		};
		
		if ( !$_POST ) $_POST = $_GET;

		$cql = $_POST['cql'] or $cql = $_GET['cql'];
		if ( $cql ) $subtit .= "<p>CQP = $cql";

		# If this is a simple search - turn it into a CQP search
		if ( $cql && !preg_match("/[\"\[\]]/", $cql) ) {
			$simple = $cql; $cql = "";
			foreach ( explode ( " ", $simple ) as $swrd ) {
				$swrd = preg_replace("/(?!\.\])\*/", ".*", $swrd);
				$cql .= "[word=\"$swrd\"] ";
			};
		};
		
		# Allow word searches to be defined via URL
		if ( !$cql && $_GET['atts'] ) {	
			$_POST['atts'] = array();
			foreach ( explode ( ";", $_GET['atts'] ) as $att ) {
				list ( $feat, $val ) = explode ( ":", $att );
				$_POST['vals'][$feat] = $val;
			};
		}; 
		
		# If this is a word search - turn it into a CQP search
		if ( !$cql && $_POST['vals'] ) {	
			$cql = "["; $sep = "";
			foreach ( $_POST['vals'] as $key => $val ) {
				$type = $_POST['matches'][$key];
				$attname = pattname($key) or $attname =  pattname('form');
				if ( $val ) {
					if ( $type == "startswith" ) {
						$cql .= " $sep $key = \"$val.*\""; $sep = "&";
						$subtit .= "<p>$attname = $val-";
					} else if ( $type == "contains" ) {
						$cql .= "$sep$key=\".*$val.*\""; $sep = " & ";
						$subtit .= "<p>$attname <i>{%contains}</i> $val";
					} else if ( $type == "endsin" ) {
						$cql .= "$sep$key=\".*$val\""; $sep = " & ";
						$subtit .= "<p>$attname -$val";
					} else {
						$cql .= "$sep$key=\"$val\""; $sep = " & ";
						$subtit .= "<p>$attname = $val";
					};
				};
			};
			$cql .= "]";
		};

		
		require ( "../common/Sources/cwcqp.php" );
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$size = $cqp->exec("set PrettyPrint off");
		$cqpquery = "Matches = $cql :: match.text_id = \"xmlfiles/$cid\"";
		$cqp->exec($cqpquery);
		$size = $cqp->exec("size Matches");
		if ( $size > 0 ) $results = $cqp->exec('tabulate Matches match .. matchend id');
		$cqp->close();
		
		if ( $subtit ) $hltit = "<p>{%Highlighted in the text are words with the following characteristics:} ".$subtit;
		
		$jmp = str_replace("\n", " ", $results);
		$tmp = explode ( " ", $jmp ); $size = count($tmp);
		
		$hlcol = $_POST['hlcol'] or $hlcol = $_GET['hlcol'];
		
		if ( $debug ) { print "<p>Query: $cqpquery<p>Matches found: $jmp<p>Link: <a href='index.php?action=file&cid=$cid&jmp=$jmp&hltit=$hltit'>index.php?action=file&cid=$cid&jmp=$jmp&hltit=$hltit</a>"; exit; };
		print "$size Matching. Reloading
			<form action='index.php?action=file&cid=$cid' method=post id=fwform name=fwform>
			<input type=hidden name=jmp value='$jmp'>
			<input type=hidden name=hlcol value='$hlcol'>
			<input type=hidden name=hltit value='$hltit'>
			</form>
			<script language=Javascript>document.fwform.submit();</script>";
		exit;
		
	} else {
		# Let the user define a highlighting query
		$highlightheader = getlangfile("highlighttext", true);
		$maintext .= "<h1>{%Hightlighting Query}</h1>
			$highlightheader
			<form action='index.php?action=$action&act=view' method=post>";

		# Show the document selector when needed
		if ( $cid ) {
			require ("../common/Sources/ttxml.php");
			$ttxml = new TTXML($cid, false);
			$maintext .= "<hr><h2>".$ttxml->title()."</h2>"; 
			$maintext .= $ttxml->tableheader(); 
			$maintext .= $ttxml->viewheader(); 

			$maintext .= "<input type=hidden name=cid value=\"$cid\">";

		} else {
			require ( "../common/Sources/cwcqp.php" );
			$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
			$cqp = new CQP();
			$cqp->exec($cqpcorpus); // Select the corpus
			$cqpquery = "Matches = <text> []";
			$cqp->exec($cqpquery);
			$size = $cqp->exec("size Matches");
			
			# Make a pull-down of the document when no doc id is selected, or a field when there are too many
			if ( $size < 100 ) {
				if ( $settings['cqp']['sattributes']['text']['title'] ) $tits = ", match text_title";
				$results = $cqp->exec('tabulate Matches match text_id'.$tits);
				$cqp->close();
			
				$options = array();
				foreach ( explode ( "\n", $results ) as $line ) {
					list ( $fid, $ftitle ) = explode ( "\t", $line );
					$fid = str_replace ( "xmlfiles/", "", $fid);
					if ( !$ftitle || $ftitle == "_" ) $ftitle = $fid;
					if ( $ftitle ) $options[$fid] = $ftitle;
				}; natsort($options);
				foreach ( $options as $fid => $ftitle ) $cidlist .= "<option value=\"$fid\">$ftitle</option>";
			
				$maintext .= "<p>{%Select a document}: <select name=cid>$cidlist</select>";
			} else {
				$cqp->close();
				$maintext .= "<p>{%Select a document}: <input name=cid size=50>";
			};
		};

		# Show word or CQP search	
		$maintext .= "
				<hr>
				<p>{%Search method}:  &nbsp;
					<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'cqp');\" $cdef> CQP &nbsp; &nbsp;
					<input type=radio name=st value='cqp' onClick=\"switchtype('st', 'word');\" $wdef> {%Word Search}
				<script language=Javascript>
				function switchtype ( tg, type ) { 
					var types = [];
					types['st'] = ['cqp', 'word'];
					types['style'] = ['kwic', 'context'];
					for ( var i in types[tg] ) {
						stype = types[tg][i]; 
						document.getElementById(stype+'search').style.display = 'none';
					};
					document.getElementById(type+'search').style.display = 'block';
				};
				</script>
				<div name='wordsearch' id='wordsearch' style='display: none;'><table>";
				
		$cqpcols = array();
		foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
			if ( $username || !$item['admin'] ) array_push($cqpcols, $key); 
		}; 
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col);
			if ( !$colname ) $colname = "[$col]";
			$tstyle = ""; 
			$coldef = $settings['cqp']['pattributes'][$col];
			if ( $coldef['admin'] == "1" ) {
				$tstyle = " class=adminpart";
				if ( !$username ) { continue; };
			};
			if ( $coldef['type'] == "mainpos" ) {
				if ( !$tagset ) {
					require ( "../common/Sources/tttags.php" );
					$tagset = new TTTAGS("", false);
				}; $optlist = "";
				foreach ( $tagset->taglist() as $letters => $name ) {
					$optlist .= "<option value=\"$letters.*\">$name</option>";
				};
				$maintext .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col]><option value=''>{%[select]}</option>$optlist</select>";
			} else if ( substr($coldef['type'], -6) == "select" ) {
				$tmp = file_get_contents("cqp/$col.lexicon"); unset($optarr); $optarr = array();
				foreach ( explode ( "\0", $tmp ) as $kval ) { 
					if ( $kval ) {
						if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
						if ( $coldef['type'] == "kselect" || $coldef['translate'] ) $kvaltxt = "{%$col-$kval}"; else $kvaltxt = $kval;
						if ( ( $coldef['type'] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) 
							$optarr[$kval] = "<option value='$kval' $seltxt>$kvaltxt</option>"; 
					};
				};
				sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				if ( $coldef['select'] == "multi" ) $multiselect = "multiple";
				
				$maintext .= "<tr><td$tstyle>{%$colname}<td colspan=2><select name=vals[$col] $multiselect><option value=''>{%[select]}</option>$optlist</select>";

			} else 
				$maintext .= "<tr><td$tstyle>{%$colname}
						      <td><select name=\"matches[$col]\"><option value='matches'>{%matches}</option><option value='startswith'>{%starts with}</option><option value='endsin'>{%ends in}</option><option value='contains'>{%contains}</option></select>
						      <td><input name=vals[$col] size=50 $chareqfn>";
		};
		
		$maintext .= "</table>$chareqtxt</div>
				<div name='cqpsearch' id='cqpsearch'>
				<p>{%CQP Query}: &nbsp; <input name=cql size=70 value='{$cql}' $chareqfn>
				$chareqjs 
				$subheader
				";

			

				
		$maintext .= "
							$stmp

			<p><b>{%Searchable fields}</b>
			
			<table>
			";
			
		foreach ( $cqpcols as $col ) {
			$colname = pattname($col);
			if ( $settings['cqp']['pattributes'][$col]['admin'] == "1" ) {
				$maintext .= "<tr><th>$col<td class=adminpart>{%$colname}</tr>";				
			} else {
				$maintext .= "<tr><th>$col<td>{%$colname}</tr>";
			};
		};
		$maintext .= "</table></div>
			<hr>";
		
		$maintext .= "
			<input type=submit value=\"{%Search}\">
			</form>
			";
		
	};

	function pattname ( $key ) {
		global $settings;
		if ( $key == "word" ) $key = "form";
		$val = $settings['xmlfile']['pattributes']['forms'][$key]['display'];
		if ( $val ) return $val;
		$val = $settings['xmlfile']['pattributes']['tags'][$key]['display'];
		if ( $val ) return $val;
		
		# Now try without the text_ or such
		if ( preg_match ("/^(.*)_(.*?)$/", $key, $matches ) ) {
			$key2 = $matches[2]; $keytype = $matches[1];
			$val = $settings['cqp']['sattributes'][$key2]['display'];
			if ( $val ) return $val;
			$val = $settings['cqp']['sattributes'][$keytype][$key2]['display'];
			if ( $val ) return $val;
		};
		
		return "<i>$key</i>";
	};
	
?>
