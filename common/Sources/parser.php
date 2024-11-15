<?php

	check_login();

	$udpipelangs = explode(",", "afr,grc,ara,hye,eus,bel,bul,cat,zho,och,cop,hrv,ces,dan,nld,eng,est,fin,fre,glg,deu,got,ell,heb,hin,hun,ind,gle,ita,jpn,kaz,kor,lat,lav,lit,mlt,mar,pcm,sme,nor,ocs,fro,fas,pol,por,rom,rus,san,gla,srp,slk,slv,spa,swe,tam,tel,tur,ukr,urd,uig,vie,cym,wol");

	if ( $_GET['cid'] || $_GET['id'] ) {
		
		require ("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();

		$perlapp = findapp("perl");
		$maintext .= "<h1>Running NLP Tagger/Parser</h1>";
		
		if ( getset('parser/notokenize') == '' && strpos($ttxml->rawtext, "</tok>") === false ) {
			$cmd = "$perlapp $ttroot/common/Scripts/xmltokenize.pl --mtxtelm=$mtxtelm --filename='xmlfiles/$ttxml->filename' ";
			$maintext .= "<h2>Tokenizing</h2>";
			$result = shell_exec("$cmd");
			$maintext .= "<pre>".htmlentities($result)."</pre>";
			$maintext .= "<hr><h2>Parsing</h2>";
		};
		
		if ( $_GET['pid'] ) {
			$prm = getset("parser/parameters/{$_GET['pid']}");
			$pid = $_GET['pid'];
		} else if ( getset('parser/parameters') != '' ) {
			foreach ( getset('parser/parameters', array()) as $key => $tmp ) {
				$xp = $tmp['restriction']; 
				if ( $ttxml->xpath($xp) ) {
					$prm = $tmp; $pid = $key; last;
				};
			};
		} else {
			$lang = current($ttxml->xpath("//langUsage/language/@ident")) or $lang = $seetings['defaults']['lang'];
			if ( $lang && in_array($lang, $udpipelangs) ) {
				$prm['model'] = $lang;
			} else {
				if ( $lang ) fatal("No current parameter settings in UDPIPE for $lang");
				else fatal("Please indicate the language code of the document language in //teiHeader/profileDesc/langUsage/language/@ident of the TEI file, or in the settings in /ttsettings/defaults/@lang");
			};
		};
		
		if ( getset('parser/nosegment') == '' ) $morecmd = " --killsent";
		if ( getset('parser/textpath') != '' ) $morecmd .= " --xpath='{$settings['parser']['textpath']}'"; 

		$cmd = "$perlapp $ttroot/common/Scripts/runparser.pl --verbose --model={$prm['model']} --filename=xmlfiles/$ttxml->filename $morecmd";
			
		$maintext .= "<pre>$cmd</pre>";
			
		$result = shell_exec("$cmd");
		$maintext .= "<pre>".htmlentities($result)."</pre>";
		$maintext .= "<hr><p><a href='index.php?action=file&cid=$ttxml->fileid'>back to text</a>";
				
	} else {
	
		$maintext .= "<h1>NLP Parser</h1>
		
			<p>In this module, you are helped to apply POS taggers or dependency parsers
			to your XML file(s). The option to tag will appear at the bottom of the document
			if there is an applicable parameter setting for the file. The default parser
			applied in TEITOK is UDPIPE (run as a web service), but most other taggers or
			parsers that output a TSV format can be applied here as well. If no model is defined,
			the model will the ISO name of the language - taken either from the //teiHeader/profileDesc/langUsage/language/@ident
			of the TEI file, or else from the /ttsettings/defaults/@lang.";
		
		$maintext .= "<h2>Current Settings</h2>";
		$prms = array();
		if ( is_array(getset('parser/parameters')) ) $prms += $settings['parser']['parameters']; 
		# Legacy UDPIPE settings
		foreach ( getset('udpipe/parameters', array()) as $prm ) {
			$prm['parser'] = "udpipe";
			$prm['model'] = $prm['params']; unset($prm['params']);
		};
		// $prms += getset('udpipe/parameters'); 
		$maintext .= "<p class=warning>UDPIPE parameters are discontinued - please change to more general parser settings";
		foreach ( $prms as $prm ) {
			if ( !$prm['name'] ) $prm['parser'] = getset('parser/parser');
			$maintext .= "<pre>".print_r($prm, 1)."</pre>";
		};		
	
	};

?>