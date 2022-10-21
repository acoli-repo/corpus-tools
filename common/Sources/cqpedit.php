<?php
	// Script to allow editing multiple files simultaneously
	// based on a CQP query - called from cqp.php
	// Indicate feature to add/modify in all matching tokens
	// Feature selections identical to tokedit.php
	// (c) Maarten Janssen, 2015

	include ("$ttroot/common/Sources/cwcqp.php");
	check_login();

	$cql = $_GET['cql'] or $cql = $_POST['cql'] ;
	
	if ( $_POST['lineedit'] ) $settings['cqp']['defaults']['background'] = false; # Does not (yet) work for lineedit
	
	if ( $act == "define" || ( !$cql && !$_POST['selected'] ) ) { 
		
		$maintext .= "
			<h1>Multi-token edit via CQP Search</h1>
			<p>Here you can define a CQP query and edit the resulting matches directly. Given that this edit works via
				the CQP corpus, only pattributes and tokens that are exported to CQP can be edited here.
			Type in the CQP query 
				directly here, or define it first in the <a href='index.php?action=cqp&act=advanced'>advanced search</a>.

				<p style='color: #aa2000; font-weight: bold'>The CQP corpus can become disaligned wrt the XML 
					files after editing tokens. 
					<br>Therefore, always regenerate the CQP corpus 
					before using this function!
			<hr>
						
			<h2>Define Query</h2>
			<form action='index.php?action=$action' method=post>
			<p>CQP Query: <input name=cql size=80 value='$cql'>
			<input type=hidden name=lineedit value=1>
			
			<hr>
			
			<h2>Define which fields to edit</h2>";

		$maintext .= "
			<form action='index.php?action=$action&act=edit&cid=$fileid' method=post>
				<table>";
		
		$settings['cqp']['pattributes']['form'] = array ( "key" => "form" ); 
		foreach ( $settings['cqp']['pattributes'] as $key => $item ) {
			if ( $key == "pform" ) $editform = ""; // Turned off editing of pfrom in verticalized view since it deletes internal nodes (or gets complicated)
			else {
				$editform = "<input type=checkbox name='fld[$key]' value=1> ";
				$display = $item['display'] 
					or $display = $settings['xmlfile']['pattributes']['forms'][$key]['display'] 
					or $display = $settings['xmlfile']['pattributes']['tags'][$key]['display'] 
					or $display = $key;
				$maintext .= "<tr>
					<td>$editform
					<td>$display
				";
			};
		};
		$maintext .= "</table>";
		
		if ( $user['permissions'] == "admin" ) $maintext .= "<hr><h2>Systematic Changes</h2> 
			<p>For making structural changes, you can define a regular expression to apply to all values (not recommendable when changing more than one field).
			<p>Regular expression: s/ <input name=refrom size=10> /
			<input name=reto size=10> /g
			";	

			
		$maintext .= "
			<hr>			<input type=submit value=Search>

			</form>
			";
		
	} else if ( $_POST['selected'] && $settings['cqp']['defaults']['background'] ) {
	
		# Run the actual changes in a background Perl script
		$pid = time();
		$xml = simplexml_load_string("<multiedit pid=\"$pid\"/>", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		$maintext .= "<h1>Executing multi-token edit</h1>";

		$maintext .= "<p>Changes will be running in the background. Click <a href='index.php?action=process&pid=$pid'>here</a> to check the status of the changes<hr>";

		$maintext .= "<p>Underlying query: <a href='index.php?action=cqp&cql={$_POST['query']}'>{$_POST['query']}</a></p>";		
		$query = $xml->addChild("query");
		$query[0] = $_POST['query'];

		$maintext .= "<h2>Changes to be made</h2>";		
		$changes = $xml->addChild("changes");
		foreach ( $_POST['atts'] as $key => $val ) {
			if ( $val != "" ) { 
				$change = $changes->addChild("tok");
				$maintext .= "   - Change $key to $val";
				$change['key'] = $key; 
				$change['val'] = $val; 
			};
		};
		if ( !$change )  { fatal('Nothing to change'); };

		$maintext .= "<h2>Tokens to process</h2>";		
		$filelist = $xml->addChild("files");
		foreach ( $_POST['selected'] as $fileid => $toklist ) {
			$maintext .= "<h3>File: $fileid</h3>";
			$file = $filelist->addChild("file"); $file['id'] = $fileid;
			foreach ( $toklist as $tokid => $val2 ) {
				$tok = $file->addChild("tok"); $tok['id'] = $tokid;
				$tok['org'] = $_POST['orgform'][$fileid][$tokid];
				$maintext .= "<p> - Token: <a target=check href='index.php?action=file&cid=$fileid&tid=$tokid'>$tokid</a>";
			};
		};		

		$maintext .= "<hr><p>Changes are running in the background. Click <a href='index.php?action=process&pid=$pid'>here</a> to check the status of the changes";

		file_put_contents("tmp/pid$pid.xml", $xml->asXML());

		# Now start the process
		$cmd = "perl $ttroot/common/Scripts/multichange.pl $pid > /dev/null &";
		exec($cmd);

	} else if ( $_POST['selected'] ) {

		$maintext .= "<h1>Executing multi-token edit</h1>";
		
		if ( !$_POST['lineedit'] ) {
			$maintext .= "<h2>Changes to be made</h2>";		
			foreach ( $_POST['atts'] as $key => $val ) {
				if ( $val != "" ) { 
					$maintext .= "   - Change $key to $val";
					$changes[$key] = $val; 
				};
			};
			if ( !$changes )  { fatal('Nothing to change'); };
		};
		
		$maintext .= "<h2>Tokens to process</h2>";		
		foreach ( $_POST['selected'] as $fileid => $toklist ) {
			# Open the file
			$fid = $fileid; 
			if ( !file_exists("$fileid") ) { 
				if ( !strpos(".xml", $fileid) ) { $fileid .= ".xml"; };
				
				$fileid = preg_replace("/^.*\//", "", $fileid);
				$test = array_merge(glob("$xmlfolder/**/$fileid")); 
				if ( !$test ) 
					$test = array_merge(glob("$xmlfolder/$fileid"), glob("$xmlfolder/*/$fileid"), glob("$xmlfolder/*/*/$fileid")); 
				$fileid = array_pop($test); 
				# $fileid = $xmlfolder.preg_replace("/^".preg_quote($xmlfolder, '/')."\/?/", "", $temp);
	
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
				
				if ( $_POST['lineedit'] ) {
					$changes = $_POST['vals'][$fileid][$tokid];
					$checks = $_POST['checks'][$fileid][$tokid];
				};
								
				@$result = $xml->xpath("//tok[@id='$tokid'] | //dtok[@id='$tokid']"); 
				$token = $result[0];
				if ( !$token ) { $maintext .= " ! token $tokid not found"; next; };
				
				# Now check if this is the right token
				$orgform = $_POST['orgform'][$fid][$tokid];
				if ( $orgform == "" || ( $token['form'] != $orgform && $token."" != $orgform ) ) {
					$maintext .= "<p> !! token $tokid does not seem to be the right token: $orgform expected, $token found - XML got modified?"; 
					next;  
				};
				if ( !$_POST['lineedit'] ) {
					$maintext .= "<p> - modifying <a target=check href='index.php?action=file&cid=$fileid&tid=$tokid'>".$token['id']."</a> in $fileid";
				};
				
				$marked = 0;
				foreach ( $changes as $chkey => $chval ) {
					# $maintext .= "<p>   - setting $chkey to $chval";
					$inherited = 0;
					if ( $_POST['lineedit'] && $settings['xmlfile']['pattributes']['forms'] ) {
						# Check that this is not an inherited value
						if ( $chval == forminherit($token, $chkey) ) {
							$inherited = 1;
							$maintext .= "<p>   -- Not changing $chkey for $tokid: value matched inherited value";
						} else {
							$token[$chkey] = $chval; 
						};
					} else {
						$token[$chkey] = $chval; 
					};
					if ( !$inherited && ( $chval != $checks[$chkey] || !$checks ) ) {
						if ( $_POST['lineedit'] ) {
							if ( !$marked ) $maintext .= "<p> - modifying <a target=check href='index.php?action=file&cid=$fileid&tid=$tokid'>".$token['id']."</a> in $fileid";
							$maintext .= "<p>   -- Changing $chkey to $chval";
						};
						$changed = 1; $marked = 1;
					};
				};
				
			};
			
			if ( $changed ) {
				$fileid = preg_replace("/^$xmlfolder\//", "", $fileid); # We need to kill the $xmlfolder before saving
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
	
	} else {


		$maintext .= "<h1>Multiple token edit via CQP Search</h1>";
		if ( $_POST['lineedit'] ) $lineedit = 1;


		if ( !$lineedit ) {
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

			$maintext .= "</table><hr><p>Click <a href='index.php?action=$action&act=define&cql=$cql'>here</a> to enter individual values for each result<hr>";
		} else {
			$maintext .= "
				<p style='color: #aa2000; font-weight: bold'>The CQP corpus can become disaligned wrt the XML 
					files after editing tokens. 
					<br>Therefore, always regenerate the CQP corpus 
					before using this function!
				<hr>
				<form action='' method=post>
				";
		};

		$cqp = new CQP();
		$cqp->exec($settings['cqp']['corpus']); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$cqpquery = "Matches = $cql";
		$results = $cqp->exec($cqpquery);

		$sort = $_POST['sort'] or $sort = $_GET['sort'] or $sort = 'word';
		if ( $sort ) {
			# $maintext .= "Sorted by $sort"; - this is not 
			$cqp->exec("sort Matches by $sort");
		};
		# $maintext .= "<P>QUERY: $cqpquery";
		$maintext .= "<input type=hidden name=query value='$cql'>";
		
			$cnt = $cqp->exec("size Matches");
	
		$context = 5;
		$max = $_POST['max'] or $max = $_GET['max'] or $max = $cqpmax or $max = 500; $_POST['max'] = $max;
		$start = $_POST['start'] or $start = $_GET['start'] or $start = 0; $_POST['start'] = $start;
		$end = $start + $max;
		$showform = "word";

		if ( strstr($cql, '@') ) { 
			$tarmat = "target";
			$matchh = "<th>Target word";
		} else $tarmat = "match";
		
			$withtarget = ", $tarmat $showform, $tarmat id";

		if ( $lineedit ) {
			foreach ( $_POST['fld'] as $fld => $tmp ) {
				if ( $fld == "form" ) $fld = "word"; # TODO: This should go when we add "form" as just an additional pattribute
				$more .= ", $tarmat $fld";
			};
			$lineedit = "<input name=lineedit type=hidden value=1>";
		};
		
		$cqpquery = "tabulate Matches $start $end match text_id, match[-$context]..match[-1] $showform, matchend[1]..matchend[$context] $showform, match .. matchend id, match .. matchend $showform $withtarget $more";
		$results = $cqp->exec($cqpquery);

		if ( $debug ) $maintext .= "<p>$cqpquery<PRE>$results</PRE>";
		if ( $_POST['refrom'] ) {
			$maintext .= "<p>Systematic change: s/<b>{$_POST['refrom']}</b>/<b>{$_POST['reto']}</b>/g;";
		};

		$resarr = explode ( "\n", $results ); $scnt = count($resarr);
		$maintext .= "<p>$cnt {%results} for $cql";
		if ( $scnt < $cnt ) { 
			$last = min($end,$cnt);
			$maintext .= " &bull; {%!showing} $start - $last";
			if ($end<$cnt) {
				$maintext .= " (<a onclick=\"document.getElementById('rsstart').value ='$end';  document.resubmit.submit();\">{%next}</a>)";
				$maintext .= "<input type=hidden name=nextnum value='".($end+1)."'><input type=hidden name=cql value='$cql'>";
			};
		};
		$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
			$lineedit
			<table>";
			
		if ( $lineedit ) {
			$maintext .= "<tr><th>File ID<th style='text-align: right'>Left context<th style='text-align: center'>Match<th>Right context";
			foreach ( $_POST['fld'] as $fld => $tmp ) {
				$fldname = $settings['xmlfile']['pattributes']['forms'][$fld]['display']
				or 
				$fldname = $settings['xmlfile']['pattributes']['tags'][$fld]['display']
				or 
				$fldname = $fld;				
				$maintext .= "<th>".$fldname;
			};
			$maintext .= $matchh;
		} else 
			$maintext .= "<tr><th>File ID<th>Sel.<th style='text-align: right'>Left context<th style='text-align: center'>Match<th>Right context$matchh";
		
		foreach ( $resarr as $line ) {
			if ( $line == "" ) continue;
			$linevals = explode ( "\t", $line );
			list ( $fileid, $lcontext, $rcontext, $tid, $word, $targetword, $targetid ) = $linevals;

				$tidarray = explode (" ", $tid );
				$tid = $targetid or $tid = $tidarray[0]; 
				$match = $targetword or $match = $word;

			if ( $lineedit ) {
				$refname = "context";
			} else {
				$refname = $fileid;
			};		
			
			if ( count($tidarray) == 1 || $targetid ) {
				if ( $lineedit ) {
					$checkbox = "<input type=hidden value='treat' name='selected[$fileid][$tid]'>
						<input type=hidden name='orgform[$fileid][$tid]' value='$match'>";
				} else {
					$checkbox = "<td><input type=checkbox value='treat' name='selected[$fileid][$tid]'>
						<input type=hidden name='orgform[$fileid][$tid]' value='$match'>";
				};
			} else {
				$checkbox = "<td><span style='color: #ff9999' title='multiword match'>&block;</span>";
			};
			
			if ( $word == "" ) {
				$match = "(empty)"; $word = "&lt;ee/&gt;";
			};
			
			if ( $match != "" && substr($line,0,1) != "#" ) 
				if ( !$fileid ) $maintext .= "<tr style='color: #aaaaaa'>
					<td>(no id)
					<td>
					<td align=right>$lcontext<td align=center><b>$word</b><td>$rcontext";
				else $maintext .= "<tr>
					<td><a target=check style='font-size: small; margin-right: 10px;' href='{$purl}index.php?action=file&cid=$fileid&jmp=$tid'$target>$refname</a>
					$checkbox
					<td align=right>$lcontext<td align=center><b>$word</b><td>$rcontext";
			 # else $maintext .= "<p>?? $match, $line";
			 
			 if ( $lineedit ) {
			 	$i = 0;
			 	
			 	foreach ( $_POST['fld'] as $fld => $tmp ) {
			 		$fldval = $linevals[$i+7]; $i++;
					$fldvalto = $fldval;
					if ( $_POST['refrom'] ) {
						$fldvalto = preg_replace("/{$_POST['refrom']}/", $_POST['reto'], $fldvalto);
					};
			 		$maintext .= "<td>
			 			<input name=vals[$fileid][$tid][$fld] title=$fld size=10 value='$fldvalto'>
			 			<input name=checks[$fileid][$tid][$fld] title=$fld type=hidden value='$fldval'>			 		
			 		";
			 	};
			 };
			 if ( $matchh ) $maintext .= "<td><b style='color: #992000;'>$targetword</b>";
			 
		};
		$maintext = str_replace('__UNDEF__', '', $maintext);
		
		if ( $lineedit ) 
			$maintext .= "</table><hr><p><input type=submit value='Save changes'> 
				&bull; <a href='index.php?action=cqpedit'>back to search</a>";
		else 
			$maintext .= "</table><hr><p><input type=submit value='Change selected'> 
				<span onClick='selectalltoks();'>select all</span>
				&bull; <a href='index.php?action=cqp'>back to search</a>";
			
		$maintext .= "</form>
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

		# To make sure that we can modify our query, create a hidden post 
		$maintext .= "\n<form action='' method=post id=resubmit name=resubmit>";
		foreach ( $_POST as $key => $val ) {
			$maintext .= "<input type=hidden name=$key value='$val'>";
			if ( is_array($val) ) {
				foreach ( $val as $key2 => $val2 )
					$maintext .= "\n    <input type=hidden id='rs$key$key2' name={$key}[$key2] value='$val2'>";
			} else {
				$maintext .= "\n  <input type=hidden name=$key id='rs$key' value='$val'>";
			};
		};
		$maintext .= "\n</form>";

	};
		
?>