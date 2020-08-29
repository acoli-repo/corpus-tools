<?php

	# Name-oriented document view and name index
	# Maarten Janssen, 2020

	$nerlist = $settings['xmlfiles']['ner']['tags'] 
		or 
		$nerlist = array(
			"placename" => array ( "display" => "Place Name", "cqp" => "place", "elm" => "placeName" ), 
			"personname" => array ( "display" => "Person Name", "cqp" => "person", "elm" => "persName" ), 
			"name" => array ( "display" => "Name", "cqp" => "name", "elm" => "name" ),
			"term" => array ( "display" => "Term", "cqp" => "term", "elm" => "term" ),
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
			unset($refnames);
			if ( $nodelist ) {
				$maintext .= "<tr><td colspan=2 style='padding-top: 10px; padding-bottom: 10px; '><b style='font-size: larger;'>{$val['display']}</b></tr>";
			
				foreach ( $nodelist as $node ) {
					$ref = $node['ref'];
					$name = $node->asXML() or $name = $ref;
					$name = preg_replace("/<[^>]+>/", "", $name);
					$refnames[$ref.""][$name.""]++;
					# $maintext .= "<tr><td><a href='index.php?action=$action&type=$key&ref=".urlencode($ref)."'>$name</a><td><a href='$ref'>$ref</a>";
				};
			};	
			foreach ( $refnames as $ref => $val ) {
				$name = join("<br/>", array_keys($val));
				if ( substr($ref, 0, 4) == "http") $reftxt = "<a href='$ref'>$ref</a>";
				else if ( substr($ref, 0, 1) == "#" ) {
					$refxp = "//*[@id=\"".substr($ref,1)."\" or @xml:id=\"".substr($ref,1)."\"]";
					$refnode = $xml->xpath($refxp);
					$reftxt = ""; $sep = "";
					if ( !$refnode ) $reftxt = ""; else 
					foreach ( $refnode[0]->xpath(".//link") as $linknode ) {
						$refname = $linknode['type'] or $refname = $linknode['target'];
						$reftxt = $sep."<a href='{$linknode['target']}'>$refname</a>"; 
						$sep = "<br/>";
					};
				} else $reftxt = $ref;
				$cidr = ""; if ( substr($ref,0,1) == "#" ) $cidr = "&cid=".$ttxml->fileid;
				if ( $trc == "odd" ) $trc = "even"; else $trc = "odd";
				$maintext .= "<tr class='$trc'><td><a href='index.php?action=$action&type=$key&ref=".urlencode($ref)."$cidr'>$name</a><td>$reftxt";
			};
		};
		$maintext .= "</table>
				<hr> <a href='index.php?action=$action&cid={$ttxml->fileid}'>{%back}</a>";

	} else if ( $_GET['cid'] && !$_GET['ref'] ) {

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

		$maintext .= "<script language=Javascript>
			var nerlist = $nerjson;
			var hlref = '{$_GET['hlref']}';
			var mtxt = document.getElementById('mtxt');
			
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
					if ( it.getAttribute('ref') == hlref ) { 
						it.style['backgroundColor'] = '#ffffbb'; 
					}
				};
			};
			
			function doclick(elm) {
				var trgt = elm.getAttribute('ref');
				var ttype = elm.nodeName;
				window.open('index.php?action=$action&ref='+trgt+'&type='+ttype, '_self');
			};
			function showinfo(elm) {
				console.log(elm);
			};
			function hideinfo(elm) {
				console.log(elm);
			};
		</script>";
		
	} else if ( $_GET['ref'] ) {
	
		if ( !$name ) $name = $_GET['ref'];
	
		$type = strtolower($_GET['type']);
	
		$maintext .= "<h2>{%Named Entities}</h2><h1>$name</h1>";
	
		$ref = $_GET['ref'];
		if ( substr($ref,0,4) == "http") {
			$maintext .= "<p>Reference: <a href='{$_GET['ref']}'>{$_GET['ref']}</a></p>";
		} else if ( substr($ref, 0, 1) == "#" ) {
			if ( $_GET['cid']) {
				require("$ttroot/common/Sources/ttxml.php");
				$ttxml = new TTXML();
				$fileid = $ttxml->fileid;
				$xmlid = $ttxml->xmlid;
				$xml = $ttxml->xml;

				$refxp = "//*[@id=\"".substr($ref,1)."\" or @xml:id=\"".substr($ref,1)."\"]";
				$refnode = $xml->xpath($refxp);
				$reftxt = ""; $sep = "";
				if ( $refnode ) $maintext .= $refnode[0]->asXML();
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
		# $cql = "Matches =  []+  :: match.{$nodeatt}_ref=\"{$_GET['ref']}\" within $nodeatt";
		$cql = "Matches = <$nodeatt> []+ </$nodeatt> :: match.{$nodeatt}_ref=\"{$_GET['ref']}\"";
		$cqp->exec($cql); 
		$results = $cqp->exec("tabulate Matches match, matchend, match text_id, match id");

		$xidxcmd = findapp("tt-cwb-xidx");
		
		$maintext .= "<table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $leftpos, $rightpos, $fileid, $tokid ) = explode("\t", $resline);
			$cmd = "$xidxcmd --filename='$fileid' --cqp='$cqpfolder' $expand $leftpos $rightpos";
			$resxml = shell_exec($cmd);
			$context = preg_replace("/.*\/(.*?)\.xml/", "\\1", $fileid);
			$maintext .= "<tr><td><a href='index.php?action=$action&cid=$fileid&jmp=$tokid&hlref=".urlencode($_GET['ref'])."'>$context</a><td>$resxml";
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
		
		$cql = "group Matches match {$neratt}_ref";
		$results = $cqp->exec($cql); 
		
		$maintext .= "<table>";
		foreach ( explode("\n", $results) as $resline ) {
			list ( $ref, $cnt, $display ) = explode("\t", $resline);
			if ( !$display ) $display = $ref;
			$maintext .= "<tr><td><a href='index.php?action=$action&ref=".urlencode($ref)."&type=$type'>$display</a></tr>";
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