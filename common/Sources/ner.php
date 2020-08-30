<?php

	# Name-oriented document view and name index
	# Maarten Janssen, 2020

	if ( !$_GET['cid'] ) $_GET['cid']  = $_GET['id'];

	$nerlist = $settings['xmlfile']['ner']['tags'] 
		or 
		$nerlist = array(
			"placename" => array ( "display" => "Place Name", "cqp" => "place", "elm" => "placeName", "nerid" => "ref" ), 
			"persname" => array ( "display" => "Person Name", "cqp" => "person", "elm" => "persName", "nerid" => "ref" ), 
			"name" => array ( "display" => "Name", "cqp" => "name", "elm" => "name", "nerid" => "ref" ),
			"term" => array ( "display" => "Term", "cqp" => "term", "elm" => "term", "nerid" => "ref" ),
			);
	$nerjson = array2json($nerlist);

	if ( $_GET['cid'] && $act == "list" ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		$maintext .= "<h2>{%Named Entities}</h2><h1>".$ttxml->title()."</h1>";
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
				} else $idtxt = $nerid."!";
				$cidr = ""; if ( substr($nerid,0,1) == "#" ) $cidr = "&cid=".$ttxml->fileid;
				if ( $trc == "odd" ) $trc = "even"; else $trc = "odd";
				$maintext .= "<tr class='$trc'><td><a href='index.php?action=$action&type=$key&nerid=".urlencode($nerid)."$cidr'>$name</a><td>$idtxt";
			};
		};
		$maintext .= "</table>
				<hr> <a href='index.php?action=$action&cid={$ttxml->fileid}'>{%back}</a>";

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
			
			for ( var i=0; i<Object.keys(nerlist).length; i++) {
				var tmp = Object.keys(nerlist)[i];
				var tagelm = nerlist[tmp]['elm'];
				if ( !tagelm ) { tagelm = tmp; };
				var its = mtxt.getElementsByTagName(tagelm);
				for ( var a = 0; a<its.length; a++ ) {
					var it = its[a];	
					it.style.color = 'green';
					it.style['font-weight'] = 'bold';
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
			infoHTML = '<table><tr><th colspan=2>' + nername + '</th></tr>';
			
			var ref = showelement.getAttribute(\"ref\");
			if ( ref ) {
				infoHTML += '<tr><th>Reference</th><td>' + ref  + '</th></tr>';
				// start Ajax to replace info by full data
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
	
		if ( !$name ) $name = $_GET['nerid'];
	
		$type = strtolower($_GET['type']);
	
		$maintext .= "<h2>{%Named Entities}</h2><h1>$name</h1>
		<p>Type of entity: <b>{$nerlist[$type]['display']}</b>";
	
		$nerid = $_GET['nerid'];
		if ( substr($nerid,0,4) == "http") {
			$maintext .= "<p>Reference: <a href='{$_GET['nerid']}'><b>{$_GET['nerid']}</b></a></p>";
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
		$maintext .= "<h2>{%Occurrences}</h2>";
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
		
		$maintext .= "<table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $leftpos, $rightpos, $fileid, $tokid ) = explode("\t", $resline);
			$cmd = "$xidxcmd --filename='$fileid' --cqp='$cqpfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);
			$context = preg_replace("/.*\/(.*?)\.xml/", "\\1", $fileid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&jmp=$tokid&hlid=".urlencode($_GET['nerid'])."'>$context</a><td>$resxml";
		};
		$maintext .= "</table>";

	} else if ( $_GET['type'] ) {

		$type = strtolower($_GET['type']);
		# List of types of NER we have
		$nername = $nerlist[$type]['display'];
		$neratt = $nerlist[$type]['cqp'];
		$maintext .= "<h2>{%Named Entities}</h2><h1>{%$nername}</h1>";

		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = $settings['cqp']['cqpfolder'] or $cqpfolder = "cqp";

		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cql = "Matches = <$neratt> []"; 
		$cqp->exec($cql); 
		
		$cql = "group Matches match {$neratt}_nerid by match {$neratt}_form";
		$results = $cqp->exec($cql); 
		
		$maintext .= "<table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $nerid, $form, $cnt ) = explode("\t", $resline);
			if ( !$form ) $form = $nerid;
			$maintext .= "<tr><td><a href='index.php?action=$action&nerid=".urlencode($nerid)."&type=$type'>$form</a></tr>";
		};
		$maintext .= "</table>";

	} else {
	
		# List of types of NER we have
		$maintext .= "<h2>{%Named Entities}</h2><h1>{%Select}</h1>";
		
		foreach ( $nerlist as $key => $val ) {
			$maintext .= "<p><a href='index.php?action=$action&type=$key'>{$val['display']}</a>";
		};

	};
	

?>