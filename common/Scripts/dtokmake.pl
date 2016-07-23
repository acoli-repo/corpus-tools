# Script to check whether there are any "redundant" forms
# Where the explicit value matches the inherited value

use XML::LibXML;
$filename = shift;

$\ = "\n"; $, = "\t";

binmode (STDOUT, ":utf8" );

$parser = XML::LibXML->new();
eval {
	$xml = $parser->load_xml(location => $filename);
};

if ( !$xml ) {
	print "Unable to parse $filename";
	exit;
};

# Remove CONTR from @pos
foreach $ttnode ($xml->findnodes("//tok[\@pos='CONTR']")) {
	$ttnode->removeAttribute("pos");
};

foreach $ttnode ($xml->findnodes("//tok[contains(\@pos,'+')]")) {
	
	@parts = split ( "[+]", $ttnode->getAttribute("pos") );
	$num = scalar @parts;

	$tokform = $ttnode->getAttribute("form") or $tokform = $ttnode->textContent;

	print $ttnode->getAttribute("id"), $ttnode->getAttribute("pos"), $tokform;

	for ( $i=0; $i<$num; $i++ ) {
		$newchild = XML::LibXML::Element->new( "dtok" );
		$ttnode->addChild($newchild);
	
		%plusatts = [];
		foreach $att ( keys %$ttnode ) {
			$val = $ttnode->getAttribute($att);
			if ( $val =~ /\+/ ) {
				@parts = split("[+]", $val);
				$newchild->setAttribute($att, $parts[$i]);
				$plusatts{$att}++;
				
			};
		};
		
		# Set the form to the tokform when there is no dtok form
		if ( !$newchild->getAttribute("form") ) {
			$newchild->setAttribute("form", "#".$tokform );
		};
		
		# Remove the plus nodes from the tok
		foreach $att ( keys %plusatts ) {
			$ttnode->removeAttribute($att);
		};

		print $newchild->toString; 
		
	};

	print;
};

	open FILE, ">$filename" or die ("no such file: $filename");
	# binmode ( FILE, ":utf8" );
	print FILE $xml->toString;
	close FILE;


