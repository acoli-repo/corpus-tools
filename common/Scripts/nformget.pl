use XML::LibXML;
$\ = "\n"; $, = "\t";
binmode ( STDOUT, ":utf8" );

$treatfile = shift;
if ( $treatfile ne '' ) {
	treatfile($treatfile);
} else {
	foreach $file ( glob ( "xmlfiles/*.xml" ) ) {
		treatfile($file);
	};
	foreach $file ( glob ( "xmlfiles/*/*.xml" ) ) {
		treatfile($file);
	};
};

sub treatfile ( $file ) {
	$file = @_[0];
	
	$parser = XML::LibXML->new();
	eval {
		$doc = $parser->load_xml(location => $file);
	};
	if ( !$doc ) {
		print " -- failed to read: $file";
		return -1;
	};
	foreach $ttnode ($doc->findnodes("//tok")) {
		$form = $ttnode->getAttribute('form');
		$nform = $ttnode->getAttribute('nform');
		$pform = $ttnode->textContent;
		if ( $form ne '' ) { $wform = $form; } else { $wform = $pform; };
		
		if ( $nform ne $wform && $nform ne '' ) {
			print $wform, $nform;
		};
	};


};