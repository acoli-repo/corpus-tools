<?php

# Tool to display geographical locations of documents onto Google Maps
# Uses CQP as an index for the locations, with an assumed <text_geo> 
# Consisting of lat and lng separated by a space
# (c) Maarten Janssen, 2016
  
$geofld = getset('geomap/cqp/geo', "geo");
$geoplace = getset('geomap/cqp/place', "place");
$ftit = getset('geomap/cqp/title', "id");
$geosep = getset('geomap/separator', ' ');

$docname = getset('geomap/documents',  "documents");
$pagtit = getset('geomap/title', "Document Map");

$apikey = getset('geomap/apikey');  

$collist = array( 'blue', 'red', 'purple', 'violet', 'pink', 'orange-dark', 'orange', 'blue-dark', 'cyan', 'green-dark', 'green', 'green-light', 'black' );

$markertype = $_GET['marker'] or $markertype = getset('geomap/markertype');

if ( $markertype == "pie" || $markertype == "cluster"  ) {
	if ( !is_array($settings['geomap']) ) $settings['geomap'] = array();
	$settings['geomap']['cluster'] = 1; 
};
  
if ( getset('geomap/cluster') ) {
	$cluster = "	    
		<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.css\"/>
	    <link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.Default.css\"/>
		<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/leaflet-extra-markers@1.0.6/dist/css/leaflet.extra-markers.min.css\"/>
		<link rel=\"stylesheet\" href=\"$jsurl/clusterpies.css\"/>
		<script src=\"https://cdn.jsdelivr.net/npm/leaflet-extra-markers@1.0.6/src/assets/js/leaflet.extra-markers.min.js\"></script>
		<script src=\"https://unpkg.com/leaflet.markercluster@1.3.0/dist/leaflet.markercluster-src.js\"></script>
		<script language=Javascript>var cluster = {$settings['geomap']['cluster']}; var markertype = '{$markertype}';</script>
		<script src=\"https://d3js.org/d3.v3.min.js\" charset=\"utf-8\"></script>
	";
} else if ( $markertype ) {
	$cluster = "
		<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/leaflet-extra-markers@1.0.6/dist/css/leaflet.extra-markers.min.css\"/>
		<script src=\"https://cdn.jsdelivr.net/npm/leaflet-extra-markers@1.0.6/src/assets/js/leaflet.extra-markers.min.js\"></script>
		<script language=Javascript>var markertype = '{$markertype}';</script>
	";
};

