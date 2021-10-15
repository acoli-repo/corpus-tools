<?php
	# Query Manager
	if ( $username && !$userid ) $userid = $username;
	$qfldr = preg_replace("/[^a-z0-9]/", "", strtolower($userid));
	if ( $act != "save" && $_GET['ufl'] ) $qfldr = $_GET['ufl'];
	
	$qfn = "Users/$qfldr/queries.xml";
	if ( file_exists($qfn) ) $qlist = simplexml_load_file($qfn);

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
	
		$q = $_POST['q'] or $q = $_POST['query'] or $q = $_POST['querytxt'];
		$id = uniqid();
		$qrec = $qroot->addChild("query");
		$qrec['ql'] = $_GET['type'];
		$qrec['id'] = $id;
		$qdef = $qrec->addChild("q", $q);
		file_put_contents($qfn, $qlist->asXML()); 
		print "<p>Query saved<script>top.location='index.php?action=$action&type={$_GET['type']}';</script>";
		exit;
	
	} else if ( $act == "modify" && $qlist ) {

		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));
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
	
	} else if ( $act == "edit" && $qlist ) {
	
		$id = $_GET['id'];
		$qrec = current($qlist->xpath("//query[@id=\"$id\"]"));
		$qname = $qrec['name'];
		$qq = $qrec->q;
		$qdesc = $qrec->desc;
		$maintext .= "<h1>{%Edit Query}</h1>
			<p>{%Query Language}: {$qrec['ql']}</p>
			<form action='index.php?action=$action&act=modify&id=$id' method=post>
			<table >
			<tr><th>{%Name}<td><input size=80 name=name value=\"$qname\">
			<tr><th>{%Query}<td><textarea style='width: 600px; height: 50px;' name=query>$qq</textarea>
			<tr><th>{%Description}<td><textarea style='width: 600px; height: 50px;' name=description>$qdesc</textarea>
			</table>
			<p><input type=submit value=\"{%Save}\"> <a href='index.php?action=$action&type={$qrec['ql']}'>{%cancel}</a>
			</form>";
	
	} else if ( $action == "querymng" ) {
	
		$maintext .= "<h1>{%Query Manager}</h1><style>#qlist q::before, #qlist q::after { content: ''; }</style>";
	
		if ( $_GET['type'] ) {
			$ql = $qls[$_GET['type']] or $ql = $_GET['type'];
			$qlq = "[@ql=\"$ql\"]";
			$maintext .= "<h2>{%Query Language}: $ql</h2>";
		};
		if ( $qlist ) $qres = $qlist->xpath("//query$qlq");
		if ( $qres ) {
			if ( !$ql ) $qlh = "<th>{%Query Language}";
			$maintext .= "<table id=qlist><tr><td><th>{%Name}$qlh<th>{%Query}<th>{%Description}";
			foreach ( $qres as $qq ) {
				$qname = $qq['name'] or $qname = "<i>unnamed</i>";
				$qaction = $qq['ql'];
				if ( !$ql ) $qlr = "<td>{$qq['ql']}";
				$maintext .= "<tr><td>
					<a href='index.php?action=$action&act=edit&id={$qq['id']}'>edit</a>
					<a href='index.php?action=$qaction&qid={$qq['id']}'>run</a>
					<td>$qname$qlr<td>".$qq->q."<td>".$qq->desc;
			};
			$maintext .= "</table>";
		} else if ( !$userid ) $maintext .= "<p><i>{%Login to manage your queries}</i></p>";
		else $maintext .= "<p><i>{%No personal queries yet]</i></p>";
			
	};

	function getq ($qid) {
		global $qlist; 
		if ( $qlist ) {
			$qrc = current($qlist->xpath("//query[@id=\"$qid\"]/q"));
			return $qrc[0];
		};

	};

?>