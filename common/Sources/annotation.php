<?php
	$annotation = $_GET['annotation'] or $annotation = $_SESSION['annotation'];
	if ( $annotation ) {
		$andef = simplexml_load_file("Annotations/{$annotation}_def.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		if ( ( !$settings['annotations'][$anid] || $settings['annotations'][$anid]['admin'] ) && !$username )  {
			fatal ( "Annotation data for <i>{$andef['name']}</i> are not publicly accessible" );
		};
		$result = $andef->xpath("//interp"); 
		foreach ( $result as $tmp ) { 
			$tagset[$tmp['key'].''] = $tmp;
			if ( $tmp['type'] != "long" ) {
				if ( $tmp['key'] == $dist ) $sel = "selected"; else $sel = "";
				$distlist .= "<option value='{$tmp['key']}' $sel>{$tmp['long']}</option>"; 
			};
		};
	};	
	$fileid = $_GET['cid'];
	if ( $fileid && !strstr($fileid, ".xml") ) $fileid .= ".xml";
	$xmlid = preg_replace("/\.xml$/", "", $fileid);
	$filename = "Annotations/{$annotation}_$fileid";

	if ( $_GET['query'] ) {
		list ( $fld, $val ) = split ( ":", $_GET['query'] );
		$sqtxt .= "<p>{$tagset[$fld]['long']} = '$val'";
		$squery = "[@$fld=\"$val\"]";
	};
	
	if ( $act && $act != "distribution" && !$username ) $act = ""; # Disable edit mode for non-users
	
	if ( !$annotation ) {
	
		$maintext .= "<h1>Annotations</h1>
			";
		foreach ( glob("Annotations/*_def.xml") as $filename ) {
			$anid = preg_replace( "/.*\/(.*?)_def\.xml/", "\\1", $filename );
			$andef = simplexml_load_file($filename, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
			$tit = $andef['name'];
			if ( ( $settings['annotations'][$anid] && !$settings['annotations'][$anid]['admin'] ) || $username )  {
				$maintext .= "<p><h2><a href='index.php?action=$action&annotation=$anid'>$tit</a></h2><p>".$andef->desc."</p>
					<p><a href='index.php?action=$action&annotation=$anid'>{%select}</a></p>";
				$some = 1; 
			};
		};
		if ( !$some ) $maintext .= "<p><i>{%No annotation schemes found}</i>";
	
	} else if ( $act == "save" && $fileid ) {
	
		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($fileid);

		foreach ( $_POST['toks'] as $key => $val ) {
			$sep = ""; $tt = "";
			foreach ( $val as $key2 => $val2 ) {
				$tt .= $sep."#".$key2; 
				$tmp = $ttxml->xml->xpath("//tok[@id='$key2']"); $tw = $tmp[0]['form'] or $tw = $tmp[0]."";
				$tws .= $sep.$tw; 
				$sep = " ";
			};
			$result = $anxml->xpath("//span[@id=\"$key\"]"); $segnode = $result[0];
			if ( $tt != $segnode['corresp'] ) {
				$_POST['sval'][$key]['corresp'] = $tt;
				$segnode[0] = $tws;
			};
		};
		
		foreach ( $_POST['sval'] as $key => $val ) {
			$result = $anxml->xpath("//span[@id=\"$key\"]"); $segnode = $result[0];
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = $fv;
			};
		};

		foreach ( $_POST['news'] as $key => $val ) {
			if ( $val['corresp'] == "" ) { continue; }
			$segnode = $anxml->addChild('span', $val['text']); unset($val['text']);
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = $fv;
			};
		};
		
		# Renumber the segments
		$result2 = $anxml->xpath("//span"); $sc = 1;
		foreach ( $result2 as $segnode ) {
			$segnode['id'] = "an-".$sc++;
		};
		file_put_contents($filename, $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 
	
	} else if ( $act == "tagset" ) {


		$maintext .= "<h1>{%{$andef['name']}}</h1>";
		$description = $andef->desc;
		$maintext .= "<p>$description</p>";

		foreach ( $andef->interp as $interp ) {
			
			if ( $interp['long'] ) $long = " ({$interp['long']})";
			$maintext .= "<h2>{$interp['display']}$long</h2>";
			$maintext .= "<p>".$interp->desc."</p>";
		
			if ( $interp->option ) {
				$maintext .= "<table>";
				foreach ( $interp->option as $option ) {
					$maintext .= "<tr><th>{$option['value']}<td>$option";
				};
				$maintext .= "</table>";
			} else {
				$maintext .= "<p><i>Free text field</i>";
			};
		
		};
		$maintext .= "<hr><p><a href='index.php?action=$action&annotation=$annotation'>To annotation list</a>";

	} else if ( $fileid && $act == "delete" ) {

		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		
		$sid = $_GET['sid'];
		$result = $anxml->xpath("//segment[@id=\"$sid\"]"); 
		$segnode = $result[0];	
		if ( !$segnode ) fatal("No such segment: $sid");
		unset($segnode[0][0]);

		# Renumber the segments
		$result = $anxml->xpath("//file");  $fc = 0;
		foreach ( $result as $fnode ) {
			$fc++; $sc = 1;
			$result2 = $fnode->xpath("./segment");
			foreach ( $result2 as $segnode ) {
				$segnode['id'] = $fc."-".$sc++;
			};
		};
		
		file_put_contents($filename, $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 

	} else if ( $fileid && $act == "redit" ) {

		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML($fileid);
		
		$sid = $_GET['id'];

		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$result = $anxml->xpath("//span[@id=\"$sid\"]"); 
		$segnode = $result[0];		
		$result = $segnode->xpath(".."); 
		$filenode = $result[0];		
		$toklist = explode ( " ", str_replace("#", "", $segnode['corresp'] ));
		$tks = max ( str_replace("w-", "", $toklist[0]) - 5, 1 );
		$tkf = str_replace("w-", "", $toklist[count($toklist)-1]) + 5;
		$toklisttxt = "<table>";
		for ( $i = $tks; $i <= $tkf; $i++ ) { 
			$tokid = "w-$i";
			if ( in_array($tokid, $toklist) ) $checked = "checked"; else $checked = "";
			$tmp = $ttxml->xml->xpath("//tok[@id=\"$tokid\"]"); $ttok = $tmp[0];
			if ($ttok) $toklisttxt .= "<tr><td><input type=checkbox name=toks[$sid][$tokid] $checked value=1><td>$tokid<td>$ttok<td style='color: #888888;'>".htmlentities($ttok->asXML());
		};
		$toklisttxt .= "</table>";
		
			$maintext .= "<h1>{%{$andef['name']}}</h1>
				<h2>Edit $sid</h2>
				
				<form action='index.php?action=$action&act=save&annotation=$annotation&cid=$fileid' method=post>
				<table>
				<tr><td><td><th>File<td>{$filenode['id']}
				<tr><td><td><th>Token list<td>$toklisttxt
				<tr><td><td><th>Word value<td>{$segnode}";
			foreach ( $tagset as $key => $val ) {
				$maintext .= "<tr><th>$key
					<th>{$tagset[$key]['display']}
					<th>{$tagset[$key]['long']}";
				$tmp = $andef->xpath("//interp[@key='$key']/option");
				$sval = $segnode[$key]; $odone = 0;
				if ( $tmp ) {	
					$optionlist = ""; $odone = 0;
					foreach ( $tmp as $option ) {
						$oval = $option['value'].""; 
						if ( $sval == $oval ) { $selected = "selected"; $odone = 1; } else $selected = ""; 
						if ( $oval == $option || !$option) $otxt = $oval; 
						else $otxt = "$oval: $option";
						$optionlist .= "<option value=\"$oval\" $selected>$otxt</option>";
					};
					if ( $sval && !$odone ) $optionlist = "<option value=\"$sval\" selected><i>$sval</i>: (invalid value)</option>$optionlist";
					$maintext .= "<td><select name='sval[$sid][$key]'><option value=\"\">[select]</option> $optionlist</select>$val</td>";
				} else {
					$maintext .= "<td><input name='sval[$sid][$key]' value='{$segnode[$key]}'></td>";
				};
			};
			$maintext .= "</table>
				<p><input type=submit value=Save> 
					<a href='index.php?action=$action&act=&annotation=$annotation&cid=$fileid'>cancel</a>
				</form>
				<p><a href='index.php?action=$action&act=delete&annotation=$annotation&sid=$sid&cid=$fileid'>delete segment</a>
				";
			
			$ctxt = $ttxml->context($toklist[0]);
			if ( $ctxt ) {	
				foreach ( $toklist as $wk ) $hllist .= "highlight('$wk', '#ffcccc');";
				$maintext .= "<hr><h2>Context</h2>$ctxt
					<script language=Javascript src=\"$jsurl/tokedit.js\"></script>
					<script language=Javascript>$hllist</script>
					";
			}
		
	} else if ( $act == "distribution" ) {
		
			$query = $_GET['query'] or $query = "//segment";
			$dist = $_GET['dist'];

			
			$maintext .= "<h1>{%{$andef['name']}}</h1>";
			$maintext .= "<h2>{%Distribution}</h2>
			
				<form action='index.php'>
					<input type=hidden name=action value='$action'>
					<input type=hidden name=act value='$act'>
					<input type=hidden name=annotation value='$annotation'>
					<p>{%Query}: <input name=query value='$query' size=80>
					<p>{%Distribute by}: <select name=dist>$distlist</select>
					<p><input type=submit value=Calculate>
				</form>
				";
			
			if ( $dist ) {
				$disttit = $tagset[$dist]['display']; # $disttit = $tagset[$dist]['long'] or 
				$maintext .= "<hr><table><tr><th>$disttit<th>{%Count}<th>{%Percentage}";
				$result = $anxml->xpath($query);  $tot = count($result);
				foreach ( $result as $tmp ) { 
					$dval = $tmp[$dist].'' or $dval = "(none)";
					$dd[$dval] = $dd[$dval] + 1;
				};
				arsort($dd);
				foreach ( $dd as $key => $val ) {
					$maintext .= "<tr><td>$key<td align=right>$val<td align=right>".sprintf("%0.2f", ($val/$tot)*100)."%";
				}; 
				$maintext .= "</table>";
			};
		$maintext .= "<hr>
			<p><a href='index.php?action=$action&annotation=$annotation'>{%to annotation list}</a>";

	} else if ( $fileid ) {
		# Show a specific annotation on a file
	
		$antxt = file_get_contents($filename);
		if ( !$antxt ) $antxt = "<spanGrp id=\"$xmlid\"></spanGrp>";
		$anxml = simplexml_load_string($antxt, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);

		require ("../common/Sources/ttxml.php");
		$ttxml = new TTXML();

		$maintext .= "<h1>{%{$andef['name']}}</h1>";
		$maintext .= "<h2>".$ttxml->title()."</h2>"; 
		$maintext .= $ttxml->tableheader(); 
		
		$description = $andef->desc;
		if ( $description ) $maintext .= "<p>$description</p><hr>";
 
		$maintext .= "
			<table width='100%'><tr><td width='50%' valign=top>".$ttxml->mtxt(0);
		$maintext .= "</td><td valign=top style='border-left: 1px solid #991000; padding-left: 20px;'>";
		if ( $sqtxt ) { $sqtxt .= " (<a href=\"index.php?action=$action&annotation=$annotation&cid=$fileid&act=$act\">reset</a>)"; };
		$maintext .= "$sqtxt
			<p>{%Move your mouse over the rows to see the corresponding text in the file}<hr>";
		if ( $act=="edit" ) $maintext .= "<form action='index.php?action=$action&annotation=$annotation&cid=$fileid&act=save' method=post>";
		$maintext .= "<table><tr><th>Text";
			foreach ( $tagset as $key => $val ) {
				$maintext .= "<th>{%{$val['display']}}</th>";
			};
			
		$xpath = "//span$squery";
		$result = $anxml->xpath($xpath); $sortrows = array();
		foreach ( $result as $segment ) {
			$sid = $segment['id'];
			$tokenlist = str_replace("#", "", $segment['corresp']);
			$newrow = "<tr onMouseOver=\"markout('$tokenlist')\" onMouseOut=\"unhighlight();\")\">";
			if ($username) 
				$newrow .= "<td><a href='index.php?action=$action&annotation=$annotation&act=redit&id=$sid&cid=$fileid'>".$segment."</a>";
			else 
				$newrow .= "<td>".$segment;
			foreach ( $tagset as $key => $val ) {
				if ( $act == "edit" ) {
					$newrow .= "<td><input name='sval[$sid][$key]' value='{$segment[$key]}'></td>";
				} else {
					$newrow .= "<td>{$segment[$key]}</td>";
				};
			};
			$newrow .= "</tr>"; array_push($sortrows, $newrow);
		};
		natsort($sortrows); $maintext .= join("\n", $sortrows);
		if ( $act == "edit" ) {
			for ( $i=1; $i<11; $i++ ) {
				$maintext .= "
				
					<tr style='display: none;' id='newrow-$i'>
						<td><input type=hidden name=\"news[$i][corresp]\" id=\"news[$i][corresp]\">
							<input name=\"news[$i][text]\" id=\"news[$i][text]\" style='border: none; font-size: 11pt;' readonly>";
				foreach ( $tagset as $key => $val ) {
					$maintext .= "<td><input name='news[$i][$key]' value=''></td>";
				};
				$maintext .= "</tr>";
			};
		};
		$maintext .= "</table>";
		if ( $act=="edit" ) $maintext .= "<input type=submit value=Save></form>
			<hr><p>Click words to create a new annotation element</p>
			<div id='newann' name='newann' style='display: none;'>
				<input id='newann-toklist' name='newann-toklist' readonly style='border: none;'>
				<input id='newann-wrdlist' name='newann-wrdlist' readonly style='border: none;'>
				<br><button onClick='makenewann()'>Create annotation</button> <button onClick='clearnewann()'>Clear</button>
			</div>
			<script language=Javascript src='$jsurl/standoff.js'></script>
			";
		$maintext .= "</td></tr></table>
		<hr>
		<p><a href='index.php?action=file&cid=$fileid'>{%to text mode}</a> &bull; <a href='index.php?action=$action&annotation=$annotation'>{%to annotation list}</a>";
		if ( $username && $act != "edit" ) $maintext .= " &bull; <span class=adminpart><a href='{$_SERVER['REQUEST_URI']}&act=edit'>{%edit mode}</a></span>"; 
		
		$maintext .= "
			<script language=Javascript>
				function markout ( list) { 
					var array = list.split(' ');
					for ( var i=0; i<array.length; i++ ) {
						highlight(array[i], '#ffcccc');
					};
				};
			</script>
		";
		
	} else {
		# Search though all the annotation files of the selected type
		
		$anfile = "<annotation>";
		foreach (glob("Annotations/{$annotation}_*.xml") as $filename) {
			$anffile = file_get_contents($filename);
			$anffile = str_replace('<?xml version="1.0"?>', "", $anffile);
			$anfile .= $anffile;
		};
		$anfile .= "</annotation>";
		$anxml = simplexml_load_string($anfile, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);


		$description = $andef->desc;
		$maintext .= "<h1>{%{$andef['name']}}</h1>
					<div>$description</div>";
		if ( !$squery ) $maintext .= "<p>{%Click on the column values to restrict the selection of annotations}";
		
		$result = $anxml->xpath("//span$squery"); 
		$maintext .= "$sqtxt<p>".count($result)." results</p>
			<hr>
			<table><tr><td><td><th>Text</th>";
			foreach ( $tagset as $key => $val ) {
				$maintext .= "<th>{%{$val['display']}}</th>";
			};
		foreach ( $result as $segment ) {
			$tmp = $segment->xpath('..'); $sfid = $tmp[0]['id'];
			$maintext .= "<tr>
				<td><a href='index.php?action=$action&annotation=$annotation&cid=$sfid&query={$_GET['query']}'>{$sfid}</a>
				<td><a href='index.php?action=$action&act=redit&annotation=$annotation&cid=$sfid&id={$segment['id']}'>edit</a>
				<td>".$segment;
			foreach ( $tagset as $key => $val ) {
				$code = $segment[$key];
				$tmp = $andef->xpath("//interp[@key='$key']/option[@value='$code']");
				if ( $tmp ) {
					$oval = $tmp[0]."";
					if ( $oval == $code || !$oval) $codetxt = $oval; 
					else $codetxt = "$code: $oval";
				} else $codetxt = $code;
				if ( $val['type'] == "long" ) {
					$maintext .= "<td>$codetxt</td>";
				} else {
					$maintext .= "<td><a href='index.php?action=$action&annotation=$annotation&query=$key:{$segment[$key]}' style='color: black'>$codetxt</a></td>";
				};
			};
		};
		$maintext .= "</table><hr>";
		$maintext .= "<a href='index.php?action=$action&act=distribution&annotation=$annotation'>{%view distribution}</a>";
		$maintext .= " &bull; <a href='index.php?action=$action&act=tagset&annotation=$annotation'>{%view tagset}</a>";
		if ( $squery ) $maintext .= " &bull; <a href='index.php?action=$action&annotation=$annotation'>{%reset selection}</a>";
	};

?>