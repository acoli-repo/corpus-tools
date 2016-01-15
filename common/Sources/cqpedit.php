<?php
	// Script to allow editing multiple files simultaneously
	// based on a CQP query - called from cqp.php
	// Indicate feature to add/modify in all matching tokens
	// Feature selections identical to tokedit.php
	// (c) Maarten Janssen, 2015

	include ("../common/Sources/cwcqp.php");
	check_login();

	if ( $_POST['selected'] ) {

		$maintext .= "<h1>Executing multi-token edit</h1>";
		
		$maintext .= "<h2>Changes to be made</h2>";		
		foreach ( $_POST['atts'] as $key => $val ) {
			if ( $val != "" ) { 
				$maintext .= "   - Change $key to $val";
				$changes[$key] = $val; 
			};
		};
		if ( !$changes )  { fatal('Nothing to change'); };
		
		$maintext .= "<h2>Tokens to process</h2>";		
		foreach ( $_POST['selected'] as $fileid => $toklist ) {
			# Open the file
			$fid = $fileid;
			if ( !file_exists("$fileid") ) { 
				$fileid .= ".xml";
	
				$fileid = preg_replace("/^.*\//", "", $fileid);
				$test = array_merge(glob("$xmlfolder/**/$fileid")); 
				if ( !$test ) 
					$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
				$temp = array_pop($test); 
				$fileid = $xmlfolder.preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
				if ( $fileid == "" ) {
					$maintext .= "<p>No such XML File found: {$fileid}"; next;
				};
			};
			
			$file = file_get_contents("$fileid"); 
			# Kill the namespace in the XML since SimpleXML does not like it
			$file = preg_replace("/ xmlns=\"[^\"]+\"/", "", $file);
			$xml = simplexml_load_string($file, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			if ( !$xml ) { $maintext .= "Failing to read/parse $fileid"; continue; };

			$maintext .= "<h3>Treating $fileid</h3>";
			$changed = 0;
		
			foreach ( $toklist as $tokid => $val2 ) {
				
				@$result = $xml->xpath("//tok[@id='$tokid'] | //dtok[@id='$tokid']"); 
				$token = $result[0];
				if ( !$token ) { $maintext .= " ! token $tokid not found"; next; };
				
				# Now check if this is the right token
				$orgform = $_POST['orgform'][$fid][$tokid];
				if ( $orgform == "" || ( $token['form'] != $orgform && $token."" != $orgform ) ) {$maintext .= "<p> !! token $tokid does not seem to be the right token: $orgform expected, $token found - XML got modified?"; next;  };
				$maintext .= "<p> - modifying <a target=check href='index.php?action=file&cid=$fileid&tid=$tokid'>".$token['id']."</a> in $fileid";
				
				foreach ( $changes as $chkey => $chval ) {
					# $maintext .= "<p>   - setting $chkey to $chval";
					$token[$chkey] = $chval;
					$changed = 1;
				};
				
			};
			
			if ( $changed ) {
				$maintext .= "<p> -- saving changes";
				saveMyXML($xml->asXML(), $fileid);
			};
					
			
		};
		
		if ( $_POST['nextnum'] ) {
   			$maintext .= "<hr><form action='index.php?action=cqpedit&start={$_POST['nextnum']}' id=cqlform name=cqlform method=post>
   				<input type=hidden name=cql value='{$_POST['cql']}'></form>
   				
   				<span onclick='document.cqlform.submit();'>Click here to continue to the next batch</a></p>
   				
   				<p style='font-weight: bold; color: #992000'>Bear in mind that the CQP corpus has not been updated, 
   					so any changes made here will not yet be reflected in the next CQP Search until you regenerate the CQP corpus";
		} else {
			$maintext .= "<hr><p>Click <a href='index.php?action=cqp'>here</a> to continue";
		};
		# exit;
	
	} else {


		$maintext .= "<h1>Multiple token edit via CQP Search</h1>";

		$maintext .= "<p>Define below which features you want to change in this search, 
			and select all the tokens for which you want that change to be made. 
			Leaving a feature empty will not eliminate it's value, but just ignore that 
			feature in the edit.
			
			<p style='color: #aa2000; font-weight: bold'>The CQP corpus can become disaligned wrt the XML 
				files after editing tokens. 
				<br>Therefore, always regenerate the CQP corpus 
				before using this function!
			<hr>
			
			<form action='' method=post>
			<table>";
		# $maintext .= "<tr><td>XML<td>Raw XML value<td><input size=60 name=word id='word' value='$xmlword'>";

		// Show all the defined forms
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			$atv = $token[$key]; 
			$val = $item['display'];
			if ( $key != "pform" && !$item['noedit'] ) {
				$atv = str_replace("'", "&#039;", $atv);
				$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
			};
		};
		$maintext .= "<tr><td colspan=10><hr>";
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$atv = $token[$key]; 
			$val = $item['display'];
			if ( $item['noedit'] ) {
				if ( $val ) $maintext .= "<tr><td>$key<td>$val<td>$atv";
				continue;
			};
			if ( $key != "pform" ) {
				// if ( $attype[$key] == "select" || $attype[$key] == "eselect" || $attype[$key] == "mselect" ) {
				if ( $item['type'] == "Select" || $item['type'] == "ESelect" || $item['type'] == "MSelect" ) {
					$tmp = file_get_contents("cqp/$key.lexicon"); $optarr = array();
					foreach ( explode ( "\0", $tmp ) as $kval ) { 
						if ( $kval ) {
							if ( $atv == $kval ) $seltxt = "selected"; else $seltxt = "";
							if ( ( $attype[$key] != "mselect" || !strstr($kval, '+') )  && $kval != "__UNDEF__" ) $optarr[$kval] = "<option value='$kval' $seltxt>$kval</option>"; 
						};
					};
					sort( $optarr, SORT_LOCALE_STRING ); $optlist = join ( "", $optarr );
				
					if ( $item['type'] == "ESelect" ) {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
						$maintext .= "<input type=checkbox>new value: <span id='newat'><input size=30 name=newatt[$key] id='f$key' value=''></span>";
					} else if ( $item['type'] == "Select" ) {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
					} else if ( $item['type'] == "MSelect" ) {
						$optlist = preg_replace("/<option[^>]+selected>.*?<\/option>/", "", $optlist);
						$maintext .= "<tr><td>$key<td>$val<td><input size=40 name=atts[$key] id='f$key' value='$atv'>
							add: <select name=null[$key] onChange=\"addvalue('$key', this);\"><option value=''>[select]</option>$optlist</select>";
					} else {
						$maintext .= "<tr><td>$key<td>$val
									<td><select name=atts[$key]><option value=''>[select]</option>$optlist</select>";
					};
					 
				} else {
					$maintext .= "<tr><td>$key<td>$val<td><input size=60 name=atts[$key] id='f$key' value='$atv'>";
				};
			};
		};

		$maintext .= "</table><hr>";

		$cqp = new CQP();
		$cqp->exec($settings['cqp']['corpus']); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		$cql = $_GET['cql'] or $cql = $_POST['cql'] ;
		
		if ( !$cql ) { fatal ('no query specified'); };
		
		$cqpquery = "Matches = $cql";
		$results = $cqp->exec($cqpquery);

		$sort = $_POST['sort'] or $sort = $_GET['sort'] or $sort = 'word';
		if ( $sort ) {
			# $maintext .= "Sorted by $sort"; - this is not 
			$cqp->exec("sort Matches by $sort");
		};
		# $maintext .= "<P>QUERY: $cqpquery";

			$cnt = $cqp->exec("size Matches");
	
		$context = 5;
		$max = $_POST['max'] or $max = $_GET['max'] or $max = $cqpmax or $max = 500; $_POST['max'] = $max;
		$start = $_POST['start'] or $start = $_GET['start'] or $start = 0; $_POST['start'] = $start;
		$end = $start + $max;
		$showform = "word";

		if ( strstr($cql, '@') ) { 
			$withtarget = ", target $showform, target id";
			$matchh = "<th>Target word";
		};

		$cqpquery = "tabulate Matches $start $end match text_id, match[-$context]..match[-1] $showform, matchend[1]..matchend[$context] $showform, match .. matchend id, match .. matchend $showform $withtarget";
		$results = $cqp->exec($cqpquery);
		# $maintext .= "<P>TABULATE: $cqpquery";

		if ( $debug ) $maintext .= "<p>$cqpquery<PRE>$results</PRE>";

		$resarr = split ( "\n", $results ); $scnt = count($resarr);
		$maintext .= "<p>$cnt {%results} for $cql";
		if ( $scnt < $cnt ) { 
			$last = min($end,$cnt);
			$maintext .= " &bull; {%Showing} $start - $last";
			if ($end<$cnt) {
				$maintext .= " (<a onclick=\"document.getElementById('rsstart').value ='$end';  document.resubmit.submit();\">{%next}</a>)";
				$maintext .= "<input type=hidden name=nextnum value='".($end+1)."'><input type=hidden name=cql value='$cql'>";
			};
		};
		$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
			<table>
			<tr><th>File ID<th>Sel.<th style='text-align: right'>Left context<th style='text-align: center'>Match<th>Right context$matchh";
		
		foreach ( $resarr as $line ) {
			if ( $line == "" ) continue;
			$tmp = explode ( "\t", $line );
			list ( $fileid, $lcontext, $rcontext, $tid, $word, $targetword, $targetid ) = $tmp;

				$tidarray = explode (" ", $tid );
				$tid = $targetid or $tid = $tidarray[0]; 
				$match = $targetword or $match = $word;

			$refname = $fileid;
			
			if ( count($tidarray) == 1 || $targetid ) {
				$checkbox = "<input type=checkbox value='treat' name='selected[$fileid][$tid]'>
					<input type=hidden name='orgform[$fileid][$tid]' value='$match'>";
			} else {
				$checkbox = "<span style='color: #ff9999' title='multiword match'>&block;</span>";
			};
			
			if ( $match != "" && substr($line,0,1) != "#" ) 
				if ( !$fileid ) $maintext .= "<tr style='color: #aaaaaa'>
					<td>(no id)
					<td>
					<td align=right>$lcontext<td align=center><b>$word</b><td>$rcontext";
				else $maintext .= "<tr>
					<td><a target=check style='font-size: small; margin-right: 10px;' href='{$purl}index.php?action=edit&cid=$fileid&jmp=$tid'$target>$refname</a>
					<td>$checkbox
					<td align=right>$lcontext<td align=center><b>$word</b><td>$rcontext
					<td><b style='color: #992000;'>$targetword</b>";
			 # else $maintext .= "<p>?? $match, $line";
			 
		};
		$maintext = str_replace('__UNDEF__', '', $maintext);
		$maintext .= "</table><hr><p><input type=submit value='Change selected'> 
			<span onClick='selectalltoks();'>select all</span>
			&bull; <a href='index.php?action=cqp'>back to search</a>
			</form>
			<script language=Javascript>
			function selectalltoks () {
				var its = document.getElementsByTagName(\"input\");
				for ( var a in its ) {
					var it = its[a];
					if ( typeof(it) != 'object' ) { continue; };
					if ( it.value == \"treat\" ) {
						it.checked = true;
					};
				};
			};
			</script>
			";
	};
		
?>