<?php

	$annotation = $_GET['annotation'];
	if ( $annotation ) {
		$anfile = file_get_contents("Annotations/$annotation.xml");
		$anxml = simplexml_load_string($anfile, NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
		$result = $anxml->xpath("//tagset/tag"); 
		foreach ( $result as $tmp ) { 
			$tagset[$tmp['key'].''] = $tmp;
			if ( $tmp['type'] != "long" ) {
				if ( $tmp['key'] == $dist ) $sel = "selected"; else $sel = "";
				$distlist .= "<option value='{$tmp['key']}' $sel>{$tmp['long']}</option>"; 
			};
		};
	};	
	$fileid = $_GET['cid'];
			if ( $_GET['query'] ) {
				list ( $fld, $val ) = split ( ":", $_GET['query'] );
				$sqtxt .= "<p>{$tagset[$fld]['long']} = '$val'";
				$squery = "[@$fld=\"$val\"]";
			};
	
	if ( $act && $act != "distribution" && !$username ) $act = ""; # Disable edit mode for non-users
	
	if ( $act == "save" && $fileid && $annotation ) {
	
		
		foreach ( $_POST['sval'] as $key => $val ) {
			$result = $anxml->xpath("//segment[@id=\"$key\"]"); $segnode = $result[0];
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = $fv;
			};
		};

		foreach ( $_POST['news'] as $key => $val ) {
			if ( $val['tokens'] == "" ) { continue; }
			$result = $anxml->xpath("//file[@id=\"$fileid\"]"); $filenode = $result[0];
			$segnode = $filenode->addChild('segment', $val['text']); unset($val['text']);
			foreach ( $val as $fk => $fv ) {
				$segnode[$fk] = $fv;
			};
		};
		
		# Renumber the segments
		$result = $anxml->xpath("//file");  $fc = 0;
		foreach ( $result as $fnode ) {
			$fc++; $sc = 1;
			$result2 = $fnode->xpath("./segment");
			foreach ( $result2 as $segnode ) {
				$segnode['id'] = $fc."-".$sc++;
			};
		};
		
		file_put_contents("Annotations/$annotation.xml", $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 
	
	} else if ( $annotation && $act == "delete" ) {

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
		
		file_put_contents("Annotations/$annotation.xml", $anxml->asXML());
			print "Changes save. Reloading. 
				<script language=Javascript>top.location='index.php?action=$action&cid=$fileid&annotation=$annotation'</script>"; 

	} else if ( $annotation && $act == "redit" ) {
		
		$sid = $_GET['id'];
		$result = $anxml->xpath("//segment[@id=\"$sid\"]"); 
		$segnode = $result[0];		
		$result = $segnode->xpath(".."); 
		$filenode = $result[0];		
		
			$maintext .= "<h1>{%{$anxml['name']}}</h1>
				<h2>Edit $sid</h2>
				
				<form action='index.php?action=$action&act=save&annotation=$annotation'>
				<table>
				<tr><td><td><th>File<td>{$filenode['id']}
				<tr><td><td><th>Token list<td>{$segnode['tokens']}
				<tr><td><td><th>Word value<td>{$segnode}";
			foreach ( $tagset as $key => $val ) {
				$maintext .= "<tr><th>$key
					<th>{$tagset[$key]['display']}
					<th>{$tagset[$key]['long']}
					<td><input name='sval[$sid][$key]' value='{$segnode[$key]}'></td>";
			};
			$maintext .= "</table>
				<p><input type=submit value=Save>
				</form>
				<p><a href='index.php?action=$action&act=delete&annotation=$annotation&sid=$sid'>delete segment</a>
				";
		
	} else if ( $annotation && $act == "distribution" ) {
		
			$query = $_GET['query'] or $query = "//segment";
			$dist = $_GET['dist'];

			
			$maintext .= "<h1>{%{$anxml['name']}}</h1>";
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
	} else if ( $annotation ) {
		if ( $fileid ) {
			require ("../common/Sources/ttxml.php");
			$ttxml = new TTXML();
	
			$maintext .= "<h1>{%{$anxml['name']}}</h1>";
			$maintext .= "<h2>".$ttxml->title()."</h2>"; 
			$maintext .= $ttxml->tableheader(); 
			 
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
			$xpath = "//file[@id=\"".$ttxml->xmlid."\"]/segment$squery";
			$result = $anxml->xpath($xpath); $sortrows = array();
			foreach ( $result as $segment ) {
				$sid = $segment['id'];
				$newrow = "<tr onMouseOver=\"markout('{$segment['tokens']}')\" onMouseOut=\"unhighlight();\")\">";
				if ($username) 
					$newrow .= "<td><a href='index.php?action=$action&annotation=$annotation&act=redit&id=$sid'>".$segment."</a>";
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
							<td><input type=hidden name=\"news[$i][tokens]\" id=\"news[$i][tokens]\">
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
				<script language=Javascript src='http://alfclul.clul.ul.pt/teitok/Scripts/standoff.js'></script>
				";
			$maintext .= "</td></tr></table>
			<hr>
			<p><a href='index.php?action=file&cid=$fileid'>{%to text mode}</a> &bull; <a href='index.php?action=$action&annotation=$annotation'>{%to annotation list}</a>";
			if ( $username && $act != "edit" ) $maintext .= " &bull; <span class=adminpart><a href='{$_SERVER['REQUEST_URI']}&act=edit'>{%edit mode}</a></span>"; 
			
			$maintext .= "
				<script language=Javascript>
					function markout ( list) { 
						var array = list.split(',');
						for ( var i=0; i<array.length; i++ ) {
							highlight(array[i], '#ffaaaa');
						};
					};
				</script>
			";
			
		} else {
			$tmp = $anxml->xpath("/annotation/description"); $description = $tmp[0];
			$maintext .= "<h1>{%{$anxml['name']}}</h1>
						<div>$description</div>";
			if ( !$squery ) $maintext .= "<p>{%Click on the column values to restrict the selection of annotations}";
			$result = $anxml->xpath("//segment$squery"); 
			$maintext .= "$sqtxt<p>".count($result)." results</p>
				<hr>
				<table><tr><td><th>Text</th>";
				foreach ( $tagset as $key => $val ) {
					$maintext .= "<th>{%{$val['display']}}</th>";
				};
			foreach ( $result as $segment ) {
				# index.php?action=file&cid=$sfid&highlight={$segment['tokens']}
				$tmp = $segment->xpath('..'); $sfid = $tmp[0]['id'];
				$maintext .= "<tr><td><a href='index.php?action=$action&annotation=$annotation&cid=$sfid&query={$_GET['query']}'>{$sfid}</a>
					<td>".$segment;
				foreach ( $tagset as $key => $val ) {
					if ( $val['type'] == "long" ) {
						$maintext .= "<td>{$segment[$key]}</td>";
					} else {
						$maintext .= "<td><a href='index.php?action=$action&annotation=$annotation&query=$key:{$segment[$key]}' style='color: black'>{$segment[$key]}</a></td>";
					};
				};
			};
			$maintext .= "</table><hr>";
			$maintext .= "<a href='index.php?action=$action&act=distribution&annotation=$annotation'>{%view distribution}</a>";
			if ( $squery ) $maintext .= " &bull; <a href='index.php?action=$action&annotation=$annotation'>{%reset selection}</a>";
		};
	} else {
		$maintext .= "<h1>Annotations</h1>
			";
		foreach (glob("Annotations/*.xml") as $filename) {
			$some = 1; $anid = preg_replace( "/.*\/(.*?)\.xml/", "\\1", $filename );
			$maintext .= "<p><a href='index.php?action=$action&annotation=$anid'>".ucfirst($anid)."</a>";
		};
		if ( !$some ) $maintext .= "<p><i>{%No annotation schemes found}</i>";
	};

?>