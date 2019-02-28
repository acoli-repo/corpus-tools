<?php

	$defaults = simplexml_load_file("$ttroot/common/Resources/teiheader.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$defaults )
	if ( $username ) fatal("Unable to load default teiheader ($ttroot/common/Resources/teiHeader.xml)");
	else fatal("An error occurred");
	
	$maintext .= "<h1>Metadata Helper</h1>
		<p>This page describes some recommended fields for the teiHeader metadata, as used in various TEITOK projects.";
	
	$maintext .= "<div style='display: none;' id='metadata'>".$defaults->asXML()."</div>";


	$maintext .= "<script src='http://code.iamkate.com/javascript/collapsible-lists/CollapsibleLists.js'></script>
		<style>
		.collapsibleList li{
  list-style-image : url('http://code.iamkate.com/javascript/collapsible-lists/button.png');
  cursor           : auto;
}

li.collapsibleListOpen{
  list-style-image : url('http://code.iamkate.com/javascript/collapsible-lists/button-open.png');
  cursor           : pointer;
}

li.collapsibleListClosed{
  list-style-image : url('http://code.iamkate.com/javascript/collapsible-lists/button-closed.png');
  cursor           : pointer;
}
		</style>";

	$maintext .= "<ul id='mainlist'>
   <li>
    teiHeader
    ".ulmake(current($defaults->xpath("//teiHeader")), "/TEI/teiHeader")."</li>
   </ul>
   
   <table><tr><th>XPath<th>Description$valuelist</table>";

	$maintext .= "<script language=Javascript>CollapsibleLists.applyTo(document.getElementById('mainlist'))</script>";

	function ulmake ( $node, $xp = "" ) {
		global $valuelist;
		
		$listtxt = "<ul>";
		foreach ( $node->children() as $child ) {
			
			if ( $child['ida'] ) $atts = "[@n=\"{$child[$child['ida']]}\"]"; 
			else $atts = "";
			
			if ( $child['nontei'] ) $style = " style='color: #aa0000' title='non-standard'"; else $style = "";
			
			$chn = $child->getName().$atts;
			$listtxt .= "\n<li><b $style>$chn</b>";
			if ( count($child->children()) ) {
				if ( $child['display'] ) $listtxt .= ": <i>".$child['display']."</i>";
				$listtxt .= ulmake($child, "$xp/$chn");
			} else {
				$listtxt .= ": <span title='$xp/$chn'>".$child."<span>";
				if ( $child."" != "" ) $valuelist .= "<tr><td>$xp/$chn<td>$child";
			};
			$listtxt .= "</li>";
		};
		foreach ( $node->attributes() as $att ) {
			$nn = $att->getName();
			if ( $nn != "ida" && $nn != "nontei" && $nn != "display" && $nn != $node['ida']."" ) {
				$listtxt .= "\n<li><b $style>@$nn</b>:  <span title='$xp/@$nn'>".$att."</span>";
				$valuelist .= "<tr><td>$xp/@$nn<td>$att";
			};
		};
		$listtxt .= "\n</ul>";
		
		return $listtxt;
	};

?>