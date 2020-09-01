<?php

	# Name-oriented document view and name index
	# Maarten Janssen, 2020

	if ( !$_GET['cid'] ) $_GET['cid']  = $_GET['id'];
	$nertitle = $settings['xmlfile']['ner']['title'] or $nertitle = "Named Entities";
	$neritemname = $settings['xmlfile']['ner']['item'] or $neritemname = "entity";

	$nerlist = $settings['xmlfile']['ner']['tags'] 
		or 
		$nerlist = array(
			"placename" => array ( "display" => "Place Name", "cqp" => "place", "elm" => "placeName", "nerid" => "ref" ), 
			"persname" => array ( "display" => "Person Name", "cqp" => "person", "elm" => "persName", "nerid" => "ref" ), 
			"name" => array ( "display" => "Name", "cqp" => "name", "elm" => "name", "nerid" => "ref" ),
			"term" => array ( "display" => "Term", "cqp" => "term", "elm" => "term", "nerid" => "ref" ),
			);
	$nerjson = array2json($nerlist);

	$nerfile = $settings['xmlfile']['ner']['nerfile'] or $nerfile = "ner.xml";
	if ( strpos($nerfile, "/") == false ) $nerfile = "Resources/$nerfile";
	if ( file_exists($nerfile) ) $nerxml = simplexml_load_file($nerfile); 

	if ( $_GET['cid'] && $act == "list" ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%$nertitle}</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();		

		$maintext .= "<table id='nertable'>";
		foreach ( $nerlist as $key => $val ) {
			$nodelist = $xml->xpath("//text//{$val['elm']}");
			unset($idnames);
			if ( $nodelist ) {
				$maintext .= "<tr><td colspan=2 style='padding-top: 10px; padding-bottom: 10px; '><b style='font-size: larger;'>{$val['display']}</b></tr>";
			
				foreach ( $nodelist as $node ) {
					$nerid = $node[$val['nerid']];
					$name = $node->asXML() or $name = $nerid;
					$name = preg_replace("/<[^>]+>/", "", $name);
					$idnames[$nerid.""][$name.""]++;
					$idcnt[$nerid.""]++;
				};
			};	
			foreach ( $idnames as $nerid => $val ) {
				$name = join("<br/>", array_keys($val));
				if ( substr($nerid, 0, 4) == "http") $idtxt = "<a href='$nerid'>$nerid</a>";
				else if ( substr($nerid, 0, 1) == "#" ) {
					$idxp = "//*[@id=\"".substr($ref,1)."\" or @xml:id=\"".substr($nerid,1)."\"]";
					$idnode = $xml->xpath($idxp);
					$idtxt = ""; $sep = "";
					if ( !$idnode ) $idtxt = ""; else 
					foreach ( $idnode[0]->xpath(".//link") as $linknode ) {
						$idname = $linknode['type'] or $idname = $linknode['target'];
						$idtxt = $sep."<a href='{$linknode['target']}'>$idname</a>"; 
						$sep = "<br/>";
					};
				} else $idtxt = "<i style='opacity: 0.5'>$nerid</i>";
				$cidr = ""; if ( substr($nerid,0,1) == "#" ) $cidr = "&cid=".$ttxml->fileid;
				if ( $trc == "odd" ) $trc = "even"; else $trc = "odd";
				$maintext .= "<tr key='$name' class='$trc'><td title='{%Lemma}'><a href='index.php?action=$action&type=$key&nerid=".urlencode($nerid)."$cidr'>$name</a><td>$idtxt<td style='opacity: 0.5; text-align: right; padding-left: 10px;' title='{%Occurrences}'>{$idcnt[$nerid]}";
			};
		};
		$maintext .= "</table>
				<hr> <a href='index.php?action=$action&cid={$ttxml->fileid}'>{%back}</a>";

	} else if ( $act == "snippet" ) {
	
		$nerid = preg_replace("/.*#/", "", $_GET['nerid']);
		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
			if ( $nernode ) {
				$snippettxt = "<table>";
				$tmp = $nernode->getName();
				foreach ( $nerlist as $key => $val ) {
					$valelm = $val['indexelm'] or $valelm = $val['elm'];
					$tmp = $nernode->xpath(".//$valelm");
					if ( $tmp ) {
						$name = current($tmp)."";
						$type = $val['display'];
						last;
					};
				};
				if ( $type) $snippettxt .= "<tr><th>{%$type}:<td style='font-weight: bold;'>$name</th></tr>";
				$snippetelm = $settings['xmlfile']['ner']['snippet'] or $snippetelm = "label";
				$snippetxml = current($nernode->xpath(".//$snippetelm"));
				if ( $snippetxml ) $snippettxt .= "<tr><td colspan=2>".$snippetxml->asXML()."</td></tr>";
				$snippettxt .= "</table>";
			};
		};
	
		if ( $snippettxt && $snippettxt != "<table></table>" ) print i18n($snippettxt);
		else if ( $_GET['debug'] ) print "No info: $nerid ".$nernode->asXML();
		else header("HTTP/1.0 500 Internal Server Error");
		exit;

	} else if ( $_GET['cid'] && !$_GET['nerid'] ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%Named Entity View}</h2><h1>".$ttxml->title()."</h1>";
		$maintext .= $ttxml->tableheader();

		$maintext .= "<div id=mtxt>".$ttxml->asXML()."</div>";

		$maintext .= "<hr>".$ttxml->viewswitch();
		$maintext .= " &bull; <a href='index.php?action=$action&act=list&cid=$ttxml->fileid'>{%List names}</a>";

		$maintext .= "
			<style>
				#mtxt tok:hover { text-shadow: none;}
			</style>
			<script language=Javascript>
			var nerlist = $nerjson;
			var hlid = '{$_GET['hlid']}';
			var mtxt = document.getElementById('mtxt');
			
			var tokinfo = document.getElementById('tokinfo');
			if ( !tokinfo ) {
				var tokinfo = document.createElement(\"div\"); 
				tokinfo.setAttribute('id', 'tokinfo');
				document.body.appendChild(tokinfo);
			};
			
			var nercolor;
			for ( var i=0; i<Object.keys(nerlist).length; i++) {
				var tmp = Object.keys(nerlist)[i];
				var tagelm = nerlist[tmp]['elm'];
				if ( !tagelm ) { tagelm = tmp; };
				var its = mtxt.getElementsByTagName(tagelm);
				nercolor = nerlist[tmp]['color']; if ( !nercolor ) { nercolor = 'green'; };
				console.log('color: ' + nercolor);
				for ( var a = 0; a<its.length; a++ ) {
					var it = its[a];	
					it.style.color = nercolor;
					// it.style['font-weight'] = 'bold';
					it.onclick = function(event) {
						doclick(this);
					};
					it.onmouseover = function(event) {
						showinfo(this);
					};
					it.onmouseout = function(event) {
						hideinfo(this);
					};
					if ( it.getAttribute(nerlist[tmp]['nerid']) == hlid ) { 
						it.style['backgroundColor'] = '#ffffbb'; 
						it.scrollIntoView(true); // TODO: this should depend on jmp
					}
				};
			};
			
			function doclick(elm) {
				var ttype = elm.nodeName.toLowerCase();
				var neratt = nerlist[ttype]['nerid'];
				var trgt = elm.getAttribute(neratt);
				window.open('index.php?action=$action&nerid='+encodeURIComponent(trgt)+'&type='+ttype, '_self');
			};

		function hideinfo(showelement) {
			if ( document.getElementById('tokinfo') ) {
				document.getElementById('tokinfo').style.display = 'none';
			};
			if ( typeof(hlbar) != \"undefined\" && typeof(facsdiv) != \"undefined\" ) {
				hlbar.style.display = 'none';
				var tmp = facsdiv.getElementsByClassName('hlbar'+hln);
			};
		};

	
		function showinfo(showelement) {
			if ( !tokinfo ) { return -1; };
			var nertype = nerlist[showelement.nodeName.toLowerCase()];

			nername = showelement.nodeName;
			if ( nertype ) nername =  nertype['display'];
			infoHTML = '<table><tr><th>' + nername + '</th><td><b><i>'+ showelement.innerHTML +'</i></b></td></tr>';
			
			var idfld = 'corresp';
		    if ( nertype ) idfld =  nertype['nerid'];
		    var nerid = showelement.getAttribute(idfld)
			if ( nerid ) {
				// start Ajax to replace info by full data
				  var xhttp = new XMLHttpRequest();
				  xhttp.onreadystatechange = function() {
					if (this.readyState == 4 && this.status == 200) {
					 tokinfo.innerHTML = this.responseText;
					}
				  };
				  xhttp.open('GET', 'index.php?action=$action&act=snippet&nerid='+encodeURIComponent(nerid), true);
				  xhttp.send();
			};
			
			tokinfo.style.display = 'block';
			var foffset = offset(showelement);
			if ( typeof(poselm) == \"object\" ) {
				var foffset = offset(poselm);
			};
			tokinfo.style.left = Math.min ( foffset.left, window.innerWidth - tokinfo.offsetWidth + window.pageXOffset ) + 'px'; 
			tokinfo.style.top = ( foffset.top + showelement.offsetHeight + 4 ) + 'px';

			infoHTML += '</table>';

			tokinfo.innerHTML = infoHTML;

		};

	function offset(elem) {
		if(!elem) elem = this;

		var x = elem.offsetLeft;
		var y = elem.offsetTop;

		if ( typeof(x) == \"undefined\" ) {

			bbr = elem.getBoundingClientRect();
			x = bbr.left + window.pageXOffset;
			y = bbr.top + window.pageYOffset;

		} else {

			while (elem = elem.offsetParent) {
				x += elem.offsetLeft;
				y += elem.offsetTop;
			}
		
		};
		
		return { left: x, top: y };
	};  
		</script>";
		
	} else if ( $_GET['nerid'] ) {
	
		$nerid = preg_replace("/.*#/", "", $_GET['nerid']);
		$type = strtolower($_GET['type']);
		if ( $nerxml ) {
			$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
			if ( $nernode ) {
				$nameelm = $nerlist[$type]['elm'] or $nameelm = "name";
				$name = current($nernode->xpath(".//$nameelm"))."";
			};
		};
		if ( !$name && $_GET['name'] ) $name = "<i>".$_GET['name']."</i>";
		if ( !$name ) $name = $nerid;
	
		$maintext .= "<h2>{%$nertitle}</h2><h1>$name</h1>
		<p>Type of $neritemname: <b>{$nerlist[$type]['display']}</b>";
	
		if ( $nernode ) {
			$descflds = array ("note", "desc", "head", "label");
			# $maintext .= "<div>".htmlentitieS($nernode->asXML())."</div>";
			$maintext .= "<table>";
			foreach ( $nernode->children() as $childnode ) {
				$nodename = $childnode->getName();
				if ( in_array( $nodename, $descflds ) ) {
					$maintext .= "<tr><td colspan=2>".$childnode->asXML();
				} else if ( trim($childnode) != "" && $nodename != $nameelm ) {
					$maintext .= "<tr><th>{%$nodename}<td>$childnode";
				};
			};
			$maintext .= "</table>";
			$links = $nernode->xpath(".//linkGrp/link");
			if ( $links ) {
				$maintext .= "<h2>{%External links}</h2><table>";
				foreach ( $links as $key => $val ) {
					if ( substr($val['target'],0,4) == "http" ) $maintext .= "<tr><th>{%{$val['type']}}<td><a href='{$val['target']}' target=info>{%{$val['target']}}</a>";
				};
				$maintext .= "</table>";
			};
		} else if ( substr($nerid,0,4) == "http") {
			$maintext .= "<p>Reference: <a href='{$_GET['nerid']}'><b>{$nerid}</b></a></p>";
		} else if ( substr($nerid, 0, 1) == "#" ) {
			if ( $_GET['cid']) {
				require("$ttroot/common/Sources/ttxml.php");
				$ttxml = new TTXML();
				$fileid = $ttxml->fileid;
				$xmlid = $ttxml->xmlid;
				$xml = $ttxml->xml;

				$idxp = "//*[@id=\"".substr($nerid,1)."\" or @xml:id=\"".substr($nerid,1)."\"]";
				$idnode = $xml->xpath($idxp);
				$idtxt = ""; $sep = "";
				if ( $idnode ) $maintext .= $idnode[0]->asXML();
			};
		};
	
		# Lookup all occurrences
		$maintext .= "<h2 style='margin-top: 20px;'>{%Occurrences}</h2>";
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");
		
		$nodetype = $nerlist[$type]['elm'];
		$nodeatt = $nerlist[$type]['cqp'];


		$cql = "Matches = <$nodeatt> []+ </$nodeatt> :: match.{$nodeatt}_nerid=\"{$_GET['nerid']}\"";
		$cqp->exec($cql); 
		$results = $cqp->exec("tabulate Matches match, matchend, match text_id, match id");

		$xidxcmd = findapp("tt-cwb-xidx");

		$csize = $settings['xmlfile']['ner']['context'] or $csize = 0;
		if ( $csize) {
			$expand = "--context=$csize";			
		};

		$neridtxt = str_replace("/", "\/", preg_quote($nerid));
			
		$maintext .= "<div id=mtxt><table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $leftpos, $rightpos, $fileid, $tokid ) = explode("\t", $resline);
			if ( !$fileid ) continue;
			$cmd = "$xidxcmd --filename='$fileid' --cqp='$cqpfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);
			
			if ( $csize ) {
				$resxml = preg_replace("/ ({$nerlist[$type]['nerid']}=\"([^\"]*#)?$neridtxt\")/", " \1 hl=\"1\"", $resxml);
				# Replace block-type elements by vertical bars
				$resxml = preg_replace ( "/(<\/?(p|seg|u|l)>\s*|<(p|seg|u|l|lg|div) [^>]*>\s*)+/", " <span style='color: #aaaaaa' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(doc)>\s*|<(doc) [^>]*>\s*)+/", " <span style='color: #995555; font-weight: bold;' title='<\\2>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<(lb|br)[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<sb[^>]*\/>\s*)+/", " <span style='color: #aaffaa' title='<p>'>|</span> ", $resxml); # non-standard section break
				$resxml = preg_replace ( "/(<pb[^>]*\/>\s*)+/", " <span style='color: #ffaaaa' title='<p>'>|</span> ", $resxml);
				$resxml = preg_replace ( "/(<\/?(table|cell|row)(?=[ >])[^>]*>\s*)+/", " ", $resxml);
			};
			$context = preg_replace("/.*\/(.*?)\.xml/", "\\1", $fileid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&jmp=$tokid&hlid=".urlencode($_GET['nerid'])."'>$context</a><td>$resxml";
		};
		$maintext .= "</table></div>";

	} else if ( $_GET['type'] ) {

		$type = strtolower($_GET['type']);
		# List of types of NER we have
		$nername = $nerlist[$type]['display'];
		$neratt = $nerlist[$type]['cqp'];
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%$nername}</h1>";

		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cql = "Matches = <$neratt> []"; 
		$cqp->exec($cql); 
		
		$cql = "group Matches  match {$neratt}_form by match {$neratt}_nerid";
		$results = $cqp->exec($cql); 
		
		foreach ( explode("\n", $results) as $resline ) {
			list ( $nerid, $form, $cnt ) = explode("\t", $resline);
			if ( $form == '' || $form == '_') $form = $nerid;
			$rowhash[$form] = "<tr key='$name'><td><a href='index.php?action=$action&nerid=".urlencode($nerid)."&type=$type&name=$form'>$form</a></tr>";
		};
		ksort($rowhash);
		$maintext .= "<table>".join("\n", array_values($rowhash))."</table>";

	} else {
	
		# List of types of NER we have
		$maintext .= "<h2>{%$nertitle}</h2><h1>{%Select}</h1>";
		
		foreach ( $nerlist as $key => $val ) {
			$maintext .= "<p><a href='index.php?action=$action&type=$key'>{$val['display']}</a>";
		};

	};
	

?>