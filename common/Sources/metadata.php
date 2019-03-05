<?php

	$fields = simplexml_load_file("$ttroot/common/Resources/teifields.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	$defaults = simplexml_load_file("$ttroot/common/Resources/teiHeader.xml", NULL, LIBXML_NOERROR | LIBXML_NOWARNING);
	if ( !$defaults ) fatal("Unable to load default teiheader");

	$maintext .= "<h1>Metadata Helper</h1>
		<p>This page describes some recommended fields for the teiHeader metadata, as used in various TEITOK projects.
			TEITOK files do not always follow TEI to the letter, as is explained <a href='http://www.teitok.org/index.php?action=help&id=teixml.html'>here</a>.
			The top gives the structure with its default interpretations, where items in red (and everything below them) are not (currently) standard TEI 
			elements, while items in blue are standard TEI elements used differently. 
			The bottom gives a list of defined fields with their explanation. In both, you can see the standard TEI definition for each field
			by moving your mouse over it. Clicking in the list will bring you to the corresponding page in the TEI P5 guidelines.
			<hr>";
	
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
    Collapsable tree of recommended teiHeader
    ".ulmake(current($defaults->xpath("//teiHeader")), "/TEI/teiHeader")."</li>
   </ul>
   
   <p onClick=\"togglextab()\" ><img id=ximg style='margin-right: 5px; margin-left: 12px' src='http://code.iamkate.com/javascript/collapsible-lists/button-closed.png'> List of recommendable fields</p> <table id=xtab style='display: none;'><tr><th>XPath<th>Description$valuelist</table>
   <script language=Javascript>
   		var xto = 0;
		function togglextab() {
			if ( xto ) {
				document.getElementById('ximg').src = 'http://code.iamkate.com/javascript/collapsible-lists/button-closed.png';
				document.getElementById('xtab').style.display='none';
				xto = 0;
			} else {
				document.getElementById('ximg').src = 'http://code.iamkate.com/javascript/collapsible-lists/button-open.png';
				document.getElementById('xtab').style.display='block';
				xto = 1;
			};
		};
   </script>";
   
   if ( $settings['teiheader'] && strpos($_SERVER['host'], "www.teitok.org") === false ) $maintext .= "<hr><p><a href='index.php?action=header&act=details'>Go to the project metadata definitions</a>";
   else if ( $username && !$settings['teiheader'] ) $maintext .= "<hr><p style='wrong'>Your settings file does not yet define metadata fields - the old
   	methods (using teiHeader-edit.tpl) will gradually become obsolete. Click <a href='index.php?action=header&act=makesettings'>here</a> to create the new settings";

	$maintext .= "<script language=Javascript>CollapsibleLists.applyTo(document.getElementById('mainlist'))</script>";

	function ulmake ( $node, $xp = "" ) {
		global $valuelist; global $fields;
		
		$listtxt = "";
		foreach ( $node->children() as $child ) {
			
			if ( $child['ida'] ) $atts = "[@n=\"{$child[$child['ida']]}\"]"; 
			else $atts = "";
			
			if ( $child['nontei'] == "1" ) $style = " style='color: #aa0000' title='non-standard element'"; 
			else if ( $child['nontei'] == "2" ) $style = " style='color: #0000aa'";
			else $style = "";
			
			$chfn = $child->getName();
			$tmp = $fields->xpath("//field[@name=\"$chfn\"]");
			$tnode = current($tmp); 
			if ( $tnode ) $ctit = $tnode['gloss'].": ".$tnode;
			else {
				$ctit = "non-standard element";
				if ( !$style ) $style = " style='color: #aa6666'";
			};
			
			$chn = $child->getName().$atts;
			$listtxt .= "\n<li><b $style title=\"$ctit\">$chn</b>";
			if ( count($child->children()) ) {
				if ( $child['display'] ) $listtxt .= ": <i>".$child['display']."</i>";
			} else {
				$listtxt .= ": <span title='$xp/$chn'>".$child."<span>";
				if ( $child."" != "" ) {
					if ( $child->xpath("ancestor-or-self::*[@nontei=\"1\"]") ) $style = "style='color: #aa0000' title='non-standard'"; 
					else {
						$style = "title='$ctit' onClick=\"window.open('https://www.tei-c.org/release/doc/tei-p5-doc/en/html/ref-$chfn.html', 'tei-c');\"";
						if ( $child['nontei'] == "2" ) $style .= " style='color: #0000aa'";
					};
					$valuelist .= "<tr><td $style>$xp/$chn<td>$child";
				};
			};
			$listtxt .= ulmake($child, "$xp/$chn");
			$listtxt .= "</li>";
		};
		foreach ( $node->attributes() as $att ) {
			$nn = $att->getName();
			if ( $nn != "ida" && $nn != "nontei" && $nn != "display" &&  $nn != "group" && $nn != $node['ida']."" ) {
				$listtxt .= "\n<li><b>@$nn</b>:  <span title='$xp/@$nn'>".$att."</span>";
				if ( $node->xpath("ancestor-or-self::*[@nontei=\"1\"]") ) $style = "style='color: #aa0000' title='non-standard'"; else $style = "";
				$valuelist .= "<tr><td $style>$xp/@$nn<td>$att";
			};
		};
		if ( $listtxt != "" ) { $listtxt = "<ul>\n$listtxt\n</ul>"; };
		
		
		return $listtxt;
	};

?>