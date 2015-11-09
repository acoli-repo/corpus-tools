$\ = "\n";
$fldr = shift;
$scriptname = $0;

( $rns = $scriptname ) =~ s/renumall/xmlrenumber/;

# print $fldr;
while ( <$fldr/*> ) {
	$fn = $_;
	$cmd = "perl $rns --filename=$fn";
	print $fn;
	`$cmd`;
};