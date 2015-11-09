use Getopt::Long;

 GetOptions ( ## Command line options
            'debug' => \$debug, # debugging mode
            'test' => \$test, # tokenize to string, do not change the database
            'filename=s' => \$filename, # language of input
            'encoding=s' => \$encoding, # language of input
            );


if ( !$filename ) { $filename = shift; };
if ( !$filename ) { $filename = "utf8";; };


$/ = undef; # $\ = "\n"; $, = "\t";

open FILE, $filename;
binmode ( FILE , ":$encoding" );
$psd = <FILE>;
close FILE;

print "<TEI>";
$level = 0;
foreach $char ( split ( //, $psd ) ) {
	$c++; 
	if ( $char eq '(' ) {
		if ( $level > 0 ) {
			$level++;
			$rawxml .=  "\n<eTree>\n";
		} else {
			$level++;
			$rawxml .=  "<forest>";
		};
	} elsif ( $char eq ')' ) {
		if ( !$level ) {
			print "Error on char $c: Out of tree"; exit;
		} elsif ( $level == 1 ) {
			$level--;
			$rawxml .= "\n</forest>";
			printtree($rawxml); $rawxml = "";
			print "\n\n";
		} else {
			$level--;
			$rawxml .= "\n</eTree>";
		};
	} else {
		$rawxml .= $char;
	};
};
print "</TEI>";

sub printtree ( $xml ) {
	$xml = @_[0];
	
	if ( $xml =~ /\nID ([^\n,]+),([^\n,]+)\n/ ) { 
		$file = $1; $loc = $2; 
		if ( $loc =~ /\.(.*?)$/ ) { $num = $1; };
		$xml =~ s/\nID ([^\n,]+),([^\n,]+)\n//;
		$xml =~ s/<forest>/<forest forestID="$num" File="$file" Location="$loc">/;
	};
	
	$xml =~ s/(\s*\n\s*)+/\n/g;
	$xml =~ s/<eTree>\n([^< ]+) /<eTree Label="\1">\n/g;
	$xml =~ s/\n([^\n<>]+)\n/\n<eLeaf Text="\1"\/>\n/g;
	$xml =~ s/<eTree><\/eTree>\n//g;
	
	print $xml; 
};