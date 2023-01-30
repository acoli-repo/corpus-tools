<?php

	if ( !$_POST ) {
		$_POST = $_GET;
	};

	if ( $_GET['query'] ) {
		$qtype = $_GET['type'] or $qtype = "BTQL";
	};
	if ( !$_POST['tqs'] ) {
		$_POST['tqs']['BTQL'] = '[upos="NOUN" and [deprel="case"] ]';
		$_POST['tqs'][$type] = $_GET['query'];
	};

	if ( !$rawq && $_GET['qid'] && ( $userid || $username ) ) {
		require("$ttroot/common/Sources/querymng.php");
		$qid = $_GET['qid'];
		$rawq = getq($qid);
	};
	$frontview = "BTQL";
	if ( !$btql && !$rawq ) {
		$btql = "[upos=\"NOUN\" and [upos=\"ADP\"] ]";
	} else if ( !$btql && $rawq ) {
		$frontview = "rawq";
	};

	$about['BTQL'] = "<p>BTQL (Bracketed Tree Query Language) is a simple language using a syntax similar to that of CQL, but with
		the option to search through (dependency) trees. BTQL does not have a search interface of itself, but is used to convert to
		various tree query languages.";
	$syntax['BTQL'] = "index.php?action=btql-text";
		
	$fldr = $_GET['folder'] or $fldr = $settings['defaults']['udxp']['folder'] or $fldr = "udxp";
	$show = $_POST['show'] or $show = "text";

	$start = $_POST['start'] or $start = 0;
	$max = $_POST['perpage'] or $max = 50;

	if (is_array($settings['qlis'])) $tqs = array_keys($settings['qlis']);
	else $tqs = array ( "BTQL", "UDAPI", "CQL" , "PML-TQ" ); 
	
	if(count($tqs) == 1 ) {
		$settingsdefs .= "document.getElementById('tqbuts').style.display = 'none';\n";
		$qtype = $frontview = $tqs[0];
	} else if ( !in_array($tqs, $qtype ) ) {
		$qtype = $frontview = $tqs[0];
	};
	
	$tqlist = '"'.join('", "', $tqs).'"';
	$abouts = "<div id='about' style='display: none; position: absolute; right: 25px; top: 80px; padding: 10px; width: 500px; z-index: 1000; background-color: #eeeeee; border: 1px solid #555555;'>
	<span style=\"float: right; font-size: small; margin-top: -5px;\" onClick=\"document.getElementById('about').style.display='none';\">close</span>
	<div id='about-txt'></div>
	<div id='syntax-link'></div>
	</div>";
	foreach ( $tqs as $tq  ) {
		$nofile = ""; $unusable = "";
		if ( file_exists("Sources/query-$tq.php") || file_exists("$sharedfolder/Sources/query-$tq.php") || file_exists("$ttroot/common/Sources/query-$tq.php") ) {
			if ( file_exists("Sources/query-$tq.php") ) include("Sources/query-$tq.php");
			else if ( file_exists("$sharedfolder/Sources/query-$tq.php") ) include("$sharedfolder/Sources/query-$tq.php");
			else if ( file_exists("$ttroot/common/Sources/query-$tq.php") ) include("$ttroot/common/Sources/query-$tq.php");
			else {
				$nofile = 1;
				if ( $username ) $maintext .= "<div class='adminpart warning'>No query definition file found for $tq (query-$tq.php)</div>";
			};
			if ( $unusable && $username ) $maintext .= "<div class='adminpart warning'>Query language $tq is not usable ($unusable)</div>";
		};
		$but = "<div class=button onClick=\"switchqv('$tq');\" id='tb-$tq' value='$tq'>$tq</div>";
		if ( $tq == "BTQL" ) {
			$tqbuts = "$but &Rarr; ";
		} else if ( $frombt[$tq] ) {
			$transbuts .= $but;
			$funclist .= $frombt[$tq];
		} else if ( !$nofile ) {
			$notrbuts .= $but;
		};
		$tqvalue = $_POST['tqs'][$tq];
		$hinttxt = $hints[$tq]; if ( $hinttxt ) { $hinttxt = str_replace('"', "&quot;", $hinttxt); $hint = "placeholder=\"$hinttxt\""; };
		$tqinput .= "<textarea id='ta-$tq' $hint title='$tq' style='width: 100%; height: 120px;' name='tqs[$tq]'>$tqvalue</textarea>";
		
		$abouts .= "<div id='abouts-$tq' style='display: none' syntax=\"{$syntax[$tq]}\"><div id='help-$tq'>{$about[$tq]}</div></div>";
	}; 

	# Document selection list	
	if ( !$corpusfolder ) $corpusfolder = "cqp";
	$cqpatts = $settings['cqp']['sattributes'];
	$docsel .= "<h3>Metadata Restrictions</h3>
		<table>";
	# Deal with old-style pattributes as xattribute
	# Deal with any additional level attributes (sentence, utterance)
	foreach ( $settings['cqp']['sattributes'] as $xatts ) {
		if ( !$xatts['display'] ) continue; 
		$lvl = $xatts['key']; $sep = "";
		if ( $lvl != "text" && !$settings['cqli']['sattributes'][$lvl] ) continue; # Only do some levels
		$lvltit = $xatts['name'] or $lvltit = $xatts['display'];
		$metarows .= $sep."'$lvl'"; $sep = ", ";
		$docsel .= "$hr<tr id='meta-$lvl'><th>$lvltit</th><td style='padding: 0;'><table style='margin: 0;'>"; $hr = "<hr>";
		foreach ( $xatts as $key => $item ) {
			if ( !is_array($item) ) { continue; };
			$xkey = "{$xatts['key']}_$key";
			$val = $item['long']."" or $val = $item['display']."";
			if ( $item['type'] == "group" ) { 
				$docsel .= "<tr><td>&nbsp;<tr><td colspan=2 style='text-align: center; color: #992000; font-size: 10pt; border-bottom: 1px solid #aaaaaa; border-top: 1px solid #aaaaaa;'>{%$val}";
			} else {
				if ( $item['nosearch'] ) $a = 1; # Ignore this in search 
				else if ( $item['type'] == "range" ) 
					$docsel .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey:start] value='' size=10>-<input name=atts[$xkey:end] value='' size=10>";
				else if ( $item['type'] == "select" || $item['type'] == "kselect" ) {
					# Read this index file
					$tmp = file_get_contents("$corpusfolder/$xkey.avs"); unset($optarr); $optarr = array();
					foreach ( explode ( "\0", $tmp ) as $kva ) { 
						if ( $kva ) {
							if ( $item['values'] == "multi" ) {
								$mvsep = $settings['cqp']['multiseperator'] or $mvsep = ",";
								$kvl = explode ( $mvsep, $kva );
							} else {
								$kvl = array ( $kva );
							}
					
							foreach ( $kvl as $kval ) {
								if ( $item['type'] == "kselect" ) $ktxt = "{%$key-$kval}"; else $ktxt = $kval;
								$optarr[$kval] = "<option value='$kval'>$ktxt</option>"; 
							};
						};
						foreach ( $kvl as $kval ) {
							if ( $kval && $kval != "_" ) {
								if ( $item['type'] == "kselect" || $item['translate'] ) $ktxt = "{%$key-$kval}"; 
									else $ktxt = $kval;
								$seld = ""; if ( $kval == $_POST['atts'][$xkey] ) { $seld = "selected"; };
								$optarr[$kval] = "<option value='$kval' $seld>$ktxt</option>"; 
							};
						};
					};
					if ( $item['sort'] == "numeric" ) sort( $optarr, SORT_NUMERIC ); 
					else sort( $optarr, SORT_LOCALE_STRING ); 
					$optlist = join ( "", $optarr );
					if ( $item['select'] == "multi" ) {
						$multiselect = "multiple";  $msarr = "[]";
						$mstext = "select choices";
					} else {
						$multiselect = ""; $msarr = "";
						$mstext = "select";
					};
					$docsel .= "<tr><th span='row'>{%$val}<td><select name=atts[$xkey]$msarr $multiselect><option value=''>[{%$mstext}]</option>$optlist</select>";
				} else 
					$docsel .= "<tr><th span='row'>{%$val}<td><input name=atts[$xkey] value='' size=40>";
			};
		};
		$docsel .= "</table></td></tr>"; 
	};	
	$settingsdefs .= "\n\t\tvar metarow = [$metarows];";
	$docsel .= "</table>";
	
	# rollover defs for tokens in results
	$showform = $_POST['showform'] or $showform = $_GET['showform'] or $showform = 'form';
	if ( $showform == "word" ) $showform = $wordfld;
	$jsonforms = array2json($settings['xmlfile']['pattributes']['forms']);
	$jsontrans = array2json($settings['transliteration']);
	// Load the tagset 
	$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
	$settingsdefs .= "\n\t\tvar tagdef = ".array2json($settings['xmlfile']['pattributes']['tags']).";";
	$sep = ""; foreach ( $metas as $key => $val ) { $metaa .= $sep."'$key': ['".join("', '", $val)."']"; $sep = ", "; };
	$settingsdefs .= "\n\t\tvar metas = { $metaa };";
	require_once ( "$ttroot/common/Sources/tttags.php" );
	$tttags = new TTTAGS($tagsetfile, false);
	if ( is_array($tttags->tagset) && $tttags->tagset['positions'] ) {
		$tmp = $tttags->xml->asXML();
		$tagsettext = preg_replace("/<([^ >]+)([^>]*)\/>/", "<\\1\\2></\\1>", $tmp);
		$maintext .= "<div id='tagset' style='display: none;'>$tagsettext</div>";
	};
	
	$tophelp = getlangfile("btqhelp-text");
	
	$treex = $settings['defaults']['treex'] or $treex = "deptree";
	$maintext .= "<h1>Tree Query</h1>
	
		<style>
			.button { display: inline-block; background-color: #eeeeee; border: 1px solid #444444; padding: 2px 10px 2px 10px; }
			.button[active] { background-color: #66ff66; }
		</style>
	
		$tophelp		
		$qname
		<form action='index.php?action=$action' method=post id='qf' onSubmit='return false;'>
		
		<div id=tqbuts>
		<div style='display: inline-block;'>$tqbuts$transbuts</div> 
		<div style='display: inline-block; float: right;'>$notrbuts</div> 
		<hr>
		</div>
		<table style='width: 100%;'>
		<tr><td valign=top>
		$tqinput
		</td><td style='width: 45%; padding-left: 15px;'>
		$docsel
		</td></tr>
		</table>
		<div class=warning id=warnings></div>
			
		$clusteropt
		$abouts

		<p><input type=button id=runq onClick='runquery();' value='Run Query'> <a onClick='showhelp();'>Help</a>
		</form>
		
		<hr>
		<div id=navdiv style='margin-bottom: 10px;'></div>
		<div id=mtxt><table id=resulttable></table></div>
		<div id=loadmore style='margin-top: 10px;'></div>

		<style>
			.blink_me {
			  animation: blinker 1s linear infinite;
			}

			@keyframes blinker {
			  50% {
				opacity: 0;
			  }
			}
		</style>
		<script language='Javascript' src=\"$jsurl/btqlparser.js\"></script>
		<script language=Javascript src='$jsurl/tokedit.js'></script>
		<script language=Javascript src='$jsurl/tokview.js'></script>
		<script>
			var frontview = '$frontview';
			var tqs = [$tqlist];
			var warnings = document.getElementById('warnings');
			var qid = 0;
			var start = $start;
			var perpage = $max;
			var lastq = '';
			var warn;
			
			var username = '$username';
			var formdef = $jsonforms;
			var orgtoks = new Object();
			var attributelist = Array($attlisttxt);
			$settingsdefs;
			var lang = '$lang';
			$attnamelist
			var orgXML = document.getElementById('mtxt').innerHTML;
			
			function showhelp() {
				var fhelp = document.getElementById('help-'+frontview);
				if ( fhelp ) {
					document.getElementById('about').style.display = 'block';
					document.getElementById('about-txt').innerHTML = '<h2>About '+frontview+'</h2>' + fhelp.innerHTML;
					var synt = document.getElementById('abouts-'+frontview).getAttribute('syntax');
					document.getElementById('syntax-link').innerHTML = '';
					if ( synt ) {
						document.getElementById('syntax-link').innerHTML = '<p><a href=\"'+synt+'\" target=help>Syntax page</a>';
					};
				};
			};
			
			function parsebq(fld) {
				var btql = fld.value;
				if ( ! btql ) return -1;
				// Run the parser (PEGJS)
				var parser = PARSER; var parsed;
				var sql = '';
				try {
					parsed = parser.parse(btql);
					warnings.innerHTML = '';
				} catch (err) {
					parsed = false;
					warnings.innerHTML = err;
				};
				if ( parsed ) {
					var toks = 'tok as t1';
					sql = bt2sql(parsed);
				};
				document.getElementById('rawq').value = sql;
			};

			function switchqv( toview = 'BTQL' ) {
				document.getElementById('about').style.display = 'none';
				btql = document.getElementById('ta-BTQL').value;
				if ( toview == 'BTQL' ) {
					document.getElementById('runq').disabled = true;
				} else {
					document.getElementById('runq').disabled = false;
				}; 
				if ( btql && frombt[toview] ) { 
					var parser = PARSER; var parsed;
					try {
						parsed = parser.parse(btql);
						warnings.innerHTML = '';
					} catch (err) {
						parsed = false;
						warnings.innerHTML = err;
					};
					if ( parsed ) {
						warn = '';
						rawq = frombt[toview](parsed); 
						warnings.innerHTML = warn; // show whatever warning the conversion gives
						document.getElementById('ta-'+toview).value=rawq;
					} else {
						warnings.innerHTML = err;
					};
				} ;
				frontview = toview;
				for ( n in tqs ) {
					tq = tqs[n];
					if ( tq == frontview ) {
						document.getElementById('tb-'+tq).setAttribute('active', 1);
						document.getElementById('ta-'+tq).style.display='block';
					} else {
						document.getElementById('tb-'+tq).removeAttribute('active');
						document.getElementById('ta-'+tq).style.display='none';
					};
				};
				for ( n in metarow ) {
					lvl = metarow[n];
					var mr = document.getElementById('meta-'+lvl);
					if ( toview == 'BTQL' || ( metas[toview] && metas[toview].indexOf(lvl) != -1 ) ) {
						mr.style.opacity = '1';
					} else {
						mr.style.opacity = '0.3';
					};
				};
			};
						
			function runquery() {
				start = 0; 
				document.getElementById('navdiv').innerHTML = '<i style=\"color: #aaaaaa;\">Running the query - please wait</i>';
				document.getElementById('resulttable').innerHTML = '';
				if ( frontview == 'BTQL' ) { 
					alert('Select a query language with a search engine first.'); 
					return false; 
				}
				rawq = document.getElementById('ta-'+frontview).value;
				if ( lastq == '' || lastq != rawq ) {
					qid  = Date.now() + '' + Math.floor(Math.random() * 100000);
				};
				lastq = rawq;
				loadmore();
				return false;
			};

			var blinker;
			function dlall(elm) {
				blinker = elm;
				blinker.classList.add('blink-me');
				var fileName = '$foldername-results.txt';
				requrl = 'index.php?action=apiquery';
				var postdata = 'type='+frontview+'&qid='+qid+'&perpage=all&start=0&query='+encodeURIComponent(rawq);
				for ( var fld in document.getElementById('qf').elements ) {
					if ( fld.substr(0,5) == 'atts[' ) {		
						val =  document.getElementById('qf')[fld].value;
 						if ( val != '' ) { postdata = postdata + '&' + fld + '=' + val; };
					};
				};
				var url = requrl + '&format=text&' + postdata;
				console.log(url);
			  fetch(url, { method: 'get', mode: 'no-cors', referrerPolicy: 'no-referrer' })
				.then(res => res.blob())
				.then(res => {
				  const aElement = document.createElement('a');
				  aElement.setAttribute('download', fileName);
				  const href = URL.createObjectURL(res);
				  aElement.href = href;
				  // aElement.setAttribute('href', href);
				  aElement.setAttribute('target', '_blank');
				  aElement.click();
				  URL.revokeObjectURL(href);
				  blinker.classList.remove('blink-me');
				});
			};

			function loadmore () {
				var xhttp = new XMLHttpRequest();
				if ( document.getElementById('navdiv').innerHTML == '' ||  document.getElementById('navdiv').innerHTML.includes('Showing') ) {
					document.getElementById('loadmore').innerHTML = '<span style=\"color: #aaaaaa; text-style: italics;\">loading results - please wait</span>';						
				} else {
					document.getElementById('loadmore').innerHTML = '';						
				};
				xhttp.onreadystatechange = function() {
				  if (this.readyState == 4 && this.status == 200) {
					 document.getElementById('navdiv').innerHTML = '';
					 data = this.responseText;
					 var json
					 try {
						json = JSON.parse(data);
					 } catch(e) {
						document.getElementById('navdiv').innerHTML = '<b>Invalid data in query results: '+e+'</b>';				  	
						console.log(data);				  	
						return false;				  	
					 }
					 if ( typeof(json) == 'undefined' ) {
						document.getElementById('navdiv').innerHTML = '<b>Invalid data in query results</b>';
						return false;				  	
					 } else if ( json.error ) {
						document.getElementById('navdiv').innerHTML = '<b>Error in query: ' + json.error + '</b>';
						console.log(json);				  	
						return false;
					 };
					 totcnt = json.total;
					 navtxt = totcnt + ' results';
					 end = start + perpage;
					 if ( totcnt > end ) {
						navtxt = 'Showing 1 - ' + end + ' of ' + totcnt + ' results';
					    document.getElementById('loadmore').innerHTML = '<a onClick=\"loadmore();\">load more results</a>';						
					 } else {
						 document.getElementById('loadmore').innerHTML = '';
					 };
					 start = end;
					 document.getElementById('navdiv').innerHTML = navtxt;
					 if ( json.results.length > 0 ) {
						 document.getElementById('butlist').innerHTML = ' &bull; <a onClick=\"dlall(this);\">{%download results}</a>';
					 }
					 for ( var i in json.results ) {
					 	rowdata = json.results[i];
					 	rcnt = parseInt(json.start) + parseInt(i);
					 	content = rowdata.content.replaceAll(' id=\"', ' id=\"row'+rcnt+'_');
					 	docid = rowdata.cid.replace('.xml', '').replace('xmlfiles/', '');
					 	doctit = 'tree';
					 	row =  '<tr id=\"row'+rcnt+'\"><td style=\"padding-right: 10px;\"><a target=details cid=\"'+docid+'\" onmouseover=\"showdocinfo(this)\" href=\"index.php?action=$treex&cid=' + rowdata.cid + '&sid=' + rowdata.sentid + '\">' + doctit + '</a></td><td>' + content + '</td></tr>';
						document.getElementById('resulttable').innerHTML += row;
						for ( var j in rowdata.toks ) { 
							tokid = rowdata.toks[j];
							rtid = 'row'+rcnt+'_'+tokid;
							// tok = document.getElementById(rtid);
							highlight(rtid);
						};
					 };
					 formify();
					 setForm('$showform');
				  } else  if (this.readyState == 4 ) {
					 document.getElementById('navdiv').innerHTML = '<b>An error has occcurred while loading the results</b>';				  	
				  };
				};
				requrl = 'index.php?action=apiquery';
				var postdata = 'type='+frontview+'&qid='+qid+'&perpage='+perpage+'&start='+start+'&query='+encodeURIComponent(rawq);
				for ( var fld in document.getElementById('qf').elements ) {
					if ( fld.substr(0,5) == 'atts[' ) {		
						val =  document.getElementById('qf')[fld].value;
 						if ( val != '' ) { postdata = postdata + '&' + fld + '=' + val; };
					};
				};
				xhttp.open('POST', requrl, true);
				xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
				xhttp.send(postdata);				
			};
									
			var tabcnt = 0;
			var frombt = {};
			var donode = {};


	$funclist	
												
			switchqv('$frontview');
		</script>
		"; 

	$maintext .= "<div id='helptext'>".getlangfile("btqltext2")."</div>";		

	$maintext .= "<hr><p><a href='index.php?action=querymng&type=btql'>stored queries</a> <span id='butlist'></span> $morebuts";

?>