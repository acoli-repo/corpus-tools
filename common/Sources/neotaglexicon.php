<?php

	// Read the neotag settings file to check the vocabulary (and other things)
	$paramsfile = $_GET['params'];
	if ( $paramsfile ) {
		foreach ( $settings['neotag']['parameters'] as $item ) {
			if ( $item['params'] == $paramsfile ) $params = $item;
		};
	} else if ( count($settings['neotag']['parameters']) == 0 ) {
		print "<p>No neotag parameter settings"; exit;
	} else if ( count($settings['neotag']['parameters']) == 1 ) {
		$params = array_shift($settings['neotag']['parameters']);
		$paramsfile = $params['params'];
	};	
	
	$maintext .= "<h1>NeoTag Lexicon</h1>";

	$query = $_GET['query'];
	if ($paramsfile) {
		$file = file_get_contents("$paramsfile");
		$paramsxml = simplexml_load_string($file);
		
		$maintext .= "<table>";
		foreach ( $params as $key => $val ) {
			$maintext .= "<tr><th>$key<td>$val";
		};
		$maintext .= "</table><hr>
		
		<form action='index.php'><input type=hidden name=action value='$action'><input type=hidden name=params value='$paramsfile'>
		<p>Query: <input size=60 name=query value=\"".preg_replace("/\"/", "&quot;", $query)."\"> <input type=checkbox name=xpath value=1> XPath query
		<input type=submit value=Query></form>";
		
	};
		
	if ( !$paramsxml ) {
	
		$maintext .= "<p>Select a parameters file from the list below
		
			<table>
			<tr><th>Filename<th>Restriction";
		
		foreach ( $settings['neotag']['parameters'] as $key => $item ) {
			if ( file_exists($item['params']) )
				$maintext .= "<tr><td><a href='index.php?action=$action&params={$item['params']}'>{$item['params']}</a><td>{$item['restriction']}";
		}; 
		$maintext .= "</table>";
	
	} else if ( $act == "tagcheck" && $settings['tagset']["positions"] ) {
		check_login();
		$maintext .= "<hr><h2>Tagset consistency</h2>
			<p>When using a position based tagset, all the tags that occur in your training corpus
				should also be described by your tagset. If not, something is wrong. Below is the 
				list of all unique tags in your training corpus, with a check about their status.
				
			<table>
			<tr><th>Tag<th>Count<th>Interpretation<th>Status";
				
		$sortarray = array();
		foreach ( $paramsxml->xpath("//tags/item") as $tok ) {
			$mfs = $tok["key"]."";
			$mainpos = $mfs[0]; $status = ""; $interpret = $settings['tagset']['positions'][$mainpos]['display'].";";
			for ( $i = 1; $i<strlen($mfs); $i++ ) {
				$let = $mfs[$i];
				if ( !$settings['tagset']['positions'][$mainpos] ) $status .= "Invalid main POS $mainpos; ";
				if ( !$settings['tagset']['positions'][$mainpos][$i][$let] ) {
					$status .= "Invalid $let in position $i for $mainpos; ";
					$interpret .= "?;";
				} else { $interpret .= $settings['tagset']['positions'][$mainpos][$i][$let]['display'].";"; };
			}; if ( !$status ) { $status = "<span style='color: #009900'>(ok)</span>"; };
			$interpret = preg_replace( "/;+$/", "", $interpret );
			$interpret = preg_replace( "/;;+/", ";", $interpret );
			array_push($sortarray, "<tr><td>$mfs<td style='text-align: right;'>{$tok['cnt']}<td>$interpret<td>$status");
		};	
		natsort($sortarray);
		$maintext .= join ( "\n", $sortarray );
		$maintext .= "</table>";
	
	} else if ( $query ) {	
	
		if ( $_GET['xpath'] ) {
			$xquery = $query;
		} else { 
			$xquery = "//lexicon/item[@key=\"{$query}\"]/tok";
		};
		$tmp = $paramsxml->xpath($xquery); 
		
		$maintext .= "<h2>Lexicon item: {$_GET['key']}</h2>";
		
		if ( $tmp ) {
			if ( $tmp[0]->getName() == "tok" ) {
				$maintext .= "<table>
					<tr><th>Count<th>Form<th>Tag<th>XML";
				foreach ( $tmp as $key => $val ) {
					$tmp2 = $val->xpath("ancestor::item"); $form = $tmp2[0]['key'];
					if ( $settings['tagset']['tagtype']['positions'] ) {
						$tagval = "<a href=\"index.php?action=tagset&act=analyze&tag={$val['key']}\">{$val['key']}</a>";
					} else $tagval = $val['key'];
					$maintext .= "<tr><td>{$val['cnt']}<td>$form<td>$tagval<td>".htmlentities($val->asXML());
				};
			} else if ( $tmp[0]->getName() == "item" ) {
				$maintext .= "<table>
					<tr><th>Form<th>XML";
				foreach ( $tmp as $key => $val ) {
					$maintext .= "<tr><td>{$val['key']}<td>".htmlentities($val->asXML());
				};
			} else {
				foreach ( $tmp as $key => $val ) {
					$maintext .= "<p>".htmlentities($val->asXML());
				};
			};
			
		};
		$maintext .= "</table>";
		if ( !$tmp ) $maintext .= "<p>No results for: <i>$xquery</i>";
	
	} else if ( $settings['tagset']["positions"] && $username ) {
		$maintext .= "<hr><p><a href='index.php?action=$action&params=$paramsfile&act=tagcheck'>check tagset consistency</a>";
	} else {
		$maintext .= print_r($setting['tagset'], 1);
	};


?>