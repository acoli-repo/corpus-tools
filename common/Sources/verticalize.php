<?php
	// Script to view and edit from a verticalized view on an XML file
	// (c) Maarten Janssen, 2015

	check_login();
	set_time_limit(60);

	require("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML();
	$fileid = $ttxml->fileid;
	$xmlid = $ttxml->xmlid;
	$xml = $ttxml->xml;
	
	if ( !$xml ) { fatal ("Failing to read/parse $fileid"); };

	$result = $xml->xpath("//title"); 
	$title = $result[0];

	if ( $act == "edit" && $username ) $editable = true;


	if ( $act == "save" && $username ) {
		foreach ( $_POST as $key => $val ) {
			if ( $settings['xmlfile']['pattributes']['forms'][$key] || $settings['xmlfile']['pattributes']['tags'][$key] || $key == "pform" ) {
				foreach ( $val as $wid => $nval ) {
					$result = $xml->xpath("//*[@id='$wid']"); 
					$stoken = $result[0]; # print_r($token); exit;
					if ( $key == "pform" ) {
						$stoken[0] = $nval;
					} else if ( $nval != "" || $stoken[$key] != "" ) {
						$stoken[$key] = $nval;
					};
				};
			};
		};
		saveMyXML($xml->asXML(), $fileid);
		$maintext .= "<hr><p>Your text has been modified - reloading";
		header("location:index.php?action=file&id=$fileid&tid=$tokid$slnk");
		
	} else if ( $act == "define" ) {

		$maintext .= "<h1>Define columns</h1>
			<p>You can select below which column to display in the verticalized view.
			
			<form action='index.php?action=$action&act=edit&cid=$fileid' method=post>
				<table>
				<tr><th>Edit<th>View<th>Column name";
		
		foreach ( $settings['xmlfile']['pattributes']['forms'] as $key => $item ) {
			if ( $key == "pform" ) $editform = ""; // Turned off editing of pfrom in verticalized view since it deletes internal nodes (or gets complicated)
			else $editform = "<input type=checkbox name='edit[$key]' value=1> ";
			$maintext .= "<tr>
				<td>$editform
				<td><input type=checkbox name='view[$key]' value=1> 
				<td>{$item['display']}
			";
		};
		$maintext .= "<tr><td colspan=10>
				<p><input type=checkbox name=inherit value='1'> Show inhereted forms
				<hr>";	
		foreach ( $settings['xmlfile']['pattributes']['tags'] as $key => $item ) {
			$maintext .= "<tr>
				<td><input type=checkbox name='edit[$key]' value=1> 
				<td><input type=checkbox name='view[$key]' value=1> 
				<td>{$item['display']}
			";
		};
		$maintext .= "<tr><td colspan=10><hr>";	

		$maintext .= "<tr><td colspan=10>Start at token: <input name='start' value='1' size=4>";	
		$maintext .= "<tr><td colspan=10>Tokens per page: <select name='max'>
			<option value=100 selected>100</option>
			<option value=250>250</option>
			<option value=500>500</option>
			</select> <i>(browser restriction make editing more than 1000 items at a time impossible)</i>";	
		
		$maintext .= "<tr><td colspan=10><hr>";	

		$maintext .= "
			<tr><td colspan=10>Optionally, define an XPath query to select tokens:
				<textarea name=xquery style=\"heigt:20pxl width: 200px;\">//tok[.]</textarea>
			<tr><td colspan=10><hr>
			";	
		
		$maintext .= "
			</table>
			<p><input type=submit value='Go'>
			</form>";

			
			
	} else if ( $act == "raw" ) {

		$maintext .= "<h1>Raw Verticalized Corpus View</h1>
			<h2>XML File: $fileid</h2>
			<h3>$title</h3>";
			
		$result = $xml->xpath("//tok"); 
		foreach ( $result as $node ) {
			$maintext .= "<p>".htmlentities($node->asXML());
		};


	} else {
		$maintext .= "<h1>Verticalized Corpus View</h1>
			<h2>XML File: $fileid</h2>
			<h3>$title</h3>";
		
		if ( $_POST['view'] ) {
			$showfields = array_keys($_POST['view']);
			$editfields = array_keys($_POST['edit']);
			# foreach ( $editfields as $fld ) if ( !in_array($fld, $showfields) ) array_push($showfields,$fld);
		} else {
			$toshow = $_GET['showfields'] or $toshow = $settings['xmlfile']['vertfields'] or $toshow = "pform,nform";
			$showfields = explode ( ",", $toshow );
			if ( $editable ) {
				$toedit = $_GET['editfields'] or $toedit = "nform,lemma,pos";
				$editfields = explode ( ",", $toedit );
			};
		};	
		unset($editfields['pform'])
		$max = $_POST['max'] or $max = $_GET['max'] or $max = 100;
		$start = $_POST['start'] or $start = $_GET['start'] or $start = 1;
		$start--;
		
		if ( $_POST['xquery'] ) {
			$xquery = $_POST['xquery'];
		} else if ( $_GET['page'] ) {
			$xquery = "//pb[@n=\"{$_GET['page']}\"]/following-sibling::tok";
		} else {
			$xquery = "//tok";
		};

		if ( $debug ) $maintext .= "<p>XQuery: $xquery<hr>";

		if ( $editable ) {
			$maintext .= "<form action='index.php?action=$action&act=save&cid=$fileid' method=post>
			<input type=hidden name=cid value='$fileid'>";
		};
		
		
		$maintext .= "<hr>
			<table>
			<tr><td>";
		foreach ( $showfields as $fld ) {
			$tittxt = $settings['xmlfile']['pattributes']['forms'][$fld]['display'] or 
			$tittxt = $settings['xmlfile']['pattributes']['tags'][$fld]['display']; 
			$maintext .= "<th>$tittxt";
		};
		if ( $username && is_array($editfields) ) foreach ( $editfields as $fld ) {
			$tittxt = $settings['xmlfile']['pattributes']['forms'][$fld]['display'] or 
			$tittxt = $settings['xmlfile']['pattributes']['tags'][$fld]['display']; 
			$maintext .= "<th>$tittxt";
		};
		
			$result = $xml->xpath($xquery); 
			foreach ( $result as $node ) {
				$cnt++;
				if ( $start && $cnt <= $start ) { continue; };
				if ( $node[$postag] || $_GET['show'] != "tagged" ) {
					$maintext .= "\n<tr><td><a target=new href='index.php?action=tokedit&cid=$fileid&tid={$node['id']}'>".$node['id'].'</a>';
					foreach ( $showfields as $fld ) {
						if ( $fld == "pform" ) {
							$val = $node->asXML();
						} else $val = $node[$fld];
						
						if ( $val == "" && ( $_POST['inherit'] || $_GET['inherit'] ) && $settings['xmlfile']['pattributes']['forms'] ) $val = forminherit($node, $fld);
						$coldir = $settings['xmlfile']['pattributes']['forms'][$fld]['direction'];
						if ( $coldir ) $colstyle = " style='direction: $coldir; padding-right: 10px;'"; else $colstyle = "";
						
						$maintext .= "<td$colstyle>$val";
					};
					if ( is_array($editfields) )
					foreach ( $editfields as $fld ) {
						if ( $fld == "pform" ) $val = $node->asXML(); // TODO: This is ... not ideal, better solution?
						else $val = $node[$fld]; 
						$nid = $node['id'];
						$maintext .= "<td><input name='{$fld}[{$nid}]' value=\"$val\">";
					};

					# Show dtoks
					$result2 = $node->xpath("dtok"); 
					foreach ( $result2 as $dnode ) {
						$maintext .= "\n<tr><td>{$dnode['id']}"; # <td>{$dnode['form']}<td>".$dnode['lemma']."<td>".$dnode[$postag]."<td>".$dnode['mfs'];
						foreach ( $showfields as $fld ) {
							if ( $fld == "pform" ) $val = $dnode['form']; # dnodes do not have an innerHTML
							else $val = $dnode[$fld];
							if ( $val == "" && $_POST['inherit']  && $settings['xmlfile']['pattributes']['forms'] ) $val = forminherit($dnode, $fld);
							$maintext .= "<td>$val";
						};
						foreach ( $editfields as $fld ) {
							if ( $fld == "pform" ) $val = $dnode."";
							else $val = $dnode[$fld];
							$nid = $dnode['id'];
					
							$maintext .= "<td><input name='{$fld}[{$nid}]' value=\"$val\">";
						};
					};		
				};
				if ( $max > 0 && $cnt > ($max+$start)-1 ) {
					break;
				};
			};	
			$maintext .= "</table>";
			
			if ( $editable ) $maintext .= "<p><input type=submit value=Save></form>";
			
			$maintext .= "<hr>";

			// Add a session logout tester
			$maintext .= "<script language=Javascript src='$jsurl/sessionrenew.js'></script>";
			
		
			if ( $_GET['show'] == "all" ) $maintext .= "<a href='index.php?action=$action&cid=$fileid&form=$showform&show='>show only tagged tokens</a>";
			else $maintext .= "<a href='index.php?action=$action&cid=$fileid&form=$showform&show=all'>show all tokens</a>";
			$maintext .= " &bull; <a href='index.php?action=file&cid=$fileid'>back to view mode</a>";
			$maintext .= " &bull; <a href='index.php?action=$action&act=raw&cid=$fileid'>verticalized XML</a>";
			$maintext .= " &bull; <a href='index.php?action=$action&act=define&cid=$fileid'>define columns</a>";

	};
	
?>