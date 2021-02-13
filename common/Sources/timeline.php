<?php

// Display documents on an interactive timeline

// Place the defined events
//   {id: 'cole', content: 'Inicio del cole', start: fakedate("2020-09-17"), minzoom: 0, maxzoom: 1},
foreach ( $settings['timeline']['events'] as $key => $val ) {
	$startend = "start: '{$val['start']}'"; if ( $val['end'] ) $startend .= ", end: '{$val['end']}'";
	$datelist .= "\t{id: '$key', content: '{$val['display']}', $startend, minzoom: '{$val['minzoom']}', maxzoom: '{$val['maxzoom']}'},\n";
};

$id = $_GET['id'];
if ( $id && $settings['timeline']['events'][$id] ) {
	$framestart = "new Date(\"{$settings['timeline']['events'][$id]['start']}\")";
	$frameend = "new Date(\"{$settings['timeline']['events'][$id]['end']}\")";
	$morescript .= "timeline.setSelection(['$id']);\n";
	$infotxt = getlangfile("timeline_$id");
} else {
	$framestart = "new Date(\"{$settings['timeline']['start']}\")";
	$frameend = "new Date(\"{$settings['timeline']['end']}\")";
};

if (  $settings['timeline']['cqpfld'] ) {
	$morescript .= "var cqpfld = '{$settings['timeline']['cqpfld']}';\n";
};

$maintext .= "
<h1>{%Interactive Timeline}</h1>

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
  background-color: greenyellow;
  border-color: green;
}
</style>";

?>
