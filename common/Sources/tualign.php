<?php

	# Check we have a PSQL DB
	$cqpcorpus = $settings['cqp']['corpus'] or $cqpcorpus = "tt-".$foldername;
	$db = strtolower(str_replace("-", "_", $cqpcorpus)); # Manatee corpus name
	$dbconn = pg_connect("host=localhost dbname=$db user=www password=localpwd");

	$tuidatt = $_GET['tuidatt'] or $tuidatt = $settings['defaults']['align']['tuidatt'] or $tuidatt = "tuid";
	$tuidtit = $_GET['tuidtit'] or $tuidtit = $settings['defaults']['align']['display']; 
	if ( !$tuidtit ) if ( $tuidatt == "tuid" ) $tuidtit = "Translation unit"; else if ( $tuidatt = "appid" ) $tuidtit = "Apparatus unit"; else $tuidtit = "Alignment unit";

	$maintext .= "<h1>{%{$tuidtit}s}</h1>";


	if ( $act == "files" ) {
	
		# Make an alignment across selected files
		# The first one defines the translation units
		# Works on a single level - default is <p>

		
		$lvl = $_GET['lvl'] or $lvl = $settings['defaults']['align']['level'] or $lvl = "p";

		$maintext .= "<h2>Selected Files</h2>
			<p>Alignment level: $lvl</p>";
		
		$ids = array_keys($_POST['ids']) or $ids = explode(",", $_GET['ids']);
		
		require("$ttroot/common/Sources/ttxml.php");
		$cids = array();
		foreach ( $ids as $cid ) {
			$tmp = new TTXML($cid, false);
			if ( $tmp->xml ) {
				$files[$cid] = $tmp;
				array_push($cids, $cid);
			} else if ( $username ) { $maintext .= "<p class=wrong>Unable to open: $cid"; }
		}; 
		if ( !in_array($mid, $cids) ) $mid = $cids[0];
		
		$maintext .= "<table id=rollovertable><tr><td>";
		foreach ( $files as $cid => $ttxml ) {
			$filetit = $ttxml->title("short") or $filetit = $ttxml->fileid;
			$maintext .= "<th id=\"tr-$cid\"><a href='index.php?action=file&cid=$ttxml->fileid'>$filetit</a></th>";
			foreach ( $ttxml->xml->xpath("//".$lvl."[@$tuidatt]") as $tu ) {
				$tuid = $tu[$tuidatt]."";
				$tus[$cid][$tuid] = $tu;
			}; 
		};
		
		foreach ( $tus[$mid] as $tuid => $tu ) {
			$tutxt = str_replace(",", "<br/>", $tuid);
			$maintext .= "<tr id=\"tr-$tuid\"><td><a href='index.php?action=$action&tuid=$tuid'>$tutxt</a></td>";
			foreach ( $files as $cid => $ttxml ) {
				if (  $tus[$cid][$tuid] ) $tutxt = $tus[$cid][$tuid]->asXML();
				else $tutxt = "<i>--</i>";
				$maintext .= "<td id=\"td-$cid-$tuid\">$tutxt</td>";
			};
		};
		$maintext .= "</table>";
		
	} else if ( $act == "columns" && ( $_GET['id'] || $_GET['cid'] ) ) { 
	
			$ids = $_GET['id'] or $ids = $_GET['cid'];
			$idlist = explode(",", $ids); 
	
			$tuid = $_GET['appid'] or $tuid = $_GET['tuid'];
			$tuidatt = $_GET['tuidatt'] or $tuidatt = $settings['defaults']['align']['tuidatt'] or $tuidatt = "tuid";
		
			require_once("$ttroot/common/Sources/ttxml.php");

			foreach ( $idlist as $cid ) {
				$tmp = new TTXML($cid, false);
				if ( $tmp->xml && $tmp->xml && $tmp->fileid ) $versions[$cid] = $tmp; 
				else $maintext .= "<p>Not found: $cid";
			};

			$verlist = array_keys($versions);
			$verj = array2json($verlist);

			$maintext .= "<h1>Aligned Texts</h1>";
			if ( $tuid ) {
				$vxml = $versions[$verlist[0]];
				$tmp = current($vxml->xml->xpath("//text//*[@$tuidatt=\"$tuid\"]"));
				if ( $tmp ) {
					$prev = current($tmp->xpath("preceding-sibling::*[@$tuidatt][1]"));
					if ( $prev ) {
						$prevb = "<a href='index.php?action=$action&act=$act&cid=$ids&tuid={$prev[$tuidatt]}'>{$prev[$tuidatt]} &lt;</a>";;
					};
					$next = current($tmp->xpath("following-sibling::*[@$tuidatt]"));
					if ( $next ) {
						$nextb = "<a href='index.php?action=$action&act=$act&cid=$ids&tuid=$next[$tuidatt]'>&gt; {$next[$tuidatt]}</a>";;
					};
				};
				$maintext .= "<table style='width:100%'><tr><td>$prevb<td style='text-align: center;'><h3>Selection: $tuid</h3><td style='text-align: right'>$nextb</tr></table><hr>";
			};
			$maintext .= "<div id='appidshow' style='float: right'></div>";

			$w = 95/(count($versions));


			foreach ( $versions as $key => $vxml ) {
				if ( $tuid ) {
					$tmp = current($vxml->xml->xpath("//text//*[@$tuidatt=\"$tuid\"]"));
					if ( !$tmp ) continue;
					$editxml = $tmp->asXML();
				} else $editxml = $vxml->asXML();
				$maintext .= "<div style='float: left; width: {$w}%; padding: 5px;' class='parbox' id='parb-$key'>";
				$title = $vxml->title();
				$maintext .= "<p><a href='index.php?action=file&cid=$key'>$key</a></p>";
				$maintext .= "<div id='mtxt-$key' style=' overflow-y: scroll;' class='mtxt'>$editxml</div>";
				$maintext .= "</div>";
			};
	
			$maintext .= "<script language=Javascript>
				// document.onclick = clickEvent; 
				document.onmouseover = mouseEvent; 
				document.onmouseout = mouseOut; 
				var hls = []; var appidshow = document.getElementById('appidshow');
				var versions = $verj;
				maxheight = window.innerHeight;
				v1 = document.getElementById('mtxt-'+versions['0']);
				if ( v1 ) {
					bb = v1.getBoundingClientRect();
					console.log(bb);
					maxheight = maxheight - bb['y'];
				};
				console.log(maxheight);
				for ( i in versions ) {
					vx = document.getElementById('mtxt-'+versions[i]);
					if ( vx ) {
						vx.height = maxheight;
						vx.style.height = maxheight+'px';
					};
				};
				function mouseEvent(evt) { 
					element = evt.toElement; 
					while ( element.parentNode && !element.getAttribute('$tuidatt') ) element = element.parentNode;
					if ( typeof(element.getAttribute) != 'function' ) return -1;
					vo = element;
					vob = vo.getBoundingClientRect();
					while ( vo.parentNode && vo.getAttribute('class') != 'mtxt' ) vo = vo.parentNode;
					alid = element.getAttribute('$tuidatt');
					if ( !alid ) return -1;
					appidshow.innerHTML = alid;
					// find element in all aligned versions
					orgScroll = element.offsetTop - vo.scrollTop; // element.offsetTop , vob['y'] , vob['height'] , vo.scrollTop
					xpath = './/*[@$tuidatt=\"'+alid+'\"]';
					for ( i in versions ) {
						vx = document.getElementById('mtxt-'+versions[i]);
						vxb = vx.getBoundingClientRect();
						van = document.evaluate(xpath, vx, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
						if ( van ) {
							highlight(van);
						};
						if ( van && van != element ) {
							topPos = van.offsetTop;
							// console.log(orgScroll);
							vx.scrollTop = topPos - orgScroll;
						};
					};
				};
				function highlight(element) { 
					hls.push(element);
					element.style.backgroundColor = '#ffff66';
				};
				function unhighlight(element) { 
					element.style.backgroundColor = null;
				};
				function mouseOut(evt) { 
					for ( i in hls ) {
						unhighlight(hls[i]);
					};
					hls = [];
					appidshow.innerHTML = '';
				};
				</script>";
		
	} else if ( $_GET['tuid'] && $dbconn && $_GET['type'] != "xml" ) {
		
		# Align a single TU across all files
		$lvl = $_GET['lvl'] or $lvl = $settings['defaults']['align']['level'] or $lvl = "p";
		$base = $settings['defaults']['align']['cqp'] or $base = "lang";
		$basetxt = $settings['cqp']['sattributes']['text'][$base]['display'] or $basetxt = "<i>".ucfirst($base)."</i>";
		$seg = $lvl;
		$tuid = $_GET['tuid'];
		$query = "SELECT * FROM $seg 
			join text on {$seg}2text=text_seq
			WHERE {$seg}_{$tuidatt} = '$tuid' 
			;";
		if ( $debug ) $maintext .= "<p>$query";
		$result = pg_query($query);
	
		if ( pg_num_rows($result) ) {
			$maintext .= "<p>
				<style>.highlight { background-color: #ffeeaa; }</style>
						<h2>Results</h2>

				<table id=rollovertable data-sortable>
				<tr><th id='filecol' title='File' data-sortable-type='alpha'>$basetxt<th colspan=2>Text";
			foreach ( $_POST['target'] as $i => $tqp ) {
				if ( $tqp == "" ) continue;
				$maintext .= "<th colspan=2>$tqp";
			};
			while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
				$tit = $line['text_'.$base];	
				$cid = $line['text_id'];
				$sid = $line[$seg.'_id'];
				$maintext .= "<tr base='$tit'><td><a href='index.php?action=file&cid=$cid&jmp=$sid'>$tit</a><td>".$line[$seg.'_rawtext']; 
				$tot++;
			};
			$maintext .= "</table>	
		<script language=Javascript src=\"https://cdnjs.cloudflare.com/ajax/libs/sortable/0.8.0/js/sortable.min.js\"></script>
		<script language=Javascript>Sortable.init(); document.getElementById('filecol').click();</script>
		<link rel=\"stylesheet\" href=\"https://github.hubspot.com/sortable/css/sortable-theme-bootstrap.css\">
		<hr><p>$tot results";
		};
	
	
	} else if ( $_GET['tuid'] ) {
		
		# Align a single TU across all files
	
		$tuid = $_GET['tuid'];
		$maintext .= "<h2>$tuidtit: $tuid</h2>";
	
		$cmd = "/usr/local/bin/tt-xpath --max=1000 --folder=xmlfiles '//*[@$tuidatt=\"$tuid\"]'";
		$res = shell_exec($cmd);

		$resxml = simplexml_load_string($res);
		
		if ( $_GET['debug'] && $username ) {
			$maintext .= "<code>$cmd</cmd><hr>".showxml($resxml);
		};
		
		$totres = count($resxml->xpath("/results/*"));
		
		$maintext .= "<table id=rollovertable data-sortable>
			 <thead><tr><th id='filecol'  data-sortable-type='alpha'>File</th><th>Text</th></tr> </thead><tbody>";
		foreach ( $resxml->xpath("/results/*") as $resline ) {
			$langid = str_replacE(".xml", "", $resline['fileid']);
			$maintext .= "<tr lnk='$langid'><td><a href='index.php?action=file&cid={$resline['fileid']}&jmp={$resline['id']}'>$langid</a><td>".html_entity_decode($resline->asXML());
		};
		$maintext .= "</tbody></table>
		<script language=Javascript src=\"https://cdnjs.cloudflare.com/ajax/libs/sortable/0.8.0/js/sortable.min.js\"></script>
		<script language=Javascript>Sortable.init(); document.getElementById('filecol').click();</script>
		<link rel=\"stylesheet\" href=\"https://github.hubspot.com/sortable/css/sortable-theme-bootstrap.css\">";
		$maintext .= "<hr>$totres results (XML)";
		
	} else if ( $_GET['id'] || $_GET['cid'] ) {
	
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML($cid, true);
		if ( !$ttxml ) {
			fatal("Failed to open {$_GET['cid']}");
		};

		$maintext .= "<h2>".$ttxml->title()."</h2>";
		$maintext .= $ttxml->tableheader();

		$editxml = "<div id=mtxt>".$ttxml->asXML()."</div>";

		$maintext .= $ttxml->pagenav;
		$maintext .= $editxml;
	
		$maintext .= "<hr>".$ttxml->viewswitch();

		$highlights = $_GET['tid'] or $highlights = $_GET['jmp'] or $highlights = $_POST['jmp'];	

		$settingsdefs .= "\n\t\tvar formdef = ".array2json($settings['xmlfile']['pattributes']['forms']).";";
			
		$maintext .= "
			<style>.tu { color: blue; font-size: x-small; };</style>
			<script language=Javascript src='$jsurl/tokedit.js'></script>
			<script language=Javascript src='$jsurl/tokview.js'></script>
			<script langauge=Javascript>
			var lasthl;
			function gotuid (elm) { 
				tuid = elm.parentNode.getAttribute('$tuidatt');
				window.open('index.php?action=$action&tuid='+tuid, '_self');
			};
			function hl (elm) { 
				tu = elm.parentNode;
				if ( lasthl && lasthl != tu ) {
					lasthl.style['background-color'] = '';
					lasthl.style.backgroundColor = '';
				};
				tu.style['background-color'] = '#fff3cc';
				tu.style.backgroundColor = '#fff3cc';
				lasthl = tu;
				tokinfo.style.display = 'block';
				tokinfo.innerHTML = '<p style=\"height: 23px; margin-bottom: 0px; background-color: #eee0bb;\">$tuidtit: ' + tu.getAttribute('$tuidatt') + '</p>';
				var foffset = offset(elm);
				tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
				tokinfo.style.top = ( foffset.top - 35 ) + 'px';
			};
			function unhl (elm) { 
				tu = elm.parentNode;
				tu.style['background-color'] = '';
				tu.style.backgroundColor = '';
				tokinfo.style.display = 'none';
			};
			var mtch = document.evaluate(\"//*[@$tuidatt]\", document, null, XPathResult.ANY_TYPE, null); 
			var mitm = mtch.iterateNext();
			var tus = [];
			while ( mitm ) {
			  if ( typeof(mitm) != 'object' ) { continue; };
			  tus.push(mitm);
			  mitm = mtch.iterateNext();
			};
			tus.forEach( mitm => {
			  const tu = document.createElement('span');
			  tu.innerHTML = '[u] ';
			  // tu.setAttribute('title', mitm.getAttribute('$tuidatt'));
			  tu.setAttribute('class', 'tu');
			  tu.onclick = function() { gotuid(this); };
			  tu.onmouseover = function() { hl(this); };
			  tu.onmouseout = function() { unhl(this); };
			  mitm.insertBefore(tu, mitm.firstChild);
			});
			var jmps = '$highlights'; var jmpid;
			if ( jmps ) { 
				var jmpar = jmps.split(' ');
				for (var i = 0; i < jmpar.length; i++) {
					var jmpid = jmpar[i];
					highlight(jmpid, '$hlcol');
				};
				element = document.getElementById(jmpar[0]);
				alignWithTop = true;
				if ( element != null && typeof(element) != null ) { 
					element.scrollIntoView(alignWithTop); 
				};
			};
			$settingsdefs;
			</script>";
	
	} else if ( $act == "list" ) {

		# List all TU in a single file 
		# TODO: should do hierarchy

		$maintext .= "
			<p>Select a ".lc($tuidtit).":</p>
				<ul>";
				
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML($cid, false);
		if ( !$ttxml ) {
			fatal("Failed to open {$_GET['cid']}");
		};

		foreach ( $ttxml->xml->xpath("//*[@$tuidatt]") as $tu ) {
			$tuid = $tu['tuid'];
			$maintext .= "<li><a href='index.php?action=$action&tuid=$tuid'>$tuid</a>";
		};

	} else if ( $act == "align" ) {

		$maintext .= "<p>Select one or more files to align:</p>
				
				<form action=\"index.php?action=$action&act=files\" method=post>
				<input type=hidden name=action value='$action'>
				<input type=hidden name=act value='files'>
				<p>";
		
		if ( file_exists("cqp/text_id.avs") ) {
			$fns = explode("\0", file_get_contents("cqp/text_id.avs"));
		} else if ( $username ) {
			$cmd = "find xmlfiles -name '*.xml' -print";
			$fns = explode("\n", shell_exec($cmd));
		} else {
			fatal("Parallel alignment not currently supported for this corpus");
		};
		
		sort($fns);
		foreach ( $fns as $fn ) {
			$fn = str_replacE("xmlfiles/", "", $fn);
			$filerest = str_replace("/", "\\/", $settings['defaults']['align']['filerest']);
			if ( $filerest && !preg_match("/$filerest/", $fn) ) continue;
			if ( $fn ) $maintext .= "<p><input type=checkbox name=ids[$fn] value='1'> <a class=black href='index.php?action=$action&cid=$fn'>$fn</a>";
		};

				
		$maintext .= "</p>
			<p><input type=submit value=Align>
			</form>";

	} else if ( $dbconn ) {
	
		$maintext .= "<h2>Parallel Search</h2>";
		
		$maintext .= "<p>Select the source and the target for the alignment, and provide a search query for the source, and optionally for the target as well.
			
			<p>You can also start by selecting <a href='index.php?action=$action&act=align'>align</a> on one or more texts.";
		
		$base = $settings['defaults']['align']['cqp'] or $base = "lang";
		$basetxt = $settings['cqp']['sattributes']['text'][$base]['display'];
		if ( !file_exists("cqp/text_$base.avs") ) {
			if ( $username ) fatal("Base does not exist in CQP corpus: text_$base");
			else fatal("Parallel search is currently not available for this corpus");
		};
		$versions = explode("\0", file_get_contents("cqp/text_$base.avs"));
		$tmp = array();
		foreach ($versions as $version) {
			if ( $version && $version != "_" ) {
				$version = str_replace("'", "&quot;", $version);
				array_push($tmp, "<option value=\"$version\">$version</option>");
			};
		};
		sort($tmp);
		$veropt = join("", $tmp);

		$n = max(1, count($_POST['target']));
		for ( $i=1; $i<= $n; $i++ ) {
			$tq = $_POST['tquery'][$i];
			$tg = $_POST['target'][$i];
			$defrows .= "		<tr id=\"row$i\"><th>Target $i<td><select name='target[$i]' id='tsel$i' value='$tg'><option value=''>[select]</option>$veropt</select><td><input name=tquery[$i] size=50 value='$tq'>\n";
			$defset .= "setOption(document.getElementById('tsel$i'), '$tg'); ";
		};

		$lvl = $_GET['lvl'] or $lvl = $settings['defaults']['align']['level'] or $lvl = "p";

		if ( !$_POST ) $_POST = $_GET;

			$seg = $_POST['align'] or $seg = $lvl;
			$source = $_POST['source'];
			$target = $_POST['target'];
			$squery = $_POST['squery'];
			$tquery = $_POST['tquery'];
		
		$maintext .= "
		<form action='index.php?action=$action&act=search' method=post>
		<input name=align value=\"$lvl\" type=hidden>
		<table>
		<tbody id='ptab'>
		<tr><td><th>$basetxt<th>Query
		<tr><th>Source<td><select name='source' id='ssel' value='$source'><option value=''>[select]</option>$veropt</select><td><input name=squery size=50 value='$squery'>
		$defrows
		<tr id=\"radd\"><td colspan=5><span onClick=\"addrow();\">+</span>		
		</tbody>
		</table>
		<script>
			function setOption(selectElement, value) {
				var options = selectElement.options;
				for (var i = 0, optionsLength = options.length; i < optionsLength; i++) {
					if (options[i].value == value) {
						selectElement.selectedIndex = i;
						return true;
					}
				}
				return false;
			}
			n=$n;
			var ptab = document.getElementById('ptab'); var plst = document.getElementById('radd'); var ssel = document.getElementById('ssel');
			var norow = '<th>Target 1</th><td><select name=\"target[1]\" id=\"tsel1\" value=\"\"><option value=\"\">[select]</option></select></td><td><input name=\"tquery[1]\" size=50 value=\"\"></td>';
			var nosel = '<option value=\"\">[select]</option>$veropt';
			function addrow() {
				n++;
				var newrow = document.createElement('tr');
				newrow.innerHTML = norow.replaceAll('1', n);
				newrow.setAttribute('id', 'row'+n);
				newrow.children[0].innerHTML = 'Target '+n;
				tds = newrow.getElementsByTagName('td')[1];
				ptab.insertBefore(newrow, plst);
				newsel = document.getElementById('tsel'+n);
				console.log(newsel);
				newsel.innerHTML = nosel;
			};

			setOption(document.getElementById('ssel'), '$source');
			$defset
		</script>
		<p><input type=submit value=Search>
		</form>
		";
		$base = $settings['defaults']['align']['cqp'] or $base = "lang";
	
		if ( $_POST['source'] && $_POST['squery'] ) {		
		
			$query = "SELECT current_database();";
			$result = pg_query($query) or die('Query failed: ' . pg_last_error($dbconn));
			$line = pg_fetch_array($result, null, PGSQL_ASSOC);
			$dbname = $line['current_database'];

			$q1 = cqlparse($_POST['squery']);
				
			foreach ( $_POST['target'] as $i => $tqp ) {
				if ( $target[$i] == "" ) continue;
				$q2 = $ttokjoin = $ttoksel  =  $ttokmain = "" ;
				if ( $tquery[$i] ) {
					$q2 = " where ".cqlparse($tquery[$i]);
					$ttoksel = ", t1.id as tok1, t1.form as form1";
					$ttokjoin = "join tok as t1 on t1.tok2$seg = t3.{$seg}_seq";
					$ttokmain = ", target$i.tok1 as t_tok1_$i, target$i.form1 as t_form1_$i";
				};
				if ( 1==1 ) $left = "LEFT";
				$targetparts .= " $left join ( select 
					t2.text_id, t2.text_$base as base, t3.{$seg}_id as seg, t3.{$seg}_{$tuidatt} as tuid, t3.{$seg}_rawtext as rawtext $ttoksel
				  from 
					$seg as t3
					join text as t2 on t3.{$seg}2text = t2.text_seq
					$ttokjoin
					$q2
				) as target$i 
				on source.tuid = target$i.tuid";
				$targetwhere .= " and target$i.base = '$target[$i]'";
				$targetsels .= "
				  , target$i.rawtext as t_raw$i, target$i.seg as t_seg$i, target$i.text_id as t_text$i $ttokmain";
			};

			$query = "select 
				  DISTINCT ON (source.tuid) source.tuid as tuid,
				  source.tok1 as s_tok1, source.form1 as s_form1, source.rawtext as s_raw, source.seg as s_seg, source.text_id as s_text
				  $targetsels
				from
				( select 
					t1.id as tok1, t1.form as form1, t2.text_id, t2.text_$base as base, t3.{$seg}_id as seg, t3.{$seg}_{$tuidatt} as tuid, t3.{$seg}_rawtext as rawtext
				  from 
					tok as t1 
					join text as t2 on t1.tok2text = t2.text_seq
					join $seg as t3 on t1.tok2$seg = t3.{$seg}_seq
				where
					$q1
				) as source 
				$targetparts
				where source.base = '$source'
				$targetwhere 
				;
			";

			$result = pg_query($query);

			if ( count($_POST['target']) > 1 ) {
				$ctt = "c"; 
				$att = "a"; 
				$ttt = "t"; 
			} else {
				$att = "align";
				$ctt = "context";
				$ttt = "TU";
			};
			if ( pg_num_rows($result) ) {
				$maintext .= "<p>
					<style>.highlight { background-color: #ffeeaa; }</style>
							<h2>Results</h2>

					<table id=rollovertable>
					<tr><th title='$tuidtit'>$ttt<th colspan=2>$source";
				foreach ( $_POST['target'] as $i => $tqp ) {
					if ( $tqp == "" ) continue;
					$maintext .= "<th colspan=2>$tqp";
				};
				while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {	
					$sraw = $line['s_raw']; $sform =  $line['s_form1'];
					if ( $sform ) $sraw = preg_replace("/(^| )($sform)( |\$)/", "\\1<span class=highlight>\\2</span>\\3", $sraw);
					$maintext .= "<tr>
						<td style='font-size: smaller;'><a href='index.php?action=$action&tuid={$line['tuid']}' title='{$line['tuid']}'>$att</a>
						<td style='font-size: smaller;'><a href='index.php?action=file&cid={$line['s_text']}&jmp={$line['s_seg']}' title='{$line['s_text']}'>$ctt</a>
						<td>$sraw";
					foreach ( $_POST['target'] as $i => $tqp ) {
						if ( $tqp == "" ) continue;
						$traw = $line['t_raw'.$i]; $tform =  $line['t_form1_'.$i];
						if ( $tform ) $traw = preg_replace("/(^| )($tform)( |\$)/", "\\1<span class=highlight>\\2</span>\\3", $traw);
						$maintext .= "<td style='font-size: smaller;'><a href='index.php?action=file&cid={$line['t_text1']}&jmp={$line['t_seg1']}' title='{$line['t_text1']}'>$ctt</a>
							<td>$traw
							";
					};
				};
				$maintext .= "</table>";
			} else {
				$maintext .= "<i>No results</i>";
				if ( $username ) $debug = 1;
			};

			# $debug = 1;
			if ( $debug ) {
				$maintext .= "<p>Query: <pre>$query</pre>";
				$maintext .= "<p>Error: <pre>".pg_last_error($dbconn)."</pre>";
			};

		};

	};

	if ( $dbconn ) pg_close($dbconn);

	function cqlparse($cql) {
		$sql = ""; $sep = "";
		preg_match_all("/\[([^\[\]]*)\]/", $cql, $toks);
		foreach ( $toks[1] as $tok  ) {
			foreach ( explode('&', $tok ) as $tpart ) {
				if ( preg_match("/([^ ]+) *([!~=]+) *\"([^\"]*)\"/", $tpart, $m2) ) {
					$satt = $m2[1]; $sval = $m2[3]; $smtch = $m2[2];
					$sql .= " $sep t1.$satt $smtch '$sval'";
					$sep = "AND";
				};
			};
		};
		
		if ( $sql == "" ) $sql = "t1.form='$cql'";
		
		return $sql;
	};

?>