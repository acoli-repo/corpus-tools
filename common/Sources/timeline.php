<?php

// Display documents on an interactive timeline

// Place the defined events
//   {id: 'cole', content: 'Inicio del cole', start: fakedate("2020-09-17"), minzoom: 0, maxzoom: 1},
foreach ( getset('timeline/events', array()) as $key => $val ) {
	$startend = "start: '{$val['start']}'"; if ( $val['end'] ) $startend .= ", end: '{$val['end']}'";
	$datelist .= "\t{id: '$key', content: '{$val['display']}', $startend, minzoom: '{$val['minzoom']}', maxzoom: '{$val['maxzoom']}'},\n";
};
if ( getset('timeline/xml') != '' ) {
	$xmlfile = "Resources/{$settings['timeline']['xml']}"; if ( substr($xmlfile, -4) != ".xml" ) $xmlfile .= ".xml";
	$eventxml = simplexml_load_file($xmlfile);
	if ( $eventxml )
	foreach ( $eventxml->children() as $event ) {
		$key = $event['id'];
		unset($val);
		foreach ( $event->children() as $child ) { 	$val[$child->getName().""] = $child.""; };
		$startend = "start: '{$val['start']}'"; if ( $val['end'] ) $startend .= ", end: '{$val['end']}'";
		$datelist .= "\t{id: '$key', content: '{$val['display']}', $startend, minzoom: '{$val['minzoom']}', maxzoom: '{$val['maxzoom']}'},\n";
	};
};

$id = $_GET['id'];
if ( $id && getset("timeline/events/$id") != "" ) {
	$framestart = "new Date(\"{$settings['timeline']['events'][$id]['start']}\")";
	$frameend = "new Date(\"{$settings['timeline']['events'][$id]['end']}\")";
	$morescript .= "timeline.setSelection(['$id']);\n";
	$infotxt = getlangfile("timeline_$id");
} else {
	$framestart = "new Date(\"{$settings['timeline']['start']}\")";
	$frameend = "new Date(\"{$settings['timeline']['end']}\")";
};

if (  getset('timeline/cqpevent') != '' ) {
	$morescript .= "var cqpfld = '{$settings['timeline']['cqpevent']}';\n";
};
if (  getset('timeline/cqpdate') != '' ) {
		include ("$ttroot/common/Sources/cwcqp.php");
		$cqpcorpus = strtoupper(getset('cqp/corpus')); # a CQP corpus name ALWAYS is in all-caps
		$cqpfolder = getset('cqp/cqpfolder', "cqp");
		$cqp = new CQP();
		$cqp->exec($cqpcorpus); // Select the corpus
		$cqp->exec("set PrettyPrint off");

		$cqpdate = getset('timeline/cqpdate');
		$cqpquery = "Matches = <$cqpdate> []";
		$results = $cqp->exec($cqpquery); 
		$rescnt = $cqp->exec("size Matches"); 

		$titlefld = getset('cqp/titlefld');
		if ( !$titlefld )
			if ( getset('cqp/sattributes/text/title') != '' ) $titlefld = "text_title"; else $titlefld = "text_id";
		
		if ( $rescnt ) { 
		
			$cqpquery = "tabulate Matches match text_id, match $cqpdate, match $titlefld";
			$results = $cqp->exec($cqpquery);
		
			$resarr = explode ( "\n", $results ); $scnt = count($resarr);
			
			foreach ( $resarr as $resline ) {
				list ( $resid, $resdate, $restitle ) = explode("\t", $resline);
				$xmldate = $resdate;
				$restitle  = str_replace("'", "&#039;", $restitle);
				if ( $xmldate ) $datearray[$xmldate.""] .= "'<a target=details href=\"index.php?action=file&cid=$resid\">$resdate. $restitle</a>',";
			};
			foreach ( $datearray as $xmldate => $doclist ) {
				$datelist .= "\t{id: '$xmldate', content: '$xmldate', list: [$doclist], start: \"$xmldate\", className:'document'},\n"; //  type: 'point', 
			};
		};
};

$modtitle = getset('timeline/title', "Interactive Timeline");	

$maintext .= "
<h1>{%$modtitle}</h1>

<div id=\"visualization\"></div>
<p>
<div id='info'>$infotxt</div>

<script src=\"https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis-timeline-graph2d.min.js\"></script>
<link href=\"https://cdnjs.cloudflare.com/ajax/libs/vis/4.21.0/vis-timeline-graph2d.min.css\" rel=\"stylesheet\" type=\"text/css\">

<script>
var datelist = [
	$datelist
];
</script>
<script type=\"text/javascript\" src=\"$jsurl/timeline.js\"></script>
<script>
timeline.setWindow($framestart, $frameend);
$morescript
</script>

<style>
.vis-item.document {
  background-color: #aaffaa;
  border-color: green;
}
.vis-item.event {
  background-color: greenyellow;
  border-color: green;
}
</style>";

if ( $username && getset('timeline/xml') != '' ) {
	$xmlfile = str_replace(".xml", "", getset('timeline/xml'));
	if ( !file_exists("Resources/$xmlfiles-entry.xml") ) 
		file_put_contents("Resources/$xmlfiles-entry.xml", "<event>
	<start list=\"1\">Start date</start>
	<end list=\"1\">End date</end>
	<display list=\"1\">Display name</display>
	<minzoom>Minimum window size for display</minzoom>
	<maxzoom>Maximum window size for display</maxzoom>
</event>");
	$maintext .= "<hr><a href='index.php?action=xmlreader&xml=$xmlfile'>edit events</a>";
};

?>
