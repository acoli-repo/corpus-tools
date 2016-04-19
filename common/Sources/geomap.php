<?php

# Tool to display geographical locations of documents onto Google Maps
# Uses CQP as an index for the locations, with an assumed <text_geo> 
# Consisting of lat and lng separated by a space
# (c) Maarten Janssen, 2016
  
$geofld = $settings['geomap']['cqp']['geo'] or $geofld = "geo";
$geoplace = $settings['geomap']['cqp']['place'] or $geoplace = "place";
$ftit = $settings['geomap']['cqp']['title'] or $ftit = "id";

$docname = $settings['geomap']['documents'] or $docname = "documents";
$pagtit = $settings['geomap']['title'] or $pagtit = "Document Map";

$apikey = $settings['geomap']['apikey'] or $apikey = "AIzaSyBOJdkaWfyEpmdmCsLP0B6JSu5Ne7WkNSE"; # Use our key when no other key is defined  
  

if ( $act == "xml" ) {

	require ("../common/Sources/ttxml.php");
	$ttxml = new TTXML($cid, false);
	$fileid = $ttxml->fileid;
	
	$geoxp = $settings['geomap']['xml']['node'] or $geoxp = "//*[geo]";
	$geoll = $settings['geomap']['xml']['geo'] or $geoll = "./geo";
	$geoname = $settings['geomap']['xml']['name'] or $geoname = "./name";
	$geodesc = $settings['geomap']['xml']['desc'] or $geodesc = "./desc";

	$maintext .= "<h1>{%Geographical Locations}</h1>";
	$maintext .= $ttxml->tableheader();
	
	$maintext .= "<ul>";
	foreach ( $ttxml->xml->xpath($geoxp) as $geonode ) {

		$geo = current($geonode->xpath($geoll))."";  
		$place = current($geonode->xpath($geoname)).""; 
		
		if ( preg_match( "/^=(.*)$/", $geodesc, $matches ) ) $desc = $matches[1];
		else $desc = current($geonode->xpath($geodesc))."";  
	
		list ( $lat, $lng ) = explode ( " ", $geo );
		$maintext .= "<li>$place"; if ( $desc ) $maintext .= ": $desc";
		
		$descs{$geo} .= "<p>$desc</p>"; $desctxt = $descs{$geo};
		$jsonpoints{$geo} = "{ \"lat\": \"$lat\", \"lng\": \"$lng\", \"location\": \"$place\", \"cnt\": 1, \"desc\": \"$desctxt\" }";

	};
	$maintext .= "</ul>";
	$jsondata = "[ ".join(", ", array_values($jsonpoints))." ]";

	// Larger circles indicate more documents from that location.
	$maintext  .= "
	<div id=\"map\" style='width: 100%; height: 600px;'></div>
	<script>
	  $moresettings
	  var defzoom = 8;
	  var jsondata = '$jsondata';
	  var doctxt = '{%$docname}';
	</script>
	<script src=\"$jsurl/geomap.js\"></script>
	<script async defer src=\"https://maps.googleapis.com/maps/api/js?key=$apikey&callback=initMap\"></script>
	<hr><p><a href='index.php?action=file&cid=".$ttxml->fileid."'>{%back to text view}</a></p>";

} else if ( $act == "view" ) {


	// Since this dependends on text_geo, make sure it exists
	if ( !file_exists("cqp/text_$geofld.rng") ) {
		fatal ( "Geodistribution is only available with a <i>$geofld</i> attribute on &lt;text&gt; in CQP." );
	};

	include ("../common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus
	$cqp->exec("set PrettyPrint off");

	# View all the documents of a given location
	$location = $_GET['location'] or $location = $_GET['lat'].' '.$_GET['lng'];
	$place = $_GET['place'];

	$cqpquery = "Matches = <text_$geofld = \"$location\"> []";
	$cqp->exec($cqpquery);

	$size = $cqp->exec("size Matches");

	$maintext .= "<h1>{%Documents from} $place</h1>
		<p>$size {%$docname}</p><hr>";

	if ( $size > 0 ) {
		$cqpquery = "tabulate Matches 0 100 match text_id, match text_$ftit";
		$results = $cqp->exec($cqpquery); 


		foreach ( split ( "\n", $results ) as $line ) {	
			list ( $fileid, $title ) = explode ( "\t", $line );
			if ( preg_match ( "/([^\/]+)\.xml/", $fileid, $matches ) ) {	
				$cid = $matches[1];
				if ( $title == $fileid || $title == "" ) $title = $cid;

				$maintext .= "<p><a href='index.php?action=file&cid=$cid'>$title</a></p>";
			};
		};
	} else {
		$maintext .= "<p><i>{%No results found}</i></p>";
	};
	

} else {

	// Since this dependends on text_geo, make sure it exists
	if ( !file_exists("cqp/text_$geofld.rng") ) {
		fatal ( "Geodistribution is only available with a <i>$geofld</i> attribute on &lt;text&gt; in CQP." );
	};
	
	include ("../common/Sources/cwcqp.php");
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
	$cqp = new CQP();
	$cqp->exec($cqpcorpus); // Select the corpus
	$cqp->exec("set PrettyPrint off");

	$cqpquery = "Matches = <text_$geofld != \"_\"> []"; # This should become "" again
	$cqp->exec($cqpquery); 

	$size = $cqp->exec("size Matches");

	$cqpquery = "group Matches match text_$geoplace by match text_$geofld";
	$results = $cqp->exec($cqpquery);

	$sep = "";
	foreach ( split ( "\n", $results ) as $line ) {	
		list ( $geo, $name, $cnt ) = split ( "\t", $line );
		list ( $lat, $lng ) = split ( " ", $geo );
		$name = htmlentities($name, ENT_QUOTES);
		$lat = preg_replace("/,.*/", "", $lat);
		$lng = preg_replace("/,.*/", "", $lng);
		$cnt += 0;
		if ($lat) {
			$tot{$geo} += $cnt;
			$jsonpoints{$geo} = "{ \"lat\": \"$lat\", \"lng\": \"$lng\", \"location\": \"$name\", \"cnt\": ".$tot{$geo}." }";
			$sep = ", ";
		};
	};
	$jsondata = "[ ".join(", ", array_values($jsonpoints))." ]";

	if ( $settings['geomap']['zoom'] ) $moresettings .= "var defzoom = {$settings['geomap']['zoom']};";
	if ( $settings['geomap']['startpos'] ) {
		list ( $lat, $lng ) = split ( " ", $settings['geomap']['startpos'] );
		$moresettings .= " var defpos = {lat: $lat, lng: $lng };";
	};

	$fileheader = getlangfile("geomaptext");
		
	// Larger circles indicate more documents from that location.
	$maintext  .= "
	<h1>{%$pagtit}</h1>

	$fileheader

	<div id=\"map\" style='width: 100%; height: 600px;'></div>
	<script>
	  $moresettings
	  var jsondata = '$jsondata';
	  var doctxt = '{%$docname}';
	</script>
	<script src=\"$jsurl/geomap.js\"></script>
	<script async defer src=\"https://maps.googleapis.com/maps/api/js?key=$apikey&callback=initMap\"></script>
	";
};


?>