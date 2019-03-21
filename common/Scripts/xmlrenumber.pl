# use encoding 'utf8';
use Getopt::Long;
use XML::LibXML;
 
 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'override' => \$override, # do no renumber existing IDs
            'test' => \$test, # tokenize to string, do not change the database
            'filename=s' => \$filename, # language of input
            'mtxtelem=s' => \$mtxtelem, # language of input
            'thisdir=s' => \$thisdir, # determine where we are running from
            );

	if ( $filename eq '' ) { $filename = shift; };
	if ( $filename eq '' ) { print "No filename indicated"; exit; };
	if ( !-e $filename  ) { print "$filename does not exist"; exit; };

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
		$tid = 	$ttnode->getAttribute('id');
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
	if ( $debug ) { print "Finding toks : //tok\n"; };
	foreach $ttnode ($tmpdoc->findnodes("//tok")) {
		print "\nID: ".$ttnode->getAttribute('id'); 
		if ( $ttnode->getAttribute('id') eq '' || $override ) {	
			print "Renumbering to w-$cnt";
			$ttnode->setAttribute('id', "w-$cnt");
			$cnt++;
		};
		$dcnt = 0;
		if ( $debug ) { print "\n- $cnt\t".$ttnode->textContent; };
		foreach $ddnode ( $ttnode->findnodes("dtok") ) {
			$dcnt++;
			if ( $debug ) { print "\n  - $dcnt\t".$ddnode->getAttribute('form'); };
			$ddnode->setAttribute('id', "d-$cnt-$dcnt");
		};
		$dcnt = 0;
		foreach $ddnode ( $ttnode->findnodes("morph") ) {
			$dcnt++;
			if ( $debug ) { print "\n  - $dcnt\t".$ddnode->getAttribute('form'); };
			$ddnode->setAttribute('id', "dm-$cnt-$dcnt");
		};
	}; 
	# warn " - number of tokens: $cnt\n";

	# Number the paragraphs
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//p")) {
		$cnt++;
		$ttnode->setAttribute('id', "p-$cnt");
	}; 
	
	# Number the mtoks
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//mtok")) {
		$cnt++;
		$ttnode->setAttribute('id', "m-$cnt");
	}; 
	
	# Number the paragraphs
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//div")) {
		$cnt++;
		$ttnode->setAttribute('id', "div-$cnt");
	}; 
	
	# Number the sentences
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//s | $mtxtelem//l")) {
		$cnt++;
		$ttnode->setAttribute('id', "s-$cnt");
	}; 
	
	# Number the utterances
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//u")) {
		$cnt++;
		$ttnode->setAttribute('id', "u-$cnt");
	}; 
	
	# Number the breaks and other empty elements
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//pb | $mtxtelem//lb | $mtxtelem//cb | $mtxtelem//gap | $mtxtelem//deco | $mtxtelem//milestone")) {
		$cnt++;
		$ttnode->setAttribute('id', "e-$cnt");
	}; 
	
	# Number the footnotes
	$cnt = 0;
	if ( $debug ) { print "Finding toks : $mtxtelem//note\n"; };
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//note")) {
		$cnt++;
		$ttnode->setAttribute('id', "ftn-$cnt");
	}; 
	
	# Number the footnotes
	$cnt = 0;
	if ( $debug ) { print "Finding anon : $mtxtelem//anon\n"; };
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//anon")) {
		$cnt++;
		$ttnode->setAttribute('id', "an-$cnt");
	}; 
	
	# Number the critical elements
	$cnt = 0;
	foreach $ttnode ($tmpdoc->findnodes("$mtxtelem//app")) {
		$cnt++;
		$ttnode->setAttribute('id', "app-$cnt");
	}; 
	
	$teitext = $tmpdoc->toString;
		
			
print "\n-- renumbering complete\n";
if ( $debug ) {
	# binmode ( STDOUT, ":utf8" );
	print $teitext;
} else {
	open FILE, ">$filename" or die ("no such file: $filename");
	# binmode ( FILE, ":utf8" );
	print FILE $teitext;
	close FILE;
};