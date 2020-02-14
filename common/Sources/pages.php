<?php
	// Script to list the <pb/> elements in an XML file - or <milestone/>
	// (c) Maarten Janssen, 2015
		
	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$maintext .= "<h1>".$ttxml->title()."</h1>"; 
	$maintext .= $ttxml->tableheader(); 
	$maintext .= $ttxml->viewheader(); 
	
	$fileid = $_GET['cid'] or $fileid = $_GET['id'];
	
	$pbsel = $_GET['pbtype'] or $pbsel = $_GET['type'];
	if ( !$pbsel ) { 
		$pbelm = "pb";
		$titelm = "Page";
		$pbtype = "pb";
	} else if ( $pbsel == "chapter" ) { 
		$pbelm = "milestone[@type=\"chapter\"]";
		$titelm = "Chapter";
		$pbtype = "milestone";
	} else {
		$pbelm = "milestone[@type=\"$pbsel\"]";
		$titelm = ucfirst($pbsel);
		$pbtype = "milestone";
	};
	
	if ( $settings['xmlfile']['toc']['file'] || file_exists("Resources/toc.xml") ) {
		
		$tocfile = $settings['xmlfile']['toc']['file'] or $tocfile = "Resources/toc.xml";
		$tocxml = simplexml_load_file($tocfile);
		// Read the appids in the XML
		foreach ( $ttxml->xml->xpath("//*[@appid]") as $appnode ) $appidlist[$appnode['appid'].""] = $appnode->getName();
		
		$tocbaseurl = "index.php?action=file&cid=$ttxml->xmlid";

		$maintext .= "<h2>{%Table of Contents}</h2><div id='toc'>".maketoctree($tocxml)."</div>\n";
		$maintext .= "<script language=Javascript>
					function toggle (elm) {
						if ( elm.getAttribute('stat') == 'leaf' ) {
							return -1;
						} else if ( elm.getAttribute('stat') == 'collapsed' ) {
							elm.setAttribute('stat', 'expanded');
						} else {
							elm.setAttribute('stat', 'collapsed');
						}
						var ul = elm.childNodes[1];
						for ( var i=0; i<ul.childNodes.length; i++ ) {
							var li = ul.childNodes[i];
							if ( li.style.display == 'block' ) {
								li.style.display = 'none';
							} else {
								li.style.display = 'block';
							};
						};
					};
					document.getElementById('toc').addEventListener('click',function(e) {
					  toggle(e.target);
					});
				</script>
				<style>
					li[stat=leaf]::before { content:'• '; }
					li[stat=collapsed]::before { content:'⊞ '; }
					li[stat=expanded]::before { content:'⊟ '; }
				</style>
				";	

	} else if ( $settings['xmlfile']['toc'] ) {

		$tocdef = $settings['xmlfile']['toc'];
		if ( $tocdef['xp'] ) $tocxp = $tocdef['xp']; else $tocxp = "//teiHeader/toc";
		if ( $ttxml->xml->xpath($tocxp) ) {
			$tocdef = xmlflatten(current($ttxml->xml->xpath($tocxp)));
		};

		$tocname = $settings['xmlfile']['toc']['display'] or $tocname = "Table of Contents"; # {%Table of Contents}
		$tocname = "{%$tocname}";
		$maintext .= "<h2>$tocname</h2>";
		$maintext .= "<div id='toc'>".makesub($ttxml->xml, 0)."</div>\n";
		$maintext .= "<script language=Javascript>
					function toggle (elm) {
						if ( elm.getAttribute('stat') == 'leaf' ) {
							return -1;
						} else if ( elm.getAttribute('stat') == 'collapsed' ) {
							elm.setAttribute('stat', 'expanded');
						} else {
							elm.setAttribute('stat', 'collapsed');
						}
						var ul = elm.childNodes[1];
						for ( var i=0; i<ul.childNodes.length; i++ ) {
							var li = ul.childNodes[i];
							if ( li.style.display == 'block' ) {
								li.style.display = 'none';
							} else {
								li.style.display = 'block';
							};
						};
					};
					document.getElementById('toc').addEventListener('click',function(e) {
					  toggle(e.target);
					});
				</script>
				<style>
					li[stat=leaf]::before { content:'• '; }
					li[stat=collapsed]::before { content:'⊞ '; }
					li[stat=expanded]::before { content:'• '; }
				</style>
				";	
	};
	
	$maintext .= "<table style='width: 100%'><tr>";
	if ( count($ttxml->xml->xpath("//pb")) > 1 ) {
		$lpnr = "";
		$maintext .= "<td valign=top>
			<h2>{%Page List}</h2>";
		# Build the list of pages
		$result = $ttxml->xml->xpath("//pb"); $tmp = 0;
		foreach ($result as $cnt => $node) {
			$pid = $node['id'] or $pid = "cnt[$cnt]";
			$pnr = $node['n'] or $pnr = "cnt[$cnt]";
			if ( $settings['defaults']['thumbnails'] ) {
				$tni = $node['facs']; 
				$tnn = "$ttxml->xmlid/$ttxml->xmlid"."_$pnr.jpg";
				if ( $tni && file_exists("Thumbnails/$tni") ) $tni = "Thumbnails/$tni";
				else if ( file_exists("Thumbnails/$tnn") ) $tni = "Thumbnails/$tnn";
				else if ( !preg_match("/http/", $tni) ) $tni = "Facsimile/$tni";
				$maintext .= "<a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\"><div class=thumbnail><img src='$tni' title=\"$ttxml->xmlid:$pnr\"/><br>$pnr</a></div>";
			} else {
				$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\">$pnr</a>";
			};
		};
		$maintext .= "</td>";
	};
	if ( !$settings['xmlfile']['index'] ) $settings['xmlfile']['index'] = array ( "chapter" => array ( "display" => "Chapter List" ));
	foreach ( $settings['xmlfile']['index'] as $key => $val ) {
		if ( $val['div'] || $val['xpath'] ) {
			if ( $val['xpath'] ) $divxp = $val['xpath'];
			else $divxp = "//{$val['div']}[@type=\"$key\"]";
			if ( count($ttxml->xml->xpath($divxp)) > 0 ) {
				$lpnr = "";
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath($divxp); $tmp = 0;
				foreach ( $result as $cnt => $node ) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&div=$pid&divtype={$key}\">$pnr</a>";
				};
				$maintext .= "</td>";
			};
		} else {
			if ( count($ttxml->xml->xpath("//milestone[@type=\"$key\"]")) > 0 ) {
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xml->xpath("//milestone[@type=\"$key\"]"); $tmp = 0;
				foreach ($result as $cnt => $node) {
					$pid = $node['id'] or $pid = "cnt[$cnt]";
					$pnr = $node['n'] or $pnr = "cnt[$cnt]";
					$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=$key\">$pnr</a>";
				};
				$maintext .= "</td>";
			};
		};
	};
	$maintext .= "</tr></table>";

	function makesub ( $node, $n, $parentname = "" ) {
		$tree = "";  global $tocdef; global $ttxml;
		$tocidx = array_keys($tocdef);
		$levdef = $tocdef[$tocidx[$n]];
		$levatt = $levdef['att'] or $levatt = "n";
		$tree .= "<ul style='list-style-type: none;'>";
		foreach ( $node->xpath(".//{$levdef['xp']}") as $level ) {
			if ( $n == 0 ) $show = ""; else $show = "style='display: none;'";
			if ( $n < count($tocidx)-1 ) 
				$tree .= "<li $show stat='collapsed'>"; #  onClick='toggle(e, this);' 
			else 
				$tree .= "<li $show stat='leaf'>";
			
			$nodet = $level[$levatt];
			if ( $parentname && substr($parentname, -2) != "::" ) $parentname = "$parentname::";
			if ( $levdef['prefix'] ) $nodet = "{%{$levdef['display']}}: $nodet";
			$jmpname = $parentname.$nodet;
			
			if ( $levdef['link'] )
				$tree .= "<a href='index.php?action=file&cid=$ttxml->filename&jmp={$level['id']}&jmpname=$jmpname'>$nodet</a>";
			else 
				$tree .= $nodet;
			if ( $n < count($tocidx) ) { $tree .= makesub($level, $n+1, $jmpname); };
		};
		$tree .= "</ul>";
		
		return $tree;
	};

	function maketoctree ( $node ) {
		global $tocbaseurl; global $ttxml; global $settings; global $appidlist;
		
		$tree .= "<ul style='list-style-type: none;'>";
		if ( $node->getName() != "toc" ) {
			$stat = "collapsed style='display: none;'"; 
		} else {
			$stat = "collapsed style='display: block;'"; 
		}
		foreach ( $node->children() as $chld ) {
			$nodename = $chld['display'];
			$nodenum = $chld['n'];
			if ( $settings['xmlfile']['toc']['id18n'] ) $nodename = "{%$nodename}";
			if ( $nodenum ) $nodename = "$nodenum. $nodename";

			$appid = $chld['appid'].""; 
			
			if ( count($chld->children()) ) {
				$tree .= "<li stat=$stat $nolink>$nodename".maketoctree($chld)."</li>";
			} else {
				if ( $appidlist[$appid] ) {
					$tree .= "<li stat=leaf style='display: none;'><a href='$tocbaseurl&appid={$chld['appid']}'>$nodename</a></li>";
				} else {
					$tree .= "<li stat=leaf nolink=\"1\" title='No appid $appid' style='display: none;'><span style='color: #bbbbbb;'>$nodename</span></li>";
				};
			};
		};
		$tree .= "</ul>";
		
		return $tree;
	};
	
?>