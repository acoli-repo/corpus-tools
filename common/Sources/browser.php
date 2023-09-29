<?php
	// File browser - browse files by category
	// (c) Maarten Janssen, 2018

	$class = $_GET['class'];
	$val = $_GET['val'];

	$brtit = getset("defaults/browser/title", "Document Browser");

	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

	$faction = $action; $saction = $action;
	if ( $settings['cqp']['subcorpora'] ) {
		if ( $act == "select" ) $_SESSION['subc-'.$foldername] = "";
		if ( $_GET['subc'] ) $_SESSION['subc-'.$foldername] = $_GET['subc'];
		$subcorpus = $_GET['subc'] or $subcorpus = $_SESSION['subc-'.$foldername] or $subcorpus = "";
		if ( !$subcorpus ) {
			# fatal("No subcorpus selected");
			$act = "select";
			$cqpcorpus = "";
		} else {
			if ( is_array($settings['cqp']['subcorpora']) && is_array($settings['cqp']['subcorpora'][$subfolder]) ) {
				$subcorpusname = $settings['cqp']['subcorpora'][$subfolder]['display'];
			};
			if ( !$subcorpusname ) $subcorpusname = "{%subc-$subcorpus}";
			
			$subname = strtolower("cqp/$subcorpus");
			$subcdef = file_get_contents($subname);
			if ( preg_match("/HOME (.+)/", $subcdef, $matches )  ) {
				$home = $matches[1];
				$cqpfolder = preg_replace("/.+\/(cqp\/)/", "cqp/", $home);
			} else {
				$cqpfolder = "$cqpfolder/$subfolder";
				$subfolder = preg_replace("/$cqpcorpus-/i", "", $subcorpus);
			};
			
			$cqpcorpus = strtoupper($subcorpus); # a CQP corpus name ALWAYS is in all-caps
			$faction = "$action&act=select";
			$saction = "$action&sub=$subcorpus";
			$subpath = " &gt; <a href='index.php?action=$saction'>$subcorpusname</a>";
		};
	};

	$maintext .= "<h1>{%$brtit}</h1>";

	$titlefld = $settings['cqp']['titlefld'];
	if ( !$titlefld )
		if ( $settings['cqp']['sattributes']['text']['title'] ) $titlefld = "text_title"; else $titlefld = "text_id";


	if ( $settings['defaults']['locale'] ) $localebit = ", '{$settings['defaults']['locale']}'";
	$docname = $settings['defaults']['browser']['documents'] or $docname = "documents";

	# Create the selected options
	$sep = " :: "; foreach ( $_GET['q'] as $rp ) {
		list ( $fld, $val ) = explode(":", $rp);
		$valtxt = $val; $fldtxt = $settings['cqp']['sattributes']['text'][$fld]['display'] or $fldtxt = $fld;
		if ( $settings['cqp']['sattributes']['text'][$fld]['translate'] ) $valtxt = "{%$fld-$valtxt}";
		$sels .= "<div class='selbox' title='$fldtxt'><span rst=\"$rp\" onclick='del(this);' class='x'>x</span> $valtxt</div>";
		$val = preg_quote($val);
		$cqlrest .= $sep."match.text_$fld = \"$val\""; $sep = " & ";
		$jsrest .= "'$rp',";
	};
	$selmenu .= "<p>$sels</p>";

	$maintext .= "
		<script language=Javascript>
			function sortList(ul){
				var new_ul = ul.cloneNode(false);

				// Add all lis to an array
				var lis = [];
				for(var i = ul.childNodes.length; i--;){
					if(ul.childNodes[i].nodeName === 'LI') {
						lis.push(ul.childNodes[i]);
					};
				}

				// Sort the lis in descending order - locale dependent
				lis.sort((a, b) => a.getAttribute('key').localeCompare(b.getAttribute('key')$localebit));
				console.log(lis);

				// Add them into the ul in order
				for(var i = 0; i < lis.length; i++)
					new_ul.appendChild(lis[i]);
				ul.parentNode.replaceChild(new_ul, ul);
			};

			var jsrest = [$jsrest];
			function add(elm) {
				var rst = elm.getAttribute('rst');
				jsrest.push(rst);
				elm.style.display = 'none';
				requery();
			};
			function del(elm) {
				var i = 0;
				var rst = elm.getAttribute('rst');
				while ( i < jsrest.length) {
					if (jsrest[i] === rst) {
					  jsrest.splice(i, 1);
					} else {
					  ++i;
					}
				};
				elm.parentNode.style.display = 'none';
				requery();
			};
			function requery() {
				newurl = 'index.php?action=$action';
				for ( var i =0; i < jsrest.length; i++ ) {
					newurl += '&q[]='+jsrest[i];
				};
				top.location = newurl;
			};
			var facslist = {};
			var intfunc = setInterval(rolling, 2000);
			var intelm; var intcid; var intidx = 0;
			function rollimages( elm ) {
				var cid = elm.getAttribute('cid');
				intcid = cid;
				intelm = elm;
				intidx = 0;
				rolling();
			};
			function rolloff( elm ) {
				var cid = elm.getAttribute('cid');
				if ( facslist[cid] ) { elm.src = intelm.src = facslist[cid][0]; };
				intcid = 0;
			};
			function rolling() {
				if ( !intcid ) { return; }
				if ( !facslist[intcid] ) {
				  var xhttp = new XMLHttpRequest();
				  xhttp.onreadystatechange = function() {
					if (this.readyState == 4 && this.status == 200) {
					  var tmp = JSON.parse(this.responseText);
					   facslist[tmp.cid] = tmp.facs;
					   rolling();
					}
				  };
				  var url = 'index.php?action=ajax&data=facs&cid='+intcid;
				  xhttp.open('GET', url, true);
				  xhttp.send();
				} else {
					intidx++; if ( intidx >= facslist[intcid].length ) { intidx = 0; };
					intelm.src = facslist[intcid][intidx];
				};
			};
			</script>
			";
	if ( $settings['defaults']['browser']['select'] == "menu" ) {
		$subsel = "menu"; $all = 1;
	};
	if ( $_GET['all'] || $_GET['show'] == "all" ) $all = 1;
	
	if ( $act == "select" ) {
	
		$corps = subcorpora();	
	
		if ( !$corps ) fatal("No subcorpora of this corpus are searchable at this time");
		
		$maintext .= "<p>{%!documents}$subpath<hr><ul>";
		# $maintext .= getlangfile("subc-select");
	
		$fullcorp = strtolower($settings['cqp']['corpus']);
		foreach ( $corps as $corpid => $corpdata ) {
			$corpname = $corpdata['name'];	
			$corpfld = $corpf[$corpid];
			$rawsize = hrnum(filesize("$corpfld/word.corpus")/4);
			if ( $rawsize > 0 || 1==1 ) $maintext .= "<li><a href='index.php?action=$action&subc=$corpid'>$corpname</a>";
		};
		$maintext .= "</ul>";
	
	} else if ( ( $class && $val ) || $all ) {

		// Do not allow searches while the corpus is being rebuilt...
		if ( file_exists("tmp/recqp.pid") ) {
			fatal ( "Search is currently unavailable because the CQP corpus is being rebuilt. Please try again in a couple of minutes." );
		};

		include ("$ttroot/common/Sources/cwcqp.php");
		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		# $val = htmlentities($val);
		$qval = cqlprotect($val); # Protect quotes
		if ( $all ) $cqpquery = "Matches = <text> [] $cqlrest";
		else if ( $item['values'] == "multi" ) $cqpquery = "Matches = <text> [] :: match.text_$class = '.*$qval.*'";
		else $cqpquery = "Matches = <text> [] :: match.text_$class = '$qval'";
		$cqp->exec($cqpquery);

	# Deal with subselection style
	if ( $subsel == "menu" ) {
		# Make the menu bar options
		foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {
			if ( !is_array($item) || ( $item['type'] != "select" && !$item['browse'] ) ) continue;
			if ( $item['admin'] && !$username ) continue;
			$xkey = "text_$key"; 

			$tmp = $cqp->exec("group Matches match $xkey");
			unset($optarr); $optarr = array();
			$resarr = explode ( "\n", $tmp );
			foreach ( $resarr as $line ) { 
				list ( $kva, $kcnt ) = explode("\t", $line ); unset($kvl);
				if ( $kva != "" && $kva != "_" ) {
					if ( $item['values'] == "multi" ) {
						$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
						$kvl = explode ( $mvsep, $kva );
					} else {
						$kvl = array ( $kva );
					}
				
					foreach ( $kvl as $kval ) {
						if ( $item['type'] == "kselect" || $item['translate']  ) $ktxt = "{-%$key-$kval}"; else $ktxt = $kval;
						if ( $ktxt == "_" ) $ktxt = "none";
						if ( $presets[$xkey] == $kval ) $sld = "selected"; else $sld = "";
						$optarr[$kval] = "<p onclick=\"add(this);\" rst =\"$key:$kval\");\">$ktxt ($kcnt)</p>"; 
					};
				};
				foreach ( $kvl as $kval ) {
					if ( $kval != "" && $kval != "_" ) {
						if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
							else $ktxt = $kval;
						if ( $presets[$xkey] == $kval ) $sld = "selected"; else $sld = "";
						$optarr[$kval] = "<p onclick=\"add(this);\" rst =\"$key:$kval\");\">$ktxt ($kcnt)</p>"; 
					};
				};
			};
			if ( $item['sort'] == "numeric" ) sort( $optarr, SORT_NUMERIC ); 
			else sort( $optarr, SORT_LOCALE_STRING ); 
			$optlist = join ( "", $optarr );
		
			$scnt = count($optarr);
			$selmenu .= "<h2>{$item['display']} ($scnt)</h2>";
			$selmenu .= "<div style='max-height: 250px; margin-bottom: 10px; overflow-y: scroll;'>$optlist</div>";
		};
	};
			
		$oval = urlencode($val);
		if ( $val == "" || $val == "_" ) $val = "({%none})";
		else if ( $item['type'] == "kselect" || $item['translate'] ) $val = "{%$class-$val}";

		$cnt = $cqp->exec("size Matches"); $size = $cnt;

		$max = $_GET['max'] or $max = 100;
		$start = $_GET['start'] or $start = 0;
		$stop = $start + $max;
		if ( $_GET['show'] ) $morel = "&show={$_GET['show']}";
		if ( $size > $max || $start > 0 ) {
			$next = $stop; $beg = $start + 1; $prev = max(0, $start - $max);
			if ( $start > 0 ) $bnav .= " <a href='index.php?action=$saction&class=$class&val=$oval&start=$prev$morel'>{%previous}</a> ";
			if ( $size > $max ) $bnav .= " <a href='index.php?action=$saction&class=$class&val=$oval&start=$next$morel'>{%next}</a> ";
			$nav = " - {%showing} $beg - $stop - $bnav";
		};
		
		if ( $subsel ) $path = "<div id=floatbox>$selmenu</div>";
		else if ( $all ) $path = "<a href='index.php?action=$faction'>{%!documents}</a>$subpath > all";
		else $path = "<a href='index.php?action=$faction'>{%!documents}</a>$subpath > <a href='index.php?action=$saction&class=$class'>{%$cat}</a> > $val";


		if ( $cnt > 0 ) {
			if ( $settings['defaults']['browser']['style'] == "table" || $settings['defaults']['browser']['style'] == "facs" ) {
				$acnt = $bcnt = 0;
				foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {
					if ( $key == $class ) continue;
					if ( !is_array($item) ) continue; # Only do real children
					if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
					$val = $item['display']; # $val = $item['long'] or
					if ( $item['type'] == "group" ) {
						$fldval = $val; # substr($key,4);
						if ( $fldval != "" ) $fldtxt = " ($fldval)";
						else $fldtxt = "";
					} else if ( $item['noshow'] ) {
						# Ignore items that are not to be shown
					} else if ( $key != "id" ) {
						$moreatts .= ", match $xkey";
						$moreth .= "<th>{%$val}";
						$atttik[$bcnt] = $key; $bcnt++;
						$atttit[$acnt] = $val;
						$acnt++;
					};
				}; 
				if ( $settings['defaults']['browser']['style'] == "facs" && $settings['cqp']['pattributes']['facs'] ) {
					$withfacs = 1;
					$moreatts .= ", match facs";
				};
				$cqpquery = "tabulate Matches $start $stop match text_id$moreatts";
				$results = $cqp->exec($cqpquery);
				
				$resarr = explode ( "\n", $results ); $scnt = count($resarr);
				$maintext .= "<p>$path<p>$cnt {%$docname}";
// 				if ( $scnt < $cnt ) {
// 					$maintext .= " &bull; {%!showing} $start - $stop";
// 				};
// 				if ( $start > 0 ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$before'; document.resubmit.submit();\">{%previous}</a>";
// 				if ( $stop < $cnt ) $maintext .= " &bull; <a onclick=\"document.getElementById('rsstart').value ='$stop'; document.resubmit.submit();\">{%next}</a>";
				$maintext .= $nav;

				if ( $settings['defaults']['browser']['style'] == "facs" ) {
					$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
						<table id=facstable>";
				} else { 
					$maintext .= "<hr style='color: #cccccc; background-color: #cccccc; margin-top: 6px; margin-bottom: 6px;'>
						<table><tr><th>ID$moreth";
				};
				if ( !$settings['defaults']['browser']['title'] ) $settings['defaults']['browser']['title'] = "title";
				foreach ( $resarr as $line ) {
					$fatts = explode ( "\t", $line ); $fid = array_shift($fatts);
					if ( !$fid ) continue; # Skip empty rows
					if ( $admin ) {
						$fidtxt = preg_replace("/^\//", "", $fid );
					} else {
						$fidtxt = preg_replace("/.*\//", "", $fid );
					};
					# Translate the columns where needed
					foreach ( $fatts as $key => $fatt ) {
						if ( $key == $class ) continue;
						$attit = $atttik[$key];
						if ( $attit == $settings['defaults']['browser']['title'] ) {
							$titelm = $fatt;
							# TODO: This was here for a reason - why did we want to delete this? Only in facs?
							if ( $settings['defaults']['browser']['style'] == "facs" ) unset($fatts[$key]);
						};
						$tmp = $settings['cqp']['sattributes']['text'][$attit]['type'];
						if ( $settings['cqp']['sattributes']['text'][$attit]['type'] == "kselect" || $settings['cqp']['sattributes']['text'][$attit]['translate'] ) {
							if ( $settings['cqp']['sattributes']['text'][$attit]['values'] == "multi" ) {
								$fatts[$key] = ""; $sep = "";
								foreach ( explode(",", $fatt) as $fattp ) { $fatts[$key] .= "$sep{%$attit-$fattp}"; $sep = ", "; };
							} else $fatts[$key] = "{%$attit-$fatt}";
						};
					};
					if ( $settings['defaults']['browser']['style'] == "facs" ) {
						$facs = array_pop($fatts);
						$cid = preg_replace("/.*\//", "", $fid);
						$opttit = $titelm or $opttit = $cid;
						$ff = ""; if ( $withfacs && $facs ) {
							if ( file_exists("Thumbnails/$facs") ) $ffolder = "Thumbnails"; else $ffolder = "Facsimile";
							$ff = "<a href='index.php?action=text&cid=$cid'><img onmouseover=\"rollimages(this);\" onmouseout=\"rolloff(this);\" cid=\"$cid\" style='height: 100px; object-fit: cover; width: 100px; margin-right: 10px;' src='$ffolder/$facs'/></a>";
						};
						$maintext .= "<tr><td style='background-color: white;'>$ff
							<td><a href='index.php?action=file&cid=$cid' style='font-size: large;'>$opttit</a><table class='subtable'>";
						foreach ( $fatts as $key => $val ) { 
							if ( $val != "_") $maintext .= "<tr><th>{$atttit[$key]}</th><td>$val</td></tr>"; 
						};
						$maintext .= "</table>";
					} else {
						$maintext .= "<tr><td><a href='index.php?action=file&cid={$fid}'>{$fidtxt}</a><td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>".join ( "<td style='padding-left: 6px; padding-right: 6px; border-left: 1px solid #dddddd;'>", $fatts );
					};
				};
				$maintext .= "</table>";

			} else {
				$maintext .= "<p>$path
					<p>$cnt {%documents} $nav
					<hr><ul id=sortlist>";

				$catq = "tabulate Matches $start $stop match text_id, match $titlefld";
				$results = $cqp->exec($catq);

				foreach ( explode("\n", $results) as $result ) {
					list ( $cid, $title ) = explode("\t", $result);
					if ( $titlefld == "text_id" ) {
						$title = preg_replace("/.*\/(.*?)\.xml/", "$1", $cid);
					};
					if ( $cid && $title ) $maintext .= "<li key='$title'><a href='index.php?action=file&cid=$cid'>$title</a></li>";
				};
				$maintext .= "</ul>";
			};
		} else {
			if ( $username ) $maintext .= "<p class=adminpart>Failed query: ".htmlentities($cqpquery);
		};
		
	} else if ( $class ) {

		$item = $settings['cqp']['sattributes']['text'][$class];
		$cat = $item['display'];

		$maintext .= "<p><a href='index.php?action=$faction'>{%!documents}</a>$subpath > {%$cat}
			<hr>";

		$list = file_get_contents("$cqpfolder/text_$class.avs");

		foreach ( explode("\0", $list) as $val ) {
			if ( $item['values'] == "multi" ) {
				foreach ( explode(",", $val) as $pval ) $vals[trim($pval)]++;
			} else $vals[$val]++;
		};
		
		if ( $item['type'] == "date") {
			$datelist = "\"".join("\",\"", array_keys($vals))."\"";
			$maintext .= "
				<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css\">
				<form name=\"datep\" id=\"datep\">
				<input type=\"text\" class=\"datepicker\" id=\"datef\">
				</form>
				<script src=\"https://cdn.jsdelivr.net/npm/flatpickr\"></script>
				<script>
					var datelist = [$datelist];
					var lastat;
					function checkmonth() {
						var tocheck = flatp.currentMonth + 12*flatp.currentYear;
						var found = false; var bef = false; var aft = false;
						console.log('looking for: ' + flatp.currentYear + '.' + flatp.currentMonth); 

						var dir = 1;
						if ( lastat < tocheck ) { 
							dir = 0;
						};
						lastat = tocheck;
						
						for ( var i=0; i<datelist.length; i++ ) {
							var tmp = datelist[i].split('-');
							var checkval = parseInt(tmp[1])-1 + 12*(parseInt(tmp[0]));

							if ( checkval < tocheck ) { 
								bef = true; 
							} else if ( checkval > tocheck ) { 
								aft = true; 
							} else if ( checkval == tocheck ) {
								found = true; break;
							};
						};
						
						if ( dir ) {
							if ( !found && bef ) { 
								flatp.changeMonth(-1);
							} else if ( !found && !bef ) {
								flatp.changeMonth(1);
							};
						} else {
							if ( !found && aft ) { 
								flatp.changeMonth(1);
							} else if ( !found && !aft ) {
								flatp.changeMonth(-1);
							};
						};
						
					};
					var flatp;
					flatpickr(\"#datef\", {
						enable: datelist,
						inline: true,
						onReady: function(selectedDates, dateStr, instance) {
							flatp = instance;
							checkmonth();
						},
						onMonthChange: function(selectedDates, dateStr, instance) {
							checkmonth();
						},
						onChange: function(selectedDates, dateStr, instance) {
							window.open('index.php?action=$saction&class=$class&val='+dateStr, '_self');
						},
					});
				</script>";
				
	
		} else {
			$maintext .= "<ul id=sortlist>";
			$scnt = count($vals);
			foreach ( $vals as $val => $cnt ) {
				$oval = urlencode($val);
				if ( $val == "" || $val == "_" ) {
					if ( !$settings['cqp']['listnone'] ) continue;
					$val = "({%none})";
				} else if ( $item['type'] == "kselect" || $item['translate'] ) $val = "{%$class-$val}";
				$maintext .= "<li key='$val'><a href='index.php?action=$saction&class=$class&val=$oval'>$val</a></li>";
			};
			$maintext .= "</ul><script language=Javascript>sortList(document.getElementById('sortlist'));</script>";
			$maintext .= "<hr><p>$scnt {%results}";
		};

	} else {

		if ( $subpath ) $doctitle = "<a href='index.php?action=$faction'>{%!documents}</a>$subpath";
		else $doctitle = getlangfile("browsertext", true);
		
		$maintext .= "$doctitle
			<hr><ul id=sortlist>";
		foreach ( $settings['cqp']['sattributes']['text'] as $key => $item ) {

			if ( !is_array($item) ) continue;
			if ( strstr('_', $key ) ) { $xkey = $key; } else { $xkey = "text_$key"; };
			$cat = $item['display']; # $val = $item['long'] or

			if ( ( $item['type'] == "select" || $item['browse'] || $item['type'] == "kselect"  || $item['type'] == "date" )
					&& is_array($item) && ( ( ( !$item['noshow'] || $item['browse'] ) && !$item['admin']  ) || $username ) ) {
				# Check we have value (only _ has size 2)
				if ( filesize("$cqpfolder/text_$key.avs") > 2 ) {
					$foundsome = 1;
					$maintext .= "<li key='$cat'><a href='index.php?action=$saction&class=$key'>{%$cat}</a>";
					if ( $username ) $maintext .= " <span style='color: grey'>".filesize("$cqpfolder/text_$key.avs")."</span>";
				};
			};
		};
		$maintext .= "<li key='$cat'><a href='index.php?action=$saction&show=all'>{%All documents}</a></li>";
		$maintext .= "</ul>"; //<script language=Javascript>sortlist(document.getElementById('sortlist'));</script>";
		if ( !$foundsome ) $maintext .= "<script language=Javascript>top.location='index.php?action=$gaction&show=all'</script>";
	};

	if ( $username ) {
			$maintext .= "<hr><div class=adminpart>The files shown here are only the files in the indexed (CQP) corpus -
				to see all XML files, click <a href='index.php?action=files'>here</a>,
				and to update the CQP corpus, click <a href='index.php?action=recqp'>here</a> </div>";
	};


?>
