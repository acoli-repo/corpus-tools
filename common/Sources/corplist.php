<?php

	# Centralized corpus list
	$xmlfile = $settings['corplist']['xml'] or $xmlfile = "Resources/corplist.xml";
	$corplist = simplexml_load_file($xmlfile);	
	if ( !$corplist ) fatal("Failed to load the corpus list");

	$maintext .= "<h1>{%Corpora}</h1>";
	
	$rest = $settings['corplist']['xprest'];
	
	$maintext .= "<ul>";
	foreach ( $corplist->xpath("//corpus$rest") as $corp ) {
		$corpname = $corp['display'] or $corpname = $corp['ident'];
		$corpurl = $corp['url'] or $corpurl = str_replace("tt_", "", $corp['ident'])."/";
		$tmp = current($corp->xpath("./desc")); if ( $tmp ) $desc = $tmp->asXML();
		
		$maintext .= "	
		<li xmlns='' class='item-box'>
<div class='item-type'>corpus</div>
<div class='item-branding label'>
<a href='/repository/xmlui/discover?filtertype=branding&amp;filter_relational_operator=equals&amp;filter=LINDAT+%2F+CLARIN'>LINDAT / CLARIN</a>
</div>
<img onerror='this.src='/repository/xmlui/themes/UFAL/images/mime/application-x-zerosize.png'' alt='corpus' class='artifact-icon pull-right' src='/repository/xmlui/themes/UFALHome/lib/images/corpus.png'>
<div class='artifact-title'>
<a href='$corpurl'>$corpname)</a>
</div>
<div class='publisher-date'>$publisher</div>
<div class='artifact-info'>
<span class='Z3988 hidden' title='ctx_ver=Z39.88-2004&amp;rft_val_fmt=info%3Aofi%2Ffmt%3Akev%3Amtx%3Adc&amp;rft_id=LDC2006T01&amp;rft_id=http%3A%2F%2Fhdl.handle.net%2F11858%2F00-097C-0000-0001-B098-5&amp;rfr_id=info%3Asid%2Fdspace.org%3Arepository&amp;rft.has=yes&amp;rft.files=281012478&amp;rft.files=8'>
                ﻿ 
            </span>
<div class='author-head'>Author(s):</div>
<div xmlns:i18n='http://apache.org/cocoon/i18n/2.1' class='author'>
<span>
$authors
</span>
</div>
</div>
</div>
</li>";

	};
	$maintext .= "</ul>";

?>