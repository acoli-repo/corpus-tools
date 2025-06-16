<?php


	if ( $act == "ajax" ) {

		$nerfile = getset('xmlfile/ner/nerfile', "ner.xml"); # NER file
		$nerbase = $nerfile;
		if ( strpos($nerfile, "/") == false ) $nerfile = "Resources/$nerfile";
		if ( file_exists($nerfile) ) $nerxml = simplexml_load_file($nerfile); 
	
		$nerid = $_GET['nerid'];
		$nernode = current($nerxml->xpath(".//*[@id=\"$nerid\"]"));
		if ( !$nernode ) exit;
		$nername = current($nernode->xpath(".//placeName"))."";

		$showname = $_GET['raw'] or $showname = $nername;

		$html = "<table><tr><th colspan=2><b>$showname</b></th></tr>
			<tr><th>{%Name}</th><td>$nername</td></tr>
			<tr><th>{%Type}</th><td>{$nernode['type']}</td></tr>
			</table>";		
		
		print i18n($html); exit;

	} else if ( $act == "saveanchor" ) {

		check_login();
		$anchor = getset("geomap/mapview/anchor", "//note[@n='anchor']"); # XP for the map anchor

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;

		print_r($_POST['coords']);
		if ( preg_match("/LatLng\((.+), (.+)\) : LatLng\((.+), (.+)\) : LatLng\((.+), (.+)\)/", $_POST['coords'], $matches) ) {

			$anchornode = current($ttxml->xpath($anchor));
			if ( $anchornode ) {
				foreach ( $anchornode->xpath("./geo") as $i => $tmp ) {
					$tmp[0][0] = $matches[($i*2)+1] . ' ' . $matches[($i*2) + 2];
				};
			};
			print "<hr>"; 
			print showxml($anchornode);
			$ttxml->save();
			print "<p>New anchor save - reloading<script>top.location='index.php?action=$action&cid=$ttxml->fileid&world=1';</script>";
			exit;
		} else {
			fatal ( "Failed to save new anchor" );
		};
		

	} else {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		$xmlid = $ttxml->xmlid;
		$xml = $ttxml->xml;
	
		$boxcolor = "transparent";
		if ( $debug ) $boxcolor = "yellow";
	
		# Define the parameters
		$neraction = getset("geomap/mapview/neraction", "ner"); # Action to use for Ajax 
		$ttaction = getset("geomap/mapview/ajax", "mapview"); # Action to use for Ajax 
		$ttact = getset("geomap/mapview/act", "ajax"); # Act to use for Ajax and linking
		$placetags = getset("geomap/mapview/placename", "placeName"); # Nodename of the placenames 
		$mapxp = getset("geomap/mapview/mapxp", "//pb/@facs"); # XP for the map URL/filename
		$anchor = getset("geomap/mapview/anchor", "//note[@n='anchor']"); # XP for the map anchor
		$zoom = getset("geomap/mapview/zoom", 1); # Action to use for Ajax and linking
		$correspatt = getset('xmlfile/ner/corresp', "corresp"); # Correspondence attribute
		$tilelayer = getset( 'geomap/osmlayer', "https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png");
		$world = $_GET['world'] or $world = 0;
	
		$anchornode = current($ttxml->xpath($anchor));
		$anchorjson = 'null';
		if ( $anchornode ) {
			$anchorjson = "{";
			foreach ( $anchornode->xpath("./geo") as $tmp ) {
				$tmp2 = explode(" ", $tmp."");
				$anchorjson .= " \"{$tmp['n']}\": [{$tmp2[0]}, {$tmp2[1]}], ";
			};
			$anchorjson .= "}";
		};
		
		$pburl = current($ttxml->xpath($mapxp));
		if ( substr($pburl,0,4) != "http" ) $pburl = "Facsimile/".$pburl;
		$size = getimagesize($pburl);
		$fw = $size[0]; $fh = $size[1];
	
		$editxml = $ttxml->asXML();

		if ( $world ) {
			$mapoptions .= "<a href='index.php?action=$action&cid=$ttxml->fileid&world=0'>{%Simple map}</a> 
				&bull; Overlay opacity: <button onclick='changeOpacity(-.1)'>-</button> <span id=opacity>50%</span> <button onclick='changeOpacity(.1)'>+</button> 
				<span style='display: none;' id=saveanchor>&bull; <form action='index.php?action=$action&cid=$ttxml->fileid&act=saveanchor' method=post id=saform style='display: inline'><input type=hidden name=coords value='' id=newcoords> <button type=submit>Save anchor</button></form></span>
				";
		} else {
			$mapoptions .= "<a href='index.php?action=$action&cid=$ttxml->fileid&world=1'>{%Overlay map}</a>";
		};
		if ( $username && !$debug ) {
			$mapoptions .= " &bull; <a href='{$_SERVER['REQUEST_URI']}&debug=1' class=adminpart>Debug</a>";
		};
		
	$maintext .= "<h2>{%Map view}</h2><h1>".$ttxml->title()."</h1>";
	$maintext .= $ttxml->tableheader();
	$maintext .= $ttxml->topswitch();
		$maintext .= "<link rel=\"stylesheet\" href=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.css\"
  integrity=\"sha512-Rksm5RenBEKSKFjgI3a41vrjkw4EVPlJ3+OiI65vTjIdo9brlAacEuKOiQ5OFh7cOI1bkDwLqdLw3Zg0cRJAAQ==\"
  crossorigin=\"\"/>
	<script src=\"https://unpkg.com/leaflet@1.3.1/dist/leaflet.js\"
  integrity=\"sha512-/Nsx9X4HebavoBvEBuyp3I7od5tA0UzAxs+j83KgC8PU0kgB4XiK4Lfe4y4cgBtaRJQEIFCW+oC506aPT2L1zw==\"
  crossorigin=\"\"></script>	
  <script src=\"https://ivansanchez.github.io/Leaflet.ImageOverlay.Rotated/Leaflet.ImageOverlay.Rotated.js\"></script>
		<style>
			.leaflet-tooltip {
				white-space: normal !important;  /* Allow text wrapping */
				width: 300px;  /* Adjust width as needed */
				max-width: 800px;  /* Adjust width as needed */
				word-wrap: break-word;  /* Ensures long words break correctly */
				font-size: 14px;
			}
			body, html { margin: 0; padding: 0; height: 100%; }
			#map-container { width: 100%; height: 80vh; margin: 0; position: relative; border: 2px solid #ccc; }
			#map { width: 100%; height: 100%; }
			#fullscreen-btn {
				position: absolute;
				top: 10px;
				right: 10px;
				z-index: 1000;
				background: white;
				padding: 5px 10px;
				border: 1px solid #aaa;
				cursor: pointer;
			}
			.fullscreen {
				width: 100vw !important;
				height: 100vh !important;
				position: fixed;
				top: 0;
				left: 0;
				z-index: 9999;
				border: none;
			}
		</style>
		<div id=\"map-container\">
			<button id=\"fullscreen-btn\">Toggle Fullscreen</button>
			<div id=\"map\"></div>
		</div>
		<div>$mapoptions</div>
		
		<div style=\"display: none;\" id=mtxt>$editxml</div>
	
		<script>
			var username = '$username';
			var fw = '$fw'; var fh = '$fh';
			var world = $world; // whether to show the world map
			var anchor = $anchorjson; // the anchor of the map image (lat, lon of lt, rt, lb)
			var placetags = '$placetags'; // XML tags of the placenames
			var corresp = '$correspatt'; // @corresp attribute on the placenames
			var neraction = '$neraction'; // action to use for linking to NER click
			var ttaction = '$ttaction'; // action to use for tooltip info
			var ttact = '$ttact'; // act to use for tooltip info
			const imageUrl = '$pburl'; // URL of the map image
			var zoom = $zoom; // default zoom factor
			var boxcolor = '$boxcolor'; // color of the boxes on the map
			var attribution = '$attribution'; // map attribution
			var tilelayer = '$tilelayer'; // tilelayer to use
		</script>
		<script src=\"$jsurl/mapview.js\"></script>
		";
		
		$maintext .= "<hr>".$ttxml->viewswitch();
	
	};

?>