if ( $act == "xml" ) {

	require ("$ttroot/common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$fileid = $ttxml->fileid;
	
	$geoxp = $settings['geomap']['xml']['node'] or $geoxp = "//*[geo]";
	$geoll = $settings['geomap']['xml']['geo'] or $geoll = "./geo";
	$geoname = $settings['geomap']['xml']['name'] or $geoname = "./name";
	$geodesc = $settings['geomap']['xml']['desc'] or $geodesc = "./desc";

	$maintext .= "<h1>{%Geographical Locations}</h1>";
	$maintext .= $ttxml->tableheader();
	
	$maintext .= "<ul>";
	foreach ( $ttxml->xpath($geoxp) as $geonode ) {

		$geo = current($geonode->xpath($geoll))."";  
		$place = current($geonode->xpath($geoname)).""; 
		
		if ( preg_match( "/^=(.*)$/", $geodesc, $matches ) ) $desc = $matches[1];
		else $desc = current($geonode->xpath($geodesc))."";  
	
		list ( $lat, $lng ) = explode ( $geosep, $geo );
		$maintext .= "<li>$place"; if ( $desc ) $maintext .= ": $desc";
		
		$descs[$geo] .= "<p>$desc</p>"; $desctxt = $descs[$geo];
		if ( $lng != "" && $lat != "" ) $jsonpoints[$geo] = "{ \"lat\": \"$lat\", \"lng\": \"$lng\", \"location\": \"$place\", \"cnt\": 1, \"desc\": \"$desctxt\" }";
		
	};
	$maintext .= "</ul>";
	$jsondata = "[ ".join(", ", array_values($jsonpoints))." ]";

	// Draw the actual map
	$maintext  .= "
	<div id=\"mapdiv\" class=\"mapdiv\" style='width: 100%; height: 600px;'></div>
	<script>
	  $moresettings
	  var defzoom = 8;
	  var jsondata = '$jsondata';
	  var doctxt = '{%$docname}';
	</script>
	<script src=\"$jsurl/geomap.js\"></script>
	<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.css\"/>
	<script src=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.js\"></script>
	<script>
	  initMap();
	</script>
	<hr><p><a href='index.php?action=file&cid=".$ttxml->fileid."'>{%back to view}</a></p>";
	
} else if ( $act == "view" ) {


	// Since this dependends on text_geo, make sure it exists
	if ( !file_exists("cqp/text_$geofld.rng") ) {
		fatal ( "Geodistribution is only available with a <i>$geofld</i> attribute on &lt;text&gt; in CQP." );
	};

	include ("$ttroot/common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus
	$cqp->exec("set PrettyPrint off");


	# View all the documents of a given location
	$location = $_GET['location'] or $location = $_GET['lat'].' '.$_GET['lng'];
	$place = $_GET['place'];

	$cql = $_GET['cql'];
	if ( $cql != 'Matches = <text_geo != "_"> []' && $cql != "" ){

		// In case we have a (set of) CQL query - first load the results
		$cqpp = explode ( "||", urldecode($cql) );
		$cqpptit = explode ( "||", urldecode($_GET['cqlname']) );
		foreach ( $cqpp as $i => $cql ) { 
			if ( strstr($cql, "<text" ) ) $txttype[$i] = 1; else $txttype[$i] = 0; 
			if ( !strstr($cql, "Matches" ) ) $cql = "Matches = $cql"; 
			$cqp->exec($cql); 

			$cqpquery = "group Matches match text_id by match text_$geofld";
			$results = $cqp->exec($cqpquery); 

			$sep = "";
			foreach ( explode ( "\n", $results ) as $line ) {	
				list ( $geo, $id, $cnt ) = explode ( "\t", $line );
				if ( $geo = $location ) {	
					$docmatch[$id][$i] = $cnt;
				};
			};

			$cqlname = $cqpptit[$i] or $cqlname = $_SESSION['myqueries'][urlencode($cql)] or $cqlname = $cql;
			$display = htmlentities($cqlname);
			$cqptit .= "<tr><td title='$cqpquery'><a href='index.php?action=cqp&cql=$cqpquery'><span style='color: {$collist[$i]}'>&#9641;</span><td>$display</a></tr>";
			$cqlname = preg_replace("/\"/", "&quot;", $cqlname);
			$cqpquery = preg_replace("/\"/", "&quot;", $cqpquery);
			$cqpjson .= "{\"set\": $i, \"name\": \"$cqlname\", \"query\": \"$cqpquery\"},";

		}; 
		$cqptit = "<table style='display: none;'><tr><td><a href='index.php?action=multisearch&act=stored'>{%edit}</a> {%Search Query}:  </td><td><table id='cqplegend'>$cqptit</table></table></p>";
		//$cqptit = "<table><tr><td>{%Search Query}: </td><td>$cqptit</table><hr>";
		if ( $cqpjson ) $cqpjson = "var cqpjson = [$cqpjson];";
		$showall = "<a href='index.php?action=geomap&act=view&place={$_GET['place']}&lat={$_GET['lat']}&lng={$_GET['lng']}'>{%show all}</a>";
	};
	
	$cqpquery = "Matches = <text_$geofld = \"$location\"> []";
	$cqp->exec($cqpquery);

	$size = $cqp->exec("size Matches") * 1;

	$maintext .= "<h1>{%Documents from} $place</h1>
		$cqptit
		";

	$tsize = 0;
	if ( $size > 0 ) {
		$cqpquery = "tabulate Matches match text_id, match text_$ftit";
		$results = $cqp->exec($cqpquery); 

		$table .= "<table>";
		foreach ( explode ( "\n", $results ) as $line ) {	
			list ( $fileid, $title ) = explode ( "\t", $line );
			if ( preg_match ( "/([^\/]+)\.xml/", $fileid, $matches ) ) {	
				$cid = $matches[1];
				if ( $title == $fileid || $title == "" ) $title = $cid;
				
				$matchcnt = "";
				foreach ( $docmatch[$fileid] as $i => $cnt ) {
					if ( !$txttype[$i] ) $cnttxt = $cnt; else $cnttxt = "&nbsp;";
					$tcnt += $cnt;
					$matchcnt .= "<span style='background-color: {$collist[$i]}; color: white; font-size: 10px; padding-left: 3px; padding-right: 3px;'>$cnttxt</span> ";
				};
				if ( !$docmatch || $matchcnt ) {
					$table .= "<tr><td>$matchcnt<td><a href='index.php?action=file&cid=$cid&cql={$_GET['cql']}'>$title</a></tr>";
					$tsize ++;
				};
			};
		};
		$table .= "</table>";
	};
	
	if ( $tsize < $size ) { 
		$ofsize = " (of $size - $showall)";
	};
	if ( $tsize > 0 ) {
		$maintext .= "
			<p>$tcnt matches in $tsize {%$docname} $ofsize</p><hr>
			$table
			";

	} else {
		$maintext .= "<p><i>{%No results found}</i></p> $tsize";
	};
	

} else {

	// Since this dependends on text_geo, make sure it exists
	if ( !file_exists("cqp/text_$geofld.rng") ) {
		fatal ( "Geodistribution is only available with a <i>$geofld</i> attribute on &lt;text&gt; in CQP." );
	};
	
	include ("$ttroot/common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus
	$cqp->exec("set PrettyPrint off");

	if ( $_GET['cql'] ) {
		
		$cqpquery = $_GET['cql']; 
		$cqpp = explode ( "||", $cqpquery );
		$cqpptit = explode ( "||", urldecode($_GET['cqlname']) );
		$cqptit = $_GET['cqptit'];
		if ( $cqptit != "" ) {
			$cqptit = "<tr><td>$cqptit";
		} else {
			if ( count($cqpp) == 1 ) $cqptit .= "<a href='index.php?action=cqp&cql=$cqpquery'>{%view}</a> ".htmlentities($cqpquery);
			else {
				foreach ( $cqpp as $i => $cql ) { 
					$tmp = trim(urlencode($cql));
					$cqlname = $cqpptit[$i] or $cqlname = $_SESSION['myqueries'][$tmp]['name'] or $cqlname = $_SESSION['myqueries'][$tmp]['display'] or $cqlname = $cql;
					$display = htmlentities($cqlname);
					$cqptit .= "<tr><td title='$cqpquery'><a href='index.php?action=cqp&cql=$cqpquery'><span style='color: {$collist[$i]}'>&#9641;</span><td>$display</a></tr>";
					$cqlname = preg_replace("/\"/", "&quot;", $cqlname);
					$cqpquery = preg_replace("/\"/", "&quot;", $cqpquery);
					$cqpjson .= "{\"set\": $i, \"name\": \"$cqlname\", \"query\": \"$cqpquery\"},";
				};
			};
		};
		$cqptit = "<table style='display: none;'><tr><td><a href='index.php?action=multisearch&act=stored'>{%edit}</a> {%Search Query}:  </td><td><table id='cqplegend'>$cqptit</table></table></p>";
		// $cqptit = "<table><tr><td>{%Search Query}: </td><td>$cqptit</table></p>";
		if ( $cqpjson ) $cqpjson = "var cqpjson = [$cqpjson];";
		
		if ( !strstr("<text", $cqpquery ) ) { 
			// TODO: for (probably) word-based results, we should have pins show counts
			// $docname = "results";
		};

	} else if ( $_POST['myqueries'] ) {
		
		$cqpp = array(); $i = 0; $sep = "";
		foreach ( $_POST['myqueries'] as $cql => $val ) {
			$sq = $_SESSION['myqueries'][$cql];
			if ( !$sq ) $sq = $val;
			$cqlt = $sq['cql']; 
			$display = $sq['name'] or $display = $sq['display'] or $display = $cqlt;
			if ( $display == "" ) continue;
			if ( preg_match("/^\d+$/", $cql) ) {
				$cql = $cqlt;
				$_GET['cqltit'] .= $display.$cql;
			};
			if ( strstr($cql, "%3D") ) 
				$cql = urldecode($cql);
			$_GET['cql'] .= $sep.$cql; $_GET['cqlname'] .= $sep.$display; $sep = "||";
			$cqptit .= "<tr><td title='$cql'><a href='index.php?action=cqp&cql=$cql'><span style='color: {$collist[$i]}'>&#9641;</span><td>$display</a></tr>";
			$cqlname = preg_replace("/\"/", "&quot;", $display);
			$cqpjson .= "{\"set\": $i, \"name\": \"$cqlname\", \"query\": \"".preg_replace("/\"/", "&quot;", $cqlt)."\"},";
			array_push($cqpp, $cql);
			$i++;	
		};
		
		$direct = "index.php?action=$action&cql={$_GET['cql']}&cqlname={$_GET['cqlname']}";

		$cqptit = "<table style='display: none;'><tr><td><a href='index.php?action=multisearch&act=stored'>{%edit}</a> {%Search Query}:  </td><td><table id='cqplegend'>$cqptit</table></table></p>";
		if ( $cqpjson ) $cqpjson = "var cqpjson = [$cqpjson];";
		
	} else {
		$cqpquery = "Matches = <text_$geofld != \"_\"> []"; # TODO: This should become "" again
		$cqpp = array($cqpquery);
		$bottomactions = $bsep."<a href='index.php?action=multisearch&act=map'>{%Search on map}</a>"; $bsep = " &bull; ";
	};	
	
	unset($tot); unset($jsonpoints); 
	foreach ( $cqpp as $i => $cql ) { 
		if ( strstr($cql, "<text" ) ) $txttype[$i] = 1; else $txttype[$i] = 0; 
		if ( !strstr($cql, "Matches" ) ) $cql = "Matches = $cql"; 
		$cqp->exec($cql); 

		$size = $cqp->exec("size Matches");

		// First read the place name for each text_geo
		$cqpquery = "group Matches match text_$geoplace by match text_$geofld";
		$results = $cqp->exec($cqpquery); 
		foreach ( explode ( "\n", $results ) as $line ) {	
			list ( $geo, $name, $cnt ) = explode ( "\t", $line );
			$names[$geo] = $name;
		};
		
		$cqpquery = "group Matches match text_id by match text_$geofld";
		$results = $cqp->exec($cqpquery); 

		$sep = ""; 
		foreach ( explode ( "\n", $results ) as $line ) {	
			list ( $geo, $cid, $cnt ) = explode ( "\t", $line );
			list ( $lat, $lng ) = explode ( $geosep, $geo );
			$name = $names[$geo];
			$name = htmlentities($name, ENT_QUOTES);
			$lat = preg_replace("/,.*/", "", $lat);
			$lng = preg_replace("/,.*/", "", $lng);
			$cnt += 0;
			if ( $lat != "" && $lat != "_" && $lng != "" && $lat != "" ) {
				$tot[$geo][$i] += $cnt; 
				$dcnt[$geo][$i] += 1;
				$cnttxt = ""; $sep = "";
				foreach ( $tot[$geo] as $mset => $mcnt ) { 
					$mdoc = $dcnt[$geo][$mset];
					$cnttxt .= $sep."$mset:$mdoc:$mcnt"; $sep = ","; 
				};
				$jsonpoints[$geo] = "{ \"lat\": \"$lat\", \"lng\": \"$lng\", \"location\": \"$name\", \"cnt\": \"$cnttxt\" }";
			} else if ( $username && $line ) {
				$cid2 = str_replace('xmlfiles/', '', $cid);
				$geowrong .= "<tr><td>$cid2<td><a target=edit href='index.php?action=header&act=edit&cid=$cid2&amp;tpl=edit'>$geo</a>";
			};
		};
		if ( $geowrong ) {
			$bottomtext .= "<HR><p class=warning>Warning: the following GEO coordinates are not in correct format: (expecting \"NUM{$geosep}NUM\")</p> <table>$geowrong</table>";
		};
		
	};
	$pointlist = "";
	if ( is_array($jsonpoints) ) {
		$pointlist = join(", ", array_values($jsonpoints)); 
	};
    $jsondata .= "[ $pointlist ]";
	
	if ( getset('geomap/zoom') ) $moresettings .= "var defzoom = {$settings['geomap']['zoom']};";
	if ( getset('geomap/startpos') != '' ) {
		list ( $lat, $lng ) = explode ( $geosep, getset('geomap/startpos') );
		if ( !$lng ) {
			$msg = "A configuration error has occurred";
			if ( $username ) $msg = "Default position not correctly defined (expecting \"NUM{$geosep}NUM\"): ".getset('geomap/startpos');
			fatal($msg);
		};
		$moresettings .= " var defpos = {lat: $lat, lng: $lng };";
	};

	$fileheader = getlangfile("geomaptext", "edit");
	
	if ( $settings['geomap']['areas'] ) {
		$areaswitch = "<p>{%Jump to}: "; $sep = "";
		foreach ( $settings['geomap']['areas'] as $area ) {
			$areaswitch .= " $sep <a onclick=\"zoomto('{$area['startpos']}', '{$area['zoom']}')\">{%{$area['display']}}</a> ";
			$sep = " &bull; ";
		};
	};
	
	$moresettings .= "var cql='".urlencode($_GET['cql'])."';";
		
	// Draw the actual map 
	if ( $settings['geomap']['osmlayer'] ) $moresettings .= "var tilelayer = '{$settings['geomap']['osmlayer']}'; ";
	if ( $settings['geomap']['osmlayertit'] ) $moresettings .= "var tiletit = '{$settings['geomap']['osmlayertit']}'; ";
	if ( $settings['geomap']['osmlayerid'] ) $moresettings .= "var tileid = '{$settings['geomap']['osmlayerid']}'; ";

	if ( $direct ) { $bottomactions .= $bsep."<a href='$direct'>{%Direct URL}</a>"; $bsep = " &bull; "; };

	if ( $_GET['cql'] ) { $bottomactions .= $bsep."<a href='index.php?action=multisearch&act=map&cql={$_GET['cql']}&cqlname={$_GET['cqlname']}'>{%Edit queries}</a>"; $bsep = " &bull; "; };

	$bottomactions .= $bsep."<a onClick='fullscreen();'>{%!fullscreen}</a>";

	$maintext  .= "
	<h1>{%$pagtit}</h1>

	$fileheader

	$areaswitch
	$cqptit

	<div id=\"mapdiv\" class=\"mapdiv\" style='width: 100%; height: 600px;'></div>

	<script>
	  $moresettings
	  $cqpjson
	  var jsondata = '$jsondata';
	  var doctxt = '{%$docname}';
	</script>
	<script src=\"$jsurl/geomap.js\"></script>
	<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.css\"/>
	<script src=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.js\"></script>
	<style>.legend { background-color:rgba(255, 255, 255, 0.7); }</style>
	$cluster
	<script>
	  initMap();
	</script>
	<hr> 
	$bottomactions
	$bottomtext
	";
	
	
};


?>