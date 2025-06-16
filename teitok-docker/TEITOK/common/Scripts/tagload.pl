use Getopt::Long;
use XML::LibXML;
 
 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'force' => \$force, # tag even if already tagged
            'test' => \$test, # tokenize to string, do not change the database
            'xmlfile=s' => \$xmlfile, # language of input
            'tagfile=s' => \$tagfile, # determine where we are running from
            );

	$parser = XML::LibXML->new(); $doc = ""; 
	eval {
		$doc = $parser->load_xml(location => $xmlfile, load_ext_dtd => 0);
	};
	if ( !$doc ) { print "Unable to parse $xmlfile\n"; exit; };
	$doc->setEncoding('UTF-8');

$\ = "\n"; $, = "\t";
open FILE, $tagfile;

# For speed, we need to load all toks first
foreach $token ( $doc->findnodes("//tok") ) {
	$tokid = $token->getAttribute('id');
	$toklist{$tokid} = $token;
};


while ( <FILE> ) {
	chop;
	( $wordid, $word, $tag, $lemma, $source ) = split ( "\t" );
	if ( $wordid eq "- dtok" ) {
		$tokid = $token->getAttribute('id');
		# First check if there are no DTOK in WORDID yet
		@result = $token->findnodes("dtok"); 
		if ( !@result || $made{$tokid} ) {
			print " - adding dtok: $tag, $lemma";
			$dtok = XML::LibXML::Element->new( 'dtok' );
			$token->addChild($dtok);
			$dtok->setAttribute('lemma', $lemma);
			$dtok->setAttribute('pos', $tag);
		
			$made{$tokid} = 1;
		} else {
			print " - {$token['id']} already has a dtok";
		};
		
	} elsif ( $wordid ) {
		print "$wordid  ($word) => $tag, $lemma";
		$token = $toklist{$wordid}; # print_r($token); exit;
		if ($token) { $tagcnt++; } else { print "<p> - token not found"; };
		
		$token->setAttribute('lemma', $lemma);			
		$token->setAttribute('pos', $tag);
		
	};
};

$teitext = $doc->toString;
		
			
print "-- adding tags complete\n";
if ( $debug ) {
	# binmode ( STDOUT, ":utf8" );
	print $teitext;
} else {
	open FILE, ">$xmlfile" or die ("no such file: $xmlfile");
	# binmode ( FILE, ":utf8" );
	print FILE $teitext;
	close FILE;
};