<?php

	check_login();

	$udpipelangs = explode(",", "afr,grc,ara,hye,eus,bel,bul,cat,zho,och,cop,hrv,ces,dan,nld,eng,est,fin,fre,glg,deu,got,ell,heb,hin,hun,ind,gle,ita,jpn,kaz,kor,lat,lav,lit,mlt,mar,pcm,sme,nor,ocs,fro,fas,pol,por,rom,rus,san,gla,srp,slk,slv,spa,swe,tam,tel,tur,ukr,urd,uig,vie,cym,wol");

	if ( $_GET['cid'] ) {
		
		require ("$ttroot/common/Sources/ttxml.php");
	
		$ttxml = new TTXML();
		
		if ( $_GET['pid'] ) {
			$prm = $settings['parser']['parameters'][$_GET['pid']];
			$pid = $_GET['pid'];
		} else if ($settings['parser']['parameters']) {
			foreach ( $settings['parser']['parameters'] as $key => $tmp ) {
				$xp = $tmp['restriction']; 
				if ( $ttxml->xml->xpath($xp) ) {
					$prm = $tmp; $pid = $key; last;
				};
			};
		} else {
			$lang = current($ttxml->xml->xpath("//langUsage/language/@ident")) or $lang = $seetings['defaults']['lang'];
			if ( $lang && in_array($lang, $udpipelangs) ) {
				$prm['model'] = $lang;
			} else {
				if ( $lang ) fatal("No current parameter settings in UDPIPE for $lang");
				else fatal("Please indicate the language code of the document language in //teiHeader/profileDesc/langUsage/language/@ident of the TEI file, or in the settings in /ttsettings/defaults/@lang");
			};
		};
		
		$cmd = "/usr/bin/perl $ttroot/common/Scripts/runparser.pl --model={$prm['model']} xmlfiles/$ttxml->filename";
		
		$maintext .= "<h1>Running NLP Tagger/Parser</h1>
			
			<pre>".print_r($prm, 1)."\n$cmd</pre>";
			
		$result = shell_exec("$cmd");
		$maintext .= "<pre>".htmlentities($result)."</pre>";
				
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
		if ( is_array($settings['parser']['parameters']) ) $prms += $settings['parser']['parameters']; 
		# Legacy UDPIPE settings
		if ( is_array($settings['udpipe']['parameters']) ) {
			foreach ( $settings['udpipe']['parameters'] as $prm ) {
				$prm['parser'] = "udpipe";
				$prm['model'] = $prm['params']; unset($prm['params']);
			};
			$prms += $settings['udpipe']['parameters']; 
			$maintext .= "<p class=warning>UDPIPE parameters are discontinued - please change to more general parser settings";
		};
		foreach ( $prms as $prm ) {
			if ( !$prm['name'] ) $prm['parser'] = $settings['parser']['parser'];
			$maintext .= "<pre>".print_r($prm, 1)."</pre>";
		};		
	
	};

?>