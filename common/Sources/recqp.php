<?php	
	check_login();
			
	$maintext .= "<h1>Regenerating the CQP Corpus</h1>";
	$thisdir = dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['SCRIPT_NAME']); 

	if ( file_exists("tmp/recqp.log") ) {
		$modtime = filemtime("tmp/recqp.log");
		$now = time(); $timediff = $now-$modtime;
		if ( $timediff < 100 ) {  $recentfile = 1; }; # If recqp ended less than 100 sec ago, do not regenerate
	};

	# Check whether registry file matches our corpus
	$registryfolder = $settings['cqp']['defaults']['registry'] or $registryfolder = "$thisdir/cqp";

	# Unless we have a recqp.pl script, we need tt-cwb-encode
	if ( !file_exists("Scripts/recqp.pl") ) {
		$tmp = findapp("tt-cwb-encode");
		if ( !$tmp ) fatal("Regenerating the CQP index relies on tt-cwb-encode, which is not installed on your server - $tmp");
	};
	
	$cqpcorpus = strtoupper($settings['cqp']['corpus']); # a CQP corpus name ALWAYS is in all-caps
	$registryfile = $registryfolder.strtolower($cqpcorpus);
	
	if ( file_exists($registryfile) ) {
		$registry = file_get_contents($registryfile);
		if ( preg_match( "/HOME\s+(.*)/", $registry, $matches ) ) { $cqphome = $matches[1]; };
		$thisfolder = $_SERVER['SCRIPT_FILENAME']; $thisfolder = substr($thisfolder, 0, strpos($thisfolder, '/index.php') );
		if ( realpath($thisfolder."/cqp") != realpath($cqphome) && $cqpome ) {
			fatal("You are trying to create corpus $cqpcorpus. 
				There is such a corpus in $cqphome, which does not seem to be this corpus ($thisfolder/$cqp). 
				Please use a different corpus name in settings.xml or remove the existing registry file ($registryfile) if this is there is no name conflict");
		};
	};

	$lastupdate = "<i>Never generated before</i>";
	$tmp = file_get_contents("tmp/lastupdate.log");
	if ( $tmp ) {
		list ( $start, $lapse, $size ) = explode("\t", $tmp);
		$size = hrnum($size);
		$lapse = preg_replace("/ 0+ ([^ ]+)/", " ", date(" z \d\a\y\s H \h\o\u\\r\s i \m\i\\n\u\\t\\e\s s \s\\e\c\o\\n\d\s", $lapse));
		$lastupdate = "Started $start - generated corpus of $size tokens in $lapse";
	};

	# First - check whether the process is not already running
	if ( file_exists("tmp/recqp.pid") ) {
	
		$maintext .= "<p>It looks like the corpus is currently being regenerated. It can happen that this is a ghost process,
			in which case you will have to remove the file tmp/recqp.pid manually";
		
		$logtxt = file_get_contents("tmp/recqp.pid");
		
		$maintext .= "<p>The current status of the process can be read below. The steps that have to be finished for the
			corpus to complete are: 
				<ol>
					<li> Encode : encode all the tokens in all the texts in the search folder in CWB format
					<li> Make : create all the necessary files for the CQP corpus
				</ol>
			<p>Step 2 tends to be fast, while steps 1 can take several minutes. 
			
			<p> - Last regeneration: $lastupdate
		
			<hr><pre>$logtxt</pre>
			
			<hr><p style='color: #777777'>This page will reload every 10 seconds.</p>
			<script type=\"text/javascript\">
					setTimeout(function () { 
					  location.reload();
					}, 10 * 1000);
				</script>";
			
	} else if ( ( file_exists("Scripts/recqp.pl") || file_exists("../common/Scripts/recqp.pl") ) && !$_GET['check'] && !$_GET['force'] ) {


		if ( ( file_exists("cqp/word.corpus")	&& !is_writable("cqp/word.corpus") ) || !is_writable("cqp") ) 
			fatal("The permissions on the CQP files prevent the system from writing them");		

		if ( file_exists("Scripts/recqp.pl") ) $scriptname = "Scripts/recqp.pl"; else $scriptname = "../common/Scripts/recqp.pl";	
		$maintext .= "
			<p>Currently, the CQP Corpus called {$settings['cqp']['corpus']} is regenerated based on the current
				content of the XML files in ({$settings['cqp']['searchfolder']}).
			
			<p>Depending on the size of the corpus, this process can take quite a while and will run in the background.
				To show the progess, this page will reload
				<script type=\"text/javascript\">
					setTimeout(function () { 
					  top.location = 'index.php?action=recqp&check=1';
					}, 5 * 1000);
				</script>";
				
		# Start the perl script as a background process
		exec("perl $scriptname > /dev/null &");

	} else if ( ( $_GET['check'] || $recentfile ) && file_exists("tmp/recqp.log")  && !$_GET['force'] ) {
		
		$logtxt = file_get_contents("tmp/recqp.log");
		if ( filesize("cqp/word.corpus") == 0 ) 
			$maintext .= "<p>The generation process seems to have terminated, but the corpus file is empty. The transcript of the process
				can be read below. ";
		else
		$maintext .= "<p>The generation process seems to have terminated successfully. The transcript of the process
			can be read below. 
			<p>Click <a href='index.php?action=cqp'>here</a> to continute to the CQP search
			
				<hr><pre>$logtxt</pre>";

	} else if ( $_GET['check'] ) {
		
		$maintext .= "<p>The regeneration process does not seem to be running. This can be due to the fact that it has
			never been used, or that TEITOK is not allowed to write to the tmp or Scripts folder";
	
	} else {
	
		# Check whether recqp.pl is writable
		if ( 
			( !file_exists("Scripts/recqp.pl") && !is_writable("Scripts/") )
			||
			( file_exists("Scripts/recqp.pl") && !is_writable("Scripts/recqp.pl") )
		) { fatal("Permission denied while trying to (re)generate recqp.pl - please contact admin"); };
	
		$maintext .= "<p>The generation script is currently being created (again).";
	
		# Create the script to regenerate the corpus and reload
	
		ob_end_flush();
		$cqpcorpus = $settings['cqp']['corpus'];
		$cqpfolder = $settings['cqp']['searchfolder'];
		$cqpcols = array_keys($settings['cqp']['pattributes']);

	if ( !is_array($settings['cqp']['sattributes']['text']) ) { 
		$settings['cqp']['sattributes']['text'] = $settings['cqp']['sattributes'];
		$settings['cqp']['sattributes']['text']['display'] = "Document search";
		$settings['cqp']['sattributes']['text']['key'] = "text";
		$settings['cqp']['sattributes']['text']['level'] = "text";
	};	
		
		# We always need the ID of the text;
		if ( !$settings['cqp']['sattributes']['text']['key'] ) 
			$settings['cqp']['sattributes']['text']['key'] = 'text';		
		
		$script = "open FILE, \">tmp/recqp.pid\";";
		$script .= "$\ = \"\\n\";";
		
		$script .= "\n\nprint FILE 'Regeneration started on '.localtime();";
		$script .= "\nprint FILE 'Process id: '.\$\$;";
		$script .= "\nprint FILE 'CQP Corpus: $cqpcorpus';";
				
		# Remove the old files (otherwise CQP gets confused)
		$cmd = "/bin/rm -f $thisdir/cqp/*";
		
		$script .= "\nprint FILE 'Removing the old files';";
		$script .= "\nprint FILE 'command:\n$cmd';";
		$script .= "\n`$cmd`;";
	
		if ( $cqpfolder == "" ) $cqpfolder = "**";
	
		$ttencode = findapp("tt-cwb-encode");
		if ( !$settings['cqp']['verticalize'] && $ttencode ) {
			# Verticalize using the verticalize C++ application
			# For simplicity, we do htmldecoding externally in Perl
			$maintext .= "<p>Using tt-cwb-encode";
			$cmd = "$ttencode -r $registryfolder/";
			$script .= "\n\nprint FILE '----------------------';";
			$script .= "\nprint FILE '(1) Encoding the corpus';";
			$script .= "\nprint FILE 'command:\n$cmd';";
			$script .= "\n`$cmd`;";
		} else {
			if ( substr($ttroot,0,1) == "/" ) { $scrt = $ttroot; } else { $scrt = "{$thisdir}/$ttroot/common"; };
			if ( $settings['cqp']['verticalize']['type'] != "xslt" && file_exists("Resources/verticalize.xslt") ) {
				# This should become a perl script or something - for which nothing is needed
				$maintext .= "<p>Using verticalize";
				$cmd = "$ttroot/bin/verticalize | perl $scrt/common/Scripts/htmldecode.pl > $thisdir/cqp/corpus.vrt";
			} else {
				$maintext .= "<p>Using XSLT";
				$xsltfile = $settings['cqp']['verticalize']['cmd'] or $xsltfile = "$thisdir/Resources/verticalize.xslt";
			
				# Verticalize using the verticalization XSLT transformation
				$cmd = "/usr/bin/which xsltproc"; 
				$pxslt = $settings['bin']['xsltproc']['path'];
				if ( !$pxslt ) {
					$pxslt = shell_exec($cmd); 
					if ( $pxslt == "" ) { print "<p>Error: xsltproc not found - no response from `$cmd`"; exit; };
					$pxslt = chop($pxslt);
				};
				foreach ( explode ( " ", $cqpfolder ) as $todofolder ) {
					$folderlist .= " $thisdir/xmlfiles/$todofolder ";
				};
		
				# We need to dedoce entities for in case there are any
				$cmd = "$pxslt --novalid $xsltfile $folderlist | perl $scrt/common/Scripts/htmldecode.pl > $thisdir/cqp/corpus.vrt";
			};
		
			$script .= "\n\nprint FILE '----------------------';";
			$script .= "\nprint FILE '(1) Verticalizing the corpus';";
			$script .= "\nprint FILE 'command:\n$cmd';";
			$script .= "\n`$cmd`;";
		
			# Encode the corpus with all the required fields
			$cmd = "export PATH=$PATH:/usr/local/bin; /usr/bin/which cwb-encode"; $pxenc = chop(shell_exec($cmd)); if ( !$pxenc ) { print "<p>Error: cwb-encode not found"; exit; };
			foreach ( $cqpcols as $val ) { $poscols .= " -P $val "; };
			foreach ( $settings['cqp']['sattributes'] as $xatt ) {
				$xkey = $xatt['key'];
				$pattlist .= " -S $xkey:0+id";
				foreach ( $xatt as $key => $val ) { 
					if ( substr($key,0,4) != "fld-" && $key != "key" && $key != "level" && $key != "display" ) $pattlist .= "+$key"; 
				};
			};
			$cmd = "$pxenc -d $thisdir/cqp -c utf8 -f $thisdir/cqp/corpus.vrt -R $registryfolder".strtolower($cqpcorpus)." -P id $poscols $pattlist";

			$script .= "\n\nprint FILE '----------------------';";
			$script .= "\nprint FILE '(2) Encoding the corpus';";
			$script .= "\nprint FILE ' - Structural attributes on <text>: $textfields -  Positional attributes: $poscols';";
			$script .= "\nprint FILE 'command:\n$cmd';";
			$script .= "\n`$cmd`;";
		};
			
		# Create the actual CQP corpus
		$cmd = "export PATH=$PATH:/usr/local/bin; /usr/bin/which cwb-makeall "; $pxmal = chop(shell_exec($cmd)); if ( !$pxmal) { print "<p>Error: cwb-makeall not found"; exit; };
		$cmd = "$pxmal  -r $registryfolder $cqpcorpus";

		$script .= "\n\nprint FILE '----------------------';";
		$script .= "\nprint FILE '(3) Creating the corpus';";
		$script .= "\nprint FILE 'command:\n$cmd';";
		$script .= "\n`$cmd`;";

		$script .= "\n\nprint FILE '----------------------';";
		$script .= "\nprint FILE 'Regeneration completed on '.localtime();";
		$script .= "\n`mv tmp/recqp.pid tmp/recqp.log`;";

		$script .= "\nclose FILE;";

		
		file_put_contents("Scripts/recqp.pl", $script);

		$maintext .= "<p>Script created - click <a href='index.php?action=$action&act=$act'>here</a> to generate the corpus.";

};