use Getopt::Long;
use XML::LibXML;
use utf8;

$\ = "\n"; $, = "\t";
binmode(STDOUT, ":utf8");

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'force' => \$force, # tag even if already tagged
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

$parser = XML::LibXML->new(); 
eval {
	$tmpdoc = $parser->load_xml(location => $filename, load_ext_dtd => 0);
};
if ( !$tmpdoc ) { print " - Unable to parse XML file"; exit; };
$tmpdoc->setEncoding('UTF-8');

$settingsxml = $parser->load_xml(location => "Resources/settings.xml", load_ext_dtd => 0);
if ( !$settingsxml ) { print " - Unable to parse settings file"; exit; };

foreach $node ( $settingsxml->findnodes('//input/simplify/item') ) { 
	$from = $node->getAttribute('key');
	$to = $node->getAttribute('value');
	
	if ( $from ne '' ) { 
		$simpl{$from.''} = $to; 
	};
};


foreach $node ( $tmpdoc->findnodes('//tok') ) { 
	$form = $node->getAttribute('form');
	if ( $form eq '' ) { $form = $node->textContent(); };
	$oform = $form;

	while ( ( $from, $to ) = each ( %simpl ) ) {
		$form =~ s/\Q$from\E/$to/g;
	};
	
	if ( $form ne $oform ) {
		if ( $debug ) { print $node->getAttribute('id'), $oform, $form; };
		$node->setAttribute('form', $form)
	};
	

};
exit;

open FILE, ">$filename";
print FILE $tmpdoc->toString;
close FILE;