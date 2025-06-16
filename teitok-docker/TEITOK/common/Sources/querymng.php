<?php
	# Query Manager
	if ( $username && !$userid ) $userid = $username;
	$qfldr = preg_replace("/[^a-z0-9]/", "", strtolower($userid));
	if ( $act != "save" && $_GET['ufl'] ) $qfldr = $_GET['ufl'];
	
	$qfn = "Users/$qfldr/queries.xml";
	if ( file_exists($qfn) ) $qlist = simplexml_load_file($qfn);
	if ( file_exists("Resources/queries.xml") ) $glqs= simplexml_load_file("Resources/queries.xml");

	$qls = array ( "cqp" => 'CQL', "psdx" => "XPath", "dicsearch" => "SQL", "pmltq" => "PML-TQ", "grew" => "Grew-match" );

	if ( $act == "save" ) {
	
		if ( !$userid ) fatal("You can only store queries when logged in.");
				
		if ( !$qlist ) {
			check_folder("Users");
			check_folder("Users/$qfldr");
			file_put_contents($qfn, "<queries/>");
			$qlist = simplexml_load_file($qfn);
		}; 
		if ( $qlist === false ) { fatal("Error loading query file"); };
		$qroot = current($qlist->xpath("/queries"));
	
		$q = $_POST['q'] or $q = $_POST['query'] or $q = $_POST['querytxt'] or $q = $_POST['cql'];
		$id = uniqid();
		$qrec = $qroot->addChild("query");
		$qrec['ql'] = $_GET['type'];
		$qrec['id'] = $id;
		$qdef = $qrec->addChild("q", htmlspecialchars($q, ENT_QUOTES, "utf-8"));
		file_put_contents($qfn, $qlist->asXML()); 
		print "<p>Query saved<script>top.location='index.php?action=$action&act=edit&id=$id';</script>";
		exit;
	
	} else if ( $act == "publish" && $qlist ) {

		if ( !$username ) fatal("Only for admin users");

		if ( !$glqs ) $glqs = simplexml_load_string("<queries/>");

		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));
		if ( !$qrec ) fatal ("No such record");
		$tmp = $glqs->addChild("query", "");
		replacenode($tmp, $qrec->asXML());
		unset($qrec[0]);
	
		if ( !is_object($qlist) ) fatal("Failed to publish query $id");
		file_put_contents($qfn, $qlist->asXML()); 
		file_put_contents("Resources/queries.xml", $glqs->asXML()); 
		print "<p>Query published<script>top.location='index.php?action=$action&type={$qrec['ql']}';</script>";
		exit;
	
	} else if ( $act == "modify" && $qlist ) {

		if ( !$userid ) fatal("Not logged in");

		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));
		if ( !is_object($qrec) ) fatal("No such query: $id");
		$qrec['name'] = $_POST['name'];
		$qq = $qrec->q;
		if ( $qq ) $qq[0] = $_POST['query'];
		else $qrec->addChild('q', $_POST['query']);
		$qdesc = $qrec->desc;
		if ( $qdesc ) $qdesc[0] = $_POST['description'];
		else $qrec->addChild('desc', $_POST['description']);
	
		file_put_contents($qfn, $qlist->asXML()); 
		print "<p>Query saved<script>top.location='index.php?action=$action&type={$qrec['ql']}';</script>";
		exit;
	
	} else if ( $act == "delete" && $qlist ) {

		if ( !$userid ) fatal("Not logged in");

		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));

		if ($qrec) unset($qrec[0]);
	
		if ( !is_object($qlist) ) fatal("Failed to delete query $id");
		file_put_contents($qfn, $qlist->asXML()); 
		print "<p>Query saved<script>top.location='index.php?action=$action&type={$qrec['ql']}';</script>";
		exit;
	
	} else if ( $act == "view" ) {
	
		$id = $_GET['id'];
		if ( $_GET['global'] ) $doq = $glqs; else $doq = $qlist;
		$qrec = current($doq->xpath("//query[@id=\"$id\"]"));
		$qname = $qrec['name'];
		$qq = $qrec->q;
		$qdesc = $qrec->desc;
		$timestamp = substr($id, 0, -5);
		$date = date('Y-m-;d h:i:s', hexdec($timestamp));  // Thu, 05 Sep 2013 15:55:04 -0400
		
		$qaction = $qrec['ql'].'';
		
		$maintext .= "<h1>{%Query}</h1>
			<p>{%Module}: {$qrec['ql']}</p>
			<p>{%Query Language}: {$qls[$qaction]}</p>
			<p>{%Date}: $date</p>
			<table >
			<tr><th>{%Name}<td>$qname
			<tr><th>{%Query}$qq
			<tr><th>{%Description}<td>$qdesc
			</table>
			
			<p><a href='index.php?action=$qaction&qid=$id'>{%run this query}</a> 
				&bull; <a href='index.php?action=$action&type={$qrec['ql']}'>{%back to list}</a>";		
			
	} else if ( $act == "edit" && $qlist ) {
	
		if ( !$userid ) fatal("Not logged in");
		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));
		$qname = $qrec['name'];
		$qq = $qrec->q;
		$qdesc = $qrec->desc;
		$qaction = $qrec['ql'].'';
		$maintext .= "<h1>{%Edit Query}</h1>
			<p>{%Module}: {$qrec['ql']}</p>
			<p>{%Query Language}: {$qls[$qaction]}</p>
			<form action='index.php?action=$action&act=modify&id=$id' method=post>
			<table >
			<tr><th>{%Name}<td><input size=80 name=name value=\"$qname\">
			<tr><th>{%Query}<td><textarea style='width: 600px; height: 50px;' name=query>$qq</textarea>
			<tr><th>{%Description}<td><textarea style='width: 600px; height: 50px;' name=description>$qdesc</textarea>
			</table>
			<p><input type=submit value=\"{%Save}\"> 
				<a href='index.php?action=$action&type={$qrec['ql']}'>{%cancel}</a>
				&bull; 
				<a href='index.php?action=$action&id=$id&act=delete'>{%delete}</a>";
		if ( $username ) $maintext .= "&bull; 
				<a href='index.php?action=$action&id=$id&act=publish' class=adminpart>{%publish}</a>";
		$maintext .= "</form>";
	
	} else if ( $action == "querymng" ) {
	
		$maintext .= "<h1>{%Query Manager}</h1><style>#qlist q::before, #qlist q::after { content: ''; }</style>";
	
		if ( $_GET['type'] ) {
			$ql = $qls[$_GET['type']] or $ql = $_GET['type'];
			$qlq = "[@ql=\"$ql\"]";
			$maintext .= "<p>{%Query Language}: <b>{$qls[$ql]} ($ql)</b></p><hr>";
		};
		if ( $qlist ) $qres = $qlist->xpath("//query$qlq");
		if ( $qres && count($qres)>0 ) {
			if ( !$ql ) $qlh = "<th>{%Query Language}";
			$maintext .= "<h2>{%Personal Queries}</h2>";
			$maintext .= "<table id=rollovertable><tr><td><th>{%Name}$qlh<th>{%Query}<th>{%Description}";
			foreach ( $qres as $qq ) {
				$qname = $qq['name'] or $qname = "<i>unnamed</i>";
				$qaction = $qq['ql'];
				$qlt = $qaction.'';
				if ( $qls[$qlt] ) $qlt = "{$qls[$qlt]} ($qlt)"; 
				if ( !$ql ) $qlr = "<td><a href='index.php?action=$action&type={$qq['ql']}'>$qlt</a>";
				$maintext .= "<tr><td>
					<a href='index.php?action=$action&act=edit&id={$qq['id']}'>edit</a>
					<a href='index.php?action=$qaction&qid={$qq['id']}'>run</a>
					<td>$qname$qlr<td>".$qq->q."<td>".$qq->desc;
			};
			$maintext .= "</table><hr>";
		} else if ( !$userid ) $maintext .= "<p><i>{%Login to manage your queries}</i></p>";
		else $maintext .= "<p><i>{%No personal queries yet}</i></p>";

		unset($qres); if ( $glqs )  $qres = $glqs->xpath("//query$qlq"); 
		if ( $qres && count($qres)>0 ) {
			$maintext .= "<h2>{%Predefined Queries}</h2>";
			if ( !$ql ) $qlh = "<th>{%Query Language}";
			$maintext .= "<table id=rollovertable><tr><td><th>{%Name}$qlh<th>{%Query}<th>{%Description}";
			foreach ( $qres as $qq ) {
				$qname = $qq['name'] or $qname = "<i>unnamed</i>";
				$qaction = $qq['ql']; 
				$qlt = $qaction.'';
				if ( $qls[$qlt] ) $qlt = "{$qls[$qlt]} ($qlt)"; 
				if ( !$ql ) $qlr = "<td><a href='index.php?action=$action&type={$qq['ql']}'>$qlt</a>";
				$maintext .= "<tr><td>
					<a href='index.php?action=$action&act=view&global=1&id={$qq['id']}'>details</a>
					<a href='index.php?action=$qaction&qid={$qq['id']}'>run</a>
					<td>$qname$qlr<td>".$qq->q."<td>".$qq->desc;
			};
			$maintext .= "</table><hr>";
		}	
		
		if ( $_SESSION['queries'] ) {
			$maintext .= "<h2>{%Recent Queries}</h2>
				<form id=qfm style='display: none;' action='index.php?action=$action&act=save' method=post>
				<textarea name='query'></textarea>
				</form>
				<script>
					function setq(cnt, ql) {
						var qfm = document.getElementById('qfm');
						qfm.action = 'index.php?action=$action&act=save&type='+ql;
						qfm['query'].value = document.getElementById('q-'+cnt).innerText; 
						qfm.submit();
					};
				</script>";
			$maintext .= "<table id=rollovertable><tr><td><th>{%Query Language}<th>{%Query}";
			$cnt = 0;
			$recnt = $_SESSION['queries'];
			krsort($recnt);
			foreach ( $recnt as $id => $qrec ) {
				$qq = $qrec['query']; $ql = $qrec['ql'];
				$qqt = urlencode($qq); $cnt++;
				$qaction = $ql;
				$qlt = $qaction.'';
				if ( $qls[$qlt] ) $qlt = "{$qls[$qlt]} ($qlt)"; 
				$maintext .= "<tr><td>
					<div style='display: none' id='q-$cnt'>$qq</div> <a onclick=\"setq($cnt, '$ql');\">store</a>
					<a href='index.php?action=$qaction&query=$qqt'>run</a>
					<td>$qlt<td>$qq<td style='color: #bbbbbb; background-color: white;'><i>".date("Y-m-d h:i:s", $id)."</i>";
			};
			$maintext .= "</table><hr>";
		};
		
		if ( $ql ) {
			$maintext .= "<p><a href='index.php?action=$action'>{%See queries in all query languages}</a>";
		};
				
	};

	function getq ($qid, $full=0) {
		global $qlist, $glqs; 
		if ( $qlist ) {
			$qrc = current($qlist->xpath("//query[@id=\"$qid\"]"));
		} else if ( $glqs ) {
			$qrc = current($glqs->xpath("//query[@id=\"$qid\"]"));
		};
		if ( $qrc ) {
			if ( $full ) return $qrc[0];
			else return $qrc[0]->q;
		};
	};

	function getqlist ($type) {
		global $qlist, $glqs;
		if ( $qlist) foreach ( $qlist->xpath("//query[@ql=\"$type\"]") as $item ) {
			$qq = $item->q;
			$qname = $item['name']."" or $qname = $qq."";
			$qs[$item['id'].""] = array ( "name" => $qname."", "query" => $qq."" );
		};
		if ( $glqs) foreach ( $glqs->xpath("//query[@ql=\"$type\"]") as $item ) {
			$qq = $item->q;
			$qname = $item['name']."" or $qname = $qq."";
			$qs[$item['id'].""] = array ( "name" => $qname."", "query" => $qq."" ) ;
		};
		return $qs;
	};
	
?>