use Getopt::Long;
$\ = "\n"; $, = "\t";

GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # test mode
            'corpussize=f' => \$corpussize, # Corpus size
            'selsize=f' => \$size, # Observed frequency of the base word
            'span=f' => \$span, # Size of the span
            'fldname=s' => \$fldname, # Name of the field used for colloation
            );

print "[[{label: '$fldname'}, {label: 'Observed'}, {label: 'Total'}, {label: 'Expected'}, {label: 'Χ2'}, {label: 'MI'}], ";

if ( !$span ) { $span = 1;};

if ( !$size || !$corpussize ) {
	print "No size or corpussize given"; exit;
};

while ( <> ) {
	chomp; 
	($fld, $obs, $tot) = split(" "); 
	if ( $fld eq '' ) { continue; };
	$exp = $size*($tot/$corpussize)*$span; 
	$x2 = ($obs-$exp)**2/$exp; 
	$mi = log( ($obs * $corpussize) / ( $size * $tot * $span ) ) / log(2);
	print "[\"$fld\", $obs, $tot, $exp, $x2, $mi],";
};
print "]";