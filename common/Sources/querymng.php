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
	
	} else if ( $act == "edit" && $qlist ) {
	
		$qrec = current($qlist->xpath("//query[@id=\"{$_GET['id']}\"]"));
		print showxml($qrec); exit;
	
	} else if ( $action == "querymng" ) {
	
		$maintext .= "<h1>Query Manager</h1><style>#qlist q::before, #qlist q::after { content: ''; }</style>";
	
		if ( $_GET['type'] ) {
			$ql = $qls[$_GET['type']] or $ql = $_GET['type'];
			$qlq = "[@ql=\"$ql\"]";
			$maintext .= "<h2>Query Language: $ql</h2>";
		};
		if ( $qlist ) $qres = $qlist->xpath("//query$qlq");
		if ( $qres ) {
			$maintext .= "<table id=qlist><tr><td><th>Query Name<th>Query Language<th>Query";
			foreach ( $qres as $qq ) {
				$qname = $qq['name'] or $qname = "<i>unnamed</i>";
				$qaction = $qq['ql'];
				$maintext .= "<tr><td>
					<a href='index.php?action=$action&act=edit&id={$qq['id']}'>edit</a>
					<a href='index.php?action=$qaction&query={$qq['id']}'>run</a>
					<td>$qname<td>{$qq['ql']}<td>".$qq->asXML();
			};
			$maintext .= "</table>";
		} else $maintext .= "<p><i>No personal queries yet</i></p>";
			
	};

	function getq ($qid) {
		global $qlist; 
		if ( $qlist ) {
			$qrc = current($qlist->xpath("//query[@id=\"$qid\"]/q"));
			return $qrc[0];
		};

	};

?>