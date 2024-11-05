<?php

	# Module for FCS (federated content search)
	# (c) 2024 Maarten Janssen
	
	# Data can be provided by each corpus separately
	# or aggregated in the shared folder
	# The shared corpus reads the corpora either from corplist.xml
	# or from the <fcs> section in the settings.xml
	
	$version = $_GET['version'] or $version = "2.0";
	$schema = $_GET['recordSchema'] or $schema = "http://clarin.eu/fcs/resource";
	$format = $_GET['recordPacking'] or $format = "xml";
	$accept = $_GET['httpAccept'] or $accept = "application/sru+xml";
	$rawquery = $_GET['query'];
	$querytype = "cql";

	$http = "http";
					
	
	header("Content-type: text/xml");
	
	if ( !$version ) {
	
		# Should we give an error if the version is left blank?
	
	} else if ( !$rawquery ) {

		print"<sru:searchRetrieveResponse xmlns:sru=\"http://www.loc.gov/zing/srw/\">
<sru:version>$version</sru:version>
<sru:numberOfRecords>0</sru:numberOfRecords>
<sru:diagnostics xmlns:diag=\"http://www.loc.gov/zing/srw/diagnostic/\">
<diag:diagnostic>
<diag:uri>info:srw/diagnostic/1/7</diag:uri>
<diag:details>query</diag:details>
<diag:message>Mandatory parameter 'query' is missing or empty. Required to perform query of query type '$querytype'.</diag:message>
</diag:diagnostic>
</sru:diagnostics>
</sru:searchRetrieveResponse>";
		exit;

	} else if ( $schema != "fcs" && $schema != "http://clarin.eu/fcs/resource" ) {

		print "<sru:searchRetrieveResponse xmlns:sru=\"http://www.loc.gov/zing/srw/ \">
<sru:version>$version</sru:version>
<sru:numberOfRecords>0</sru:numberOfRecords>
<sru:diagnostics xmlns:diag=\"http://www.loc.gov/zing/srw/diagnostic/\">
<diag:diagnostic>
<diag:uri>info:srw/diagnostic/1/66</diag:uri>
<diag:details>$schema</diag:details>
<diag:message>Record schema \"$schema\" is not supported for retrieval.</diag:message>
</diag:diagnostic>
</sru:diagnostics>
</sru:searchRetrieveResponse>";
		exit;
		
	} else if ( $_GET['operation'] == "searchRetrieve") {
	
		# Build the CQL query
		if ( $type == "fcsql" ) { 
		} else {
			$cql = 'Matches = ';
			foreach ( explode (" ", $rawquery) as $word ) {
				$cql .= " [ word = \"$word\" ]";
			};
		};
		$cat  = "tabulate Matches match text_id, match[0]..matchend[0] id, match[-5]..match[-1] word, match[0]..matchend[0] word, matchend[1]..matchend[5] word";
		
		$pid = "hdl:11022/0000-0000-2417-E";

		$sru = simplexml_load_string("<sru:searchRetrieveResponse xmlns:sru=\"http://www.loc.gov/zing/srw/\"><sru:version>$version</sru:version>
<sru:echoedSearchRetrieveRequest>
<sru:version>$version</sru:version>
<sru:query>$rawquery</sru:query>
<sru:xQuery xmlns=\"http://www.loc.gov/zing/cql/xcql/\">
<searchClause>
<index>cql.serverChoice</index>
<relation>
<value>scr</value>
</relation>
<term>help</term>
</searchClause>
</sru:xQuery>
<sru:startRecord>$start</sru:startRecord>
</sru:echoedSearchRetrieveRequest>
</sru:searchRetrieveResponse>");
		
		# $sru['debug'] = print_r($_SERVER, 1);
		
		$srudom = dom_import_simplexml($sru)->ownerDocument;
		$srecords = $srudom->createElement("sru:records");
		$srudom->firstChild->appendChild($srecords);

		include ("$ttroot/common/Sources/cwcqp.php");

		$checkshared = preg_replace("/.*\/([^\/]+)\/?/", "\\1", getenv('TT_SHARED'));
		if ( $checkshared != $foldername ) { 
			$isshared = false;
		} else {
			$isshared = true;
		};
	
		# Cycle through the corpora
		$totcnt = 0;
		if ( $isshared ) {

			$corplist = getset('fcs/corpora');
			if ( $corplist == ""  ){
				$corplist = array();
				$corpxml = simplexml_load_file("Resources/corplist.xml");
				foreach ( $corpxml->xpath("//corpus") as $corp ) {
					$title = $corp->name."";
					$folder = $corp->url."";
					$id = preg_replace("/.*\//", "", $folder);
					$lang = $corp->lang."";
					$corplist[$id] = array("folder" => $folder, "title" => $title, "reg" => "../$folder/cqp", "lang" => $lang);
				};
			};

			foreach ( $corplist as $id => $corp ) {
						
				$results = searchcorpus($corp);
				
				$corpusurl = "$http://{$_SERVER['SERVER_NAME']}$baseurl../{$corp['folder']}";
				makerecs($results);
								
			};
			
		} else {
		
			$folder = "."; $title = getset("defaults/title/display"); $lang = getset("defaults/lang");
			$corp = array( "folder" => $folder, "title" => $title, "lang" => $lang, "reg" => "cqp", "id" => getset("cqp/corpus") );
			$results = searchcorpus($corp);
			$corpusurl = "httpx://{$_SERVER['SERVER_NAME']}{$_SERVER['SCRIPT_NAME']}";
			makerecs($results);

		};

		$sru->addChild("sru:numberOfRecords", $totcnt );		
		print $srudom->saveXML();
		
		
		exit;
		
	};
	
	function makerecs( $results ) {
		global $srecords, $srudom, $corpusurl;
	
		foreach ( $results as $result ) {
			if ( !$result ) continue;
			
			list ( $textid, $tokids, $left, $hit, $right ) = explode("\t", $result);
			
			$srec = $srudom->createElement("sru:record");
			$srecords->appendChild($srec);
			$srec->appendChild($srudom->createElement("sru:recordSchema", "http://clarin.eu/fcs/resource")); # ); 
			$srec->appendChild($srudom->createElement("sru:recordPacking", "xml")); # ); 
			$sdata = $srudom->createElement("sru:recordData");
			$srec->appendChild($sdata);
			$tmp = $srudom->createElementNS("http://clarin.eu/fcs/resource", "fcs:Resource");
			$sdata->appendChild($tmp);
// 					# $tmp['pid'] = $pid;
			$tmp2 = $srudom->createElement("fcs:ResourceFragment");
			$tmp->appendChild($tmp2);
			$tmp3 = $srudom->createElement("fcs:DataView");
			$tmp2->appendChild($tmp3);
			$shit = $srudom->createElementNS("http://clarin.eu/fcs/dataview/hits", "hits:Result");
			$tmp3->appendChild($shit);
			$shit->appendChild($srudom->createTextNode($left));
			$shit->appendChild($srudom->createElement("hits:Hit", $hit));
			$shit->appendChild($srudom->createTextNode($right));
			$shit->setAttribute("ref", "$corpusurl/index.php?action=file&cid=$textid&jmp=$tokids");
		};
	};
	
	function searchcorpus($corp) {
		
		global $cql, $cat, $totcnt;	
		
		$reg = $corp['reg'] or $reg = $corp['folder']."/cqp";
		$cqpcorpus = strtoupper($corp['id']);
		$reg = getcwd()."/$reg";

		$cqp = new CQP($reg);
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

// 		print_r($corp); print "<p>Corpus: $cqpcorpus, Query: $cql, CAT: $cat";
// 		 exit;

		$cqp->exec($cql);
		$size = $cqp->exec("size Matches");
		$totcnt += (int)$size;
		$results = $cqp->exec($cat);
		
		return explode("\n", $results);			
	
	};
	
?>