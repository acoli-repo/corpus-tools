<?php

	$file = "Resources/bib.xml";

	$bib = simplexml_load_file($file);

	$disp = array (
		"talk" => "{#author} ({#year}) <b>{#title}</b>. Presented at <i>{#conference}</i>, {#date}, {#address}",
		"inproceedings" => "{#author} ({#year}) <b>{#title}</b>. In: <i>{#booktitle}</i>, {#address}",
		"incollection" => "{#author} ({#year}) <b>{#title}</b>. In: {#editor} (eds.) <i>{#booktitle}</i>, {#address}: {#publisher}",
		"article" => "{#author} ({#year}) <b>{#title}</b>. <i>{#journal}</i>, vol. <b>{#volume}</b>: {#pages}",
	);

	$maintext .= "<h1>Publications</h1>";
	foreach ( $bib->xpath("//item") as $item ) {
		$type = chv($item, 'type');
		$itemtxt = makeitem($item, $disp[$type] );
		$maintext .= "<div style='padding: 5px; text-indent: -20px; padding-left: 20px;'>$itemtxt</div>";
		$cnt++;
	};
	
	$maintext .= "<hr><p>$cnt publications";

	function chv ( $node, $child ) {
		$tmp = $node->xpath("./$child");
		if ( $tmp ) return current($tmp)."";
		$tmp = $node->xpath(".//$child");
		if ( $tmp ) return current($tmp)."";
		return "";		
	};

	function makeitem( $node, $template ) {
		if ( $template == "" ) $template = "{#author} ({#year}) <b>{#title}</b>";
		
		while ( preg_match("/{#([^}]+)}/", $template, $matches ) ) {
			$from = $matches[0];
			$fld = $matches[1];
			$to = chv($node, $fld);
			if ( $fld == "title" ) {
				$url = chv($node, "url");
				if ( $url ) $to = "<a href='$url'>$to</a>";
			} else if ( $fld == "author" || $fld == "editor" ) {
				$to = makename($to);
			};
			$template = str_replace($from, $to, $template);
		};
		
		return $template;
	};
	
	function makename ( $string ) {
		$names = "";		$sep = "";
	
		$list = explode(" and ", $string);
		
		foreach ( $list as $i => $name ) {
			if ( $i == count($list)-1 && $sep ) $sep .= " and ";
			if ( strpos( $name, "," ) == false ) $name = preg_replace("/^(.*) +(.*?)$/", "\\2, \\1", $name );
			$names .= "$sep$name";
			$sep = "; ";
		};
		
		return $names;
	};
	

?>