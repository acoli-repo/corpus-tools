<?php
	// Script to tokenize an XML file (actually done in Perl)
	// (c) Maarten Janssen, 2015

	check_login();
	header('Content-type: text/html; charset=utf-8');
	// mb_internal_encoding("UTF-8");

	$perlapp = findapp("perl") or $perlapp = "/usr/bin/perl";

	// Character-level tags can be defined within each project, but this is the default list
	if ( !getset('defaults/chartags') ) $chartags = Array ( "add", "del", "supplied", "expan", "abbr", "hi", "lb", "pb", "cb", "ex" ); 
	
	$fileid = $_POST['id'] or $fileid = $_GET['cid'] or $fileid = $_GET['id'];
	
	if ( !$fileid ) fatal("Oops - no filename has been provided");
 
	if ( $_POST['setlang'] ) {

		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();

		$langxp = "/TEI/teiHeader/profileDesc/langUsage/language";

		$setlang = $_POST['setlang']; 
		$langname = $_POST['names'][$setlang.''];
		$langnode = xpathnode($ttxml->xml, $langxp);
		$langnode['ident'] = $setlang;
		$langnode[0][0] = $langname;
		$slangname = $langn[$setlang];
		if ( $langnode && $slangname ) $langnode[0] = $slangname."";
		$ttxml->save();
		print "<p>Language set to {$langn[$setlang]} - reloading
			<script>top.location='index.php?action=$action&cid=$ttxml->fileid'</script>";
		exit;

	} else if ( $act == "setlang") {
	
		$maintext .= "<h1>Set document language</h1>
			<p>The tokenization TEITOK using xmlanntools uses a parser (by default UDPIPE or UDMorph) to tokenize the text. No language has been selected,
				so the system is unable to tokenize. You need to either set the default language for the entire project, or set the language for this 
				particular document. 
				
			<p>You can set the project language in the <a href='index.php?action=adminsettings&amp;act=edit&amp;node=/ttsettings/defaults/@lang'>settings</a>
			
			<form action='index.php?action=$action&cid=$fileid' method=post><p>You can select a language for this individual document here: <select name=setlang id=langlist><option><i style='color: #aaaaaa'>loading language list</i></option></select><span style='display: none' id=langnames></span> <input type=submit value='Set language'></form>
			
			<p>The language dependent tokenization can only be used if either UDPIPE or UDMorph has a tokenizer model for it. If no tokenizer model is
				available for the language of this document, you can use the <a href='index.php?action=$action&nolang=1&cid=$fileid'>language independent</a> tokenizer mode provided by TEITOK itself.</p>
				
			<script>
				var listurl = 'https://lindat.mff.cuni.cz/services/teitok-live/udmorph/index.php?action=tag&act=list';
				// Function to fetch JSON and populate select
				async function populateSelect() {
					try {
						const response = await fetch(listurl);
						const data = await response.json();
		
						const select = document.getElementById('langlist');
						const namefield = document.getElementById('langnames');
						select.innerHTML = ''; // Clear previous options
		
						for (const key in data) {
							var optdat = data[key];
							if ( optdat['tagger'] == 'UDPIPE2' ) {
								let option = document.createElement('option');								
								option.value = optdat['model'];  // Key as value
								option.textContent = optdat['name']; // Value as display text
								select.appendChild(option);
								let optname = document.createElement('input');	
								optname.name = 'names['+optdat['model']+']';							
								optname.value = optdat['name'];							
								namefield.appendChild(optname);
							};
						};
		
					} catch (error) {
						console.error('Error fetching JSON:', error);
					}
				}
		
				// Call the function on page load
				populateSelect();
			</script>";
	
	} else if ( getset("defaults/tokenizer/app") == "xmlanntools" && !$_GET['nolang'] ) {
	
		require("$ttroot/common/Sources/ttxml.php");
		$ttxml = new TTXML();
		$fileid = $ttxml->fileid;
		
		$tmp = $ttxml->xml->xpath("//langUsage/language");
		if ( $tmp ) {
			$langnode = current($tmp);
			$langmodel = $langnode['ident']."" or $langmodel = $langnode."";
		} else {
			$langmodel = $settings['defaults']['lang'];
		};
		if ( !$langmodel ) {
			print "<script>top.location='index.php?action=$action&act=setlang&cid=$fileid';</script><p>No language set - reloading.</p>"; exit;
		};
		
		require("$ttroot/common/Sources/venv.php");
		
		$venv = new VENV();
		$gitfolder = getset("defaults/base/git", "/home/git");
		$perl = findapp("perl");
		
		if ( !is_dir("$gitfolder/xmlanntools") ) shell_exec("mkdir -p $gitfolder; cd $gitfolder; git clone https://github.com/czcorpus/xmlanntools.git");
		if ( !is_dir("$gitfolder/teitok-tools") ) shell_exec("mkdir -p $gitfolder; cd $gitfolder; git clone https://github.com/ufal/teitok-tools.git");
	
		$xmlid = preg_replace("/.*\//", "", $fileid);
		$xmlid = str_replace(".xml", "", $fileid);
	
		$venv->installmod("requests"); # Make sure requests is installed
		
		ob_start();
		copy("xmlfiles/$xmlid.xml", "backups/$xmlid-beftok.xml");
		copy("xmlfiles/$xmlid.xml", "tmp/$xmlid.xml");
		$cmd = "$gitfolder/xmlanntools/xml2standoff -e note,del,choice/sic -t text,text//p,text//u,text//head,text//div tmp/$xmlid.xml";
		$venv->exec($cmd); 
		$cmd = "$gitfolder/xmlanntools/tag_ud -m $langmodel -f tmp/$xmlid.txt > tmp/$xmlid.conllu";
		$venv->exec($cmd); 
		$cmd = "$gitfolder/xmlanntools/ann2standoff  --token-element tok  -a ord,form,lemma,upos,xpos,feats,ohead,deprel,deps,misc tmp/$xmlid.conllu"; # 
		$venv->exec($cmd); 
		$cmd = "$gitfolder/xmlanntools/standoff2xml -te tok  tmp/$xmlid.txt";
		$venv->exec($cmd); 
		$cmd = "$perl $ttroot/common/Scripts/xmlrenumber.pl tmp/$xmlid.ann.xml";
		shell_exec($cmd); 
		if ( getset("defaults/tokenizer/noparse") != '' ) {
			 # Clean out the annotation?
		};
		$cmd = "$perl $gitfolder/teitok-tools/Scripts/xancorrect.pl tmp/$xmlid.ann.xml"; 
		shell_exec($cmd); 
		copy("tmp/$xmlid.ann.xml", "xmlfiles/$fileid");
		ob_end_flush();		
		
		# Cleanup
		if ( !$debug ) {
			unlink("tmp/$xmlid.xml");
			unlink("tmp/$xmlid.conllu");
			unlink("tmp/$xmlid.json");
			unlink("tmp/$xmlid.txt");
			unlink("tmp/$xmlid.ann.xml");
			unlink("tmp/$xmlid.ann.json");
		};
				
		$nexturl = "index.php?action=file&id=$fileid";
		print "<p>The text has been tokenized</p><script langauge=Javasript>top.location='$nexturl';</script>";
		exit;
		
	} else {

		if ( preg_match("/\/([a-z0-9]+)$/i", $mtxtelement, $matches ) ) {
			$mtxtelm = $matches[1];
		} else {
			$mtxtelm = "text";
		};

		if ( getset('xmlfile/linebreaks') ) { $lbcmd = " --linebreaks "; };
		if ( $_GET['s'] ) { $lbcmd .= " --s={$_GET['s']} "; }; # Sentence split

		$tmp = ""; 
		if ( is_array(getset('defaults/tokenizer')) ) $tmp = getset('defaults/tokenizer/sentences');
		if ( $tmp != '0' ) $lbcmd .= " --sent=1";

		# Build the UNIX command
		if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot"; };
		$cmd = "$perlapp $scrt/common/Scripts/xmltokenize.pl --mtxtelm=$mtxtelm --filename='xmlfiles/$fileid' $lbcmd ";
		# print $cmd; exit;
		$res = shell_exec($cmd);
		for ( $i=1; $i<1000; $i++ ) { $n = $n+(($i+$n)/$i); }; # Force a bit of waiting...
		
		if ( strpos($res, "Invalid XML") !== false ) { 
			$maintext .= "<p>Tokenization failed - potentially due to the use of namespaces in the XML file, which are not supported by the Perl tokenization module";
		} else if (strpos($res, "XML got messed up") !== false ) { 
			$error = shell_exec("xmllint /tmp/wrong.xml");
			$maintext .= "<p>Tokenization failed - probably due to a complex cluster of not token-based XML annotations";
			if ( $error ) $maintext .= " - below is an output of the error analysis, which might suggest where the problem is located.
				<pre>$error</pre>";
		} else {		
			if ( $_GET['tid'] )
				$nexturl = "index.php?action=tokedit&cid=$fileid&tid={$_GET['tid']}";
			else 
				$nexturl = "index.php?action=file&id=$fileid";
			$maintext .= "<hr><p>Your text has been renumbered - reloading to <a href='$nexturl'>the edit page</a>";
			$maintext .= "<script langauge=Javasript>top.location='$nexturl';</script>";
		};
		
	
	};

?>