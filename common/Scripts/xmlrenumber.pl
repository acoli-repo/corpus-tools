use Getopt::Long;
use XML::LibXML;
 
 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'verbose' => \$verbose, # debugging mode
            'override' => \$override, # do no renumber existing IDs
            'test' => \$test, # tokenize to string, do not change the database
            'filename=s' => \$filename, # language of input
            'mtxtelem=s' => \$mtxtelem, # language of input
            'xx=s' => \$xx, # custom items to number
            'thisdir=s' => \$thisdir, # determine where we are running from
            'emptyatt=s' => \$emptyatt, # attribute to use for empty sentences
            );

	if ( $filename eq '' ) { $filename = shift; };
	if ( $filename eq '' ) { print "No filename indicated"; exit; };
	if ( !-e $filename  ) { print "$filename does not exist"; exit; };

	if ( $debug ) { $verbose = 1; };

	$parser = XML::LibXML->new(); $doc = ""; 
	eval {
		$tmpdoc = $parser->load_xml(location => $filename, load_ext_dtd => 0 );
	};
	if ( !$tmpdoc ) { 
		print "Unable to parse\n"; print $@;
		exit;
	};
	$tmpdoc->setEncoding('UTF-8');


	# Define default namespace as "tei" - does still not parse
	#my $context = XML::LibXML::XPathContext->new( $tmpdoc->documentElement() );
	#my $ns = ( $tmpdoc->documentElement()->getNamespaces() )[0]->getValue();
	#$context->registerNs( 'tei' => $ns );
	
	if ( $mtxtelem eq '' ) { $mtxtelem = "//text"; }; # Do we want this?
	if ( $mtxtelem ne '' && $mtxtelem !~ /\// ) { $mtxtelem = "//$mtxtelem"; };


	# Find the last token number
	$max = 0; 
	foreach $ttnode ($tmpdoc->findnodes("//tok")) {
		$tid = 	$ttnode->getAttribute('id')."";
		if ( $idlist{$tid} && $tid ) {
			print "Found duplicate node: $tid\n";
			$ttnode->setAttribute('torenum', '1');
		};
		$idlist{$tid} = $ttnode;
		if ( $tid =~ /^w-(\d+)$/ ) {
			if ( $1 > $max ) { $max = $1;};
		};
	};
		
	# Number the tokens
	if ( !$override ) {
		$cnt = $max + 1;
	} else {
		$cnt = 1;
	};
	
	
	if ( !$override ) {
		foreach $ttnode ($tmpdoc->findnodes("//tok[\@torenum]")) {
			$tid = 	$ttnode->getAttribute('id')."";
			$newtid = "w-".$cnt++;
			$ttnode->setAttribute('id', $newtid);
			$ttnode->removeAttribute('torenum');
			print "Renumbering duplicate node $tid to $newtid\n";
		};
	};
	
	if ( $debug ) { print "Finding toks : //tok\n"; };
	foreach $ttnode ( $tmpdoc->findnodes("$mtxtelem//tok") ) {
		if ( $ttnode->getAttribute('id') eq '' || $override ) {	
			if ( $debug ) { print "\nRenumbering to w-$cnt"; };
			$ttnode->setAttribute('id', "w-$cnt");
			$cnt++;
		};
		$dcnt = 0; $tokid = $ttnode->getAttribute("id"); $tokid =~ s/w-//;
		if ( $debug ) { print "\n- $cnt\t".$ttnode->textContent; };
		foreach $ddnode ( $ttnode->findnodes("./dtok") ) {
			$dcnt++;
			if ( $debug ) { print "\n  - $dcnt\t".$ddnode->getAttribute('form'); };
			$ddnode->setAttribute('id', "d-$tokid-$dcnt");
		};
		$dcnt = 0;
		foreach $mnode ( $ttnode->findnodes("./m") ) {
			$dcnt++;
			if ( $debug ) { print "\n  - $dcnt\t".$mnode->getAttribute('form'); };
			$mnode->setAttribute('id', "m-$tokid-$dcnt");
		};
	}; 
	if ( $verbose ) { print "\n - number of tokens: $cnt\n"; };


	# Number the paragraphs
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//p")) {
		addid($ttnode, "p", ++$cnt);
	}; 
	
	# Number the mtoks
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//mtok")) {
		addid($ttnode, "m", ++$cnt);
	}; 
	
	# In case things have ended up as tei_div - rename
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//tei_div")) {
		$ttnode->setName('div');
	}; 
	
	# Number the divs
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//div")) {
		addid($ttnode, "div", ++$cnt);
	}; 
	
	# Number the sentences
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//s | $mtxtelem//l")) {
		addid($ttnode, "s", ++$cnt);
	}; 

	# In case we have empty, unnumbered sentences
	if ( !$emptyatt ) { $emptyatt = "sameAs"; };
	if ( $tmpdoc->findnodes("//s[not(.//tok) and not(\@$emptyatt)]") ) {
		if ( $verbose ) { print "Adding tokens explicitly to sentences"; };
		foreach $tok ( $tmpdoc->findnodes("$mtxtelem//tok" ) ) {
			$tokid = $tok->getAttribute("id");
			$tmp = $tok->findnodes("./ancestor::s");
			if ( $tmp ) { 
				$sid = $tmp->item(0)->getAttribute("id");
				if ( 1==1 ) { print "$tokid => $sid"; };
				$s2tok{$sid} .= "#$tokid ";
			} else {
				print $tmp->item(0)->toString;
				print "\nNo sent found for $tokid: ".$tok->parentNode->toString;
			};
		};

		foreach $snt ( $tmpdoc->findnodes("$mtxtelem//s" ) ) {
			$sid = $snt->getAttribute("id");
			$stoks = $s2tok{$sid}; $stoks =~ s/ +$//;
			if ( $stoks ) { $snt->setAttribute($emptyatt, $stoks); };
		};
	};
		
	# Number the utterances
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//u")) {
		$ttnode->setAttribute('id', "u", ++$cnt);
	}; 
	
	# Number the breaks and other empty elements
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//pb | $mtxtelem//lb | $mtxtelem//cb | $mtxtelem//gap | $mtxtelem//deco | $mtxtelem//milestone")) {
		addid($ttnode, "e", ++$cnt);
	}; 
	
	# Number the footnotes
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//note")) {
		addid($ttnode, "ftn", ++$cnt);
	}; 
	
	# Number the footnotes
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//anon")) {
		addid($ttnode, "an", ++$cnt);
	}; 
	
	# Number the critical elements
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//app")) {
		$ttnode->setAttribute('id', "app", ++$cnt);
	}; 
	
	# Number the named entities
	$cnt = 1;
	foreach $nerelm ( split(",", "term,placeName,persName,orgName,name,ner,ne") ) {
		foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//$nerelm")) {
			addid($ttnode, "ner", ++$cnt)
		}; 
	};
		
	# Number any custom defined items
	if ( $xx ne '' ) {
		$cnt = 0;
		foreach $tmp ( split(",", $xx) ) {
			if ( $debug ) { print "Finding custom element : $mtxtelem//$tmp\n"; };
			foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//$tmp")) {
				addid($ttnode, "xx", ++$cnt)
			}; 
		};
	};
	
	$teitext = $tmpdoc->toString;
			
print "\n-- renumbering complete\n";
if ( $test ) {
	# binmode ( STDOUT, ":utf8" );
	print $teitext;
} else {
	if ( $debug ) { print $teitext; };
	open FILE, ">$filename" or die ("no such file: $filename");
	# binmode ( FILE, ":utf8" );
	print FILE $teitext;
	close FILE;
};

sub addid($xnode, $newid, $newcnt) { 
	( $xnode, $newid, $newcnt ) = @_; 
	$oldid = $xnode->getAttribute('id');
	if ( $newcnt && ( !$oldid || $override || $oldid eq "torenew" ) ) { 
		$tmp = $newid.'-'.$newcnt;
		# make sure IDs are unique
		if ( !$override ) {
			while ( $tmpdoc->findnodes("//*[\@id=\"$tmp\"]") ) { 
				$tmp = $newid.'-'.++$newcnt;
			};
		};
		$newid = $tmp;
	}
	if ( $oldid eq "torenew" ) { $oldid = ""; $gotoid = $newid; print "NEWID: $gotoid"; };
	if ( !$oldid || $override ) {
		if ( $debug ) { print "Setting ID to $newid\n"; };
		$xnode->setAttribute('id', $newid);
	};
};