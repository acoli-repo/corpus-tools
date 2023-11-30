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
	
	if ( ( is_array($settings['xmlfile']['toc']) && $settings['xmlfile']['toc']['file'] ) || file_exists("Resources/toc.xml") ) {
		
		$tocfile = "Resources/toc.xml";
		if ( is_array($settings['xmlfile']['toc']) && $settings['xmlfile']['toc']['file'] ) $tocfile = $settings['xmlfile']['toc']['file'];
		$tocxml = simplexml_load_file($tocfile);
		// Read the appids in the XML
		foreach ( $ttxml->xpath("//*[@appid]") as $appnode ) $appidlist[$appnode['appid'].""] = $appnode['id']."";
		
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
							} else if ( !li.getAttribute('empty') || li.getAttribute('type') == 'leaf' ) {
								li.style.display = 'block';
							};
						};
					};
					document.getElementById('toc').addEventListener('click',function(e) {
					  toggle(e.target);
					});
					console.log('checking');
					var lis = document.getElementById('toc').getElementsByTagName('li');
					for ( var i=0; i<lis.length; i++ ) {
						var li = lis[i];
						var links = document.evaluate(\".//a\", li, null, XPathResult.ANY_TYPE, null);
						if ( !links.iterateNext() ) {
							li.setAttribute('empty', '1');
							li.style.display = 'none';
						};
					};
				</script>
				<style>
					li[stat=leaf]::before { content:'• '; }
					li[stat=collapsed]::before { content:'⊞ '; }
					li[stat=expanded]::before { content:'⊟ '; }
					li[empty] { display: none; }
				</style>
				";	

	} else if ( $settings['xmlfile']['toc'] ) {

		$tocdef = $settings['xmlfile']['toc'];
		if ( $tocdef['xp'] ) $tocxp = $tocdef['xp']; else $tocxp = "//teiHeader/toc";
		if ( $ttxml->xpath($tocxp) ) {
			$tocdef = xmlflatten(current($ttxml->xpath($tocxp)));
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
	if ( count($ttxml->xpath("//$pbelm")) > 1 ) {
		$lpnr = "";
		$maintext .= "<td valign=top>
			<h2>{%Page List}</h2>";
		# Build the list of pages
		$result = $ttxml->xpath("//pb"); $tmp = 0;
		foreach ($result as $cnt => $node) {
			$pid = $node['id'] or $pid = "[$cnt]";
			$pnr = $node['n'] or $pnr = "[$cnt]";
			$tst = ""; if ( $node['empty'] ) $tst = "style='opacity: 0.2;' title='{%empty}'";
			if ( $settings['defaults']['thumbnails'] ) {
				$tni = $node['facs']; 
				$tnn = "$ttxml->xmlid/$ttxml->xmlid"."_$pnr.jpg";
				if ( $tni && file_exists("Thumbnails/$tni") ) $tni = "Thumbnails/$tni";
				else if ( file_exists("Thumbnails/$tnn") ) $tni = "Thumbnails/$tnn";
				else if ( !preg_match("/http/", $tni) ) $tni = "Facsimile/$tni";
				$maintext .= "<a  $tst href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\"><div class=thumbnail><img src='$tni' title=\"$ttxml->xmlid:$pnr\"/><br>$pnr</a></div>";
			} else {
				$maintext .= "<p><a $tst href=\"index.php?action=file&cid=$fileid&pageid=$pid&pbtype=pb\">$pnr</a>";
			};
		};
		$maintext .= "</td>";
	};
	if ( !$settings['xmlfile']['index'] ) $settings['xmlfile']['index'] = array ( "chapter" => array ( "display" => "Chapter List" ));
	foreach ( $settings['xmlfile']['index'] as $key => $val ) {
		$pbatt = $val['att'] or $pbatt = "n";
		if ( $val['div'] || $val['xpath'] ) {
			if ( $val['xpath'] ) $divxp = $val['xpath'];
			else $divxp = "//{$val['div']}[@type=\"$key\"]";
			if ( count($ttxml->xpath($divxp)) > 0 ) {
				$lpnr = "";
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xpath($divxp); $tmp = 0;
				foreach ( $result as $cnt => $node ) {
					$pid = $node['id'] or $pid = "[$cnt]";
					$pnr = $node[$pbatt] or $pnr = "[$cnt]";
					$maintext .= "<p><a href=\"index.php?action=file&cid=$fileid&div=$pid&divtype={$key}\">$pnr</a>";
				};
				$maintext .= "</td>";
			};
		} else {
			if ( count($ttxml->xpath("//milestone[@type=\"$key\"]")) > 0 ) {
				$maintext .= "<td valign=top><h2>{%{$val['display']}}</h2>";
				# Build the list of pages
				$result = $ttxml->xpath("//milestone[@type=\"$key\"]"); $tmp = 0;
				foreach ($result as $cnt => $node) {
					$pid = $node['id'] or $pid = "[$cnt]";
					$pnr = $node[$pbatt] or $pnr = "[$cnt]";
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
			if ( $nodenum ) {
				$leaf = "nolist";
				$nodename = "$nodenum. $nodename";
			} else $leaf = "leaf";
			
			$appid = $chld['appid'].""; 
			
			if ( count($chld->children()) ) {
				if ( $appidlist[$appid] ) $nodename = "<a href='$tocbaseurl&appid={$chld['appid']}&jmp={$appidlist[$appid]}'>$nodename</a>";
				$tree .= "<li stat=$stat $nolink>$nodename".maketoctree($chld)."</li>";
			} else {
				if ( $appidlist[$appid] ) {
					$tree .= "<li stat=$leaf style='display: none;'><a href='$tocbaseurl&appid={$chld['appid']}&jmp={$appidlist[$appid]}'>$nodename</a></li>";
				} else {
					$litit = ""; if ( $appid ) $litit = "No appid $appid";
					$tree .= "<li stat=$leaf nolink=\"1\" title=\"$litit\" style='display: none;'><span style='color: #bbbbbb;'>$nodename</span></li>";
				};
			};
		};
		$tree .= "</ul>";
		
		return $tree;
	};
	
?>