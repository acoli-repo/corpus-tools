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
	
	# Load the tagset as well
	require ( "../common/Sources/tttags.php" );
	$tagset = new TTTAGS("", false);
	$settings['tagset'] = $tagset->tagset;
	
	$paramdef = array ( 
		"lemmatize" => "The form attribute used for lemmatization of OOV items",
		"tagform" => "The form attribute used for tagging",
		"tagpos" => "The attribute used as the POS tag",
		"checkform" => "When already partially treated, use this attribute to help choose the correct tag",
		"params" => "The filename of this parameter set",
		"restriction" => "Only XML files matching this restriction are used with this parameter set",
		"training" => "The folder(s) used for training the POS tagger",
		"formtags" => "The list of attributes assigned by the POS tagger (form level)",
	);
	
	if ( !$paramsfile ) {
	
		$maintext .= "
			<h1>NeoTag Parameter Settings</h1>
			<p>Below are the parameter setting for NeoTag
		
			<table>
			<tr><td><th>Filename<th>Restriction<th>Last Update";
		
		foreach ( $settings['neotag']['parameters'] as $key => $item ) {
			
			$lines = shell_exec("head {$item['params']}");
			if ( preg_match ("/created=\"(.*?)\"/", $lines, $matches ) ) $lastupdate = $matches[1];
			
			// if ( file_exists($item['params']) )
				$maintext .= "<tr>
					<td><a href='index.php?action=$action&params={$item['params']}'>select</a>
					<td>{$item['params']}
					<td>{$item['restriction']}
					<td>$lastupdate 
					";
		}; 
		$maintext .= "</table>";

	} else if ( $act == "lexicon" ) {
	
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
		
			<form action='index.php'><input type=hidden name=action value='$action'><input type=hidden name=act value='lexicon'><input type=hidden name=params value='$paramsfile'>
			<p>Query: <input size=60 name=query value=\"".preg_replace("/\"/", "&quot;", $query)."\"> <input type=checkbox name=xpath value=1> XPath query
			<input type=submit value=Query></form>";
		
		};
		
		if ( $query ) {	
	
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
		};	
	
			$maintext .= "<hr><p><a href='index.php?action=$action&params=$paramsfile'>Back to parameter definitions</a>";
	
	} else if ( ( $act == "tag" || !$act ) && $_GET['cid'] ) {
	
		// Update this settings file
		check_login();
		
		$cid = $_GET['cid'];
		if ( !file_exists($cid) ) { fatal ( "File does not exist: $cid" ); };
		
		$exec = $settings['bin']['neotag'] or $exec = "/usr/local/bin/neotagxml";
		
		if ( $params['pid'] ) $pid = "--pid='{$params['pid']}'";

		$cmd = "$exec --xmlfile=$cid --verbose $pid";

		$response = shell_exec($cmd);
		
		$maintext .= "<h1>File tagged</h1>
			<p>Tagging command: $cmd 
			<p>Reponse text: 
			<pre>$response</pre>
		
		" ;
			$maintext .= "<hr><p><a href='index.php?action=file&cid=$cid'>Back to text</a>";
	
	} else if ( $act == "update" ) {
	
		// Update this settings file
		check_login();
		
		$exec = $settings['bin']['neotagtrain'] or $exec = "/usr/local/bin/neotagtrain";
		
		if ( $params['pid'] ) $pid = "--pid='{$params['pid']}'";
		$cmd = "$exec --verbose $pid";
		$response = shell_exec($cmd);
		
		$maintext .= "<h1>NeoTag Parameter Seting Updated</h1>
			<p>Update command: $cmd 
			<p>Reponse text: 
			<pre>$response</pre>
		
		" ;
			$maintext .= "<hr><p><a href='index.php?action=$action&params=$paramsfile'>Back to parameter definitions</a>";
	
	} else if ( $act == "tagcheck" && $settings['tagset']["positions"] ) {
	
		// Position-based tagset check
		check_login();

		$file = file_get_contents($paramsfile);
		$paramsxml = @simplexml_load_string($file);

		$maintext .= "<h1>NeoTag Parameter Set</h1>";
		$maintext .= "<h2>Tagset consistency</h2>
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

			$maintext .= "<hr><p><a href='index.php?action=$action&params=$paramsfile'>Back to parameter definitions</a>";
	
	} else {
	
		$file = file_get_contents($paramsfile);
		$paramsxml = @simplexml_load_string($file);
		
		
		$maintext .= "<h1>NeoTag Parameter Set</h1>
					
			<table>";
			foreach ( $params as $key => $val ) {
				$txt = $paramdef[$key]."";
				$maintext .= "<tr><th>$key<td>$val<td style='padding-left: 30px;'><i>{$txt}</i>";
			};
			if ($paramsxml) {
				$maintext .= "<tr><th>last update<td>{$paramsxml[0]['created']}";
				$maintext .= "<tr><th>training size<td>{$paramsxml[0]['cnt']}";
				$maintext .= "<tr><th>lexicon size<td>".count($paramsxml->{"lexicon"}[0]->{"item"});
				$maintext .= "<tr><th>tagset size<td>".count($paramsxml->{"tags"}[0]->{"item"});
				$maintext .= "</table>";
			} else {
				$maintext .= "</table>";
				$maintext .= "<p><div style='color: #992000; font-weight: bold;'>Failed to load $paramsfile - probably a corrupted file, please train again</div>";
			};
			
			$maintext .= "<hr><p><a href='index.php?action=$action&params=$paramsfile&act=lexicon'>Search lexicon for this parameter set</a>";
			if ( $params['training'] && ( file_exists("/usr/local/bin/neotagtrain") || $settings['neotag']['exec'] )  ) {
				$maintext .= "<p><a href='index.php?action=$action&params=$paramsfile&act=update'>Update this parameter set</a>";
			};
			if ( $settings['tagset']["positions"]  ) $maintext .= "<p><a href='index.php?action=$action&params=$paramsfile&act=tagcheck'>Check tagset consistency for this parameter set</a>";
			if ( count($settings['neotag']['parameters']) > 1  ) $maintext .= "<p><a href='index.php?action=$action'>Switch parameter set</a>";
			
	};


?>