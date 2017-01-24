# Script to automatically change <tok> with a + in @pos 
# into the corresponding <dtok>

use Getopt::Long;
use XML::LibXML;

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'pos=s' => \$pos, # the form used for detecting split tokens
            'file' => \$filename, # the form used for detecting split tokens
            'forceform' => \$forceform, # whether or not to put probs in the XML
            );

$\ = "\n"; $, = "\t";

if ( $pos eq '' ) { $pos = "pos"; };

binmode (STDOUT, ":utf8" );

if ( !$filename ) {
	$filename = shift;
};

if ( !$filename ) {
	print "Usage: perl dtokmake.pl [options] file

options:
--debug		verbose mode
--pos=xxx  	tag used for detecting splits
--forceform 	when creating dtok without a form, add # + tokform as dtokform
";
	exit;
};

$parser = XML::LibXML->new();
eval {
	$xml = $parser->load_xml(location => $filename);
};



if ( !$xml ) {
	print "Unable to parse $filename";
	exit;
};

# Remove CONTR from @pos
foreach $ttnode ($xml->findnodes("//tok[\@$pos='CONTR']")) {
	$ttnode->removeAttribute($pos);
};

foreach $ttnode ($xml->findnodes("//tok[contains(\@$pos,'+')]")) {
	
	@parts = split ( "[+]", $ttnode->getAttribute($pos) );
	$num = scalar @parts;

	$tokform = $ttnode->getAttribute("form") or $tokform = $ttnode->textContent;

	$id = $ttnode->getAttribute("id");

	print $id, $ttnode->getAttribute($pos), $tokform;

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
		if ( !$newchild->getAttribute("form") && $forceform ) {
			$newchild->setAttribute("form", "#".$tokform );
		};
		
		( $newid = $id."-".($i+1) ) =~ s/w-/d-/;
		$newchild->setAttribute("id", $newid );

		print $newchild->toString; 
		
	};
	# Remove the plus nodes from the tok
	foreach $att ( keys %plusatts ) {
		$ttnode->removeAttribute($att);
	};

	print;
};

	open FILE, ">$filename" or die ("unable to write file: $filename");
	# binmode ( FILE, ":utf8" );
	print FILE $xml->toString;
	close FILE;

	$scriptname = $0;
	( $renum = $scriptname ) =~ s/dtokmake/xmlrenumber/;

	# Finally, run the renumber command over the same file
	if ( -e $renum ) {
		$cmd = "/usr/bin/perl $renum --filename=$filename";
		`$cmd`;
	};
