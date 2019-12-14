<?php

	# Stastistics
	# (c) Maarten Janssen, 2019

	include ("$ttroot/common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus

	$cid = $_GET['cid'] or $cid = $_GET['id'];
	
	$maintext .= "<h1>{%Statistics}</h1>";
	
	if ( !$settings['cqp']['stats'] ) {
		$settings['cqp']['stats'] = array ( 
					array ( "var" => "tokcnt",  "cql" => "[]", "type" => "size", "display" => "Token count" ),
					array ( "var" => "formtypes",  "cql" => "group Tokcnt match form", "type" => "count", "display" => "Token types" ),
					array ( "var" => "ttrform",  "calc" => "formtypes/tokcnt", "type" => "calc", "display" => "TTR on forms" ),
			);
	};
	
	if ( $cid ) {

		include ("$ttroot/common/Sources/ttxml.php");

		$ttxml = new TTXML($cid);
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 

		# Get the token count 
		$cqp->exec("Matches = [] :: match.text_id='xmlfiles/$cid'");
		$tokcnt = $cqp->exec("size Matches");

		$maintext .= getlangfile("statstext");

		$maintext .= "<table>";
		foreach ( $settings['cqp']['stats'] as $key => $val ) {
			$varname = $val['var'];
			$cql = $val['cql'];
			$vartype = $val['type'];
			$tit = $val['cql'];
			if ( $vartype == "size" ) {
				$cql = ucfirst($varname)." = $cql :: match.text_id='xmlfiles/$cid'";
				$tmp = $cqp->exec($cql);
				$varval = $cqp->exec("size ".ucfirst($varname));
			} else if ( $vartype == "calc" ) {
				$tit = $val['calc'];
				$varval = evalmath($tit);
			} else if ( $vartype == "count" ) {
				$tmp = $cqp->exec($cql);
				$varval = count(explode("\n", $tmp));
			};
			 
			$vars{$val['var']} = $varval;
			$tit = str_replace("'", "&quot;", "$varname: $tit");
			$maintext .= "<tr><th title='$tit'>{%{$val['display']}}<td>$varval";
		};
		$maintext .= "</table>";

	
		$maintext .= "<hr><p><a href='index.php?action=text&cid=$cid'>{%Text view}</a>";
	
	} else {

		

	}

function evalmath($equation) {
	global $vars; 
    $result = 0;
    // sanitize imput
    $equation = preg_replace("/[^a-z0-9+\-.*\/()%]/","",$equation);
    // convert alphabet to $variabel 
    while ( preg_match("/([a-z]+)/i", $equation, $matches) ) {
		$equation = str_replace($matches[1], $vars[$matches[1]]+0, $equation); 
    };
    if ( $equation != "" ){
        $result = @eval("return " . $equation . ";" );
    }
    if ($result == null) {
        throw new Exception("Unable to calculate equation");
    }
    return $result;
}


?>