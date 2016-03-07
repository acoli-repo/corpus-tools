<?php

# Tool to display geographical locations of documents onto Google Maps
# Uses CQP as an index for the locations, with an assumed <text_geo> 
# Consisting of lat and lng separated by a space
# (c) Maarten Janssen, 2016
  
$geofld = $settings['geomap']['geo'] or $geofld = "geo";
$geoplace = $settings['geomap']['place'] or $geoplace = "place";
$ftit = $settings['geomap']['title'] or $ftit = "id";

$apikey = $settings['geomap']['apikey'] or $apikey = "AIzaSyBOJdkaWfyEpmdmCsLP0B6JSu5Ne7WkNSE"; # Use our key when no other key is defined  
  
// Since this dependends on text_geo, make sure it exists
if ( !file_exists("cqp/text_$geofld.rng") ) {
	fatal ( "Geodistribution is only available with a $geofld attribute on <text>." );
};

include ("../common/Sources/cwcqp.php");
$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
  
$cqp = new CQP();
$cqp->exec($cqpcorpus); // Select the corpus
$cqp->exec("set PrettyPrint off");

if ( $act == "view" ) {

	# View all the documents of a given location
	$location = $_GET['location'] or $location = $_GET['lat'].' '.$_GET['lng'];
	$place = $_GET['place'];

	$cqpquery = "Matches = <text_$geofld = \"$location\"> []";
	$cqp->exec($cqpquery);

	$maintext .= "<h1>{%Documents from} $place</h1>";

	$cqpquery = "tabulate Matches 0 100 match text_id, match text_$ftit";
	$results = $cqp->exec($cqpquery); 


	foreach ( split ( "\n", $results ) as $line ) {	
		list ( $fileid, $title ) = explode ( "\t", $line );
		if ( preg_match ( "/([^\/]+)\.xml/", $fileid, $matches ) ) {	
			$cid = $matches[1];
			if ( $title == $fileid ) $title = $cid;
		
			$maintext .= "<p><a href='index.php?action=file&cid=$cid'>$title</a></p>";
		};
	};
	

} else {
	$cqpquery = "Matches = <text_$geofld != \"_\"> []"; # This should become "" again
	$cqp->exec($cqpquery);  print $cqpquery;

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
			$jsondata{$geo} = "{ \"lat\": $lat, \"lng\": $lng, \"location\": \"$name\", \"cnt\": ".$tot{$geo}." }";
			$sep = ", ";
		};
	};
	$jsondata = "[ ".join(", ", array_values($jsondata))." ]";

	if ( $settings['geomap']['zoom'] ) $moresettings .= "var defzoom = {$settings['geomap']['zoom']};";
	if ( $settings['geomap']['startpos'] ) {
		list ( $lat, $lng ) = split ( " ", $settings['geomap']['startpos'] );
		$moresettings .= " var defpos = {lat: $lat, lng: $lng };";
	};

	$fileheader = getlangfile("geomaptext");
	
	// Larger circles indicate more documents from that location.
	$maintext  .= "
	<h1>{%Document Map}</h1>

	$fileheader

	<div id=\"map\" style='width: 100%; height: 600px;'></div>
	<script>
	  $moresettings
	  var jsondata = '$jsondata';
	  var doctxt = '{%documents}';
	</script>
	<script src=\"$jsurl/geomap.js\"></script>
	<script async defer src=\"https://maps.googleapis.com/maps/api/js?key=$apikey&callback=initMap\"></script>
	";
};


?>